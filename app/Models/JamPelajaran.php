<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JamPelajaran extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'jam_pelajaran';
    protected $fillable = ['jam_ke', 'jam_mulai', 'jam_selesai', 'jenis', 'label', 'urutan'];

    public function getRentangAttribute(): string
    {
        return \Carbon\Carbon::parse($this->jam_mulai)->format('H:i') . ' – ' . \Carbon\Carbon::parse($this->jam_selesai)->format('H:i');
    }

    public function isPelajaran(): bool
    {
        return $this->jenis === 'pelajaran';
    }
}
