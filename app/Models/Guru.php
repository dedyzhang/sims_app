<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guru extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $fillable = [
        'id_login', 'nama', 'nik', 'nip', 'jk', 'tempat_lahir',
        'tanggal_lahir', 'agama', 'alamat', 'tingkat_studi', 'program_studi',
        'universitas', 'tahun_tamat', 'tmt_ngajar', 'tmt_smp', 'no_telp', 'foto',
        'face_descriptor', 'face_registered_at', 'face_photo', 'sekretaris_rapat',
    ];

    protected $casts = [
        'face_descriptor'    => 'array',
        'face_registered_at' => 'datetime',
        'sekretaris_rapat'   => 'boolean',
    ];

    public function getFacePhotoUrlAttribute(): ?string
    {
        return \App\Support\FaceMatch::photoUrl($this->face_photo, $this->uuid);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_login', 'uuid');
    }

    public function walikelas()
    {
        return $this->hasOne(Walikelas::class, 'id_guru', 'uuid');
    }

    public function ngajars()
    {
        return $this->hasMany(Ngajar::class, 'id_guru', 'uuid');
    }

    public function perangkatUploads()
    {
        return $this->hasMany(PerangkatAjarGuru::class, 'id_guru', 'uuid');
    }

    public function rapatHadir()
    {
        return $this->belongsToMany(Rapat::class, 'rapat_hadir', 'id_guru', 'id_rapat', 'uuid', 'uuid')->withTimestamps();
    }

    public function getNamaLoginAttribute(): string
    {
        return $this->user?->username ?? '-';
    }
}
