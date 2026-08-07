<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UjianPelanggaran extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ujian_pelanggaran';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_attempt', 'id_siswa', 'tipe', 'detail'];

    public function attempt()
    {
        return $this->belongsTo(UjianAttempt::class, 'id_attempt', 'uuid');
    }

    public function siswa()
    {
        return $this->belongsTo(User::class, 'id_siswa', 'uuid');
    }

    public function tipeLabel(): string
    {
        return match ($this->tipe) {
            'keluar_fullscreen' => 'Keluar Fullscreen',
            'ganti_tab'         => 'Berpindah Tab',
            'reset_oleh_guru'   => 'Dibuka Kunci Guru',
            'direset_admin'     => 'Direset Ulang Admin',
            default             => $this->tipe,
        };
    }
}
