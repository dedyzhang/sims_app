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
        'status', 'dikunci', 'wajib_token_ulang', 'auto_submit',
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
            'wajib_token_ulang'       => 'boolean',
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

    /**
     * Skor SEMENTARA (soal objektif saja, esai dianggap 0) — supaya halaman Hasil tak
     * kosong ("—") selama status masih 'submitted' menunggu esai dinilai guru. Skala sama
     * persis dgn total_skor final (UjianGrader::normalisasiSkor()), jadi angkanya "naik" ke
     * skor final begitu esai selesai dinilai, bukan lompat skala. Null kalau memang belum
     * ada skor objektif sama sekali (belum submit). $totalPoin/$modeSkor opsional — kirim
     * dari caller kalau sudah dihitung di luar (roster banyak siswa, hindari N+1).
     */
    public function skorSementara(?int $totalPoin = null, ?string $modeSkor = null): ?float
    {
        if ($this->status === self::STATUS_DINILAI) {
            return $this->total_skor !== null ? (float) $this->total_skor : null;
        }
        if ($this->skor_objektif === null) {
            return null;
        }

        if ($totalPoin === null || $modeSkor === null) {
            $ujian = $this->ujianKelas->ujian;
            $totalPoin ??= (int) UjianSoal::where('id_ujian', $ujian->uuid)->get()->sum(fn (UjianSoal $s) => $s->poinEfektif());
            $modeSkor ??= $ujian->pelajaran?->mode_skor_ujian ?? 'rata_rata';
        }

        return \App\Services\UjianGrader::normalisasiSkor((float) $this->skor_objektif, $totalPoin, $modeSkor);
    }
}
