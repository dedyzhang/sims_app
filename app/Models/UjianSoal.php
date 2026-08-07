<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UjianSoal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ujian_soal';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_ujian', 'tipe', 'teks_soal', 'poin', 'urutan', 'meta', 'penjelasan'];

    protected function casts(): array
    {
        return [
            'poin'    => 'integer',
            'urutan'  => 'integer',
            'meta'    => 'array',
        ];
    }

    public function ujian()
    {
        return $this->belongsTo(Ujian::class, 'id_ujian', 'uuid');
    }

    public function opsi()
    {
        return $this->hasMany(UjianSoalOpsi::class, 'id_soal', 'uuid')->orderBy('urutan');
    }

    public function butuhOpsi(): bool
    {
        return in_array($this->tipe, ['mcq', 'mcq_complex', 'true_false'], true);
    }

    public function typeLabel(): string
    {
        return match ($this->tipe) {
            'mcq'         => 'Pilihan Ganda',
            'mcq_complex' => 'Pilihan Ganda Kompleks',
            'true_false'  => 'Benar/Salah',
            'match'       => 'Mencocokkan',
            'essay'       => 'Esai',
            default       => $this->tipe,
        };
    }
}
