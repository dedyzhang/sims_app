<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class OsisPaslon extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'osis_paslon';
    protected $fillable = ['id_pemilihan', 'nomor_urut', 'nama_ketua', 'nama_wakil', 'foto', 'visi', 'misi', 'urutan_tampil'];

    public function pemilihan()
    {
        return $this->belongsTo(OsisPemilihan::class, 'id_pemilihan', 'uuid');
    }

    /** Misi disimpan 1 poin/baris (textarea polos) — dipecah jadi <li> di halaman publik. */
    public function getMisiPointsAttribute(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->misi))
            ->map(fn ($l) => trim($l))->filter()->values()->all();
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto ? Storage::disk('public')->url($this->foto) : null;
    }
}
