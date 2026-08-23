<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Materi;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 2 modul Ujian: alur penyusunan (guru/admin) via HTTP — buat ujian, susun
 * soal 5 tipe, tetapkan kelas, terbitkan. Akses diuji lewat UjianPolicy nyata
 * (bukan mock), face_descriptor diisi supaya guru lolos gate EnsureFaceRegistered.
 */
class UjianAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;
    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
    }

    private function buatGuru(string $username): array
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $user->uuid, 'nama' => ucfirst($username), 'nik' => (string) random_int(1000000000, 9999999999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        return [$user, $guru];
    }

    private function ngajarMilik(Guru $guru): Ngajar
    {
        return Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
    }

    public function test_guru_submit_jenis_atau_target_nilai_selain_harian_sumatif_dipaksa_server(): void
    {
        [$user, $guru] = $this->buatGuru('guru_ujian1');
        $ngajar = $this->ngajarMilik($guru);
        $materi = Materi::create(['id_ngajar' => $ngajar->uuid, 'nama' => 'Bab 1 Bilangan', 'urutan' => 1]);

        // Guru biasa tak lagi boleh bikin ujian formal PTS/PAS/UAS lepas — itu sekarang
        // eksklusif lewat Paket Ujian (admin/pengelola). Kalaupun klien (form lama/nakal)
        // tetap kirim jenis=pts & target_nilai=pts, server HARUS memaksanya jadi
        // harian/sumatif, bukan cuma mengandalkan form yg sudah dikunci di sisi UI.
        $res = $this->actingAs($user)->post(route('ujian.store'), [
            'judul' => 'PTS Ganjil Matematika', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => [$this->kelas->uuid],
            'id_materi' => $materi->uuid, 'durasi_menit' => 90,
        ]);

        $res->assertRedirect();
        $ujian = Ujian::where('judul', 'PTS Ganjil Matematika')->first();
        $this->assertNotNull($ujian);
        $this->assertSame($user->uuid, $ujian->created_by);
        $this->assertSame('harian', $ujian->jenis);
        $this->assertSame('sumatif', $ujian->target_nilai);
    }

    public function test_admin_membuat_ujian_pts_langsung_menetapkan_kelas(): void
    {
        $admin = User::create(['username' => 'admin_ujian1', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        // kelasPilihan() memvalidasi kelas via data Ngajar (lintas guru manapun) — sediakan
        // satu supaya kelas ini dianggap "beneran diajar" mapel tsb.
        [, $guruSiapapun] = $this->buatGuru('guru_ngajar_utk_admin_test');
        $this->ngajarMilik($guruSiapapun);

        $res = $this->actingAs($admin)->post(route('ujian.store'), [
            'judul' => 'PTS Admin', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => [$this->kelas->uuid], 'durasi_menit' => 90,
        ]);
        $res->assertRedirect();

        $ujian = Ujian::where('judul', 'PTS Admin')->firstOrFail();
        $this->assertDatabaseHas('ujian_kelas', ['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid]);
    }

    public function test_guru_tanpa_id_materi_ditolak_membuat_ujian(): void
    {
        [$user] = $this->buatGuru('guru_ujian2'); // sengaja TANPA Ngajar/Materi

        $this->actingAs($user)->post(route('ujian.store'), [
            'judul' => 'PTS Nekat', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => [$this->kelas->uuid], 'durasi_menit' => 90,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('ujians', ['judul' => 'PTS Nekat']);
    }

    public function test_guru_pakai_materi_milik_guru_lain_ditolak_membuat_ujian(): void
    {
        [$user] = $this->buatGuru('guru_ujian2b');
        [, $guruLain] = $this->buatGuru('guru_ujian2b_lain');
        $ngajarLain = $this->ngajarMilik($guruLain);
        $materiLain = Materi::create(['id_ngajar' => $ngajarLain->uuid, 'nama' => 'Bab 1', 'urutan' => 1]);

        $this->actingAs($user)->post(route('ujian.store'), [
            'judul' => 'Ulangan Nekat', 'jenis' => 'harian', 'target_nilai' => 'sumatif',
            'id_materi' => $materiLain->uuid, 'durasi_menit' => 90,
        ])->assertForbidden();

        $this->assertDatabaseMissing('ujians', ['judul' => 'Ulangan Nekat']);
    }

    public function test_ujian_sumatif_menurunkan_id_pelajaran_dari_materi_dan_hanya_boleh_satu_kelas(): void
    {
        [$user, $guru] = $this->buatGuru('guru_ujian3');
        $ngajar = $this->ngajarMilik($guru);
        $materi = Materi::create(['id_ngajar' => $ngajar->uuid, 'nama' => 'Bab 1 Bilangan', 'urutan' => 1]);

        $res = $this->actingAs($user)->post(route('ujian.store'), [
            'judul' => 'Ulangan Harian Bab 1', 'jenis' => 'harian', 'target_nilai' => 'sumatif',
            'id_materi' => $materi->uuid, 'durasi_menit' => 40,
        ]);
        $res->assertRedirect();

        $ujian = Ujian::where('judul', 'Ulangan Harian Bab 1')->firstOrFail();
        $this->assertSame($this->pelajaran->uuid, $ujian->id_pelajaran, 'id_pelajaran harus otomatis ikut mapel dari Ngajar pemilik Materi.');
        // store() kini otomatis menetapkan kelas TUNGGAL dari Materi->Ngajar sekaligus (tak perlu
        // langkah syncKelas() terpisah lagi utk kasus sumatif — kelasnya sudah pasti tunggal).
        $this->assertSame(1, $ujian->kelas()->count());
        $this->assertDatabaseHas('ujian_kelas', ['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid]);

        // Penetapan kelas (syncKelas) eksklusif admin/pengelola — guru pengampu bukan lagi
        // aktor yg tepat utk cek ini, jadi dites lewat admin.
        $admin = User::create(['username' => 'admin_ujian_sumatif', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $kelasLain = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $this->actingAs($admin)->post(route('ujian.kelas.sync', $ujian), [
            'id_kelas' => [$this->kelas->uuid, $kelasLain->uuid],
        ])->assertSessionHasErrors('id_kelas');

        $this->assertSame(1, $ujian->kelas()->count(), 'Kelas tetap 1 (yg sudah ada dari awal) krn percobaan assign 2 kelas ditolak.');
    }

    public function test_publish_gagal_tanpa_soal_atau_tanpa_kelas(): void
    {
        [$user, $guru] = $this->buatGuru('guru_ujian4');
        $this->ngajarMilik($guru);

        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $user->uuid,
            'judul' => 'PTS Kosong', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90,
        ]);

        $this->actingAs($user)->post(route('ujian.publish', $ujian))
            ->assertSessionHas('error');
        $this->assertSame('draft', $ujian->fresh()->status);

        $this->actingAs($user)->post(route('ujian.soal.store', $ujian), [
            'tipe' => 'mcq', 'teks_soal' => '2+2=?', 'poin' => 10,
            'opsi' => [['teks' => '4', 'benar' => '1'], ['teks' => '5', 'benar' => '']],
        ])->assertRedirect();

        // Masih tanpa kelas -> tetap gagal.
        $this->actingAs($user)->post(route('ujian.publish', $ujian))->assertSessionHas('error');
        $this->assertSame('draft', $ujian->fresh()->status);

        // Penetapan kelas eksklusif admin/pengelola — guru tak bisa lagi syncKelas sendiri.
        $admin = User::create(['username' => 'admin_ujian4', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $this->actingAs($admin)->post(route('ujian.kelas.sync', $ujian), ['id_kelas' => [$this->kelas->uuid]])->assertRedirect();
        $this->actingAs($user)->post(route('ujian.publish', $ujian))->assertSessionHas('success');
        $this->assertSame('published', $ujian->fresh()->status);

        $ujianKelas = $ujian->kelas()->first();
        $this->assertNotEmpty($ujianKelas->token_masuk, 'Token masuk harus tergenerate saat kelas ditetapkan.');
    }

    public function test_mcq_wajib_tepat_satu_opsi_benar(): void
    {
        [$user, $guru] = $this->buatGuru('guru_ujian5');
        $this->ngajarMilik($guru);
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $user->uuid,
            'judul' => 'PTS Validasi', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90,
        ]);

        // Tanpa opsi benar sama sekali.
        $this->actingAs($user)->post(route('ujian.soal.store', $ujian), [
            'tipe' => 'mcq', 'teks_soal' => 'Soal tanpa jawaban benar', 'poin' => 10,
            'opsi' => [['teks' => 'A', 'benar' => ''], ['teks' => 'B', 'benar' => '']],
        ])->assertStatus(422);
        $this->assertDatabaseMissing('ujian_soal', ['teks_soal' => 'Soal tanpa jawaban benar']);
    }

    public function test_soal_mencocokkan_tersimpan_di_meta_pairs(): void
    {
        [$user, $guru] = $this->buatGuru('guru_ujian6');
        $this->ngajarMilik($guru);
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $user->uuid,
            'judul' => 'PTS Match', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90,
        ]);

        $this->actingAs($user)->post(route('ujian.soal.store', $ujian), [
            'tipe' => 'match', 'teks_soal' => 'Cocokkan ibukota', 'poin' => 10,
            'pasangan' => [['kiri' => 'Riau', 'kanan' => 'Pekanbaru'], ['kiri' => 'DKI', 'kanan' => 'Jakarta']],
        ])->assertRedirect();

        $soal = \App\Models\UjianSoal::where('id_ujian', $ujian->uuid)->firstOrFail();
        $this->assertSame('match', $soal->tipe);
        $this->assertSame([['left' => 'Riau', 'right' => 'Pekanbaru'], ['left' => 'DKI', 'right' => 'Jakarta']], $soal->meta['pairs']);
    }

    public function test_pasangan_mencocokkan_pakai_tinymce_dibersihkan_dari_script(): void
    {
        [$user, $guru] = $this->buatGuru('guru_ujian_match_html');
        $this->ngajarMilik($guru);
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $user->uuid,
            'judul' => 'PTS Match Rumus', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90,
        ]);

        $this->actingAs($user)->post(route('ujian.soal.store', $ujian), [
            'tipe' => 'match', 'teks_soal' => 'Cocokkan rumus', 'poin' => 10,
            'pasangan' => [
                ['kiri' => '<p>2+2</p><script>alert(1)</script>', 'kanan' => '<p>4</p>'],
                ['kiri' => '<p>3+3</p>', 'kanan' => '<p>6</p>'],
            ],
        ])->assertRedirect();

        $soal = \App\Models\UjianSoal::where('id_ujian', $ujian->uuid)->firstOrFail();
        $this->assertSame('<p>2+2</p>', $soal->meta['pairs'][0]['left']);
        $this->assertSame('<p>4</p>', $soal->meta['pairs'][0]['right']);
    }

    public function test_soal_benar_salah_tersimpan_dengan_opsi_benar_yg_dipilih(): void
    {
        [$user, $guru] = $this->buatGuru('guru_ujian_tf');
        $this->ngajarMilik($guru);
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $user->uuid,
            'judul' => 'PTS Benar Salah', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90,
        ]);

        $this->actingAs($user)->post(route('ujian.soal.store', $ujian), [
            'tipe' => 'true_false', 'teks_soal' => 'Ibu kota Indonesia adalah Bandung.', 'poin' => 10,
            'opsi' => [['teks' => 'Benar', 'benar' => ''], ['teks' => 'Salah', 'benar' => '1']],
        ])->assertRedirect();

        $soal = \App\Models\UjianSoal::where('id_ujian', $ujian->uuid)->with('opsi')->firstOrFail();
        $this->assertSame('true_false', $soal->tipe);
        $this->assertSame('Benar', $soal->opsi[0]->teks_opsi);
        $this->assertFalse((bool) $soal->opsi[0]->is_benar);
        $this->assertSame('Salah', $soal->opsi[1]->teks_opsi);
        $this->assertTrue((bool) $soal->opsi[1]->is_benar);
    }

    public function test_soal_tidak_bisa_diubah_setelah_ujian_terbit(): void
    {
        [$user, $guru] = $this->buatGuru('guru_ujian7');
        $this->ngajarMilik($guru);
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $user->uuid,
            'judul' => 'PTS Terkunci', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90, 'status' => 'published',
        ]);
        UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => 'ABC123']);

        $this->actingAs($user)->post(route('ujian.soal.store', $ujian), [
            'tipe' => 'essay', 'teks_soal' => 'Coba tambah setelah terbit', 'poin' => 10,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('ujian_soal', ['teks_soal' => 'Coba tambah setelah terbit']);
    }

    /**
     * Sejak token PTS/PAS dibagi rata per tingkat (bukan per kelas), satu ujian sering
     * mencakup BEBERAPA kelas satu tingkat yg diampu guru BERBEDA-BEDA. UjianPolicy::manage()
     * harus mengizinkan SEMUA guru yg mengajar mapel ini di SALAH SATU kelas yg ditetapkan,
     * bukan cuma pembuat ujian — supaya mereka bisa kolaborasi mengisi bank soal bersama.
     */
    public function test_guru_lain_yg_mengajar_kelas_lain_di_tingkat_yang_sama_bisa_kelola_soal(): void
    {
        [$pembuat, $guruPembuat] = $this->buatGuru('guru_kolab1');
        $this->ngajarMilik($guruPembuat); // mengajar $this->kelas (7A)

        $kelasB = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        [$guruLain, $guruLainModel] = $this->buatGuru('guru_kolab2');
        Ngajar::create(['id_guru' => $guruLainModel->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $kelasB->uuid]);

        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $pembuat->uuid,
            'judul' => 'PTS Kolaborasi', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90,
        ]);
        // Kelas 7A & 7B (tingkat sama) sama-sama ditetapkan ke ujian ini, tapi masing-masing
        // diampu guru BERBEDA — hanya guruPembuat yg mengajar 7A, hanya guruLain yg mengajar 7B.
        // Penetapan kelas eksklusif admin/pengelola (bukan pembuat ujian) — dites lewat admin.
        $admin = User::create(['username' => 'admin_kolab', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $this->actingAs($admin)->post(route('ujian.kelas.sync', $ujian), [
            'id_kelas' => [$this->kelas->uuid, $kelasB->uuid],
        ])->assertRedirect();

        // guruLain TIDAK membuat ujian ini & TIDAK mengajar 7A — tapi karena 7B (kelasnya)
        // termasuk yg ditetapkan, dia tetap harus lolos kelola soal.
        $this->actingAs($guruLain)->post(route('ujian.soal.store', $ujian), [
            'tipe' => 'essay', 'teks_soal' => 'Soal dari guru kedua', 'poin' => 10,
        ])->assertRedirect();

        $this->assertDatabaseHas('ujian_soal', ['id_ujian' => $ujian->uuid, 'teks_soal' => 'Soal dari guru kedua']);

        // Guru yg TIDAK mengajar mapel ini di kelas manapun yg ditetapkan tetap ditolak.
        [$guruTakTerkait] = $this->buatGuru('guru_kolab3');
        $this->actingAs($guruTakTerkait)->post(route('ujian.soal.store', $ujian), [
            'tipe' => 'essay', 'teks_soal' => 'Nekat dari guru tak terkait', 'poin' => 10,
        ])->assertForbidden();
    }

    /**
     * Regresi: form asli browser SELALU ikut mengirim field pasangan[]/opsi[] milik tipe
     * soal LAIN yg sedang disembunyikan lewat x-show (bukan dihapus dari DOM) — kalau
     * validasinya pakai required_with, field "ada tapi kosong" itu bikin submit APA PUN
     * tipe soalnya (mcq, essay, dst) selalu gagal 422. Simulasikan persis apa yg browser
     * kirim: array pasangan kosong ikut nampil di payload walau tipe=mcq.
     */
    public function test_submit_mcq_tak_gagal_krn_field_pasangan_kosong_ikut_terkirim(): void
    {
        [$user, $guru] = $this->buatGuru('guru_ujian8');
        $this->ngajarMilik($guru);
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $user->uuid,
            'judul' => 'PTS Field Tersembunyi', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90,
        ]);

        $this->actingAs($user)->post(route('ujian.soal.store', $ujian), [
            'tipe' => 'mcq', 'teks_soal' => 'Berapa 5+3?', 'poin' => 10,
            'opsi' => [['teks' => '8', 'benar' => '1'], ['teks' => '7', 'benar' => '']],
            'pasangan' => [['kiri' => '', 'kanan' => ''], ['kiri' => '', 'kanan' => '']],
            'kunci_esai' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('ujian_soal', ['id_ujian' => $ujian->uuid, 'tipe' => 'mcq']);
    }
}
