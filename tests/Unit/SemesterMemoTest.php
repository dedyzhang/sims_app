<?php

namespace Tests\Unit;

use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perbaikan performa: Semester::aktif() dipanggil di ~18 lokasi berbeda (nyaris tiap
 * controller nilai/absensi/rapor/dashboard) tanpa memoization — jadi belasan query SELECT
 * identik per page load. Fix: aktif() sekarang di-memo per-request (pola sama Setting &
 * RolePermission). Test ini mengunci PERILAKU (nilai benar) DAN bahwa cache-nya segar saat
 * data berubah — termasuk mass-update yg tak memicu event model (butuh clearCache() manual).
 */
class SemesterMemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_aktif_mengembalikan_semester_yang_aktif(): void
    {
        Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => false]);
        $aktif = Semester::create(['semester' => 2, 'tahun' => '2025/2026', 'aktif' => true]);

        $this->assertNotNull(Semester::aktif());
        $this->assertSame($aktif->id, Semester::aktif()->id);
    }

    public function test_aktif_null_kalau_tak_ada_yang_aktif(): void
    {
        Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => false]);

        $this->assertNull(Semester::aktif());
    }

    /** Inti perbaikan: panggil aktif() berkali-kali cuma boleh 1 query total. */
    public function test_aktif_berulang_hanya_satu_query(): void
    {
        Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        Semester::aktif(); // pemanasan memo

        DB::enableQueryLog();
        for ($i = 0; $i < 5; $i++) {
            Semester::aktif();
        }
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $log, 'aktif() berulang setelah memo hangat tak boleh query lagi.');
    }

    /** Bahkan hasil null (tak ada semester aktif) harus di-memo — jangan query ulang tiap panggil. */
    public function test_hasil_null_juga_di_memo(): void
    {
        Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => false]);
        Semester::aktif(); // pemanasan (hasil null)

        DB::enableQueryLog();
        Semester::aktif();
        Semester::aktif();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $log, 'Hasil null pun harus di-memo, bukan query ulang tiap panggil.');
    }

    public function test_update_eloquent_langsung_invalidasi_memo(): void
    {
        $s1 = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $s2 = Semester::create(['semester' => 2, 'tahun' => '2025/2026', 'aktif' => false]);

        $this->assertSame($s1->id, Semester::aktif()->id); // hangatkan memo

        // Pindah semester aktif lewat Eloquent update (memicu event 'saved' → clearCache).
        $s1->update(['aktif' => false]);
        $s2->update(['aktif' => true]);

        $this->assertSame($s2->id, Semester::aktif()->id, 'Update Eloquent harus langsung terbaca — memo tak boleh nyangkut versi lama.');
    }

    public function test_mass_update_dgn_clear_cache_manual_ikut_bersihkan_memo(): void
    {
        $s1 = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $s2 = Semester::create(['semester' => 2, 'tahun' => '2025/2026', 'aktif' => false]);

        $this->assertSame($s1->id, Semester::aktif()->id); // hangatkan memo

        // Simulasikan pola SettingController::updateSemester(): mass-update via query builder
        // (TIDAK memicu event model), lalu clearCache() manual, baru aktifkan yg baru.
        Semester::query()->update(['aktif' => false]);
        Semester::clearCache();
        $s2->update(['aktif' => true]);

        $this->assertSame($s2->id, Semester::aktif()->id, 'Setelah mass-update + clearCache(), memo lama tak boleh nyangkut.');
    }
}
