<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankSoal extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'bank_soal';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_pelajaran', 'created_by', 'tipe', 'teks_soal', 'poin', 'urutan', 'meta', 'penjelasan'];

    protected function casts(): array
    {
        return [
            'poin'   => 'integer',
            'urutan' => 'integer',
            'meta'   => 'array',
        ];
    }

    public function pelajaran()
    {
        return $this->belongsTo(Pelajaran::class, 'id_pelajaran', 'uuid');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function opsi()
    {
        return $this->hasMany(BankSoalOpsi::class, 'id_soal', 'uuid')->orderBy('urutan');
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
