<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OsisPemilih extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'osis_pemilih';
    protected $fillable = [
        'id_pemilihan', 'tipe_pemilih', 'id_siswa', 'id_guru',
        'nama_snapshot', 'nis_snapshot', 'kelas_snapshot', 'token',
        'id_paslon_dipilih', 'sudah_memilih_at', 'ip_saat_memilih', 'user_agent_saat_memilih',
    ];

    protected function casts(): array
    {
        return ['sudah_memilih_at' => 'datetime'];
    }

    public function pemilihan() { return $this->belongsTo(OsisPemilihan::class, 'id_pemilihan', 'uuid'); }
    public function siswa()     { return $this->belongsTo(Siswa::class, 'id_siswa', 'uuid'); }
    public function guru()      { return $this->belongsTo(Guru::class, 'id_guru', 'uuid'); }
    public function paslonDipilih() { return $this->belongsTo(OsisPaslon::class, 'id_paslon_dipilih', 'uuid'); }

    public function sudahMemilih(): bool
    {
        return $this->sudah_memilih_at !== null;
    }
}
