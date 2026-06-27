<?php

namespace App\Sarpras\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutasiAset extends SarprasModel
{
    protected $table = 'sarpras_mutasi_aset';

    protected $fillable = [
        'school_id', 'aset_id', 'ruangan_asal_id', 'ruangan_tujuan_id',
        'alasan', 'tgl_mutasi', 'dilakukan_oleh',
    ];

    protected $casts = ['tgl_mutasi' => 'date'];

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }

    public function ruanganAsal(): BelongsTo
    {
        return $this->belongsTo(DenahRuangan::class, 'ruangan_asal_id');
    }

    public function ruanganTujuan(): BelongsTo
    {
        return $this->belongsTo(DenahRuangan::class, 'ruangan_tujuan_id');
    }

    public function pelaksana(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dilakukan_oleh');
    }
}
