<?php

namespace Tests\Unit;

use App\Integrations\Ludensa\LudensaJenjang;
use Ludensa\Support\JenjangAnak;
use Tests\TestCase;

class LudensaJenjangAnakTest extends TestCase
{
    // Integrasi ludensa/ludensa (composer path repo) belum terpasang di vendor/
    // di checkout ini -- dijeda atas permintaan FL sampai paketnya dipasang &
    // config/ludensa.php dilengkapi. Hapus skip ini begitu vendor/ludensa ada.
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(\Ludensa\Support\JenjangAnak::class)) {
            $this->markTestSkipped('Integrasi Ludensa dijeda -- paket ludensa/ludensa belum terpasang.');
        }
    }

    public function test_bank_jenjang_menggabungkan_sd_rendah_dan_kelas_3(): void
    {
        $this->assertSame(['sd_rendah', 'sd_kelas_3'], JenjangAnak::bankJenjang('sd_rendah'));
        $this->assertSame(['sd_rendah', 'sd_kelas_3'], JenjangAnak::bankJenjang('sd_kelas_3'));
        $this->assertSame(['sd_tinggi'], JenjangAnak::bankJenjang('sd_tinggi'));
    }

    public function test_boleh_main_permainan_detektif_fail_closed_tanpa_tingkat_kelas(): void
    {
        $item = config('ludensa.permainan.detektif');

        $this->assertFalse(JenjangAnak::bolehMainPermainan('sd_rendah', $item, 0));
        $this->assertFalse(JenjangAnak::bolehMainPermainan('sd_rendah', $item, 2));
        $this->assertTrue(JenjangAnak::bolehMainPermainan('sd_rendah', $item, 3));
    }

    public function test_mapping_tingkat_kelas_ke_jenjang_unik(): void
    {
        $this->assertSame('sd_rendah', LudensaJenjang::fromKelasTingkat(1));
        $this->assertSame('sd_rendah', LudensaJenjang::fromKelasTingkat(2));
        $this->assertSame('sd_kelas_3', LudensaJenjang::fromKelasTingkat(3));
        $this->assertSame('sd_tinggi', LudensaJenjang::fromKelasTingkat(5));
        $this->assertNull(LudensaJenjang::fromKelasTingkat(7));
    }

    public function test_label_jenjang_tidak_ganda(): void
    {
        $labels = array_values(config('ludensa.jenjang_label'));

        $this->assertSame(count($labels), count(array_unique($labels)));
        $this->assertSame('SD Kelas 1–2', config('ludensa.jenjang_label.sd_rendah'));
        $this->assertSame('SD Kelas 3', config('ludensa.jenjang_label.sd_kelas_3'));
        $this->assertSame('SD Kelas 4–6', config('ludensa.jenjang_label.sd_tinggi'));
    }
}
