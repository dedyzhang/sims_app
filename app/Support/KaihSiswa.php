<?php

namespace App\Support;

use App\Models\KaihJawaban;
use App\Models\Setting;

/**
 * Aturan: siswa wajib mengisi kuesioner 7 KAIH (7 Kebiasaan Anak Indonesia Hebat)
 * pada hari berjalan sebelum boleh absen (QR maupun scan wajah).
 */
class KaihSiswa
{
    /** Apakah pengecekan KAIH sebelum absen diaktifkan (default: ya). */
    public static function wajibSebelumAbsen(): bool
    {
        return Setting::get('kaih_wajib_sebelum_absen', '1') === '1';
    }

    /** Sudah adakah jawaban (diisi ATAU dilewati) untuk siswa pada tanggal ini. */
    public static function sudahDiisi(string $idSiswa, ?string $tanggal = null): bool
    {
        $tanggal = $tanggal ?: now()->toDateString();
        return KaihJawaban::where('id_siswa', $idSiswa)->whereDate('tanggal', $tanggal)->exists();
    }

    /** True bila siswa sudah boleh absen (KAIH terisi/dilewati, atau aturan nonaktif). */
    public static function bolehAbsen(string $idSiswa, ?string $tanggal = null): bool
    {
        if (!self::wajibSebelumAbsen()) {
            return true;
        }
        return self::sudahDiisi($idSiswa, $tanggal);
    }

    /** Pesan penolakan standar bila KAIH belum diisi. */
    public static function pesanTolak(): string
    {
        return 'Isi kuesioner 7 KAIH hari ini dulu sebelum absen (menu "Isi 7 KAIH" di dashboard).';
    }
}
