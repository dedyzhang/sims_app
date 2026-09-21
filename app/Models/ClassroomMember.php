<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClassroomMember extends Model
{
    use HasUuids;

    protected $table = 'classroom_members';
    protected $primaryKey = 'uuid';
    protected $fillable = ['classroom_id', 'user_id', 'role_in_class', 'joined_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
    }

    /**
     * ClassroomPolicy::isMember() memo per-user statis (mirip pola Setting::memo()) — kalau
     * baris keanggotaan berubah, lupakan cache user itu supaya cek akses berikutnya dalam
     * proses/request yang sama membaca data terbaru, bukan snapshot dari cek sebelumnya.
     */
    protected static function booted(): void
    {
        static::created(fn (self $m) => \App\Policies\ClassroomPolicy::lupakanCacheAnggota($m->user_id));
        static::deleted(fn (self $m) => \App\Policies\ClassroomPolicy::lupakanCacheAnggota($m->user_id));
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'uuid');
    }
}
