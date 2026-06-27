<?php

namespace App\Sarpras\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends SarprasModel
{
    protected $table = 'sarpras_supplier';

    protected $fillable = [
        'school_id', 'nama', 'kontak', 'telepon', 'email', 'alamat', 'npwp',
    ];

    public function pengadaanItem(): HasMany
    {
        return $this->hasMany(PengadaanItem::class, 'supplier_id');
    }
}
