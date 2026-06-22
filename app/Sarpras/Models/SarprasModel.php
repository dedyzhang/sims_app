<?php

namespace App\Sarpras\Models;

use App\Sarpras\Concerns\HasRupiahFormat;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| Base model modul Sarpras (terintegrasi SIMS — single tenant).
|--------------------------------------------------------------------------
| - HasUuids        : primary key UUID (konvensi SIMS).
| - HasRupiahFormat : helper tampilan uang.
|
| Catatan integrasi:
|   SIMS adalah aplikasi satu sekolah (tanpa multi-tenant) dan tidak memakai
|   spatie/activitylog. Kolom `school_id` tetap ada agar index/unique komposit
|   yang sudah dirancang tetap valid, dan diisi otomatis konstanta SCHOOL_ID.
|   Trait tenant (BelongsToSchool) & LogsActivity versi standalone dihapus.
*/
abstract class SarprasModel extends Model
{
    use HasUuids, HasRupiahFormat;

    /** Tenant tunggal SIMS — menjaga unique(['school_id','kode']) tetap efektif. */
    public const SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->school_id)) {
                $model->school_id = static::SCHOOL_ID;
            }
        });
    }
}
