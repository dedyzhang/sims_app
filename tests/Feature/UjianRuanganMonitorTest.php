<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianJadwal;
use App\Models\UjianKelas;
use App\Models\UjianPaket;
use App\Models\UjianRuangan;
use App\Models\UjianSesi;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 6: halaman "1 halaman" pengawas ruangan — reset-kunci, daftar hadir,
 * berita acara. Otorisasi lewat UjianRuanganPolicy::awasi(): GURU MANA PUN
 * boleh masuk (tak perlu penugasan tersimpan), ASAL ruangan ini punya ujian
 * dijadwalkan (UjianJadwal) pada HARI itu — SENGAJA terpisah dari
 * UjianPolicy::manage() (pengelola per-mapel) yg dipakai halaman monitor
 * ujian LAMA — bukti isolasi diuji eksplisit di
 * test_guru_tanpa_relasi_mapel_tetap_ditolak_di_monitor_ujian_lama.
 */
class UjianRuanganMonitorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guruBebas; // guru biasa, TAK punya relasi mapel apapun ke ujian ini — tetap boleh masuk monitor ruangan
    private Ujian $ujian;
    private UjianPaket $paket;
    private UjianRuangan $ruangan;
    private UjianSesi $sesi;
    private UjianAttempt $attempt;
    private User $siswaUser;
    private Siswa $siswaLuarRoster;
    private UjianAttempt $attemptLuarRoster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['username' => 'admin_ruangan_monitor', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);

        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $kelasLain = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);

        $guruMapelUser = User::create(['username' => 'guru_mapel_ruangan', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guruMapel = Guru::create(['id_login' => $guruMapelUser->uuid, 'nama' => 'Guru Mapel', 'nik' => '9111111111', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $guruMapel->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $kelas->uuid]);
        Ngajar::create(['id_guru' => $guruMapel->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $kelasLain->uuid]);

        $this->guruBebas = User::create(['username' => 'guru_bebas_ruangan', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $this->guruBebas->uuid, 'nama' => 'Guru Bebas', 'nik' => '9222222222', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->paket = UjianPaket::create([
            'nama' => 'PAS', 'jenis' => 'pas', 'created_by' => $this->admin->uuid,
            'tanggal_mulai' => now()->subDay()->toDateString(), 'tanggal_selesai' => now()->addDay()->toDateString(),
        ]);
        $this->ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid, 'created_by' => $guruMapelUser->uuid, 'id_ujian_paket' => $this->paket->uuid,
            'judul' => 'PAS Matematika', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60, 'status' => 'published',
        ]);
        $soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '?', 'poin' => 10, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2]);
        UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $kelas->uuid, 'token_masuk' => 'RUANGTOKEN']);
        UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $kelasLain->uuid, 'token_masuk' => 'RUANGTOKEN2']);

        $this->sesi = UjianSesi::create([
            'id_ujian_paket' => $this->paket->uuid, 'tanggal' => now()->toDateString(),
            'jam_mulai' => '00:00', 'jam_selesai' => '23:59',
        ]);
        UjianJadwal::create([
            'id_ujian_paket' => $this->paket->uuid, 'id_ujian' => $this->ujian->uuid, 'id_sesi' => $this->sesi->uuid,
            'tanggal' => now()->toDateString(), 'jam_mulai' => '00:00', 'jam_selesai' => '23:59',
        ]);

        $this->ruangan = UjianRuangan::create(['id_ujian_paket' => $this->paket->uuid, 'nama' => 'Ruang 1']);

        $this->siswaUser = User::create(['username' => 'siswa_ruangan_monitor', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $siswa = Siswa::create(['id_login' => $this->siswaUser->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa Ruangan Monitor', 'nis' => '7301', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $this->ruangan->peserta()->create(['id_siswa' => $siswa->uuid]);

        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'RUANGTOKEN'])->assertRedirect();
        $this->attempt = UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->firstOrFail();

        // Siswa lain, kelas LAIN, ujian & token SAMA — TAPI tidak dimasukkan ke roster ruangan ini.
        $siswaLuarUser = User::create(['username' => 'siswa_luar_roster', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $this->siswaLuarRoster = Siswa::create(['id_login' => $siswaLuarUser->uuid, 'id_kelas' => $kelasLain->uuid, 'nama' => 'Siswa Luar Roster', 'nis' => '7401', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $this->actingAs($siswaLuarUser)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'RUANGTOKEN2'])->assertRedirect();
        $this->attemptLuarRoster = UjianAttempt::where('id_siswa', $siswaLuarUser->uuid)->firstOrFail();
    }

    public function test_guru_bebas_bisa_buka_kunci_siswa_di_roster_ruangan_asal_ada_jadwal_hari_ini(): void
    {
        $this->attempt->update(['dikunci' => true]);

        $this->actingAs($this->guruBebas)
            ->post(route('ujian.ruangan.bukaKunci', [$this->ruangan, $this->attempt]))
            ->assertRedirect();

        $this->attempt->refresh();
        $this->assertFalse($this->attempt->dikunci);
        $this->assertDatabaseHas('ujian_pelanggaran', ['id_attempt' => $this->attempt->uuid, 'tipe' => 'reset_oleh_pengawas']);
    }

    /** Bukti isolasi otorisasi: guru bebas (tak mengajar mapel ini sama sekali) TETAP ditolak di halaman monitor ujian LAMA yg berbasis `manage()`, walau boleh masuk monitor ruangan. */
    public function test_guru_tanpa_relasi_mapel_tetap_ditolak_di_monitor_ujian_lama(): void
    {
        $this->actingAs($this->guruBebas)->get(route('ujian.monitor.index', $this->ujian))->assertForbidden();
        $this->actingAs($this->guruBebas)->post(route('ujian.monitor.unlock', [$this->ujian, $this->attempt]))->assertForbidden();
    }

    public function test_guru_ditolak_di_semua_aksi_ruangan_kalau_belum_ada_jadwal_hari_ini(): void
    {
        $paketLain = UjianPaket::create(['nama' => 'Paket Tanpa Jadwal', 'jenis' => 'pas', 'created_by' => $this->admin->uuid]);
        $ruanganTanpaJadwal = UjianRuangan::create(['id_ujian_paket' => $paketLain->uuid, 'nama' => 'Ruang Kosong']);

        $this->actingAs($this->guruBebas)->get(route('ujian.ruangan.monitor', $ruanganTanpaJadwal))->assertForbidden();
        $this->actingAs($this->guruBebas)->getJson(route('ujian.ruangan.poll', $ruanganTanpaJadwal))->assertForbidden();
        // $this->sesi dipakai cuma sbg model binding valid (bukan milik ruangan ini) — authorize('awasi')
        // gagal LEBIH DULU krn ruanganTanpaJadwal memang tak py jadwal sama sekali hari ini.
        $this->actingAs($this->guruBebas)->post(route('ujian.ruangan.sesi.simpan', [$ruanganTanpaJadwal, $this->sesi]), ['id_ujian' => [], 'hadir' => []])->assertForbidden();
    }

    public function test_buka_kunci_siswa_di_luar_roster_ruangan_ditolak(): void
    {
        $this->attemptLuarRoster->update(['dikunci' => true]);

        $this->actingAs($this->guruBebas)
            ->post(route('ujian.ruangan.bukaKunci', [$this->ruangan, $this->attemptLuarRoster]))
            ->assertNotFound();

        $this->assertTrue($this->attemptLuarRoster->fresh()->dikunci, 'Attempt siswa di luar roster tidak boleh ikut terbuka kuncinya.');
    }

    public function test_poll_mengembalikan_status_attempt_peserta_roster(): void
    {
        $res = $this->actingAs($this->guruBebas)->getJson(route('ujian.ruangan.poll', $this->ruangan));
        $res->assertOk();
        $res->assertJsonPath('peserta.0.nama', 'Siswa Ruangan Monitor');
        $res->assertJsonPath('peserta.0.status', 'in_progress');
        $res->assertJsonPath('peserta.0.attempt_uuid', $this->attempt->uuid);
    }

    public function test_guru_bebas_bisa_simpan_daftar_hadir(): void
    {
        $siswa = Siswa::where('id_login', $this->siswaUser->uuid)->firstOrFail();

        $this->actingAs($this->guruBebas)->post(route('ujian.ruangan.sesi.simpan', [$this->ruangan, $this->sesi]), [
            'id_ujian' => [$this->ujian->uuid],
            'hadir' => [
                ['id_siswa' => $siswa->uuid, 'status' => 'hadir', 'keterangan' => null],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('ujian_daftar_hadir', [
            'id_ruangan' => $this->ruangan->uuid, 'id_siswa' => $siswa->uuid, 'id_sesi' => $this->sesi->uuid, 'status' => 'hadir',
        ]);
    }

    public function test_hadir_utk_siswa_di_luar_roster_diabaikan(): void
    {
        $this->actingAs($this->guruBebas)->post(route('ujian.ruangan.sesi.simpan', [$this->ruangan, $this->sesi]), [
            'id_ujian' => [$this->ujian->uuid],
            'hadir' => [
                ['id_siswa' => $this->siswaLuarRoster->uuid, 'status' => 'hadir', 'keterangan' => null],
            ],
        ])->assertRedirect();

        $this->assertDatabaseMissing('ujian_daftar_hadir', ['id_siswa' => $this->siswaLuarRoster->uuid]);
    }

    public function test_guru_bebas_bisa_simpan_berita_acara(): void
    {
        $this->actingAs($this->guruBebas)->post(route('ujian.ruangan.sesi.simpan', [$this->ruangan, $this->sesi]), [
            'id_ujian' => [$this->ujian->uuid],
            'jam_mulai_aktual' => '08:05',
            'jam_selesai_aktual' => '10:00',
            'jumlah_hadir' => 1,
            'jumlah_tidak_hadir' => 0,
            'catatan_kejadian' => 'Tidak ada kejadian khusus.',
            'hadir' => [],
        ])->assertRedirect();

        $ba = \App\Models\UjianBeritaAcara::where('id_ruangan', $this->ruangan->uuid)->where('id_sesi', $this->sesi->uuid)->firstOrFail();
        $this->assertSame(1, $ba->jumlah_hadir);
        $this->assertSame('Tidak ada kejadian khusus.', $ba->catatan_kejadian);
        $this->assertTrue($ba->ujianList->contains('uuid', $this->ujian->uuid), 'Mapel yg dicentang harus tercatat di pivot ujianList.');
    }

    /** Multi-mapel dicentang sekaligus (checkbox) -> SATU dokumen Berita Acara gabungan, bukan banyak dokumen terpisah. */
    public function test_guru_centang_multi_mapel_tersimpan_sbg_satu_berita_acara_gabungan(): void
    {
        $pelajaranKedua = Pelajaran::create(['nama' => 'IPA', 'kkm' => 75]);
        $ujianKedua = Ujian::create([
            'id_pelajaran' => $pelajaranKedua->uuid, 'created_by' => $this->admin->uuid, 'id_ujian_paket' => $this->paket->uuid,
            'judul' => 'PAS IPA', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60, 'status' => 'published',
        ]);
        UjianJadwal::create([
            'id_ujian_paket' => $this->paket->uuid, 'id_ujian' => $ujianKedua->uuid, 'id_sesi' => $this->sesi->uuid,
            'tanggal' => now()->toDateString(), 'jam_mulai' => '00:00', 'jam_selesai' => '23:59',
        ]);

        $this->actingAs($this->guruBebas)->post(route('ujian.ruangan.sesi.simpan', [$this->ruangan, $this->sesi]), [
            'id_ujian' => [$this->ujian->uuid, $ujianKedua->uuid], 'catatan_kejadian' => 'Sesi gabungan.', 'hadir' => [],
        ])->assertRedirect();

        $ba = \App\Models\UjianBeritaAcara::where('id_ruangan', $this->ruangan->uuid)->where('id_sesi', $this->sesi->uuid)->firstOrFail();
        $this->assertCount(1, \App\Models\UjianBeritaAcara::where('id_ruangan', $this->ruangan->uuid)->where('id_sesi', $this->sesi->uuid)->get(), 'Harus satu dokumen gabungan, bukan dua terpisah.');
        $this->assertSame(2, $ba->ujianList()->count());
        $this->assertTrue($ba->ujianList->contains('uuid', $this->ujian->uuid));
        $this->assertTrue($ba->ujianList->contains('uuid', $ujianKedua->uuid));
    }

    /** Bug report FL: berita acara berdasarkan SESI, bukan cuma "pilih guru scan" — auto-fill pengawas dari scan diuji terpisah di UjianRuanganScanTest. */
    public function test_guru_bisa_pilih_pengawas_manual_di_form_berita_acara(): void
    {
        $guruLain = Guru::create(['id_login' => User::create(['username' => 'guru_pengawas_manual', 'password' => Hash::make('rahasia123'), 'access' => 'guru'])->uuid, 'nama' => 'Guru Pengawas Manual', 'nik' => '9777777777', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($this->guruBebas)->post(route('ujian.ruangan.sesi.simpan', [$this->ruangan, $this->sesi]), [
            'id_ujian' => [$this->ujian->uuid],
            'id_guru_pengawas' => $guruLain->uuid,
            'catatan_kejadian' => 'Diisi manual.',
            'hadir' => [],
        ])->assertRedirect();

        $this->assertDatabaseHas('ujian_berita_acara', [
            'id_ruangan' => $this->ruangan->uuid, 'id_sesi' => $this->sesi->uuid, 'id_guru_pengawas' => $guruLain->uuid,
        ]);
    }

    public function test_simpan_berita_acara_sesi_yang_tak_terjadwal_hari_ini_ditolak(): void
    {
        $ujianLain = Ujian::create([
            'id_pelajaran' => Pelajaran::create(['nama' => 'IPS', 'kkm' => 75])->uuid, 'created_by' => $this->admin->uuid,
            'judul' => 'Ujian Tak Terjadwal', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60,
        ]);

        $this->actingAs($this->guruBebas)->post(route('ujian.ruangan.sesi.simpan', [$this->ruangan, $this->sesi]), [
            'id_ujian' => [$ujianLain->uuid], 'catatan_kejadian' => 'Coba injeksi mapel asing.', 'hadir' => [],
        ])->assertSessionHasErrors('id_ujian.0');

        $this->assertDatabaseMissing('ujian_berita_acara_ujian', ['id_ujian' => $ujianLain->uuid]);
    }

    public function test_cetak_pdf_berita_acara_render_tanpa_error(): void
    {
        $this->actingAs($this->guruBebas)->post(route('ujian.ruangan.sesi.simpan', [$this->ruangan, $this->sesi]), [
            'id_ujian' => [$this->ujian->uuid], 'jumlah_hadir' => 1, 'jumlah_tidak_hadir' => 0,
            'catatan_kejadian' => 'Aman terkendali.', 'hadir' => [],
        ]);
        $ba = \App\Models\UjianBeritaAcara::where('id_ruangan', $this->ruangan->uuid)->where('id_sesi', $this->sesi->uuid)->firstOrFail();

        $res = $this->actingAs($this->guruBebas)->get(route('ujian.ruangan.beritaAcara.cetak', [$this->ruangan, $ba]));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
    }

    public function test_cetak_pdf_daftar_hadir_render_tanpa_error(): void
    {
        $res = $this->actingAs($this->guruBebas)->get(route('ujian.ruangan.sesi.hadir.cetak', [$this->ruangan, $this->sesi]));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
    }

    public function test_simpan_berita_acara_dua_kali_di_hari_sama_memperbarui_bukan_dobel(): void
    {
        $this->actingAs($this->guruBebas)->post(route('ujian.ruangan.sesi.simpan', [$this->ruangan, $this->sesi]), ['id_ujian' => [$this->ujian->uuid], 'catatan_kejadian' => 'Draf awal.', 'hadir' => []]);
        $this->actingAs($this->guruBebas)->post(route('ujian.ruangan.sesi.simpan', [$this->ruangan, $this->sesi]), ['id_ujian' => [$this->ujian->uuid], 'catatan_kejadian' => 'Draf final.', 'hadir' => []]);

        $this->assertSame(1, \App\Models\UjianBeritaAcara::where('id_ruangan', $this->ruangan->uuid)->where('id_sesi', $this->sesi->uuid)->count());
        $this->assertDatabaseHas('ujian_berita_acara', ['id_ruangan' => $this->ruangan->uuid, 'id_sesi' => $this->sesi->uuid, 'catatan_kejadian' => 'Draf final.']);
    }

    public function test_halaman_monitor_render_tanpa_error_utk_guru_bebas(): void
    {
        $this->actingAs($this->guruBebas)->get(route('ujian.ruangan.monitor', $this->ruangan))
            ->assertOk()
            ->assertSee($this->ruangan->nama)
            ->assertSee('Siswa Ruangan Monitor');
    }

    /** Bug report FL: pengawas harus bisa lihat token ujian hari ini tanpa minta guru mapel/admin — discope ke kelas peserta RUANGAN INI saja (RUANGTOKEN2 milik kelasLain yg TAK ada di roster ruangan ini, tidak boleh ikut tampil). */
    public function test_halaman_monitor_menampilkan_token_ujian_hari_ini_discope_ke_roster_ruangan(): void
    {
        $this->actingAs($this->guruBebas)->get(route('ujian.ruangan.monitor', $this->ruangan))
            ->assertOk()
            ->assertSee('Token Ujian Hari Ini')
            ->assertSee('RUANGTOKEN')
            ->assertDontSee('RUANGTOKEN2');
    }

    public function test_daftar_ruangan_saya_tampilkan_tombol_scan_walau_cuma_satu_ruangan(): void
    {
        // TIDAK auto-redirect walau cuma 1 ruangan — guru harus tetap lihat & tap sendiri
        // tombol "Scan & Masuk"-nya (pemicu catatPengawasSesiAktif()), bukan dilompati
        // diam-diam ke monitor tanpa pernah menyentuh langkah scan-nya.
        $this->actingAs($this->guruBebas)->get(route('ujian.ruangan.saya'))
            ->assertOk()
            ->assertSee($this->ruangan->nama)
            ->assertSee(route('ujian.ruangan.scan', $this->ruangan), false);
    }

    public function test_daftar_ruangan_saya_kosong_kalau_tak_ada_ruangan_berjadwal_hari_ini(): void
    {
        // Guru baru, tanpa relasi apa pun — daftar ruangan bergantung pada jadwal HARI INI
        // secara sistem-lebar (bukan per-guru lagi), jadi hapus jadwal fixture dulu.
        UjianJadwal::query()->delete();

        $this->actingAs($this->guruBebas)->get(route('ujian.ruangan.saya'))
            ->assertOk()
            ->assertViewHas('ruanganList', fn ($list) => $list->isEmpty());
    }

    /**
     * Bug report FL: satu ruangan bisa berisi siswa lintas kelas/tingkat yg diuji mata
     * pelajaran BERBEDA hari yg sama (mis. kelas 7A ujian Matematika, kelas 7B ujian
     * Bahasa Indonesia, keduanya "hari ini") — poll() harus menampilkan baris TERPISAH
     * per (siswa, mapel) dgn label mapel yg benar, supaya pengawas bisa reset-kunci
     * tepat sasaran, dan filter `?mapel=` harus mempersempit sesuai pilihan.
     */
    public function test_roster_lintas_mapel_tampil_terpisah_dgn_label_mapel_yang_benar(): void
    {
        $pelajaranLain = Pelajaran::create(['nama' => 'Bahasa Indonesia', 'kkm' => 75]);
        $kelasLain2 = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);
        $ujianLain = Ujian::create([
            'id_pelajaran' => $pelajaranLain->uuid, 'created_by' => $this->admin->uuid, 'id_ujian_paket' => $this->paket->uuid,
            'judul' => 'PAS Bahasa Indonesia', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60, 'status' => 'published',
        ]);
        $soalLain = UjianSoal::create(['id_ujian' => $ujianLain->uuid, 'tipe' => 'mcq', 'teks_soal' => '?', 'poin' => 10, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soalLain->uuid, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soalLain->uuid, 'teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2]);
        UjianKelas::create(['id_ujian' => $ujianLain->uuid, 'id_kelas' => $kelasLain2->uuid, 'token_masuk' => 'BINDOTOKEN']);
        UjianJadwal::create([
            'id_ujian_paket' => $this->paket->uuid, 'id_ujian' => $ujianLain->uuid,
            'tanggal' => now()->toDateString(), 'jam_mulai' => '00:00', 'jam_selesai' => '23:59',
        ]);

        $siswaLain2User = User::create(['username' => 'siswa_bindo_ruangan', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $siswaLain2 = Siswa::create(['id_login' => $siswaLain2User->uuid, 'id_kelas' => $kelasLain2->uuid, 'nama' => 'Siswa Bindo', 'nis' => '8101', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $this->ruangan->peserta()->create(['id_siswa' => $siswaLain2->uuid]);
        $this->actingAs($siswaLain2User)->post(route('ujian.siswa.start', $ujianLain), ['token' => 'BINDOTOKEN'])->assertRedirect();

        $res = $this->actingAs($this->guruBebas)->getJson(route('ujian.ruangan.poll', $this->ruangan));
        $res->assertOk();
        $peserta = collect($res->json('peserta'));

        $this->assertCount(2, $peserta, 'Dua siswa dari dua mapel berbeda harus tampil sbg dua baris terpisah.');
        $barisMatematika = $peserta->firstWhere('nama', 'Siswa Ruangan Monitor');
        $barisBindo = $peserta->firstWhere('nama', 'Siswa Bindo');
        $this->assertSame('Matematika', $barisMatematika['mapel']);
        $this->assertSame('Bahasa Indonesia', $barisBindo['mapel']);
        $this->assertSame('in_progress', $barisBindo['status']);

        $mapelOpsi = collect($res->json('mapelOpsi'))->pluck('label');
        $this->assertTrue($mapelOpsi->contains(fn ($l) => str_contains($l, 'Matematika')));
        $this->assertTrue($mapelOpsi->contains(fn ($l) => str_contains($l, 'Bahasa Indonesia')));

        // Filter ?mapel= mempersempit ke ujian itu saja.
        $resFilter = $this->actingAs($this->guruBebas)->getJson(route('ujian.ruangan.poll', $this->ruangan) . '?mapel=' . $ujianLain->uuid);
        $pesertaFilter = collect($resFilter->json('peserta'));
        $this->assertCount(1, $pesertaFilter);
        $this->assertSame('Siswa Bindo', $pesertaFilter->first()['nama']);
    }

    /**
     * Kasus kritis: SATU siswa yg kelasnya diuji DUA mata pelajaran di hari yg sama (mis.
     * Matematika pagi + IPA siang) harus tampil sbg DUA baris dgn attempt masing2 — versi
     * lama poll() (keyBy id_siswa saja) menimpa jadi SATU baris & menyembunyikan salah satu
     * attempt sepenuhnya dari pengawas, bikin reset-kunci mapel yg "hilang" itu mustahil.
     */
    public function test_satu_siswa_dua_mapel_hari_sama_tampil_dua_baris_terpisah(): void
    {
        $pelajaranKedua = Pelajaran::create(['nama' => 'IPA', 'kkm' => 75]);
        $ujianKedua = Ujian::create([
            'id_pelajaran' => $pelajaranKedua->uuid, 'created_by' => $this->admin->uuid, 'id_ujian_paket' => $this->paket->uuid,
            'judul' => 'PAS IPA', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60, 'status' => 'published',
        ]);
        $soalKedua = UjianSoal::create(['id_ujian' => $ujianKedua->uuid, 'tipe' => 'mcq', 'teks_soal' => '?', 'poin' => 10, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soalKedua->uuid, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soalKedua->uuid, 'teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2]);
        // SAMA kelas dgn ujian Matematika ($this->attempt) — siswa Ruangan Monitor ini jg diuji IPA hari ini.
        $ukKedua = UjianKelas::create(['id_ujian' => $ujianKedua->uuid, 'id_kelas' => $this->attempt->ujianKelas->id_kelas, 'token_masuk' => 'IPATOKEN']);
        UjianJadwal::create([
            'id_ujian_paket' => $this->paket->uuid, 'id_ujian' => $ujianKedua->uuid,
            'tanggal' => now()->toDateString(), 'jam_mulai' => '00:00', 'jam_selesai' => '23:59',
        ]);
        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $ujianKedua), ['token' => 'IPATOKEN'])->assertRedirect();
        $attemptIpa = UjianAttempt::where('id_ujian_kelas', $ukKedua->uuid)->where('id_siswa', $this->siswaUser->uuid)->firstOrFail();
        $attemptIpa->update(['dikunci' => true]);

        $res = $this->actingAs($this->guruBebas)->getJson(route('ujian.ruangan.poll', $this->ruangan));
        $res->assertOk();
        $barisSiswaIni = collect($res->json('peserta'))->where('nama', 'Siswa Ruangan Monitor');

        $this->assertCount(2, $barisSiswaIni, 'Siswa yg sama dgn 2 mapel hari ini harus dapat 2 baris, bukan 1 baris yg menimpa salah satunya.');
        $mapelnya = $barisSiswaIni->pluck('mapel')->sort()->values()->all();
        $this->assertSame(['IPA', 'Matematika'], $mapelnya);

        $barisIpa = $barisSiswaIni->firstWhere('mapel', 'IPA');
        $this->assertTrue($barisIpa['dikunci'], 'Attempt IPA yg terkunci harus tetap terlihat & bisa direset, tak boleh tertimpa attempt Matematika.');
        $this->assertSame($attemptIpa->uuid, $barisIpa['attempt_uuid']);

        // Reset kunci mapel IPA lewat baris itu TIDAK boleh menyentuh attempt Matematika.
        $this->actingAs($this->guruBebas)
            ->post(route('ujian.ruangan.bukaKunci', [$this->ruangan, $attemptIpa]))
            ->assertRedirect();
        $this->assertFalse($attemptIpa->fresh()->dikunci);
        $this->assertFalse($this->attempt->fresh()->dikunci, 'Attempt Matematika tak pernah dikunci sejak awal — reset IPA tak boleh mengubahnya.');
    }

    /**
     * Bug report FL: tabel live jangan penuh baris "Belum Mulai" tiap kali roster dipecah
     * per mapel — HANYA siswa yg SUDAH mulai mengerjakan yg boleh tampil di sini (roster
     * lengkap ada di Daftar Hadir di bawahnya, itu tanggung jawab berbeda).
     */
    public function test_siswa_yang_belum_mulai_tidak_tampil_di_poll_tapi_muncul_begitu_mulai(): void
    {
        // Peserta baru di roster, kelasnya SAMA dgn Siswa Ruangan Monitor (jadi py ujian
        // Matematika relevan hari ini) — tapi belum pernah start() sama sekali.
        $siswaBelumMulaiUser = User::create(['username' => 'siswa_belum_mulai_ruangan', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $siswaBelumMulai = Siswa::create([
            'id_login' => $siswaBelumMulaiUser->uuid, 'id_kelas' => $this->attempt->ujianKelas->id_kelas,
            'nama' => 'Siswa Belum Mulai', 'nis' => '7302', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2],
        ]);
        $this->ruangan->peserta()->create(['id_siswa' => $siswaBelumMulai->uuid]);

        $res = $this->actingAs($this->guruBebas)->getJson(route('ujian.ruangan.poll', $this->ruangan));
        $res->assertOk();
        $nama = collect($res->json('peserta'))->pluck('nama');
        $this->assertTrue($nama->contains('Siswa Ruangan Monitor'), 'Yg sudah mulai (dari setUp) tetap harus tampil.');
        $this->assertFalse($nama->contains('Siswa Belum Mulai'), 'Yg belum mulai sama sekali TIDAK boleh tampil di tabel live.');

        // Begitu dia mulai, langsung muncul di poll berikutnya.
        $this->actingAs($siswaBelumMulaiUser)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'RUANGTOKEN'])->assertRedirect();

        $res2 = $this->actingAs($this->guruBebas)->getJson(route('ujian.ruangan.poll', $this->ruangan));
        $nama2 = collect($res2->json('peserta'))->pluck('nama');
        $this->assertTrue($nama2->contains('Siswa Belum Mulai'), 'Setelah start(), siswa itu harus langsung muncul di tabel live.');
    }
}
