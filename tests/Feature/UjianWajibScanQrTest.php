<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Pelajaran;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianDaftarHadir;
use App\Models\UjianJadwal;
use App\Models\UjianKelas;
use App\Models\UjianPaket;
use App\Models\UjianRuangan;
use App\Models\UjianSesi;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * UjianPaket::wajib_scan_qr — admin pilih per paket: mode 1 (default, langsung
 * token, tak berubah) vs mode 2 (siswa wajib scan QR ruangan dulu, sekali scan
 * buka akses ke SEMUA mapel paket itu hari ini). Menyambungkan UjianPolicy::take()
 * /UjianSiswaController::gate() ke infrastruktur UjianRuanganScanController/
 * UjianDaftarHadir yg sudah ada (lihat juga UjianRuanganScanTest).
 */
class UjianWajibScanQrTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private UjianPaket $paket;
    private Kelas $kelas;
    private UjianRuangan $ruangan;
    private Siswa $siswa;
    private User $siswaUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['username' => 'admin_wsq', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);

        $this->paket = UjianPaket::create(['nama' => 'PAS Scan', 'jenis' => 'pas', 'created_by' => $this->admin->uuid]);
        $this->ruangan = UjianRuangan::create(['id_ujian_paket' => $this->paket->uuid, 'nama' => 'Ruang WSQ 1']);

        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->siswaUser = User::create(['username' => 'siswa_wsq', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $this->siswa = Siswa::create(['id_login' => $this->siswaUser->uuid, 'id_kelas' => $this->kelas->uuid, 'nama' => 'Siswa Wsq', 'nis' => '7601', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $this->ruangan->peserta()->create(['id_siswa' => $this->siswa->uuid]);
    }

    /** Buat satu mapel ujian lengkap (soal + UjianKelas token) + jadwal hari ini di ruangan test, agar bisa langsung dites gate()/start(). */
    private function buatMapelHariIni(string $namaMapel, string $token): Ujian
    {
        $ujian = Ujian::create([
            'id_pelajaran' => Pelajaran::create(['nama' => $namaMapel, 'kkm' => 75])->uuid,
            'created_by' => $this->admin->uuid, 'id_ujian_paket' => $this->paket->uuid,
            'judul' => 'PAS ' . $namaMapel, 'jenis' => 'pas', 'target_nilai' => 'pas',
            'durasi_menit' => 60, 'status' => 'published',
        ]);
        $soal = UjianSoal::create(['id_ujian' => $ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '1+1=?', 'poin' => 10, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => '2', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => '3', 'is_benar' => false, 'urutan' => 2]);

        UjianKelas::create([
            'id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => $token,
            'dibuka_mulai' => now()->subHour(), 'dibuka_sampai' => now()->addHours(6),
        ]);

        $sesi = UjianSesi::create(['id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(), 'jam_mulai' => '00:00', 'jam_selesai' => '23:59', 'label' => $namaMapel]);
        UjianJadwal::create(['id_ujian_paket' => $this->paket->uuid, 'id_ujian' => $ujian->uuid, 'id_sesi' => $sesi->uuid, 'tanggal' => now()->toDateString(), 'jam_mulai' => '00:00', 'jam_selesai' => '23:59']);

        return $ujian;
    }

    public function test_toggle_default_false_alur_token_langsung_tanpa_scan(): void
    {
        $ujian = $this->buatMapelHariIni('Matematika', 'TOKENDEF');

        $this->actingAs($this->siswaUser)->get(route('ujian.siswa.gate', $ujian))
            ->assertOk()->assertSee('Token Masuk')->assertDontSee('Belum Scan QR');

        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $ujian), ['token' => 'TOKENDEF'])
            ->assertRedirect();
        $this->assertSame(1, UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->count());
    }

    public function test_wajib_scan_belum_scan_tampilkan_wall_bukan_form_token(): void
    {
        $this->paket->update(['wajib_scan_qr' => true]);
        $ujian = $this->buatMapelHariIni('Fisika', 'TOKENWALL');

        $this->actingAs($this->siswaUser)->get(route('ujian.siswa.gate', $ujian))
            ->assertOk()->assertSee('Anda Belum Scan QR Ruangan')->assertSee('Scan Sekarang')->assertDontSee('Token Masuk')
            // Bug report FL: tombol scan muncul tapi kamera gagal ("Fitur scan QR belum siap
            // dimuat") — root cause: script CDN qr-scanner di layout dimuat KONDISIONAL per
            // $path (layouts/app.blade.php), lupa didaftarkan utk halaman siswa ini. Assert
            // script-nya benar2 ada di HTML, bukan cuma tombolnya.
            ->assertSee('qr-scanner@1.4.2', false);

        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $ujian), ['token' => 'TOKENWALL'])
            ->assertForbidden();
        $this->assertSame(0, UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->count());
    }

    public function test_scan_sekali_buka_semua_mapel_hari_itu(): void
    {
        $this->paket->update(['wajib_scan_qr' => true]);
        $ujianA = $this->buatMapelHariIni('Kimia', 'TOKENA1');
        $ujianB = $this->buatMapelHariIni('Biologi', 'TOKENB1');
        $ujianC = $this->buatMapelHariIni('Geografi', 'TOKENC1');

        $this->actingAs($this->siswaUser)->get(route('ujian.ruangan.scan', $this->ruangan))->assertOk();

        foreach ([[$ujianA, 'TOKENA1'], [$ujianB, 'TOKENB1'], [$ujianC, 'TOKENC1']] as [$ujian, $token]) {
            $this->actingAs($this->siswaUser)->get(route('ujian.siswa.gate', $ujian))->assertOk()->assertSee('Token Masuk');
            $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $ujian), ['token' => $token])->assertRedirect();
        }

        $this->assertSame(3, UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->count());
        $this->assertSame(3, UjianDaftarHadir::where('id_siswa', $this->siswa->uuid)
            ->where('status', 'hadir')->whereDate('tanggal', now()->toDateString())->count());
    }

    public function test_koreksi_manual_ke_alpa_menutup_akses_lagi(): void
    {
        $this->paket->update(['wajib_scan_qr' => true]);
        $ujian = $this->buatMapelHariIni('Sejarah', 'TOKENALPA');

        $this->actingAs($this->siswaUser)->get(route('ujian.ruangan.scan', $this->ruangan))->assertOk();
        $this->actingAs($this->siswaUser)->get(route('ujian.siswa.gate', $ujian))->assertSee('Token Masuk');

        UjianDaftarHadir::where('id_siswa', $this->siswa->uuid)->update(['status' => 'alpa']);

        $this->actingAs($this->siswaUser)->get(route('ujian.siswa.gate', $ujian))
            ->assertOk()->assertSee('Anda Belum Scan QR Ruangan');
    }

    public function test_toggle_admin_tersimpan_lewat_update(): void
    {
        $payload = ['nama' => $this->paket->nama, 'jenis' => $this->paket->jenis, 'status' => 'draft', 'wajib_scan_qr' => '1'];
        $this->actingAs($this->admin)->post(route('ujian.paket.update', $this->paket), $payload)->assertRedirect();
        $this->assertTrue($this->paket->fresh()->wajib_scan_qr);

        $payload2 = ['nama' => $this->paket->nama, 'jenis' => $this->paket->jenis, 'status' => 'draft'];
        $this->actingAs($this->admin)->post(route('ujian.paket.update', $this->paket), $payload2)->assertRedirect();
        $this->assertFalse($this->paket->fresh()->wajib_scan_qr);
    }

    public function test_ujian_standalone_selalu_mode_1(): void
    {
        // Paket LAIN yg wajib_scan_qr — pastikan tak ikut memengaruhi ujian standalone (tanpa paket).
        $this->paket->update(['wajib_scan_qr' => true]);

        [$guruUser, $guru] = [User::create(['username' => 'guru_wsq_standalone', 'password' => Hash::make('rahasia123'), 'access' => 'guru']), null];
        $guru = Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Wsq', 'nik' => '9777700007', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $ujian = Ujian::create([
            'id_pelajaran' => Pelajaran::create(['nama' => 'PPKn', 'kkm' => 75])->uuid,
            'created_by' => $guruUser->uuid, 'judul' => 'Ulangan PPKn', 'jenis' => 'harian',
            'target_nilai' => 'harian', 'durasi_menit' => 30, 'status' => 'published',
        ]);
        $soal = UjianSoal::create(['id_ujian' => $ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => 'x', 'poin' => 10, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'a', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'b', 'is_benar' => false, 'urutan' => 2]);
        UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => 'STANDALONE1']);

        $this->assertFalse($ujian->wajibScanQr());

        $this->actingAs($this->siswaUser)->get(route('ujian.siswa.gate', $ujian))->assertOk()->assertSee('Token Masuk');
        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $ujian), ['token' => 'STANDALONE1'])->assertRedirect();
        $this->assertSame(1, UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->count());
    }
}
