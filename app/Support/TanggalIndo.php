<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Nama hari/bulan Bahasa Indonesia utk dokumen resmi (Berita Acara, dll).
 * TIDAK andalkan Carbon::translatedFormat()/setLocale() — APP_LOCALE app ini
 * "en" (lihat .env), jadi translatedFormat selalu keluar Inggris ("Friday",
 * "August") kecuali locale global diubah (berisiko efek samping ke bagian
 * lain app). Pola sama spt App\Support\BlueprintDocxBuilder::signature() yg
 * sudah lebih dulu hardcode nama bulan manual krn alasan yg sama.
 */
class TanggalIndo
{
    private const HARI = [
        0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
        4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu',
    ];

    private const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public static function hari(Carbon $tanggal): string
    {
        return self::HARI[(int) $tanggal->format('w')];
    }

    public static function bulan(Carbon $tanggal): string
    {
        return self::BULAN[(int) $tanggal->format('n')];
    }

    /** "21 Agustus 2026" */
    public static function panjang(Carbon $tanggal): string
    {
        return $tanggal->format('j') . ' ' . self::bulan($tanggal) . ' ' . $tanggal->format('Y');
    }

    /** "Jumat, 21 Agustus 2026" */
    public static function panjangDenganHari(Carbon $tanggal): string
    {
        return self::hari($tanggal) . ', ' . self::panjang($tanggal);
    }
}
