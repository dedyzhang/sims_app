<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UjianKelas extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ujian_kelas';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_ujian', 'id_kelas', 'id_guru_pengampu', 'token_masuk', 'dibuka_mulai', 'dibuka_sampai', 'status'];

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

    public function guruPengampu()
    {
        return $this->belongsTo(Guru::class, 'id_guru_pengampu', 'uuid');
    }

    public function attempts()
    {
        return $this->hasMany(UjianAttempt::class, 'id_ujian_kelas', 'uuid');
    }

    public static function generateToken(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
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

    /** Sudah terbit & py jadwal, tapi jam bukanya belum sampai — beda dari "ditutup"/belum terbit sama sekali, dipakai utk tampilkan badge "Belum Dimulai" + jam buka ke siswa. */
    public function belumDimulai(): bool
    {
        return $this->status !== 'closed'
            && $this->ujian?->isPublished()
            && $this->dibuka_mulai
            && now()->lt($this->dibuka_mulai);
    }
}
