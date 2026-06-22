<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClassroomLockEvent extends Model
{
    use HasUuids;

    protected $table = 'classroom_lock_events';
    protected $primaryKey = 'uuid';
    protected $fillable = ['lockable_type', 'lockable_id', 'student_id', 'type', 'reason'];

    public function lockable()
    {
        return $this->morphTo();
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id', 'uuid');
    }
}
