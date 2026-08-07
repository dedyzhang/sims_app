<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianKelas;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * teks_soal & opsi kini berisi HTML dari TinyMCE (rumus, gambar, format) — HARUS
 * dibersihkan lewat App\Support\RichText::clean() baik saat simpan (UjianSoalController)
 * MAUPUN saat di-embed ke JSON utk halaman pengerjaan siswa (UjianSiswaController), krn
 * kerjakan.blade.php merender lewat x-html (bukan lewat Blade {!! !!} lagi setelah di-embed).
 */
class UjianRichContentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD_JAHAT = '<p>Soal aman</p><script>alert(1)</script><img src=x onerror="alert(2)">';

    private User $guruUser;
    private Ujian $ujian;

    protected function setUp(): void
    {
        parent::setUp();
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);

        $this->guruUser = User::create(['username' => 'guru_xss', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $this->guruUser->uuid, 'nama' => 'Guru XSS', 'nik' => '4040404040', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $kelas->uuid]);

        // Status TETAP draft di setUp() — UjianSoalController::store() menolak soal baru
        // pada ujian yg sudah terbit. Test yg butuh siswa mengerjakan (jalur ke-3) akan
        // menerbitkan ujian SETELAH soal ditambahkan.
        $this->ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid, 'created_by' => $this->guruUser->uuid,
            'judul' => 'PTS XSS', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 60,
        ]);
        UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $kelas->uuid, 'token_masuk' => 'XSSTOK1']);
    }

    public function test_script_tag_dibuang_saat_soal_disimpan(): void
    {
        $this->actingAs($this->guruUser)->post(route('ujian.soal.store', $this->ujian), [
            'tipe' => 'essay', 'teks_soal' => self::PAYLOAD_JAHAT, 'poin' => 10,
        ])->assertRedirect();

        $soal = UjianSoal::where('id_ujian', $this->ujian->uuid)->firstOrFail();
        $this->assertStringNotContainsString('<script', $soal->teks_soal);
        $this->assertStringNotContainsString('onerror', $soal->teks_soal);
        $this->assertStringContainsString('Soal aman', $soal->teks_soal);
    }

    public function test_opsi_jawaban_juga_dibersihkan(): void
    {
        $this->actingAs($this->guruUser)->post(route('ujian.soal.store', $this->ujian), [
            'tipe' => 'mcq', 'teks_soal' => 'Pilih yang benar', 'poin' => 10,
            'opsi' => [
                ['teks' => self::PAYLOAD_JAHAT, 'benar' => '1'],
                ['teks' => 'Opsi biasa', 'benar' => ''],
            ],
        ])->assertRedirect();

        $opsi = UjianSoalOpsi::where('is_benar', true)->firstOrFail();
        $this->assertStringNotContainsString('<script', $opsi->teks_opsi);
        $this->assertStringNotContainsString('onerror', $opsi->teks_opsi);
    }

    /**
     * Walau sudah dibersihkan saat simpan, endpoint pengerjaan siswa membersihkan LAGI
     * (defense in depth) sebelum di-embed ke JSON — payload jahat yg entah bagaimana lolos
     * ke DB (mis. lewat query manual) tetap tak boleh sampai ke browser siswa.
     */
    public function test_konten_jahat_di_db_tetap_dibersihkan_saat_ditampilkan_ke_siswa(): void
    {
        // Simulasikan payload lolos ke DB langsung (bypass controller) — kasus terburuk.
        // Ujian baru diterbitkan SETELAH soal dibuat langsung lewat model (skip validasi
        // controller yg menolak ubah soal pada ujian terbit — di sini itu justru poinnya:
        // walau lolos ke DB entah bagaimana, tetap tak boleh sampai ke browser siswa).
        UjianSoal::create([
            'id_ujian' => $this->ujian->uuid, 'tipe' => 'essay',
            'teks_soal' => self::PAYLOAD_JAHAT, 'poin' => 10, 'urutan' => 1,
        ]);
        $this->ujian->update(['status' => 'published']);

        $siswaUser = User::create(['username' => 'siswa_xss', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $siswaUser->uuid, 'id_kelas' => $this->ujian->kelas()->first()->id_kelas, 'nama' => 'Siswa XSS', 'nis' => '4001', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($siswaUser)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'XSSTOK1'])->assertRedirect();
        $attempt = UjianAttempt::where('id_siswa', $siswaUser->uuid)->firstOrFail();

        $html = $this->actingAs($siswaUser)->get(route('ujian.siswa.kerjakan', [$this->ujian, $attempt]))->assertOk()->getContent();

        // Halaman ITU SENDIRI sah punya banyak <script> (Alpine dkk) — yg diperiksa di sini
        // adalah payload JAHAT-nya spesifik tak lolos utuh, bukan larangan blanket <script.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('Soal aman', $html);
    }

    public function test_rumus_svg_dan_gambar_upload_tetap_tampil_utuh(): void
    {
        $svgAman = '<p>Hitung: <img class="math-svg" src="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=" data-latex="x^2" alt="x^2"></p>';
        $this->actingAs($this->guruUser)->post(route('ujian.soal.store', $this->ujian), [
            'tipe' => 'essay', 'teks_soal' => $svgAman, 'poin' => 10,
        ])->assertRedirect();

        $soal = UjianSoal::where('id_ujian', $this->ujian->uuid)->firstOrFail();
        $this->assertStringContainsString('class="math-svg"', $soal->teks_soal);
        $this->assertStringContainsString('data-latex="x^2"', $soal->teks_soal);
    }
}
