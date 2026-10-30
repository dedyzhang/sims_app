<?php

namespace Tests\Unit;

use App\Support\ProsemProtaDocument;
use App\Support\TpAtpDocument;
use Tests\TestCase;

class ProsemProtaDocumentTest extends TestCase
{
    private function sampleTpAtpText(): string
    {
        return <<<TXT
KOP SURAT RESMI SEKOLAH
SMP MAITREYAWIRA TANJUNGPINANG
TERAKREDITASI A

TUJUAN PEMBELAJARAN (TP)
MATA PELAJARAN : ILMU PENGETAHUAN SOSIAL
FASE / KELAS : D/VII
TAHUN PELAJARAN : 2026/2027

ELEMEN | CAPAIAN PEMBELAJARAN | LINGKUP MATERI | TUJUAN PEMBELAJARAN | KODE TP | ALOKASI WAKTU (JP) | SEMESTER
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial... | Diri dan Keluarga di Tengah Lingkungan Sosial | Menjelaskan pengertian dan sejarah asal-usul keluarga | PK.7.1 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial... | Diri dan Keluarga di Tengah Lingkungan Sosial | Memanfaatkan peta untuk menunjukkan lokasi tempat tinggal | PK.7.2 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial... | Diri dan Keluarga di Tengah Lingkungan Sosial | Menganalisis beragam kebutuhan manusia | PK.7.3 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial... | Diri dan Keluarga di Tengah Lingkungan Sosial | Menjelaskan pentingnya melakukan interaksi antarwilayah | PK.7.4 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial... | Keberagaman Lingkungan Sekitar | Mengidentifikasi kondisi lingkungan masyarakat sekitar | PK.7.5 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial... | Keberagaman Lingkungan Sekitar | Mengidentifikasi karakteristik lingkungan alam sekitar | PK.7.6 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial... | Keberagaman Lingkungan Sekitar | Menjelaskan berbagai permasalahan terkait kelangkaan sumber daya | PK.7.7 | 27 JP | Ganjil
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial... | Potensi Ekonomi Lingkungan | Menjelaskan potensi sumber daya alam yang tersedia | PK.7.8 | 25 JP | Genap
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial... | Potensi Ekonomi Lingkungan | Menguraikan dinamika perkembangan Hindu-Buddha | PK.7.9 | 25 JP | Genap
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial... | Potensi Ekonomi Lingkungan | Mengidentifikasi ragam kegiatan ekonomi | PK.7.10 | 25 JP | Genap
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial... | Potensi Ekonomi Lingkungan | Menjelaskan konsep status dan peran sosial | PK.7.11 | 25 JP | Genap
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial... | Pemberdayaan Masyarakat | Mengidentifikasi keterkaitan kondisi geografis dan sosial budaya | PK.7.12 | 24 JP | Genap
Pemahaman Konsep | Peserta didik memahami keberagaman kondisi geografis dan sosial... | Pemberdayaan Masyarakat | Menjelaskan metode pemberdayaan masyarakat | PK.7.13 | 24 JP | Genap

Mengetahui,
Kepala SMPS Maitreyawira Tanjungpinang
Dra. Megawati
NIK. 10010070010

Tanjungpinang, 17 Juli 2026
Guru Mata Pelajaran
Desmalini, S.E., Gr.
NIK. 10090150308
TXT;
    }

    public function test_builds_prota_and_prosem_correctly_from_tpatp(): void
    {
        $built = ProsemProtaDocument::buildFromTpAtp($this->sampleTpAtpText(), [
            'jp_per_week' => 3,
            'sumatif_jp' => 2,
            'cadangan_jp_sem1' => 0,
            'cadangan_jp_sem2' => 3,
        ]);

        $this->assertTrue($built['is_valid']);
        $this->assertEquals('ILMU PENGETAHUAN SOSIAL', $built['meta']['mapel']);
        $this->assertEquals('VII', $built['meta']['kelas']);
        $this->assertEquals('2026/2027', $built['meta']['tahun']);

        // Test Prota Structure
        $prota = $built['prota'];
        $this->assertEquals('PROGRAM TAHUNAN', $prota['title']);
        $this->assertEquals('KELAS VII', $prota['kelas']);
        $this->assertArrayHasKey('semester_1', $prota['semesters']);
        $this->assertArrayHasKey('semester_2', $prota['semesters']);

        // Semester 1: Bab 1 (27 JP total: 25 JP TP + 2 JP Sumatif) + Bab 2 (27 JP total: 25 JP TP + 2 JP Sumatif) = 54 JP
        $sem1 = $prota['semesters']['semester_1'];
        $this->assertCount(2, $sem1['babs']);
        $this->assertEquals('BAB 1 : Diri dan Keluarga di Tengah Lingkungan Sosial', $sem1['babs'][0]['bab_title']);
        $this->assertEquals(27, $sem1['babs'][0]['alokasi_jp']);
        $this->assertEquals(25, $sem1['babs'][0]['tp_alokasi_jp']);
        $this->assertEquals(2, $sem1['babs'][0]['sumatif_jp']);
        $this->assertCount(4, $sem1['babs'][0]['tps']);
        $this->assertEquals(27, $sem1['babs'][1]['alokasi_jp']);
        $this->assertEquals(25, $sem1['babs'][1]['tp_alokasi_jp']);
        $this->assertEquals(2, $sem1['babs'][1]['sumatif_jp']);
        $this->assertCount(3, $sem1['babs'][1]['tps']);
        $this->assertEquals(54, $sem1['total_jp']);

        // Semester 2: Bab 3 (25 JP total) + Bab 4 (24 JP total) + Cadangan (3 JP) = 52 JP
        $sem2 = $prota['semesters']['semester_2'];
        $this->assertCount(2, $sem2['babs']);
        $this->assertEquals(25, $sem2['babs'][0]['alokasi_jp']);
        $this->assertEquals(23, $sem2['babs'][0]['tp_alokasi_jp']);
        $this->assertEquals(24, $sem2['babs'][1]['alokasi_jp']);
        $this->assertEquals(22, $sem2['babs'][1]['tp_alokasi_jp']);
        $this->assertEquals(3, $sem2['cadangan_jp']);
        $this->assertEquals(52, $sem2['total_jp']);

        // Test Prosem Ganjil Structure
        $prosemGanjil = $built['prosem']['ganjil'];
        $this->assertEquals('PROGRAM SEMESTER GANJIL KELAS VII', $prosemGanjil['title']);
        $this->assertEquals(26, $prosemGanjil['total_week_cols']);
        $this->assertCount(6, $prosemGanjil['months']); // Juli .. Desember
        $this->assertNotEmpty($prosemGanjil['calendar_events']);

        // Check non-KBM blocks: Juli M1-M4 and Juli M5 are blocked
        $this->assertArrayHasKey(0, $prosemGanjil['blocked_col_map']);
        $this->assertArrayHasKey(3, $prosemGanjil['blocked_col_map']);
        $this->assertArrayHasKey(4, $prosemGanjil['blocked_col_map']);
        // Agustus M1 (index 5) is active
        $this->assertArrayNotHasKey(5, $prosemGanjil['blocked_col_map']);
        $this->assertContains(5, $prosemGanjil['active_week_cols']);

        // Verify weekly hours allocated to TP 1 on active weeks
        $bab1 = $prosemGanjil['babs'][0];
        $tp1 = $bab1['tps'][0];
        $this->assertNotEmpty($tp1['weeks']);
        $this->assertEquals(6, $tp1['target_jp']);
        $this->assertEquals([5 => 3, 6 => 3], $tp1['weeks']);

        // Verify TP 4 is 7 JP and only shares week 13 with Sumatif (1 JP + 2 JP = 3 JP)
        $tp4 = $bab1['tps'][3];
        $this->assertEquals(7, $tp4['target_jp']);
        $this->assertEquals([11 => 3, 12 => 3, 13 => 1], $tp4['weeks']);
        $this->assertEquals([13 => 2], $bab1['sumatif_weeks']);

        // Sum of all weeks for Bab 1 TPs (25 JP) + Sumatif (2 JP) equals Bab 1 total (27 JP)
        $bab1TpSum = 0;
        foreach ($bab1['tps'] as $t) {
            $bab1TpSum += array_sum($t['weeks']);
        }
        $this->assertEquals(25, $bab1TpSum);
        $bab1SumatifSum = array_sum($bab1['sumatif_weeks']);
        $this->assertEquals(2, $bab1SumatifSum);
        $this->assertEquals(27, $bab1TpSum + $bab1SumatifSum);

        // Test Docx Builder package generation
        $tmp = tempnam(sys_get_temp_dir(), 'prosem_docx_');
        $written = \App\Support\ProsemProtaDocxBuilder::write($tmp, $built, 'both');
        $this->assertTrue($written);
        $this->assertFileExists($tmp);
        $this->assertGreaterThan(1000, filesize($tmp));
        @unlink($tmp);
    }

    public function test_default_calendar_events_and_week_columns(): void
    {
        $colsGanjil = ProsemProtaDocument::getWeekColumns(true);
        $this->assertCount(26, $colsGanjil);
        $this->assertEquals('Juli', $colsGanjil[0]['month']);
        $this->assertEquals(1, $colsGanjil[0]['week_num']);
        $this->assertEquals('Juli M1', $colsGanjil[0]['label']);
        $this->assertEquals('Desember M4', $colsGanjil[25]['label']);

        $colsGenap = ProsemProtaDocument::getWeekColumns(false);
        $this->assertCount(26, $colsGenap);
        $this->assertEquals('Januari', $colsGenap[0]['month']);
        $this->assertEquals('Juni M4', $colsGenap[25]['label']);

        $defGanjil = ProsemProtaDocument::getDefaultCalendarEvents(true);
        $this->assertNotEmpty($defGanjil);
        $this->assertEquals(0, $defGanjil[0]['start_col']);
        $this->assertArrayHasKey('end_col', $defGanjil[0]);

        $defGenap = ProsemProtaDocument::getDefaultCalendarEvents(false);
        $this->assertNotEmpty($defGenap);
        $this->assertEquals(0, $defGenap[0]['start_col']);
    }

    public function test_normalize_custom_calendar_events(): void
    {
        $rawEvents = [
            [
                'label' => 'Jeda Tengah Semester',
                'start_col' => 10,
                'end_col' => 11,
                'color' => 'a7f3d0',
            ],
            [
                'label' => 'MPLS Sekolah',
                'start_col' => 4,
                'col_span' => 1,
            ],
        ];

        $norm = ProsemProtaDocument::normalizeCustomCalendarEvents($rawEvents);
        $this->assertCount(2, $norm);
        // Sorted by start_col
        $this->assertEquals('MPLS Sekolah', $norm[0]['label']);
        $this->assertEquals(4, $norm[0]['start_col']);
        $this->assertEquals(1, $norm[0]['col_span']);
        $this->assertEquals(4, $norm[0]['end_col']);

        $this->assertEquals('Jeda Tengah Semester', $norm[1]['label']);
        $this->assertEquals(10, $norm[1]['start_col']);
        $this->assertEquals(2, $norm[1]['col_span']);
        $this->assertEquals(11, $norm[1]['end_col']);
        $this->assertEquals('#a7f3d0', $norm[1]['color']);
    }

    public function test_builds_prosem_with_custom_school_calendar_events(): void
    {
        $customEventsGanjil = [
            [
                'label' => 'LIBUR KHUSUS SEKOLAH A',
                'start_col' => 0,
                'end_col' => 2, // Juli M1-M3 (span 3)
                'color' => '#f8c4cb',
                'text_color' => '#881337',
            ],
            [
                'label' => 'KEGIATAN PESANTREN KILAT',
                'start_col' => 12,
                'end_col' => 12, // September M4 (col 12 blocked)
                'color' => '#fef08a',
                'text_color' => '#713f12',
            ],
        ];

        $built = ProsemProtaDocument::buildFromTpAtp($this->sampleTpAtpText(), [
            'calendar_events_ganjil' => $customEventsGanjil,
        ]);

        $prosem = $built['prosem']['ganjil'];
        $this->assertArrayHasKey(0, $prosem['blocked_col_map']);
        $this->assertArrayHasKey(1, $prosem['blocked_col_map']);
        $this->assertArrayHasKey(2, $prosem['blocked_col_map']);
        $this->assertEquals('LIBUR KHUSUS SEKOLAH A', $prosem['blocked_col_map'][0]);

        // Col 3 (Juli M4) is NOT blocked because custom event only goes until col 2
        $this->assertArrayNotHasKey(3, $prosem['blocked_col_map']);
        $this->assertContains(3, $prosem['active_week_cols']);

        // Col 12 is blocked by KEGIATAN PESANTREN KILAT
        $this->assertArrayHasKey(12, $prosem['blocked_col_map']);
        $this->assertEquals('KEGIATAN PESANTREN KILAT', $prosem['blocked_col_map'][12]);
        $this->assertNotContains(12, $prosem['active_week_cols']);
    }

    public function test_calculate_clean_tp_jp_list_allocates_clean_weeks(): void
    {
        // 4 TP from 25 JP (27 JP - 2 JP sumatif), 3 JP/week
        $res4 = ProsemProtaDocument::calculateCleanTpJpList(25, 4, 3, 2);
        $this->assertEquals([6, 6, 6, 7], $res4);
        $this->assertEquals(25, array_sum($res4));

        // 3 TP from 25 JP, 3 JP/week
        $res3 = ProsemProtaDocument::calculateCleanTpJpList(25, 3, 3, 2);
        $this->assertEquals([9, 9, 7], $res3);
        $this->assertEquals(25, array_sum($res3));

        // 2 TP from 25 JP, 3 JP/week
        $res2 = ProsemProtaDocument::calculateCleanTpJpList(25, 2, 3, 2);
        $this->assertEquals([12, 13], $res2);
        $this->assertEquals(25, array_sum($res2));
    }

    public function test_docx_builder_generates_valid_landscape_table_xml(): void
    {
        $built = ProsemProtaDocument::buildFromTpAtp($this->sampleTpAtpText(), [
            'mapel' => 'Ilmu Pengetahuan Sosial',
            'kelas' => 'VII',
            'tahun' => '2026/2027',
        ]);

        $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'prosem_prota_test_'.uniqid().'.docx';
        try {
            $ok = \App\Support\ProsemProtaDocxBuilder::write($tmpPath, $built, 'both');
            $this->assertTrue($ok);
            $this->assertFileExists($tmpPath);

            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($tmpPath));
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            $this->assertIsString($xml);
            $this->assertStringContainsString('w:orient="landscape"', $xml);
            $this->assertStringContainsString('w:w="16838"', $xml);
            $this->assertStringContainsString('<w:gridSpan', $xml);
            $this->assertStringContainsString('<w:vMerge', $xml);
            $this->assertStringContainsString('<w:shd', $xml);
            $this->assertStringContainsString('<w:textDirection w:val="btLr"', $xml);
            $this->assertStringContainsString('<w:cantSplit/>', $xml);
            $this->assertStringContainsString('<w:tblHeader/>', $xml);
            $this->assertStringContainsString('w:bottom w:val="single" w:sz="12"', $xml);
        } finally {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }
}
