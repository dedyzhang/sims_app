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
}
