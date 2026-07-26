<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AppUpdate extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'app_updates';

    protected $fillable = [
        'version', 'title', 'content', 'released_at', 'is_published', 'created_by',
    ];

    protected $casts = [
        'released_at' => 'date',
        'is_published' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    protected static function booted()
    {
        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('app_update_latest');
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('app_update_latest');
        });
    }

    /** Update terbaru yang diterbitkan & belum ditutup permanen oleh user ini. */
    public static function pendingFor(User $user): ?self
    {
        $latest = \Illuminate\Support\Facades\Cache::rememberForever('app_update_latest', function () {
            return static::where('is_published', true)
                ->orderByDesc('released_at')
                ->orderByDesc('created_at')
                ->first();
        });

        if (!$latest || $latest->uuid === $user->dismissed_update_id) {
            return null;
        }

        return $latest;
    }
}
