<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\UjianBeritaAcara;
use App\Models\UjianDaftarHadir;
use App\Models\UjianPaket;
use App\Models\UjianRuangan;
use App\Models\UjianSesi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bug report FL: sesi "yatim" (jadwalnya sudah dihapus admin) sempat muncul jadi baris
 * hantu "AD-HOC/Tanpa Jadwal" di Rekap — membingungkan, seolah ada sesi terjadwal yg BA-nya
 * belum diisi padahal jadwalnya memang sudah dihapus. UjianRekapController::sesiPunyaData()
 * menyaring sesi yatim yg BENAR-BENAR kosong (tak py jadwal/BA/daftar hadir sama sekali),
 * tapi TETAP mempertahankan sesi yatim yg SUDAH terlanjur py data historis (BA/daftar hadir).
 */
class UjianRekapTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private UjianPaket $paket;
    private UjianRuangan $ruangan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['username' => 'admin_rekap', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $this->paket = UjianPaket::create(['nama' => 'PAS Rekap', 'jenis' => 'pas', 'created_by' => $this->admin->uuid]);
        $this->ruangan = UjianRuangan::create(['id_ujian_paket' => $this->paket->uuid, 'nama' => 'Ruang Rekap 1']);
    }

    public function test_sesi_yatim_tanpa_data_apa_pun_tidak_tampil_di_rekap(): void
    {
        // Sesi tanpa jadwal (sudah dihapus), tanpa BA, tanpa daftar hadir — benar2 kosong.
        UjianSesi::create([
            'id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00', 'label' => 'Yatim Kosong',
        ]);

        $res = $this->actingAs($this->admin)->get(route('ujian.rekap.index', ['tanggal' => now()->toDateString()]));
        $res->assertOk();
        $res->assertDontSee('Yatim Kosong');
        $res->assertSee('Tidak ada sesi di ruangan ini pada tanggal terpilih.');
    }

    public function test_sesi_yatim_yang_sudah_py_berita_acara_tetap_tampil(): void
    {
        $sesi = UjianSesi::create([
            'id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ]);
        UjianBeritaAcara::create([
            'id_ruangan' => $this->ruangan->uuid, 'id_sesi' => $sesi->uuid,
            'tanggal' => now()->toDateString(), 'jam_mulai_aktual' => '08:00', 'jam_selesai_aktual' => '10:00',
            'jumlah_hadir' => 5, 'jumlah_tidak_hadir' => 0,
        ]);

        $res = $this->actingAs($this->admin)->get(route('ujian.rekap.index', ['tanggal' => now()->toDateString()]));
        $res->assertOk();
        $res->assertSee('AD-HOC');
        $res->assertSee('5');
    }

    public function test_sesi_yatim_yang_sudah_py_daftar_hadir_tetap_tampil(): void
    {
        // Sesi ini SENGAJA tanpa BA (cuma daftar hadir) — tipe='adhoc' krn jadwal kosong,
        // view tak render $sesi->label utk tipe adhoc, jadi assert lewat absennya empty-state
        // + munculnya baris "Tanpa Jadwal" (bukan collapse ke "Tidak ada sesi...").
        $sesi = UjianSesi::create([
            'id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $siswaUser = User::create(['username' => 'siswa_rekap_hadir', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $siswa = Siswa::create(['id_login' => $siswaUser->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa Rekap Hadir', 'nis' => '7701', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        UjianDaftarHadir::create([
            'id_ruangan' => $this->ruangan->uuid, 'id_sesi' => $sesi->uuid,
            'id_siswa' => $siswa->uuid,
            'tanggal' => now()->toDateString(), 'status' => 'hadir',
        ]);

        $res = $this->actingAs($this->admin)->get(route('ujian.rekap.index', ['tanggal' => now()->toDateString()]));
        $res->assertOk();
        $res->assertDontSee('Tidak ada sesi di ruangan ini pada tanggal terpilih.');
        $res->assertSee('Tanpa Jadwal');
    }

    public function test_sesi_terjadwal_normal_tetap_tampil_walau_belum_py_ba(): void
    {
        $ujian = \App\Models\Ujian::create([
            'id_pelajaran' => \App\Models\Pelajaran::create(['nama' => 'Matematika Rekap', 'kkm' => 75])->uuid,
            'created_by' => $this->admin->uuid, 'id_ujian_paket' => $this->paket->uuid,
            'judul' => 'PAS Matematika Rekap', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60,
        ]);
        $sesi = UjianSesi::create([
            'id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ]);
        \App\Models\UjianJadwal::create([
            'id_ujian_paket' => $this->paket->uuid, 'id_ujian' => $ujian->uuid, 'id_sesi' => $sesi->uuid,
            'tanggal' => now()->toDateString(), 'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ]);

        $res = $this->actingAs($this->admin)->get(route('ujian.rekap.index', ['tanggal' => now()->toDateString()]));
        $res->assertOk();
        $res->assertSee('Matematika Rekap');
        $res->assertSee('Belum ada data');
    }

    public function test_cetak_pdf_render_tanpa_error_dgn_sesi_yatim_kosong(): void
    {
        UjianSesi::create([
            'id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ]);

        $this->actingAs($this->admin)
            ->get(route('ujian.rekap.cetak', ['tanggal' => now()->toDateString()]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /** Command ujian:bersihkan-sesi-yatim — cuma hapus sesi yg BENAR-BENAR kosong (jadwal, BA, DAN daftar hadir semuanya absen), sisanya (BA/daftar hadir/jadwal ada) TIDAK disentuh. */
    public function test_command_bersihkan_sesi_yatim_hanya_hapus_yang_benar_benar_kosong(): void
    {
        $sesiKosong = UjianSesi::create([
            'id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ]);

        $sesiPyBa = UjianSesi::create([
            'id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(),
            'jam_mulai' => '11:00', 'jam_selesai' => '13:00',
        ]);
        UjianBeritaAcara::create([
            'id_ruangan' => $this->ruangan->uuid, 'id_sesi' => $sesiPyBa->uuid,
            'tanggal' => now()->toDateString(), 'jam_mulai_aktual' => '11:00', 'jam_selesai_aktual' => '13:00',
            'jumlah_hadir' => 3, 'jumlah_tidak_hadir' => 0,
        ]);

        $sesiPyHadir = UjianSesi::create([
            'id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(),
            'jam_mulai' => '14:00', 'jam_selesai' => '16:00',
        ]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $siswaUser = User::create(['username' => 'siswa_rekap_cmd', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $siswa = Siswa::create(['id_login' => $siswaUser->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa Rekap Cmd', 'nis' => '7702', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        UjianDaftarHadir::create([
            'id_ruangan' => $this->ruangan->uuid, 'id_sesi' => $sesiPyHadir->uuid,
            'id_siswa' => $siswa->uuid, 'tanggal' => now()->toDateString(), 'status' => 'hadir',
        ]);

        $ujian = \App\Models\Ujian::create([
            'id_pelajaran' => \App\Models\Pelajaran::create(['nama' => 'IPA Rekap Cmd', 'kkm' => 75])->uuid,
            'created_by' => $this->admin->uuid, 'id_ujian_paket' => $this->paket->uuid,
            'judul' => 'PAS IPA Rekap Cmd', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60,
        ]);
        $sesiTerjadwal = UjianSesi::create([
            'id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(),
            'jam_mulai' => '17:00', 'jam_selesai' => '19:00',
        ]);
        \App\Models\UjianJadwal::create([
            'id_ujian_paket' => $this->paket->uuid, 'id_ujian' => $ujian->uuid, 'id_sesi' => $sesiTerjadwal->uuid,
            'tanggal' => now()->toDateString(), 'jam_mulai' => '17:00', 'jam_selesai' => '19:00',
        ]);

        // --dry-run dulu: tak ada yg terhapus.
        $this->artisan('ujian:bersihkan-sesi-yatim --dry-run')->assertSuccessful();
        $this->assertSame(4, UjianSesi::count());

        // Jalankan sungguhan.
        $this->artisan('ujian:bersihkan-sesi-yatim')->assertSuccessful();

        $this->assertDatabaseMissing('ujian_sesi', ['uuid' => $sesiKosong->uuid]);
        $this->assertDatabaseHas('ujian_sesi', ['uuid' => $sesiPyBa->uuid]);
        $this->assertDatabaseHas('ujian_sesi', ['uuid' => $sesiPyHadir->uuid]);
        $this->assertDatabaseHas('ujian_sesi', ['uuid' => $sesiTerjadwal->uuid]);
        $this->assertSame(3, UjianSesi::count());
    }
}
