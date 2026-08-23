<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\UjianPaket;
use App\Models\UjianRuangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 4: UjianRuangan = ruang ujian fisik dlm satu UjianPaket — roster siswa
 * lintas kelas. TIDAK ADA penugasan pengawas tersimpan — siapa yg mengawasi
 * ditentukan lewat scan QR ruangan (lihat UjianRuanganScanTest).
 */
class UjianRuanganTest extends TestCase
{
    use RefreshDatabase;

    private User $guruA;
    private User $guruB;
    private UjianPaket $paket;
    private Kelas $kelas7a;
    private Kelas $kelas8a;
    private Siswa $siswa1;
    private Siswa $siswa2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guruA = User::create(['username' => 'guru_ruangan_a', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $this->guruA->uuid, 'nama' => 'Guru Ruangan A', 'nik' => '3333333333', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->guruB = User::create(['username' => 'guru_ruangan_b', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $this->guruB->uuid, 'nama' => 'Guru Ruangan B', 'nik' => '4444444444', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->paket = UjianPaket::create([
            'nama' => 'PAS', 'jenis' => 'pas', 'created_by' => $this->guruA->uuid,
            'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2026-12-10',
        ]);

        $this->kelas7a = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->kelas8a = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);
        $siswa1User = User::create(['username' => 'siswa_ruangan_1', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $this->siswa1 = Siswa::create(['id_login' => $siswa1User->uuid, 'id_kelas' => $this->kelas7a->uuid, 'nama' => 'Siswa Ruangan 1', 'nis' => '7101', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $siswa2User = User::create(['username' => 'siswa_ruangan_2', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $this->siswa2 = Siswa::create(['id_login' => $siswa2User->uuid, 'id_kelas' => $this->kelas8a->uuid, 'nama' => 'Siswa Ruangan 2', 'nis' => '8101', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
    }

    public function test_pembuat_paket_bisa_membuat_ruangan(): void
    {
        $res = $this->actingAs($this->guruA)->post(route('ujian.paket.ruangan.store', $this->paket), [
            'nama' => 'Ruang 1', 'kapasitas' => 20,
        ]);
        $res->assertRedirect();
        $this->assertDatabaseHas('ujian_ruangan', ['id_ujian_paket' => $this->paket->uuid, 'nama' => 'Ruang 1']);
    }

    public function test_guru_lain_tak_bisa_membuat_ruangan_di_paket_orang(): void
    {
        $this->actingAs($this->guruB)
            ->post(route('ujian.paket.ruangan.store', $this->paket), ['nama' => 'Ruang X'])
            ->assertForbidden();
    }

    public function test_roster_bisa_lintas_kelas_dan_lepas_peserta(): void
    {
        $ruangan = UjianRuangan::create(['id_ujian_paket' => $this->paket->uuid, 'nama' => 'Ruang 1']);

        $this->actingAs($this->guruA)->post(route('ujian.paket.ruangan.peserta', [$this->paket, $ruangan]), [
            'id_siswa' => [$this->siswa1->uuid, $this->siswa2->uuid],
        ])->assertRedirect();

        $ruangan->refresh();
        $this->assertCount(2, $ruangan->peserta);
        $this->assertEqualsCanonicalizing(
            [$this->siswa1->uuid, $this->siswa2->uuid],
            $ruangan->peserta->pluck('id_siswa')->all()
        );

        $peserta1 = $ruangan->peserta->firstWhere('id_siswa', $this->siswa1->uuid);
        $this->actingAs($this->guruA)
            ->delete(route('ujian.paket.ruangan.peserta.destroy', [$this->paket, $ruangan, $peserta1]))
            ->assertRedirect();
        $this->assertCount(1, $ruangan->fresh()->peserta);
    }

    public function test_sync_peserta_tidak_dobel_saat_siswa_yang_sama_disubmit_ulang(): void
    {
        $ruangan = UjianRuangan::create(['id_ujian_paket' => $this->paket->uuid, 'nama' => 'Ruang 1']);

        $this->actingAs($this->guruA)->post(route('ujian.paket.ruangan.peserta', [$this->paket, $ruangan]), ['id_siswa' => [$this->siswa1->uuid]]);
        $this->actingAs($this->guruA)->post(route('ujian.paket.ruangan.peserta', [$this->paket, $ruangan]), ['id_siswa' => [$this->siswa1->uuid]]);

        $this->assertCount(1, $ruangan->fresh()->peserta);
    }

    public function test_guru_lain_tak_bisa_atur_peserta_ruangan_di_paket_orang(): void
    {
        $ruangan = UjianRuangan::create(['id_ujian_paket' => $this->paket->uuid, 'nama' => 'Ruang 1']);

        $this->actingAs($this->guruB)
            ->post(route('ujian.paket.ruangan.peserta', [$this->paket, $ruangan]), ['id_siswa' => [$this->siswa1->uuid]])
            ->assertForbidden();
    }

    public function test_halaman_show_ruangan_render_tanpa_error_dan_tampilkan_qr(): void
    {
        $ruangan = UjianRuangan::create(['id_ujian_paket' => $this->paket->uuid, 'nama' => 'Ruang 1']);
        $this->actingAs($this->guruA)->post(route('ujian.paket.ruangan.peserta', [$this->paket, $ruangan]), ['id_siswa' => [$this->siswa1->uuid]]);

        $this->actingAs($this->guruA)->get(route('ujian.paket.ruangan.show', [$this->paket, $ruangan]))
            ->assertOk()
            ->assertSee($this->siswa1->nama)
            ->assertSee(route('ujian.ruangan.scan', $ruangan));
    }

    public function test_poster_cetak_qr_render_tanpa_error(): void
    {
        $ruangan = UjianRuangan::create(['id_ujian_paket' => $this->paket->uuid, 'nama' => 'Ruang 1', 'kapasitas' => 25]);

        $this->actingAs($this->guruA)->get(route('ujian.paket.ruangan.cetak', [$this->paket, $ruangan]))
            ->assertOk()
            ->assertSee($ruangan->nama)
            ->assertSee($this->paket->nama)
            ->assertSee('Untuk Siswa')
            ->assertSee('Untuk Guru')
            ->assertSee($ruangan->uuid); // muncul di dalam URL scan yg di-embed (JSON-escaped) ke script QRious
    }

    public function test_poster_cetak_qr_ditolak_utk_guru_lain(): void
    {
        $ruangan = UjianRuangan::create(['id_ujian_paket' => $this->paket->uuid, 'nama' => 'Ruang 1']);

        $this->actingAs($this->guruB)
            ->get(route('ujian.paket.ruangan.cetak', [$this->paket, $ruangan]))
            ->assertForbidden();
    }
}
