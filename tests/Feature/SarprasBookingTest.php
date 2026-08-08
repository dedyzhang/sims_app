<?php

namespace Tests\Feature;

use App\Models\User;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\Peminjaman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SarprasBookingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'sap_booking', 'password' => Hash::make('x'), 'access' => 'superadmin']);
    }

    private function room(): DenahRuangan
    {
        $denah = Denah::create(['nama' => 'Lantai 1', 'gambar_path' => 'x.png']);

        return DenahRuangan::create([
            'denah_id' => $denah->id, 'kode' => '7A', 'nama' => 'Kelas 7A',
            'pos_x' => 10, 'pos_y' => 10, 'status' => 'tersedia', 'kapasitas' => 32,
            'fasilitas' => ['Proyektor', 'AC'],
        ]);
    }

    public function test_pengajuan_ruangan_berstatus_diajukan(): void
    {
        $room = $this->room();

        $this->actingAs($this->admin())->post('/sarpras/peminjaman-ruangan', [
            'ruangan_id' => $room->id,
            'keperluan' => 'Rapat Komite',
            'tanggal' => '2026-07-01',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
        ])->assertRedirect();

        $this->assertDatabaseHas('sarpras_peminjaman', [
            'ruangan_id' => $room->id, 'keperluan' => 'Rapat Komite', 'status' => 'diajukan',
        ]);
    }

    public function test_ruangan_bentrok_ditolak(): void
    {
        $room = $this->room();
        $admin = $this->admin();

        Peminjaman::create([
            'kode' => 'PJM-TEST-1',
            'peminjam_id' => $admin->uuid,
            'ruangan_id' => $room->id,
            'keperluan' => 'Awal',
            'mulai' => Carbon::parse('2026-07-01 09:00'),
            'selesai' => Carbon::parse('2026-07-01 11:00'),
            'tgl_pinjam' => '2026-07-01',
            'tgl_kembali_rencana' => '2026-07-01',
            'status' => 'dipinjam',
        ]);

        $this->actingAs($admin)->post('/sarpras/peminjaman-ruangan', [
            'ruangan_id' => $room->id,
            'keperluan' => 'Bentrok',
            'tanggal' => '2026-07-01',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
        ])->assertSessionHas('gagal');

        $this->assertDatabaseMissing('sarpras_peminjaman', ['keperluan' => 'Bentrok']);
    }

    public function test_setujui_dan_tolak_peminjaman_ruangan(): void
    {
        $room = $this->room();
        $admin = $this->admin();

        $p = Peminjaman::create([
            'kode' => 'PJM-T-1',
            'peminjam_id' => $admin->uuid,
            'ruangan_id' => $room->id,
            'keperluan' => 'Acara',
            'mulai' => Carbon::parse('2026-07-02 08:00'),
            'selesai' => Carbon::parse('2026-07-02 10:00'),
            'tgl_pinjam' => '2026-07-02',
            'tgl_kembali_rencana' => '2026-07-02',
            'status' => 'diajukan',
        ]);

        $this->actingAs($admin)->post('/sarpras/peminjaman/' . $p->id . '/setujui')->assertRedirect();
        $this->assertSame('dipinjam', $p->fresh()->status);

        $p2 = Peminjaman::create([
            'kode' => 'PJM-T-2',
            'peminjam_id' => $admin->uuid,
            'ruangan_id' => $room->id,
            'keperluan' => 'Acara 2',
            'mulai' => Carbon::parse('2026-07-03 08:00'),
            'selesai' => Carbon::parse('2026-07-03 10:00'),
            'tgl_pinjam' => '2026-07-03',
            'tgl_kembali_rencana' => '2026-07-03',
            'status' => 'diajukan',
        ]);
        $this->actingAs($admin)->post('/sarpras/peminjaman/' . $p2->id . '/tolak', [
            'alasan_tolak' => 'Bentrok jadwal',
        ])->assertRedirect();
        $this->assertSame('ditolak', $p2->fresh()->status);
    }

    public function test_booking_route_redirect_ke_peminjaman_ruangan(): void
    {
        $this->actingAs($this->admin())
            ->get('/sarpras/booking')
            ->assertRedirect(route('sarpras.peminjaman.index', ['tab' => 'ruangan']));
    }
}
