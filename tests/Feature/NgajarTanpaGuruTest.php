<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NgajarTanpaGuruTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Guru $guruA;
    private Guru $guruB;
    private Pelajaran $pelajaran;
    private Kelas $kelas7a;
    private Kelas $kelas7b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'admin_test',
            'password' => Hash::make('password123'),
            'access'   => 'admin',
        ]);

        $userA = User::create(['username' => 'guru_a', 'password' => Hash::make('pass'), 'access' => 'guru']);
        $this->guruA = Guru::create(['id_login' => $userA->uuid, 'nama' => 'Guru A', 'nik' => '1001', 'jk' => 'L']);

        $userB = User::create(['username' => 'guru_b', 'password' => Hash::make('pass'), 'access' => 'guru']);
        $this->guruB = Guru::create(['id_login' => $userB->uuid, 'nama' => 'Guru B', 'nik' => '1002', 'jk' => 'P']);

        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->kelas7a = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->kelas7b = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
    }

    public function test_halaman_ngajar_tanpa_guru_menampilkan_data_orphan(): void
    {
        // Buat penugasan untuk guruA
        $ngajar = Ngajar::create([
            'id_guru'      => $this->guruA->uuid,
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_kelas'     => $this->kelas7a->uuid,
        ]);

        // Hapus guruA
        $this->actingAs($this->admin)->delete(route('guru.destroy', $this->guruA->uuid));

        // Halaman ngajar-tanpa-guru harus menampilkan penugasan yang kehilangan guru
        $res = $this->actingAs($this->admin)->get(route('guru.ngajar-tanpa-guru'));
        $res->assertOk();
        $res->assertSee($this->pelajaran->nama);
        $res->assertSee('7A');
    }

    public function test_bisa_ganti_guru_pengganti_satuan(): void
    {
        $ngajar = Ngajar::create([
            'id_guru'      => $this->guruA->uuid,
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_kelas'     => $this->kelas7a->uuid,
        ]);

        // GuruA dihapus
        $this->guruA->delete();

        // Ganti ke GuruB
        $res = $this->actingAs($this->admin)->post(route('guru.ngajar-tanpa-guru.ganti', $ngajar->uuid), [
            'id_guru_tujuan' => $this->guruB->uuid,
        ]);

        $res->assertRedirect();
        $ngajar->refresh();
        $this->assertSame($this->guruB->uuid, $ngajar->id_guru);
    }

    public function test_bisa_bulk_ganti_guru_pengganti(): void
    {
        $ngajar1 = Ngajar::create([
            'id_guru'      => $this->guruA->uuid,
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_kelas'     => $this->kelas7a->uuid,
        ]);
        $ngajar2 = Ngajar::create([
            'id_guru'      => $this->guruA->uuid,
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_kelas'     => $this->kelas7b->uuid,
        ]);

        // GuruA dihapus
        $this->guruA->delete();

        // Bulk transfer ke GuruB
        $res = $this->actingAs($this->admin)->post(route('guru.ngajar-tanpa-guru.bulk-ganti'), [
            'id_ngajar'      => [$ngajar1->uuid, $ngajar2->uuid],
            'id_guru_tujuan' => $this->guruB->uuid,
        ]);

        $res->assertRedirect();
        $this->assertSame($this->guruB->uuid, $ngajar1->fresh()->id_guru);
        $this->assertSame($this->guruB->uuid, $ngajar2->fresh()->id_guru);
    }

    public function test_bisa_hapus_penugasan_tanpa_guru(): void
    {
        $ngajar = Ngajar::create([
            'id_guru'      => $this->guruA->uuid,
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_kelas'     => $this->kelas7a->uuid,
        ]);

        $this->guruA->delete();

        $res = $this->actingAs($this->admin)->delete(route('guru.ngajar-tanpa-guru.hapus', $ngajar->uuid));
        $res->assertRedirect();
        $this->assertDatabaseMissing('ngajars', ['uuid' => $ngajar->uuid]);
    }

    public function test_assign_normal_otomatis_ambil_alih_penugasan_orphan(): void
    {
        // Penugasan lama milik guruA
        $ngajarLama = Ngajar::create([
            'id_guru'      => $this->guruA->uuid,
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_kelas'     => $this->kelas7a->uuid,
        ]);

        // GuruA dihapus
        $this->guruA->delete();

        // GuruB di-assign mapel + kelas yang sama via form normal guru.ngajar
        $res = $this->actingAs($this->admin)->post(route('guru.ngajar', $this->guruB->uuid), [
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_kelas'     => [$this->kelas7a->uuid],
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');

        // Pastikan penugasan sekarang berpindah ke GuruB dan tidak diblokir bentrok
        $this->assertSame($this->guruB->uuid, $ngajarLama->fresh()->id_guru);
    }
}
