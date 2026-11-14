<?php
namespace App\Survei;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

abstract class ModelSurvei extends Model
{
    use HasUuids;
    public const SCHOOL_ID = '00000000-0000-0000-0000-000000000001';
    protected $primaryKey = 'uuid';
    protected $guarded = [];
    protected static function booted(): void
    {
        // SIMS deployment ini satu sekolah. Jangan gunakan konstanta ini untuk shared-database multi-tenant.
        static::addGlobalScope('school', fn (Builder $q) => $q->where($q->getModel()->qualifyColumn('school_id'), static::SCHOOL_ID));
        static::creating(fn ($model) => $model->school_id = static::SCHOOL_ID);
    }
}
