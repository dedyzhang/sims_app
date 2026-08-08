<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * `activity_log.subject_id`/`causer_id` dibuat nullableMorphs() default (unsignedBigInteger)
 * — cocok utk model auto-increment, tapi SEMUA model di app ini pakai UUID string sbg
 * primary key (User, GuruTidakHadir, dst). Di MySQL strict mode (produksi), insert UUID ke
 * kolom bigint gagal dgn "Data truncated for column 'causer_id'" — tak kelihatan di SQLite
 * lokal krn manifest typing-nya longgar. Test ini cuma cek TIPE KOLOM (bukan round-trip
 * insert, yg tak akan menangkap bug ini di SQLite) — lihat migration
 * 2026_08_08_120000_fix_activity_log_morph_id_columns_to_string.
 */
class ActivityLogMorphColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_dan_causer_id_bertipe_string_bukan_integer(): void
    {
        $this->assertSame('varchar', Schema::getColumnType('activity_log', 'subject_id'));
        $this->assertSame('varchar', Schema::getColumnType('activity_log', 'causer_id'));
    }

    public function test_activity_log_bisa_menyimpan_causer_dan_subject_uuid_utuh(): void
    {
        $user = \App\Models\User::create(['username' => 'guru_actlog', 'password' => 'x', 'access' => 'guru']);

        activity('piket')->causedBy($user)->log('uji log UUID');

        $row = \DB::table('activity_log')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame($user->uuid, $row->causer_id, 'causer_id harus tersimpan UTUH (36 char), bukan terpotong/dikonversi.');
    }
}
