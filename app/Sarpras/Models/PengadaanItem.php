<?php

namespace App\Sarpras\Models;

use App\Sarpras\Support\Rupiah;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengadaanItem extends SarprasModel
{
    protected $table = 'sarpras_pengadaan_item';

    protected $fillable = [
        'school_id', 'pengadaan_id', 'kategori_id', 'supplier_id',
        'nama_barang', 'qty', 'satuan', 'estimasi_harga',
        'qty_diterima', 'kondisi_terima', 'tgl_terima',
    ];

    protected $casts = [
        'qty' => 'integer',
        'qty_diterima' => 'integer',
        'estimasi_harga' => 'integer',     // UANG integer rupiah (BCMath)
        'tgl_terima' => 'date',
    ];

    public function pengadaan(): BelongsTo
    {
        return $this->belongsTo(Pengadaan::class, 'pengadaan_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriAset::class, 'kategori_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /** Subtotal item = harga × qty (BCMath). */
    public function getSubtotalAttribute(): string
    {
        return Rupiah::mul($this->estimasi_harga, $this->qty);
    }

    public function getSubtotalRpAttribute(): string
    {
        return Rupiah::format($this->subtotal);
    }
}
