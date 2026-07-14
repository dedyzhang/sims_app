<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Siswa extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'siswa';
    protected $fillable = [
        'id_login', 'nama', 'nis', 'nisn', 'id_kelas', 'jk',
        'tempat_lahir', 'tanggal_lahir', 'agama', 'alamat', 'no_handphone',
        'nama_ayah', 'pekerjaan_ayah', 'no_telp_ayah',
        'nama_ibu', 'pekerjaan_ibu', 'no_telp_ibu',
        'nama_wali', 'pekerjaan_wali', 'no_telp_wali',
        'sekolah_asal', 'nama_ijazah', 'ortu_ijazah',
        'tempat_lahir_ijazah', 'tanggal_lahir_ijazah',
        'va', 'spp', 'foto',
        'face_descriptor', 'face_registered_at', 'face_photo',
    ];

    protected $casts = [
        'face_descriptor'    => 'array',
        'face_registered_at' => 'datetime',
    ];

    public function getFacePhotoUrlAttribute(): ?string
    {
        return \App\Support\FaceMatch::photoUrl($this->face_photo, $this->uuid);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_login', 'uuid');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'uuid');
    }

    public function orangtua()
    {
        return $this->hasOne(Orangtua::class, 'id_siswa', 'uuid');
    }

    public function kartuPelajar()
    {
        return $this->hasOne(KartuPelajar::class, 'id_siswa', 'uuid');
    }

    public function rombels()
    {
        return $this->hasMany(Rombel::class, 'id_siswa', 'uuid');
    }

    public function sekretaris()
    {
        return $this->hasOne(Sekretaris::class, 'id_siswa', 'uuid');
    }

    public function pembayaran()
    {
        return $this->hasMany(SppPembayaran::class, 'id_siswa', 'uuid');
    }

    public function kaihJawaban()
    {
        return $this->hasMany(KaihJawaban::class, 'id_siswa', 'uuid');
    }
}
