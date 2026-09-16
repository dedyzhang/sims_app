<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianJawaban;
use App\Models\UjianKelas;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Keamanan & beban CBT saat ujian serentak.
 *
 * Dua kelas masalah yang diuji di sini:
 *  - CELAH: siswa memakai attempt-nya sendiri pada URL ujian LAIN, dan satu siswa
 *    berakhir dengan dua attempt aktif karena cek-lalu-buat yang tidak atomik.
 *  - BEBAN: jumlah query pada halaman yang dibuka/di-poll serentak oleh seluruh
 *    peserta tidak boleh tumbuh mengikuti jumlah soal atau jumlah siswa (N+1) —
 *    itulah yang membuat server tumbang saat 300 siswa masuk bersamaan.
 */
class UjianKeamananDanBebanTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;

    private Kelas $kelas;

    private User $guruUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);

        $this->guruUser = User::create(['username' => 'guru_beban', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $this->guruUser->uuid, 'nama' => 'Guru Beban', 'nik' => '9090909090', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
    }

    /** @return array{0: Ujian, 1: UjianKelas, 2: list<UjianSoal>} */
    private function buatUjian(string $judul, string $token, int $jumlahSoal = 1): array
    {
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $this->guruUser->uuid,
            'judul' => $judul, 'jenis' => 'harian', 'target_nilai' => 'sumatif',
            'durasi_menit' => 60, 'status' => 'published',
        ]);

        $soal = [];
        for ($i = 1; $i <= $jumlahSoal; $i++) {
            $s = UjianSoal::create(['id_ujian' => $ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => "Soal {$i}?", 'poin' => 10, 'urutan' => $i]);
            UjianSoalOpsi::create(['id_soal' => $s->uuid, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
            UjianSoalOpsi::create(['id_soal' => $s->uuid, 'teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2]);
            $soal[] = $s;
        }

        $ujianKelas = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => $token]);

        return [$ujian, $ujianKelas, $soal];
    }

    private function buatSiswa(string $username): User
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'id_kelas' => $this->kelas->uuid, 'nama' => ucfirst($username), 'nis' => (string) random_int(100000, 999999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        return $user;
    }

    /** Jumlah query yang dijalankan oleh satu closure. */
    private function hitungQuery(callable $fn): int
    {
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });
        $fn();
        // Laravel tidak punya API melepas listener; test berikutnya memakai instance
        // aplikasi baru, jadi listener ini tidak bocor antar test.
        return $n;
    }

    // ───────────────────────── CELAH KEAMANAN ─────────────────────────

    /**
     * {ujian} dan {attempt} adalah dua parameter route yang tidak saling terikat.
     * Tanpa pengecekan silang, siswa bisa memasang attempt Ujian A pada URL Ujian B:
     * simpanJawaban() lalu memvalidasi id_soal terhadap bank soal Ujian B dan
     * menuliskannya ke attempt Ujian A — jawaban lintas-ujian masuk & merusak nilai.
     */
    public function test_attempt_ujian_lain_ditolak_dan_tak_bisa_menulis_jawaban_silang(): void
    {
        [$ujianA, $ujianKelasA] = $this->buatUjian('Ulangan A', 'TOKENA');
        [$ujianB, , $soalB] = $this->buatUjian('Ulangan B', 'TOKENB');

        $siswa = $this->buatSiswa('siswa_silang');
        $this->actingAs($siswa)->post(route('ujian.siswa.start', $ujianA), ['token' => 'TOKENA'])->assertRedirect();
        $attemptA = UjianAttempt::where('id_ujian_kelas', $ujianKelasA->uuid)->firstOrFail();

        // Attempt milik sendiri, tapi dipasang pada ujian yang salah.
        $this->actingAs($siswa)->get(route('ujian.siswa.kerjakan', [$ujianB, $attemptA]))->assertNotFound();
        $this->actingAs($siswa)->get(route('ujian.siswa.hasil', [$ujianB, $attemptA]))->assertNotFound();
        $this->actingAs($siswa)->post(route('ujian.siswa.submit', [$ujianB, $attemptA]))->assertNotFound();

        $this->actingAs($siswa)
            ->postJson(route('ujian.siswa.jawab', [$ujianB, $attemptA]), ['id_soal' => $soalB[0]->uuid])
            ->assertNotFound();

        $this->assertSame(0, UjianJawaban::where('id_attempt', $attemptA->uuid)->count());
        $this->assertSame(UjianAttempt::STATUS_IN_PROGRESS, $attemptA->fresh()->status);
    }

    /** Attempt milik siswa lain tetap 403, bukan 404 — kepemilikan dicek lebih dulu. */
    public function test_attempt_siswa_lain_tetap_ditolak(): void
    {
        [$ujian, $ujianKelas] = $this->buatUjian('Ulangan Milik', 'TOKENM');

        $korban = $this->buatSiswa('siswa_korban');
        $this->actingAs($korban)->post(route('ujian.siswa.start', $ujian), ['token' => 'TOKENM'])->assertRedirect();
        $attempt = UjianAttempt::where('id_ujian_kelas', $ujianKelas->uuid)->firstOrFail();

        $penyusup = $this->buatSiswa('siswa_penyusup');
        $this->actingAs($penyusup)->get(route('ujian.siswa.kerjakan', [$ujian, $attempt]))->assertForbidden();
        $this->actingAs($penyusup)->post(route('ujian.siswa.submit', [$ujian, $attempt]))->assertForbidden();
    }

    /**
     * Klik ganda / dua tab pada tombol "Mulai" tidak boleh menghasilkan dua attempt
     * aktif: jawaban akan terbelah dan Pemantauan Live (UjianRoster keyBy id_siswa)
     * hanya menampilkan salah satunya, diam-diam.
     */
    public function test_start_dipanggil_berulang_tetap_satu_attempt(): void
    {
        [$ujian, $ujianKelas] = $this->buatUjian('Ulangan Ganda', 'TOKENG');
        $siswa = $this->buatSiswa('siswa_ganda');

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($siswa)->post(route('ujian.siswa.start', $ujian), ['token' => 'TOKENG'])->assertRedirect();
        }

        $this->assertSame(1, UjianAttempt::where('id_ujian_kelas', $ujianKelas->uuid)
            ->where('id_siswa', $siswa->uuid)
            ->where('status', '!=', UjianAttempt::STATUS_DIBATALKAN)
            ->count());
    }

    /** Token salah tidak boleh membuat attempt sama sekali. */
    public function test_token_salah_tidak_membuat_attempt(): void
    {
        [$ujian, $ujianKelas] = $this->buatUjian('Ulangan Token', 'BENAR123');
        $siswa = $this->buatSiswa('siswa_token');

        $this->actingAs($siswa)->post(route('ujian.siswa.start', $ujian), ['token' => 'SALAH123']);

        $this->assertSame(0, UjianAttempt::where('id_ujian_kelas', $ujianKelas->uuid)->count());
    }

    /** Autosave berulang pada soal yang sama meng-update baris yang sama, bukan menumpuk. */
    public function test_autosave_berulang_tidak_menggandakan_baris_jawaban(): void
    {
        [$ujian, $ujianKelas, $soal] = $this->buatUjian('Ulangan Autosave', 'TOKENAS');
        $opsi = UjianSoalOpsi::where('id_soal', $soal[0]->uuid)->orderBy('urutan')->get();

        $siswa = $this->buatSiswa('siswa_autosave');
        $this->actingAs($siswa)->post(route('ujian.siswa.start', $ujian), ['token' => 'TOKENAS'])->assertRedirect();
        $attempt = UjianAttempt::where('id_ujian_kelas', $ujianKelas->uuid)->firstOrFail();

        foreach ([0, 1, 0, 1, 0] as $pilih) {
            $this->actingAs($siswa)->postJson(route('ujian.siswa.jawab', [$ujian, $attempt]), [
                'id_soal' => $soal[0]->uuid,
                'id_opsi_dipilih' => $opsi[$pilih]->uuid,
            ])->assertOk();
        }

        $this->assertSame(1, UjianJawaban::where('id_attempt', $attempt->uuid)->count());
        $this->assertSame($opsi[0]->uuid, UjianJawaban::where('id_attempt', $attempt->uuid)->value('id_opsi_dipilih'));
    }

    // ───────────────────────── BEBAN / QUERY DOBEL ─────────────────────────

    /**
     * Halaman kerjakan dibuka SEMUA peserta hampir bersamaan. Jumlah query-nya harus
     * tetap saat jumlah soal naik — kalau tumbuh per soal, ujian 40 soal × 300 siswa
     * langsung menghabiskan koneksi database.
     */
    public function test_halaman_kerjakan_jumlah_query_tidak_tumbuh_mengikuti_jumlah_soal(): void
    {
        [$ujianKecil, $ujianKelasKecil] = $this->buatUjian('Ulangan 1 Soal', 'TOKENK1', 1);
        [$ujianBesar, $ujianKelasBesar] = $this->buatUjian('Ulangan 25 Soal', 'TOKENK25', 25);

        $siswa = $this->buatSiswa('siswa_query');

        $this->actingAs($siswa)->post(route('ujian.siswa.start', $ujianKecil), ['token' => 'TOKENK1']);
        $attemptKecil = UjianAttempt::where('id_ujian_kelas', $ujianKelasKecil->uuid)->firstOrFail();
        $this->actingAs($siswa)->post(route('ujian.siswa.start', $ujianBesar), ['token' => 'TOKENK25']);
        $attemptBesar = UjianAttempt::where('id_ujian_kelas', $ujianKelasBesar->uuid)->firstOrFail();

        // Pemanasan: cache soal (Ujian::getCachedSoalDanOpsi) diisi di request pertama.
        $this->actingAs($siswa)->get(route('ujian.siswa.kerjakan', [$ujianKecil, $attemptKecil]))->assertOk();
        $this->actingAs($siswa)->get(route('ujian.siswa.kerjakan', [$ujianBesar, $attemptBesar]))->assertOk();

        $queryKecil = $this->hitungQuery(fn () => $this->actingAs($siswa)
            ->get(route('ujian.siswa.kerjakan', [$ujianKecil, $attemptKecil]))->assertOk());
        $queryBesar = $this->hitungQuery(fn () => $this->actingAs($siswa)
            ->get(route('ujian.siswa.kerjakan', [$ujianBesar, $attemptBesar]))->assertOk());

        $this->assertSame(
            $queryKecil,
            $queryBesar,
            "Query halaman kerjakan tumbuh dari {$queryKecil} (1 soal) ke {$queryBesar} (25 soal) — ada N+1 per soal."
        );
    }

    /**
     * Pemantauan Live guru di-poll berkala selama ujian berlangsung. Jumlah query-nya
     * harus tetap saat jumlah peserta naik, kalau tidak satu ruangan besar bisa
     * menjatuhkan server hanya dari halaman pengawasnya sendiri.
     */
    public function test_pemantauan_live_jumlah_query_tidak_tumbuh_mengikuti_jumlah_siswa(): void
    {
        [$ujian] = $this->buatUjian('Ulangan Monitor', 'TOKENMON', 3);

        for ($i = 1; $i <= 3; $i++) {
            $this->buatSiswa('siswa_mon_a'.$i);
        }
        $this->actingAs($this->guruUser)->getJson(route('ujian.monitor.poll', $ujian))->assertOk();
        $querySedikit = $this->hitungQuery(fn () => $this->actingAs($this->guruUser)
            ->getJson(route('ujian.monitor.poll', $ujian))->assertOk());

        for ($i = 1; $i <= 25; $i++) {
            $this->buatSiswa('siswa_mon_b'.$i);
        }
        $queryBanyak = $this->hitungQuery(fn () => $this->actingAs($this->guruUser)
            ->getJson(route('ujian.monitor.poll', $ujian))->assertOk());

        $this->assertSame(
            $querySedikit,
            $queryBanyak,
            "Query Pemantauan Live tumbuh dari {$querySedikit} (3 siswa) ke {$queryBanyak} (28 siswa) — ada N+1 per siswa."
        );
    }

    /** Daftar "Ujian Saya" siswa: query tetap walau jumlah ujian di kelasnya bertambah. */
    public function test_daftar_ujian_siswa_jumlah_query_tidak_tumbuh_mengikuti_jumlah_ujian(): void
    {
        $siswa = $this->buatSiswa('siswa_daftar');

        $this->buatUjian('Ulangan D1', 'TOKEND1');
        $this->actingAs($siswa)->get(route('ujian.siswa.index'))->assertOk();
        $querySatu = $this->hitungQuery(fn () => $this->actingAs($siswa)
            ->get(route('ujian.siswa.index'))->assertOk());

        for ($i = 2; $i <= 10; $i++) {
            $this->buatUjian('Ulangan D'.$i, 'TOKEND'.$i);
        }
        $querySepuluh = $this->hitungQuery(fn () => $this->actingAs($siswa)
            ->get(route('ujian.siswa.index'))->assertOk());

        $this->assertSame(
            $querySatu,
            $querySepuluh,
            "Query daftar ujian tumbuh dari {$querySatu} (1 ujian) ke {$querySepuluh} (10 ujian) — ada N+1 per ujian."
        );
    }
}
