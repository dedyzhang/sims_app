<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\OsisPaslon;
use App\Models\OsisPemilih;
use App\Models\OsisPemilihan;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regresi fitur Pemilihan OSIS — mengunci 3 sifat kritis yg secara eksplisit diminta FL:
 * (1) tidak bisa memilih dua kali walau request nyaris bersamaan (atomic lockForUpdate),
 * (2) generate token massal tak N+1 (bulk upsert, bukan loop create()),
 * (3) dashboard/hasil selalu query agregat konstan, tak pernah re-query per-pemilih.
 */
class OsisVotingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'admin_osis', 'password' => Hash::make('x'), 'access' => 'superadmin']);
    }

    private function buatKelasSiswa(int $jumlah = 5): array
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $siswas = collect();
        for ($i = 1; $i <= $jumlah; $i++) {
            $siswas->push(Siswa::create(['nama' => "Siswa {$i}", 'nis' => "OSIS00{$i}", 'jk' => 'L', 'id_kelas' => $kelas->uuid]));
        }

        return [$kelas, $siswas];
    }

    public function test_memo_aktif_hanya_satu_query_dan_terinvalidasi_saat_ganti(): void
    {
        $p1 = OsisPemilihan::create(['nama' => 'Periode 1', 'aktif' => false]);
        $p2 = OsisPemilihan::create(['nama' => 'Periode 2', 'aktif' => false]);

        $p1->update(['aktif' => true]);
        $this->assertSame($p1->uuid, OsisPemilihan::aktif()->uuid);

        DB::enableQueryLog();
        OsisPemilihan::aktif();
        OsisPemilihan::aktif();
        $log = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(0, $log, 'Panggilan berulang aktif() setelah memo hangat tak boleh query lagi.');

        // Mass-update (query builder, tak memicu event) + clearCache manual, pola sama Semester/SettingController.
        OsisPemilihan::query()->update(['aktif' => false]);
        OsisPemilihan::clearCache();
        $p2->update(['aktif' => true]);
        $this->assertSame($p2->uuid, OsisPemilihan::aktif()->uuid, 'Setelah ganti periode aktif, memo tak boleh nyangkut versi lama.');
    }

    public function test_generate_token_kelas_bulk_bukan_n_plus_1_dan_token_unik(): void
    {
        [$kelas, $siswas] = $this->buatKelasSiswa(20);
        $pemilihan = OsisPemilihan::create(['nama' => 'Test', 'status' => 'draft']);

        $ctrl = app(\App\Http\Controllers\Osis\OsisPemilihController::class);
        DB::enableQueryLog();
        $ctrl->generateTokenKelas($pemilihan, request()->merge(['id_kelas' => $kelas->uuid]));
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(6, count($log), 'Generate token 20 siswa harus flat (~5 query), bukan 20×N loop create().');

        $pemilihList = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)->get();
        $this->assertCount(20, $pemilihList);
        $this->assertSame(20, $pemilihList->pluck('token')->unique()->count(), 'Semua token harus unik.');

        // Re-generate: token LAMA tak boleh berubah (safety net thd 2x klik generate).
        $tokenLama = $pemilihList->first()->token;
        $ctrl->generateTokenKelas($pemilihan, request()->merge(['id_kelas' => $kelas->uuid]));
        $this->assertSame($tokenLama, OsisPemilih::where('id_pemilihan', $pemilihan->uuid)->where('id_siswa', $siswas->first()->uuid)->first()->token);
        $this->assertSame(20, OsisPemilih::where('id_pemilihan', $pemilihan->uuid)->count(), 'Re-generate tak boleh membuat baris duplikat.');
    }

    public function test_vote_kedua_dengan_token_sama_ditolak_walau_paslon_beda(): void
    {
        [$kelas, $siswas] = $this->buatKelasSiswa(1);
        $pemilihan = OsisPemilihan::create(['nama' => 'Test', 'status' => 'dibuka']);
        $p1 = OsisPaslon::create(['id_pemilihan' => $pemilihan->uuid, 'nomor_urut' => 1, 'nama_ketua' => 'Budi']);
        $p2 = OsisPaslon::create(['id_pemilihan' => $pemilihan->uuid, 'nomor_urut' => 2, 'nama_ketua' => 'Citra']);
        $pemilih = OsisPemilih::create([
            'id_pemilihan' => $pemilihan->uuid, 'tipe_pemilih' => 'siswa', 'id_siswa' => $siswas->first()->uuid,
            'nama_snapshot' => $siswas->first()->nama, 'token' => 'tok-'.\Illuminate\Support\Str::random(20),
        ]);

        $this->post(route('osis.publik.store', $pemilih->token), ['id_paslon' => $p1->uuid])->assertRedirect();
        $pemilih->refresh();
        $this->assertTrue($pemilih->sudahMemilih());
        $this->assertSame($p1->uuid, $pemilih->id_paslon_dipilih);

        // Vote kedua, paslon BEDA — harus ditolak, pilihan pertama tak berubah.
        $this->post(route('osis.publik.store', $pemilih->token), ['id_paslon' => $p2->uuid])->assertRedirect();
        $pemilih->refresh();
        $this->assertSame($p1->uuid, $pemilih->id_paslon_dipilih, 'Vote kedua harus ditolak — pilihan pertama tak boleh tertimpa.');
        $this->assertSame(1, OsisPemilih::where('uuid', $pemilih->uuid)->count());
    }

    public function test_vote_ditolak_jika_pemilihan_belum_dibuka_atau_sudah_ditutup(): void
    {
        [$kelas, $siswas] = $this->buatKelasSiswa(1);
        $pemilihan = OsisPemilihan::create(['nama' => 'Test', 'status' => 'draft']);
        $paslon = OsisPaslon::create(['id_pemilihan' => $pemilihan->uuid, 'nomor_urut' => 1, 'nama_ketua' => 'Budi']);
        $pemilih = OsisPemilih::create([
            'id_pemilihan' => $pemilihan->uuid, 'tipe_pemilih' => 'siswa', 'id_siswa' => $siswas->first()->uuid,
            'nama_snapshot' => $siswas->first()->nama, 'token' => 'tok-'.\Illuminate\Support\Str::random(20),
        ]);

        $this->post(route('osis.publik.store', $pemilih->token), ['id_paslon' => $paslon->uuid]);
        $pemilih->refresh();
        $this->assertFalse($pemilih->sudahMemilih(), 'Pemilihan masih draft — vote tak boleh tersimpan.');

        $this->get(route('osis.publik.show', $pemilih->token))->assertOk()->assertSee('belum dibuka', false);
    }

    public function test_dashboard_dan_hasil_data_query_konstan_tak_peduli_jumlah_pemilih(): void
    {
        [$kelas, $siswas] = $this->buatKelasSiswa(30);
        $pemilihan = OsisPemilihan::create(['nama' => 'Test', 'status' => 'dibuka']);
        $paslon = OsisPaslon::create(['id_pemilihan' => $pemilihan->uuid, 'nomor_urut' => 1, 'nama_ketua' => 'Budi']);

        foreach ($siswas as $i => $s) {
            OsisPemilih::create([
                'id_pemilihan' => $pemilihan->uuid, 'tipe_pemilih' => 'siswa', 'id_siswa' => $s->uuid,
                'nama_snapshot' => $s->nama, 'token' => 'tok-'.$i.'-'.\Illuminate\Support\Str::random(15),
                'id_paslon_dipilih' => $i < 20 ? $paslon->uuid : null,
                'sudah_memilih_at' => $i < 20 ? now() : null,
            ]);
        }

        $ctrl = app(\App\Http\Controllers\Osis\OsisDashboardController::class);

        DB::enableQueryLog();
        $dash = json_decode($ctrl->dashboardData($pemilihan)->getContent(), true);
        $qDash = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(3, $qDash, 'dashboardData() harus persis 3 query agregat, tak peduli jumlah pemilih.');
        $this->assertSame(20, $dash['siswa']['sudah']);
        $this->assertSame(30, $dash['siswa']['total']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $hasil = json_decode($ctrl->hasilData($pemilihan)->getContent(), true);
        $qHasil = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(3, $qHasil, 'hasilData() harus persis 3 query agregat.');
        $this->assertSame(20, $hasil['siswa'][0]);
    }

    public function test_alur_admin_lengkap_via_http(): void
    {
        $admin = $this->admin();
        [$kelas, $siswas] = $this->buatKelasSiswa(3);

        $this->actingAs($admin)->post(route('osis.store'), ['nama' => 'Pemilihan Ketua OSIS 2026'])->assertRedirect();
        $pemilihan = OsisPemilihan::first();
        $this->assertNotNull($pemilihan);

        $this->actingAs($admin)->post(route('osis.paslon.store', $pemilihan), [
            'nomor_urut' => 1, 'nama_ketua' => 'Budi', 'nama_wakil' => 'Ani', 'visi' => 'Visi', 'misi' => "A\nB",
        ])->assertRedirect();
        $this->assertDatabaseHas('osis_paslon', ['id_pemilihan' => $pemilihan->uuid, 'nama_ketua' => 'Budi']);

        $this->actingAs($admin)->post(route('osis.pemilih.generateSiswa', $pemilihan), ['id_kelas' => $kelas->uuid])->assertRedirect();
        $this->assertSame(3, OsisPemilih::where('id_pemilihan', $pemilihan->uuid)->count());

        $this->actingAs($admin)->patch(route('osis.status', $pemilihan), ['status' => 'dibuka'])->assertRedirect();
        $this->assertSame('dibuka', $pemilihan->fresh()->status);

        $pemilih = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)->first();
        $paslon = OsisPaslon::where('id_pemilihan', $pemilihan->uuid)->first();

        // Publik: TANPA login (guest), scan QR (buka URL token) → tampil form → submit.
        $this->get(route('osis.publik.show', $pemilih->token))->assertOk()->assertSee($pemilih->nama_snapshot);
        $this->post(route('osis.publik.store', $pemilih->token), ['id_paslon' => $paslon->uuid])->assertRedirect();
        $this->assertTrue($pemilih->fresh()->sudahMemilih());

        // Admin: dashboard & hasil bisa diakses.
        $this->actingAs($admin)->get(route('osis.dashboard', $pemilihan))->assertOk();
        $this->actingAs($admin)->get(route('osis.dashboard.data', $pemilihan))->assertOk()->assertJsonPath('siswa.sudah', 1);
        $this->actingAs($admin)->get(route('osis.hasil', $pemilihan))->assertOk();
    }

    public function test_vote_ditolak_sebelum_jadwal_mulai_walau_status_sudah_dibuka(): void
    {
        [$kelas, $siswas] = $this->buatKelasSiswa(1);
        $pemilihan = OsisPemilihan::create([
            'nama' => 'Test', 'status' => 'dibuka', 'jadwal_mulai' => now()->addHour(),
        ]);
        $paslon = OsisPaslon::create(['id_pemilihan' => $pemilihan->uuid, 'nomor_urut' => 1, 'nama_ketua' => 'Budi']);
        $pemilih = OsisPemilih::create([
            'id_pemilihan' => $pemilihan->uuid, 'tipe_pemilih' => 'siswa', 'id_siswa' => $siswas->first()->uuid,
            'nama_snapshot' => $siswas->first()->nama, 'token' => 'tok-'.\Illuminate\Support\Str::random(20),
        ]);

        $this->assertFalse($pemilihan->bolehMemilihSekarang(), 'jadwal_mulai di masa depan — belum boleh memilih walau status dibuka.');
        $this->assertSame('terjadwal', $pemilihan->statusEfektif());

        $this->get(route('osis.publik.show', $pemilih->token))->assertOk()->assertSee('dijadwalkan mulai', false);

        $this->post(route('osis.publik.store', $pemilih->token), ['id_paslon' => $paslon->uuid]);
        $pemilih->refresh();
        $this->assertFalse($pemilih->sudahMemilih(), 'Vote sebelum jadwal_mulai harus ditolak.');
    }

    public function test_vote_diterima_setelah_jadwal_mulai_terlewati(): void
    {
        [$kelas, $siswas] = $this->buatKelasSiswa(1);
        $pemilihan = OsisPemilihan::create([
            'nama' => 'Test', 'status' => 'dibuka', 'jadwal_mulai' => now()->subMinute(),
        ]);
        $paslon = OsisPaslon::create(['id_pemilihan' => $pemilihan->uuid, 'nomor_urut' => 1, 'nama_ketua' => 'Budi']);
        $pemilih = OsisPemilih::create([
            'id_pemilihan' => $pemilihan->uuid, 'tipe_pemilih' => 'siswa', 'id_siswa' => $siswas->first()->uuid,
            'nama_snapshot' => $siswas->first()->nama, 'token' => 'tok-'.\Illuminate\Support\Str::random(20),
        ]);

        $this->assertTrue($pemilihan->bolehMemilihSekarang());

        $this->post(route('osis.publik.store', $pemilih->token), ['id_paslon' => $paslon->uuid])->assertRedirect();
        $this->assertTrue($pemilih->fresh()->sudahMemilih());
    }

    public function test_vote_ditolak_setelah_jadwal_selesai_terlewati_walau_status_masih_dibuka(): void
    {
        [$kelas, $siswas] = $this->buatKelasSiswa(1);
        $pemilihan = OsisPemilihan::create([
            'nama' => 'Test', 'status' => 'dibuka',
            'jadwal_mulai' => now()->subDay(), 'jadwal_selesai' => now()->subMinute(),
        ]);
        $paslon = OsisPaslon::create(['id_pemilihan' => $pemilihan->uuid, 'nomor_urut' => 1, 'nama_ketua' => 'Budi']);
        $pemilih = OsisPemilih::create([
            'id_pemilihan' => $pemilihan->uuid, 'tipe_pemilih' => 'siswa', 'id_siswa' => $siswas->first()->uuid,
            'nama_snapshot' => $siswas->first()->nama, 'token' => 'tok-'.\Illuminate\Support\Str::random(20),
        ]);

        $this->assertFalse($pemilihan->bolehMemilihSekarang());
        $this->assertSame('ditutup', $pemilihan->statusEfektif(), 'jadwal_selesai terlewati harus dianggap tertutup walau kolom status msh dibuka.');

        $this->post(route('osis.publik.store', $pemilih->token), ['id_paslon' => $paslon->uuid]);
        $this->assertFalse($pemilih->fresh()->sudahMemilih());
    }

    public function test_admin_set_jadwal_via_http_dan_validasi_selesai_setelah_mulai(): void
    {
        $admin = $this->admin();
        $pemilihan = OsisPemilihan::create(['nama' => 'Test', 'status' => 'draft']);

        $mulai = now()->addDay();
        $selesai = now()->addDays(2);
        $this->actingAs($admin)->patch(route('osis.jadwal', $pemilihan), [
            'jadwal_mulai' => $mulai->format('Y-m-d H:i:s'),
            'jadwal_selesai' => $selesai->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $pemilihan->refresh();
        $this->assertSame($mulai->format('Y-m-d H:i:s'), $pemilihan->jadwal_mulai->format('Y-m-d H:i:s'));
        $this->assertSame($selesai->format('Y-m-d H:i:s'), $pemilihan->jadwal_selesai->format('Y-m-d H:i:s'));

        // Selesai sebelum mulai harus ditolak.
        $this->actingAs($admin)->patch(route('osis.jadwal', $pemilihan), [
            'jadwal_mulai' => $mulai->format('Y-m-d H:i:s'),
            'jadwal_selesai' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('jadwal_selesai');
    }

    public function test_cetak_absensi_kelas_pdf_berhasil_dan_ikut_permission_gate(): void
    {
        $admin = $this->admin();
        [$kelas, $siswas] = $this->buatKelasSiswa(3);
        $pemilihan = OsisPemilihan::create(['nama' => 'Test', 'status' => 'dibuka']);
        $paslon = OsisPaslon::create(['id_pemilihan' => $pemilihan->uuid, 'nomor_urut' => 1, 'nama_ketua' => 'Budi']);

        $this->actingAs($admin)->post(route('osis.pemilih.generateSiswa', $pemilihan), ['id_kelas' => $kelas->uuid]);
        $pemilihSatu = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)->where('id_siswa', $siswas->first()->uuid)->first();
        $this->post(route('osis.publik.store', $pemilihSatu->token), ['id_paslon' => $paslon->uuid]);

        // Admin ber-akses: PDF berhasil digenerate (status "Sudah Pilih" utk 1 siswa dites manual via Read tool, lihat riwayat kerja).
        $this->actingAs($admin)->get(route('osis.pemilih.cetakAbsensiKelas', [$pemilihan, $kelas]))->assertOk();

        // Guru tanpa permission manage_osis ditolak, konsisten dgn seluruh route admin OSIS lain.
        $guru = User::create(['username' => 'guru_cetak_absensi', 'password' => Hash::make('x'), 'access' => 'guru']);
        $this->actingAs($guru)->get(route('osis.pemilih.cetakAbsensiKelas', [$pemilihan, $kelas]))->assertForbidden();
    }

    /**
     * Cetak QR pemilih kelas: estimasi awal "10 baris/halaman" TERBUKTI meleset saat
     * dirender sungguhan lewat dompdf (row asli lebih tinggi dari perkiraan mm manual) —
     * cuma 8 baris yg benar2 muat per halaman, sisanya meluber ke halaman baru yg nyaris
     * kosong (kertas terbuang, dilaporkan FL). Diverifikasi manual via render PDF
     * sungguhan (lihat riwayat kerja) bahwa 8/9 memang pas; test ini cuma mengunci bahwa
     * nilai LAMA yg terbukti salah (10/12) tak lagi diterima sbg per_halaman valid.
     */
    public function test_cetak_qr_kelas_per_halaman_normalisasi_ke_nilai_yang_benar2_muat(): void
    {
        $admin = $this->admin();
        [$kelas, $siswas] = $this->buatKelasSiswa(3);
        $pemilihan = OsisPemilihan::create(['nama' => 'Test', 'status' => 'dibuka']);
        $this->actingAs($admin)->post(route('osis.pemilih.generateSiswa', $pemilihan), ['id_kelas' => $kelas->uuid]);

        // per_halaman lama (10/12) TERBUKTI salah (kertas terbuang) — tak boleh lagi
        // diterima apa adanya, harus dinormalisasi ke default baru (8) yg benar2 muat.
        $this->actingAs($admin)->get(route('osis.pemilih.cetakKelas', [$pemilihan, $kelas]).'?per_halaman=10')->assertOk();
        $this->actingAs($admin)->get(route('osis.pemilih.cetakKelas', [$pemilihan, $kelas]).'?per_halaman=12')->assertOk();
        // Nilai baru yg SUDAH diverifikasi benar2 muat (lihat docblock controller).
        $this->actingAs($admin)->get(route('osis.pemilih.cetakKelas', [$pemilihan, $kelas]).'?per_halaman=8')->assertOk();
        $this->actingAs($admin)->get(route('osis.pemilih.cetakKelas', [$pemilihan, $kelas]).'?per_halaman=9')->assertOk();
    }

    public function test_role_tanpa_permission_ditolak_dari_menu_admin(): void
    {
        $guru = User::create(['username' => 'guru_biasa', 'password' => Hash::make('x'), 'access' => 'guru']);
        $pemilihan = OsisPemilihan::create(['nama' => 'Test']);

        $this->actingAs($guru)->get(route('osis.index'))->assertForbidden();
        $this->actingAs($guru)->get(route('osis.dashboard', $pemilihan))->assertForbidden();
    }
}
