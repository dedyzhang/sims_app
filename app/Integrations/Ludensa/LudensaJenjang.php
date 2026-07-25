<?php

namespace App\Integrations\Ludensa;

class LudensaJenjang
{
    public static function fromKelasTingkat(int $tingkat): ?string
    {
        return match (true) {
            $tingkat >= 1 && $tingkat <= 2 => 'sd_rendah',
            $tingkat === 3 => 'sd_kelas_3',
            $tingkat >= 4 && $tingkat <= 6 => 'sd_tinggi',
            default => null,
        };
    }

    public static function isSd(?string $jenjang): bool
    {
        return in_array($jenjang, ['sd_rendah', 'sd_kelas_3', 'sd_tinggi'], true);
    }

    /** Siswa SD kelas 1–6 yang sudah punya rombel. */
    public static function bolehAksesSiswa(?\App\Models\User $user): bool
    {
        if (! $user || $user->access !== 'siswa') {
            return false;
        }

        if (self::isSd($user->jenjang)) {
            return true;
        }

        $tingkat = (int) ($user->siswa?->kelas?->tingkat ?? 0);

        return $tingkat >= 1 && $tingkat <= 6;
    }
}
