<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Jenjang sekolah aplikasi ini (SD/SMP/SMA) — satu instalasi = satu jenjang,
 * menentukan rentang tingkat kelas yang boleh dibuat di menu Data Kelas.
 */
class JenjangSekolah
{
    public const JENJANG = [
        'sd'  => 'SD',
        'smp' => 'SMP',
        'sma' => 'SMA / SMK',
    ];

    /** Rentang tingkat [min, max] per jenjang. */
    public const RENTANG = [
        'sd'  => [1, 6],
        'smp' => [7, 9],
        'sma' => [10, 12],
    ];

    public static function aktif(): string
    {
        $j = Setting::get('jenjang_sekolah', 'smp');
        return array_key_exists($j, self::JENJANG) ? $j : 'smp';
    }

    public static function label(): string
    {
        return self::JENJANG[self::aktif()];
    }

    /** @return array{0:int,1:int} */
    public static function rentangTingkat(): array
    {
        return self::RENTANG[self::aktif()];
    }
}
