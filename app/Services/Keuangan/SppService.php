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
        return app(SppMutasiMatchingService::class)->preview($transaksi);
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
        $lunas = collect();

        DB::transaction(function () use ($keputusan, $actorUuid, &$hasil, &$lunas) {
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

                $lunas->push($p);
                $hasil[] = ['pesan' => ($p->siswa->nama ?? '-') . ": {$p->label_bulan} ditandai LUNAS.", 'berhasil' => true];
            }
        });

        SppNotifier::statusDiperbarui($lunas, 'lunas');

        return $hasil;
    }
}
