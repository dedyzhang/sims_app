<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ForumAudit extends Model
{
    use HasUuids;

    protected $table = 'forum_audits';
    protected $primaryKey = 'uuid';
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'meta'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }
}
