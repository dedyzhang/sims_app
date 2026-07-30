<?php

namespace Tests\Unit;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perbaikan performa nyata: dashboard admin sempat memuat ~67 query per render, salah satu
 * penyumbang terbesarnya adalah Setting::get() yang lama — memo-nya per-KEY, jadi tiap key
 * BEDA yang pertama kali dibaca = 1 query terpisah (aplikasi ini pakai ~50 key setting berbeda,
 * layout saja mengecek 22 modul). Fix: memo() sekarang memuat SEMUA baris settings sekaligus
 * (1 query) begitu get() pertama dipanggil, bukan lazy per-key. Test ini mengunci PERILAKU
 * (bukan cuma soal query count) supaya refactor lain di masa depan tak diam-diam merusaknya.
 */
class SettingMemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_mengembalikan_nilai_tersimpan(): void
    {
        Setting::set('nama_sekolah', 'SMP Contoh');

        $this->assertSame('SMP Contoh', Setting::get('nama_sekolah'));
    }

    public function test_get_mengembalikan_default_utk_key_yang_tak_ada(): void
    {
        $this->assertSame('fallback', Setting::get('key_yang_tak_pernah_diset', 'fallback'));
        $this->assertNull(Setting::get('key_yang_tak_pernah_diset'));
    }

    /** Inti perbaikan: menyentuh BANYAK key BERBEDA (lewat set() maupun get()) sepanjang request
     *  cuma boleh memicu SATU query SELECT ke tabel settings — bukan 1 query per key spt dulu.
     *  DB langsung (bukan Setting::create/set) dipakai utk 2 baris pertama supaya TIDAK memicu
     *  event `saved` yg akan menghangatkan memo lebih awal — memo baru boleh tersentuh saat
     *  Setting::get() pertama kali dipanggil di bawah, persis skenario baca-banyak-key nyata. */
    public function test_membaca_banyak_key_berbeda_hanya_satu_query(): void
    {
        DB::table('settings')->insert([
            ['key' => 'a', 'value' => '1'],
            ['key' => 'b', 'value' => '2'],
            ['key' => 'c', 'value' => '3'],
        ]);

        DB::enableQueryLog();
        Setting::get('a');
        Setting::get('b');
        Setting::get('c');
        Setting::get('d_tak_ada', 'default');
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $selectQueries = array_filter($log, fn ($q) => str_contains($q['query'], 'select') && str_contains($q['query'], 'settings'));
        $this->assertCount(
            1,
            $selectQueries,
            'Membaca 4 key berbeda (termasuk yg tak ada) semestinya cuma 1 query SELECT — memo harus memuat semua baris sekaligus, bukan per-key.'
        );
    }

    /** Mengulang baca key YANG SAMA berkali-kali tetap harus 0 query tambahan (memo bekerja). */
    public function test_membaca_key_yang_sama_berulang_tidak_query_lagi(): void
    {
        Setting::set('x', 'nilai');
        Setting::get('x'); // pemanasan memo

        DB::enableQueryLog();
        for ($i = 0; $i < 5; $i++) {
            Setting::get('x');
        }
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $log);
    }

    public function test_set_memperbarui_memo_langsung_tanpa_perlu_baca_ulang_dari_db(): void
    {
        Setting::set('flag', 'lama');
        $this->assertSame('lama', Setting::get('flag'));

        Setting::set('flag', 'baru');
        $this->assertSame('baru', Setting::get('flag'));
    }

    public function test_hapus_setting_membuat_get_balik_ke_default(): void
    {
        $s = Setting::create(['key' => 'sementara', 'value' => 'ada']);
        $this->assertSame('ada', Setting::get('sementara'));

        $s->delete();

        $this->assertSame('default', Setting::get('sementara', 'default'));
    }
}
