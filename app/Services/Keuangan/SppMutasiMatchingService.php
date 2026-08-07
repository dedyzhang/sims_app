<?php

namespace App\Services\Keuangan;

use App\Models\Siswa;
use App\Models\SppPembayaran;
use App\Support\TahunAjaran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Skor pencocokan mutasi rekening ↔ tagihan SPP (Fase B1) — deterministik, bukan LLM.
 */
class SppMutasiMatchingService
{
    /**
     * Pratinjau impor rekening koran dengan skor kecocokan per baris.
     *
     * @param  array<int, array{no_pelanggan:string, nominal:int, tanggal:Carbon, waktu?:string, lokasi?:string, baris_asli?:string}>  $transaksi
     * @return array<int, array<string, mixed>>
     */
    public function preview(array $transaksi): array
    {
        $index = $this->buildVaSuffixIndex();
        $siswaByVa = $index['siswaByVa'];
        $vaGanda = $index['vaGanda'];

        $preview = [];
        foreach ($transaksi as $t) {
            $suffix = substr($t['no_pelanggan'], -6);
            $row = [
                'no_pelanggan'          => $t['no_pelanggan'],
                'nominal'               => $t['nominal'],
                'tanggal'               => $t['tanggal']->toDateString(),
                'siswa'                 => null,
                'status'                => 'va_tidak_ditemukan',
                'pesan'                 => "Tidak ada siswa dengan VA berakhiran {$suffix}.",
                'saran_pembayaran_uuid' => null,
                'skor'                  => 0,
                'alasan_skor'           => [],
                'opsi'                  => collect(),
            ];

            if (isset($vaGanda[$suffix])) {
                $row['status'] = 'va_ganda';
                $row['pesan'] = "VA {$suffix} dipakai lebih dari satu siswa — perbaiki data VA dulu.";
                $preview[] = $row;
                continue;
            }

            $siswa = $siswaByVa[$suffix] ?? null;
            if (! $siswa) {
                $preview[] = $row;
                continue;
            }
            $row['siswa'] = $siswa;

            $opsi = $this->tagihanBelumLunas($siswa);
            $row['opsi'] = $opsi;

            $scored = $opsi
                ->map(fn (SppPembayaran $p) => $this->scoreMatch($t, $p, $siswa))
                ->sortByDesc('skor')
                ->values();

            $terbaik = $scored->first();
            if ($terbaik && $terbaik['skor'] >= (int) config('keuangan-ai.matching.skor_otomatis_min', 70)) {
                $p = $terbaik['pembayaran'];
                $row['status'] = 'saran_otomatis';
                $row['skor'] = $terbaik['skor'];
                $row['alasan_skor'] = $terbaik['alasan'];
                $row['pesan'] = "Skor {$terbaik['skor']} — cocok {$p->label_bulan} · Rp ".number_format($p->nominal, 0, ',', '.').'.';
                $row['saran_pembayaran_uuid'] = $p->uuid;
            } elseif ($opsi->isNotEmpty()) {
                $row['status'] = 'perlu_pilih_manual';
                $row['skor'] = $terbaik['skor'] ?? 0;
                $row['alasan_skor'] = $terbaik['alasan'] ?? [];
                $row['pesan'] = 'Skor terbaik '.($row['skor']).' — pilih bulan manual (nominal bank Rp '.number_format($t['nominal'], 0, ',', '.').').';
            } else {
                $row['status'] = 'tidak_ada_tagihan';
                $row['pesan'] = "{$siswa->nama}: semua tagihan sudah lunas / belum ada baris tagihan aktif.";
            }

            $preview[] = $row;
        }

        return $preview;
    }

    /**
     * @return array{skor:int, alasan:array<string,string>, pembayaran:SppPembayaran}
     */
    public function scoreMatch(array $mutasi, SppPembayaran $pembayaran, ?Siswa $siswa = null): array
    {
        $siswa ??= $pembayaran->siswa;
        $bobot = config('keuangan-ai.matching.bobot', []);
        $alasan = [];

        $suffixMutasi = substr((string) ($mutasi['no_pelanggan'] ?? ''), -6);
        $suffixVa = $siswa ? substr(preg_replace('/\D/', '', (string) $siswa->va), -6) : '';
        $skorVa = ($suffixVa !== '' && $suffixMutasi === $suffixVa) ? 100 : 0;
        if ($skorVa > 0) {
            $alasan['va'] = "VA cocok (…{$suffixVa})";
        }

        $skorNominal = ((int) $mutasi['nominal'] === (int) $pembayaran->nominal) ? 100 : 0;
        if ($skorNominal > 0) {
            $alasan['nominal'] = 'Nominal persis sama';
        }

        $skorTanggal = 0;
        $tglMutasi = $mutasi['tanggal'] instanceof Carbon
            ? $mutasi['tanggal']
            : Carbon::parse($mutasi['tanggal']);
        if ($pembayaran->tanggal_bayar) {
            $selisih = abs($tglMutasi->diffInDays($pembayaran->tanggal_bayar));
            $maxHari = max(1, (int) config('keuangan-ai.matching.tanggal_max_hari', 7));
            $skorTanggal = $selisih <= $maxHari
                ? (int) round(100 * (1 - $selisih / $maxHari))
                : 0;
            if ($skorTanggal > 0) {
                $alasan['tanggal'] = "Tanggal ±{$selisih} hari";
            }
        }

        $skorNama = 0;
        $teksMutasi = Str::lower((string) ($mutasi['baris_asli'] ?? $mutasi['lokasi'] ?? ''));
        if ($siswa && $teksMutasi !== '') {
            $nama = Str::lower($siswa->nama);
            $kata = collect(explode(' ', $nama))->filter(fn ($w) => strlen($w) > 2);
            $cocok = $kata->filter(fn ($w) => str_contains($teksMutasi, $w))->count();
            if ($kata->isNotEmpty()) {
                $skorNama = (int) round(($cocok / $kata->count()) * 100);
            }
            if ($skorNama >= 50) {
                $alasan['nama'] = 'Nama terdeteksi di keterangan bank';
            }
        }

        $totalBobot = array_sum($bobot) ?: 100;
        $skor = (int) round(
            ($skorVa * ($bobot['va'] ?? 40)
                + $skorNominal * ($bobot['nominal'] ?? 35)
                + $skorTanggal * ($bobot['tanggal'] ?? 15)
                + $skorNama * ($bobot['nama'] ?? 10)) / $totalBobot
        );

        return [
            'skor'        => min(100, $skor),
            'alasan'      => $alasan,
            'pembayaran'  => $pembayaran,
        ];
    }

    /**
     * Tagihan terverifikasi yang menunggu validasi rekening koran.
     *
     * @return Collection<int, array{pembayaran:SppPembayaran, skor:int, alasan:array<string,string>}>
     */
    public function antrianValidasiBank(string $tahunAjaran): Collection
    {
        return SppPembayaran::with('siswa.kelas')
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('status', SppPembayaran::STATUS_TERVERIFIKASI)
            ->orderBy('diverifikasi_pada')
            ->get()
            ->map(fn (SppPembayaran $p) => [
                'pembayaran' => $p,
                'skor'       => $this->prioritasValidasi($p),
                'alasan'     => $this->alasanPrioritasValidasi($p),
            ]);
    }

    private function prioritasValidasi(SppPembayaran $p): int
    {
        $usia = $p->diverifikasi_pada
            ? (int) $p->diverifikasi_pada->diffInDays(now())
            : (int) $p->updated_at->diffInDays(now());

        return min(100, 50 + $usia * 5 + min(30, (int) ($p->nominal / 100000)));
    }

    /** @return array<string,string> */
    private function alasanPrioritasValidasi(SppPembayaran $p): array
    {
        $alasan = [];
        if ($p->diverifikasi_pada) {
            $hari = (int) $p->diverifikasi_pada->diffInDays(now());
            if ($hari > 0) {
                $alasan['usia'] = "Menunggu validasi bank {$hari} hari";
            }
        }
        $alasan['nominal'] = 'Rp '.number_format($p->nominal, 0, ',', '.');

        return $alasan;
    }

    /**
     * Satu query siswa ber-VA → indeks suffix + deteksi VA ganda.
     *
     * @return array{siswaByVa: array<string, Siswa>, vaGanda: array<string, int>}
     */
    private function buildVaSuffixIndex(): array
    {
        $siswaByVa = [];
        $counts = [];

        foreach (Siswa::whereNotNull('va')->where('va', '!=', '')->get(['uuid', 'nama', 'va', 'id_kelas']) as $s) {
            $suffix = substr(preg_replace('/\D/', '', (string) $s->va), -6);
            if ($suffix === '' || strlen($suffix) < 6) {
                continue;
            }
            $counts[$suffix] = ($counts[$suffix] ?? 0) + 1;
            $siswaByVa[$suffix] = $s;
        }

        return [
            'siswaByVa' => $siswaByVa,
            'vaGanda'   => array_filter($counts, fn ($c) => $c > 1),
        ];
    }

    /** @return Collection<int, SppPembayaran> */
    private function tagihanBelumLunas(Siswa $siswa): Collection
    {
        return SppPembayaran::where('id_siswa', $siswa->uuid)
            ->whereIn('status', [
                SppPembayaran::STATUS_TERVERIFIKASI,
                SppPembayaran::STATUS_BELUM,
                SppPembayaran::STATUS_MENUNGGU,
                SppPembayaran::STATUS_DITOLAK,
            ])
            ->get()
            ->sortBy(fn ($p) => [TahunAjaran::tahunAwal($p->tahun_ajaran), $p->bulan <= 6 ? $p->bulan + 12 : $p->bulan])
            ->values();
    }
}
