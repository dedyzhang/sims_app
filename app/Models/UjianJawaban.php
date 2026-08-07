<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UjianJawaban extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ujian_jawaban';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'id_attempt', 'id_soal', 'id_opsi_dipilih', 'opsi_dipilih_multi', 'jawaban_pasangan',
        'jawaban_esai', 'is_benar', 'skor_diperoleh', 'dinilai_manual', 'dinilai_oleh',
        'catatan_guru', 'dijawab_pada',
    ];

    protected function casts(): array
    {
        return [
            'opsi_dipilih_multi' => 'array',
            'jawaban_pasangan'   => 'array',
            'is_benar'           => 'boolean',
            'skor_diperoleh'     => 'decimal:2',
            'dinilai_manual'     => 'boolean',
            'dijawab_pada'       => 'datetime',
        ];
    }

    public function attempt()
    {
        return $this->belongsTo(UjianAttempt::class, 'id_attempt', 'uuid');
    }

    public function soal()
    {
        return $this->belongsTo(UjianSoal::class, 'id_soal', 'uuid');
    }

    public function selectedOption()
    {
        return $this->belongsTo(UjianSoalOpsi::class, 'id_opsi_dipilih', 'uuid');
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'dinilai_oleh', 'uuid');
    }
}
