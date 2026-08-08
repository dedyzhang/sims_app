<?php

namespace App\Sarpras\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SarprasActivityLog extends SarprasModel
{
    public $timestamps = false;

    protected $table = 'sarpras_activity_log';

    protected $fillable = [
        'school_id', 'aksi', 'subjek_tipe', 'subjek_id', 'pelaku_id', 'meta', 'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pelaku_id');
    }
}
