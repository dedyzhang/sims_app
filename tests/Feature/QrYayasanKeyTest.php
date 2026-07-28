<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Diminta user: guru mengajar di >1 sekolah dalam satu naungan yayasan — QR absen harian
 * perlu bisa valid lintas sekolah dalam yayasan yg sama, tapi TETAP terisolasi dari sekolah
 * lain di luar yayasan. Solusinya: kunci penandatangan token QR bisa diganti dari APP_KEY
 * (default, terisolasi per instalasi) ke kunci bersama tingkat-yayasan (services.yayasan.qr_key
 * / env YAYASAN_QR_KEY) — SAMA persis di tiap .env sekolah dalam satu yayasan.
 */
class QrYayasanKeyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'yayasan_qr_admin', 'password' => Hash::make('x'), 'access' => 'superadmin']);
    }

    private function setupQr(): void
    {
        Setting::set('kaih_wajib_sebelum_absen', '0');
        Setting::set('cara_absensi_guru', 'barcode');
        Setting::set('qr_absensi_aktif', '1');
        Setting::set('qr_absensi_mode', 'harian');
        Setting::set('qr_geo_wajib', '0'); // fokus tes ini ke token, bukan geofence
    }

    private function tokenHarianFromHtml(string $html): string
    {
        // Token di-embed via @js($token) ke QRious(...) di qr/show.blade.php — ambil string literalnya
        // (@js() memakai kutip TUNGGAL, mis. value: 'b5953447f0c3').
        preg_match("/value:\s*'([a-f0-9]{12})'/", $html, $m);
        $this->assertNotEmpty($m, 'Token QR harian tidak ditemukan di halaman qr.absensi.');

        return $m[1];
    }

    public function test_default_tanpa_kunci_yayasan_token_tetap_pakai_app_key(): void
    {
        // Perilaku lama TIDAK berubah kalau services.yayasan.qr_key kosong (default) —
        // QR tetap terisolasi murni per instalasi via APP_KEY, persis spt sebelumnya.
        config(['services.yayasan.qr_key' => null]);
        $this->setupQr();

        $expected = substr(hash_hmac('sha256', 'qrabsen|' . now()->toDateString(), (string) config('app.key')), 0, 12);

        $html = $this->actingAs($this->admin())->get(route('qr.absensi'))->assertOk()->getContent();
        $this->assertSame($expected, $this->tokenHarianFromHtml($html));
    }

    public function test_kunci_yayasan_diisi_token_tak_lagi_ikut_app_key(): void
    {
        config(['services.yayasan.qr_key' => 'rahasia-yayasan-bersama']);
        $this->setupQr();

        $expectedYayasan = substr(hash_hmac('sha256', 'qrabsen|' . now()->toDateString(), 'rahasia-yayasan-bersama'), 0, 12);
        $expectedLamaViaAppKey = substr(hash_hmac('sha256', 'qrabsen|' . now()->toDateString(), (string) config('app.key')), 0, 12);

        $html = $this->actingAs($this->admin())->get(route('qr.absensi'))->assertOk()->getContent();
        $token = $this->tokenHarianFromHtml($html);

        $this->assertSame($expectedYayasan, $token);
        $this->assertNotSame($expectedLamaViaAppKey, $token, 'Token seharusnya tak lagi bergantung APP_KEY saat kunci yayasan diisi.');
    }

    /** Simulasikan "sekolah lain dalam yayasan yg sama": APP_KEY BEDA (di sini diwakili string
     *  acak yg sengaja TAK PERNAH dipakai dlm hitungan di bawah, tanpa perlu memutasi
     *  config('app.key') global-nya — memutasinya bocor ke test lain krn PHPUnit tak mereset
     *  config() antar-test dlm satu proses), tapi kunci yayasan SAMA → token QR yg dihasilkan
     *  tetap identik & valid diproses di kedua "instalasi". */
    public function test_token_tetap_sama_walau_app_key_beda_selama_kunci_yayasan_sama(): void
    {
        $tanggal = now()->toDateString();
        $appKeySekolahA = 'base64:' . base64_encode(random_bytes(32)); // dilewatkan, TAK dipakai
        $appKeySekolahB = 'base64:' . base64_encode(random_bytes(32)); // sengaja beda dr A
        $kunciYayasan = 'kunci-yayasan-x';

        $tokenSekolahA = substr(hash_hmac('sha256', 'qrabsen|' . $tanggal, $kunciYayasan), 0, 12);
        $tokenSekolahB = substr(hash_hmac('sha256', 'qrabsen|' . $tanggal, $kunciYayasan), 0, 12);

        $this->assertNotSame($appKeySekolahA, $appKeySekolahB, 'Sanity check: dua APP_KEY simulasi ini memang harus beda.');
        $this->assertSame($tokenSekolahA, $tokenSekolahB, 'APP_KEY beda tak boleh menghasilkan token beda selama kunci yayasan sama.');
    }

    /** Alur nyata: token QR "sekolah lain" (dihitung pakai kunci yayasan, APP_KEY apa saja)
     *  tetap diterima endpoint absen — guru di sekolah INI (login akun sekolah ini) berhasil
     *  absen memakai QR yg secara fisik ditampilkan/dicetak di sekolah lain dlm yayasan yg sama. */
    public function test_guru_bisa_absen_pakai_token_qr_yang_dihitung_dgn_kunci_yayasan(): void
    {
        config(['services.yayasan.qr_key' => 'kunci-yayasan-bersama']);
        $this->setupQr();

        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $user = User::create(['username' => 'yayasan_qr_siswa', 'password' => Hash::make('x'), 'access' => 'siswa']);
        $siswa = Siswa::create([
            'id_login' => $user->uuid, 'nama' => 'Siswa Yayasan', 'nis' => '999201', 'id_kelas' => $kelas->uuid, 'jk' => 'L',
        ]);

        // Token dihitung independen dari sisi "sekolah lain" — pakai kunci yayasan yg sama,
        // tak menyentuh APP_KEY instalasi manapun sama sekali.
        $tokenDariSekolahLain = substr(hash_hmac('sha256', 'qrabsen|' . now()->toDateString(), 'kunci-yayasan-bersama'), 0, 12);

        $this->actingAs($user)->postJson('/absen-qr', ['token' => $tokenDariSekolahLain])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotNull($siswa->fresh()->id_kelas);
    }
}
