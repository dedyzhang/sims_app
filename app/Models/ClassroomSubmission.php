<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClassroomSubmission extends Model
{
    use HasUuids;

    protected $table = 'classroom_submissions';
    protected $primaryKey = 'uuid';
    protected $fillable = [
        'assignment_id', 'classroom_id', 'student_id', 'body', 'submitted_at', 'is_late',
        'score', 'feedback', 'graded_by', 'graded_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'graded_at'    => 'datetime',
            'is_late'      => 'boolean',
            'score'        => 'integer',
        ];
    }

    public function assignment()
    {
        return $this->belongsTo(ClassroomAssignment::class, 'assignment_id', 'uuid');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id', 'uuid');
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by', 'uuid');
    }

    public function files()
    {
        return $this->hasMany(ClassroomSubmissionFile::class, 'submission_id', 'uuid');
    }

    public const STATUS_LABEL = [
        'draft' => 'Draf', 'submitted' => 'Dikumpulkan', 'returned' => 'Dikembalikan', 'graded' => 'Dinilai',
    ];
}
