<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_uuid', 'primary_color', 'secondary_color', 'accent_color',
        'sidebar_style', 'sidebar_bg', 'sidebar_text',
        'theme_mode', 'motif', 'ui_style', 'dashboard_theme', 'font_size', 'compact_mode', 'dashboard_widgets',
        'dashboard_layout', 'dashboard_hidden',
    ];

    /** Urutan kanonik blok dashboard (default sebelum di-drag). */
    public const DASHBOARD_BLOCKS = [
        'ringkasan_siswa', 'ringkasan_guru', 'ringkasan_kelas', 'ringkasan_tahun',
        'presensi_hadir', 'presensi_terlambat', 'presensi_tidak_hadir', 'presensi_belum',
        'sarpras_aset', 'sarpras_kerusakan', 'sarpras_peminjaman', 'sarpras_pengadaan',
        'recent_tingkat', 'recent_komposisi', 'sebaran', 'quicklinks',
        'siswa_jadwal', 'siswa_absensi', 'siswa_poin', 'siswa_podium',
        'guru_jadwal', 'guru_presensi', 'guru_agenda',
        'kesiswaan_pending', 'kesiswaan_absensi',
        'kurikulum_agenda',
    ];

    protected function casts(): array
    {
        return [
            'compact_mode'       => 'boolean',
            'dashboard_widgets'  => 'array',
            'dashboard_layout'   => 'array',
            'dashboard_hidden'   => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public static function defaults(): array
    {
        return [
            'primary_color'     => '#2563eb',
            'secondary_color'   => '#3b82f6',
            'accent_color'      => '#f59e0b',
            'sidebar_style'     => 'default',
            'sidebar_bg'        => '#ffffff',
            'sidebar_text'      => '#475569',
            'theme_mode'        => 'light',
            'motif'             => 'minimal',
            'ui_style'          => 'soft',
            'dashboard_theme'   => 'windows11',
            'font_size'         => 'md',
            'compact_mode'      => false,
            'dashboard_widgets' => ['stats', 'calendar', 'jadwal', 'pengumuman'],
        ];
    }
}
