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
     * dengan tagihan SPP siswa via 6 digit belakang VA — TANPA menulis apa pun ke DB.
     * Dipakai utk pratinjau: bendahara meninjau (terima saran otomatis apa adanya,
     * atau ganti manual per baris) sebelum benar-benar diterapkan lewat applyRekeningKoran().
     *
     * Saran otomatis dicari dari tagihan siswa yg belum lunas dgn NOMINAL PERSIS SAMA,
     * diambil yg jatuh temponya paling awal (pelunasan tunggakan lama duluan) — tapi
     * `opsi` tetap membawa SEMUA tagihan belum lunas siswa itu, supaya bendahara bisa
     * pilih bulan lain kalau saran otomatisnya salah.
     *
     * @param  array<int, array{no_pelanggan:string, nominal:int, tanggal:Carbon, waktu:string, lokasi:string, baris_asli:string}>  $transaksi
     * @return array<int, array{
     *     no_pelanggan:string, nominal:int, tanggal:string, siswa:?Siswa,
     *     status:string, pesan:string, saran_pembayaran_uuid:?string, opsi:Collection<int,SppPembayaran>
     * }>
     */
    public function previewRekeningKoran(array $transaksi): array
    {
        $siswaByVa = [];
        $vaGanda   = [];
        foreach (Siswa::whereNotNull('va')->where('va', '!=', '')->get(['uuid', 'nama', 'va', 'id_kelas']) as $s) {
            $suffix = substr(preg_replace('/\D/', '', (string) $s->va), -6);
            if ($suffix === '' || strlen($suffix) < 6) {
                continue;
            }
            if (isset($siswaByVa[$suffix])) {
                $vaGanda[$suffix] = true;
            }
            $siswaByVa[$suffix] = $s;
        }

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
                'opsi'                  => collect(),
            ];

            if (isset($vaGanda[$suffix])) {
                $row['status'] = 'va_ganda';
                $row['pesan'] = "VA {$suffix} dipakai lebih dari satu siswa — perbaiki data VA dulu.";
                $preview[] = $row;
                continue;
            }

            $siswa = $siswaByVa[$suffix] ?? null;
            if (!$siswa) {
                $preview[] = $row;
                continue;
            }
            $row['siswa'] = $siswa;

            $opsi = SppPembayaran::where('id_siswa', $siswa->uuid)
                ->whereIn('status', [
                    SppPembayaran::STATUS_TERVERIFIKASI,
                    SppPembayaran::STATUS_BELUM,
                    SppPembayaran::STATUS_MENUNGGU,
                    SppPembayaran::STATUS_DITOLAK,
                ])
                ->get()
                ->sortBy(fn ($p) => [TahunAjaran::tahunAwal($p->tahun_ajaran), $p->bulan <= 6 ? $p->bulan + 12 : $p->bulan])
                ->values();
            $row['opsi'] = $opsi;

            $sarankan = $opsi->firstWhere('nominal', $t['nominal']);
            if ($sarankan) {
                $row['status'] = 'saran_otomatis';
                $row['pesan'] = "Cocok dengan {$sarankan->label_bulan} — Rp " . number_format($sarankan->nominal, 0, ',', '.') . '.';
                $row['saran_pembayaran_uuid'] = $sarankan->uuid;
            } elseif ($opsi->isNotEmpty()) {
                $row['status'] = 'perlu_pilih_manual';
                $row['pesan'] = 'Tidak ada tagihan senilai persis Rp ' . number_format($t['nominal'], 0, ',', '.') . ' — pilih bulan manual.';
            } else {
                $row['status'] = 'tidak_ada_tagihan';
                $row['pesan'] = "{$siswa->nama}: semua tagihan sudah lunas / belum ada baris tagihan aktif.";
            }

            $preview[] = $row;
        }

        return $preview;
    }

    /**
     * Terapkan keputusan hasil pratinjau (baik saran otomatis yg diterima apa adanya,
     * maupun pilihan manual bendahara) — SATU-SATUNYA titik yg benar-benar menulis ke DB
     * utk alur import rekening koran. Baris yg SppPembayaran-nya sudah lunas duluan
     * (mis. file yg sama diproses dua kali) dilewati, bukan ditimpa.
     *
     * @param  array<int, array{pembayaran_uuid:string, nominal:int, tanggal_bayar:string}>  $keputusan
     * @return array<int, array{pesan:string, berhasil:bool}>
     */
    public function applyRekeningKoran(array $keputusan, ?string $actorUuid): array
    {
        $hasil = [];

        DB::transaction(function () use ($keputusan, $actorUuid, &$hasil) {
            foreach ($keputusan as $k) {
                $p = SppPembayaran::with('siswa')->find($k['pembayaran_uuid']);
                if (!$p) {
                    $hasil[] = ['pesan' => 'Satu baris tidak ditemukan (mungkin sudah dihapus) — dilewati.', 'berhasil' => false];
                    continue;
                }
                if ($p->status === SppPembayaran::STATUS_LUNAS) {
                    $hasil[] = ['pesan' => ($p->siswa->nama ?? '-') . ": {$p->label_bulan} sudah lunas sebelumnya — dilewati.", 'berhasil' => false];
                    continue;
                }

                $p->status            = SppPembayaran::STATUS_LUNAS;
                $p->nominal           = (int) $k['nominal'];
                $p->tanggal_bayar     = $k['tanggal_bayar'];
                $p->bank              = 'BCA';
                $p->catatan           = null;
                $p->diverifikasi_oleh = $actorUuid;
                $p->diverifikasi_pada = now();
                $p->save();

                $hasil[] = ['pesan' => ($p->siswa->nama ?? '-') . ": {$p->label_bulan} ditandai LUNAS.", 'berhasil' => true];
            }
        });

        return $hasil;
    }
}
