<?php

namespace App\Models;

use App\Support\PresentationSlides;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherPresentation extends Model
{
    use HasUuids;

    protected $table = 'teacher_presentations';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    public const STATUSES = [
        'draft' => 'Draft',
        'in_progress' => 'Dikerjakan',
        'done' => 'Selesai',
    ];

    protected $fillable = [
        'user_uuid',
        'title',
        'subject',
        'status',
        'outline',
        'notes',
        'slides',
        'canva_design_id',
        'canva_edit_url',
        'canva_view_url',
        'canva_exported_pdf_path',
        'canva_last_synced_at',
        'last_opened_at',
    ];

    protected function casts(): array
    {
        return [
            'slides' => 'array',
            'last_opened_at' => 'datetime',
            'canva_last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** @return list<array{title:string,body:string}> */
    public function resolvedSlides(): array
    {
        return PresentationSlides::normalize($this->slides, $this->outline);
    }
}
