<?php

namespace Tests\Feature;

use App\Models\AiTeacherHistory;
use App\Models\Guru;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTeacherProsemProtaTest extends TestCase
{
    use RefreshDatabase;

    private string $sampleTpAtpContent = <<<'TXT'
KOP SURAT RESMI SEKOLAH
SMP MAITREYAWIRA TANJUNGPINANG
TERAKREDITASI A

TUJUAN PEMBELAJARAN (TP)
MATA PELAJARAN : Ilmu Pengetahuan Sosial
FASE / KELAS : D/VII
TAHUN PELAJARAN : 2026/2027

TABEL TUJUAN PEMBELAJARAN
ELEMEN | CAPAIAN PEMBELAJARAN | LINGKUP MATERI | TUJUAN PEMBELAJARAN (TP) | KODE TP | ALOKASI WAKTU (JP) | SEMESTER
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial budaya | Diri dan Keluarga di Tengah Lingkungan Sosial | Menjelaskan pengertian dan sejarah asal-usul keluarga | PK.7.1 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial budaya | Diri dan Keluarga di Tengah Lingkungan Sosial | Memanfaatkan peta untuk menunjukkan lokasi tempat tinggal | PK.7.2 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial budaya | Diri dan Keluarga di Tengah Lingkungan Sosial | Menganalisis beragam kebutuhan manusia | PK.7.3 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial budaya | Diri dan Keluarga di Tengah Lingkungan Sosial | Menjelaskan pentingnya melakukan interaksi antarwilayah | PK.7.4 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial budaya | Keberagaman Lingkungan Sekitar | Mengidentifikasi kondisi lingkungan masyarakat sekitar | PK.7.5 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial budaya | Keberagaman Lingkungan Sekitar | Mengidentifikasi karakteristik lingkungan alam sekitar | PK.7.6 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial budaya | Keberagaman Lingkungan Sekitar | Menjelaskan berbagai permasalahan terkait kelangkaan sumber daya | PK.7.7 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial budaya | Potensi Ekonomi Lingkungan | Menjelaskan potensi sumber daya alam yang tersedia | PK.7.8 | 25 JP | Genap
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial budaya | Potensi Ekonomi Lingkungan | Menguraikan dinamika perkembangan Hindu-Buddha | PK.7.9 | 25 JP | Genap
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial budaya | Pemberdayaan Masyarakat | Mengidentifikasi keterkaitan kondisi geografis dan sosial budaya | PK.7.10 | 24 JP | Genap
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial budaya | Pemberdayaan Masyarakat | Menjelaskan metode pemberdayaan masyarakat | PK.7.11 | 24 JP | Genap

Mengetahui,
Kepala SMPS Maitreyawira Tanjungpinang
Dra. Megawati
NIK. 10010070010

Tanjungpinang, 17 Juli 2026
Guru Mata Pelajaran
Desmalini, S.E., Gr.
NIK. 10090150308
TXT;

    private function makeGuru(): User
    {
        return User::create([
            'username' => 'guru-prosem',
            'password' => 'password',
            'access' => 'guru',
            'name' => 'Guru IPS',
        ]);
    }

    public function test_guru_can_access_ai_teacher_index_with_prosem_prota_tab(): void
    {
        $guru = $this->makeGuru();

        // Buat riwayat TP/ATP tersimpan
        AiTeacherHistory::create([
            'user_uuid' => $guru->uuid,
            'type' => 'tpatp',
            'type_label' => 'TP & ATP',
            'title' => 'TP & ATP IPS Kelas 7',
            'excerpt' => 'Ringkasan TP & ATP',
            'answer' => $this->sampleTpAtpContent,
            'metadata' => [
                'mapel' => 'Ilmu Pengetahuan Sosial',
                'fase_kelas' => 'D/VII',
                'tahun' => '2026/2027',
                'jp' => 27,
            ],
        ]);

        $response = $this->actingAs($guru)->get(route('ai.teacher.index'));

        $response->assertOk();
        $response->assertSee('Prosem & Prota');
        $response->assertViewHas('tpatpDocuments');
        $this->assertCount(1, $response->viewData('tpatpDocuments'));
    }

    public function test_guru_can_generate_prosem_prota_from_content(): void
    {
        $guru = $this->makeGuru();

        $response = $this->actingAs($guru)->postJson(route('ai.teacher.prosem-prota.generate'), [
            'content' => $this->sampleTpAtpContent,
            'kelas' => 'VII',
            'jp_per_week' => 3,
            'sumatif_jp' => 2,
            'cadangan_jp_sem1' => 0,
            'cadangan_jp_sem2' => 3,
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure([
                'ok',
                'answer',
                'doc' => [
                    'is_valid',
                    'meta',
                    'prota',
                    'prosem' => ['ganjil', 'genap'],
                ],
                'history' => ['uuid', 'title'],
            ]);

        $this->assertDatabaseHas('ai_teacher_histories', [
            'user_uuid' => $guru->uuid,
            'type' => 'prosem_prota',
        ]);
    }

    public function test_guru_can_generate_prosem_prota_from_saved_source_uuid(): void
    {
        $guru = $this->makeGuru();

        $savedHistory = AiTeacherHistory::create([
            'user_uuid' => $guru->uuid,
            'type' => 'tpatp',
            'type_label' => 'TP & ATP',
            'title' => 'TP & ATP IPS Kelas 7 Tersimpan',
            'excerpt' => 'Ringkasan',
            'answer' => $this->sampleTpAtpContent,
            'metadata' => ['mapel' => 'IPS', 'fase_kelas' => 'D/VII'],
        ]);

        $response = $this->actingAs($guru)->postJson(route('ai.teacher.prosem-prota.generate'), [
            'source_uuid' => $savedHistory->uuid,
            'kelas' => 'VII',
            'jp_per_week' => 3,
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true]);

        $data = $response->json();
        $this->assertEquals('PROGRAM TAHUNAN', $data['doc']['prota']['title']);
        $this->assertCount(2, $data['doc']['prota']['semesters']['semester_1']['babs']);
    }

    public function test_guru_can_preview_prosem_prota(): void
    {
        $guru = $this->makeGuru();

        $response = $this->actingAs($guru)->postJson(route('ai.teacher.prosem-prota.preview'), [
            'content' => $this->sampleTpAtpContent,
            'kelas' => 'VII',
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['ok', 'html', 'doc']);

        $html = $response->json('html');
        $this->assertStringContainsString('PROGRAM TAHUNAN', $html);
        $this->assertStringContainsString('PROGRAM SEMESTER', $html);
        $this->assertStringContainsString('PENILAIAN SUMATIF', $html);
    }

    public function test_guru_can_export_prosem_prota_word(): void
    {
        $guru = $this->makeGuru();

        $response = $this->actingAs($guru)->post(route('ai.teacher.prosem-prota.export-word'), [
            'content' => $this->sampleTpAtpContent,
            'type' => 'both',
            'title' => 'Prosem & Prota IPS Kelas 7',
        ]);

        $response->assertOk();
        $this->assertTrue(str_contains(
            $response->headers->get('content-type', ''),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ));
    }

    public function test_guru_can_export_prosem_prota_pdf(): void
    {
        $guru = $this->makeGuru();

        // Export Prota PDF
        $resProta = $this->actingAs($guru)->post(route('ai.teacher.prosem-prota.export-pdf'), [
            'content' => $this->sampleTpAtpContent,
            'doc_type' => 'prota',
            'title' => 'Program Tahunan IPS Kelas 7',
        ]);

        $resProta->assertOk();
        $this->assertTrue(str_contains($resProta->headers->get('content-type', ''), 'application/pdf'));

        // Export Prosem Ganjil PDF
        $resProsem = $this->actingAs($guru)->post(route('ai.teacher.prosem-prota.export-pdf'), [
            'content' => $this->sampleTpAtpContent,
            'doc_type' => 'prosem_ganjil',
            'title' => 'Program Semester Ganjil IPS Kelas 7',
        ]);

        $resProsem->assertOk();
        $this->assertTrue(str_contains($resProsem->headers->get('content-type', ''), 'application/pdf'));
    }

    public function test_guru_can_preview_from_generated_unified_prosem_prota_text(): void
    {
        $guru = $this->makeGuru();

        // 1. Generate Prosem & Prota
        $genRes = $this->actingAs($guru)->postJson(route('ai.teacher.prosem-prota.generate'), [
            'content' => $this->sampleTpAtpContent,
            'kelas' => 'VII',
            'jp_per_week' => 3,
        ]);
        $genRes->assertOk();
        $this->assertNotEmpty($genRes->json('html'));
        $answerText = $genRes->json('answer');

        // 2. Client calls preview with the generated answer text (which previously caused TOTAL JP 0 JP)
        $prevRes = $this->actingAs($guru)->postJson(route('ai.teacher.prosem-prota.preview'), [
            'content' => $answerText,
            'kelas' => 'VII',
        ]);

        $prevRes->assertOk();
        $doc = $prevRes->json('doc');
        $this->assertTrue($doc['is_valid']);
        $this->assertNotEmpty($doc['prota']['semesters']['semester_1']['babs']);
        $this->assertGreaterThan(0, $doc['prota']['semesters']['semester_1']['total_jp']);

        $html = $prevRes->json('html');
        $this->assertStringNotContainsString('TOTAL JP 0 JP', $html);
        $this->assertStringContainsString('54 JP', $html);
    }

    public function test_guru_can_export_word_from_generated_unified_text(): void
    {
        $guru = $this->makeGuru();

        $genRes = $this->actingAs($guru)->postJson(route('ai.teacher.prosem-prota.generate'), [
            'content' => $this->sampleTpAtpContent,
            'kelas' => 'VII',
        ]);
        $answerText = $genRes->json('answer');

        $response = $this->actingAs($guru)->post(route('ai.teacher.prosem-prota.export-word'), [
            'content' => $answerText,
            'type' => 'both',
            'title' => 'Prosem & Prota IPS Kelas 7',
        ]);

        $response->assertOk();
        $this->assertTrue(str_contains(
            $response->headers->get('content-type', ''),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ));
    }

    public function test_guru_can_generate_and_preview_prosem_with_custom_calendar_events(): void
    {
        $guru = $this->makeGuru();

        $customEventsGanjil = [
            [
                'label' => 'JEDA TENGAH SEMESTER KHUSUS',
                'start_col' => 11,
                'end_col' => 11,
                'color' => '#e9d5ff',
                'text_color' => '#581c87',
            ],
        ];

        $res = $this->actingAs($guru)->postJson(route('ai.teacher.prosem-prota.generate'), [
            'content' => $this->sampleTpAtpContent,
            'kelas' => 'VII',
            'calendar_events_ganjil' => $customEventsGanjil,
        ]);

        $res->assertOk();
        $this->assertDatabaseHas('ai_teacher_histories', [
            'user_uuid' => $guru->uuid,
            'type' => 'prosem_prota',
        ]);

        $html = $res->json('html');
        $this->assertStringContainsString('JEDA TENGAH SEMESTER KHUSUS', $html);

        // Preview endpoint juga bekerja dengan custom calendar
        $prevRes = $this->actingAs($guru)->postJson(route('ai.teacher.prosem-prota.preview'), [
            'content' => $res->json('answer'),
            'kelas' => 'VII',
            'calendar_events_ganjil' => $customEventsGanjil,
        ]);

        $prevRes->assertOk();
        $this->assertStringContainsString('JEDA TENGAH SEMESTER KHUSUS', $prevRes->json('html'));
    }

    public function test_validation_fails_when_content_and_source_uuid_are_empty(): void
    {
        $guru = $this->makeGuru();

        $response = $this->actingAs($guru)->postJson(route('ai.teacher.prosem-prota.generate'), [
            'content' => '',
            'source_uuid' => '',
        ]);

        $response->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_signature_block_reflects_logged_in_user_and_kepala_sekolah_from_database(): void
    {
        \App\Models\Setting::set('wajib_daftar_wajah', '0');

        // 1. Setup user kepala sekolah di database
        $kepalaUser = User::create([
            'username' => 'kepala-sekolah',
            'password' => 'password',
            'access' => 'kepala',
            'name' => 'Dra. Megawati M.Pd.',
        ]);
        Guru::create([
            'id_login' => $kepalaUser->uuid,
            'nama' => 'Dra. Megawati M.Pd.',
            'nik' => '197001012000032001',
        ]);

        // 2. Setup user guru yang sedang login
        $teacherUser = User::create([
            'username' => 'guru-matematika',
            'password' => 'password',
            'access' => 'guru',
            'name' => 'Budi Santoso, S.Pd.',
        ]);
        Guru::create([
            'id_login' => $teacherUser->uuid,
            'nama' => 'Budi Santoso, S.Pd.',
            'nik' => '198505052010011005',
        ]);

        $response = $this->actingAs($teacherUser)->postJson(route('ai.teacher.prosem-prota.generate'), [
            'content' => $this->sampleTpAtpContent,
            'kelas' => 'VII',
        ]);

        $response->assertOk();
        $html = $response->json('html');
        $doc = $response->json('doc');

        // Signature di PROTA & PROSEM mencerminkan nama & NIK kepala sekolah
        $this->assertEquals('Dra. Megawati M.Pd.', $doc['prota']['signature']['kepala_sekolah']);
        $this->assertEquals('197001012000032001', $doc['prota']['signature']['nip_kepala']);

        // Signature di PROTA & PROSEM mencerminkan nama & NIK guru yang login
        $this->assertEquals('Budi Santoso, S.Pd.', $doc['prota']['signature']['guru']);
        $this->assertEquals('198505052010011005', $doc['prota']['signature']['nip_guru']);

        // Muncul di output HTML rendered
        $this->assertStringContainsString('Dra. Megawati M.Pd.', $html);
        $this->assertStringContainsString('197001012000032001', $html);
        $this->assertStringContainsString('Budi Santoso, S.Pd.', $html);
        $this->assertStringContainsString('198505052010011005', $html);
    }

    public function test_prosem_prota_preview_and_exports_carry_dynamic_signature(): void
    {
        \App\Models\Setting::set('wajib_daftar_wajah', '0');

        $kepalaUser = User::create([
            'username' => 'kepala-sekolah-2',
            'password' => 'password',
            'access' => 'kepala',
            'name' => 'Dra. Megawati M.Pd.',
        ]);
        Guru::create([
            'id_login' => $kepalaUser->uuid,
            'nama' => 'Dra. Megawati M.Pd.',
            'nik' => '197001012000032001',
        ]);

        $teacherUser = User::create([
            'username' => 'guru-ipa',
            'password' => 'password',
            'access' => 'guru',
            'name' => 'Siti Nurhaliza, S.Pd.',
        ]);
        Guru::create([
            'id_login' => $teacherUser->uuid,
            'nama' => 'Siti Nurhaliza, S.Pd.',
            'nik' => '199001012020122002',
        ]);

        // Preview
        $prevRes = $this->actingAs($teacherUser)->postJson(route('ai.teacher.prosem-prota.preview'), [
            'content' => $this->sampleTpAtpContent,
            'kelas' => 'VII',
        ]);
        $prevRes->assertOk();
        $this->assertStringContainsString('Dra. Megawati M.Pd.', $prevRes->json('html'));
        $this->assertStringContainsString('Siti Nurhaliza, S.Pd.', $prevRes->json('html'));
        $this->assertStringContainsString('199001012020122002', $prevRes->json('html'));

        // Export Word
        $wordRes = $this->actingAs($teacherUser)->post(route('ai.teacher.prosem-prota.export-word'), [
            'content' => $this->sampleTpAtpContent,
            'kelas' => 'VII',
            'type' => 'both',
        ]);
        $wordRes->assertOk();

        // Export PDF PROTA
        $pdfProtaRes = $this->actingAs($teacherUser)->post(route('ai.teacher.prosem-prota.export-pdf'), [
            'content' => $this->sampleTpAtpContent,
            'kelas' => 'VII',
            'doc_type' => 'prota',
        ]);
        $pdfProtaRes->assertOk();

        // Export PDF PROSEM
        $pdfProsemRes = $this->actingAs($teacherUser)->post(route('ai.teacher.prosem-prota.export-pdf'), [
            'content' => $this->sampleTpAtpContent,
            'kelas' => 'VII',
            'doc_type' => 'prosem_ganjil',
        ]);
        $pdfProsemRes->assertOk();
    }

    public function test_asisten_guru_index_provides_histories_matching_tabs(): void
    {
        $guru = $this->makeGuru();

        // Buat history dari beberapa modul
        AiTeacherHistory::create([
            'user_uuid' => $guru->uuid,
            'type' => 'prosem_prota',
            'type_label' => 'Prosem & Prota',
            'title' => 'Prosem & Prota IPS Kelas VII',
            'excerpt' => 'Ringkasan prosem',
            'answer' => 'Teks Prosem Prota',
        ]);

        AiTeacherHistory::create([
            'user_uuid' => $guru->uuid,
            'type' => 'tpatp',
            'type_label' => 'TP & ATP',
            'title' => 'TP & ATP IPS Kelas VII',
            'excerpt' => 'Ringkasan TP ATP',
            'answer' => 'Teks TP ATP',
        ]);

        AiTeacherHistory::create([
            'user_uuid' => $guru->uuid,
            'type' => 'quiz',
            'type_label' => 'Generator Soal',
            'title' => 'Soal Ulangan IPS',
            'excerpt' => 'Ringkasan Soal',
            'answer' => 'Teks Soal',
        ]);

        $res = $this->actingAs($guru)->get(route('ai.teacher.index', ['tab' => 'prosemprota']));
        $res->assertOk();
        $res->assertSee('History Generate');
        $res->assertSee('tabMatchesHistory');
        $res->assertSee('setHistoryFilter');
        $res->assertSee('displayedHistories');
        $res->assertSee('Prosem');
    }
}
