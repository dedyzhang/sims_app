<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RkasPlan extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'rkas_plans';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_READY = 'ready_for_arkas_input';
    public const STATUS_SUBMITTED = 'submitted_in_arkas';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REVISION = 'revision_required';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_VALIDATED, self::STATUS_READY,
        self::STATUS_SUBMITTED, self::STATUS_APPROVED, self::STATUS_REVISION, self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'npsn', 'nama_sekolah', 'tahun_anggaran', 'jenjang', 'sumber_dana',
        'reference_set_uuid', 'pagu', 'status', 'validated_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['tahun_anggaran' => 'integer', 'pagu' => 'integer', 'validated_at' => 'datetime'];
    }

    public function referenceSet()
    {
        return $this->belongsTo(RkasReferenceSet::class, 'reference_set_uuid', 'uuid');
    }

    public function items()
    {
        return $this->hasMany(RkasItem::class, 'plan_uuid', 'uuid')->orderBy('bulan_dianggarkan')->orderBy('kode_kegiatan');
    }

    public function validations()
    {
        return $this->hasMany(RkasValidation::class, 'plan_uuid', 'uuid')->latest();
    }

    public function syncLogs()
    {
        return $this->hasMany(RkasSyncLog::class, 'plan_uuid', 'uuid')->latest('occurred_at');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'uuid');
    }

    public function hasValidationErrors(): bool
    {
        return $this->validations()->where('severity', 'error')->exists();
    }

    public function totalPlanned(): int
    {
        return (int) $this->items()->sum('total');
    }
}
