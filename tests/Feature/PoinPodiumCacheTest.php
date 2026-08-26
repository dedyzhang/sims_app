<?php

namespace Tests\Feature;

use App\Http\Controllers\PoinController;
use App\Models\Aturan;
use App\Models\Kelas;
use App\Models\Poin;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Optimasi #4/#6: top3Sekolah() (widget podium di dashboard TIAP siswa/ortu) & dashboard()
 * scope sekolah dulu memuat SELURUH siswa + SELURUH poin tiap dipanggil — komputasi berat
 * yg berulang per user. Kini di-cache 5 menit, di-invalidasi otomatis tiap poin berubah
 * (Poin::booted()). Test ini mengunci: (a) hasil benar, (b) panggilan kedua tak query lagi,
 * (c) perubahan poin langsung tercermin (cache tak stale).
 */
class PoinPodiumCacheTest extends TestCase
{
    use RefreshDatabase;

    private function siswaPoin(Kelas $kelas, Aturan $aturan, string $nama, string $nis): Siswa
    {
        $siswa = Siswa::create(['nama' => $nama, 'nis' => $nis, 'jk' => 'L', 'id_kelas' => $kelas->uuid]);
        Poin::create(['tanggal' => now()->toDateString(), 'id_siswa' => $siswa->uuid, 'id_aturan' => $aturan->uuid]);

        return $siswa;
    }

    public function test_top3_benar_dan_panggilan_kedua_pakai_cache(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $tambah = Aturan::create(['kode' => 'T1', 'jenis' => 'tambah', 'aturan' => 'Juara', 'poin' => 20]);

        // 3 siswa dengan aktivitas + 1 tanpa aktivitas (tak boleh masuk podium).
        $a = $this->siswaPoin($kelas, $tambah, 'Andi', 'P001');
        $this->siswaPoin($kelas, $tambah, 'Budi', 'P002');
        $this->siswaPoin($kelas, $tambah, 'Cici', 'P003');
        Siswa::create(['nama' => 'Kosong', 'nis' => 'P004', 'jk' => 'P', 'id_kelas' => $kelas->uuid]);

        $top3 = PoinController::top3Sekolah();
        $this->assertCount(3, $top3, 'Hanya siswa beraktivitas yg masuk podium.');
        $this->assertSame($a->uuid, $top3->first()['siswa']->uuid, 'Semua sisa sama; tie-break nama → Andi paling atas.');

        // Panggilan kedua: harus dari cache, TIDAK query tabel poin/siswa lagi.
        DB::enableQueryLog();
        PoinController::top3Sekolah();
        $log = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(0, $log, 'Panggilan kedua top3Sekolah() harus dari cache — 0 query.');
    }

    public function test_perubahan_poin_langsung_invalidasi_cache_podium(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $tambah = Aturan::create(['kode' => 'T1', 'jenis' => 'tambah', 'aturan' => 'Juara', 'poin' => 20]);

        $this->siswaPoin($kelas, $tambah, 'Andi', 'P001');
        $this->assertCount(1, PoinController::top3Sekolah()); // hangatkan cache

        // Tambah siswa beraktivitas baru → cache HARUS ikut segar lewat Poin::booted().
        $this->siswaPoin($kelas, $tambah, 'Budi', 'P002');

        $this->assertCount(2, PoinController::top3Sekolah(), 'Podium harus langsung tercermin setelah poin baru — cache tak boleh stale.');
    }

    public function test_hapus_poin_juga_invalidasi_cache(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $tambah = Aturan::create(['kode' => 'T1', 'jenis' => 'tambah', 'aturan' => 'Juara', 'poin' => 20]);

        $siswa = Siswa::create(['nama' => 'Andi', 'nis' => 'P001', 'jk' => 'L', 'id_kelas' => $kelas->uuid]);
        $poin = Poin::create(['tanggal' => now()->toDateString(), 'id_siswa' => $siswa->uuid, 'id_aturan' => $tambah->uuid]);
        $this->assertCount(1, PoinController::top3Sekolah()); // hangatkan cache

        $poin->delete();

        $this->assertCount(0, PoinController::top3Sekolah(), 'Setelah poin dihapus, siswa tanpa aktivitas hilang dari podium — cache tak boleh stale.');
    }
}
