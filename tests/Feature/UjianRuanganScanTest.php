<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\UjianJadwal;
use App\Models\UjianPaket;
use App\Models\UjianRuangan;
use App\Models\UjianSesi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Satu QR per ruangan (UjianRuanganScanController) — siswa scan utk catat
 * hadir sendiri, guru scan (SIAPA PUN, tak perlu penugasan) utk masuk monitor,
 * ASAL ruangan ini punya ujian dijadwalkan (UjianJadwal) pada HARI itu.
 */
class UjianRuanganScanTest extends TestCase
{
    use RefreshDatabase;

    private UjianPaket $paket;
    private UjianRuangan $ruangan;
    private Siswa $siswaRoster;
    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::create(['username' => 'admin_scan', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);

        $this->paket = UjianPaket::create(['nama' => 'PAS', 'jenis' => 'pas', 'created_by' => $admin->uuid]);
        $this->ruangan = UjianRuangan::create(['id_ujian_paket' => $this->paket->uuid, 'nama' => 'Ruang Scan 1']);

        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $siswaUser = User::create(['username' => 'siswa_scan', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $this->siswaRoster = Siswa::create(['id_login' => $siswaUser->uuid, 'id_kelas' => $this->kelas->uuid, 'nama' => 'Siswa Scan', 'nis' => '7501', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $this->ruangan->peserta()->create(['id_siswa' => $this->siswaRoster->uuid]);
    }

    private function actingAsSiswa(): User
    {
        return User::where('username', 'siswa_scan')->firstOrFail();
    }

    private function beriJadwalHariIni(): UjianSesi
    {
        $idUjian = \App\Models\Ujian::create([
            'id_pelajaran' => \App\Models\Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75])->uuid,
            'created_by' => User::first()->uuid, 'id_ujian_paket' => $this->paket->uuid,
            'judul' => 'PAS Matematika', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60,
        ])->uuid;
        $sesi = UjianSesi::create([
            'id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(),
            'jam_mulai' => '00:00', 'jam_selesai' => '23:59',
        ]);
        UjianJadwal::create([
            'id_ujian_paket' => $this->paket->uuid, 'id_ujian' => $idUjian, 'id_sesi' => $sesi->uuid,
            'tanggal' => now()->toDateString(), 'jam_mulai' => '00:00', 'jam_selesai' => '23:59',
        ]);

        return $sesi;
    }

    public function test_siswa_scan_mencatat_hadir_sendiri(): void
    {
        $this->beriJadwalHariIni();
        $siswaUser = $this->actingAsSiswa();

        $this->actingAs($siswaUser)->get(route('ujian.ruangan.scan', $this->ruangan))
            ->assertOk()
            ->assertSee('Kehadiran Tercatat')
            ->assertSee($this->ruangan->nama);

        $this->assertDatabaseHas('ujian_daftar_hadir', [
            'id_ruangan' => $this->ruangan->uuid, 'id_siswa' => $this->siswaRoster->uuid,
            'status' => 'hadir', 'dicatat_oleh' => $siswaUser->uuid,
        ]);
    }

    public function test_siswa_scan_dua_kali_tidak_membuat_baris_dobel(): void
    {
        $this->beriJadwalHariIni();
        $siswaUser = $this->actingAsSiswa();

        $this->actingAs($siswaUser)->get(route('ujian.ruangan.scan', $this->ruangan));
        $this->actingAs($siswaUser)->get(route('ujian.ruangan.scan', $this->ruangan))
            ->assertOk()
            ->assertSee('Sudah Tercatat Hadir');

        $this->assertSame(1, \App\Models\UjianDaftarHadir::where('id_ruangan', $this->ruangan->uuid)->where('id_siswa', $this->siswaRoster->uuid)->count());
    }

    public function test_siswa_di_luar_roster_ditolak_scan(): void
    {
        $this->beriJadwalHariIni();
        $kelasLain = Kelas::create(['tingkat' => 8, 'kelas' => 'B']);
        $siswaLuarUser = User::create(['username' => 'siswa_luar_scan', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $siswaLuarUser->uuid, 'id_kelas' => $kelasLain->uuid, 'nama' => 'Siswa Luar', 'nis' => '8501', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($siswaLuarUser)->get(route('ujian.ruangan.scan', $this->ruangan))->assertForbidden();
    }

    public function test_siswa_scan_ditolak_kalau_belum_ada_jadwal_hari_ini(): void
    {
        // Tak panggil beriJadwalHariIni() — ruangan ini belum py jadwal apa pun.
        $siswaUser = $this->actingAsSiswa();

        $this->actingAs($siswaUser)->get(route('ujian.ruangan.scan', $this->ruangan))->assertNotFound();
        $this->assertDatabaseMissing('ujian_daftar_hadir', ['id_ruangan' => $this->ruangan->uuid]);
    }

    /**
     * Fix bug nyata: sebelumnya checkinSiswa() resolve sesi murni via jam (sesiAktifSekarang()),
     * jadi kalau 2 sesi jamnya IDENTIK (persis skenario produksi: 2 mapel sama2 08:00-16:00 di
     * hari yg sama), siswa bisa salah tercatat ke sesi mapel yg BUKAN diikutinya. Sekarang
     * resolve via eligibility kelas (UjianKelas) — siswa harus masuk ke sesi mapel yg BENAR.
     */
    public function test_siswa_checkin_ke_sesi_yang_sesuai_kelasnya_walau_jam_dua_sesi_sama(): void
    {
        $admin = User::first() ?? User::create(['username' => 'admin_scan3', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);

        // Sesi A: mapel yg kelas siswa TIDAK ikuti.
        $sesiA = UjianSesi::create(['id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(), 'jam_mulai' => '00:00', 'jam_selesai' => '23:59', 'label' => 'A']);
        $ujianA = \App\Models\Ujian::create([
            'id_pelajaran' => \App\Models\Pelajaran::create(['nama' => 'Matematika Scan', 'kkm' => 75])->uuid,
            'created_by' => $admin->uuid, 'id_ujian_paket' => $this->paket->uuid,
            'judul' => 'PAS Matematika Scan', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60,
        ]);
        UjianJadwal::create(['id_ujian_paket' => $this->paket->uuid, 'id_ujian' => $ujianA->uuid, 'id_sesi' => $sesiA->uuid, 'tanggal' => now()->toDateString(), 'jam_mulai' => '00:00', 'jam_selesai' => '23:59']);
        $kelasLain = Kelas::create(['tingkat' => 9, 'kelas' => 'Z']);
        \App\Models\UjianKelas::create(['id_ujian' => $ujianA->uuid, 'id_kelas' => $kelasLain->uuid, 'token_masuk' => 'TOKENA']);

        // Sesi B: JAM SAMA PERSIS dgn sesi A, tapi mapel yg kelas siswa MEMANG ikuti.
        $sesiB = UjianSesi::create(['id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(), 'jam_mulai' => '00:00', 'jam_selesai' => '23:59', 'label' => 'B']);
        $ujianB = \App\Models\Ujian::create([
            'id_pelajaran' => \App\Models\Pelajaran::create(['nama' => 'IPA Scan', 'kkm' => 75])->uuid,
            'created_by' => $admin->uuid, 'id_ujian_paket' => $this->paket->uuid,
            'judul' => 'PAS IPA Scan', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60,
        ]);
        UjianJadwal::create(['id_ujian_paket' => $this->paket->uuid, 'id_ujian' => $ujianB->uuid, 'id_sesi' => $sesiB->uuid, 'tanggal' => now()->toDateString(), 'jam_mulai' => '00:00', 'jam_selesai' => '23:59']);
        \App\Models\UjianKelas::create(['id_ujian' => $ujianB->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => 'TOKENB']);

        $siswaUser = $this->actingAsSiswa();
        $this->actingAs($siswaUser)->get(route('ujian.ruangan.scan', $this->ruangan))->assertOk();

        $this->assertDatabaseHas('ujian_daftar_hadir', [
            'id_ruangan' => $this->ruangan->uuid, 'id_siswa' => $this->siswaRoster->uuid, 'id_sesi' => $sesiB->uuid,
        ]);
        $this->assertDatabaseMissing('ujian_daftar_hadir', [
            'id_ruangan' => $this->ruangan->uuid, 'id_siswa' => $this->siswaRoster->uuid, 'id_sesi' => $sesiA->uuid,
        ]);
    }

    public function test_guru_mana_pun_boleh_scan_masuk_monitor_kalau_ada_jadwal_hari_ini(): void
    {
        $this->beriJadwalHariIni();
        $guruUser = User::create(['username' => 'guru_scan_bebas', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Scan Bebas', 'nik' => '9555555555', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($guruUser)->get(route('ujian.ruangan.scan', $this->ruangan))
            ->assertRedirect(route('ujian.ruangan.monitor', $this->ruangan));
    }

    public function test_guru_ditolak_scan_kalau_belum_ada_jadwal_hari_ini(): void
    {
        $guruUser = User::create(['username' => 'guru_scan_tanpa_jadwal', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Tanpa Jadwal', 'nik' => '9666666666', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($guruUser)->get(route('ujian.ruangan.scan', $this->ruangan))->assertForbidden();
    }

    public function test_akun_bukan_siswa_bukan_guru_ditolak_scan(): void
    {
        $this->beriJadwalHariIni();
        $bendahara = User::create(['username' => 'bendahara_scan', 'password' => Hash::make('rahasia123'), 'access' => 'bendahara']);

        $this->actingAs($bendahara)->get(route('ujian.ruangan.scan', $this->ruangan))->assertForbidden();
    }

    /**
     * Bug report FL: berita acara butuh "form gurunya berdasarkan siapa yang scan, langsung
     * membaca Nama dan NIK-nya" — scan guru saat sesi lagi berjalan (jam_mulai..jam_selesai
     * mencakup sekarang) otomatis mencatat/menimpa id_guru_pengawas berita acara sesi itu.
     */
    public function test_guru_scan_otomatis_tercatat_sbg_pengawas_berita_acara_sesi_aktif(): void
    {
        $sesi = $this->beriJadwalHariIni();
        $guruUser = User::create(['username' => 'guru_scan_pengawas', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Scan Pengawas', 'nik' => '9888888888', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($guruUser)->get(route('ujian.ruangan.scan', $this->ruangan))->assertRedirect();

        $this->assertDatabaseHas('ujian_berita_acara', [
            'id_ruangan' => $this->ruangan->uuid, 'id_sesi' => $sesi->uuid,
            'tanggal' => now()->toDateString(), 'id_guru_pengawas' => $guru->uuid,
        ]);
    }

    /** Guru KEDUA yg scan (mis. pergantian pengawas) — catatan pindah ke yg TERAKHIR scan. */
    public function test_scan_guru_kedua_menimpa_pengawas_tercatat_dgn_yang_terbaru(): void
    {
        $sesi = $this->beriJadwalHariIni();
        $guru1User = User::create(['username' => 'guru_scan_1', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $guru1User->uuid, 'nama' => 'Guru Scan Satu', 'nik' => '9111100001', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $guru2User = User::create(['username' => 'guru_scan_2', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru2 = Guru::create(['id_login' => $guru2User->uuid, 'nama' => 'Guru Scan Dua', 'nik' => '9222200002', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($guru1User)->get(route('ujian.ruangan.scan', $this->ruangan));
        $this->actingAs($guru2User)->get(route('ujian.ruangan.scan', $this->ruangan));

        $this->assertSame(1, \App\Models\UjianBeritaAcara::where('id_ruangan', $this->ruangan->uuid)->where('id_sesi', $sesi->uuid)->count());
        $this->assertDatabaseHas('ujian_berita_acara', ['id_ruangan' => $this->ruangan->uuid, 'id_sesi' => $sesi->uuid, 'id_guru_pengawas' => $guru2->uuid]);
    }

    /** Scan di luar jam SEMUA sesi hari ini (jadwal ada tapi jamnya sudah lewat) — no-op, tak bikin berita_acara sembarangan. */
    public function test_scan_guru_di_luar_jam_sesi_tidak_mencatat_pengawas(): void
    {
        $ujianLewat = \App\Models\Ujian::create([
            'id_pelajaran' => \App\Models\Pelajaran::create(['nama' => 'Fisika', 'kkm' => 75])->uuid,
            'created_by' => User::first()?->uuid ?? User::create(['username' => 'admin_scan2', 'password' => Hash::make('rahasia123'), 'access' => 'admin'])->uuid,
            'id_ujian_paket' => $this->paket->uuid, 'judul' => 'PAS Fisika', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60,
        ]);
        $sesiLewat = UjianSesi::create([
            'id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(),
            'jam_mulai' => '00:00:01', 'jam_selesai' => '00:00:02',
        ]);
        UjianJadwal::create([
            'id_ujian_paket' => $this->paket->uuid, 'id_ujian' => $ujianLewat->uuid, 'id_sesi' => $sesiLewat->uuid,
            'tanggal' => now()->toDateString(), 'jam_mulai' => '00:00:01', 'jam_selesai' => '00:00:02',
        ]);
        $guruUser = User::create(['username' => 'guru_scan_diluar_jam', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Di Luar Jam', 'nik' => '9333300003', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($guruUser)->get(route('ujian.ruangan.scan', $this->ruangan))->assertRedirect();

        $this->assertDatabaseMissing('ujian_berita_acara', ['id_ruangan' => $this->ruangan->uuid]);
    }
}
