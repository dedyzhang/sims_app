<?php

namespace Tests\Unit;

use App\Models\RolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perbaikan performa nyata: User::canAccess() (dipanggil berkali-kali per page load dgn
 * permission BERBEDA — tiap item sidebar + macam2 feature-gate) memanggil
 * RolePermission::granted() yg dulu SELALU query EXISTS baru tanpa memo sama sekali. Ini
 * muncul di HAMPIR SEMUA halaman berlogin, bukan cuma satu route. Fix: granted() sekarang
 * memuat SEMUA permission milik satu role sekaligus (1 query per role, bukan 1 query per
 * kombinasi role+permission). Test ini mengunci baik PERILAKU (bukan cuma soal query count)
 * maupun bahwa cache-nya benar2 ikut segar saat data berubah.
 */
class RolePermissionMemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_granted_true_utk_kombinasi_yg_ada_false_utk_yg_tak_ada(): void
    {
        RolePermission::create(['role' => 'kesiswaan', 'permission' => 'manage_disiplin']);

        $this->assertTrue(RolePermission::granted('kesiswaan', 'manage_disiplin'));
        $this->assertFalse(RolePermission::granted('kesiswaan', 'manage_rapor'));
        $this->assertFalse(RolePermission::granted('guru', 'manage_disiplin'));
    }

    /** Inti perbaikan: cek BANYAK permission berbeda utk SATU role yg sama cuma boleh 1 query. */
    public function test_cek_banyak_permission_berbeda_utk_satu_role_hanya_satu_query(): void
    {
        RolePermission::create(['role' => 'kesiswaan', 'permission' => 'manage_disiplin']);
        RolePermission::create(['role' => 'kesiswaan', 'permission' => 'manage_agenda']);

        DB::enableQueryLog();
        RolePermission::granted('kesiswaan', 'manage_disiplin');
        RolePermission::granted('kesiswaan', 'manage_agenda');
        RolePermission::granted('kesiswaan', 'manage_rapor'); // tak ada, tetap harus dr memo
        RolePermission::granted('kesiswaan', 'manage_absensi'); // tak ada juga
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $roleQueries = array_filter($log, fn ($q) => str_contains($q['query'], 'role_permissions') && str_contains($q['query'], 'select'));
        $this->assertCount(
            1,
            $roleQueries,
            'Cek 4 permission berbeda utk role yg SAMA semestinya cuma 1 query — memo harus memuat semua permission role itu sekaligus, bukan per-kombinasi.'
        );
    }

    public function test_cek_permission_yg_sama_berulang_tidak_query_lagi(): void
    {
        RolePermission::create(['role' => 'kurikulum', 'permission' => 'manage_rapor']);
        RolePermission::granted('kurikulum', 'manage_rapor'); // pemanasan memo

        DB::enableQueryLog();
        for ($i = 0; $i < 5; $i++) {
            RolePermission::granted('kurikulum', 'manage_rapor');
        }
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $log);
    }

    public function test_role_berbeda_punya_memo_terpisah(): void
    {
        RolePermission::create(['role' => 'kesiswaan', 'permission' => 'manage_disiplin']);
        RolePermission::create(['role' => 'kurikulum', 'permission' => 'manage_rapor']);

        $this->assertTrue(RolePermission::granted('kesiswaan', 'manage_disiplin'));
        $this->assertFalse(RolePermission::granted('kurikulum', 'manage_disiplin'));
        $this->assertTrue(RolePermission::granted('kurikulum', 'manage_rapor'));
    }

    public function test_create_baru_langsung_terbaca_walau_role_sudah_pernah_dicek(): void
    {
        // Cek dulu (memo kosong utk role ini, tapi sudah "tersentuh").
        $this->assertFalse(RolePermission::granted('sekretaris', 'manage_agenda'));

        RolePermission::create(['role' => 'sekretaris', 'permission' => 'manage_agenda']);

        $this->assertTrue(
            RolePermission::granted('sekretaris', 'manage_agenda'),
            'Permission yg baru ditambahkan harus langsung kebaca — memo tak boleh nyangkut versi lama.'
        );
    }

    public function test_hapus_satu_baris_langsung_hilang_dari_memo(): void
    {
        $rp = RolePermission::create(['role' => 'sarpras', 'permission' => 'manage_sarpras']);
        $this->assertTrue(RolePermission::granted('sarpras', 'manage_sarpras'));

        $rp->delete();

        $this->assertFalse(RolePermission::granted('sarpras', 'manage_sarpras'));
    }

    public function test_mass_delete_dgn_clear_cache_manual_ikut_bersihkan_memo(): void
    {
        RolePermission::create(['role' => 'walikelas', 'permission' => 'manage_agenda']);
        $this->assertTrue(RolePermission::granted('walikelas', 'manage_agenda'));

        // Simulasikan pola SettingController::rolesSave(): mass delete via query builder
        // (TIDAK memicu event model), lalu clearCache() manual.
        RolePermission::query()->delete();
        RolePermission::clearCache();

        $this->assertFalse(
            RolePermission::granted('walikelas', 'manage_agenda'),
            'Setelah mass-delete + clearCache(), memo lama tak boleh nyangkut.'
        );
    }
}
