<?php

namespace Tests\Feature;

use App\Models\User;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\LaporanKerusakan;
use App\Sarpras\Models\Peminjaman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SarprasGuruScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_dan_tab_guru_hanya_menu_operasional(): void
    {
        $guru = User::create([
            'username' => 'guru_sarpras_menu',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $html = $this->actingAs($guru)->get('/sarpras/peminjaman')->assertOk()->getContent();

        $this->assertStringContainsString('Peminjaman', $html);
        $this->assertStringContainsString('Ruangan', $html);
        $this->assertStringContainsString('Lapor Kerusakan', $html);
        $this->assertStringContainsString('Ruangan', $html);
        $this->assertStringNotContainsString('Inventaris Barang', $html);
        $this->assertStringNotContainsString('>Pengadaan</span>', $html);
        $this->assertStringNotContainsString('>Supplier</span>', $html);
        $this->assertStringNotContainsString('Mutasi &amp; Hapus', $html);
        $this->assertStringNotContainsString('>Master Data</span>', $html);
    }

    public function test_guru_hanya_lihat_peminjaman_dan_kerusakan_milik_sendiri(): void
    {
        $guru = User::create([
            'username' => 'guru_sarpras_scope',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        $lain = User::create([
            'username' => 'guru_lain_scope',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $denah = Denah::create(['nama' => 'Gedung Guru Scope']);
        DenahRuangan::create([
            'denah_id' => $denah->id,
            'kode' => 'R-G',
            'nama' => 'Ruang Guru',
            'status' => 'tersedia',
        ]);

        Peminjaman::create([
            'kode' => 'PJM-GURU-001',
            'peminjam_id' => $guru->uuid,
            'keperluan' => 'Pinjam milik guru',
            'tgl_pinjam' => now()->toDateString(),
            'tgl_kembali_rencana' => now()->addDay()->toDateString(),
            'status' => 'diajukan',
        ]);
        Peminjaman::create([
            'kode' => 'PJM-LAIN-001',
            'peminjam_id' => $lain->uuid,
            'keperluan' => 'Pinjam milik orang lain',
            'tgl_pinjam' => now()->toDateString(),
            'tgl_kembali_rencana' => now()->addDay()->toDateString(),
            'status' => 'diajukan',
        ]);

        LaporanKerusakan::create([
            'kode' => 'KR-GURU-001',
            'pelapor_id' => $guru->uuid,
            'deskripsi' => 'Kerusakan milik guru',
            'urgensi' => 'sedang',
            'status' => 'dilaporkan',
        ]);
        LaporanKerusakan::create([
            'kode' => 'KR-LAIN-001',
            'pelapor_id' => $lain->uuid,
            'deskripsi' => 'Kerusakan milik orang lain',
            'urgensi' => 'tinggi',
            'status' => 'dilaporkan',
        ]);

        $pinjam = $this->actingAs($guru)
            ->get('/sarpras/peminjaman')
            ->assertOk()
            ->assertSee('Pinjam milik guru')
            ->assertDontSee('Pinjam milik orang lain');

        $this->assertSame(1, substr_count($pinjam->getContent(), '>Detail</a>'));

        $this->actingAs($guru)
            ->get('/sarpras/kerusakan')
            ->assertOk()
            ->assertSee('KR-GURU-001')
            ->assertDontSee('KR-LAIN-001');
    }

    public function test_guru_tidak_bisa_akses_detail_peminjaman_orang_lain(): void
    {
        $guru = User::create([
            'username' => 'guru_peminjaman_show',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        $lain = User::create([
            'username' => 'guru_peminjaman_lain',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $peminjaman = Peminjaman::create([
            'kode' => 'PJM-SHOW-403',
            'peminjam_id' => $lain->uuid,
            'keperluan' => 'Rahasia',
            'tgl_pinjam' => now()->toDateString(),
            'tgl_kembali_rencana' => now()->addDay()->toDateString(),
            'status' => 'diajukan',
        ]);

        $this->actingAs($guru)
            ->get('/sarpras/peminjaman/' . $peminjaman->id)
            ->assertForbidden();
    }

    public function test_guru_tidak_bisa_akses_detail_kerusakan_orang_lain(): void
    {
        $guru = User::create([
            'username' => 'guru_kerusakan_show',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        $lain = User::create([
            'username' => 'guru_kerusakan_lain',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $laporan = LaporanKerusakan::create([
            'kode' => 'KR-SHOW-403',
            'pelapor_id' => $lain->uuid,
            'deskripsi' => 'Rahasia',
            'urgensi' => 'sedang',
            'status' => 'dilaporkan',
        ]);

        $this->actingAs($guru)
            ->get('/sarpras/kerusakan/' . $laporan->id)
            ->assertForbidden();
    }

    public function test_denah_store_redirect_ke_halaman_show(): void
    {
        $admin = User::create([
            'username' => 'admin_denah_store',
            'password' => Hash::make('password'),
            'access' => 'sarpras',
        ]);

        $response = $this->actingAs($admin)->post('/sarpras/denah', [
            'nama' => 'Gedung Baru',
            'gedung' => 'Gedung B',
            'lantai' => '1',
        ]);

        $denah = Denah::where('gedung', 'Gedung B')->first();
        $this->assertNotNull($denah);
        $response->assertRedirect(route('sarpras.denah.show', $denah));
    }

    public function test_staff_sarpras_melihat_menu_kelola_lengkap(): void
    {
        $sarpras = User::create([
            'username' => 'staff_sarpras_menu',
            'password' => Hash::make('password'),
            'access' => 'sarpras',
        ]);

        $html = $this->actingAs($sarpras)->get('/sarpras')->assertOk()->getContent();

        $this->assertStringContainsString('Inventaris', $html);
        $this->assertStringContainsString('Laporan', $html);
        $this->assertStringContainsString('Dashboard', $html);
    }
}
