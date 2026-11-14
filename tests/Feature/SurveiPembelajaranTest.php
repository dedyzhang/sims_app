<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Walikelas;
use App\Survei\AnalisisGuru;
use App\Survei\Periode;
use App\Survei\Pertanyaan;
use App\Survei\Respons;
use App\Survei\Target;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SurveiPembelajaranTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, string $role): User
    {
        return User::create(['username' => $name, 'password' => Hash::make('password'), 'access' => $role]);
    }

    private function student(string $name, Kelas $kelas): Siswa
    {
        $u = $this->user($name, 'siswa');

        return Siswa::create(['id_login' => $u->uuid, 'nama' => $name, 'nis' => $name, 'id_kelas' => $kelas->uuid, 'jk' => 'L']);
    }

    private function fixture(): array
    {
        Setting::set('wajib_daftar_wajah', '0');
        $admin = $this->user('survei_admin', 'admin');
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $guruUser = $this->user('survei_guru', 'guru');
        $guru = Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Contoh']);
        $mapel = Pelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        Ngajar::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru->uuid, 'id_pelajaran' => $mapel->uuid]);
        Jadwal::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru->uuid, 'id_pelajaran' => $mapel->uuid, 'hari' => 1]);
        Walikelas::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru->uuid]);
        $siswa = $this->student('survei_siswa', $kelas);

        return compact('admin', 'kelas', 'guruUser', 'guru', 'mapel', 'siswa');
    }

    private function periode(): Periode
    {
        return Periode::create(['judul' => 'Semester', 'mulai' => now()->subDay(), 'selesai' => now()->addDay(), 'dibuat_oleh' => 'admin', 'pertanyaan' => ['mapel' => Pertanyaan::untuk('mapel'), 'wali' => Pertanyaan::untuk('wali'), 'komentar_mapel' => Pertanyaan::terbuka('mapel'), 'komentar_wali' => Pertanyaan::terbuka('wali')]]);
    }

    private function target(Periode $p, array $f): Target
    {
        return $p->targets()->create(['kelas_id' => $f['kelas']->uuid, 'guru_id' => $f['guru']->uuid, 'pelajaran_id' => $f['mapel']->uuid, 'jenis' => 'mapel', 'kelas_nama' => 'Kelas 7 A', 'guru_nama' => 'Guru Contoh', 'pelajaran_nama' => 'Matematika']);
    }

    public function test_admin_membuat_sasaran_dan_qr_tersedia(): void
    {
        $f = $this->fixture();
        $kelasLain = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);
        $guruLain = Guru::create(['nama' => 'Guru Tingkat Delapan']);
        Ngajar::create(['id_kelas' => $f['kelas']->uuid, 'id_guru' => $guruLain->uuid, 'id_pelajaran' => $f['mapel']->uuid]);
        Jadwal::create(['id_kelas' => $kelasLain->uuid, 'id_guru' => $guruLain->uuid, 'id_pelajaran' => $f['mapel']->uuid, 'hari' => 1]);
        Jadwal::create(['id_kelas' => $f['kelas']->uuid, 'id_guru' => $f['guru']->uuid, 'id_pelajaran' => $f['mapel']->uuid, 'hari' => 2]);
        Jadwal::create(['id_kelas' => $f['kelas']->uuid, 'hari' => 2, 'keterangan' => 'Istirahat']);
        $kelasTanpaJadwal = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);

        $payload = ['judul' => 'Semester', 'mulai' => now()->subHour()->toDateTimeString(), 'selesai' => now()->addDay()->toDateTimeString()];
        $this->actingAs($f['admin'])->post(route('survei.store'), $payload + ['kelas' => [$f['kelas']->uuid, $kelasTanpaJadwal->uuid]])
            ->assertSessionHasErrors('kelas');
        $this->assertSame(0, Periode::count());
        $this->post(route('survei.store'), $payload + ['kelas' => [$f['kelas']->uuid, $kelasLain->uuid]])->assertRedirect();
        $p = Periode::firstOrFail();
        $this->assertCount(3, $p->targets);
        $this->assertSame([$f['guru']->uuid], $p->targets()->where('kelas_id', $f['kelas']->uuid)->where('jenis', 'mapel')->pluck('guru_id')->all());
        $this->assertCount(10, $p->pertanyaan['mapel']);
        $this->assertCount(6, $p->pertanyaan['wali']);
        $this->assertTrue($p->wajib_token);
        $this->assertFalse($p->wajib_seb);
        $this->actingAs($f['admin'])->get(route('survei.periode', $p))->assertOk()->assertSee('svg', false)->assertSee(route('survei.masuk', $p));
        $this->actingAs($f['siswa']->user)->get(route('survei.form', $p))->assertOk()
            ->assertSee('Menunggu sesi dibuka')->assertDontSee('Guru Tingkat Delapan');
    }

    public function test_sesi_token_membatasi_kelas_dan_menjaga_jawaban_saat_sesi_berubah(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $p->update(['dibuat_oleh' => $f['admin']->uuid, 'wajib_token' => true]);
        $mapelA = $this->target($p, $f);
        $kelasB = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $mapelB = $this->target($p, [...$f, 'kelas' => $kelasB]);
        $mapelB->update(['kelas_nama' => 'Kelas 7 B', 'pelajaran_nama' => 'Biologi 7B']);
        $siswaB = $this->student('siswa_tujuh_b', $kelasB);
        $siswaSusulan = $this->student('siswa_susulan', $f['kelas']);
        $form = route('survei.form', $p);
        $masuk = route('survei.token', $p);
        $mulai = route('survei.sesi.mulai', $p);
        $kirim = route('survei.kirim-batch', $p);
        $detail = route('survei.periode', $p);
        $pilihA = ['kelas_sesi' => [$f['kelas']->uuid]];
        $jawabanA = ['jawaban' => [$mapelA->uuid => array_fill(0, 10, 5)]];

        $adminLain = $this->user('admin_sesi_lain', 'superadmin');
        $this->actingAs($adminLain)->post($mulai, $pilihA)->assertForbidden();
        $this->post(route('survei.tutup', $p))->assertForbidden();
        $this->actingAs($f['siswa']->user)->get($form)->assertOk()->assertSee('Menunggu sesi dibuka')->assertDontSee('Guru Contoh');
        $this->postJson($kirim, $jawabanA)->assertForbidden();

        $this->actingAs($f['admin'])->post($mulai, $pilihA)->assertRedirect($detail);
        $sesiA = $p->sesi()->sole();
        $kodeA = $sesiA->token;
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $kodeA);
        $this->assertNotSame($kodeA, $sesiA->getRawOriginal('token'));
        $this->get($detail)->assertOk()->assertSee($kodeA)->assertSee('Dimulai')->assertSee('Siap')
            ->assertSee('Boleh mengisi')->assertSee('Tertutup pada sesi ini')->assertSee('Kelas 7 B')
            ->assertDontSee('Durasi sesi');
        $this->post($mulai, $pilihA)->assertSessionHasErrors('kelas_sesi');
        $this->actingAs($adminLain)->get($detail)->assertOk()->assertDontSee('Tampilkan token');
        $this->post(route('survei.sesi.token', [$p, $sesiA]))->assertForbidden();
        $this->post(route('survei.sesi.akhiri', [$p, $sesiA]))->assertForbidden();

        $this->actingAs($siswaB->user)->post($masuk, ['token' => $kodeA])->assertRedirect($form)->assertSessionHasErrors('token');
        $this->get($form)->assertOk()->assertSee('Kelasmu belum dibuka')->assertDontSee('Biologi 7B')->assertDontSee('Matematika');
        $this->postJson($kirim, ['jawaban' => [$mapelB->uuid => array_fill(0, 10, 5)], 'tanda_sesi' => $kodeA])->assertForbidden();
        $this->assertSame(0, Respons::count());
        $this->actingAs($f['siswa']->user)->get($form)->assertOk()->assertSee('Token dari surveyor')->assertDontSee('Guru Contoh');
        $this->post($masuk, ['token' => $kodeA])->assertRedirect($form);
        $tandaA = $this->get($form)->assertOk()->assertSee('Matematika')->assertDontSee('Biologi 7B')->viewData('tandaSesi');

        $this->actingAs($f['admin'])->post(route('survei.sesi.token', [$p, $sesiA]))->assertRedirect($detail);
        $kodeBaru = $sesiA->fresh()->token;
        $this->assertNotSame($kodeA, $kodeBaru);
        $this->actingAs($f['siswa']->user)->postJson($kirim, $jawabanA + ['tanda_sesi' => $tandaA])->assertForbidden();
        $this->post($masuk, ['token' => $kodeA])->assertSessionHasErrors('token');
        $this->post($masuk, ['token' => $kodeBaru])->assertRedirect($form);
        $tandaBaru = $this->get($form)->assertOk()->viewData('tandaSesi');
        $this->postJson($kirim, $jawabanA + ['tanda_sesi' => $tandaA])->assertForbidden();
        $this->postJson($kirim, $jawabanA + ['tanda_sesi' => $tandaBaru])->assertOk()->assertJson(['tersimpan' => true]);
        $this->actingAs($f['admin'])->get($detail)->assertOk()->assertSee('Kelas 7 A · 1 siswa');
        $this->post(route('survei.sesi.akhiri', [$p, $sesiA]))->assertRedirect($detail);
        $this->assertNull($p->sesiAktif());

        $this->post($mulai, ['kelas_sesi' => [$f['kelas']->uuid, $kelasB->uuid]])->assertRedirect($detail);
        $gabungan = $p->sesiAktif();
        $this->assertNotSame($kodeBaru, $gabungan->token);
        $this->actingAs($siswaB->user)->post($masuk, ['token' => $gabungan->token])->assertRedirect($form);
        $this->get($form)->assertOk()->assertSee('Biologi 7B')->assertDontSee('Matematika');
        $this->actingAs($siswaSusulan->user)->post($masuk, ['token' => $gabungan->token])->assertRedirect($form);
        $tandaSusulan = $this->get($form)->assertOk()->assertSee('Matematika')->viewData('tandaSesi');
        $this->actingAs($f['siswa']->user)->get($form)->assertRedirect(route('survei.index'));
        $this->postJson($kirim, $jawabanA + ['tanda_sesi' => $tandaBaru])->assertOk()->assertJson(['tersimpan' => true]);

        $this->travel(6)->minutes();
        $this->actingAs($siswaSusulan->user)->get($form)->assertOk()->assertSee('Matematika');
        $this->actingAs($f['admin'])->post(route('survei.sesi.akhiri', [$p, $gabungan]))->assertRedirect($detail);
        $this->assertNull($p->fresh()->sesiAktif());
        $this->actingAs($siswaSusulan->user)->get($form)->assertOk()->assertSee('Menunggu sesi dibuka');
        $this->postJson($kirim, $jawabanA + ['tanda_sesi' => $tandaSusulan])->assertForbidden();
        $this->assertSame(1, Respons::count());

        $duaBelas = [$f['kelas']->uuid, $kelasB->uuid];
        for ($n = 1; $n <= 10; $n++) {
            $kelas = Kelas::create(['tingkat' => 9, 'kelas' => (string) $n]);
            $this->target($p, [...$f, 'kelas' => $kelas])->update(['kelas_nama' => 'Kelas 9 '.$n]);
            $duaBelas[] = $kelas->uuid;
        }
        $this->actingAs($f['admin'])->get($detail)->assertOk()
            ->assertSee('Centang kelas yang dimulai sekarang')
            ->assertSee('Kelas 9 10')
            ->assertDontSee('Durasi sesi');
        $this->post($mulai, ['kelas_sesi' => $duaBelas])->assertRedirect($detail);
        $gelombang = $p->sesiAktif();
        $this->assertNotNull($gelombang);
        $this->assertEqualsCanonicalizing($duaBelas, $gelombang->kelas_ids);
        $this->assertTrue($gelombang->berlaku_sampai->equalTo($p->fresh()->selesai));
        $this->get($detail)->assertOk()->assertSee('Siap')->assertSee('Dimulai');
    }

    public function test_seb_memvalidasi_klien_dan_membuka_jalur_keluar_setelah_jawaban_tersimpan(): void
    {
        $f = $this->fixture();
        $periode = $this->periode();
        $periode->update(['dibuat_oleh' => $f['admin']->uuid, 'wajib_token' => true]);
        $target = $this->target($periode, $f);
        $detail = route('survei.periode', $periode);
        $mulai = route('survei.sesi.mulai', $periode);
        $form = route('survei.form', $periode);
        $kirim = route('survei.kirim-batch', $periode);
        $kelas = ['kelas_sesi' => [$f['kelas']->uuid]];
        $quitId = fake()->uuid();
        $urlKeluar = route('survei.seb.keluar', ['kode' => $quitId]);
        $browserKey = hash('sha256', 'test-browser-exam-key');

        $this->actingAs($this->user('admin_lain_seb', 'superadmin'))
            ->post(route('survei.seb.wajib', $periode))->assertForbidden();
        $this->actingAs($f['admin'])->post(route('survei.seb.wajib', $periode))->assertRedirect($detail);
        $this->post($mulai, $kelas)->assertSessionHasErrors('wajib_seb');
        config(['survei.seb_browser_exam_keys' => $browserKey, 'survei.seb_quit_id' => $quitId]);
        $this->post($mulai, $kelas)->assertRedirect($detail);

        $this->actingAs($f['siswa']->user)->get($urlKeluar)->assertNotFound();
        $this->post(route('survei.token', $periode), ['token' => $periode->sesiAktif()->token])->assertRedirect($form);
        $verifikasi = route('survei.seb.verifikasi', $periode);
        $urlForm = $this->get($form)->headers->get('Location');
        $this->assertStringStartsWith($form.'?verifikasi=', $urlForm);
        $this->get($urlForm)->assertOk()->assertSee('Gunakan Safe Exam Browser')->assertDontSee('Matematika');
        $this->postJson($verifikasi, ['bukti_seb' => hash('sha256', $form.$browserKey)])->assertForbidden();
        $this->get($urlForm, ['X-SafeExamBrowser-RequestHash' => hash('sha256', $urlForm.$browserKey)])
            ->assertOk()->assertSee('Matematika');

        $bukti = hash('sha256', $urlForm.$browserKey);
        $this->postJson($verifikasi, ['bukti_seb' => $bukti])->assertOk()->assertJson(['form' => $urlForm]);
        $tanda = $this->get($urlForm)->assertOk()->assertSee('Matematika')->viewData('tandaSesi');
        $payload = ['jawaban' => [$target->uuid => array_fill(0, 10, 5)], 'tanda_sesi' => $tanda];
        $this->postJson($kirim, $payload)->assertForbidden();
        $this->postJson($kirim, ['tanda_sesi' => $tanda, 'bukti_seb' => $bukti])->assertUnprocessable();
        $this->get($urlKeluar)->assertNotFound();
        $this->assertSame(0, Respons::count());
        $this->postJson($kirim, $payload + ['bukti_seb' => $bukti])->assertOk()
            ->assertJson(['tersimpan' => true, 'keluar' => $urlKeluar]);
        $this->assertSame(1, Respons::count());
        $this->get($urlKeluar)->assertRedirect(route('survei.index'));
        $this->get($urlKeluar)->assertNotFound();
    }

    public function test_sims_kios_memerlukan_tanda_tangan_perangkat_untuk_buka_dan_kirim(): void
    {
        $f = $this->fixture();
        $periode = $this->periode();
        $periode->update(['dibuat_oleh' => $f['admin']->uuid, 'wajib_seb' => true]);
        $target = $this->target($periode, $f);
        $detail = route('survei.periode', $periode);
        $form = route('survei.form', $periode);
        $selesai = route('survei.kios.selesai', $periode);
        $kelas = ['kelas_sesi' => [$f['kelas']->uuid]];
        $private = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $public = openssl_pkey_get_details($private)['key'];
        config(['survei.kios_public_keys' => preg_replace('/-----[^-]+-----|\s+/', '', $public)]);

        $this->actingAs($f['admin'])->post(route('survei.kios.wajib', $periode))->assertRedirect($detail);
        $periode->refresh();
        $this->assertTrue($periode->wajib_kios);
        $this->assertFalse($periode->wajib_seb);
        $this->post(route('survei.sesi.mulai', $periode), $kelas)->assertRedirect($detail);
        $this->actingAs($f['siswa']->user)->get($selesai)->assertNotFound();
        $this->post(route('survei.token', $periode), ['token' => $periode->sesiAktif()->token])->assertRedirect($form);
        $gate = $this->get($form)->assertOk()->assertSee('Buka di SIMS Kios')->assertDontSee('Matematika');
        $nonce = $gate->viewData('tantanganKios');
        $tanda = $gate->viewData('tandaSesi');
        $sign = function (string $action) use ($private, $periode, $nonce, $tanda): string {
            $this->assertTrue(openssl_sign("sims-kios-v1|{$periode->uuid}|$action|$nonce|$tanda", $signature, $private, OPENSSL_ALGO_SHA256));

            return base64_encode($signature);
        };

        $verifikasi = route('survei.kios.verifikasi', $periode);
        $this->postJson($verifikasi, ['tanda_sesi' => $tanda, 'bukti_kios' => $sign('kirim')])->assertForbidden();
        $this->postJson($verifikasi, ['tanda_sesi' => $tanda, 'bukti_kios' => $sign('buka')])->assertOk()->assertJson(['form' => $form]);
        $this->get($form)->assertOk()->assertSee('id="survei-pengisian"', false);
        $this->get($form)->assertOk()->assertDontSee('id="survei-pengisian"', false);
        $kirim = route('survei.kirim-batch', $periode);
        $jawaban = ['jawaban' => [$target->uuid => array_fill(0, 10, 5)], 'tanda_sesi' => $tanda];
        $this->postJson($kirim, $jawaban + ['bukti_kios' => $sign('buka')])->assertForbidden();
        $this->postJson($kirim, ['tanda_sesi' => $tanda, 'bukti_kios' => $sign('kirim')])->assertUnprocessable();
        $this->get($selesai)->assertNotFound();
        $this->postJson($kirim, $jawaban + ['bukti_kios' => $sign('kirim')])->assertOk()
            ->assertJson(['tersimpan' => true, 'kios_selesai' => $selesai]);
        $this->get($selesai)->assertOk();
        $this->assertSame(1, Respons::count());
    }

    public function test_siswa_hanya_kelasnya_kirim_sekali_dan_tidak_bisa_setelah_tutup(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $t = $this->target($p, $f);
        $other = $this->student('kelas_lain', Kelas::create(['tingkat' => 7, 'kelas' => 'B']));
        $this->actingAs($other->user)->get(route('survei.isi', $t))->assertForbidden();
        $this->post(route('survei.kirim-batch', $p), ['jawaban' => [$t->uuid => array_fill(0, 10, '4')]])->assertForbidden();
        $this->actingAs($f['siswa']->user)->get(route('survei.masuk', $p))->assertRedirect(route('survei.form', $p));
        $this->get(route('survei.index'))->assertOk()->assertSee('Survei ini bersifat rahasia.')->assertSee('Isi survei kelas');
        $this->get(route('survei.form', $p))->assertOk()->assertSee('Survei ini bersifat rahasia.')->assertSee('Tidak tahu')
            ->assertSee('Mulai layar penuh')->assertSee('survei-fokus.js')
            ->assertSee('survei-pembelajaran.css')->assertSee('Progres jawaban wajib');
        $answers = array_fill(0, 10, '4');
        $payload = ['jawaban' => [$t->uuid => $answers], 'kendala' => [$t->uuid => ['materi']], 'komentar' => [$t->uuid => ['Bagus', 'Perlu lebih banyak contoh']]];
        $this->postJson(route('survei.kirim-batch', $p), $payload)->assertOk()->assertExactJson(['tersimpan' => true]);
        $payload['jawaban'][$t->uuid][0] = '1';
        $this->post(route('survei.kirim-batch', $p), $payload)->assertRedirect(route('survei.index'));
        $this->assertSame(1, Respons::count());
        $this->assertSame('4', Respons::first()->jawaban[0]);
        $this->assertSame(['pilihan' => ['materi'], 'lainnya' => null], Respons::first()->kendala);
        $this->get(route('survei.form', $p))->assertRedirect(route('survei.index'));
        $this->followingRedirects()->get(route('survei.masuk', $p))->assertOk()
            ->assertSee('Jawaban sudah dikirim dan bersifat rahasia.')->assertDontSee('Kirim semua jawaban')->assertDontSee('Bagus');
        $this->get(route('survei.hasil', $t))->assertForbidden();

        $kelasBaru = Kelas::create(['tingkat' => 8, 'kelas' => 'B']);
        $targetBaru = $this->target($p, array_replace($f, ['kelas' => $kelasBaru]));
        $f['siswa']->update(['id_kelas' => $kelasBaru->uuid]);
        $this->get(route('survei.form', $p))->assertRedirect(route('survei.index'));
        $this->post(route('survei.kirim-batch', $p), ['jawaban' => [$targetBaru->uuid => $answers]])
            ->assertRedirect(route('survei.index'));
        $this->assertSame(1, Respons::count());
        $p->update(['ditutup' => true]);
        $this->post(route('survei.kirim-batch', $p), $payload)->assertForbidden();
    }

    public function test_scope_sekolah_dan_siswa_tidak_bisa_kelola_periode(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $t = $this->target($p, $f);
        $this->actingAs($f['siswa']->user)->post(route('survei.store'), [])->assertForbidden();
        DB::table('survei_targets')->where('uuid', $t->uuid)->update(['school_id' => '99999999-9999-9999-9999-999999999999']);
        $this->get(route('survei.isi', $t))->assertNotFound();
    }

    public function test_input_jawaban_wajib_lengkap_dan_tidak_menerima_nilai_tak_sah(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $t = $this->target($p, $f);
        $this->actingAs($f['siswa']->user)->post(route('survei.kirim-batch', $p), ['jawaban' => [$t->uuid => array_fill(0, 10, '9')]])->assertSessionHasErrors();
        $this->postJson(route('survei.kirim-batch', $p), ['jawaban' => [$t->uuid => array_fill(0, 9, '4')]])
            ->assertUnprocessable()->assertJsonMissingPath('tersimpan');
        $this->assertSame(0, Respons::count());
    }

    public function test_satu_formulir_menyimpan_masukan_mapel_dan_wali_kelas(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $mapel = $this->target($p, $f);
        $wali = $p->targets()->create([
            'kelas_id' => $f['kelas']->uuid, 'guru_id' => $f['guru']->uuid,
            'jenis' => 'wali', 'kelas_nama' => 'Kelas 7 A', 'guru_nama' => 'Guru Contoh',
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($f['siswa']->user)->get(route('survei.form', $p))->assertOk()
            ->assertSeeInOrder(['Matematika', 'Apakah ada kendala dengan wali kelasmu?'])
            ->assertSeeInOrder(['Apa kendala utamamu', 'Penjelasan sulit dipahami', 'Tugas terlalu banyak', 'Tidak ada kendala', 'Lainnya'])
            ->assertDontSee('Alat atau fasilitas kurang')
            ->assertDontSee('simsPollInterval(updateTickerStats', false)
            ->assertSee('Tidak tahu / belum pernah');
        $queries = collect(DB::getQueryLog())->pluck('query');
        $this->assertCount(1, $queries->filter(fn ($sql) => str_contains($sql, 'survei_respons')));

        $payload = [
            'jawaban' => [$mapel->uuid => array_fill(0, 10, '4'), $wali->uuid => array_fill(0, 6, '0')],
            'kendala' => [$mapel->uuid => ['lainnya'], $wali->uuid => ['ya']],
            'kendala_lainnya' => [$mapel->uuid => 'Waktu latihan singkat'],
            'komentar' => [$mapel->uuid => [1 => 'Perlu lebih banyak contoh'], $wali->uuid => ['Informasi kadang terlambat']],
        ];
        $this->post(route('survei.kirim-batch', $p), array_replace($payload, ['kendala' => [$mapel->uuid => ['lainnya']]]))
            ->assertSessionHasErrors('kendala.'.$wali->uuid);
        $this->assertSame(0, Respons::count());

        DB::flushQueryLog();
        $this->post(route('survei.kirim-batch', $p), $payload)->assertRedirect(route('survei.index'));
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains($sql, 'survei_respons'));
        DB::disableQueryLog();
        $this->assertCount(1, $queries->filter(fn ($sql) => str_starts_with($sql, 'select')));
        $this->assertCount(1, $queries->filter(fn ($sql) => str_starts_with($sql, 'insert')));
        $this->assertSame(2, Respons::count());
        $this->assertSame([null, 'Perlu lebih banyak contoh'], $mapel->respons()->firstOrFail()->komentar);
        $this->assertSame('Waktu latihan singkat', $mapel->respons()->firstOrFail()->kendala['lainnya']);
        $this->assertSame(['Informasi kadang terlambat'], $wali->respons()->firstOrFail()->komentar);
        $this->get(route('survei.index'))->assertOk()->assertSee('Jawaban sudah dikirim dan bersifat rahasia.')->assertDontSee('Isi survei kelas');
    }

    public function test_periode_lama_mempertahankan_butir_dan_arah_skor_aslinya(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $p->update(['pertanyaan' => ['mapel' => array_fill(0, 12, 'Butir periode sebelumnya.')]]);
        $t = $this->target($p, $f);

        $this->actingAs($f['siswa']->user)->get(route('survei.form', $p))->assertOk()
            ->assertSee('Butir periode sebelumnya.')->assertDontSee(Pertanyaan::untuk('mapel')[7]);
        $this->post(route('survei.kirim-batch', $p), ['jawaban' => [$t->uuid => array_fill(0, 12, '4')]])
            ->assertRedirect(route('survei.index'));
        $this->assertSame(4, $p->skor('mapel', 7, 4));
    }

    public function test_qr_mengarahkan_kembali_siswa_setelah_login(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $this->target($p, $f);
        $this->get(route('survei.masuk', $p))->assertRedirect(route('login'));
        $this->post(route('login.post'), ['credential' => 'survei_siswa', 'password' => 'password'])->assertRedirect(route('survei.masuk', $p));
    }

    public function test_ganti_akun_survei_kembali_ke_tautan_yang_diminta(): void
    {
        $f = $this->fixture();
        $aktif = $this->periode();
        $this->target($aktif, $f);
        $tutup = $this->periode();
        $tutup->update(['ditutup' => true]);
        $analisis = route('survei.analisis', $tutup);
        $form = route('survei.masuk', $aktif);

        $this->actingAs($f['siswa']->user)->get($analisis)->assertForbidden()
            ->assertSee('Ganti akun')->assertDontSee('Cara membaca skor');
        $this->post(route('survei.ganti-akun', [$tutup, 'analisis']))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.post'), ['credential' => 'survei_admin', 'password' => 'password'])->assertRedirect($analisis);
        $this->get($analisis)->assertOk()->assertSee('Cara membaca skor');

        $this->get($form)->assertForbidden()->assertSee('Ganti akun')->assertDontSee('Kirim semua jawaban');
        $this->post(route('survei.ganti-akun', [$aktif, 'isi']))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.post'), ['credential' => 'survei_siswa', 'password' => 'password'])->assertRedirect($form);
        $this->followingRedirects()->get($form)->assertOk()->assertSee('Survei ini bersifat rahasia.');
    }

    public function test_surveyor_yang_keluar_dari_tautan_siswa_kembali_ke_pemantauan(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $this->target($p, $f);
        $humas = $this->user('waka_humas_kembali', 'humas');
        $masuk = route('survei.masuk', $p);
        $pemantauan = route('survei.periode', $p);

        $this->actingAs($humas)->get($masuk)->assertRedirect($pemantauan);
        $this->followingRedirects()->actingAs($humas)->get(route('survei.form', $p))
            ->assertOk()
            ->assertSee('Tautan itu untuk siswa. Pemantauan survei tetap di halaman ini.')
            ->assertSee('Sesi pengisian');

        $this->actingAs($f['guruUser'])->get($masuk)->assertForbidden()
            ->assertSee('Ganti akun')
            ->assertDontSee('Kembali ke pemantauan');
        $this->actingAs($f['admin'])->get($masuk)->assertForbidden()
            ->assertSee('Ganti akun')
            ->assertSee('Kembali ke pemantauan');

        $this->actingAs($humas)->post(route('survei.ganti-akun', [$p, 'isi']))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.post'), ['credential' => 'waka_humas_kembali', 'password' => 'password'])
            ->assertRedirect($pemantauan);
        $this->get($pemantauan)->assertOk()
            ->assertSee('Sesi pengisian')
            ->assertSee('target="_blank"', false)
            ->assertSee($masuk);

        $p->update(['dibuat_oleh' => $f['admin']->uuid]);
        $this->actingAs($f['admin'])->get($masuk)->assertRedirect($pemantauan);
    }

    public function test_ekspor_hanya_admin_setelah_tutup_dan_tidak_bocor_kelompok_kecil(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $t = $this->target($p, $f);
        $this->actingAs($f['admin'])->get(route('survei.export', $p))->assertForbidden();
        $p->update(['ditutup' => true]);
        for ($i = 0; $i < 5; $i++) {
            $jawaban = array_fill(0, 10, '4');
            $jawaban[0] = $i === 0 ? '4' : '0';
            Respons::create(['target_id' => $t->uuid, 'siswa_id' => 'export-'.$i, 'jawaban' => $jawaban, 'komentar' => ['KOMENTAR RAHASIA']]);
        }
        $this->actingAs($f['guruUser'])->get(route('survei.export', $p))->assertForbidden();
        $csv = $this->actingAs($f['admin'])->get(route('survei.export', $p))->assertOk()->streamedContent();
        $this->assertStringContainsString('Matematika', $csv);
        $this->assertStringNotContainsString('KOMENTAR RAHASIA', $csv);
        $this->assertStringNotContainsString(Pertanyaan::untuk('mapel')[0], $csv);
        $this->assertStringNotContainsString('export-', $csv);
    }

    public function test_hasil_tertutup_dan_terbatas_untuk_petugas_yang_berhak(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $p->update(['dibuat_oleh' => $f['admin']->uuid]);
        $t = $this->target($p, $f);
        $this->actingAs($f['guruUser'])->get(route('survei.hasil', $t))->assertForbidden();
        $petugas = $this->user('petugas_mutu', 'kurikulum');
        $this->actingAs($petugas)->get(route('survei.hasil', $t))->assertForbidden();
        RolePermission::create(['role' => 'kurikulum', 'permission' => 'view_survei']);
        $this->get(route('survei.hasil', $t))->assertForbidden();
        $p->update(['ditutup' => true]);
        $this->actingAs($f['guruUser'])->get(route('survei.hasil', $t))->assertOk()->assertSee('kurang dari lima');
        RolePermission::create(['role' => 'siswa', 'permission' => 'view_survei']);
        $this->actingAs($f['siswa']->user)->get(route('survei.hasil', $t))->assertForbidden();
        $this->actingAs($petugas)->get(route('survei.index'))->assertOk()->assertSee('Lihat rekap');
        $this->get(route('survei.hasil', $t))->assertOk()->assertSee('kurang dari lima');
        for ($i = 0; $i < 5; $i++) {
            Respons::create(['target_id' => $t->uuid, 'siswa_id' => 'siswa-'.$i, 'jawaban' => array_fill(0, 12, $i === 0 ? '0' : '4'), 'komentar' => ['pribadi']]);
        }
        $this->actingAs($f['guruUser'])->get(route('survei.hasil', $t))->assertOk()
            ->assertDontSee('pribadi')->assertDontSee('siswa-0');
        $this->actingAs($petugas);
        $this->get(route('survei.hasil', $t))->assertOk()->assertSee('Belum cukup jawaban')->assertDontSee('pribadi');
        $this->actingAs($this->user('admin_lain', 'admin'))->get(route('survei.hasil', $t))->assertOk()->assertDontSee('pribadi');
        $this->actingAs($f['admin'])->get(route('survei.hasil', $t))->assertOk()->assertSee('pribadi')->assertDontSee('siswa-0');
    }

    public function test_daftar_responden_hanya_untuk_pembuat_periode(): void
    {
        $f = $this->fixture();
        $periode = $this->periode();
        $periode->update(['dibuat_oleh' => $f['admin']->uuid]);
        $target = $this->target($periode, $f);
        $targetKedua = $this->target($periode, $f);
        $targetLuar = $this->target($this->periode(), $f);
        $siswaLuar = $this->student('Siswa dari periode lain', $f['kelas']);
        foreach ([[$target, $f['siswa']], [$targetKedua, $f['siswa']], [$targetLuar, $siswaLuar]] as [$sasaran, $siswa]) {
            $sasaran->respons()->create([
                'siswa_id' => $siswa->uuid, 'jawaban' => array_fill(0, 10, 5), 'komentar' => ['Komentar privat'],
            ]);
        }
        $url = route('survei.responden', $periode);
        $detail = route('survei.periode', $periode);
        $this->actingAs($f['admin'])->get($url)->assertForbidden();
        $periode->update(['ditutup' => true]);

        RolePermission::create(['role' => 'kurikulum', 'permission' => 'view_survei']);
        $petugas = $this->user('petugas_rekap', 'kurikulum');
        $adminLain = $this->user('admin_bukan_pembuat', 'superadmin');
        foreach ([$petugas, $adminLain, $f['siswa']->user] as $user) {
            $this->actingAs($user)->get($url)->assertForbidden()->assertDontSee($f['siswa']->nama);
        }
        $this->actingAs($adminLain)->get($detail)->assertOk()->assertDontSee($url);
        $this->actingAs($f['admin'])->get($detail)->assertOk()->assertSee($url);
        $response = $this->get($url)->assertOk()->assertSee('Kelas 7 A')
            ->assertSee($f['siswa']->nama)->assertDontSee($siswaLuar->nama)->assertDontSee('Komentar privat');
        $this->assertSame(1, $response->viewData('kelas')->first()->count());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('forum_audits', [
            'user_id' => $f['admin']->uuid, 'action' => 'survei_responden_dibuka', 'subject_id' => $periode->uuid,
        ]);
        $this->get(route('survei.analisis', $periode))->assertOk()->assertDontSee($f['siswa']->nama);
    }

    public function test_skala_tidak_tahu_dan_butir_negatif_dihitung_dengan_benar(): void
    {
        $this->assertSame([1 => 'Tidak pernah', 3 => 'Kadang-kadang', 5 => 'Selalu', 0 => 'Tidak tahu'], Pertanyaan::SKALA);
        $this->assertCount(10, Pertanyaan::untuk('mapel'));
        $this->assertCount(6, Pertanyaan::untuk('wali'));
        $this->assertNull(Pertanyaan::skor('mapel', 7, 0));
        $this->assertSame(5, Pertanyaan::skor('mapel', 7, 1));
        $this->assertSame(1, Pertanyaan::skor('mapel', 7, 5));
        $this->assertSame(4, Pertanyaan::skor('mapel', 2, 4));
    }

    public function test_rekap_nilai_dan_catatan_terpisah_per_guru_dengan_query_tetap(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $a = $this->target($p, $f);
        $kelasB = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $b = $this->target($p, [...$f, 'kelas' => $kelasB]);
        $b->update(['kelas_nama' => 'Kelas 7 B']);
        $kecil = $this->target($p, $f);
        $guruLain = Guru::create(['nama' => 'Guru Kedua']);
        $lain = $this->target($p, [...$f, 'guru' => $guruLain]);
        $lain->update(['guru_nama' => 'Guru Kedua']);
        $luar = $this->target($this->periode(), $f);
        foreach ([[$a, 5, 4, 1, 'Penjelasan jelas'], [$b, 10, 2, 5, 'Tambahkan contoh'],
            [$kecil, 4, 1, 5, 'CATATAN KELOMPOK KECIL'], [$lain, 5, 5, 1, 'CATATAN GURU KEDUA'],
            [$luar, 5, 5, 1, 'CATATAN PERIODE LAIN']] as [$target, $n, $positif, $negatif, $catatan]) {
            for ($i = 0; $i < $n; $i++) {
                $jawaban = array_fill(0, 10, 0);
                $jawaban[0] = $positif;
                $jawaban[7] = $negatif;
                $target->respons()->create([
                    'siswa_id' => 'identitas-rahasia-'.$i, 'jawaban' => $jawaban,
                    'komentar' => [$i === 1 ? mb_strtoupper(' '.$catatan.' ') : $catatan, null],
                    'kendala' => ['lainnya' => $target->is($a) ? 'Proyektor kurang jelas' : null],
                ]);
            }
        }
        // Butir dengan empat jawaban valid tetap dikeluarkan meskipun ada lima respons.
        $a->respons()->get()->each(function (Respons $row, int $i) {
            $j = $row->jawaban;
            $j[1] = $i < 4 ? 5 : 0;
            $row->update(['jawaban' => $j]);
        });
        DB::enableQueryLog();
        DB::flushQueryLog();
        $rekap = app(AnalisisGuru::class)->rekap($p, sertakanCatatan: true);
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(2, $queries);
        $this->assertStringNotContainsString('siswa_id', $queries->pluck('query')->implode(' '));
        $r = $rekap[$f['guru']->uuid]['peran']['mapel'];
        $this->assertSame(30, $r['jawaban_dinilai']);
        $this->assertEqualsWithDelta(2.5, $r['skor'], 0.00001);
        $this->assertEqualsWithDelta(50, $r['nilai'], 0.00001);
        $this->assertSame([0, 7], $r['butir']->pluck('index')->all());
        $this->assertSame(2, $r['kelas']);
        $this->assertSame(19, $r['isian']);
        $notes = $r['catatan']->first()['daftar'];
        $this->assertSame('Tambahkan contoh', $notes->first()['teks']);
        $this->assertSame(10, $notes->first()['jumlah']);
        $this->assertSame(15, $r['catatan']->first()['jumlah']);
        $this->assertSame('Proyektor kurang jelas', $r['catatan']->last()['daftar']->first()['teks']);
        $this->assertStringNotContainsString('CATATAN GURU KEDUA', $r['catatan']->toJson());
        $this->assertStringNotContainsString('CATATAN PERIODE LAIN', $r['catatan']->toJson());
        $this->assertStringNotContainsString('CATATAN KELOMPOK KECIL', $r['catatan']->toJson());
        $this->assertTrue(app(AnalisisGuru::class)->rekap($p)[$f['guru']->uuid]['peran']['mapel']['catatan']->isEmpty());
    }

    public function test_laporan_guru_menjaga_akses_catatan_dan_menghasilkan_pdf(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $p->update(['dibuat_oleh' => $f['admin']->uuid]);
        $target = $this->target($p, $f);
        $guruLain = Guru::create(['nama' => 'Guru Tanpa Data']);
        $kosong = $this->target($p, [...$f, 'guru' => $guruLain]);
        $kosong->update(['guru_nama' => 'Guru Tanpa Data']);
        $wali = $p->targets()->create([
            'kelas_id' => $f['kelas']->uuid, 'guru_id' => $f['guru']->uuid,
            'jenis' => 'wali', 'kelas_nama' => 'Kelas 7 A', 'guru_nama' => 'Guru Contoh',
        ]);
        for ($i = 0; $i < 5; $i++) {
            $jawaban = array_fill(0, 10, 4);
            foreach ([7, 8, 9] as $index) {
                $jawaban[$index] = 2;
            }
            $target->respons()->create([
                'siswa_id' => 'identitas-pdf-'.$i, 'jawaban' => $jawaban,
                'komentar' => ['Materi dijelaskan runtut', $i === 0 ? '<script>alert("uji")</script>' : 'Lebih banyak contoh latihan'],
            ]);
            $wali->respons()->create([
                'siswa_id' => 'identitas-pdf-'.$i, 'jawaban' => array_fill(0, 6, 5),
                'komentar' => ['Informasi kelas disampaikan tepat waktu'],
            ]);
        }
        $url = route('survei.laporan-guru', $p);
        $this->actingAs($f['admin'])->get($url)->assertForbidden();
        $p->update(['ditutup' => true]);
        foreach ([$f['siswa']->user, $f['guruUser'], $this->user('tanpa_izin_rekap', 'kurikulum')] as $u) {
            $this->actingAs($u)->get($url)->assertForbidden();
        }
        $this->actingAs($f['admin']);
        $page = $this->get(route('survei.analisis', $p))->assertOk()
            ->assertSee($url)->assertSee('Nilai 80,00 / 100')->assertSee('Nilai 100,00 / 100')
            ->assertSee('Materi dijelaskan runtut')->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(', false)->assertDontSee('identitas-pdf-');
        $gurus = $page->viewData('gurus');
        $html = view('survei.laporan-guru-pdf', [
            'periode' => $p, 'sekolah' => 'Sekolah Demo', 'gurus' => $gurus,
            'lihatCatatan' => true, 'minimum' => AnalisisGuru::MINIMUM,
        ])->render();
        $this->assertStringContainsString('Materi dijelaskan runtut', $html);
        $this->assertStringContainsString('Guru Tanpa Data', $html);
        $this->assertStringContainsString('Tidak diberikan nilai nol', $html);
        $this->assertStringContainsString('LAPORAN EVALUASI GURU', $html);
        $this->assertStringContainsString('Rencana tindak lanjut yang disepakati', $html);
        $this->assertStringContainsString('Refleksi dan hasil pemantauan', $html);
        $this->assertStringContainsString('Verifikasi hasil diskusi', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('identitas-pdf-', $html);
        $pdf = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('rekap-survei-per-guru-semester.pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));
        $this->assertDatabaseHas('forum_audits', ['action' => 'survei_laporan_guru_diekspor', 'subject_id' => $p->uuid]);
        if ($qaPath = getenv('SIMS_SURVEI_QA_PDF')) {
            file_put_contents($qaPath, $pdf->getContent());
        }
        RolePermission::create(['role' => 'kurikulum', 'permission' => 'view_survei']);
        $petugas = $this->user('waka_rekap', 'kurikulum');
        $this->actingAs($petugas)->get(route('survei.analisis', $p))->assertOk()
            ->assertSee($url)->assertSee('Nilai 80,00 / 100')->assertDontSee('Materi dijelaskan runtut')
            ->assertDontSee('Informasi kelas disampaikan tepat waktu');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->user('admin_bukan_pembuat_rekap', 'admin'))
            ->get(route('survei.analisis', $p))->assertOk()->assertDontSee('Materi dijelaskan runtut');
        $this->actingAs($f['guruUser'])->get(route('survei.analisis', $p))->assertOk()
            ->assertDontSee('Materi dijelaskan runtut')->assertDontSee($url)->assertDontSee('Guru Tanpa Data');
    }

    public function test_analisis_menggabungkan_mapel_per_guru_dan_memisahkan_peran_wali(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $mapel = $this->target($p, $f);
        $mapelLain = $this->target($p, $f);
        $mapelLain->update(['pelajaran_nama' => 'Informatika']);
        $wali = $p->targets()->create([
            'kelas_id' => $f['kelas']->uuid, 'guru_id' => $f['guru']->uuid,
            'jenis' => 'wali', 'kelas_nama' => 'Kelas 7 A', 'guru_nama' => 'Guru Contoh',
        ]);
        $kecil = $this->target($p, $f);

        foreach ([[$mapel, 5, '4'], [$mapelLain, 10, '5'], [$kecil, 4, '1']] as [$target, $count, $rating]) {
            for ($i = 0; $i < $count; $i++) {
                $jawaban = array_fill(0, 10, '0');
                $jawaban[0] = $rating;
                $jawaban[7] = '5';
                $target->respons()->create(['siswa_id' => 'identitas-'.$i, 'jawaban' => $jawaban, 'komentar' => ['rahasia-pribadi']]);
            }
        }
        for ($i = 0; $i < 5; $i++) {
            $wali->respons()->create(['siswa_id' => 'identitas-'.$i, 'jawaban' => array_fill(0, 6, '4'), 'komentar' => []]);
        }

        $url = route('survei.analisis', $p);
        $this->actingAs($f['admin'])->get($url)->assertForbidden();
        $p->update(['ditutup' => true]);
        $this->actingAs($f['siswa']->user)->get($url)->assertForbidden();
        $guruLain = Guru::create(['nama' => 'Guru Lain']);
        $sasaranLain = $this->target($p, [...$f, 'guru' => $guruLain]);
        $sasaranLain->update(['guru_nama' => $guruLain->nama, 'pelajaran_nama' => 'Mapel Guru Lain']);
        $periodeLain = $this->periode();
        $periodeLain->update(['judul' => 'Periode guru lain', 'ditutup' => true]);
        $this->target($periodeLain, [...$f, 'guru' => $guruLain]);
        $this->actingAs($f['guruUser'])->get(route('survei.index'))->assertOk()
            ->assertSee('Lihat rekap saya')->assertDontSee('Periode guru lain');
        $this->get($url)->assertOk()->assertSee('Rekap Survei Saya')->assertSee('Skor 2,83 / 5')
            ->assertDontSee('Guru Lain')->assertDontSee('Unduh PDF')->assertDontSee('rahasia-pribadi');
        $this->get(route('survei.hasil', $sasaranLain))->assertForbidden();
        $this->get(route('survei.analisis', $periodeLain))->assertForbidden();
        $petugas = $this->user('analisis_kurikulum', 'kurikulum');
        RolePermission::create(['role' => 'kurikulum', 'permission' => 'view_survei']);
        $response = $this->actingAs($petugas)->get($url)->assertOk()
            ->assertSee('Kekuatan')->assertSee('Area perbaikan')->assertSee('Skor 2,83 / 5')
            ->assertSee('Skor 4,00 / 5')->assertDontSee('rahasia-pribadi')->assertDontSee('identitas-0');
        $peran = $response->viewData('gurus')[$f['guru']->uuid]['peran'];
        $this->assertSame(30, $peran['mapel']['jawaban_dinilai']);
        $this->assertSame(1, $peran['mapel']['perbaikan']->first()['rata']);
        $this->assertEqualsWithDelta(14 / 3, $peran['mapel']['kekuatan']->first()['rata'], 0.0001);
        $this->assertSame(30, $peran['wali']['jawaban_dinilai']);
    }

    public function test_laporan_pdf_menghitung_hanya_jawaban_dari_kelas_terpilih(): void
    {
        $f = $this->fixture();
        $periode = $this->periode();
        $mapel = $this->target($periode, $f);
        $kelasLain = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $mapelLain = $this->target($periode, [...$f, 'kelas' => $kelasLain]);
        $mapelLain->update(['kelas_nama' => 'Kelas 7 B']);

        foreach ([[$mapel, 5], [$mapelLain, 1]] as [$target, $nilai]) {
            for ($i = 0; $i < 5; $i++) {
                $jawaban = array_fill(0, 10, 0);
                $jawaban[0] = $nilai;
                $target->respons()->create(['siswa_id' => 'responden-'.$i, 'jawaban' => $jawaban, 'komentar' => ['Komentar pribadi']]);
            }
        }

        $analisis = app(AnalisisGuru::class);
        $hasil = $analisis->rekap($periode, $f['kelas']->uuid);
        $ringkasan = $hasil[$f['guru']->uuid]['peran']['mapel'];
        $this->assertSame([$mapel->uuid], $ringkasan['targets']->pluck('uuid')->all());
        $this->assertSame(['jawaban_dinilai' => 5, 'skor' => 5], $analisis->skorKelas($hasil)['mapel']);
        $this->assertNull($analisis->skorKelas($hasil)['wali']['skor']);

        $url = route('survei.laporan-kelas', [$periode, $f['kelas']->uuid]);
        $this->actingAs($f['admin'])->get($url)->assertForbidden();
        $periode->update(['ditutup' => true]);
        $this->actingAs($f['siswa']->user)->get($url)->assertForbidden();
        $this->actingAs($f['guruUser'])->get($url)->assertForbidden();
        $this->actingAs($f['admin'])->get(route('survei.laporan-kelas', [$periode, fake()->uuid()]))->assertNotFound();
        $this->get(route('survei.analisis', $periode))->assertOk()->assertSee($url);

        $petugas = $this->user('laporan_kurikulum', 'kurikulum');
        RolePermission::create(['role' => 'kurikulum', 'permission' => 'view_survei']);
        $pdf = $this->actingAs($petugas)->get($url)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('laporan-survei-kelas-7-a.pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));
    }

    public function test_admin_menunjuk_surveier_lewat_rbac_dan_surveier_memandu_tanpa_melihat_identitas(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $p->update(['dibuat_oleh' => $f['admin']->uuid, 'wajib_token' => true]);
        $this->target($p, $f);
        $kurikulum = $this->user('petugas_survei', 'kurikulum');
        $guruBukan = $this->user('guru_bukan_surveier', 'guru');
        $detail = route('survei.periode', $p);
        $mulai = route('survei.sesi.mulai', $p);
        $pilih = ['kelas_sesi' => [$f['kelas']->uuid]];

        $this->actingAs($kurikulum)->get(route('survei.index'))->assertForbidden();
        $this->post($mulai, $pilih)->assertForbidden();
        $this->get($detail)->assertForbidden();
        $this->actingAs($guruBukan)->get($detail)->assertForbidden();

        $this->actingAs($f['admin'])->get(route('setting.roles'))->assertOk()->assertSee('Memandu Sesi Survei (Surveyor)');
        $this->post(route('setting.roles.save'), [
            'perms' => [
                'kurikulum' => ['jadi_surveyor' => '1'],
                'siswa' => ['jadi_surveyor' => '1', 'view_survei' => '1'],
                'orangtua' => ['jadi_surveyor' => '1'],
            ],
        ])->assertRedirect();
        $this->assertTrue(RolePermission::granted('kurikulum', 'jadi_surveyor'));
        $this->assertFalse(RolePermission::granted('siswa', 'jadi_surveyor'));
        $this->assertFalse(RolePermission::granted('orangtua', 'jadi_surveyor'));
        $this->assertFalse(RolePermission::granted('siswa', 'view_survei'));

        RolePermission::create(['role' => 'siswa', 'permission' => 'jadi_surveyor']);
        $this->actingAs($f['siswa']->user)->get(route('survei.index'))->assertOk()->assertDontSee('Papan Surveyor');

        $this->actingAs($kurikulum)->get(route('survei.index'))->assertOk()
            ->assertSee('Papan Surveyor')
            ->assertSee('Dari surveyor ke siswa')
            ->assertSee($p->judul);
        $this->get($detail)->assertOk()
            ->assertSee('Mulai sesi')
            ->assertSee(route('survei.masuk', $p))
            ->assertDontSee('Tutup periode')
            ->assertDontSee('Wajibkan Safe Exam Browser')
            ->assertDontSee('Wajibkan SIMS Kios');
        $this->post($mulai, $pilih)->assertRedirect($detail);
        $token = $p->sesiAktif()->token;
        $this->get($detail)->assertOk()->assertSee($token)->assertSee('Tampilkan token');
        $this->post(route('survei.tutup', $p))->assertForbidden();
        $this->get(route('survei.responden', $p))->assertForbidden();
        $this->post(route('survei.seb.wajib', $p))->assertForbidden();

        $this->actingAs($f['siswa']->user)->post(route('survei.token', $p), ['token' => $token])
            ->assertRedirect(route('survei.form', $p));
        $this->get(route('survei.form', $p))->assertOk()->assertSee('Matematika');

        $this->actingAs($f['guruUser'])->get(route('survei.index'))->assertOk()->assertDontSee('Papan Surveyor');
        $this->post($mulai, $pilih)->assertForbidden();
    }

    public function test_rute_survei_bebas_gate_wajah_tetapi_wajib_ganti_password(): void
    {
        $f = $this->fixture();
        Setting::set('wajib_daftar_wajah', '1');
        $siswa = $f['siswa']->user;

        // Gate wajah tetap aktif di luar survei, tetapi survei tidak terhambat biometrik.
        $this->actingAs($siswa)->get('/dashboard')->assertRedirect(route('face.self'));
        $this->get(route('survei.index'))->assertOk();

        // Wajib ganti password tetap berlaku di survei, termasuk orang tua yang bebas gate wajah.
        $siswa->update(['must_change_password' => true]);
        $this->actingAs($siswa)->get(route('survei.index'))->assertRedirect(route('ganti.password'));

        $ortu = User::create(['username' => 'ortu_survei', 'password' => Hash::make('password'), 'access' => 'orangtua', 'must_change_password' => true]);
        $this->actingAs($ortu)->get(route('survei.index'))->assertRedirect(route('ganti.password'));
    }

    public function test_tombol_tutup_periode_hanya_untuk_yang_berhak_menutup(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $p->update(['dibuat_oleh' => $f['admin']->uuid, 'wajib_token' => false]);
        $this->target($p, $f);
        $humas = $this->user('humas_tutup', 'humas');
        $detail = route('survei.periode', $p);

        $this->actingAs($humas)->get($detail)->assertOk()->assertDontSee('Tutup periode survei');
        $this->post(route('survei.tutup', $p))->assertForbidden();

        $this->actingAs($f['admin'])->get($detail)->assertOk()->assertSee('Tutup periode survei');
    }

    public function test_waka_humas_dan_sarpras_ditunjuk_sebagai_surveier(): void
    {
        $f = $this->fixture();
        $p = $this->periode();
        $p->update(['dibuat_oleh' => $f['admin']->uuid, 'wajib_token' => true]);
        $this->target($p, $f);
        $humas = $this->user('waka_humas_survei', 'humas');
        $sarpras = $this->user('waka_sarpras_survei', 'sarpras');

        $this->assertTrue(RolePermission::granted('humas', 'jadi_surveyor'));
        $this->assertTrue(RolePermission::granted('sarpras', 'jadi_surveyor'));
        $this->assertFalse(RolePermission::granted('guru', 'jadi_surveyor'));

        $this->actingAs($f['admin'])->get(route('setting.roles'))->assertOk()
            ->assertSee('Waka Humas')
            ->assertSee('Waka Sarpras')
            ->assertSee('name="perms[humas][jadi_surveyor]"', false)
            ->assertSee('name="perms[sarpras][jadi_surveyor]"', false);
        $this->actingAs($f['admin'])->get(route('guru.edit', $f['guru']))->assertOk()->assertSee('Waka Humas');

        $this->actingAs($humas)->get(route('survei.index'))->assertOk()
            ->assertSee('Papan Surveyor')
            ->assertSee($p->judul);
        $this->actingAs($sarpras)->get(route('survei.periode', $p))->assertOk()
            ->assertSee('Mulai sesi')
            ->assertDontSee('Tutup periode');
        $this->post(route('survei.sesi.mulai', $p), ['kelas_sesi' => [$f['kelas']->uuid]])
            ->assertRedirect(route('survei.periode', $p));
        $this->actingAs($f['guruUser'])->get(route('survei.periode', $p))->assertForbidden();
    }
}
