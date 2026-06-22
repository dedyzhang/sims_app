<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClassroomAssignmentFile extends Model
{
    use HasUuids;

    protected $table = 'classroom_assignment_files';
    protected $primaryKey = 'uuid';
    protected $fillable = ['assignment_id', 'original_name', 'stored_name', 'path', 'mime', 'size_original', 'size_compressed'];

    public function assignment()
    {
        return $this->belongsTo(ClassroomAssignment::class, 'assignment_id', 'uuid');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
