<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TugasKelas extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'tugas_kelas';
    protected $fillable = [
        'id_penugasan_pengganti', 'jenis', 'judul', 'deskripsi', 'file_path', 'file_nama_asli', 'dibuat_oleh', 'id_agenda', 'id_classroom_assignment',
    ];

    public const JENIS = [
        'upload_guru_asli' => 'Upload Guru Asli',
        'titip_manual_piket' => 'Titip Manual Piket',
    ];

    public function penugasanPengganti()
    {
        return $this->belongsTo(PenugasanPengganti::class, 'id_penugasan_pengganti', 'uuid');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh', 'uuid');
    }

    public function agenda()
    {
        return $this->belongsTo(Agenda::class, 'id_agenda', 'uuid');
    }

    public function classroomAssignment()
    {
        return $this->belongsTo(ClassroomAssignment::class, 'id_classroom_assignment', 'uuid');
    }

    public function isTerkonfirmasi(): bool
    {
        return $this->id_agenda !== null;
    }
}
