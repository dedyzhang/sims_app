<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoProvisioningEvent extends Model
{
    use HasUuids;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_CONFLICT = 'conflict';

    protected $fillable = [
        'demo_access_id',
        'source_request_id',
        'idempotency_key',
        'body_hash',
        'event_type',
        'status',
        'response_code',
        'safe_metadata',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'safe_metadata' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function access(): BelongsTo
    {
        return $this->belongsTo(DemoAccess::class, 'demo_access_id');
    }
}
