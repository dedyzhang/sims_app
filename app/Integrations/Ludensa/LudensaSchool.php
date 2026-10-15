<?php

namespace App\Integrations\Ludensa;

use App\Models\Pelajaran;
use App\Models\Setting;
use Ludensa\Models\Mapel;
use Ludensa\Models\School;

class LudensaSchool
{
    /**
     * Kunci scope tenant SIMS saat ini (nama sekolah).
     * Nanti bisa diganti/ditambah config('sims.tenant_id') saat multi-tenant aktif.
     */
    public static function scopeKey(): string
    {
        $nama = trim((string) Setting::get('nama_sekolah', 'Sekolah')) ?: 'Sekolah';

        return hash('sha256', $nama);
    }

    public const DEFAULT_SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    public static function id(): string
    {
        $scopeKey = self::scopeKey();
        $cachedScope = Setting::get('ludensa_school_scope');
        $cachedId = Setting::get('ludensa_school_id');

        if (filled($cachedId) && $cachedScope === $scopeKey) {
            return (string) $cachedId;
        }

        if (! class_exists(School::class)) {
            return (string) ($cachedId ?: self::DEFAULT_SCHOOL_ID);
        }

        $nama = Setting::get('nama_sekolah', 'Sekolah') ?: 'Sekolah';
        $school = School::query()->firstOrCreate(['nama' => $nama]);

        Setting::set('ludensa_school_id', $school->id);
        Setting::set('ludensa_school_scope', $scopeKey);

        return (string) $school->id;
    }

    public static function ensureMapelsFromPelajaran(): void
    {
        if (! class_exists(Mapel::class)) {
            return;
        }

        $schoolId = self::id();

        foreach (Pelajaran::query()->orderBy('urutan')->orderBy('nama')->get() as $pelajaran) {
            Mapel::firstOrCreate([
                'school_id' => $schoolId,
                'nama' => $pelajaran->nama,
            ]);
        }

        if (Mapel::where('school_id', $schoolId)->count() === 0) {
            foreach (['Matematika', 'Bahasa Indonesia', 'IPA'] as $nama) {
                Mapel::firstOrCreate(['school_id' => $schoolId, 'nama' => $nama]);
            }
        }
    }
}
