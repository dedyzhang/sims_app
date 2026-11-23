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

class UjianSiswaHasilTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;
    private Kelas $kelas;
    private Ujian $ujian;
    private UjianKelas $ujianKelas;
    private User $siswaUser;
    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Setting::set('wajib_daftar_wajah', '0');

        $this->pelajaran = Pelajaran::create(['nama' => 'Ilmu Pengetahuan Alam', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);

        $guruUser = User::create(['username' => 'guru_ipa', 'password' => Hash::make('password'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru IPA']);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);

        $this->siswaUser = User::create(['username' => 'siswa_hasil', 'password' => Hash::make('password'), 'access' => 'siswa']);
        $this->siswa = Siswa::create(['id_login' => $this->siswaUser->uuid, 'nama' => 'Siswa Hasil', 'id_kelas' => $this->kelas->uuid, 'nis' => '12345']);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid,
            'created_by' => $guruUser->uuid,
            'judul' => 'Ulangan IPA Bab 1',
            'jenis' => 'harian',
            'target_nilai' => 'sumatif',
            'mode_skor' => 'rata_rata',
            'durasi_menit' => 60,
            'status' => 'published',
            'tampilkan_pembahasan' => false,
        ]);

        $this->ujianKelas = UjianKelas::create([
            'id_ujian' => $this->ujian->uuid,
            'id_kelas' => $this->kelas->uuid,
            'token_masuk' => 'TOKENIPA',
        ]);
    }

    public function test_layar_hasil_tanpa_pembahasan_efisien_dan_tidak_query_soal_atau_jawaban(): void
    {
        $attempt = UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelas->uuid,
            'id_siswa' => $this->siswaUser->uuid,
            'urutan_soal' => [],
            'status' => UjianAttempt::STATUS_DINILAI,
            'total_skor' => 85,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->siswaUser)->get(route('ujian.siswa.hasil', [$this->ujian, $attempt]));

        $response->assertOk();
        $response->assertSee('Ujian Selesai');
        $response->assertSee('Terima kasih sudah mengerjakan');
        $response->assertDontSee('Pembahasan');

        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        // Tidak boleh ada query ke ujian_soal atau ujian_jawaban saat pembahasan tidak aktif
        $this->assertFalse($queries->contains(fn ($q) => str_contains($q, 'ujian_soal')));
        $this->assertFalse($queries->contains(fn ($q) => str_contains($q, 'ujian_jawaban')));
    }

    public function test_layar_hasil_dengan_pembahasan_bebas_n_plus_1_pada_soal_dan_opsi(): void
    {
        $this->ujian->update(['tampilkan_pembahasan' => true]);

        // Buat 5 soal (termasuk mcq_complex dengan proporsional untuk memicu potensi N+1 pada opsi)
        $soalUuids = [];
        for ($i = 1; $i <= 5; $i++) {
            $tipe = ($i % 2 === 0) ? 'mcq_complex' : 'mcq';
            $soal = UjianSoal::create([
                'id_ujian' => $this->ujian->uuid,
                'tipe' => $tipe,
                'teks_soal' => "Pertanyaan Soal Ke-{$i}?",
                'poin' => 10,
                'urutan' => $i,
                'skor_mode' => 'proporsional',
                'penjelasan' => "Penjelasan detail soal ke-{$i}",
            ]);
            $soalUuids[] = $soal->uuid;

            UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'Opsi A', 'is_benar' => true, 'urutan' => 1]);
            UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'Opsi B', 'is_benar' => false, 'urutan' => 2]);
        }

        $attempt = UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelas->uuid,
            'id_siswa' => $this->siswaUser->uuid,
            'urutan_soal' => $soalUuids,
            'status' => UjianAttempt::STATUS_DINILAI,
            'total_skor' => 90,
        ]);

        foreach ($soalUuids as $uid) {
            UjianJawaban::create([
                'id_attempt' => $attempt->uuid,
                'id_soal' => $uid,
                'is_benar' => true,
                'skor_diperoleh' => 10,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->siswaUser)->get(route('ujian.siswa.hasil', [$this->ujian, $attempt]));

        $response->assertOk();
        $response->assertSee('Pembahasan');
        $response->assertSee('Pertanyaan Soal Ke-1?');
        $response->assertSee('Penjelasan detail soal ke-1');
        $response->assertSee('10 / 10 poin');

        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        // Verifikasi bahwa opsi tidak diquery berulang kali (N+1 query)
        $opsiQueries = $queries->filter(fn ($q) => str_contains($q, 'ujian_soal_opsi'));
        $this->assertLessThanOrEqual(1, $opsiQueries->count());

        // Verifikasi bahwa jawaban hanya diquery 1 kali secara batch
        $jawabanQueries = $queries->filter(fn ($q) => str_contains($q, 'ujian_jawaban'));
        $this->assertLessThanOrEqual(1, $jawabanQueries->count());
    }

    public function test_siswa_tidak_bisa_melihat_hasil_attempt_milik_siswa_lain(): void
    {
        $siswaLainUser = User::create(['username' => 'siswa_lain', 'password' => Hash::make('password'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $siswaLainUser->uuid, 'nama' => 'Siswa Lain', 'id_kelas' => $this->kelas->uuid, 'nis' => '99999']);

        $attempt = UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelas->uuid,
            'id_siswa' => $this->siswaUser->uuid,
            'urutan_soal' => [],
            'status' => UjianAttempt::STATUS_DINILAI,
        ]);

        $this->actingAs($siswaLainUser)->get(route('ujian.siswa.hasil', [$this->ujian, $attempt]))
            ->assertForbidden();
    }

    public function test_attempt_yang_masih_in_progress_dialihkan_kembali_ke_layar_kerjakan(): void
    {
        $attempt = UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelas->uuid,
            'id_siswa' => $this->siswaUser->uuid,
            'urutan_soal' => [],
            'status' => UjianAttempt::STATUS_IN_PROGRESS,
            'batas_waktu_pada' => now()->addMinutes(30),
        ]);

        $this->actingAs($this->siswaUser)->get(route('ujian.siswa.hasil', [$this->ujian, $attempt]))
            ->assertRedirect(route('ujian.siswa.kerjakan', [$this->ujian, $attempt]));
    }
}
