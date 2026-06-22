<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClassroomMaterialFile extends Model
{
    use HasUuids;

    protected $table = 'classroom_material_files';
    protected $primaryKey = 'uuid';
    protected $fillable = [
        'material_id', 'original_name', 'stored_name', 'path', 'mime',
        'size_original', 'size_compressed', 'sort_order',
    ];

    public function material()
    {
        return $this->belongsTo(ClassroomMaterial::class, 'material_id', 'uuid');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
