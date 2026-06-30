<?php

namespace App\Support;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Sumber data tunggal untuk ticker "SIMS-NET" di layout & endpoint polling.
 *
 * Angka mentahnya di-cache singkat agar tidak menjalankan ~15 query agregat
 * di SETIAP load halaman (ticker ada di layout, jadi kena semua halaman).
 * SIMS single-tenant (lihat SarprasModel) → satu cache key global aman.
 *
 * Pemformatan & penyaringan per-role murni operasi array (tanpa query),
 * sehingga aman dipanggil per-request setelah cache terisi.
 */
class TickerStats
{
    public const CACHE_KEY = 'sims_ticker_stats';
    public const TTL       = 60; // detik — angka ticker tak perlu real-time presisi

    /** Angka mentah ter-cache. */
    public static function raw(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL, function () {
            $nowSub3 = now()->subMinutes(3);

            $aset = class_exists(\App\Sarpras\Models\Aset::class)
                ? \App\Sarpras\Models\Aset::count() : 0;
            $kerusakan = class_exists(\App\Sarpras\Models\LaporanKerusakan::class)
                ? \App\Sarpras\Models\LaporanKerusakan::whereIn('status', ['dilaporkan', 'diterima'])->count() : 0;
            $peminjaman = class_exists(\App\Sarpras\Models\Peminjaman::class)
                ? \App\Sarpras\Models\Peminjaman::whereIn('status', ['disetujui', 'dipinjam', 'terlambat'])->count() : 0;

            $semester = Semester::where('aktif', true)->first();

            return [
                'siswa'          => Siswa::count(),
                'siswaL'         => Siswa::where('jk', 'L')->count(),
                'siswaP'         => Siswa::where('jk', 'P')->count(),
                'guru'           => Guru::count(),
                'kelas'          => Kelas::count(),
                'mapel'          => Pelajaran::count(),
                'aset'           => $aset,
                'kerusakan'      => $kerusakan,
                'peminjaman'     => $peminjaman,
                'semesterLabel'  => $semester ? 'SEMESTER ' . $semester->semester . ' (' . $semester->tahun . ')' : 'TIDAK AKTIF',
                'onlineSiswa'    => User::where('access', 'siswa')->where('last_seen_at', '>=', $nowSub3)->count(),
                'onlineGuru'     => User::whereIn('access', ['guru', 'walikelas', 'kurikulum', 'kesiswaan', 'sekretaris', 'kepala', 'sapras', 'bendahara'])->where('last_seen_at', '>=', $nowSub3)->count(),
                'onlineOrangTua' => User::where('access', 'orangtua')->where('last_seen_at', '>=', $nowSub3)->count(),
                'onlineAdmin'    => User::whereIn('access', ['superadmin', 'admin'])->where('last_seen_at', '>=', $nowSub3)->count(),
            ];
        });
    }

    /** Flag bagian mana yang tampil untuk sebuah role. */
    public static function flags(?string $role): array
    {
        $management = in_array($role, ['superadmin', 'admin', 'kepala', 'kurikulum', 'kesiswaan', 'sapras', 'sekretaris'], true);
        $teacher    = in_array($role, ['superadmin', 'admin', 'kepala', 'kurikulum', 'kesiswaan', 'sapras', 'sekretaris', 'walikelas', 'guru'], true);

        return [
            'management' => $management,
            'teacher'    => $teacher,
            'student'    => $teacher,
            'online'     => $role !== 'siswa',
        ];
    }

    /** Teks daftar user online dari angka mentah. */
    public static function onlineText(array $r): string
    {
        $list = [];
        if ($r['onlineSiswa'] > 0)    $list[] = $r['onlineSiswa'] . ' SISWA';
        if ($r['onlineGuru'] > 0)     $list[] = $r['onlineGuru'] . ' GURU';
        if ($r['onlineOrangTua'] > 0) $list[] = $r['onlineOrangTua'] . ' ORANG TUA';
        if ($r['onlineAdmin'] > 0)    $list[] = $r['onlineAdmin'] . ' ADMIN';

        return empty($list) ? 'TIDAK ADA USER ONLINE' : implode(' • ', $list);
    }

    /** Versi siap-tampil (string) per-role untuk endpoint JSON. */
    public static function forRole(?string $role): array
    {
        $r = self::raw();
        $f = self::flags($role);

        return [
            'semester'   => $r['semesterLabel'],
            'siswa'      => $f['student'] ? number_format($r['siswa']) . ' (' . number_format($r['siswaL']) . ' L • ' . number_format($r['siswaP']) . ' P)' : '',
            'guru'       => $f['teacher'] ? number_format($r['guru']) . ' GURU' : '',
            'kelas'      => $f['teacher'] ? number_format($r['kelas']) . ' KELAS' : '',
            'mapel'      => number_format($r['mapel']) . ' MAPEL',
            'aset'       => $f['management'] ? number_format($r['aset']) . ' UNIT' : '',
            'kerusakan'  => $f['management'] ? number_format($r['kerusakan']) . ' LAPORAN' : '',
            'peminjaman' => $f['management'] ? number_format($r['peminjaman']) . ' TRANSAKSI' : '',
            'online'     => $f['online'] ? self::onlineText($r) : '',
        ];
    }
}
