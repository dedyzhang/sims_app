<?php

namespace App\Services\Keuangan;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\SppPembayaran;
use App\Support\TahunAjaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Logika inti pembayaran SPP: memastikan baris 12 bulan ada, menyusun grid
 * per kelas untuk bendahara, dan rekap tagihan per siswa untuk ortu/siswa.
 */
class SppService
{
    /**
     * Pastikan 12 baris bulan (Juli..Juni) ada untuk satu siswa pada tahun
     * ajaran tertentu. Nominal default diambil dari kolom siswa.spp.
     */
    public function ensureRows(Siswa $siswa, string $ta): void
    {
        $existing = SppPembayaran::where('id_siswa', $siswa->uuid)
            ->where('tahun_ajaran', $ta)
            ->pluck('bulan')
            ->all();

        $nominal = (int) preg_replace('/\D/', '', (string) ($siswa->spp ?? '')) ?: 0;

        $missing = [];
        foreach (array_keys(TahunAjaran::BULAN) as $idx) {
            if (!in_array($idx, $existing, true)) {
                $missing[] = [
                    'uuid'         => (string) \Illuminate\Support\Str::uuid(),
                    'id_siswa'     => $siswa->uuid,
                    'tahun_ajaran' => $ta,
                    'bulan'        => $idx,
                    'nominal'      => $nominal,
                    'status'       => SppPembayaran::STATUS_BELUM,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }
        }
        if ($missing) {
            SppPembayaran::insert($missing);
        }
    }

    /**
     * Pastikan baris ada untuk seluruh siswa di satu kelas — SEKALIGUS (2 query total: 1 select
     * + 1 insert bulk), bukan ensureRows() dipanggil per-siswa di dalam loop spt sebelumnya (N+1
     * nyata: /keuangan/kelas/{kelas} terukur 78 query utk kelas berisi puluhan siswa).
     */
    public function ensureRowsForKelas(Kelas $kelas, string $ta): void
    {
        $siswaList = $kelas->siswa()->get(['uuid', 'spp']);
        if ($siswaList->isEmpty()) {
            return;
        }

        $existingByStudent = SppPembayaran::whereIn('id_siswa', $siswaList->pluck('uuid'))
            ->where('tahun_ajaran', $ta)
            ->get(['id_siswa', 'bulan'])
            ->groupBy('id_siswa')
            ->map(fn ($rows) => $rows->pluck('bulan')->all());

        $missing = [];
        foreach ($siswaList as $siswa) {
            $existing = $existingByStudent->get($siswa->uuid, []);
            $nominal = (int) preg_replace('/\D/', '', (string) ($siswa->spp ?? '')) ?: 0;
            foreach (array_keys(TahunAjaran::BULAN) as $idx) {
                if (!in_array($idx, $existing, true)) {
                    $missing[] = [
                        'uuid'         => (string) \Illuminate\Support\Str::uuid(),
                        'id_siswa'     => $siswa->uuid,
                        'tahun_ajaran' => $ta,
                        'bulan'        => $idx,
                        'nominal'      => $nominal,
                        'status'       => SppPembayaran::STATUS_BELUM,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];
                }
            }
        }
        if ($missing) {
            SppPembayaran::insert($missing);
        }
    }

    /**
     * Pembayaran satu siswa untuk satu tahun ajaran, terurut bulan 1..12
     * dan ter-index berdasarkan bulan.
     *
     * @return Collection<int, SppPembayaran>
     */
    public function forSiswa(Siswa $siswa, string $ta): Collection
    {
        $this->ensureRows($siswa, $ta);

        return SppPembayaran::where('id_siswa', $siswa->uuid)
            ->where('tahun_ajaran', $ta)
            ->orderBy('bulan')
            ->get()
            ->keyBy('bulan');
    }

    /**
     * Grid kelas: tiap siswa beserta pembayaran ter-index bulan + ringkasan.
     *
     * @return array{siswa: Siswa, bayar: Collection<int,SppPembayaran>, lunas:int, nominal:int}[]
     */
    public function gridForKelas(Kelas $kelas, string $ta): array
    {
        $this->ensureRowsForKelas($kelas, $ta);

        $siswaList = $kelas->siswa()->get();
        $all = SppPembayaran::whereIn('id_siswa', $siswaList->pluck('uuid'))
            ->where('tahun_ajaran', $ta)
            ->get()
            ->groupBy('id_siswa');

        $rows = [];
        foreach ($siswaList as $siswa) {
            $bayar = ($all[$siswa->uuid] ?? collect())->keyBy('bulan');
            $rows[] = [
                'siswa'   => $siswa,
                'bayar'   => $bayar,
                'lunas'   => $bayar->where('status', SppPembayaran::STATUS_LUNAS)->count(),
                'nominal' => (int) $bayar->where('status', SppPembayaran::STATUS_LUNAS)->sum('nominal'),
            ];
        }
        return $rows;
    }

    /**
     * Ringkasan tagihan satu siswa: total bulan, lunas, menunggu, tunggakan.
     *
     * @param Collection<int,SppPembayaran> $bayar
     * @return array{total:int, lunas:int, menunggu:int, belum:int, tunggakan:int}
     */
    public function ringkasan(Collection $bayar): array
    {
        $belumLengkap = $bayar->whereIn('status', [SppPembayaran::STATUS_BELUM, SppPembayaran::STATUS_DITOLAK]);
        
        $belumSudahTiba = 0;
        $tunggakanNominal = 0;

        foreach ($belumLengkap as $p) {
            $tgl = TahunAjaran::tanggal($p->tahun_ajaran, $p->bulan)->startOfMonth();
            if (!$tgl->isAfter(now()->startOfMonth())) {
                $belumSudahTiba++;
                $tunggakanNominal += $p->nominal;
            }
        }

        return [
            'total'         => $bayar->count(),
            'lunas'         => $bayar->where('status', SppPembayaran::STATUS_LUNAS)->count(),
            'terverifikasi' => $bayar->where('status', SppPembayaran::STATUS_TERVERIFIKASI)->count(),
            'menunggu'      => $bayar->where('status', SppPembayaran::STATUS_MENUNGGU)->count(),
            'belum'         => $belumSudahTiba,
            'tunggakan'     => $tunggakanNominal,
        ];
    }

    /**
     * Cocokkan transaksi rekening koran bank (hasil RekeningKoranBcaParser::parse())
     * dengan tagihan SPP siswa via 6 digit belakang VA, lalu tandai LUNAS otomatis.
     *
     * Pencocokan sengaja MENGABAIKAN bulan dari tanggal transaksi (ortu bisa bayar
     * lebih awal/telat dari jadwal) — baris tagihan siswa yang belum lunas dgn
     * NOMINAL PERSIS SAMA dicari, lalu diambil yang jatuh temponya paling awal
     * (pelunasan tunggakan lama duluan). Kalau nominal tak ada yang cocok persis,
     * SENGAJA tidak ditebak/dipaksakan — ditandai perlu tinjau manual bendahara,
     * krn uang salah bulan/salah siswa sulit dilacak-balik kalau sudah kepalang lunas.
     *
     * @param  array<int, array{no_pelanggan:string, nominal:int, tanggal:Carbon, waktu:string, lokasi:string, baris_asli:string}>  $transaksi
     * @return array<int, array{baris:string, hasil:string, pesan:string}>
     */
    public function importRekeningKoran(array $transaksi, ?string $actorUuid): array
    {
        $siswaByVa = [];
        $vaGanda   = [];
        foreach (Siswa::whereNotNull('va')->where('va', '!=', '')->get(['uuid', 'nama', 'va']) as $s) {
            $suffix = substr(preg_replace('/\D/', '', (string) $s->va), -6);
            if ($suffix === '' || strlen($suffix) < 6) {
                continue;
            }
            if (isset($siswaByVa[$suffix])) {
                $vaGanda[$suffix] = true;
            }
            $siswaByVa[$suffix] = $s;
        }

        $hasil = [];

        DB::transaction(function () use ($transaksi, $siswaByVa, $vaGanda, $actorUuid, &$hasil) {
            foreach ($transaksi as $t) {
                $suffix = substr($t['no_pelanggan'], -6);
                $ringkas = "{$t['no_pelanggan']} · Rp " . number_format($t['nominal'], 0, ',', '.') . " · {$t['tanggal']->format('d/m/Y')}";

                if (isset($vaGanda[$suffix])) {
                    $hasil[] = ['baris' => $ringkas, 'hasil' => 'va_ganda', 'pesan' => "VA {$suffix} dipakai lebih dari satu siswa — perbaiki data VA dulu."];
                    continue;
                }

                $siswa = $siswaByVa[$suffix] ?? null;
                if (!$siswa) {
                    $hasil[] = ['baris' => $ringkas, 'hasil' => 'va_tidak_ditemukan', 'pesan' => "Tidak ada siswa dengan VA berakhiran {$suffix}."];
                    continue;
                }

                $kandidat = SppPembayaran::where('id_siswa', $siswa->uuid)
                    ->whereIn('status', [
                        SppPembayaran::STATUS_TERVERIFIKASI,
                        SppPembayaran::STATUS_BELUM,
                        SppPembayaran::STATUS_MENUNGGU,
                        SppPembayaran::STATUS_DITOLAK,
                    ])
                    ->where('nominal', $t['nominal'])
                    ->get()
                    ->sortBy(fn ($p) => [TahunAjaran::tahunAwal($p->tahun_ajaran), $p->bulan <= 6 ? $p->bulan + 12 : $p->bulan])
                    ->first();

                if (!$kandidat) {
                    $sudahLunas = SppPembayaran::where('id_siswa', $siswa->uuid)
                        ->where('status', SppPembayaran::STATUS_LUNAS)
                        ->where('nominal', $t['nominal'])
                        ->where('tanggal_bayar', $t['tanggal']->toDateString())
                        ->exists();

                    if ($sudahLunas) {
                        $hasil[] = ['baris' => $ringkas, 'hasil' => 'sudah_lunas', 'pesan' => "{$siswa->nama}: transaksi ini sudah pernah diproses sebelumnya."];
                    } else {
                        $hasil[] = ['baris' => $ringkas, 'hasil' => 'nominal_tidak_cocok', 'pesan' => "{$siswa->nama}: tidak ada tagihan belum lunas senilai Rp " . number_format($t['nominal'], 0, ',', '.') . ' — tinjau manual.'];
                    }
                    continue;
                }

                $kandidat->status            = SppPembayaran::STATUS_LUNAS;
                $kandidat->tanggal_bayar     = $t['tanggal'];
                $kandidat->bank              = 'BCA';
                $kandidat->catatan           = null;
                $kandidat->diverifikasi_oleh = $actorUuid;
                $kandidat->diverifikasi_pada = now();
                $kandidat->save();

                $hasil[] = ['baris' => $ringkas, 'hasil' => 'lunas_baru', 'pesan' => "{$siswa->nama}: {$kandidat->label_bulan} ditandai LUNAS."];
            }
        });

        return $hasil;
    }
}
