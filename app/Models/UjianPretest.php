<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UjianPretest extends Model
{
    use HasUuids;

    protected $table = 'ujian_pretests';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'id_ujian',
        'token',
        'judul',
        'is_active',
        'durasi_menit',
        'acak_soal',
        'acak_opsi',
        'tampilkan_nilai',
        'tampilkan_pembahasan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'durasi_menit' => 'integer',
            'acak_soal' => 'boolean',
            'acak_opsi' => 'boolean',
            'tampilkan_nilai' => 'boolean',
            'tampilkan_pembahasan' => 'boolean',
        ];
    }

    public function setTokenAttribute($value): void
    {
        $this->attributes['token'] = strtoupper((string) $value);
    }

    public function ujian(): BelongsTo
    {
        return $this->belongsTo(Ujian::class, 'id_ujian', 'uuid');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(UjianPretestPeserta::class, 'id_pretest', 'uuid');
    }

    public function getUrlPublikAttribute(): string
    {
        return route('pretest.public.show', ['token' => $this->token]);
    }
}
