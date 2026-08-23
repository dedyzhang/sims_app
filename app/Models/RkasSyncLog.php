<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RkasSyncLog extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'rkas_sync_logs';

    protected $fillable = ['plan_uuid', 'actor_id', 'status', 'note', 'evidence_path', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function plan()
    {
        return $this->belongsTo(RkasPlan::class, 'plan_uuid', 'uuid');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id', 'uuid');
    }
}
