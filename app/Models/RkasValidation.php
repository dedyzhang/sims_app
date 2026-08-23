<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RkasValidation extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'rkas_validations';

    protected $fillable = ['plan_uuid', 'item_uuid', 'kode', 'severity', 'message', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function plan()
    {
        return $this->belongsTo(RkasPlan::class, 'plan_uuid', 'uuid');
    }

    public function item()
    {
        return $this->belongsTo(RkasItem::class, 'item_uuid', 'uuid');
    }
}
