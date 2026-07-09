<?php

namespace Tests\Feature;

use App\Models\KartuPelajar;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Kartu Pelajar Digital: admin unggah per siswa, siswa unduh miliknya. */
class KartuPelajarTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $access, string $username): User
    {
        return User::create(['username' => $username, 'password' => bcrypt('rahasia123'), 'access' => $access]);
    }

    private function siswa(string $username): Siswa
    {
        $u = $this->user('siswa', $username);
        return Siswa::create(['id_login' => $u->getKey(), 'nama' => 'Siswa ' . $username, 'nis' => 'N' . $username, 'face_descriptor' => [0.1, 0.2]]);
    }

    /** Buat User+Siswa terhubung yang sudah lolos gate wajah, kembalikan keduanya. */
    private function siswaLogin(string $username, array $extra = []): array
    {
        $u = $this->user('siswa', $username);
        $s = Siswa::create(array_merge(['id_login' => $u->getKey(), 'nama' => $username, 'face_descriptor' => [0.1, 0.2]], $extra));
        return [$u, $s];
    }

    public function test_admin_upload_kartu(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin', 'kp_admin');
        $siswa = $this->siswa('kp_a');

        $this->actingAs($admin)->post(route('kartu-pelajar.store', $siswa->uuid), [
            'kartu' => UploadedFile::fake()->image('kartu.png'),
        ])->assertRedirect();

        $kartu = KartuPelajar::where('id_siswa', $siswa->uuid)->first();
        $this->assertNotNull($kartu);
        Storage::disk('local')->assertExists($kartu->path);
    }

    public function test_validasi_tolak_format_salah(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin', 'kp_admin2');
        $siswa = $this->siswa('kp_b');

        $this->actingAs($admin)->post(route('kartu-pelajar.store', $siswa->uuid), [
            'kartu' => UploadedFile::fake()->create('kartu.txt', 10),
        ])->assertSessionHasErrors('kartu');

        $this->assertDatabaseCount('kartu_pelajar', 0);
    }

    public function test_siswa_unduh_kartu_sendiri(): void
    {
        Storage::fake('local');
        [$u, $siswa] = $this->siswaLogin('kp_own');
        Storage::disk('local')->put('kartu-pelajar/own.png', 'IMG');
        KartuPelajar::create([
            'id_siswa'      => $siswa->uuid,
            'path'          => 'kartu-pelajar/own.png',
            'original_name' => 'kartu.png',
            'mime'          => 'image/png',
        ]);

        $this->actingAs($u)->get(route('kartu-pelajar.self'))->assertOk()->assertSee('Unduh Kartu');
        $this->actingAs($u)->get(route('kartu-pelajar.unduh'))->assertOk()->assertDownload('kartu.png');
    }

    public function test_siswa_lihat_kartu_otomatis(): void
    {
        [$u] = $this->siswaLogin('kp_auto', ['nama' => 'Rara Auto', 'nis' => '12345']);

        $this->actingAs($u)->get(route('kartu-pelajar.self'))
            ->assertOk()
            ->assertSee('KARTU TANDA PELAJAR')
            ->assertSee('Rara Auto')
            ->assertSee('Unduh Kartu (PDF)');
    }

    public function test_siswa_unduh_pdf_otomatis(): void
    {
        [$u] = $this->siswaLogin('kp_pdf', ['nama' => 'Budi PDF', 'nis' => '67890']);

        $res = $this->actingAs($u)->get(route('kartu-pelajar.unduh'));
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $res->headers->get('content-type'));
    }

    public function test_non_admin_tak_bisa_kelola(): void
    {
        [$u] = $this->siswaLogin('kp_nonadmin');

        $this->actingAs($u)->get(route('kartu-pelajar.kelola'))->assertForbidden();
    }

    public function test_admin_cetak_kartu_per_tingkat(): void
    {
        $admin = $this->user('admin', 'kp_cetak_admin');
        $kelas = \App\Models\Kelas::create(['tingkat' => '7', 'kelas' => 'A']);
        for ($i = 0; $i < 12; $i++) { // 12 siswa → >1 halaman (10/halaman)
            Siswa::create(['nama' => "Siswa $i", 'nis' => "70$i", 'id_kelas' => $kelas->uuid, 'jk' => $i % 2 ? 'P' : 'L']);
        }

        $res = $this->actingAs($admin)->get(route('kartu-pelajar.cetak', ['tingkat' => '7']));
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $res->headers->get('content-type'));
    }

    public function test_cetak_tingkat_kosong_404(): void
    {
        $admin = $this->user('admin', 'kp_cetak_empty');

        $this->actingAs($admin)->get(route('kartu-pelajar.cetak', ['tingkat' => '99']))->assertNotFound();
    }

    public function test_non_admin_tak_bisa_cetak(): void
    {
        [$u] = $this->siswaLogin('kp_cetak_non');

        $this->actingAs($u)->get(route('kartu-pelajar.cetak', ['tingkat' => '7']))->assertForbidden();
    }
}
