<?php

namespace Tests\Feature;

use App\Http\Controllers\PoinController;
use App\Models\Aturan;
use App\Models\Kelas;
use App\Models\Poin;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bug nyata terukur: /poin/siswa & /poin/dashboard sempat memicu 340+ query — poinIndex()
 * dan rankingAktif() (dipakai dashboard()/top3Sekolah()) memanggil PoinController::hitung()
 * SATU PER SATU di dalam loop utk tiap siswa (2 query/panggilan), bukan sekali secara bulk.
 * Fix: hitungBulk() menghitung sisa/totalTambah/adaAktivitas utk BANYAK siswa via 1 query.
 * Test ini mengunci baik KEBENARAN angkanya (hasil hitungBulk harus identik dgn hitung() satu
 * per satu) MAUPUN jumlah query-nya (harus tetap sama brp pun jumlah siswanya).
 */
class PoinBulkHitungTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'username' => 'admin_poin_bulk',
            'password' => Hash::make('password'),
            'access' => 'superadmin',
        ]);
    }

    private function buatSiswaDenganPoin(Kelas $kelas, Aturan $tambah, Aturan $kurang, string $nis): Siswa
    {
        $siswa = Siswa::create(['nama' => 'Siswa ' . $nis, 'nis' => $nis, 'jk' => 'L', 'id_kelas' => $kelas->uuid]);
        Poin::create(['tanggal' => now()->toDateString(), 'id_siswa' => $siswa->uuid, 'id_aturan' => $tambah->uuid]);
        Poin::create(['tanggal' => now()->toDateString(), 'id_siswa' => $siswa->uuid, 'id_aturan' => $kurang->uuid]);

        return $siswa;
    }

    public function test_hitung_bulk_menghasilkan_angka_sama_persis_dgn_hitung_satu_per_satu(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $tambah = Aturan::create(['kode' => 'T1', 'jenis' => 'tambah', 'aturan' => 'Juara lomba', 'poin' => 20]);
        $kurang = Aturan::create(['kode' => 'K1', 'jenis' => 'kurang', 'aturan' => 'Terlambat', 'poin' => 5]);

        $siswaAda = $this->buatSiswaDenganPoin($kelas, $tambah, $kurang, 'BULK001');
        $siswaKosong = Siswa::create(['nama' => 'Siswa Kosong', 'nis' => 'BULK002', 'jk' => 'P', 'id_kelas' => $kelas->uuid]);

        $expected = [
            $siswaAda->uuid => PoinController::hitung($siswaAda->uuid),
            $siswaKosong->uuid => PoinController::hitung($siswaKosong->uuid),
        ];

        $bulk = PoinController::hitungBulk([$siswaAda->uuid, $siswaKosong->uuid]);

        $this->assertSame($expected[$siswaAda->uuid]['sisa'], $bulk[$siswaAda->uuid]['sisa']);
        $this->assertSame($expected[$siswaAda->uuid]['totalTambah'], $bulk[$siswaAda->uuid]['totalTambah']);
        $this->assertSame($expected[$siswaAda->uuid]['adaAktivitas'], $bulk[$siswaAda->uuid]['adaAktivitas']);
        $this->assertSame($expected[$siswaAda->uuid]['peringatan'], $bulk[$siswaAda->uuid]['peringatan']);

        // Siswa tanpa rekam poin sama sekali: sisa 100 default, tak ada aktivitas.
        $this->assertSame(100, $bulk[$siswaKosong->uuid]['sisa']);
        $this->assertFalse($bulk[$siswaKosong->uuid]['adaAktivitas']);
    }

    public function test_poin_siswa_index_jumlah_query_tidak_bertambah_seiring_jumlah_siswa(): void
    {
        Setting::create(['key' => 'jenis_aturan', 'value' => 'poin']);
        $admin = $this->admin();
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $tambah = Aturan::create(['kode' => 'T2', 'jenis' => 'tambah', 'aturan' => 'Prestasi', 'poin' => 10]);
        $kurang = Aturan::create(['kode' => 'K2', 'jenis' => 'kurang', 'aturan' => 'Pelanggaran', 'poin' => 5]);

        $this->buatSiswaDenganPoin($kelas, $tambah, $kurang, 'BULK101');
        $this->actingAs($admin);

        $countQuery = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/poin/siswa')->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        // Panggilan pemanasan (efek samping auth last_seen_at dkk pada request pertama).
        $countQuery();
        $with1Siswa = $countQuery();

        for ($i = 0; $i < 9; $i++) {
            $this->buatSiswaDenganPoin($kelas, $tambah, $kurang, 'BULK2' . $i);
        }

        $with10Siswa = $countQuery();

        $this->assertSame(
            $with1Siswa,
            $with10Siswa,
            'Jumlah query /poin/siswa harus SAMA persis walau siswa naik dari 1 ke 10 — kalau naik, N+1 balik lagi.'
        );
    }
}
