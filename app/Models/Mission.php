<?php

namespace App\Models;

use App\Support\ArenaJenjang;
use App\Support\ArenaMechanics;
use Database\Factories\MissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mission extends Model
{
    /** @use HasFactory<MissionFactory> */
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'classroom_id',
        'created_by',
        'slug',
        'title',
        'subject',
        'grade_level',
        'mechanic_type',
        'summary',
        'objectives',
        'duration_minutes',
        'max_score',
        'is_published',
        'requires_reflection',
        'visible_to_teachers',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'max_score' => 'integer',
            'is_published' => 'boolean',
            'requires_reflection' => 'boolean',
            'visible_to_teachers' => 'boolean',
            'objectives' => 'array',
            'meta' => 'array',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'uuid');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(MissionStep::class, 'mission_id', 'uuid')->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(MissionAttempt::class, 'mission_id', 'uuid');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MissionAssignment::class, 'mission_id', 'uuid');
    }

    public function assignmentFor(Classroom $classroom): ?MissionAssignment
    {
        return $this->assignments->firstWhere('classroom_id', $classroom->uuid)
            ?? $this->assignments()->where('classroom_id', $classroom->uuid)->first();
    }

    public function reflectionPrompts(): HasMany
    {
        return $this->hasMany(MissionReflectionPrompt::class, 'mission_id', 'uuid')->orderBy('position');
    }

    public function isPublished(): bool
    {
        return $this->is_published || $this->status === 'published';
    }

    /** Misi siap dimainkan: sudah punya langkah (bukan cangkang metadata saja). */
    public function isPlayable(): bool
    {
        if (array_key_exists('steps_count', $this->attributes)) {
            return (int) $this->attributes['steps_count'] > 0;
        }

        if ($this->relationLoaded('steps')) {
            return $this->steps->isNotEmpty();
        }

        return $this->steps()->exists();
    }

    public function mechanicLabel(): string
    {
        return ArenaMechanics::label($this->mechanic_type);
    }

    /** Kunci jenjang: sd | smp | sma | umum */
    public function jenjangKey(): string
    {
        return ArenaJenjang::fromGradeLevel($this->grade_level, $this->meta);
    }

    public function jenjangLabel(): string
    {
        $key = $this->jenjangKey();

        return $key === 'umum'
            ? ($this->grade_level ?: 'Umum')
            : ArenaJenjang::label($key);
    }

    public function isTren(): bool
    {
        $meta = $this->meta;

        return is_array($meta) && ! empty($meta['tren']);
    }

    public function trenTag(): ?string
    {
        $meta = $this->meta;

        return is_array($meta) && isset($meta['tren_tag']) && is_string($meta['tren_tag'])
            ? $meta['tren_tag']
            : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getRouteKey(): mixed
    {
        return $this->slug;
    }
}
