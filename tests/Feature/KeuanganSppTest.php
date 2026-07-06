<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Orangtua;
use App\Models\Siswa;
use App\Models\SppPembayaran;
use App\Models\User;
use App\Support\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Smoke test modul Keuangan/SPP: grid bendahara, tagihan ortu/siswa,
 * unggah bukti, verifikasi, dan guard kepemilikan data.
 */
class KeuanganSppTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $access, string $username): User
    {
        return User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access'   => $access,
        ]);
    }

    private function makeKelas(): Kelas
    {
        return Kelas::create(['tingkat' => '7', 'kelas' => 'A']);
    }

    private function makeSiswa(Kelas $kelas, ?User $login = null, int $spp = 150000, string $va = '8810123'): Siswa
    {
        return Siswa::create([
            'id_login' => $login?->uuid,
            'nama'     => 'Budi ' . substr(md5($va . microtime()), 0, 4),
            'nis'      => (string) random_int(10000, 99999),
            'id_kelas' => $kelas->uuid,
            'jk'       => 'L',
            'spp'      => (string) $spp,
            'va'       => $va,
        ]);
    }

    public function test_bendahara_bisa_buka_dashboard_keuangan(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara1');
        $this->makeKelas();

        $this->actingAs($bendahara)->get('/keuangan')->assertOk();
    }

    public function test_role_lain_dilarang_akses_keuangan(): void
    {
        $guru = $this->makeUser('guru', 'guru_keu');
        $this->actingAs($guru)->get('/keuangan')->assertForbidden();
    }

    public function test_buka_grid_kelas_membuat_12_baris_per_siswa(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara2');
        $kelas = $this->makeKelas();
        $this->makeSiswa($kelas, null, 200000);

        $this->actingAs($bendahara)->get('/keuangan/kelas/' . $kelas->uuid)->assertOk();

        $ta = TahunAjaran::current();
        $this->assertSame(12, SppPembayaran::where('tahun_ajaran', $ta)->count());
        // Nominal default mengikuti siswa.spp
        $this->assertDatabaseHas('spp_pembayaran', ['nominal' => 200000, 'status' => 'belum']);
    }

    public function test_ortu_melihat_tagihan_anak_12_bulan(): void
    {
        $ortu = $this->makeUser('orangtua', 'ortu1');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas, null, 175000);
        Orangtua::create(['id_login' => $ortu->uuid, 'id_siswa' => $siswa->uuid]);

        $this->actingAs($ortu)->get('/tagihan-spp')->assertOk();

        $this->assertSame(12, SppPembayaran::where('id_siswa', $siswa->uuid)->count());
        $this->assertDatabaseHas('spp_pembayaran', ['id_siswa' => $siswa->uuid, 'nominal' => 175000]);
    }

    public function test_siswa_upload_bukti_jadi_menunggu(): void
    {
        Storage::fake('local');

        $ortu = $this->makeUser('orangtua', 'ortu_upload');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        Orangtua::create(['id_login' => $ortu->uuid, 'id_siswa' => $siswa->uuid]);

        // Pastikan baris ada
        $this->actingAs($ortu)->get('/tagihan-spp')->assertOk();
        $p = SppPembayaran::where('id_siswa', $siswa->uuid)->where('bulan', 1)->firstOrFail();

        $res = $this->actingAs($ortu)->post('/tagihan-spp/' . $p->uuid . '/bukti', [
            'bank'  => 'BCA',
            'bukti' => UploadedFile::fake()->image('bukti.jpg', 600, 800),
        ]);
        $res->assertRedirect();

        $p->refresh();
        $this->assertSame('menunggu', $p->status);
        $this->assertSame('BCA', $p->bank);
        $this->assertNotNull($p->bukti_path);
        Storage::disk('local')->assertExists($p->bukti_path);
    }

    public function test_verifikasi_dua_tahap_menunggu_terverifikasi_lunas(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_verif');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);

        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid,
            'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 3,
            'nominal' => 150000,
            'status' => 'menunggu',
            'bank' => 'Mandiri',
            'tanggal_bayar' => now()->toDateString(),
        ]);

        // Tahap 1: verifikasi bukti → terverifikasi (BELUM lunas).
        $this->actingAs($bendahara)->post('/keuangan/verifikasi/verify', ['ids' => [$p->uuid]])->assertRedirect();
        $p->refresh();
        $this->assertSame('terverifikasi', $p->status);

        // Tahap 2: validasi rekening koran → lunas.
        $this->actingAs($bendahara)->post('/keuangan/verifikasi/validate', ['ids' => [$p->uuid]])->assertRedirect();
        $p->refresh();
        $this->assertSame('lunas', $p->status);
        $this->assertSame($bendahara->uuid, $p->diverifikasi_oleh);
        $this->assertNotNull($p->diverifikasi_pada);
    }

    public function test_validasi_hanya_berlaku_dari_terverifikasi(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_valid');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);

        // Masih menunggu (belum diverifikasi) → validate tidak boleh melunaskan.
        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 4, 'nominal' => 150000, 'status' => 'menunggu',
        ]);

        $this->actingAs($bendahara)->post('/keuangan/verifikasi/validate', ['ids' => [$p->uuid]])->assertRedirect();
        $p->refresh();
        $this->assertSame('menunggu', $p->status);
    }

    public function test_bendahara_update_sel_via_cell(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_cell');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);

        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid,
            'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 5,
            'nominal' => 150000,
            'status' => 'belum',
        ]);

        $this->actingAs($bendahara)->postJson('/keuangan/pembayaran/' . $p->uuid . '/cell', [
            'status'        => 'lunas',
            'nominal'       => 180000,
            'tanggal_bayar' => '2026-06-28',
        ])->assertOk()->assertJsonPath('ok', true);

        $p->refresh();
        $this->assertSame('lunas', $p->status);
        $this->assertSame(180000, $p->nominal);
    }

    public function test_bendahara_update_beberapa_sel_pembayaran_sekaligus(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_bulk_cell');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        $ta = TahunAjaran::current();

        $p1 = SppPembayaran::create([
            'id_siswa' => $siswa->uuid,
            'tahun_ajaran' => $ta,
            'bulan' => 1,
            'nominal' => 150000,
            'status' => 'belum',
        ]);
        $p2 = SppPembayaran::create([
            'id_siswa' => $siswa->uuid,
            'tahun_ajaran' => $ta,
            'bulan' => 2,
            'nominal' => 150000,
            'status' => 'belum',
        ]);
        $p3 = SppPembayaran::create([
            'id_siswa' => $siswa->uuid,
            'tahun_ajaran' => $ta,
            'bulan' => 3,
            'nominal' => 150000,
            'status' => 'belum',
        ]);

        $this->actingAs($bendahara)->postJson('/keuangan/pembayaran/' . $p1->uuid . '/cell', [
            'status'           => 'lunas',
            'nominal'          => 200000,
            'tanggal_bayar'    => '2026-06-28',
            'selected_bulans'  => [1, 2, 3],
        ])->assertOk()->assertJsonPath('ok', true);

        $p1->refresh();
        $p2->refresh();
        $p3->refresh();

        $this->assertSame('lunas', $p1->status);
        $this->assertSame(200000, $p1->nominal);
        $this->assertSame('2026-06-28', $p1->tanggal_bayar->toDateString());

        $this->assertSame('lunas', $p2->status);
        $this->assertSame(200000, $p2->nominal);
        $this->assertSame('2026-06-28', $p2->tanggal_bayar->toDateString());

        $this->assertSame('lunas', $p3->status);
        $this->assertSame(200000, $p3->nominal);
        $this->assertSame('2026-06-28', $p3->tanggal_bayar->toDateString());
    }

    public function test_upload_sekaligus_beberapa_bulan(): void
    {
        Storage::fake('local');

        $ortu = $this->makeUser('orangtua', 'ortu_batch');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas, null, 150000, '8810BATCH');
        Orangtua::create(['id_login' => $ortu->uuid, 'id_siswa' => $siswa->uuid]);

        $this->actingAs($ortu)->get('/tagihan-spp')->assertOk();
        $rows = SppPembayaran::where('id_siswa', $siswa->uuid)->orderBy('bulan')->get();
        [$b1, $b2, $b3] = [$rows[0], $rows[1], $rows[2]];

        $res = $this->actingAs($ortu)->post('/tagihan-spp/' . $b1->uuid . '/bukti', [
            'bank'       => 'BCA',
            'bukti'      => UploadedFile::fake()->image('bukti.jpg', 600, 800),
            'bulan_lain' => [$b2->uuid, $b3->uuid],
        ]);
        $res->assertRedirect();

        foreach ([$b1, $b2, $b3] as $b) {
            $b->refresh();
            $this->assertSame('menunggu', $b->status, "Bulan {$b->bulan} harusnya menunggu");
            $this->assertNotNull($b->bukti_path);
            Storage::disk('local')->assertExists($b->bukti_path);
        }
        // Tiap bulan punya salinan file sendiri (path berbeda).
        $this->assertNotSame($b1->bukti_path, $b2->bukti_path);
        $this->assertNotSame($b2->bukti_path, $b3->bukti_path);
    }

    public function test_upload_batch_mengabaikan_bulan_milik_siswa_lain(): void
    {
        Storage::fake('local');
        $kelas = $this->makeKelas();

        $ortu = $this->makeUser('orangtua', 'ortu_safe');
        $anak = $this->makeSiswa($kelas, null, 150000, '8810SAFE');
        Orangtua::create(['id_login' => $ortu->uuid, 'id_siswa' => $anak->uuid]);

        $lain = $this->makeSiswa($kelas, null, 150000, '8810OTHER');
        $pLain = SppPembayaran::create([
            'id_siswa' => $lain->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 1, 'nominal' => 150000, 'status' => 'belum',
        ]);

        $this->actingAs($ortu)->get('/tagihan-spp')->assertOk();
        $milik = SppPembayaran::where('id_siswa', $anak->uuid)->where('bulan', 1)->firstOrFail();

        $this->actingAs($ortu)->post('/tagihan-spp/' . $milik->uuid . '/bukti', [
            'bank'       => 'BCA',
            'bukti'      => UploadedFile::fake()->image('bukti.jpg'),
            'bulan_lain' => [$pLain->uuid],
        ])->assertRedirect();

        // Pembayaran siswa lain tidak boleh tersentuh.
        $pLain->refresh();
        $this->assertSame('belum', $pLain->status);
        $this->assertNull($pLain->bukti_path);
    }

    public function test_bendahara_verifikasi_batch_sekaligus(): void
    {
        Storage::fake('local');

        $ortu = $this->makeUser('orangtua', 'ortu_batchverif');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas, null, 120000, '8810BV');
        Orangtua::create(['id_login' => $ortu->uuid, 'id_siswa' => $siswa->uuid]);

        // Ortu bayar 3 bulan sekaligus.
        $this->actingAs($ortu)->get('/tagihan-spp')->assertOk();
        $rows = SppPembayaran::where('id_siswa', $siswa->uuid)->orderBy('bulan')->get();
        [$b1, $b2, $b3] = [$rows[0], $rows[1], $rows[2]];
        $this->actingAs($ortu)->post('/tagihan-spp/' . $b1->uuid . '/bukti', [
            'bank'       => 'BCA',
            'bukti'      => UploadedFile::fake()->image('bukti.jpg'),
            'bulan_lain' => [$b2->uuid, $b3->uuid],
        ])->assertRedirect();

        // Ketiganya satu batch_id.
        $b1->refresh();
        $this->assertNotNull($b1->batch_id);
        $this->assertSame(3, SppPembayaran::where('batch_id', $b1->batch_id)->count());

        // Bendahara verifikasi sekaligus → semua terverifikasi.
        $bendahara = $this->makeUser('bendahara', 'bendahara_batch');
        $ids = SppPembayaran::where('batch_id', $b1->batch_id)->pluck('uuid')->all();
        $this->actingAs($bendahara)->post('/keuangan/verifikasi/verify', ['ids' => $ids])->assertRedirect();
        $this->assertSame(3, SppPembayaran::whereIn('uuid', $ids)->where('status', 'terverifikasi')->count());

        // Lalu validasi sekaligus → semua lunas.
        $this->actingAs($bendahara)->post('/keuangan/verifikasi/validate', ['ids' => $ids])->assertRedirect();
        $this->assertSame(3, SppPembayaran::whereIn('uuid', $ids)->where('status', 'lunas')->count());
        $this->assertSame(0, SppPembayaran::whereIn('uuid', $ids)->where('status', '!=', 'lunas')->count());
    }

    public function test_bendahara_tolak_batch_sekaligus(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_reject');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        $batch = (string) \Illuminate\Support\Str::uuid();
        foreach ([1, 2] as $bln) {
            SppPembayaran::create([
                'id_siswa' => $siswa->uuid, 'tahun_ajaran' => TahunAjaran::current(),
                'bulan' => $bln, 'batch_id' => $batch, 'nominal' => 100000,
                'status' => 'menunggu', 'bank' => 'BCA', 'tanggal_bayar' => now()->toDateString(),
            ]);
        }
        $ids = SppPembayaran::where('batch_id', $batch)->pluck('uuid')->all();

        $this->actingAs($bendahara)->post('/keuangan/verifikasi/reject', [
            'ids' => $ids, 'catatan' => 'Nominal kurang',
        ])->assertRedirect();

        $this->assertSame(2, SppPembayaran::whereIn('uuid', $ids)->where('status', 'ditolak')->count());
        $this->assertDatabaseHas('spp_pembayaran', ['batch_id' => $batch, 'catatan' => 'Nominal kurang']);
    }

    public function test_bendahara_atur_va_dan_nominal_per_siswa(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_va');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas, null, 0, '');

        // Buka grid → 12 baris 'belum' terbentuk (nominal 0).
        $this->actingAs($bendahara)->get('/keuangan/kelas/' . $kelas->uuid)->assertOk();

        // Tandai 1 bulan sudah lunas dengan nominal lama (tidak boleh diubah).
        $lunas = SppPembayaran::where('id_siswa', $siswa->uuid)->where('bulan', 1)->firstOrFail();
        $lunas->update(['status' => 'lunas', 'nominal' => 100000]);

        $this->actingAs($bendahara)->post('/keuangan/kelas/' . $kelas->uuid . '/pengaturan', [
            'va'       => [$siswa->uuid => '8810999'],
            'spp'      => [$siswa->uuid => 175000],
            'terapkan' => '1',
            'ta'       => TahunAjaran::current(),
        ])->assertRedirect();

        $siswa->refresh();
        $this->assertSame('8810999', $siswa->va);
        $this->assertSame('175000', (string) $siswa->spp);

        // 11 bulan 'belum' jadi 175000; bulan lunas tetap 100000.
        $this->assertSame(11, SppPembayaran::where('id_siswa', $siswa->uuid)->where('nominal', 175000)->count());
        $lunas->refresh();
        $this->assertSame(100000, $lunas->nominal);
    }

    public function test_bendahara_revisi_nominal_tanpa_ubah_status(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_revisi');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas, null, 0); // spp 0 → nominal 0

        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 1, 'nominal' => 0, 'status' => 'terverifikasi',
            'bank' => 'BCA', 'tanggal_bayar' => now()->toDateString(),
        ]);

        $this->actingAs($bendahara)->post('/keuangan/verifikasi/revise', [
            'nominal'       => [$p->uuid => 175000],
            'bank'          => 'Mandiri',
            'tanggal_bayar' => '2026-06-20',
        ])->assertRedirect();

        $p->refresh();
        $this->assertSame(175000, $p->nominal);
        $this->assertSame('Mandiri', $p->bank);
        $this->assertSame('terverifikasi', $p->status); // status tidak berubah
    }

    public function test_verifikasi_search_nama_dan_kelas(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_search');
        $kelasA = Kelas::create(['tingkat' => '9', 'kelas' => 'C']);
        $kelasB = Kelas::create(['tingkat' => '7', 'kelas' => 'A']);

        $adres = Siswa::create(['nama' => 'Adres Iniesta', 'nis' => '111', 'id_kelas' => $kelasA->uuid, 'jk' => 'L', 'spp' => '150000']);
        $budi  = Siswa::create(['nama' => 'Budi Santoso', 'nis' => '222', 'id_kelas' => $kelasB->uuid, 'jk' => 'L', 'spp' => '150000']);
        foreach ([$adres, $budi] as $s) {
            SppPembayaran::create([
                'id_siswa' => $s->uuid, 'tahun_ajaran' => TahunAjaran::current(),
                'bulan' => 1, 'nominal' => 150000, 'status' => 'menunggu', 'bank' => 'BCA',
            ]);
        }

        // Cari nama
        $this->actingAs($bendahara)->get('/keuangan/verifikasi?q=Adres')
            ->assertOk()->assertSee('Adres Iniesta')->assertDontSee('Budi Santoso');

        // Cari kelas (tingkat)
        $this->actingAs($bendahara)->get('/keuangan/verifikasi?q=7')
            ->assertOk()->assertSee('Budi Santoso')->assertDontSee('Adres Iniesta');
    }

    public function test_ortu_lihat_bukti_lunas(): void
    {
        $ortu = $this->makeUser('orangtua', 'ortu_receipt');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        Orangtua::create(['id_login' => $ortu->uuid, 'id_siswa' => $siswa->uuid]);

        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 2, 'nominal' => 150000, 'status' => 'lunas',
            'bank' => 'BCA', 'bukti_path' => 'bukti-spp/x.jpg',
            'tanggal_bayar' => now()->toDateString(), 'diverifikasi_oleh' => $ortu->uuid, 'diverifikasi_pada' => now(),
        ]);

        $this->actingAs($ortu)->get('/tagihan-spp/' . $p->uuid)
            ->assertOk()
            ->assertSee('Lunas')
            ->assertSee('Bukti Pembayaran');
    }

    public function test_ortu_tidak_bisa_akses_tagihan_anak_lain(): void
    {
        $kelas = $this->makeKelas();

        $ortuA = $this->makeUser('orangtua', 'ortuA');
        $anakA = $this->makeSiswa($kelas, null, 150000, '8810AAA');
        Orangtua::create(['id_login' => $ortuA->uuid, 'id_siswa' => $anakA->uuid]);

        $ortuB = $this->makeUser('orangtua', 'ortuB');
        $anakB = $this->makeSiswa($kelas, null, 150000, '8810BBB');
        Orangtua::create(['id_login' => $ortuB->uuid, 'id_siswa' => $anakB->uuid]);

        $pB = SppPembayaran::create([
            'id_siswa' => $anakB->uuid,
            'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 1,
            'nominal' => 150000,
            'status' => 'belum',
        ]);

        // Ortu A mencoba membuka tagihan anak Ortu B → 403
        $this->actingAs($ortuA)->get('/tagihan-spp/' . $pB->uuid)->assertForbidden();
    }

    public function test_bukti_file_hanya_pemilik_atau_bendahara(): void
    {
        Storage::fake('local');
        $kelas = $this->makeKelas();

        $ortuA = $this->makeUser('orangtua', 'ortuA_bukti');
        $anakA = $this->makeSiswa($kelas, null, 150000, '8810FA');
        Orangtua::create(['id_login' => $ortuA->uuid, 'id_siswa' => $anakA->uuid]);

        $ortuB = $this->makeUser('orangtua', 'ortuB_bukti');
        $anakB = $this->makeSiswa($kelas, null, 150000, '8810FB');
        Orangtua::create(['id_login' => $ortuB->uuid, 'id_siswa' => $anakB->uuid]);

        // Bukti milik anak A (file ada di disk privat).
        Storage::disk('local')->put('bukti-spp/' . $anakA->uuid . '/x.jpg', 'dummy');
        $p = SppPembayaran::create([
            'id_siswa' => $anakA->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 1, 'nominal' => 150000, 'status' => 'menunggu',
            'bukti_path' => 'bukti-spp/' . $anakA->uuid . '/x.jpg',
        ]);
        $url = '/tagihan-spp/' . $p->uuid . '/bukti-file';

        // Pemilik → boleh
        $this->actingAs($ortuA)->get($url)->assertOk();
        // Ortu lain → 403 (bukan pemilik & bukan staf)
        $this->actingAs($ortuB)->get($url)->assertForbidden();
        // Bendahara → boleh (verifikasi)
        $this->actingAs($this->makeUser('bendahara', 'bendahara_bukti'))->get($url)->assertOk();
        // Guru (bukan pemilik, bukan staf keuangan) → 403
        $this->actingAs($this->makeUser('guru', 'guru_bukti'))->get($url)->assertForbidden();
    }

    public function test_tahun_ajaran_juli_juni(): void
    {
        $this->assertSame('2025/2026', TahunAjaran::current(\Illuminate\Support\Carbon::parse('2026-06-15')));
        $this->assertSame('2026/2027', TahunAjaran::current(\Illuminate\Support\Carbon::parse('2026-07-01')));
        // Bulan idx 1 = Juli tahun awal, idx 12 = Juni tahun berikutnya
        $this->assertSame(7, TahunAjaran::tanggal('2025/2026', 1)->month);
        $this->assertSame(2025, TahunAjaran::tanggal('2025/2026', 1)->year);
        $this->assertSame(6, TahunAjaran::tanggal('2025/2026', 12)->month);
        $this->assertSame(2026, TahunAjaran::tanggal('2025/2026', 12)->year);
    }
}
