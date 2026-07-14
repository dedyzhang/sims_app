<?php

namespace Tests\Feature;

use App\Support\LearningDocument;
use Tests\TestCase;

class LearningDocumentTest extends TestCase
{
    /** Fixture acuan PDF/DOCX Fellianto harus terparse penuh dan rubrik LAMPIRAN 2 ber-header Kriteria. */
    public function test_fixture_rpm_fellianto_terparse_dengan_rubrik_kriteria(): void
    {
        $path = base_path('tests/Fixtures/rpm-fellianto-agama-buddha-kelas8.txt');
        $this->assertFileExists($path);

        $doc = LearningDocument::parse((string) file_get_contents($path));

        $this->assertTrue($doc['parsed']);
        $this->assertSame('YAYASAN BUMI MAITRI', $doc['kop'][0]);
        $this->assertCount(3, $doc['identifikasi']);
        $this->assertCount(8, $doc['desain']);
        $this->assertCount(6, $doc['pengalaman']);
        $this->assertCount(3, $doc['asesmen']);
        $this->assertCount(3, $doc['lampiran']);

        $rubrik = null;
        foreach ($doc['lampiran'][1]['blocks'] as $block) {
            if ($block['type'] === 'table') {
                $rubrik = $block;
                break;
            }
        }
        $this->assertNotNull($rubrik);
        $this->assertSame('Kompetensi', $rubrik['rows'][0][0]);
        $this->assertSame('Baru Mulai', $rubrik['rows'][0][1]);
        $this->assertGreaterThanOrEqual(4, count($rubrik['rows']));
    }

    /** Keluaran nyata Gemini kerap ber-Markdown dan memakai placeholder, bukan teks polos ideal. */
    public function test_dokumen_ber_markdown_tetap_terparse_sebagai_rpm(): void
    {
        $doc = LearningDocument::parse($this->keluaranAi());

        $this->assertTrue($doc['parsed']);
        $this->assertSame(['YAYASAN [NAMA YAYASAN]', 'SD [NAMA SEKOLAH]'], $doc['kop']);
        $this->assertSame('PERENCANAAN PEMBELAJARAN MENDALAM', $doc['title']);
        $this->assertSame('"EKOSISTEM"', $doc['subtitle']);
        $this->assertCount(3, $doc['identifikasi']);   // Murid, Materi, DPL
        $this->assertCount(2, $doc['desain']);
        $this->assertCount(2, $doc['pengalaman']);     // AWAL, PENUTUP
        $this->assertCount(1, $doc['asesmen']);

        // Markdown tebal/heading tidak boleh bocor ke isi dokumen.
        $this->assertStringNotContainsString('**', $doc['text']);
    }

    /** Centang hanya sah di PENGALAMAN BELAJAR; model kerap menaruhnya di sel deskriptif. */
    public function test_centang_liar_di_sel_tabel_dibuang(): void
    {
        $doc = LearningDocument::parse($this->keluaranAi());

        $this->assertSame(['Fase C dengan gaya belajar beragam.'], $doc['identifikasi'][0]['lines']);
        $this->assertSame('check', $doc['pengalaman'][0]['items'][0]['type']);
    }

    public function test_dpl_terbaca_dengan_status_centang(): void
    {
        $dpl = LearningDocument::parse($this->keluaranAi())['identifikasi'][2]['dpl'];

        $this->assertCount(2, $dpl);
        $this->assertTrue($dpl[0]['checked']);
        $this->assertFalse($dpl[1]['checked']);
        $this->assertSame('DPL 2 Berkebinekaan global.', $dpl[1]['label']);
    }

    /** "[Tempat], [tanggal]" ditulis tepat sebelum "Mengetahui," — jangan tertelan sel asesmen. */
    public function test_tempat_tanggal_placeholder_masuk_blok_tanda_tangan(): void
    {
        $doc = LearningDocument::parse($this->keluaranAi());

        $this->assertSame('[Tempat], [tanggal]', $doc['signature']['date']);
        $this->assertSame(['[Nama Kepala Sekolah]', '[Nama Guru]'], $doc['signature']['rows'][1]);

        $selAsesmen = implode("\n", $doc['asesmen'][0]['lines']);
        $this->assertStringNotContainsString('[Tempat]', $selAsesmen);
    }

    /** Baris pemisah header tabel Markdown bukan data rubrik. */
    public function test_baris_pemisah_tabel_markdown_dibuang(): void
    {
        $lampiran = LearningDocument::parse($this->keluaranAi())['lampiran'];

        $tabel = null;
        foreach ($lampiran[0]['blocks'] as $block) {
            if ($block['type'] === 'table') {
                $tabel = $block;
            }
        }

        $this->assertNotNull($tabel);
        $this->assertCount(2, $tabel['rows']); // header + 1 baris isi, tanpa baris "---"
        $this->assertSame('Kompetensi', $tabel['rows'][0][0]);
        $this->assertSame('Kolaborasi', $tabel['rows'][1][0]);
    }

    /** Konten bebas (bukan RPM) tetap aman: tak diparse, fallback teks polos dipakai. */
    public function test_konten_non_rpm_tidak_dianggap_terparse(): void
    {
        $doc = LearningDocument::parse("Catatan bebas guru.\nTidak berformat RPM sama sekali.");

        $this->assertFalse($doc['parsed']);
        $this->assertNotSame('', $doc['text']);
    }

    private function keluaranAi(): string
    {
        return <<<'TXT'
        Berikut adalah RPM yang lengkap dan siap digunakan.

        YAYASAN [NAMA YAYASAN]
        SD [NAMA SEKOLAH]

        **PERENCANAAN PEMBELAJARAN MENDALAM**
        **"EKOSISTEM"**

        SEKOLAH : SD [NAMA SEKOLAH]
        NAMA GURU : [NAMA GURU]

        **IDENTIFIKASI**

        Murid:
        ✓ Fase C dengan gaya belajar beragam.

        Materi:
        Komponen Ekosistem.

        Dimensi Profil Lulusan (DPL):
        ☑ DPL 1 Keimanan dan ketakwaan terhadap Tuhan Yang Maha Esa.
        ☐ DPL 2 Berkebinekaan global.

        **DESAIN PEMBELAJARAN**

        Capaian Pembelajaran:
        Peserta didik menyelidiki hubungan antar-komponen ekosistem.

        Tujuan Pembelajaran:
        1. Murid mampu membedakan komponen biotik dan abiotik.

        **PENGALAMAN BELAJAR**

        AWAL (Berkesadaran, Bermakna, dan Menggembirakan)
        * Guru memulai kelas dengan hening sejenak.
        "Apa yang terjadi jika air di toples menguap habis?"

        PENUTUP (Bermakna dan Berkesadaran)
        * Kelas ditutup dengan doa bersama.

        **ASESMEN PEMBELAJARAN**

        Asesmen pada Akhir Pembelajaran:
        Soal evaluasi mandiri berbentuk analisis studi kasus.

        [Tempat], [tanggal]
        Mengetahui, | Guru Mata Pelajaran
        [Nama Kepala Sekolah] | [Nama Guru]
        NIP. .......... | NIP. ..........

        **LAMPIRAN 2: ASESMEN PADA PROSES PEMBELAJARAN**

        Lembar Observasi Kinerja Kelompok

        Kompetensi | Baru Mulai | Berkembang | Cakap | Mahir
        --- | --- | --- | --- | ---
        Kolaborasi | Pasif dalam kelompok. | Perlu diingatkan. | Berpartisipasi aktif. | Menunjukkan kepemimpinan.
        TXT;
    }
}
