<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Support\SchoolLetterhead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolLetterheadTest extends TestCase
{
    use RefreshDatabase;

    public function test_lines_dari_identitas_sekolah(): void
    {
        Setting::set('nama_sekolah', 'SMP Harapan');
        Setting::set('alamat_sekolah', 'Jl. Melati 10');
        Setting::set('telp_sekolah', '021-111');
        Setting::set('npsn', '999');
        Setting::set('kota', 'Jakarta');
        Setting::set('provinsi', 'DKI Jakarta');

        $lines = SchoolLetterhead::lines();

        $this->assertSame('SMP Harapan', $lines[0]);
        $this->assertSame('Jl. Melati 10', $lines[1]);
        $this->assertStringContainsString('Telp. 021-111', $lines[2]);
        $this->assertStringContainsString('NPSN 999', $lines[2]);
        $this->assertStringContainsString('Jakarta, DKI Jakarta', $lines[2]);
    }

    public function test_ensure_prefix_mengganti_kop_asing(): void
    {
        Setting::set('nama_sekolah', 'SMP Harapan');
        Setting::set('alamat_sekolah', 'Jl. Melati 10');

        $body = "YAYASAN BUMI MAITRI\nSMP MAITREYAWIRA\nSOAL EVALUASI IPA\n1. Apa?";
        $fixed = SchoolLetterhead::ensurePrefix($body);

        $this->assertStringStartsWith("SMP Harapan\nJl. Melati 10", $fixed);
        $this->assertStringContainsString('SOAL EVALUASI IPA', $fixed);
        $this->assertStringNotContainsString('YAYASAN BUMI MAITRI', $fixed);
    }

    public function test_ensure_prefix_mempertahankan_judul_huruf_kapital(): void
    {
        Setting::set('nama_sekolah', 'SMP Harapan');

        $body = "MATERI POKOK\n- Fotosintesis\nBAGIAN A - PILIHAN GANDA\n1. Apa?";
        $fixed = SchoolLetterhead::ensurePrefix($body);

        $this->assertStringContainsString('MATERI POKOK', $fixed);
        $this->assertStringContainsString('BAGIAN A - PILIHAN GANDA', $fixed);
        $this->assertStringContainsString('- Fotosintesis', $fixed);
        $this->assertStringStartsWith("SMP Harapan", $fixed);
    }

    public function test_ensure_prefix_cocokkan_baris_pertama_bukan_prefix_body(): void
    {
        Setting::set('nama_sekolah', 'Sekolah');

        $body = "Sekolah adalah tempat belajar.\nPoin dua";
        $fixed = SchoolLetterhead::ensurePrefix($body);

        $this->assertStringContainsString('Sekolah adalah tempat belajar.', $fixed);
        // Fallback nama "Sekolah" tidak boleh menahan body yang hanya diawali kata yang sama.
        $this->assertTrue(str_starts_with($fixed, "Sekolah\n"));
        $this->assertStringContainsString("Sekolah\n\nSekolah adalah tempat belajar.", $fixed);
    }
}
