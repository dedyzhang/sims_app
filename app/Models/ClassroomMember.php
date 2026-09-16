<?php

namespace App\Models;

use App\Policies\ClassroomPolicy;
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

    protected static function booted(): void
    {
        // ClassroomPolicy memoisasi keanggotaan per user untuk menghindari query
        // berulang saat merender daftar ruang kelas. Enrolment berubah di tengah
        // request yang sama (auto-enroll pindah kelas, set kelas massal, repair),
        // jadi memo itu harus dibuang begitu barisnya ditulis/dihapus.
        $forget = static fn (self $member) => ClassroomPolicy::forgetMemberMemo((string) $member->user_id);

        static::saved($forget);
        static::deleted($forget);
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
