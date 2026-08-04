<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\HariEfektif;
use App\Models\Kelas;
use App\Models\Orangtua;
use App\Models\Sekretaris;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Walikelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Fitur baru: sekretaris kelas (siswa yg ditunjuk wali kelas) bisa mengisi absensi
 * kelasnya sendiri, selain kemampuan lama (ajukan poin/P3). AbsensiController::store()
 * juga ditulis ulang jadi bulk upsert (bukan firstOrNew()+save() per siswa) sekalian —
 * test query-count di sini mengunci itu TETAP flat walau jumlah siswa bertambah.
 */
class SekretarisAbsensiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('kaih_wajib_sebelum_absen', '0');
    }

    private function buatSiswa(Kelas $kelas, string $username, string $nama, string $nis): Siswa
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('x'), 'access' => 'siswa']);

        return Siswa::create([
            'id_login' => $user->uuid, 'id_kelas' => $kelas->uuid, 'nama' => $nama, 'nis' => $nis, 'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);
    }

    private function jadikanSekretaris(Siswa $siswa, Kelas $kelas): void
    {
        Sekretaris::create(['id_siswa' => $siswa->uuid, 'id_kelas' => $kelas->uuid]);
    }

    public function test_helper_siswa_sekretaris_kelas(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $sekretaris = $this->buatSiswa($kelas, 'sek_helper', 'Sekretaris Helper', 'SEK-H1');
        $bukanSekretaris = $this->buatSiswa($kelas, 'bukan_sek_helper', 'Bukan Sekretaris', 'SEK-H2');
        $this->jadikanSekretaris($sekretaris, $kelas);

        $this->assertTrue($sekretaris->fresh()->isSekretarisKelas());
        $this->assertSame($kelas->uuid, $sekretaris->fresh()->sekretarisKelasId());
        $this->assertFalse($bukanSekretaris->fresh()->isSekretarisKelas());
        $this->assertNull($bukanSekretaris->fresh()->sekretarisKelasId());
    }

    public function test_sekretaris_bisa_isi_absensi_kelasnya_tanpa_jam_masuk(): void
    {
        Queue::fake();
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $sekretarisSiswa = $this->buatSiswa($kelas, 'sek_isi', 'Sekretaris Isi', 'SEK-I1');
        $this->jadikanSekretaris($sekretarisSiswa, $kelas);
        $teman = $this->buatSiswa($kelas, 'teman_isi', 'Teman Sekelas', 'SEK-I2');

        $parentUser = User::create(['username' => 'ortu_sek_isi', 'password' => Hash::make('x'), 'access' => 'orangtua']);
        Orangtua::create(['id_login' => $parentUser->uuid, 'id_siswa' => $teman->uuid, 'nama' => 'Ortu Teman']);

        $sekretarisUser = User::where('username', 'sek_isi')->first();

        $this->actingAs($sekretarisUser)->get(route('absensi.index'))->assertOk();

        $this->actingAs($sekretarisUser)->post('/absensi', [
            'id_kelas' => $kelas->uuid,
            'tanggal' => '2026-07-31',
            'status' => [$teman->uuid => 'hadir'],
        ])->assertRedirect();

        $this->assertDatabaseHas('absensis', [
            'id_siswa' => $teman->uuid,
            'tanggal' => '2026-07-31',
            'status' => 'hadir',
            'jam_masuk' => null, // sekretaris (spt wali kelas) bukan bukti scan sungguhan
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $parentUser->uuid,
            'type' => 'App\\Notifications\\StudentAttendanceRecorded',
        ]);
    }

    public function test_sekretaris_tidak_bisa_isi_absensi_kelas_lain(): void
    {
        $kelasSaya = Kelas::create(['tingkat' => 7, 'kelas' => 'C']);
        $kelasLain = Kelas::create(['tingkat' => 7, 'kelas' => 'D']);
        $sekretarisSiswa = $this->buatSiswa($kelasSaya, 'sek_lain', 'Sekretaris Lain', 'SEK-L1');
        $this->jadikanSekretaris($sekretarisSiswa, $kelasSaya);
        $siswaLain = $this->buatSiswa($kelasLain, 'siswa_lain_kelas', 'Siswa Kelas Lain', 'SEK-L2');

        $sekretarisUser = User::where('username', 'sek_lain')->first();

        $this->actingAs($sekretarisUser)->post('/absensi', [
            'id_kelas' => $kelasLain->uuid,
            'tanggal' => '2026-07-31',
            'status' => [$siswaLain->uuid => 'hadir'],
        ])->assertForbidden();

        $this->assertDatabaseMissing('absensis', ['id_siswa' => $siswaLain->uuid]);
    }

    public function test_siswa_biasa_bukan_sekretaris_ditolak_dari_absensi(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'E']);
        $siswaBiasa = $this->buatSiswa($kelas, 'siswa_biasa_abs', 'Siswa Biasa', 'SEK-B1');
        $siswaUser = User::where('username', 'siswa_biasa_abs')->first();

        $this->actingAs($siswaUser)->get(route('absensi.index'))->assertForbidden();
        $this->actingAs($siswaUser)->post('/absensi', [
            'id_kelas' => $kelas->uuid,
            'tanggal' => '2026-07-31',
            'status' => [$siswaBiasa->uuid => 'hadir'],
        ])->assertForbidden();
    }

    public function test_sekretaris_terblokir_kalender_tapi_walikelas_admin_tetap_bisa(): void
    {
        Setting::set('kalender_absen_aktif', '1');
        HariEfektif::create(['tanggal' => '2026-07-31', 'absen_siswa' => false]);

        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'F']);
        $sekretarisSiswa = $this->buatSiswa($kelas, 'sek_kalender', 'Sekretaris Kalender', 'SEK-K1');
        $this->jadikanSekretaris($sekretarisSiswa, $kelas);
        $teman = $this->buatSiswa($kelas, 'teman_kalender', 'Teman Kalender', 'SEK-K2');
        $sekretarisUser = User::where('username', 'sek_kalender')->first();

        // Sekretaris DITOLAK — tanggal belum dibuka utk absen siswa.
        $this->actingAs($sekretarisUser)->post('/absensi', [
            'id_kelas' => $kelas->uuid,
            'tanggal' => '2026-07-31',
            'status' => [$teman->uuid => 'hadir'],
        ])->assertForbidden();
        $this->assertDatabaseMissing('absensis', ['id_siswa' => $teman->uuid]);

        // Wali kelas TETAP bisa (perilaku lama dipertahankan, tak ikut digerbang).
        $waliUser = User::create(['username' => 'wali_kalender', 'password' => Hash::make('x'), 'access' => 'guru']);
        $guru = Guru::create([
            'id_login' => $waliUser->uuid, 'nama' => 'Wali Kalender', 'nik' => 'WALI-KAL-01',
            'face_descriptor' => [array_map(fn ($i) => $i % 2 === 0 ? 1.0 : -1.0, range(0, 63))],
        ]);
        Walikelas::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru->uuid]);

        $this->actingAs($waliUser)->post('/absensi', [
            'id_kelas' => $kelas->uuid,
            'tanggal' => '2026-07-31',
            'status' => [$teman->uuid => 'hadir'],
        ])->assertRedirect();
        $this->assertDatabaseHas('absensis', ['id_siswa' => $teman->uuid, 'status' => 'hadir']);
    }

    public function test_jumlah_query_store_tidak_naik_seiring_jumlah_siswa(): void
    {
        Queue::fake();
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'G']);
        $sekretarisSiswa = $this->buatSiswa($kelas, 'sek_scale', 'Sekretaris Scale', 'SEK-S1');
        $this->jadikanSekretaris($sekretarisSiswa, $kelas);
        $sekretarisUser = User::where('username', 'sek_scale')->first();

        $lima = [];
        for ($i = 0; $i < 5; $i++) {
            $lima[] = $this->buatSiswa($kelas, "siswa_scale_5_{$i}", "Siswa Scale5 {$i}", "SEK-S5-{$i}");
        }

        // Pemanasan: request pertama yg terautentikasi ikut memicu query satu-kali (update
        // last_seen_at, lookup siswa/guru dari middleware) yg tak boleh ikut terhitung —
        // itu bukan bagian dari store() itu sendiri.
        $this->actingAs($sekretarisUser)->get(route('absensi.index'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($sekretarisUser)->post('/absensi', [
            'id_kelas' => $kelas->uuid,
            'tanggal' => '2026-07-31',
            'status' => collect($lima)->mapWithKeys(fn ($s) => [$s->uuid => 'hadir'])->all(),
        ])->assertRedirect();
        $queryLima = count(DB::getQueryLog());
        DB::disableQueryLog();

        $duaPuluh = $lima;
        for ($i = 0; $i < 15; $i++) {
            $duaPuluh[] = $this->buatSiswa($kelas, "siswa_scale_20_{$i}", "Siswa Scale20 {$i}", "SEK-S20-{$i}");
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($sekretarisUser)->post('/absensi', [
            'id_kelas' => $kelas->uuid,
            'tanggal' => '2026-08-01',
            'status' => collect($duaPuluh)->mapWithKeys(fn ($s) => [$s->uuid => 'hadir'])->all(),
        ])->assertRedirect();
        $queryDuaPuluh = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertDatabaseCount('absensis', 25);
        $this->assertSame(
            $queryLima,
            $queryDuaPuluh,
            "Query store() semestinya TETAP walau jumlah siswa naik dr 5 ke 20 (skrg {$queryLima} vs {$queryDuaPuluh}) — indikasi N+1 di jalur simpan absensi kembali muncul."
        );
    }

    public function test_sekretaris_masih_bisa_ajukan_poin_dan_p3_setelah_refactor(): void
    {
        Setting::set('jenis_aturan', 'poin');
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'H']);
        $sekretarisSiswa = $this->buatSiswa($kelas, 'sek_ajukan', 'Sekretaris Ajukan', 'SEK-A1');
        $this->jadikanSekretaris($sekretarisSiswa, $kelas);
        $sekretarisUser = User::where('username', 'sek_ajukan')->first();

        $this->actingAs($sekretarisUser)->get(route('poin.guru.index'))->assertOk();
        $this->actingAs($sekretarisUser)->get(route('p3.guru.index'))->assertOk();
    }
}
