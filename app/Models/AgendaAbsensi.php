<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaAbsensi extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'agenda_absensi';
    protected $fillable = ['id_agenda', 'id_siswa', 'absensi', 'keterangan'];

    public function agenda()
    {
        return $this->belongsTo(Agenda::class, 'id_agenda', 'uuid');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'uuid');
    }
}
