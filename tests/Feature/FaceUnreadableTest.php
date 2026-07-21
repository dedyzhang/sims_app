<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\User;
use App\Support\FaceMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FaceUnreadableTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'username' => 'unreadable_admin',
            'password' => Hash::make('password'),
            'access'   => 'superadmin',
        ]);
    }

    /** Vektor konstan (dim 64) — semua elemen sama, dipakai sbg sampel "konsisten". */
    private function constVec(float $v = 1.0): array
    {
        return array_fill(0, 64, $v);
    }

    /** Vektor alternating +1/-1 — dipakai sbg sampel "arahnya beda jauh" dari constVec()/vektor lawan. */
    private function altVec(bool $flip = false): array
    {
        return array_map(fn ($i) => ($i % 2 === 0) !== $flip ? 1.0 : -1.0, range(0, 63));
    }

    public function test_wajah_tanpa_sampel_valid_ditandai_critical(): void
    {
        Siswa::create(['nama' => 'Siswa Kosong', 'nis' => 'UNRD001', 'jk' => 'L', 'face_descriptor' => []]);

        $items = FaceMatch::unreadableFaces();

        $this->assertCount(1, $items);
        $this->assertSame('critical', $items[0]['level']);
        $this->assertSame('Data wajah kosong/rusak', $items[0]['issue']);
    }

    public function test_wajah_dgn_satu_sampel_ditandai_warning(): void
    {
        Siswa::create(['nama' => 'Siswa Satu Sampel', 'nis' => 'UNRD002', 'jk' => 'L', 'face_descriptor' => [$this->constVec()]]);

        $items = FaceMatch::unreadableFaces();

        $this->assertCount(1, $items);
        $this->assertSame('warning', $items[0]['level']);
        $this->assertSame('Hanya 1 sampel wajah', $items[0]['issue']);
    }

    public function test_wajah_dgn_sampel_konsisten_tidak_ditandai(): void
    {
        Siswa::create([
            'nama' => 'Siswa Konsisten', 'nis' => 'UNRD003', 'jk' => 'L',
            'face_descriptor' => [$this->constVec(1.0), $this->constVec(1.01), $this->constVec(0.99)],
        ]);

        $this->assertSame([], FaceMatch::unreadableFaces());
    }

    public function test_wajah_dgn_sampel_saling_bertentangan_ditandai_critical(): void
    {
        // 3 sampel milik 1 orang yg arahnya saling menjauh (cosine antar-pasangan <= 0, jauh di
        // bawah SUPPORT_THRESHOLD 0.62) — mensimulasikan foto keliru/tercampur saat registrasi.
        Siswa::create([
            'nama' => 'Siswa Tak Konsisten', 'nis' => 'UNRD004', 'jk' => 'L',
            'face_descriptor' => [$this->altVec(false), $this->altVec(true), $this->constVec(1.0)],
        ]);

        $items = FaceMatch::unreadableFaces();

        $this->assertCount(1, $items);
        $this->assertSame('critical', $items[0]['level']);
        $this->assertSame('Sampel wajah tidak konsisten', $items[0]['issue']);
    }

    public function test_wajah_dgn_satu_sampel_outlier_ditandai_warning(): void
    {
        // 2 sampel konsisten satu sama lain + 1 sampel outlier yg tak didukung siapa pun.
        Siswa::create([
            'nama' => 'Siswa Outlier', 'nis' => 'UNRD005', 'jk' => 'L',
            'face_descriptor' => [$this->constVec(1.0), $this->constVec(1.01), $this->altVec(false)],
        ]);

        $items = FaceMatch::unreadableFaces();

        $this->assertCount(1, $items);
        $this->assertSame('warning', $items[0]['level']);
        $this->assertSame('Konsistensi sampel rendah', $items[0]['issue']);
        $this->assertStringContainsString('1 dari 3', $items[0]['detail']);
    }

    public function test_critical_ditampilkan_lebih_dulu_dari_warning(): void
    {
        Siswa::create(['nama' => 'Siswa Kosong 2', 'nis' => 'UNRD006', 'jk' => 'L', 'face_descriptor' => []]);
        Siswa::create(['nama' => 'Siswa Satu Sampel 2', 'nis' => 'UNRD007', 'jk' => 'P', 'face_descriptor' => [$this->constVec()]]);

        $items = FaceMatch::unreadableFaces();

        $this->assertCount(2, $items);
        $this->assertSame('critical', $items[0]['level']);
        $this->assertSame('warning', $items[1]['level']);
    }

    public function test_halaman_wajah_tak_terbaca_bisa_diakses_admin(): void
    {
        Siswa::create(['nama' => 'Siswa Kosong 3', 'nis' => 'UNRD008', 'jk' => 'L', 'face_descriptor' => []]);

        $this->actingAs($this->admin())
            ->get(route('wajah.takTerbaca'))
            ->assertOk()
            ->assertSee('Siswa Kosong 3')
            ->assertSee('Data wajah kosong/rusak');
    }

    public function test_halaman_wajah_tak_terbaca_kosong_saat_semua_aman(): void
    {
        Siswa::create([
            'nama' => 'Siswa Aman', 'nis' => 'UNRD009', 'jk' => 'L',
            'face_descriptor' => [$this->constVec(1.0), $this->constVec(1.01), $this->constVec(0.99)],
        ]);

        $this->actingAs($this->admin())
            ->get(route('wajah.takTerbaca'))
            ->assertOk()
            ->assertSee('Semua wajah terdaftar aman');
    }
}
