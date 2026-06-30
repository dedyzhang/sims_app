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
        'theme_mode', 'motif', 'ui_style', 'font_size', 'compact_mode', 'dashboard_widgets',
        'dashboard_layout', 'dashboard_hidden',
    ];

    /** Urutan kanonik blok dashboard (default sebelum di-drag). */
    public const DASHBOARD_BLOCKS = ['stats', 'ringkasan', 'insight', 'recent', 'sebaran', 'quicklinks', 'sarpras'];

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
            'primary_color'     => '#7ba088', // sage green
            'secondary_color'   => '#9db89f', // soft sage
            'accent_color'      => '#e5996c', // terracotta
            'sidebar_style'     => 'default',
            'sidebar_bg'        => '#fceadb', // warm cream peach
            'sidebar_text'      => '#57534e', // warm taupe
            'theme_mode'        => 'light',
            'motif'             => 'botanical',
            'ui_style'          => 'soft',
            'font_size'         => 'md',
            'compact_mode'      => false,
            'dashboard_widgets' => ['stats', 'calendar', 'jadwal', 'pengumuman'],
        ];
    }
}
