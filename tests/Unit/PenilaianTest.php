<?php

namespace Tests\Unit;

use App\Support\Penilaian;
use Tests\TestCase;

/**
 * Unit test perhitungan rapor (App\Support\Penilaian). Murni fungsi statis,
 * tanpa DB — area paling kritis karena bug di sini = nilai rapor siswa salah.
 */
class PenilaianTest extends TestCase
{
    public function test_hitung_bagi4_membobot_formatif_dua_kali(): void
    {
        // avgF = (80+90)/2 = 85, avgS = 70, pas = 60
        // bagi4 = (2*85 + 70 + 60) / 4 = 300/4 = 75
        $r = Penilaian::hitung([80, 90], [70], 60, 'bagi4');

        $this->assertSame(85, $r['rataFormatif']);
        $this->assertSame(70, $r['rataSumatif']);
        $this->assertSame(60, $r['pas']);
        $this->assertSame(75, $r['rapor']);
    }

    public function test_hitung_bagi3_rata_rata_sederhana(): void
    {
        // bagi3 = (85 + 70 + 60) / 3 = 215/3 = 71.67 -> 72
        $r = Penilaian::hitung([80, 90], [70], 60, 'bagi3');

        $this->assertSame(72, $r['rapor']);
    }

    public function test_hitung_jumlah_dulu_menghitung_tiap_nilai_sebagai_satu_data(): void
    {
        // jumlahDulu: (80+90+70+60) / 4 data = 300/4 = 75
        $r = Penilaian::hitung([80, 90], [70], 60, 'jumlahDulu');

        $this->assertSame(75, $r['rapor']);
    }

    public function test_hitung_tanpa_pas_tidak_menghitung_pas_sebagai_data(): void
    {
        // jumlahDulu tanpa PAS: formatif [100], sumatif [] -> 100/1 = 100
        $r = Penilaian::hitung([100], [], null, 'jumlahDulu');

        $this->assertSame(100, $r['rapor']);
        $this->assertSame(0, $r['pas']);
    }

    public function test_hitung_kosong_menghasilkan_nol_tanpa_division_by_zero(): void
    {
        $r = Penilaian::hitung([], [], null, 'bagi4');

        $this->assertSame(0, $r['rataFormatif']);
        $this->assertSame(0, $r['rataSumatif']);
        $this->assertSame(0, $r['rapor']);
    }

    public function test_predikat_mengembalikan_strip_bila_nilai_null(): void
    {
        $this->assertSame('-', Penilaian::predikat(null, 75));
    }

    public function test_predikat_d_bila_di_bawah_kkm(): void
    {
        $this->assertSame('D', Penilaian::predikat(74, 75));
    }

    /**
     * KKM 75 -> interval (100-75)/3 = 8.33.
     * C: 75..83.32, B: 83.33..91.66, A: >= 91.67
     */
    public function test_predikat_batas_a_b_c(): void
    {
        $this->assertSame('C', Penilaian::predikat(75, 75));   // tepat di KKM
        $this->assertSame('C', Penilaian::predikat(83, 75));   // tepat di bawah ambang B
        $this->assertSame('B', Penilaian::predikat(84, 75));   // tepat di atas ambang B
        $this->assertSame('B', Penilaian::predikat(91, 75));
        $this->assertSame('A', Penilaian::predikat(92, 75));   // di atas ambang A
        $this->assertSame('A', Penilaian::predikat(100, 75));
    }

    public function test_predikat_kata_untuk_ekskul(): void
    {
        $this->assertSame('Amat baik', Penilaian::predikatKata('A'));
        $this->assertSame('Baik', Penilaian::predikatKata('B'));
        $this->assertSame('Cukup', Penilaian::predikatKata('C'));
        $this->assertSame('Perlu bimbingan', Penilaian::predikatKata('D'));
    }
}
