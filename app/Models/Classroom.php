<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Classroom extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'classrooms';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_semester', 'id_pelajaran', 'id_kelas', 'created_by', 'title', 'description',
        'cover_color', 'status', 'published_at', 'scheduled_publish_at', 'class_code',
    ];

    protected function casts(): array
    {
        return [
            'published_at'         => 'datetime',
            'scheduled_publish_at' => 'datetime',
        ];
    }

    public const STATUS_LABEL = [
        'draft' => 'Draf', 'scheduled' => 'Terjadwal', 'published' => 'Terbit', 'archived' => 'Arsip',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABEL[$this->status] ?? $this->status;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function getRouteKeyName(): string
    {
        return 'class_code';
    }

    // ─── Relasi ───
    public function semester()
    {
        return $this->belongsTo(Semester::class, 'id_semester', 'id');
    }

    public function pelajaran()
    {
        return $this->belongsTo(Pelajaran::class, 'id_pelajaran', 'uuid');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function kelas()
    {
        return $this->belongsToMany(Kelas::class, 'classroom_kelas', 'classroom_id', 'id_kelas', 'uuid', 'uuid');
    }

    /** Kelas (rombel) utama ruang ini — model baru: 1 ruang = 1 kelas + 1 mapel. */
    public function rombel()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'uuid');
    }

    public function members()
    {
        return $this->hasMany(ClassroomMember::class, 'classroom_id', 'uuid');
    }

    /** Materi yang tampil di kelas ini (taut many-to-many). */
    public function materials()
    {
        return $this->belongsToMany(ClassroomMaterial::class, 'classroom_material_links', 'classroom_id', 'material_id', 'uuid', 'uuid');
    }

    /** Latihan/tugas yang tampil di kelas ini (taut many-to-many). */
    public function assignments()
    {
        return $this->belongsToMany(ClassroomAssignment::class, 'classroom_assignment_links', 'classroom_id', 'assignment_id', 'uuid', 'uuid');
    }

    /** Forum diskusi yang ter-auto-generate untuk ruang kelas ini. */
    public function forumTopic()
    {
        return $this->hasOne(ForumTopic::class, 'classroom_id', 'uuid');
    }
}
