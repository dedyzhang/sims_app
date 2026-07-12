<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
| History hasil generate Asisten Guru per user. Dipakai sebagai pengingat
| cepat untuk Soal, RPM Learning, Perangkum Materi, dan Draft Feedback.
*/
class AiTeacherHistory extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_uuid',
        'type',
        'type_label',
        'title',
        'excerpt',
        'metadata',
        'answer',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
}
