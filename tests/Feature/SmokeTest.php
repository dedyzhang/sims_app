<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Smoke test: muat halaman-halaman utama sebagai admin & pastikan tidak ada
 * error server (5xx). Menangkap error runtime yang lolos dari lint/route/test
 * unit — mis. variabel view hilang, query rusak, dsb.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        // Admin tanpa profil guru/siswa → lolos gate EnsureFaceRegistered.
        return User::create([
            'username' => 'smoke_admin',
            'password' => Hash::make('password'),
            'access'   => 'superadmin',
        ]);
    }

    public static function halamanGet(): array
    {
        return [
            'dashboard'            => ['/dashboard'],
            'ticker-stats'         => ['/dashboard/ticker-stats'],
            'sarpras dashboard'    => ['/sarpras'],
            'sarpras booking'      => ['/sarpras/booking'],
            'keuangan'             => ['/keuangan'],
            'keuangan verifikasi'  => ['/keuangan/verifikasi'],
            'keuangan bank'        => ['/keuangan/bank'],
            'kalender absensi'     => ['/kalender-absensi'],
            'data guru'            => ['/guru'],
            'data kelas'           => ['/kelas'],
            'data siswa'           => ['/siswa'],
            'mata pelajaran'       => ['/pelajaran'],
            'jadwal'               => ['/jadwal'],
            'absensi'              => ['/absensi'],
            'absensi wajah'        => ['/absensi/wajah'],
            'absensi scan wajah'   => ['/absensi/scan'],
            'presensi guru'        => ['/presensi-guru'],
            'nilai'                => ['/nilai'],
            'rekap nilai'          => ['/rekap-nilai'],
            'forum'                => ['/forum'],
            'chatbot inbox'        => ['/chatbot/admin/inbox'],
            'profile'              => ['/profile'],
            'preferensi tampilan'  => ['/profile/tampilan'],
            'pengaturan'           => ['/settings'],
            'sarpras aset'         => ['/sarpras/aset'],
            'sarpras denah'        => ['/sarpras/denah'],
            'sarpras kerusakan'    => ['/sarpras/kerusakan'],
            'sarpras peminjaman'   => ['/sarpras/peminjaman'],
            'sarpras pengadaan'    => ['/sarpras/pengadaan'],
            'sarpras perbaikan'    => ['/sarpras/perbaikan'],
            'sarpras laporan'      => ['/sarpras/laporan'],
            'sarpras supplier'     => ['/sarpras/supplier'],
            'sarpras teknisi'      => ['/sarpras/teknisi'],
        ];
    }

    #[DataProvider('halamanGet')]
    public function test_halaman_tidak_error_server(string $url): void
    {
        $res = $this->actingAs($this->admin())->get($url);

        $this->assertLessThan(
            500,
            $res->getStatusCode(),
            "Halaman {$url} mengembalikan {$res->getStatusCode()} (server error)."
        );
    }

    public function test_siswa_tanpa_wajah_bisa_merender_halaman_wajah_saya(): void
    {
        $user = User::create([
            'username' => 'face_student',
            'password' => Hash::make('password'),
            'access'   => 'siswa',
        ]);

        Siswa::create([
            'id_login' => $user->getKey(),
            'nama'     => 'Siswa Face',
            'nis'      => 'FACE001',
            'jk'       => 'L',
        ]);

        $this->actingAs($user)
            ->get('/wajah-saya')
            ->assertOk()
            ->assertSee('Daftarkan Wajah Anda');
    }
}
