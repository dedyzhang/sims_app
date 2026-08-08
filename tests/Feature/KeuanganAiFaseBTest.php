<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\SppPembayaran;
use App\Models\User;
use App\Notifications\BendaharaAntrianDigestNotification;
use App\Services\Keuangan\BendaharaAntrianDigest;
use App\Services\Keuangan\SppAnomalyDetector;
use App\Services\Keuangan\SppMutasiMatchingService;
use App\Services\Keuangan\SppService;
use App\Support\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fase B — Matching & rekonsiliasi cerdas: skor matching, anomali, digest antrian.
 */
class KeuanganAiFaseBTest extends TestCase
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

    private function makeSiswa(Kelas $kelas, int $spp = 150000, string $va = '8810123456'): Siswa
    {
        return Siswa::create([
            'nama'     => 'Budi Santoso',
            'nis'      => (string) random_int(10000, 99999),
            'id_kelas' => $kelas->uuid,
            'jk'       => 'L',
            'spp'      => (string) $spp,
            'va'       => $va,
        ]);
    }

    // ─── B1: Skor matching mutasi ────────────────────────────────────────

    public function test_matching_skor_va_dan_nominal_persis(): void
    {
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas, 150000, '8810402353');
        $ta = TahunAjaran::current();

        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => $ta, 'bulan' => 1,
            'nominal' => 150000, 'status' => 'terverifikasi',
            'tanggal_bayar' => '2026-06-20',
        ]);

        $mutasi = [
            'no_pelanggan' => '402353',
            'nominal'      => 150000,
            'tanggal'      => Carbon::parse('2026-06-20'),
            'baris_asli'   => 'BUDI SANTOSO transfer',
        ];

        $hasil = app(SppMutasiMatchingService::class)->scoreMatch($mutasi, $p, $siswa);

        $this->assertGreaterThanOrEqual(70, $hasil['skor']);
        $this->assertArrayHasKey('va', $hasil['alasan']);
        $this->assertArrayHasKey('nominal', $hasil['alasan']);
    }

    public function test_preview_rekening_koran_menyertakan_skor(): void
    {
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas, 770000, '8810402353');
        $ta = TahunAjaran::current();

        SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => $ta, 'bulan' => 1,
            'nominal' => 770000, 'status' => 'belum',
        ]);

        $transaksi = [[
            'no_pelanggan' => '402353',
            'nominal'      => 770000,
            'tanggal'      => Carbon::parse('2026-06-20'),
            'waktu'        => '06:51:52',
            'lokasi'       => 'BRYAN DOMINIC',
            'baris_asli'   => 'BRYAN DOMINIC TI',
        ]];

        $preview = app(SppService::class)->previewRekeningKoran($transaksi);

        $this->assertCount(1, $preview);
        $this->assertSame('saran_otomatis', $preview[0]['status']);
        $this->assertGreaterThan(0, $preview[0]['skor']);
    }

    public function test_bendahara_bisa_buka_halaman_rekonsiliasi(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_rekon');
        $ta = TahunAjaran::current();
        $this->actingAs($bendahara)
            ->get(route('keuangan.verifikasi', ['tab' => 'validasi']))
            ->assertOk()
            ->assertSee('Rekonsiliasi Mutasi Bank', false);

        $response = $this->actingAs($bendahara)
            ->get(route('keuangan.bendahara-ai.rekonsiliasi'));
        $response->assertRedirect(route('keuangan.verifikasi', ['ta' => $ta, 'tab' => 'validasi']).'#validasi');
    }

    // ─── B2: Anomali flag ────────────────────────────────────────────────

    public function test_deteksi_duplikat_bukti(): void
    {
        $kelas = $this->makeKelas();
        $siswaA = $this->makeSiswa($kelas, 150000, '8810111111');
        $siswaB = Siswa::create([
            'nama' => 'Ani', 'nis' => '99999', 'id_kelas' => $kelas->uuid, 'jk' => 'P', 'spp' => '150000', 'va' => '8810222222',
        ]);
        $ta = TahunAjaran::current();
        $path = 'bukti-spp/shared.jpg';

        SppPembayaran::create([
            'id_siswa' => $siswaA->uuid, 'tahun_ajaran' => $ta, 'bulan' => 1,
            'nominal' => 150000, 'status' => 'menunggu', 'bukti_path' => $path,
        ]);
        SppPembayaran::create([
            'id_siswa' => $siswaB->uuid, 'tahun_ajaran' => $ta, 'bulan' => 2,
            'nominal' => 150000, 'status' => 'menunggu', 'bukti_path' => $path,
        ]);

        $items = app(SppAnomalyDetector::class)->scan($ta);

        $this->assertGreaterThanOrEqual(2, $items->count());
        $this->assertTrue(
            $items->flatMap(fn ($i) => $i['flags'])->contains(fn ($f) => $f['kode'] === SppAnomalyDetector::FLAG_DUPLIKAT_BUKTI)
        );
    }

    public function test_deteksi_nominal_janggal(): void
    {
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas, 150000);
        $ta = TahunAjaran::current();

        SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => $ta, 'bulan' => 1,
            'nominal' => 200000, 'status' => 'menunggu',
        ]);

        $items = app(SppAnomalyDetector::class)->scan($ta);

        $this->assertCount(1, $items);
        $this->assertSame(SppAnomalyDetector::FLAG_NOMINAL_JANGGAL, $items->first()['flags'][0]['kode']);
    }

    public function test_bendahara_bisa_buka_halaman_anomali(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_anomali');
        $ta = TahunAjaran::current();
        $this->actingAs($bendahara)
            ->get(route('keuangan.verifikasi', ['filter' => 'anomali', 'prioritas' => 1]))
            ->assertOk()
            ->assertSee('Filter anomali', false);

        $this->actingAs($bendahara)
            ->get(route('keuangan.bendahara-ai.anomali'))
            ->assertRedirect(route('keuangan.verifikasi', ['ta' => $ta, 'filter' => 'anomali', 'prioritas' => 1]));
    }

    // ─── B3: Digest antrian ──────────────────────────────────────────────

    public function test_digest_mengirim_notifikasi_saat_antrian_menumpuk(): void
    {
        Notification::fake();
        config(['keuangan-ai.digest.menunggu_min' => 2]);

        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        $ta = TahunAjaran::current();
        $bendahara = $this->makeUser('bendahara', 'bendahara_digest');

        foreach ([1, 2, 3] as $bulan) {
            SppPembayaran::create([
                'id_siswa' => $siswa->uuid, 'tahun_ajaran' => $ta, 'bulan' => $bulan,
                'nominal' => 150000, 'status' => 'menunggu',
            ]);
        }

        $ringkasan = app(BendaharaAntrianDigest::class)->ringkasan($ta);
        $this->assertTrue($ringkasan['menumpuk']);

        $n = app(BendaharaAntrianDigest::class)->kirimDigest();
        $this->assertGreaterThanOrEqual(1, $n);

        Notification::assertSentTo($bendahara, BendaharaAntrianDigestNotification::class);
    }

    public function test_hub_menampilkan_banner_antrian_menumpuk(): void
    {
        config(['keuangan-ai.digest.menunggu_min' => 1]);

        $bendahara = $this->makeUser('bendahara', 'bendahara_hub');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        $ta = TahunAjaran::current();

        SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => $ta, 'bulan' => 1,
            'nominal' => 150000, 'status' => 'menunggu',
        ]);

        $html = $this->actingAs($bendahara)->get(route('keuangan.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Antrian menumpuk', $html);
    }
}
