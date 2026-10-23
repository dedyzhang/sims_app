<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Mode Ujian (Exam Lockdown Mode):
 * Saat aktif:
 * - Seluruh polling background dihentikan 100%.
 * - Ticker SIMS-NET di footer disembunyikan.
 * - Firebase RTDB di client dinonaktifkan.
 * - Siswa yang login / akses web langsung dialihkan ke /ujian/saya.
 * - Siswa dikunci: tidak bisa membuka dashboard, ruang kelas, arena belajar, forum, dll.
 * - Modul Arena Belajar dinonaktifkan sementara.
 */
class ModeUjian
{
    public const SETTING_KEY = 'mode_ujian_aktif';

    public static function aktif(): bool
    {
        return Setting::get(self::SETTING_KEY, '0') === '1';
    }

    public static function nonaktif(): bool
    {
        return ! self::aktif();
    }

    public static function aktifkan(): void
    {
        Setting::set(self::SETTING_KEY, '1');
    }

    public static function nonaktifkan(): void
    {
        Setting::set(self::SETTING_KEY, '0');
    }

    public static function toggle(): bool
    {
        $baru = self::aktif() ? '0' : '1';
        Setting::set(self::SETTING_KEY, $baru);

        return $baru === '1';
    }
}
