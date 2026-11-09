<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UjianPretestPeserta extends Model
{
    use HasUuids;

    protected $table = 'ujian_pretest_pesertas';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'id_pretest',
        'guest_token',
        'nama',
        'identitas',
        'nomor_kontak',
        'urutan_soal',
        'urutan_opsi',
        'mulai_pada',
        'batas_waktu_pada',
        'selesai_pada',
        'status',
        'skor_objektif',
        'total_skor',
        'jumlah_benar',
        'jumlah_salah',
        'total_soal',
    ];

    protected function casts(): array
    {
        return [
            'urutan_soal' => 'array',
            'urutan_opsi' => 'array',
            'mulai_pada' => 'datetime',
            'batas_waktu_pada' => 'datetime',
            'selesai_pada' => 'datetime',
            'skor_objektif' => 'float',
            'total_skor' => 'float',
            'jumlah_benar' => 'integer',
            'jumlah_salah' => 'integer',
            'total_soal' => 'integer',
        ];
    }

    public function pretest(): BelongsTo
    {
        return $this->belongsTo(UjianPretest::class, 'id_pretest', 'uuid');
    }

    public function jawaban(): HasMany
    {
        return $this->hasMany(UjianPretestJawaban::class, 'id_peserta', 'uuid');
    }

    public function isExpired(): bool
    {
        if ($this->status === 'submitted') {
            return false;
        }

        return $this->batas_waktu_pada && now()->greaterThan($this->batas_waktu_pada);
    }

    public function sisaDetik(): ?int
    {
        if (! $this->batas_waktu_pada) {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($this->batas_waktu_pada, false));
    }
}
