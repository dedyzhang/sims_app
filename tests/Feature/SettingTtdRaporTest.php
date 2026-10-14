<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Walikelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingTtdRaporTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'username' => 'super_admin',
            'password' => Hash::make('password'),
            'access'   => 'superadmin',
        ]);
    }

    public function test_admin_can_view_kop_rapor_page_with_signature_fields_and_walikelas_list(): void
    {
        $admin = $this->admin();

        $kelas = Kelas::create([
            'tingkat' => 7,
            'kelas' => 'A',
        ]);

        $guru = Guru::create([
            'nama' => 'Wali Kelas 7A, S.Pd.',
            'nik' => '19800101',
            'jk' => 'L',
        ]);

        Walikelas::create([
            'id_kelas' => $kelas->uuid,
            'id_guru' => $guru->uuid,
        ]);

        $response = $this->actingAs($admin)->get(route('setting.kopRapor'));

        $response->assertOk();
        $response->assertSee('Tanda Tangan &amp; Cap Rapor (Distribusi Online)', false);
        $response->assertSee('name="ttd_kepala_sekolah"', false);
        $response->assertSee('name="cap_sekolah"', false);
        $response->assertSee('name="ttd_walikelas"', false);
        $response->assertSee('ttd_kelas_' . $kelas->uuid, false);
        $response->assertSee('Wali Kelas 7A, S.Pd.');
    }

    public function test_admin_can_upload_and_delete_ttd_kepala_sekolah(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $kepalaUser = User::create([
            'username' => 'kepala_sekolah',
            'password' => Hash::make('password'),
            'access' => 'kepala',
        ]);

        $kepalaGuru = Guru::create([
            'id_login' => $kepalaUser->uuid,
            'nama' => 'Drs. H. Kepala Sekolah, M.Pd.',
            'nik' => '19700101',
            'jk' => 'L',
        ]);

        $file = UploadedFile::fake()->image('ttd_kepala.png', 200, 100);

        $response = $this->actingAs($admin)->post(route('setting.kopRapor.save'), [
            'ttd_kepala_sekolah' => $file,
        ]);

        $response->assertRedirect();
        $this->assertNotEmpty(Setting::get('ttd_kepala_sekolah'));
        Storage::disk('public')->assertExists(Setting::get('ttd_kepala_sekolah'));

        $kepalaGuru->refresh();
        $this->assertSame(Setting::get('ttd_kepala_sekolah'), $kepalaGuru->tanda_tangan);

        // Hapus TTD kepala
        $responseHapus = $this->actingAs($admin)->post(route('setting.kopRapor.save'), [
            'hapus_ttd_kepala_sekolah' => 1,
        ]);

        $responseHapus->assertRedirect();
        $this->assertEmpty(Setting::get('ttd_kepala_sekolah'));
        $kepalaGuru->refresh();
        $this->assertNull($kepalaGuru->tanda_tangan);
    }

    public function test_admin_can_upload_and_delete_cap_sekolah(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $file = UploadedFile::fake()->image('cap_sekolah.png', 120, 120);

        $response = $this->actingAs($admin)->post(route('setting.kopRapor.save'), [
            'cap_sekolah' => $file,
        ]);

        $response->assertRedirect();
        $this->assertNotEmpty(Setting::get('cap_sekolah'));
        Storage::disk('public')->assertExists(Setting::get('cap_sekolah'));

        // Hapus Cap Sekolah
        $responseHapus = $this->actingAs($admin)->post(route('setting.kopRapor.save'), [
            'hapus_cap_sekolah' => 1,
        ]);

        $responseHapus->assertRedirect();
        $this->assertEmpty(Setting::get('cap_sekolah'));
    }

    public function test_admin_can_upload_and_delete_ttd_walikelas_default(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $file = UploadedFile::fake()->image('ttd_walikelas_default.png', 200, 100);

        $response = $this->actingAs($admin)->post(route('setting.kopRapor.save'), [
            'ttd_walikelas' => $file,
        ]);

        $response->assertRedirect();
        $this->assertNotEmpty(Setting::get('ttd_walikelas'));
        Storage::disk('public')->assertExists(Setting::get('ttd_walikelas'));

        // Hapus TTD Walikelas default
        $responseHapus = $this->actingAs($admin)->post(route('setting.kopRapor.save'), [
            'hapus_ttd_walikelas' => 1,
        ]);

        $responseHapus->assertRedirect();
        $this->assertEmpty(Setting::get('ttd_walikelas'));
    }

    public function test_admin_can_upload_and_delete_ttd_per_kelas(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $kelas = Kelas::create([
            'tingkat' => 8,
            'kelas' => 'B',
        ]);

        $guru = Guru::create([
            'nama' => 'Guru Walikelas 8B',
            'nik' => '19850505',
            'jk' => 'P',
        ]);

        Walikelas::create([
            'id_kelas' => $kelas->uuid,
            'id_guru' => $guru->uuid,
        ]);

        $file = UploadedFile::fake()->image('ttd_8b.png', 180, 80);

        $response = $this->actingAs($admin)->post(route('setting.kopRapor.save'), [
            'ttd_kelas_' . $kelas->uuid => $file,
        ]);

        $response->assertRedirect();
        $path = Setting::get('ttd_walikelas_' . $kelas->uuid);
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);

        $guru->refresh();
        $this->assertSame($path, $guru->tanda_tangan);

        // Hapus TTD khusus kelas
        $responseHapus = $this->actingAs($admin)->post(route('setting.kopRapor.save'), [
            'hapus_ttd_kelas_' . $kelas->uuid => 1,
        ]);

        $responseHapus->assertRedirect();
        $this->assertEmpty(Setting::get('ttd_walikelas_' . $kelas->uuid));
        $guru->refresh();
        $this->assertNull($guru->tanda_tangan);
    }

    public function test_cetak_rapor_displays_ttd_kepala_and_ttd_walikelas_when_uploaded(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $sem = Semester::create([
            'tahun' => '2026/2027',
            'semester' => 1,
            'aktif' => true,
        ]);

        $kelas = Kelas::create([
            'tingkat' => 9,
            'kelas' => 'A',
        ]);

        $guruWk = Guru::create([
            'nama' => 'Walikelas Sembilan A',
            'nik' => '999111',
            'jk' => 'L',
        ]);

        Walikelas::create([
            'id_kelas' => $kelas->uuid,
            'id_guru' => $guruWk->uuid,
        ]);

        $siswa = Siswa::create([
            'id_kelas' => $kelas->uuid,
            'nis' => '9901',
            'nama' => 'Siswa Pintar',
            'jk' => 'L',
        ]);

        // Simpan file TTD dan Cap di fake disk
        $fileKepala = UploadedFile::fake()->image('ttd_kepsek.png', 150, 60);
        $pathKepala = $fileKepala->storeAs('ttd', 'ttd_kepala_test.png', 'public');
        Setting::set('ttd_kepala_sekolah', $pathKepala);

        $fileCap = UploadedFile::fake()->image('cap_sekolah.png', 120, 120);
        $pathCap = $fileCap->storeAs('ttd', 'cap_sekolah_test.png', 'public');
        Setting::set('cap_sekolah', $pathCap);

        $fileWk = UploadedFile::fake()->image('ttd_wk.png', 150, 60);
        $pathWk = $fileWk->storeAs('ttd', 'ttd_wk_test.png', 'public');
        Setting::set('ttd_walikelas_' . $kelas->uuid, $pathWk);

        $response = $this->actingAs($admin)->get(route('cetak.rapor', [
            'kelas' => $kelas->uuid,
            'siswa' => $siswa->uuid,
        ]));

        $response->assertOk();
        $response->assertSee('alt="Tanda Tangan Kepala Sekolah"', false);
        $response->assertSee('alt="Cap Sekolah"', false);
        $response->assertSee('alt="Tanda Tangan Wali Kelas"', false);
    }

    public function test_cetak_rapor_renders_clean_blank_lines_when_no_signature_is_set(): void
    {
        $admin = $this->admin();

        $sem = Semester::create([
            'tahun' => '2026/2027',
            'semester' => 1,
            'aktif' => true,
        ]);

        $kelas = Kelas::create([
            'tingkat' => 7,
            'kelas' => 'C',
        ]);

        $siswa = Siswa::create([
            'id_kelas' => $kelas->uuid,
            'nis' => '7701',
            'nama' => 'Siswa Baru',
            'jk' => 'P',
        ]);

        Setting::set('ttd_kepala_sekolah', '');
        Setting::set('cap_sekolah', '');
        Setting::set('ttd_walikelas', '');

        $response = $this->actingAs($admin)->get(route('cetak.rapor', [
            'kelas' => $kelas->uuid,
            'siswa' => $siswa->uuid,
        ]));

        $response->assertOk();
        $response->assertDontSee('alt="Tanda Tangan Kepala Sekolah"', false);
        $response->assertDontSee('alt="Cap Sekolah"', false);
        $response->assertDontSee('alt="Tanda Tangan Wali Kelas"', false);
    }

    public function test_teacher_can_upload_own_signature_via_profile(): void
    {
        Storage::fake('public');
        Setting::set('wajib_daftar_wajah', '0');

        $user = User::create([
            'username' => 'guru_wali',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $guru = Guru::create([
            'id_login' => $user->uuid,
            'nama' => 'Ibu Guru, M.Pd.',
            'nik' => '19920101',
            'jk' => 'P',
        ]);

        $file = UploadedFile::fake()->image('ttd_saya.png', 160, 70);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'username' => 'guru_wali',
            'nama' => 'Ibu Guru, M.Pd.',
            'jk' => 'P',
            'tanda_tangan' => $file,
        ]);

        $response->assertRedirect(route('profile.index'));
        $guru->refresh();
        $this->assertNotNull($guru->tanda_tangan);
        Storage::disk('public')->assertExists($guru->tanda_tangan);

        $viewResponse = $this->actingAs($user)->get(route('profile.index'));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Tanda Tangan Digital');
    }
}
