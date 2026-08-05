<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Setting;
use App\Models\User;
use App\Support\JenjangSekolah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Setting "Jenjang Sekolah" (SD/SMP/SMA) — menentukan rentang tingkat kelas
 * yang ditawarkan di halaman registrasi kelas (kelas.create/kelas.edit).
 * Default aplikasi tetap SMP (7-9) kalau setting belum pernah diisi, supaya
 * instalasi lama yang sudah jalan tidak berubah perilakunya diam-diam.
 */
class JenjangSekolahTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'admin_jenjang', 'password' => Hash::make('password'), 'access' => 'superadmin']);
    }

    public function test_default_jenjang_smp_tanpa_setting(): void
    {
        $this->assertSame('smp', JenjangSekolah::aktif());
        $this->assertSame([7, 9], JenjangSekolah::rentangTingkat());
    }

    public function test_admin_bisa_ubah_jenjang_sekolah(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('setting.jenjangSekolah'), ['jenjang_sekolah' => 'sd'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('sd', Setting::get('jenjang_sekolah'));
        $this->assertSame('sd', JenjangSekolah::aktif());
        $this->assertSame([1, 6], JenjangSekolah::rentangTingkat());
    }

    public function test_jenjang_invalid_ditolak(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('setting.jenjangSekolah'), ['jenjang_sekolah' => 'smk_tinggi'])
            ->assertSessionHasErrors('jenjang_sekolah');

        $this->assertSame('smp', JenjangSekolah::aktif(), 'Jenjang tidak boleh berubah kalau input invalid.');
    }

    public function test_halaman_tambah_kelas_hanya_tampilkan_tingkat_sesuai_jenjang(): void
    {
        $admin = $this->admin();
        Setting::set('jenjang_sekolah', 'sd');

        $res = $this->actingAs($admin)->get(route('kelas.create'));
        $res->assertOk();
        $res->assertSee('Kelas 1');
        $res->assertSee('Kelas 6');
        $res->assertDontSee('Kelas 7');
        $res->assertDontSee('Kelas 12');
    }

    public function test_simpan_kelas_ditolak_jika_tingkat_di_luar_jenjang_aktif(): void
    {
        $admin = $this->admin();
        Setting::set('jenjang_sekolah', 'sd'); // rentang 1-6

        $this->actingAs($admin)->post(route('kelas.store'), ['tingkat' => 8, 'kelas' => 'A'])
            ->assertSessionHasErrors('tingkat');

        $this->assertDatabaseMissing('kelas', ['tingkat' => 8, 'kelas' => 'A']);
    }

    public function test_simpan_kelas_berhasil_jika_tingkat_sesuai_jenjang_aktif(): void
    {
        $admin = $this->admin();
        Setting::set('jenjang_sekolah', 'sma'); // rentang 10-12

        $this->actingAs($admin)->post(route('kelas.store'), ['tingkat' => 10, 'kelas' => 'A'])
            ->assertRedirect(route('kelas.index'));

        $this->assertDatabaseHas('kelas', ['tingkat' => 10, 'kelas' => 'A']);
    }

    public function test_edit_kelas_lama_di_luar_jenjang_aktif_tetap_tersimpan_tanpa_dipaksa_ubah(): void
    {
        $admin = $this->admin();

        // Kelas dibuat saat jenjang masih SMP (tingkat 8) ...
        Setting::set('jenjang_sekolah', 'smp');
        $kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'Lama']);

        // ... lalu sekolah beralih jadi SD.
        Setting::set('jenjang_sekolah', 'sd');

        $res = $this->actingAs($admin)->get(route('kelas.edit', $kelas->uuid));
        $res->assertOk();
        // Tingkat lama tetap ditawarkan sbg opsi (bukan hilang begitu saja).
        $res->assertSee('Kelas 8 (di luar jenjang aktif)');

        // Admin cuma ganti nama kelas, TIDAK menyentuh dropdown tingkat — nilai lama
        // (8) tetap terkirim krn itu opsi yg selected, dan update() TIDAK menyempitkan
        // validasi (beda dgn store()), jadi tidak menolak data lama yg sah ini.
        $this->actingAs($admin)->put(route('kelas.update', $kelas->uuid), ['tingkat' => 8, 'kelas' => 'Baru'])
            ->assertRedirect(route('kelas.index'));

        $kelas->refresh();
        $this->assertSame(8, $kelas->tingkat);
        $this->assertSame('Baru', $kelas->kelas);
    }
}
