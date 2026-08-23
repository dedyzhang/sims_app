<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Ujian;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fitur baru: guru bisa pratinjau tampilan siswa (mobile) SEBELUM ujian diterbitkan —
 * pakai kerangka Alpine yg sama dgn kerjakan.blade.php, tapi tanpa UjianAttempt/autosave/
 * server-side jawaban. BEDA dari kerjakan.blade.php sungguhan (yg SENGAJA menyembunyikan
 * is_benar krn siswa yg lihat): halaman ini KHUSUS GURU, jadi kunci jawaban SENGAJA ikut
 * dikirim (key 'benar' per opsi, 'kunci_esai' utk esai) — dipakai fitur "Tampilkan Jawaban
 * Benar" supaya form pratinjau bisa langsung terisi kunci tanpa guru klik satu-satu.
 */
class UjianPratinjauTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;
    private Kelas $kelas;
    private Ujian $ujian;
    private User $guruUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelajaran = Pelajaran::create(['nama' => 'Fisika', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 9, 'kelas' => 'A']);

        $this->guruUser = User::create(['username' => 'guru_pratinjau', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $this->guruUser->uuid, 'nama' => 'Guru Pratinjau', 'nik' => '2222222222', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);

        // SENGAJA status draft — pratinjau harus bisa diakses SEBELUM ujian diterbitkan,
        // beda dari gate() siswa yg mensyaratkan published/closed.
        $this->ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $this->guruUser->uuid,
            'judul' => 'PTS Pratinjau', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'durasi_menit' => 60, 'status' => 'draft',
        ]);
    }

    public function test_guru_bisa_pratinjau_ujian_masih_draft(): void
    {
        $soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => 'Apa ibukota Indonesia?', 'poin' => 10, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'Jakarta', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'Bandung', 'is_benar' => false, 'urutan' => 2]);

        $res = $this->actingAs($this->guruUser)->get(route('ujian.pratinjau', $this->ujian));
        $res->assertOk();
        $res->assertSee('Apa ibukota Indonesia?');
        $res->assertSee('Jakarta');
        $res->assertSee('Bandung');
    }

    /**
     * BEDA dari kerjakan.blade.php siswa: halaman ini khusus guru, jadi flag opsi 'benar'
     * SENGAJA ikut dikirim di payload JSON x-data (dipakai fitur "Tampilkan Jawaban Benar").
     * Payload di-decode balik dari HTML (bukan string-match mentah) krn Js::from() meng-
     * escape kutip/tag jadi \uXXXX — string-match langsung terlalu rapuh thd detail escaping.
     */
    public function test_payload_mcq_menandai_opsi_yang_benar(): void
    {
        $soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '2+2=?', 'poin' => 10, 'urutan' => 1]);
        $opsiBenar = UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => '4', 'is_benar' => true, 'urutan' => 1]);
        $opsiSalah = UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => '5', 'is_benar' => false, 'urutan' => 2]);

        $res = $this->actingAs($this->guruUser)->get(route('ujian.pratinjau', $this->ujian));
        $res->assertOk();
        $soalTampil = $this->decodeSoalTampilPayload($res->getContent());

        $opsiPayload = collect($soalTampil[0]['opsi'])->keyBy('uuid');
        $this->assertTrue($opsiPayload[$opsiBenar->uuid]['benar']);
        $this->assertFalse($opsiPayload[$opsiSalah->uuid]['benar']);
    }

    /** Soal esai dgn kunci_jawaban terisi di meta harus ikut terkirim sbg 'kunci_esai' (dipakai fitur Tampilkan Jawaban Benar). */
    public function test_payload_esai_menyertakan_kunci_jawaban(): void
    {
        UjianSoal::create([
            'id_ujian' => $this->ujian->uuid, 'tipe' => 'essay', 'teks_soal' => 'Jelaskan fotosintesis.', 'poin' => 10, 'urutan' => 1,
            'meta' => ['kunci_jawaban' => 'Proses tumbuhan mengubah cahaya jadi energi.'],
        ]);

        $res = $this->actingAs($this->guruUser)->get(route('ujian.pratinjau', $this->ujian));
        $res->assertOk();
        $soalTampil = $this->decodeSoalTampilPayload($res->getContent());

        $this->assertSame('Proses tumbuhan mengubah cahaya jadi energi.', $soalTampil[0]['kunci_esai']);
    }

    /**
     * Ambil array soalTampil dari dalam `x-data="ujianPratinjau(JSON.parse('...'))"`.
     * Js::from() membungkus JSON dgn JSON.parse('...') + JSON_HEX_APOS (kutip tunggal di
     * DATA ikut ter-'-kan), jadi kutip tunggal PERTAMA setelah JSON.parse(' SELALU
     * penutup wrapper (aman dipakai non-greedy) — bukan string-match mentah yg rapuh thd
     * detail escaping.
     */
    private function decodeSoalTampilPayload(string $html): array
    {
        preg_match("/ujianPratinjau\\(JSON\\.parse\\('(.*?)'\\)\\)\"/s", $html, $m);
        $this->assertNotEmpty($m, 'Payload ujianPratinjau(JSON.parse(...)) tidak ditemukan di HTML.');
        $json = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        $json = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', fn ($mm) => mb_convert_encoding(pack('H*', $mm[1]), 'UTF-8', 'UTF-16BE'), $json);

        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded, 'Gagal decode JSON payload: ' . json_last_error_msg());

        return $decoded;
    }

    public function test_pratinjau_menampilkan_semua_tipe_soal(): void
    {
        UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => 'Soal MCQ', 'poin' => 1, 'urutan' => 1]);
        $complex = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq_complex', 'teks_soal' => 'Soal Kompleks', 'poin' => 1, 'urutan' => 2]);
        UjianSoalOpsi::create(['id_soal' => $complex->uuid, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $complex->uuid, 'teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2]);
        UjianSoal::create([
            'id_ujian' => $this->ujian->uuid, 'tipe' => 'match', 'teks_soal' => 'Soal Cocokkan', 'poin' => 1, 'urutan' => 3,
            'meta' => ['pairs' => [['left' => 'Kiri A', 'right' => 'Kanan A']]],
        ]);
        UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'essay', 'teks_soal' => 'Soal Esai', 'poin' => 1, 'urutan' => 4]);

        $res = $this->actingAs($this->guruUser)->get(route('ujian.pratinjau', $this->ujian));
        $res->assertOk();
        $res->assertSee('Soal MCQ');
        $res->assertSee('Soal Kompleks');
        $res->assertSee('Soal Cocokkan');
        $res->assertSee('Soal Esai');
        $res->assertSee('Kiri A');
        $res->assertSee('Kanan A');
    }

    public function test_pratinjau_ujian_tanpa_soal_menampilkan_pesan_kosong(): void
    {
        $res = $this->actingAs($this->guruUser)->get(route('ujian.pratinjau', $this->ujian));
        $res->assertOk();
        $res->assertSee('Belum ada soal utk ditampilkan');
    }

    public function test_guru_lain_tak_bisa_pratinjau_ujian_orang(): void
    {
        $guruLain = User::create(['username' => 'guru_pratinjau_lain', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $guruLain->uuid, 'nama' => 'Guru Lain', 'nik' => '1111111111', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($guruLain)->get(route('ujian.pratinjau', $this->ujian))->assertForbidden();
    }

    public function test_siswa_tak_bisa_akses_pratinjau_guru(): void
    {
        $user = User::create(['username' => 'siswa_pratinjau', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        \App\Models\Siswa::create(['id_login' => $user->uuid, 'id_kelas' => $this->kelas->uuid, 'nama' => 'Siswa', 'nis' => '9001', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($user)->get(route('ujian.pratinjau', $this->ujian))->assertForbidden();
    }
}
