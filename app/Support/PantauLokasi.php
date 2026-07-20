<?php

namespace App\Support;

use App\Models\Orangtua;
use App\Models\Setting;
use App\Models\User;

/**
 * Fitur Pantau Lokasi Siswa — riwayat titik GPS absen (bukan live tracking).
 * Hanya titik di dalam area geofence sekolah yang ditampilkan.
 */
class PantauLokasi
{
    public static function aktif(): bool
    {
        return Setting::get('pantau_lokasi_aktif', '1') === '1';
    }

    /** Pin sekolah sudah diatur (wajib untuk pantau lokasi). */
    public static function sekolahPinSiap(): bool
    {
        $lat = Setting::get('sekolah_lat');
        $lng = Setting::get('sekolah_lng');

        return $lat !== null && $lat !== '' && $lng !== null && $lng !== '';
    }

    /**
     * Pin & radius sekolah, dibaca SEKALI lalu dioper ke titikDiDalamArea().
     * Dipisah agar pengecekan banyak titik tidak membaca Setting berulang —
     * benar tanpa bergantung pada memo per-request di model Setting.
     *
     * @return array{lat: float, lng: float, radius: float}|null  null = pin belum diatur
     */
    public static function areaSekolah(): ?array
    {
        if (! self::sekolahPinSiap()) {
            return null;
        }

        return [
            'lat'    => (float) Setting::get('sekolah_lat'),
            'lng'    => (float) Setting::get('sekolah_lng'),
            'radius' => (float) Setting::get('absen_radius', 200),
        ];
    }

    /**
     * True bila titik berada di dalam radius area (+ toleransi GPS server).
     * Murni hitungan — tanpa akses Setting, aman dipakai di dalam loop.
     *
     * @param  array{lat: float, lng: float, radius: float}  $area
     */
    public static function titikDiDalamArea(array $area, float $lat, float $lng): bool
    {
        $dist = Geofence::distanceMeters($area['lat'], $area['lng'], $lat, $lng);

        return Geofence::withinRadius($dist, $area['radius']);
    }

    /**
     * Versi satu-titik (mis. blok dashboard siswa). Untuk banyak titik gunakan
     * areaSekolah() + titikDiDalamArea() agar Setting tidak dibaca berulang.
     */
    public static function diDalamAreaSekolah(float $lat, float $lng): bool
    {
        $area = self::areaSekolah();

        return $area !== null && self::titikDiDalamArea($area, $lat, $lng);
    }

    /** Admin / kepala / kesiswaan / manage_absensi → seluruh sekolah. */
    public static function canViewSchoolWide(User $user): bool
    {
        if ($user->canAccess('manage_absensi')) {
            return true;
        }

        // UserRole::matches, bukan in_array: users.access disimpan kanonik, dan
        // perbandingan mentah pernah membuat peran beralias tertolak diam-diam.
        return UserRole::matches((string) $user->access, 'superadmin', 'admin', 'kepala', 'kesiswaan');
    }

    /** UUID kelas homeroom bila user adalah wali kelas (null jika bukan). */
    public static function walikelasKelasId(User $user): ?string
    {
        return $user->guru?->walikelas?->id_kelas;
    }

    /** UUID siswa anak dari akun orang tua. */
    public static function anakIds(User $user): array
    {
        if ($user->access !== 'orangtua') {
            return [];
        }

        return Orangtua::where('id_login', $user->uuid)
            ->pluck('id_siswa')
            ->filter()
            ->values()
            ->all();
    }

    public static function canAccess(User $user): bool
    {
        if (! self::aktif()) {
            return false;
        }

        if (self::canViewSchoolWide($user)) {
            return true;
        }

        if (self::walikelasKelasId($user)) {
            return true;
        }

        return $user->access === 'orangtua' && count(self::anakIds($user)) > 0;
    }
}
