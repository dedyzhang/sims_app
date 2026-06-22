<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClassroomSubmissionFile extends Model
{
    use HasUuids;

    protected $table = 'classroom_submission_files';
    protected $primaryKey = 'uuid';
    protected $fillable = ['submission_id', 'original_name', 'stored_name', 'path', 'mime', 'size_original', 'size_compressed'];

    public function submission()
    {
        return $this->belongsTo(ClassroomSubmission::class, 'submission_id', 'uuid');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
