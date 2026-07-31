<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bug nyata (dilaporkan user via screenshot): saat model AI InsightFace gagal dimuat (mis.
 * jaringan putus di tengah unduh model dari Hugging Face), 3 halaman registrasi wajah
 * (siswa/guru/daftar-sendiri) TETAP menampilkan kamera + tombol "Ambil Sampel" aktif —
 * krn tombol itu hanya digerbang oleh `streaming` (diset true SEBELUM loadInsightFace()
 * di-await), bukan oleh keberhasilan pemuatan model. Klik tombolnya lalu memanggil
 * ifDetSession.run(...) padahal sesinya masih null → "Cannot read properties of null
 * (reading 'run')". scan.blade.php TIDAK kena bug ini krn loop-nya mengecek flag `ifReady`
 * langsung tiap tick, bukan proxy UI spt `streaming`.
 *
 * Perbaikan: catch block di ketiga halaman mereset `streaming=false` + mematikan stream
 * kamera saat pemuatan gagal, dan loadInsightFace() sendiri mereset `ifLoadingPromise`
 * saat gagal supaya percobaan berikutnya (buka kamera lagi) betul2 mencoba ulang.
 */
class FaceEnrollLoadFailureResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('face_engine', 'insightface');
    }

    public function test_engine_reset_promise_saat_gagal_dimuat(): void
    {
        $admin = User::create(['username' => 'admin_if_reset', 'password' => Hash::make('x'), 'access' => 'superadmin']);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);

        $res = $this->actingAs($admin)->get(route('absensi.wajah', ['kelas' => $kelas->uuid]))->assertOk();
        $res->assertSee('ifLoadingPromise = null', false);
    }

    public function test_halaman_wajah_siswa_matikan_streaming_saat_model_gagal_dimuat(): void
    {
        $admin = User::create(['username' => 'admin_if_reset2', 'password' => Hash::make('x'), 'access' => 'superadmin']);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);

        $res = $this->actingAs($admin)->get(route('absensi.wajah', ['kelas' => $kelas->uuid]))->assertOk();
        $res->assertSee('this.streaming=false;', false);
        $res->assertSee("this.stream?.getTracks().forEach(t => t.stop());", false);
    }

    public function test_halaman_wajah_guru_matikan_streaming_saat_model_gagal_dimuat(): void
    {
        $admin = User::create(['username' => 'admin_if_reset3', 'password' => Hash::make('x'), 'access' => 'superadmin']);
        Guru::create(['nama' => 'Guru Reset Engine', 'nik' => '1112223335', 'jk' => 'L']);

        $res = $this->actingAs($admin)->get(route('absensi.wajah-guru'))->assertOk();
        $res->assertSee('this.streaming=false;', false);
        $res->assertSee("this.stream?.getTracks().forEach(t => t.stop());", false);
    }

    public function test_halaman_daftar_wajah_sendiri_matikan_streaming_saat_model_gagal_dimuat(): void
    {
        $user = User::create(['username' => 'guru_self_if_reset', 'password' => Hash::make('x'), 'access' => 'guru']);
        Guru::create(['id_login' => $user->uuid, 'nama' => 'Guru Self Reset', 'nik' => '1112223336', 'jk' => 'L']);

        $res = $this->actingAs($user)->get(route('face.self'))->assertOk();
        $res->assertSee('this.streaming=false;', false);
        $res->assertSee("this.stream?.getTracks().forEach(t => t.stop());", false);
    }
}
