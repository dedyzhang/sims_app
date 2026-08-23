<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RkasReferenceSet extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'rkas_reference_sets';

    protected $fillable = [
        'label', 'tahun_anggaran', 'versi', 'jenjang', 'sumber_dana',
        'source_url', 'source_checksum', 'rules', 'metadata', 'imported_by', 'is_active',
    ];

    protected function casts(): array
    {
        return ['tahun_anggaran' => 'integer', 'rules' => 'array', 'metadata' => 'array', 'is_active' => 'boolean'];
    }

    public function references()
    {
        return $this->hasMany(RkasReference::class, 'reference_set_uuid', 'uuid')->orderBy('kode_kegiatan');
    }

    public function plans()
    {
        return $this->hasMany(RkasPlan::class, 'reference_set_uuid', 'uuid');
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by', 'uuid');
    }
}
