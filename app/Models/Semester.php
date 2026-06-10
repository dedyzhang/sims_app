<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    protected $fillable = ['semester', 'tahun', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public static function aktif(): ?self
    {
        return static::where('aktif', true)->first();
    }

    public function getNamaLengkapAttribute(): string
    {
        return "Semester {$this->semester} - {$this->tahun}";
    }
}
