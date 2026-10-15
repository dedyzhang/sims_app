<?php

namespace Tests\Feature;

use App\Models\User;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\KategoriAset;
use App\Sarpras\Models\SarprasModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SarprasAsetTest extends TestCase
{
    use RefreshDatabase;

    private function userSarpras(): User
    {
        return User::create([
            'username' => 'petugas_sarpras',
            'password' => Hash::make('password'),
            'access' => 'sarpras',
        ]);
    }

    public function test_penambahan_aset_berhasil_tanpa_error_ludensa(): void
    {
        $user = $this->userSarpras();

        $kategori = KategoriAset::create([
            'kode' => 'ELK',
            'nama' => 'Elektronik',
        ]);

        $denah = Denah::create([
            'nama' => 'Gedung A',
            'gambar_path' => 'gedung_a.png',
        ]);

        $ruangan = DenahRuangan::create([
            'denah_id' => $denah->id,
            'kode' => 'LAB-KOMP',
            'nama' => 'Laboratorium Komputer',
            'pos_x' => 10,
            'pos_y' => 10,
        ]);

        $response = $this->actingAs($user)->post('/sarpras/aset', [
            'kode' => 'AST-2026-001',
            'nama' => 'Laptop Lenovo ThinkPad',
            'kategori_id' => $kategori->id,
            'ruangan_id' => $ruangan->id,
            'merk' => 'Lenovo',
            'kondisi' => 'baik',
            'status' => 'aktif',
            'tgl_perolehan' => '2026-01-10',
            'nilai_perolehan' => 12500000,
            'masa_manfaat_tahun' => 5,
            'sumber_dana' => 'BOS Reguler',
            'spek_key' => ['RAM', 'Processor'],
            'spek_val' => ['16GB', 'Intel Core i7'],
        ]);

        $response->assertSessionHasNoErrors();

        $aset = Aset::where('kode', 'AST-2026-001')->first();
        $this->assertNotNull($aset);
        $response->assertRedirect(route('sarpras.aset.show', $aset));

        $this->assertEquals(SarprasModel::SCHOOL_ID, $aset->school_id);
        $this->assertEquals('Laptop Lenovo ThinkPad', $aset->nama);
        $this->assertEquals(12500000, $aset->nilai_perolehan);
        $this->assertEquals(['RAM' => '16GB', 'Processor' => 'Intel Core i7'], $aset->spesifikasi);
    }

    public function test_validasi_kode_aset_unik_per_sekolah(): void
    {
        $user = $this->userSarpras();

        Aset::create([
            'kode' => 'AST-EXISTING',
            'nama' => 'Proyektor BenQ',
            'kondisi' => 'baik',
            'status' => 'aktif',
            'nilai_perolehan' => 7500000,
        ]);

        $response = $this->actingAs($user)->post('/sarpras/aset', [
            'kode' => 'AST-EXISTING',
            'nama' => 'Proyektor Lain',
            'kondisi' => 'baik',
            'status' => 'aktif',
            'nilai_perolehan' => 5000000,
        ]);

        $response->assertSessionHasErrors(['kode']);
    }
}
