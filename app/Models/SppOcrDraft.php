<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Draft saran OCR bukti transfer — sementara, HITL (A2).
 */
class SppOcrDraft extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'spp_ocr_drafts';

    protected $fillable = [
        'pembayaran_uuid', 'saran', 'file_path', 'dibuat_oleh', 'kadaluarsa_pada',
    ];

    protected function casts(): array
    {
        return [
            'saran'           => 'array',
            'kadaluarsa_pada' => 'datetime',
        ];
    }

    public function pembayaran()
    {
        return $this->belongsTo(SppPembayaran::class, 'pembayaran_uuid', 'uuid');
    }
}
