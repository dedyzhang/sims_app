<?php

namespace Tests\Feature;

use App\Models\GrupChat;
use App\Models\GrupChatMember;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Orangtua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Walikelas;
use App\Services\GrupChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MembangunSekolahGrupChat;
use Tests\TestCase;

/**
 * Sinkronisasi keanggotaan. Ini penjaga utama terhadap kelas bug paling berbahaya
 * di modul ini: ex-anggota yang terus membaca grup lamanya.
 */
class GrupChatSinkronTest extends TestCase
{
    use RefreshDatabase, MembangunSekolahGrupChat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bangunSekolah();
    }

    private function anggotaAktif(GrupChat $grup): array
    {
        return GrupChatMember::where('grup_id', $grup->uuid)
            ->whereNull('left_at')
            ->pluck('user_id')
            ->all();
    }

    public function test_provision_membuat_dua_grup_per_kelas(): void
    {
        $this->assertSame(4, GrupChat::count());   // 2 kelas x 2 tipe

        $grup = $this->grupKelas($this->kelas7a);
        $this->assertSame('Grup Kelas 7 A', $grup->nama);
        $this->assertSame('2025/2026', $grup->tahun_ajaran);
        $this->assertSame(GrupChat::MODE_PENGUMUMAN, $grup->mode);

        $paguyuban = $this->grupPaguyuban($this->kelas7a);
        $this->assertSame(GrupChat::MODE_DISKUSI, $paguyuban->mode);
    }

    /**
     * Isolasi dari syncKelas(): setiap test lain memanggil provisionKelas() lewat
     * syncKelas(), yang langsung menjalankan self-heal mode sesudahnya -- itu bisa
     * menutupi create-array provisionKelas() sendiri kalau ternyata salah. Di sini
     * provisionKelas() dipanggil SENDIRIAN pada kelas baru, tanpa syncKelas() sama
     * sekali, supaya nilai mode yang diperiksa murni dari create-array-nya.
     */
    public function test_provision_kelas_menyetel_mode_tanpa_bantuan_sync_kelas(): void
    {
        $kelasBaru = Kelas::create(['tingkat' => 9, 'kelas' => 'Y']);

        [$grupKelas, $grupPaguyuban] = app(GrupChatService::class)->provisionKelas($kelasBaru);

        $this->assertSame(GrupChat::MODE_PENGUMUMAN, $grupKelas->mode);
        $this->assertSame(GrupChat::MODE_DISKUSI, $grupPaguyuban->mode);
    }

    public function test_command_sinkron_idempoten(): void
    {
        $this->artisan('grupchat:sinkron')->assertSuccessful();
        $sesudahSekali = GrupChatMember::whereNull('left_at')->count();

        $this->artisan('grupchat:sinkron')->assertSuccessful();

        $this->assertSame($sesudahSekali, GrupChatMember::whereNull('left_at')->count());
        $this->assertSame(4, GrupChat::count());
    }

    public function test_command_sinkron_gagal_tanpa_semester_aktif_dan_tanpa_tahun(): void
    {
        // Mass-update via query builder tak memicu event model — wajib clearCache() manual
        // supaya Semester::aktif() tak baca memo lama (pola sama SettingController::updateSemester).
        \App\Models\Semester::query()->update(['aktif' => false]);
        \App\Models\Semester::clearCache();

        $this->artisan('grupchat:sinkron')->assertFailed();
        $this->artisan('grupchat:sinkron', ['--tahun' => '2026/2027'])->assertSuccessful();
    }

    public function test_siswa_pindah_kelas_kehilangan_grup_lama_dan_dapat_grup_baru(): void
    {
        $lama = $this->grupKelas($this->kelas7a);
        $baru = $this->grupKelas($this->kelas7b);

        $this->assertContains($this->siswa7a->uuid, $this->anggotaAktif($lama));

        $this->actingAs($this->admin)->put(route('siswa.update', $this->profilSiswa7a->uuid), [
            'nama' => $this->profilSiswa7a->nama,
            'jk' => 'L',
            'id_kelas' => $this->kelas7b->uuid,
        ])->assertRedirect();

        $this->assertNotContains($this->siswa7a->uuid, $this->anggotaAktif($lama));
        $this->assertContains($this->siswa7a->uuid, $this->anggotaAktif($baru));

        $this->actingAs($this->siswa7a)->get(route('grup.show', $lama))->assertForbidden();
        $this->actingAs($this->siswa7a)->get(route('grup.show', $baru))->assertOk();
    }

    public function test_ortu_ikut_pindah_saat_anaknya_pindah(): void
    {
        $lama = $this->grupPaguyuban($this->kelas7a);
        $baru = $this->grupPaguyuban($this->kelas7b);

        $this->profilSiswa7a->update(['id_kelas' => $this->kelas7b->uuid]);
        app(GrupChatService::class)->syncSiswa($this->profilSiswa7a->fresh());

        $this->assertNotContains($this->ortu7a->uuid, $this->anggotaAktif($lama));
        $this->assertContains($this->ortu7a->uuid, $this->anggotaAktif($baru));
    }

    public function test_ortu_multi_anak_tetap_di_paguyuban_selama_satu_anak_bertahan(): void
    {
        // Kasus yang mudah salah: menghapus keanggotaan ortu berdasarkan id_siswa
        // akan mengeluarkan ortu dari paguyuban padahal anak keduanya masih di sana.
        [, $adik] = $this->buatSiswa($this->kelas7a, 'siswa_adik', 'Adik', 'NISADK');
        Orangtua::where('id_siswa', $adik->uuid)->delete();
        Orangtua::create(['id_siswa' => $adik->uuid, 'id_login' => $this->ortu7a->uuid]);
        app(GrupChatService::class)->syncOrangtuaUser($this->ortu7a->uuid);

        $paguyuban7a = $this->grupPaguyuban($this->kelas7a);
        $this->assertContains($this->ortu7a->uuid, $this->anggotaAktif($paguyuban7a));

        // Anak pertama pindah ke 7B, adik tetap di 7A.
        $this->profilSiswa7a->update(['id_kelas' => $this->kelas7b->uuid]);
        app(GrupChatService::class)->syncSiswa($this->profilSiswa7a->fresh());

        $this->assertContains($this->ortu7a->uuid, $this->anggotaAktif($paguyuban7a));
        $this->assertContains($this->ortu7a->uuid, $this->anggotaAktif($this->grupPaguyuban($this->kelas7b)));
    }

    public function test_siswa_lulus_kehilangan_semua_grup(): void
    {
        // luluskan() hanya menyentuh kelas tingkat 9 — fixture bawaan (kelas7a/b)
        // sengaja tidak dipakai di sini supaya test ini benar-benar lewat
        // Kelas::where('tingkat', 9) di controller, bukan jalur service langsung.
        $kelas9 = Kelas::create(['tingkat' => 9, 'kelas' => 'A']);
        [$siswa9, $profilSiswa9, $ortu9] = $this->buatSiswa($kelas9, 'siswa_9a', 'Dedi', 'NIS9A');
        app(GrupChatService::class)->syncKelas($kelas9);

        $grupKelas9 = $this->grupKelas($kelas9);
        $grupPaguyuban9 = $this->grupPaguyuban($kelas9);
        $this->assertContains($siswa9->uuid, $this->anggotaAktif($grupKelas9));
        $this->assertContains($ortu9->uuid, $this->anggotaAktif($grupPaguyuban9));

        $this->actingAs($this->admin)->post(route('alumni.luluskan'), [
            'tahun_lulus' => '2026',
            'angkatan' => '2026',
        ])->assertRedirect();

        $this->assertSame('lulus', $profilSiswa9->fresh()->status);
        $this->assertNotContains($siswa9->uuid, $this->anggotaAktif($grupKelas9));
        $this->assertNotContains($ortu9->uuid, $this->anggotaAktif($grupPaguyuban9));

        // Kelas 7 di fixture bawaan tidak ikut tersentuh oleh luluskan().
        $this->assertContains($this->siswa7a->uuid, $this->anggotaAktif($this->grupKelas($this->kelas7a)));
    }

    public function test_walikelas_baru_menggantikan_yang_lama(): void
    {
        $guruBaru = Guru::create([
            'id_login' => \App\Models\User::create([
                'username' => 'wali_pengganti',
                'password' => bcrypt('password'),
                'access' => 'guru',
            ])->uuid,
            'nama' => 'Bu Cici',
            'nik' => '3200009999',
            'jk' => 'P',
            'face_descriptor' => [0.7, 0.8],
        ]);

        $this->actingAs($this->admin)
            ->post(route('kelas.walikelas', $this->kelas7a->uuid), ['id_guru' => $guruBaru->uuid])
            ->assertRedirect();

        $grup = $this->grupKelas($this->kelas7a);
        $aktif = $this->anggotaAktif($grup);

        $this->assertContains($guruBaru->id_login, $aktif);
        $this->assertNotContains($this->wali7a->uuid, $aktif);
        $this->assertSame('walikelas', GrupChatMember::where('grup_id', $grup->uuid)
            ->where('user_id', $guruBaru->id_login)->value('peran'));
    }

    public function test_siswa_baru_langsung_jadi_anggota_kedua_grup(): void
    {
        $this->actingAs($this->admin)->post(route('siswa.store'), [
            'nama' => 'Dedi',
            'jk' => 'L',
            'id_kelas' => $this->kelas7a->uuid,
        ])->assertRedirect();

        $siswa = Siswa::where('nama', 'Dedi')->firstOrFail();
        $ortuUserId = Orangtua::where('id_siswa', $siswa->uuid)->value('id_login');

        $this->assertContains($siswa->id_login, $this->anggotaAktif($this->grupKelas($this->kelas7a)));
        $this->assertContains($ortuUserId, $this->anggotaAktif($this->grupPaguyuban($this->kelas7a)));
    }

    public function test_sinkron_tidak_pernah_menaruh_ortu_di_grup_kelas(): void
    {
        $this->artisan('grupchat:sinkron')->assertSuccessful();

        $ortuIds = Orangtua::pluck('id_login')->filter()->all();
        $grupKelasIds = GrupChat::where('tipe', GrupChat::TIPE_KELAS)->pluck('uuid');

        $this->assertSame(0, GrupChatMember::whereIn('grup_id', $grupKelasIds)
            ->whereIn('user_id', $ortuIds)->count());
    }

    public function test_sinkron_tidak_pernah_menaruh_siswa_di_paguyuban(): void
    {
        $this->artisan('grupchat:sinkron')->assertSuccessful();

        $siswaIds = Siswa::pluck('id_login')->filter()->all();
        $paguyubanIds = GrupChat::where('tipe', GrupChat::TIPE_PAGUYUBAN)->pluck('uuid');

        $this->assertSame(0, GrupChatMember::whereIn('grup_id', $paguyubanIds)
            ->whereIn('user_id', $siswaIds)->count());
    }

    public function test_anggota_masuk_lagi_mempertahankan_batas_riwayatnya(): void
    {
        $grup = $this->grupKelas($this->kelas7a);
        $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => 'p1']);

        $member = GrupChatMember::where('grup_id', $grup->uuid)
            ->where('user_id', $this->siswa7a->uuid)->first();
        $joinedSeqAwal = (int) $member->joined_seq;

        // Keluar lalu masuk lagi (mis. akibat glitch sinkronisasi sesaat).
        $member->update(['left_at' => now()]);
        app(GrupChatService::class)->syncKelas($this->kelas7a);

        $member->refresh();
        $this->assertNull($member->left_at);
        $this->assertSame($joinedSeqAwal, (int) $member->joined_seq);
    }

    public function test_guru_pengajar_tidak_pernah_masuk_grup_kelas(): void
    {
        // Grup Kelas murni jalur walikelas-siswa; penugasan Ngajar (dibuat ATAU
        // dicabut) tidak pernah memicu apa pun di Grup Chat — lihat NgajarObserver.
        $guru = $this->buatGuruPengajar($this->kelas7a, 'guru_ipa', 'Pak IPA', '3200001234');
        $grup = $this->grupKelas($this->kelas7a);

        $this->assertNotContains($guru->uuid, $this->anggotaAktif($grup));

        app(GrupChatService::class)->syncKelas($this->kelas7a);
        $this->assertNotContains($guru->uuid, $this->anggotaAktif($grup));

        \App\Models\Ngajar::where('id_kelas', $this->kelas7a->uuid)
            ->whereIn('id_guru', Guru::where('id_login', $guru->uuid)->pluck('uuid'))
            ->get()->each->delete();

        $this->assertNotContains($guru->uuid, $this->anggotaAktif($grup));
    }

    /**
     * Bukti langsung bahwa NgajarObserver sudah tidak menyentuh GrupChatService
     * sama sekali -- test_guru_pengajar_tidak_pernah_masuk_grup_kelas() di atas
     * cuma membuktikan guru tidak ADA di keanggotaan (yang tetap benar walau
     * observer-nya masih memanggil syncKelas()), bukan bahwa hook-nya sendiri
     * sudah dihapus. Di sini GrupChatService di-mock dan dilarang menerima
     * panggilan apa pun selama Ngajar dibuat & dihapus.
     */
    public function test_ngajar_created_dan_deleted_tidak_memanggil_grup_chat_service(): void
    {
        $this->mock(GrupChatService::class, function ($mock) {
            $mock->shouldNotReceive('syncKelas', 'provisionKelas', 'syncSiswa', 'syncOrangtuaUser');
        });

        $guruLogin = User::create([
            'username' => 'guru_observer_test',
            'password' => bcrypt('password'),
            'access' => 'guru',
        ]);
        $guru = Guru::create([
            'id_login' => $guruLogin->uuid,
            'nama' => 'Guru Observer Test',
            'nik' => '3200009999',
            'jk' => 'L',
            'face_descriptor' => [0.7, 0.8],
        ]);
        $pelajaran = \App\Models\Pelajaran::create(['nama' => 'Observer Test', 'kkm' => 75]);

        $ngajar = \App\Models\Ngajar::create([
            'id_guru' => $guru->uuid,
            'id_kelas' => $this->kelas7a->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ]);

        $ngajar->delete();

        // Tidak ada assertion eksplisit di sini -- Mockery memverifikasi
        // shouldNotReceive() otomatis di akhir test lewat tearDown TestCase.
        $this->assertTrue(true);
    }

    public function test_grup_kelas_selalu_mode_pengumuman_grup_paguyuban_diskusi(): void
    {
        $grupKelas = $this->grupKelas($this->kelas7a);
        $grupPaguyuban = $this->grupPaguyuban($this->kelas7a);

        $this->assertSame(GrupChat::MODE_PENGUMUMAN, $grupKelas->mode);
        $this->assertSame(GrupChat::MODE_DISKUSI, $grupPaguyuban->mode);

        // Grup lama yang masih 'diskusi' (dibuat sebelum aturan ini) harus
        // otomatis dibetulkan begitu syncKelas() jalan lagi (nightly/observer).
        $grupKelas->update(['mode' => GrupChat::MODE_DISKUSI]);
        app(GrupChatService::class)->syncKelas($this->kelas7a);
        $this->assertSame(GrupChat::MODE_PENGUMUMAN, $grupKelas->fresh()->mode);
    }

    public function test_kelas_dihapus_menghapus_grupnya(): void
    {
        $kelas = Kelas::create(['tingkat' => 9, 'kelas' => 'Z']);
        app(GrupChatService::class)->provisionKelas($kelas);
        $this->assertSame(2, GrupChat::where('id_kelas', $kelas->uuid)->count());

        // Grup belum pernah dipakai (last_seq = 0 di kedua grup) — aman dihapus
        // bersama kelasnya lewat controller yang sekarang mengosongkannya dulu.
        Walikelas::where('id_kelas', $kelas->uuid)->delete();
        $this->actingAs($this->admin)->delete(route('kelas.destroy', $kelas->uuid))->assertRedirect();

        $this->assertDatabaseMissing('kelas', ['uuid' => $kelas->uuid]);
        $this->assertSame(0, GrupChat::where('id_kelas', $kelas->uuid)->count());
    }

    public function test_kelas_dengan_riwayat_pesan_tidak_bisa_dihapus(): void
    {
        // Regresi untuk temuan review: FK cascade lama akan menghapus riwayat
        // percakapan bertahun-tahun diam-diam begitu kelas dihapus. Sekarang
        // controller menolak, dan constraint restrictOnDelete jadi jaring
        // pengaman terakhir kalau ada jalur lain yang lupa mengecek ini.
        $grup = $this->grupKelas($this->kelas7a);
        $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => 'riwayat penting']);

        $this->actingAs($this->admin)
            ->delete(route('kelas.destroy', $this->kelas7a->uuid))
            ->assertRedirect();

        $this->assertDatabaseHas('kelas', ['uuid' => $this->kelas7a->uuid]);
        $this->assertDatabaseHas('grup_chats', ['id_kelas' => $this->kelas7a->uuid]);
        $this->assertDatabaseHas('grup_chat_messages', ['grup_id' => $grup->uuid]);
    }

    public function test_pindah_kelas_lewat_save_rombel_keluar_dari_grup_lama(): void
    {
        // Regresi untuk temuan review: mengganti syncSiswa() per-siswa dengan
        // syncKelas() batch tidak boleh melupakan kelas ASAL siswa yang pindah.
        $lama = $this->grupKelas($this->kelas7a);
        $baru = $this->grupKelas($this->kelas7b);
        $this->assertContains($this->siswa7a->uuid, $this->anggotaAktif($lama));

        $this->actingAs($this->admin)->post(route('kelas.saveRombel', $this->kelas7b->uuid), [
            'siswa_ids' => [$this->profilSiswa7a->uuid],
        ])->assertRedirect();

        $this->assertNotContains($this->siswa7a->uuid, $this->anggotaAktif($lama));
        $this->assertContains($this->siswa7a->uuid, $this->anggotaAktif($baru));
    }
}
