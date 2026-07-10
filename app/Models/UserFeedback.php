<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserFeedback extends Model
{
    use HasUuids;

    protected $table = 'user_feedback';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_uuid',
        'category',
        'status',
        'rating',
        'subject',
        'message',
        'context_url',
        'admin_response',
        'responded_by',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public const CATEGORIES = [
        'bug' => 'Kendala / Bug',
        'ide' => 'Ide Fitur',
        'data' => 'Data Tidak Sesuai',
        'tampilan' => 'Tampilan / Kemudahan Pakai',
        'lainnya' => 'Lainnya',
    ];

    public const STATUSES = [
        'baru' => 'Baru',
        'dibaca' => 'Dibaca',
        'diproses' => 'Diproses',
        'selesai' => 'Selesai',
        'ditolak' => 'Tidak Dilanjutkan',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by', 'uuid');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
