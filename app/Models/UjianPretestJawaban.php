<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UjianPretestJawaban extends Model
{
    use HasUuids;

    protected $table = 'ujian_pretest_jawabans';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'id_peserta',
        'id_soal',
        'id_opsi_dipilih',
        'opsi_dipilih_multi',
        'jawaban_pasangan',
        'jawaban_esai',
        'is_benar',
        'skor_diperoleh',
        'dijawab_pada',
    ];

    protected function casts(): array
    {
        return [
            'opsi_dipilih_multi' => 'array',
            'jawaban_pasangan' => 'array',
            'is_benar' => 'boolean',
            'skor_diperoleh' => 'float',
            'dijawab_pada' => 'datetime',
        ];
    }

    public function peserta(): BelongsTo
    {
        return $this->belongsTo(UjianPretestPeserta::class, 'id_peserta', 'uuid');
    }

    public function soal(): BelongsTo
    {
        return $this->belongsTo(UjianSoal::class, 'id_soal', 'uuid');
    }

    public function opsi(): BelongsTo
    {
        return $this->belongsTo(UjianSoalOpsi::class, 'id_opsi_dipilih', 'uuid');
    }
}
