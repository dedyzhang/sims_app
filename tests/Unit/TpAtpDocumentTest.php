<?php

namespace Tests\Unit;

use App\Support\StandardCurriculumCp;
use App\Support\TpAtpDocument;
use App\Support\TpAtpDocxBuilder;
use Tests\TestCase;

class TpAtpDocumentTest extends TestCase
{
    private string $sampleContent = <<<'TXT'
YAYASAN BUMI MAITRI
SMP MAITREYAWIRA TANJUNGPINANG
TERAKREDITASI A
Komp. Gedung Pendidikan dan Pelatihan Buddhis Bumi Maitreya
Jl. Prof. Ir. Sutami No. 38 Telp (0771) 4505723 Email smpmai.tpi@gmail.com
Tanjungpinang – Kepulauan Riau

TUJUAN PEMBELAJARAN (TP)
MATA PELAJARAN : Ilmu Pengetahuan Sosial
FASE / KELAS : D/VII-IX
TAHUN PELAJARAN : 2026-2027

TABEL TUJUAN PEMBELAJARAN
ELEMEN | CAPAIAN PEMBELAJARAN | LINGKUP MATERI | TUJUAN PEMBELAJARAN (TP) | KODE TP | ALOKASI WAKTU (JP) | SEMESTER
Pemahaman Konsep | Pada akhir Fase D, murid memiliki kemampuan menjelaskan keberagaman kondisi geografis Indonesia... | Diri dan Keluarga di Tengah Lingkungan Sosial | Menjelaskan pengertian dan sejarah asal-usul keluarga, serta memahami bagaimana proses sosialisasi | PK.7.1 | 27 | Ganjil
Pemahaman Konsep | Pada akhir Fase D, murid memiliki kemampuan menjelaskan keberagaman kondisi geografis Indonesia... | Diri dan Keluarga di Tengah Lingkungan Sosial | Memanfaatkan peta untuk menunjukkan lokasi tempat tinggal serta membandingkan karakteristik kondisi geografis | PK.7.2 | 27 | Ganjil
Pemahaman Konsep | Pada akhir Fase D, murid memiliki kemampuan menjelaskan keberagaman kondisi geografis Indonesia... | Keberagaman Lingkungan Sekitar | Mengidentifikasi kondisi lingkungan masyarakat sekitar serta proses interaksi sosial | PK.7.5 | 27 | Ganjil
Pemahaman Konsep | Pada akhir Fase D, murid memiliki kemampuan menjelaskan keberagaman kondisi geografis Indonesia... | Potensi Ekonomi Lingkungan | Menjelaskan potensi sumber daya alam yang tersedia serta faktor penyebab perubahan | PK.7.8 | 24 | Genap

ALUR TUJUAN PEMBELAJARAN (ATP)
MATA PELAJARAN : Ilmu Pengetahuan Sosial
FASE / KELAS : D/VII-IX
TAHUN PELAJARAN : 2026-2027

DIAGRAM ALUR TUJUAN PEMBELAJARAN
TAHAP | KELAS | SEMESTER | KODE | JUDUL ALUR | TIPE
Awal | 7 | Ganjil | - | Awal fase D Kelas 7 Semester Ganjil | start
TP 1 | 7 | Ganjil | P.7.1 | Kesadaran akan keberadaan diri | tp
TP 2 | 7 | Ganjil | P.7.2 | Kondisi geografis daerah | tp
Mid | 7 | Genap | - | Awal fase D Kelas 7 Semester Genap | start
TP 3 | 7 | Genap | P.7.3 | Potensi sumber daya alam | tp
Transisi | 7 | Genap | - | Capaian Pembelajaran Akhir Fase D Kelas 7 menuju pembelajaran fase D kelas 8 Semester Ganjil | transition
TP 5 | 8 | Ganjil | P.8.1 | Masyarakat dalam memenuhi kebutuhan hidupnya | tp
Mid | 8 | Genap | - | Awal fase D Kelas 8 Semester Genap | start
TP 7 | 8 | Genap | P.8.3 | Kesadaran perubahan sosial di era kontemporer | tp
Transisi | 8 | Genap | - | Capaian Pembelajaran Akhir Fase D Kelas 8 menuju pembelajaran fase D kelas 9 Semester Ganjil | transition
TP 8 | 9 | Ganjil | P.9.1 | Perkembangan ekonomi di era digital | tp
Mid | 9 | Genap | - | Awal fase D Kelas 9 Semester Genap | start
TP 10 | 9 | Genap | P.9.3 | Menyadari perannya sebagai bagian dari masyarakat indonesia | tp
Akhir | 9 | Genap | - | Capaian Pembelajaran Akhir Fase D Kelas 9 | end

TABEL ALUR TUJUAN PEMBELAJARAN
NO | KELAS | SEMESTER | LINGKUP MATERI | KODE TP | TUJUAN PEMBELAJARAN | ALOKASI JP
1 | 7 | Ganjil | Diri dan Keluarga | PK.7.1 | Menjelaskan pengertian dan sejarah keluarga | 27
2 | 7 | Ganjil | Diri dan Keluarga | PK.7.2 | Memanfaatkan peta untuk lokasi | 27

TANDA TANGAN:
Tanjungpinang, 17 Juli 2026
Guru Mata Pelajaran

Desmalini, S.E., Gr.
NIK. 10090150308
TXT;

    public function test_looks_like(): void
    {
        $this->assertTrue(TpAtpDocument::looksLike($this->sampleContent));
        $this->assertTrue(TpAtpDocument::looksLike("DOKUMEN TUJUAN PEMBELAJARAN (TP)"));
        $this->assertTrue(TpAtpDocument::looksLike("ALUR TUJUAN PEMBELAJARAN (ATP)"));
        $this->assertFalse(TpAtpDocument::looksLike("SOAL EVALUASI MATEMATIKA\n1. Hitung 2+2"));
    }

    public function test_parse_structured_tp_and_atp(): void
    {
        $doc = TpAtpDocument::parse($this->sampleContent);

        $this->assertTrue($doc['parsed']);
        $this->assertTrue($doc['has_tp']);
        $this->assertTrue($doc['has_atp']);

        // Identitas
        $this->assertSame('Ilmu Pengetahuan Sosial', $doc['identity']['mapel']);
        $this->assertSame('D/VII-IX', $doc['identity']['fase_kelas']);
        $this->assertSame('2026-2027', $doc['identity']['tahun']);

        // Baris TP
        $this->assertCount(4, $doc['tp_table']['rows']);
        $this->assertSame('PK.7.1', $doc['tp_table']['rows'][0]['kode_tp']);
        $this->assertSame('27', $doc['tp_table']['rows'][0]['jp']);
        $this->assertSame('Ganjil', $doc['tp_table']['rows'][0]['semester']);

        // Grup Elemen
        $this->assertCount(1, $doc['tp_table']['elements']);
        $elemen = $doc['tp_table']['elements'][0];
        $this->assertSame('Pemahaman Konsep', $elemen['nama']);
        $this->assertSame(4, $elemen['rowspan']);
        $this->assertCount(3, $elemen['materi_groups']);

        // Diagram Alur ATP
        $this->assertNotEmpty($doc['atp_diagram']['nodes']);
        $byGrade = $doc['atp_diagram']['by_grade'];
        $this->assertNotEmpty($byGrade[7]);
        $this->assertNotEmpty($byGrade[8]);
        $this->assertNotEmpty($byGrade[9]);

        // Tanda tangan
        $this->assertStringContainsString('Tanjungpinang, 17 Juli 2026', $doc['signature']['place_date']);
        $this->assertSame('Desmalini, S.E., Gr.', $doc['signature']['name']);
        $this->assertSame('10090150308', $doc['signature']['nip']);
    }

    public function test_docx_builder_can_generate_valid_package(): void
    {
        $doc = TpAtpDocument::parse($this->sampleContent);
        $tempPath = tempnam(sys_get_temp_dir(), 'test_tpatp_docx_');

        try {
            $written = TpAtpDocxBuilder::write($tempPath, $doc);
            $this->assertTrue($written);
            $this->assertFileExists($tempPath);
            $this->assertGreaterThan(1000, filesize($tempPath));

            // Periksa bahwa file adalah ZIP valid (struktur docx)
            $zip = new \ZipArchive();
            $opened = $zip->open($tempPath);
            $this->assertTrue($opened === true);
            $documentXml = $zip->getFromName('word/document.xml');
            $this->assertNotFalse($documentXml);
            $this->assertStringContainsString('TUJUAN PEMBELAJARAN', $documentXml);
            $this->assertStringContainsString('Ilmu Pengetahuan Sosial', $documentXml);
            $this->assertStringContainsString('PK.7.1', $documentXml);

            // Flowchart visual sebagai tabel kotak-kotak berwarna (Gambar 1)
            $this->assertStringContainsString('EDE9FE', $documentXml); // Start node lavender
            $this->assertStringContainsString('FEE2E2', $documentXml); // Kelas 7 TP node pink
            $this->assertStringContainsString('FFEDD5', $documentXml); // Kelas 8 TP node orange
            $this->assertStringContainsString('DCFCE7', $documentXml); // Kelas 9 TP node green
            $this->assertStringContainsString('FED7AA', $documentXml); // Transisi node peach
            $this->assertStringContainsString('BBF7D0', $documentXml); // Akhir fase node green
            $this->assertStringContainsString('➔', $documentXml); // Panah horizontal antar node
            $this->assertStringContainsString('w:vAlign w:val="center"', $documentXml);
            $this->assertStringContainsString('Ringkasan Alur Tujuan Pembelajaran', $documentXml);

            // Memastikan teks lama (paragraf kurung siku dan label berulang) tidak muncul
            $this->assertStringNotContainsString('DIAGRAM ALUR TUJUAN PEMBELAJARAN (FLOWCHART)', $documentXml);
            $this->assertStringNotContainsString('KELAS VII (FASE D)', $documentXml);
            $this->assertStringNotContainsString('[ Awal fase D', $documentXml);

            $zip->close();
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function test_standard_curriculum_cp_knowledge_base(): void
    {
        $all = StandardCurriculumCp::all();
        $this->assertArrayHasKey('ips', $all);
        $this->assertArrayHasKey('ipa', $all);
        $this->assertArrayHasKey('mtk', $all);
        $this->assertArrayHasKey('bin', $all);

        $foundIps = StandardCurriculumCp::find('Ilmu Pengetahuan Sosial');
        $this->assertNotNull($foundIps);
        $this->assertSame('IPS', $foundIps['kode']);
        $this->assertStringContainsString('keberagaman kondisi geografis Indonesia', $foundIps['cp_utama']);

        $foundMtk = StandardCurriculumCp::find('MTK');
        $this->assertNotNull($foundMtk);
        $this->assertSame('Matematika', $foundMtk['nama']);
    }

    public function test_parse_tp_only(): void
    {
        $tpOnly = <<<'TXT'
TUJUAN PEMBELAJARAN (TP)
MATA PELAJARAN : IPA
FASE / KELAS : D/VII
TAHUN PELAJARAN : 2026-2027

TABEL TUJUAN PEMBELAJARAN
ELEMEN | CAPAIAN PEMBELAJARAN | LINGKUP MATERI | TUJUAN PEMBELAJARAN (TP) | KODE TP | ALOKASI WAKTU (JP) | SEMESTER
Pemahaman IPA | Mengidentifikasi interaksi makhluk hidup | Ekosistem | Mengidentifikasi komponen biotik dan abiotik | IPA.7.1 | 20 | Ganjil
Pemahaman IPA | Mengidentifikasi interaksi makhluk hidup | Ekosistem | Menganalisis rantai makanan dan jaring-jaring makanan | IPA.7.2 | 20 | Ganjil

TANDA TANGAN:
Tanjungpinang, 10 Juli 2026
Guru Mata Pelajaran

Budi Santoso, S.Pd.
NIP. 198001012005011001
TXT;

        $doc = TpAtpDocument::parse($tpOnly);
        $this->assertTrue($doc['parsed']);
        $this->assertTrue($doc['has_tp']);
        $this->assertFalse($doc['has_atp']);
        $this->assertCount(2, $doc['tp_table']['rows']);
        $this->assertSame('IPA', $doc['identity']['mapel']);
        $this->assertSame('Budi Santoso, S.Pd.', $doc['signature']['name']);
    }

    public function test_parse_atp_only(): void
    {
        $atpOnly = <<<'TXT'
ALUR TUJUAN PEMBELAJARAN (ATP)
MATA PELAJARAN : Matematika
FASE / KELAS : D/VII-IX
TAHUN PELAJARAN : 2026-2027

DIAGRAM ALUR TUJUAN PEMBELAJARAN
TAHAP | KELAS | SEMESTER | KODE | JUDUL ALUR | TIPE
Awal | 7 | Ganjil | - | Awal fase D Kelas 7 Semester Ganjil | start
TP 1 | 7 | Ganjil | MTK.7.1 | Operasi bilangan bulat | tp
Transisi | 7 | Genap | - | Transisi menuju kelas 8 | transition
TP 2 | 8 | Ganjil | MTK.8.1 | Teorema Pythagoras | tp
Akhir | 9 | Genap | - | Capaian Pembelajaran Akhir Fase D Kelas 9 | end
TXT;

        $doc = TpAtpDocument::parse($atpOnly);
        $this->assertTrue($doc['parsed']);
        $this->assertFalse($doc['has_tp']);
        $this->assertTrue($doc['has_atp']);
        $this->assertCount(5, $doc['atp_diagram']['nodes']);
        $this->assertNotEmpty($doc['atp_diagram']['by_grade'][7]);
        $this->assertNotEmpty($doc['atp_diagram']['by_grade'][8]);
    }

    public function test_docx_builder_splits_flowchart_into_multiple_rows_and_details_lingkup_materi(): void
    {
        // Konten dengan 13 node dalam satu jenjang (Kelas 7) seperti keluhan pengguna
        $content = <<<'TXT'
TUJUAN PEMBELAJARAN (TP)
MATA PELAJARAN : Ilmu Pengetahuan Sosial
FASE / KELAS : D/VII
TAHUN PELAJARAN : 2026-2027

TABEL TUJUAN PEMBELAJARAN
ELEMEN | CAPAIAN PEMBELAJARAN | LINGKUP MATERI | TUJUAN PEMBELAJARAN (TP) | KODE TP | ALOKASI WAKTU (JP) | SEMESTER
Pemahaman Konsep | CP IPS | Keberagaman Geografis dan SDA | Menjelaskan kondisi geografis serta potensinya | PK.7.1 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Perubahan Iklim dan Bencana | Menganalisis dampak perubahan iklim | PK.7.2 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Adaptasi Iklim dan SDGs | Memprediksi upaya adaptasi iklim | PK.7.3 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Konsep Dasar Ilmu Sejarah | Menguraikan konsep sinkronis dan diakronis | PK.7.4 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Asal-Usul dan Jalur Rempah | Menganalisis dinamika jalur rempah nusantara | PK.7.5 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Interaksi dan Lembaga Sosial | Menjelaskan fungsi lembaga sosial di masyarakat | PK.7.6 | 27 | Genap
Pemahaman Konsep | CP IPS | Dinamika Sosial dan Kebhinekaan | Mengidentifikasi bentuk interaksi disosiatif dan asosiatif | PK.7.7 | 27 | Genap
Pemahaman Konsep | CP IPS | Kegiatan Ekonomi dan Pasar | Menganalisis permintaan dan penawaran | PK.7.8 | 27 | Genap
Pemahaman Konsep | CP IPS | Ekonomi Digital dan Negara Maju | Membandingkan indikator negara maju | PK.7.9 | 27 | Genap

ALUR TUJUAN PEMBELAJARAN (ATP)
MATA PELAJARAN : Ilmu Pengetahuan Sosial
FASE / KELAS : D/VII
TAHUN PELAJARAN : 2026-2027

DIAGRAM ALUR TUJUAN PEMBELAJARAN
TAHAP | KELAS | SEMESTER | KODE | LINGKUP MATERI | TUJUAN PEMBELAJARAN | TIPE
Awal | 7 | Ganjil | - | - | Awal fase D Kelas 7 Semester Ganjil | start
TP 1 | 7 | Ganjil | PK.7.1 | Keberagaman Geografis dan SDA | Menjelaskan kondisi geografis serta potensinya | tp
TP 2 | 7 | Ganjil | PK.7.2 | Perubahan Iklim dan Bencana | Menganalisis dampak perubahan iklim | tp
TP 3 | 7 | Ganjil | PK.7.3 | Adaptasi Iklim dan SDGs | Memprediksi upaya adaptasi iklim | tp
TP 4 | 7 | Ganjil | PK.7.4 | Konsep Dasar Ilmu Sejarah | Menguraikan konsep sinkronis dan diakronis | tp
TP 5 | 7 | Ganjil | PK.7.5 | Asal-Usul dan Jalur Rempah | Menganalisis dinamika jalur rempah nusantara | tp
Mid | 7 | Ganjil | - | - | Evaluasi dan Refleksi Tengah Fase Kelas 7 Ganjil ke Genap | transition
Awal 2 | 7 | Genap | - | - | Awal fase D Kelas 7 Semester Genap | start
TP 6 | 7 | Genap | PK.7.6 | Interaksi dan Lembaga Sosial | Menjelaskan fungsi lembaga sosial di masyarakat | tp
TP 7 | 7 | Genap | PK.7.7 | Dinamika Sosial dan Kebhinekaan | Mengidentifikasi bentuk interaksi disosiatif | tp
TP 8 | 7 | Genap | PK.7.8 | Kegiatan Ekonomi dan Pasar | Menganalisis permintaan dan penawaran | tp
TP 9 | 7 | Genap | PK.7.9 | Ekonomi Digital dan Negara Maju | Membandingkan indikator negara maju | tp
Akhir | 7 | Genap | - | - | Capaian Pembelajaran Akhir Fase D Kelas 7 | end

TANDA TANGAN:
Tanjungpinang, 17 Juli 2026
Guru Mata Pelajaran

Desmalini, S.E., Gr.
NIK. 10090150308
TXT;

        $doc = TpAtpDocument::parse($content);
        $tempPath = tempnam(sys_get_temp_dir(), 'test_multi_row_');

        try {
            $written = TpAtpDocxBuilder::write($tempPath, $doc);
            $this->assertTrue($written);

            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($tempPath) === true);
            $xml = $zip->getFromName('word/document.xml');
            $this->assertNotFalse($xml);

            // 1. Memastikan tidak dijadikan 1 baris saja: ada panah penghubung ke bawah (↓) antar-baris
            $this->assertStringContainsString('↓', $xml);

            // 2. Lingkup Materi dijabarkan di dalam kotak TP
            $this->assertStringContainsString('Materi: Keberagaman Geografis dan SDA', $xml);
            $this->assertStringContainsString('Materi: Interaksi dan Lembaga Sosial', $xml);

            // 3. Uraian TP dan Kode TP juga muncul
            $this->assertStringContainsString('(PK.7.1)', $xml);
            $this->assertStringContainsString('(PK.7.9)', $xml);

            $zip->close();
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function test_atp_nodes_do_not_contain_isolated_hyphen_titles(): void
    {
        $raw = <<<TXT
DIAGRAM ALUR TUJUAN PEMBELAJARAN
TAHAP | KELAS | SEMESTER | KODE | LINGKUP MATERI | TUJUAN PEMBELAJARAN | TIPE
Awal | 7 | Ganjil | - | - | Awal fase D Kelas 7 Semester Ganjil | start
TP 1 | 7 | Ganjil | PK.7.1 | Keberagaman Alam | Menjelaskan kondisi geologis | tp
Transisi | 7 | Genap | - | - | - | transition
Akhir | 9 | Genap | - | - | - | end
TXT;

        $doc = TpAtpDocument::parse($raw);
        $nodes = $doc['atp_diagram']['nodes'];
        $this->assertCount(4, $nodes);

        // Start node tidak boleh '-'
        $this->assertSame('Awal fase D Kelas 7 Semester Ganjil', $nodes[0]['judul']);
        $this->assertNotSame('-', $nodes[0]['judul']);

        // Transisi node dengan '-' harus di-fallback otomatis, bukan '-'
        $this->assertNotSame('-', $nodes[2]['judul']);
        $this->assertStringContainsString('Capaian Pembelajaran Akhir Fase D Kelas 7', $nodes[2]['judul']);

        // End node dengan '-' harus di-fallback otomatis, bukan '-'
        $this->assertNotSame('-', $nodes[3]['judul']);
        $this->assertSame('Capaian Pembelajaran Akhir Fase D', $nodes[3]['judul']);
    }

    public function test_materi_group_calculates_total_jp_when_sub_divided_per_tp(): void
    {
        $raw = <<<TXT
TABEL TUJUAN PEMBELAJARAN
ELEMEN | CAPAIAN PEMBELAJARAN | LINGKUP MATERI | TUJUAN PEMBELAJARAN (TP) | KODE TP | ALOKASI WAKTU (JP) | SEMESTER
Pemahaman Konsep | CP IPS | Diri dan Keluarga di Tengah Lingkungan Sosial | TP 1 Menjelaskan konsep sejarah diri | PK.7.1 | 9 JP | Ganjil
Pemahaman Konsep | CP IPS | Diri dan Keluarga di Tengah Lingkungan Sosial | TP 2 Mengelaborasi interaksi keluarga | PK.7.2 | 9 JP | Ganjil
Pemahaman Konsep | CP IPS | Diri dan Keluarga di Tengah Lingkungan Sosial | TP 3 Menganalisis toponimi wilayah | PK.7.3 | 9 JP | Ganjil
Pemahaman Konsep | CP IPS | Keberagaman Lingkungan Sekitar | TP 4 Menjelaskan kondisi geografis | PK.7.4 | 27 JP | Ganjil
Pemahaman Konsep | CP IPS | Keberagaman Lingkungan Sekitar | TP 5 Menganalisis potensi alam | PK.7.5 | 27 JP | Ganjil
TXT;

        $doc = TpAtpDocument::parse($raw);
        $elements = $doc['tp_table']['elements'];
        $this->assertCount(1, $elements);

        $materiGroups = $elements[0]['materi_groups'];
        $this->assertCount(2, $materiGroups);

        // Kasus 1: Baris-baris TP dibagi 9 JP, 9 JP, 9 JP -> Harus terakumulasi menjadi 27 JP
        $this->assertSame('Diri dan Keluarga di Tengah Lingkungan Sosial', $materiGroups[0]['nama']);
        $this->assertSame('27 JP', $materiGroups[0]['jp']);
        $this->assertSame(3, $materiGroups[0]['rowspan']);

        // Kasus 2: Baris-baris TP sudah bernilai total 27 JP -> Tetap 27 JP (bukan 54 JP)
        $this->assertSame('Keberagaman Lingkungan Sekitar', $materiGroups[1]['nama']);
        $this->assertSame('27 JP', $materiGroups[1]['jp']);
        $this->assertSame(2, $materiGroups[1]['rowspan']);
    }

    public function test_elemen_and_cp_are_merged_across_multiple_materi_groups_when_identical(): void
    {
        $raw = <<<TXT
TABEL TUJUAN PEMBELAJARAN
ELEMEN | CAPAIAN PEMBELAJARAN | LINGKUP MATERI | TUJUAN PEMBELAJARAN (TP) | KODE TP | ALOKASI WAKTU (JP) | SEMESTER
Pemahaman Konsep | Pada akhir Fase D murid memahami keberagaman geografis | Lingkup Materi A | TP A.1 | PK.7.1 | 27 | Ganjil
Pemahaman Konsep | Pada akhir Fase D murid memahami keberagaman geografis | Lingkup Materi A | TP A.2 | PK.7.2 | 27 | Ganjil
Pemahaman Konsep | Pada akhir Fase D murid memahami keberagaman geografis | Lingkup Materi B | TP B.1 | PK.7.3 | 27 | Ganjil
Pemahaman Konsep | Pada akhir Fase D murid memahami keberagaman geografis | Lingkup Materi B | TP B.2 | PK.7.4 | 27 | Ganjil
TXT;

        $doc = TpAtpDocument::parse($raw);
        $this->assertCount(1, $doc['tp_table']['elements']);
        $el = $doc['tp_table']['elements'][0];
        $this->assertSame('Pemahaman Konsep', $el['nama']);
        $this->assertSame('Pada akhir Fase D murid memahami keberagaman geografis', $el['cp']);
        $this->assertSame(4, $el['rowspan']);
        $this->assertCount(2, $el['materi_groups']);
        $this->assertSame(2, $el['materi_groups'][0]['rowspan']);
        $this->assertSame(2, $el['materi_groups'][1]['rowspan']);

        // Uji ekspor Docx: kolom Elemen dan CP harus dimerge (vmerge restart hanya di baris pertama elemen)
        $tempPath = tempnam(sys_get_temp_dir(), 'test_merge_docx_');
        try {
            $written = TpAtpDocxBuilder::write($tempPath, $doc);
            $this->assertTrue($written);

            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($tempPath) === true);
            $xml = $zip->getFromName('word/document.xml');
            $this->assertNotFalse($xml);

            // Teks Elemen dan CP hanya boleh muncul 1 kali di dalam dokumen XML tabel
            $this->assertSame(1, substr_count($xml, 'Pemahaman Konsep'));
            $this->assertSame(1, substr_count($xml, 'Pada akhir Fase D murid memahami keberagaman geografis'));

            $zip->close();
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function test_chunk_elements_for_pdf_splits_by_grade_for_three_levels(): void
    {
        $raw = <<<TXT
TABEL TUJUAN PEMBELAJARAN
ELEMEN | CAPAIAN PEMBELAJARAN | LINGKUP MATERI | TUJUAN PEMBELAJARAN (TP) | KODE TP | ALOKASI WAKTU (JP) | SEMESTER
Pemahaman Konsep | CP IPS | Materi 7 | TP 7.1 | PK.7.1.1 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 7 | TP 7.2 | PK.7.1.2 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 7 | TP 7.3 | PK.7.1.3 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 7 | TP 7.4 | PK.7.1.4 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 7 | TP 7.5 | PK.7.1.5 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 7 | TP 7.6 | PK.7.1.6 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 8 | TP 8.1 | PK.8.1.1 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 8 | TP 8.2 | PK.8.1.2 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 8 | TP 8.3 | PK.8.1.3 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 8 | TP 8.4 | PK.8.1.4 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 8 | TP 8.5 | PK.8.1.5 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 9 | TP 9.1 | PK.9.1.1 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 9 | TP 9.2 | PK.9.1.2 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 9 | TP 9.3 | PK.9.1.3 | 27 | Ganjil
Pemahaman Konsep | CP IPS | Materi 9 | TP 9.4 | PK.9.1.4 | 27 | Ganjil
TXT;

        $doc = TpAtpDocument::parse($raw);
        $chunks = $doc['tp_table']['pdf_chunks'] ?? [];

        // Harus terbagi menjadi 3 chunk terpisah berdasarkan jenjang kelas (7, 8, 9)
        $this->assertCount(3, $chunks);
        $this->assertSame(7, $chunks[0]['grade']);
        $this->assertSame('Fase D — Kelas VII', $chunks[0]['grade_label']);
        $this->assertSame(6, $chunks[0]['elements'][0]['rowspan']);

        $this->assertSame(8, $chunks[1]['grade']);
        $this->assertSame('Fase D — Kelas VIII', $chunks[1]['grade_label']);
        $this->assertSame(5, $chunks[1]['elements'][0]['rowspan']);

        $this->assertSame(9, $chunks[2]['grade']);
        $this->assertSame('Fase D — Kelas IX', $chunks[2]['grade_label']);
        $this->assertSame(4, $chunks[2]['elements'][0]['rowspan']);
    }

    public function test_atp_summary_rows_distributes_total_jp_proportionally_per_tp(): void
    {
        // Kasus persis screenshot pengguna: 18 JP per materi dengan 2 butir TP
        $raw = <<<TXT
TABEL TUJUAN PEMBELAJARAN
ELEMEN | CAPAIAN PEMBELAJARAN | LINGKUP MATERI | TUJUAN PEMBELAJARAN (TP) | KODE TP | ALOKASI WAKTU (JP) | SEMESTER
Berpikir Komputasional | CP Informatika | Penerapan Berpikir Komputasional dan Algoritma Problem Solving | TP 1 | BK.7.1 | 18 JP | Ganjil
Berpikir Komputasional | CP Informatika | Penerapan Berpikir Komputasional dan Algoritma Problem Solving | TP 2 | BK.7.2 | 18 JP | Ganjil
Berpikir Komputasional | CP Informatika | Algoritma dan Pemrograman | TP 3 | AP.7.1 | 27 JP | Genap
Berpikir Komputasional | CP Informatika | Algoritma dan Pemrograman | TP 4 | AP.7.2 | 27 JP | Genap
Berpikir Komputasional | CP Informatika | Algoritma dan Pemrograman | TP 5 | AP.7.3 | 27 JP | Genap

TABEL ALUR TUJUAN PEMBELAJARAN
NO | KELAS | SEMESTER | LINGKUP MATERI | KODE TP | TUJUAN PEMBELAJARAN | ALOKASI JP
1 | 7 | Ganjil | Penerapan Berpikir Komputasional dan Algoritma Problem Solving | BK.7.1 | TP 1 | 18 JP
2 | 7 | Ganjil | Penerapan Berpikir Komputasional dan Algoritma Problem Solving | BK.7.2 | TP 2 | 18 JP
3 | 7 | Genap | Algoritma dan Pemrograman | AP.7.1 | TP 3 | 27 JP
4 | 7 | Genap | Algoritma dan Pemrograman | AP.7.2 | TP 4 | 27 JP
5 | 7 | Genap | Algoritma dan Pemrograman | AP.7.3 | TP 5 | 27 JP
TXT;

        $doc = TpAtpDocument::parse($raw);
        $summary = $doc['atp_diagram']['summary_rows'];
        $this->assertCount(5, $summary);

        // Materi 1: Total 18 JP dibagi 2 TP => masing-masing 9 JP (bukan 18 JP per TP)
        $this->assertSame('9 JP', $summary[0]['jp']);
        $this->assertSame('9 JP', $summary[1]['jp']);

        // Materi 2: Total 27 JP dibagi 3 TP => masing-masing 9 JP (bukan 27 JP per TP)
        $this->assertSame('9 JP', $summary[2]['jp']);
        $this->assertSame('9 JP', $summary[3]['jp']);
        $this->assertSame('9 JP', $summary[4]['jp']);
    }
}
