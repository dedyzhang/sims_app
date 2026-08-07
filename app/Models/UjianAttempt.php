<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UjianAttempt extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ujian_attempts';
    protected $primaryKey = 'uuid';

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_DINILAI = 'dinilai';
    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $fillable = [
        'id_ujian_kelas', 'id_siswa', 'urutan_soal', 'urutan_opsi',
        'mulai_pada', 'batas_waktu_pada', 'selesai_pada',
        'status', 'dikunci', 'auto_submit',
        'skor_objektif', 'total_skor', 'butuh_penilaian_manual',
        'status_transfer_nilai', 'durasi_ms',
    ];

    protected function casts(): array
    {
        return [
            'urutan_soal'             => 'array',
            'urutan_opsi'             => 'array',
            'mulai_pada'              => 'datetime',
            'batas_waktu_pada'        => 'datetime',
            'selesai_pada'            => 'datetime',
            'dikunci'                 => 'boolean',
            'auto_submit'             => 'boolean',
            'skor_objektif'           => 'decimal:2',
            'total_skor'              => 'decimal:2',
            'butuh_penilaian_manual'  => 'boolean',
        ];
    }

    public function ujianKelas()
    {
        return $this->belongsTo(UjianKelas::class, 'id_ujian_kelas', 'uuid');
    }

    public function siswa()
    {
        return $this->belongsTo(User::class, 'id_siswa', 'uuid');
    }

    public function jawaban()
    {
        return $this->hasMany(UjianJawaban::class, 'id_attempt', 'uuid');
    }

    public function pelanggaran()
    {
        return $this->hasMany(UjianPelanggaran::class, 'id_attempt', 'uuid')->latest();
    }

    public function isLocked(): bool
    {
        return (bool) $this->dikunci;
    }

    public function isActive(): bool
    {
        return !in_array($this->status, [self::STATUS_DIBATALKAN, self::STATUS_DINILAI], true);
    }

    public function isExpired(): bool
    {
        return $this->batas_waktu_pada && now()->gte($this->batas_waktu_pada);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SUBMITTED   => 'Menunggu Penilaian',
            self::STATUS_DINILAI     => 'Selesai Dinilai',
            self::STATUS_DIBATALKAN  => 'Dibatalkan',
            default                  => 'Sedang Mengerjakan',
        };
    }
}
