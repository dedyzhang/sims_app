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

        $this->assertStringContainsString('Pinjam Barang', $html);
        $this->assertStringContainsString('Booking Ruangan', $html);
        $this->assertStringContainsString('Lapor Kerusakan', $html);
        $this->assertStringContainsString('Denah Sekolah', $html);
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
            ->assertSee('Pengajuan Pinjam Saya');

        $this->assertSame(1, substr_count($pinjam->getContent(), '>Detail</a>'));

        $this->actingAs($guru)
            ->get('/sarpras/kerusakan')
            ->assertOk()
            ->assertSee('KR-GURU-001')
            ->assertDontSee('KR-LAIN-001');
    }
}
