<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DemoResetRun extends Model
{
    use HasUuids;

    public const STATUS_STARTED = 'started';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DRY_RUN = 'dry_run';

    protected $fillable = [
        'manifest_version',
        'status',
        'dry_run',
        'table_counts',
        'error_code',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'table_counts' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
