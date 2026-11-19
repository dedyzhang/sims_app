<?php

namespace Tests\Unit;

use App\Models\BankSoal;
use App\Models\UjianSoal;
use App\Support\RichText;
use PHPUnit\Framework\TestCase;

class RichTextSnippetTest extends TestCase
{
    public function test_membersihkan_nbsp_dan_tag_html_dari_cuplikan(): void
    {
        $input = '&nbsp; &nbsp; &nbsp; Proses pemecahan senyawa kompleks menjadi senyawa sederhana...';
        $hasil = RichText::snippet($input, 80);

        $this->assertSame('Proses pemecahan senyawa kompleks menjadi senyawa sederhana...', $hasil);
        $this->assertStringNotContainsString('&nbsp;', $hasil);
    }

    public function test_membersihkan_html_p_dan_nbsp_di_tengah_dan_akhir(): void
    {
        $input = '<p>&nbsp; &nbsp;Urutan tahapan respirasi aerob sel yang benar, adalah.... &nbsp;</p>';
        $hasil = RichText::snippet($input, 80);

        $this->assertSame('Urutan tahapan respirasi aerob sel yang benar, adalah....', $hasil);
        $this->assertStringNotContainsString('&nbsp;', $hasil);
    }

    public function test_membersihkan_amp_nbsp_dan_entitas_numeric(): void
    {
        $input = '&amp;nbsp; &#160; Hasil akhir dari dekarboksilasi oksidatif adalah...';
        $hasil = RichText::snippet($input, 80);

        $this->assertSame('Hasil akhir dari dekarboksilasi oksidatif adalah...', $hasil);
    }

    public function test_memotong_panjang_teks_sesuai_limit(): void
    {
        $input = str_repeat('Kata ', 30);
        $hasil = RichText::snippet($input, 20);

        $this->assertLessThanOrEqual(23, mb_strlen($hasil)); // 20 + '...'
        $this->assertStringEndsWith('...', $hasil);
    }

    public function test_mempertahankan_data_latex_pada_rumus_matematika(): void
    {
        $input = '<p>Berapa nilai dari <img class="math-svg" data-latex="x^2 + y^2 = r^2" alt="formula"> pada lingkaran?</p>';
        $hasil = RichText::snippet($input, 100);

        $this->assertSame('Berapa nilai dari x^2 + y^2 = r^2 pada lingkaran?', $hasil);
    }

    public function test_fallback_gambar_jika_hanya_ada_tag_img_tanpa_teks(): void
    {
        $input = '<p><img src="/storage/soal/gambar1.png" /></p>';
        $hasil = RichText::snippet($input, 80);

        $this->assertSame('[Gambar]', $hasil);
    }

    public function test_model_ujian_soal_dan_bank_soal_cuplikan_teks(): void
    {
        $ujianSoal = new UjianSoal();
        $ujianSoal->teks_soal = '<p>&nbsp; &nbsp; &nbsp; Pertanyaan Ujian</p>';
        $this->assertSame('Pertanyaan Ujian', $ujianSoal->cuplikanTeks(80));

        $bankSoal = new BankSoal();
        $bankSoal->teks_soal = '<p>&nbsp; &nbsp; Pertanyaan Bank Soal &nbsp;</p>';
        $this->assertSame('Pertanyaan Bank Soal', $bankSoal->cuplikanTeks(80));
    }

    public function test_clean_membersihkan_font_family_dan_size_dari_word(): void
    {
        $input = '<p class="MsoNormal" style="font-family: \'Times New Roman\', serif; font-size: 12.0pt;"><span style="font-family: \'Times New Roman\';">Teks soal dari Word</span></p>';
        $hasil = RichText::clean($input);

        $this->assertStringNotContainsString('Times New Roman', $hasil);
        $this->assertStringNotContainsString('font-family', $hasil);
        $this->assertStringNotContainsString('font-size', $hasil);
        $this->assertStringNotContainsString('MsoNormal', $hasil);
        $this->assertStringContainsString('Teks soal dari Word', $hasil);
    }

    public function test_clean_membersihkan_tab_lari_dan_mso_tab_count(): void
    {
        $input = '<p>Bola A dan B saling tarik,<br><span style="mso-tab-count:1">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>sedangkan bola C dan D saling<br>&nbsp;&nbsp;&nbsp;&nbsp;tarik.</p>';
        $hasil = RichText::clean($input);

        $this->assertStringNotContainsString('mso-tab-count', $hasil);
        $this->assertSame('<p>Bola A dan B saling tarik,<br>sedangkan bola C dan D saling<br>tarik.</p>', $hasil);
    }

    public function test_clean_mempertahankan_rumus_svg_dan_formatting_dasar(): void
    {
        $input = '<p style="font-family: Arial;">Tentukan <b>nilai</b> <img class="math-svg" src="data:image/svg+xml;base64,PHN2Zz4=" data-latex="x^2" style="vertical-align:middle;height:1.5em;font-family:Verdana"> pada <i>lingkaran</i>.</p>';
        $hasil = RichText::clean($input);

        $this->assertStringContainsString('class="math-svg"', $hasil);
        $this->assertStringContainsString('data-latex="x^2"', $hasil);
        $this->assertStringContainsString('vertical-align:middle', $hasil);
        $this->assertStringNotContainsString('font-family', $hasil);
        $this->assertStringContainsString('<b>nilai</b>', $hasil);
        $this->assertStringContainsString('<i>lingkaran</i>', $hasil);
    }

    public function test_clean_kop_mempertahankan_font_size_dan_font_family_dari_setting(): void
    {
        $input = '<p class="MsoNormal" style="text-align: center;"><strong style="mso-bidi-font-weight: normal;"><span style="font-size: 18.0pt; font-family: \'Baskerville Old Face\',serif;">SMP MAITREYAWIRA TANJUNGPINANG</span></strong></p><script>alert("xss")</script>';
        $hasil = RichText::cleanKop($input);

        $this->assertStringNotContainsString('script', $hasil);
        $this->assertStringNotContainsString('alert', $hasil);
        $this->assertStringNotContainsString('MsoNormal', $hasil);
        $this->assertStringNotContainsString('mso-bidi-font-weight', $hasil);
        $this->assertStringContainsString('font-size: 18.0pt', $hasil);
        $this->assertStringContainsString('Baskerville Old Face', $hasil);
        $this->assertStringContainsString('SMP MAITREYAWIRA TANJUNGPINANG', $hasil);
    }
}
