<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\JamPelajaran;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Jam khusus (istirahat/upacara/dll) bisa dibuat spesifik utk sebagian kelas saja
 * (kelas_scope) — kelas lain tetap dapat slot pelajaran biasa pada jam yang sama
 * (istirahat bergilir per-kelas), bukan lagi selalu berlaku utk SEMUA kelas sekaligus.
 */
class JadwalSpesialKelasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Kelas $kelas7;
    private Kelas $kelas8;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create([
            'username' => 'admin_jadwal_spesial',
            'password' => Hash::make('x'),
            'access' => 'superadmin',
        ]);
        $this->kelas7 = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->kelas8 = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);
        // Migration jam_pelajaran menyeeding 10 slot default (termasuk 2 istirahat global) utk
        // tiap hari agar grid langsung terisi di instalasi baru — dibersihkan di sini supaya
        // tiap test membangun struktur jam-nya sendiri yg terkontrol penuh, tanpa slot lain
        // yg tak terduga ikut nongol di assertion (mis. "Istirahat" dari slot bawaan lain).
        JamPelajaran::query()->delete();
    }

    private function buatIstirahatSebagianKelas(int $hari = 1): JamPelajaran
    {
        return JamPelajaran::create([
            'hari' => $hari, 'jam_mulai' => '09:00', 'jam_selesai' => '09:20',
            'jenis' => 'istirahat', 'label' => 'Istirahat', 'urutan' => 1,
            'kelas_scope' => [$this->kelas7->uuid],
        ]);
    }

    // ===== Model helpers =====

    public function test_untuk_semua_kelas_true_saat_kosong(): void
    {
        $jam = JamPelajaran::create(['hari' => 1, 'jam_mulai' => '09:00', 'jam_selesai' => '09:20', 'jenis' => 'istirahat', 'urutan' => 1]);
        $this->assertTrue($jam->untukSemuaKelas());
        $this->assertTrue($jam->berlakuUntukKelas($this->kelas7->uuid));
        $this->assertTrue($jam->berlakuUntukKelas($this->kelas8->uuid));
        $this->assertTrue($jam->isKhususUntukKelas($this->kelas7->uuid));
    }

    public function test_kelas_scope_membatasi_cakupan(): void
    {
        $jam = $this->buatIstirahatSebagianKelas();
        $this->assertFalse($jam->untukSemuaKelas());
        $this->assertTrue($jam->isKhususUntukKelas($this->kelas7->uuid));
        $this->assertFalse($jam->isKhususUntukKelas($this->kelas8->uuid));
    }

    public function test_jam_pelajaran_biasa_tidak_pernah_khusus(): void
    {
        $jam = JamPelajaran::create(['hari' => 1, 'jam_ke' => 1, 'jam_mulai' => '07:00', 'jam_selesai' => '07:40', 'jenis' => 'pelajaran', 'urutan' => 1]);
        $this->assertFalse($jam->isKhususUntukKelas($this->kelas7->uuid));
        $this->assertFalse($jam->isKhususUntukKelas($this->kelas8->uuid));
    }

    // ===== jamStore() =====

    public function test_jam_store_menyimpan_kelas_scope_utk_jam_khusus(): void
    {
        $this->actingAs($this->admin)->post(route('jadwal.jam.store'), [
            'hari' => 1, 'jam_mulai' => '09:00', 'jam_selesai' => '09:20',
            'jenis' => 'istirahat', 'label' => 'Istirahat',
            'kelas_scope' => [$this->kelas7->uuid],
        ])->assertRedirect();

        $jam = JamPelajaran::where('hari', 1)->where('jenis', 'istirahat')->first();
        $this->assertNotNull($jam);
        $this->assertSame([$this->kelas7->uuid], $jam->kelas_scope);
    }

    public function test_jam_store_kosong_kelas_scope_berarti_semua_kelas(): void
    {
        $this->actingAs($this->admin)->post(route('jadwal.jam.store'), [
            'hari' => 1, 'jam_mulai' => '09:00', 'jam_selesai' => '09:20',
            'jenis' => 'istirahat', 'label' => 'Istirahat',
        ])->assertRedirect();

        $jam = JamPelajaran::where('hari', 1)->where('jenis', 'istirahat')->first();
        $this->assertNull($jam->kelas_scope);
        $this->assertTrue($jam->untukSemuaKelas());
    }

    public function test_jam_store_pelajaran_mengabaikan_kelas_scope(): void
    {
        // kelas_scope cuma relevan utk jam khusus — kalau dikirim utk jenis pelajaran, diabaikan.
        $this->actingAs($this->admin)->post(route('jadwal.jam.store'), [
            'hari' => 1, 'jam_ke' => 1, 'jam_mulai' => '07:00', 'jam_selesai' => '07:40',
            'jenis' => 'pelajaran', 'kelas_scope' => [$this->kelas7->uuid],
        ])->assertRedirect();

        $jam = JamPelajaran::where('hari', 1)->where('jenis', 'pelajaran')->first();
        $this->assertNull($jam->kelas_scope);
    }

    // ===== saveCell() guard =====

    public function test_save_cell_ditolak_utk_kelas_yang_sedang_istirahat(): void
    {
        $jam = $this->buatIstirahatSebagianKelas();
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'urutan' => 1, 'jp' => 4]);

        $this->actingAs($this->admin)->postJson(route('jadwal.cell.save'), [
            'id_kelas' => $this->kelas7->uuid, 'hari' => 1, 'id_jam' => $jam->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertDatabaseMissing('jadwals', ['id_kelas' => $this->kelas7->uuid, 'id_jam' => $jam->uuid]);
    }

    public function test_save_cell_tetap_bisa_utk_kelas_yang_tidak_istirahat(): void
    {
        $jam = $this->buatIstirahatSebagianKelas(); // hanya scope ke kelas7
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'urutan' => 1, 'jp' => 4]);

        $this->actingAs($this->admin)->postJson(route('jadwal.cell.save'), [
            'id_kelas' => $this->kelas8->uuid, 'hari' => 1, 'id_jam' => $jam->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('jadwals', [
            'id_kelas' => $this->kelas8->uuid, 'id_jam' => $jam->uuid, 'id_pelajaran' => $pelajaran->uuid,
        ]);
    }

    // ===== Editor grid (index) rendering =====

    public function test_grid_menampilkan_sel_istirahat_utk_kelas_scope_dan_input_utk_kelas_lain(): void
    {
        $jam = $this->buatIstirahatSebagianKelas();

        $res = $this->actingAs($this->admin)->get(route('jadwal.index', ['hari' => 1]))->assertOk();

        // Badge "1 kelas" muncul (indikator cakupan sebagian), bukan banner colspan penuh.
        $res->assertSee('1 kelas');
        $res->assertSee('data-kelas="' . $this->kelas8->uuid . '"', false);
        // Kelas8 tetap dapat input teks (bisa diisi pelajaran), krn di luar cakupan istirahat —
        // cek elemen <tr> yg BENAR2 dipakaikan class ini, bukan cuma definisi CSS-nya di <style>
        // (yg pasti selalu ada di halaman terlepas dari ada/tidaknya baris istirahat global).
        $res->assertDontSee('<tr class="istirahat-row">', false);
    }

    public function test_grid_tetap_banner_penuh_saat_scope_kosong(): void
    {
        JamPelajaran::create(['hari' => 1, 'jam_mulai' => '09:00', 'jam_selesai' => '09:20', 'jenis' => 'istirahat', 'label' => 'Istirahat', 'urutan' => 1]);

        $res = $this->actingAs($this->admin)->get(route('jadwal.index', ['hari' => 1]))->assertOk();

        $res->assertSee('istirahat-row', false);
    }

    // ===== generate() per-kelas slot filtering =====

    public function test_generate_mengisi_kelas_non_scope_dan_melewati_kelas_yang_istirahat(): void
    {
        $jamIstirahat = $this->buatIstirahatSebagianKelas(); // hanya kelas7
        // Satu jam pelajaran biasa jam ke-1 supaya total slot > 0 & tidak dianggap kosong.
        JamPelajaran::create(['hari' => 1, 'jam_ke' => 1, 'jam_mulai' => '07:00', 'jam_selesai' => '07:40', 'jenis' => 'pelajaran', 'urutan' => 2]);

        $guru = Guru::create(['nama' => 'Guru Matematika', 'nik' => '1', 'jk' => 'L']);
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'urutan' => 1, 'jp' => 1]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $this->kelas8->uuid]);

        $this->actingAs($this->admin)->post(route('jadwal.generate'), ['mode' => 'timpa'])->assertRedirect();

        // Kelas8 (bukan Ngajar utk kelas7) bisa mendapat slot di jam istirahat itu krn tidak
        // termasuk cakupannya — sedangkan kelas7 tidak pernah diisi krn tidak ada Ngajar utknya.
        $this->assertDatabaseHas('jadwals', [
            'id_kelas' => $this->kelas8->uuid, 'id_jam' => $jamIstirahat->uuid, 'id_pelajaran' => $pelajaran->uuid,
        ]);
        $this->assertDatabaseMissing('jadwals', ['id_kelas' => $this->kelas7->uuid, 'id_jam' => $jamIstirahat->uuid]);
    }

    public function test_generate_tidak_pernah_mengisi_kelas_yang_termasuk_scope_istirahat(): void
    {
        $jamIstirahat = $this->buatIstirahatSebagianKelas(); // hanya kelas7
        JamPelajaran::create(['hari' => 1, 'jam_ke' => 1, 'jam_mulai' => '07:00', 'jam_selesai' => '07:40', 'jenis' => 'pelajaran', 'urutan' => 2]);

        $guru = Guru::create(['nama' => 'Guru Matematika', 'nik' => '2', 'jk' => 'L']);
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'urutan' => 1, 'jp' => 2]);
        // Ngajar utk KELAS7 (yg sedang istirahat pada jam tsb) dgn JP tinggi supaya coba dipaksa masuk semua slot.
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $this->kelas7->uuid]);

        $this->actingAs($this->admin)->post(route('jadwal.generate'), ['mode' => 'timpa'])->assertRedirect();

        $this->assertDatabaseMissing('jadwals', ['id_kelas' => $this->kelas7->uuid, 'id_jam' => $jamIstirahat->uuid]);
    }

    // ===== Read-only views: per kelas & per guru =====

    public function test_view_per_kelas_kelas_scope_tetap_tampil_istirahat(): void
    {
        $this->buatIstirahatSebagianKelas();

        $this->actingAs($this->admin)
            ->get(route('jadwal.kelas', ['kelas' => $this->kelas7->uuid]))
            ->assertOk()
            ->assertSee('Istirahat');
    }

    public function test_view_per_kelas_di_luar_scope_tidak_tampil_istirahat(): void
    {
        $jam = $this->buatIstirahatSebagianKelas(); // hanya kelas7
        $pelajaran = Pelajaran::create(['nama' => 'Bahasa Indonesia', 'kode' => 'BIN', 'urutan' => 1, 'jp' => 2]);
        $guru = Guru::create(['nama' => 'Guru Bindo', 'nik' => '3', 'jk' => 'P']);
        Jadwal::create([
            'id_kelas' => $this->kelas8->uuid, 'hari' => 1, 'id_jam' => $jam->uuid,
            'jam_mulai' => '09:00', 'jam_selesai' => '09:20',
            'id_pelajaran' => $pelajaran->uuid, 'id_guru' => $guru->uuid,
        ]);

        $res = $this->actingAs($this->admin)
            ->get(route('jadwal.kelas', ['kelas' => $this->kelas8->uuid]))
            ->assertOk();

        // Kelas8 tidak termasuk cakupan istirahat — harus tampil sbg pelajaran (kode mapel), bukan "Istirahat".
        $res->assertSee('BIN');
        $res->assertDontSee('Istirahat');
    }

    public function test_view_per_guru_scope_sebagian_tidak_jadi_banner_penuh(): void
    {
        $jam = $this->buatIstirahatSebagianKelas(); // hanya kelas7
        $pelajaran = Pelajaran::create(['nama' => 'Bahasa Indonesia', 'kode' => 'BIN', 'urutan' => 1, 'jp' => 2]);
        $guru = Guru::create(['nama' => 'Guru Bindo', 'nik' => '4', 'jk' => 'P']);
        Jadwal::create([
            'id_kelas' => $this->kelas8->uuid, 'hari' => 1, 'id_jam' => $jam->uuid,
            'jam_mulai' => '09:00', 'jam_selesai' => '09:20',
            'id_pelajaran' => $pelajaran->uuid, 'id_guru' => $guru->uuid,
        ]);

        $res = $this->actingAs($this->admin)
            ->get(route('jadwal.guru', ['guru' => $guru->uuid]))
            ->assertOk();

        // Guru ini mengajar kelas8 (di luar cakupan istirahat) pada jam tsb — harus tampil BIN,
        // bukan banner istirahat penuh yg dulu selalu menutupi baris ini apa pun isinya.
        $res->assertSee('BIN');
    }
}
