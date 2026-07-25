<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Integrasi mesin InsightFace ke 3 halaman REGISTRASI wajah (siswa, guru, daftar-sendiri) —
 * pasangan dari FaceEngineScanIntegrationTest yg menutup sisi kios scan. Sama seperti scan:
 * skrip yg dimuat & badge "Terdaftar" harus mengikuti setting face_engine, tanpa mengubah
 * apa pun di jalur Human.js default.
 */
class FaceEngineRegistrationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['username' => 'admin_if_reg', 'password' => Hash::make('x'), 'access' => 'superadmin']);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
    }

    public function test_halaman_wajah_siswa_muat_human_default_dan_insightface_saat_diaktifkan(): void
    {
        $res1 = $this->actingAs($this->admin)->get(route('absensi.wajah', ['kelas' => $this->kelas->uuid]))->assertOk();
        $res1->assertSee('@vladmandic/human@3.3.6/dist/human.js', false);
        $res1->assertDontSee('onnxruntime-web', false);

        Setting::set('face_engine', 'insightface');
        $res2 = $this->actingAs($this->admin)->get(route('absensi.wajah', ['kelas' => $this->kelas->uuid]))->assertOk();
        $res2->assertSee('onnxruntime-web@1.27.0', false);
        $res2->assertSee('loadInsightFace', false);
        $res2->assertDontSee('@vladmandic/human@3.3.6/dist/human.js', false);
    }

    public function test_badge_terdaftar_siswa_ikuti_kolom_descriptor_engine_aktif(): void
    {
        $siswa = Siswa::create([
            'id_kelas' => $this->kelas->uuid, 'nama' => 'Siswa Dua Kolom', 'nis' => '888002', 'jk' => 'L',
            'face_descriptor_if' => [[0.9, 0.8]],
        ]);

        // Default (human): belum terdaftar krn face_descriptor kosong (walau face_descriptor_if terisi).
        $res1 = $this->actingAs($this->admin)->get(route('absensi.wajah', ['kelas' => $this->kelas->uuid]))->assertOk();
        $res1->assertSee('Belum daftar wajah');

        // insightface: terdaftar krn face_descriptor_if terisi.
        Setting::set('face_engine', 'insightface');
        $res2 = $this->actingAs($this->admin)->get(route('absensi.wajah', ['kelas' => $this->kelas->uuid]))->assertOk();
        $res2->assertSee('Terdaftar');
    }

    public function test_halaman_wajah_guru_muat_human_default_dan_insightface_saat_diaktifkan(): void
    {
        Guru::create(['nama' => 'Guru Uji Engine', 'nik' => '9998887776', 'jk' => 'L']);

        $res1 = $this->actingAs($this->admin)->get(route('absensi.wajah-guru'))->assertOk();
        $res1->assertSee('@vladmandic/human@3.3.6/dist/human.js', false);
        $res1->assertDontSee('onnxruntime-web', false);

        Setting::set('face_engine', 'insightface');
        $res2 = $this->actingAs($this->admin)->get(route('absensi.wajah-guru'))->assertOk();
        $res2->assertSee('onnxruntime-web@1.27.0', false);
        $res2->assertSee('loadInsightFace', false);
        $res2->assertDontSee('@vladmandic/human@3.3.6/dist/human.js', false);
    }

    public function test_halaman_daftar_wajah_sendiri_muat_human_default_dan_insightface_saat_diaktifkan(): void
    {
        $user = User::create(['username' => 'guru_self_if', 'password' => Hash::make('x'), 'access' => 'guru']);
        Guru::create(['id_login' => $user->uuid, 'nama' => 'Guru Self Engine', 'nik' => '1112223334', 'jk' => 'L']);

        $res1 = $this->actingAs($user)->get(route('face.self'))->assertOk();
        $res1->assertSee('@vladmandic/human@3.3.6/dist/human.js', false);
        $res1->assertDontSee('onnxruntime-web', false);

        Setting::set('face_engine', 'insightface');
        $res2 = $this->actingAs($user)->get(route('face.self'))->assertOk();
        $res2->assertSee('onnxruntime-web@1.27.0', false);
        $res2->assertSee('loadInsightFace', false);
        $res2->assertDontSee('@vladmandic/human@3.3.6/dist/human.js', false);
    }

    public function test_face_self_redirect_gate_ikuti_kolom_descriptor_engine_aktif(): void
    {
        $user = User::create(['username' => 'guru_gate_if', 'password' => Hash::make('x'), 'access' => 'guru']);
        Guru::create(['id_login' => $user->uuid, 'nama' => 'Guru Gate Engine', 'nik' => '5556667778', 'jk' => 'L', 'face_descriptor_if' => [[0.5, 0.5]]]);

        // Default (human): face_descriptor masih kosong -> TIDAK diredirect (halaman form tampil).
        $this->actingAs($user)->get(route('face.self'))->assertOk()->assertSee('Daftarkan Wajah Anda');

        // insightface: face_descriptor_if sudah terisi -> dianggap sudah terdaftar, redirect ke dashboard.
        Setting::set('face_engine', 'insightface');
        $this->actingAs($user)->get(route('face.self'))->assertRedirect(route('dashboard'));
    }
}
