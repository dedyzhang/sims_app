<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UjianKelas extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ujian_kelas';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_ujian', 'id_kelas', 'token_masuk', 'dibuka_mulai', 'dibuka_sampai', 'status'];

    protected function casts(): array
    {
        return [
            'dibuka_mulai'  => 'datetime',
            'dibuka_sampai' => 'datetime',
        ];
    }

    public function ujian()
    {
        return $this->belongsTo(Ujian::class, 'id_ujian', 'uuid');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'uuid');
    }

    public function attempts()
    {
        return $this->hasMany(UjianAttempt::class, 'id_ujian_kelas', 'uuid');
    }

    public static function generateToken(): string
    {
        return Str::upper(Str::random(6));
    }

    public function isOpenNow(): bool
    {
        if ($this->status === 'closed' || !$this->ujian?->isPublished()) {
            return false;
        }
        if ($this->dibuka_mulai && now()->lt($this->dibuka_mulai)) {
            return false;
        }
        if ($this->dibuka_sampai && now()->gt($this->dibuka_sampai)) {
            return false;
        }
        return true;
    }
}
