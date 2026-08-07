<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\SppOcrDraft;
use App\Models\SppPembayaran;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\Keuangan\SppActivityLogger;
use App\Services\Keuangan\SppMonthlyDashboard;
use App\Services\Keuangan\SppVerificationQueue;
use App\Support\RekeningKoran\RekeningKoranBcaParser;
use App\Support\RekeningKoran\RekeningKoranMandiriParser;
use App\Support\RekeningKoran\RekeningKoranParserResolver;
use App\Support\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Fase A — AI Bendahara SPP Operasional: antrian prioritas, dashboard, parser, activity log.
 */
class KeuanganAiFaseATest extends TestCase
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

    private function makeSiswa(Kelas $kelas, int $spp = 150000, string $va = '8810123'): Siswa
    {
        return Siswa::create([
            'nama'     => 'Budi Test',
            'nis'      => (string) random_int(10000, 99999),
            'id_kelas' => $kelas->uuid,
            'jk'       => 'L',
            'spp'      => (string) $spp,
            'va'       => $va,
        ]);
    }

    // ─── A1: Antrian prioritas ───────────────────────────────────────────

    public function test_bendahara_bisa_buka_antrian_prioritas(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_ai1');
        $this->actingAs($bendahara)->get(route('keuangan.bendahara-ai.antrian'))->assertOk();
    }

    public function test_guru_dilarang_akses_asisten_bendahara(): void
    {
        $guru = $this->makeUser('guru', 'guru_ai1');
        $this->actingAs($guru)->get(route('keuangan.bendahara-ai.index'))->assertForbidden();
    }

    public function test_antrian_prioritas_urutkan_nominal_lebih_tinggi_dulu(): void
    {
        $kelas = $this->makeKelas();
        $siswaA = $this->makeSiswa($kelas, 100000, '8810AAA');
        $siswaB = $this->makeSiswa($kelas, 500000, '8810BBB');
        $ta = TahunAjaran::current();

        SppPembayaran::create([
            'id_siswa' => $siswaA->uuid, 'tahun_ajaran' => $ta, 'bulan' => 1,
            'nominal' => 100000, 'status' => 'menunggu', 'bank' => 'BCA',
        ]);
        SppPembayaran::create([
            'id_siswa' => $siswaB->uuid, 'tahun_ajaran' => $ta, 'bulan' => 1,
            'nominal' => 500000, 'status' => 'menunggu', 'bank' => 'BCA',
        ]);

        $queue = app(SppVerificationQueue::class);
        $sorted = $queue->prioritized($ta);

        $this->assertGreaterThanOrEqual(2, $sorted->count());
        $this->assertSame(500000, $sorted->first()['pembayaran']->nominal);
    }

    // ─── A3: Dashboard SPP ─────────────────────────────────────────────

    public function test_dashboard_spp_agregat_bigint_lunas_saja(): void
    {
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        $ta = TahunAjaran::current();

        SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => $ta, 'bulan' => 1,
            'nominal' => 175000, 'status' => 'lunas',
        ]);
        SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => $ta, 'bulan' => 2,
            'nominal' => 175000, 'status' => 'menunggu',
        ]);

        $dash = app(SppMonthlyDashboard::class)->ringkasanTahun($ta);

        $this->assertSame(175000, $dash['bulan'][1]['total']);
        $this->assertSame(1, $dash['bulan'][1]['jumlah']);
        $this->assertSame(0, $dash['bulan'][2]['total'], 'Menunggu tidak masuk agregat lunas');
        $this->assertIsInt($dash['grand_total']);
    }

    public function test_bendahara_bisa_buka_dashboard_spp(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_dash');
        $this->actingAs($bendahara)->get(route('keuangan.bendahara-ai.dashboard'))->assertOk();
    }

    // ─── A4: Parser rekening koran ───────────────────────────────────────

    public function test_parser_bca_tetap_terbaca(): void
    {
        $sample = "     1  402353              BRYAN DOMINIC TI  IDR            770,000.00  20/06/26  06:51:52  9527N  -                -\n";
        $rows = RekeningKoranBcaParser::parse($sample);
        $this->assertCount(1, $rows);
        $this->assertSame(770000, $rows[0]['nominal']);
    }

    public function test_parser_mandiri_csv(): void
    {
        $sample = "402353;BRYAN DOMINIC;770000;20/06/2026;06:51:52\n";
        $this->assertTrue(RekeningKoranMandiriParser::detect($sample));
        $rows = RekeningKoranMandiriParser::parse($sample);
        $this->assertCount(1, $rows);
        $this->assertSame('402353', $rows[0]['no_pelanggan']);
        $this->assertSame(770000, $rows[0]['nominal']);
    }

    public function test_resolver_deteksi_bank_bca(): void
    {
        $content = "LAPORAN TRANSAKSI VIA E-BANKING\n     1  402353  TEST  IDR 100,000.00  20/06/26  06:51:52  9503N\n";
        $result = RekeningKoranParserResolver::resolve($content);
        $this->assertSame('BCA', $result['bank']);
        $this->assertNotEmpty($result['transaksi']);
    }

    // ─── A5: Activity log ────────────────────────────────────────────────

    public function test_verifikasi_menulis_activity_log(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_log');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);

        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 3, 'nominal' => 150000, 'status' => 'menunggu',
        ]);

        $this->actingAs($bendahara)->post(route('keuangan.verify-batch'), ['ids' => [$p->uuid]])->assertRedirect();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => SppActivityLogger::LOG_NAME,
            'event'    => 'spp_verifikasi_disetujui',
        ]);
    }

    public function test_bendahara_bisa_lihat_jejak_audit(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_audit');
        $this->actingAs($bendahara)->get(route('keuangan.bendahara-ai.log'))->assertOk();
    }

    // ─── A2: OCR tidak auto-post ─────────────────────────────────────────

    public function test_antrian_menampilkan_tombol_baca_bukti(): void
    {
        Storage::fake('local');

        $bendahara = $this->makeUser('bendahara', 'bendahara_ocr_ui');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        $ta = TahunAjaran::current();

        $path = 'bukti-spp/'.$siswa->uuid.'/test.jpg';
        Storage::disk('local')->put($path, 'fake-image');

        SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => $ta, 'bulan' => 1,
            'nominal' => 150000, 'status' => 'menunggu', 'bank' => 'BCA', 'bukti_path' => $path,
        ]);

        $p = SppPembayaran::where('id_siswa', $siswa->uuid)->firstOrFail();
        $html = $this->actingAs($bendahara)->get(route('keuangan.bendahara-ai.antrian'))->assertOk()->getContent();

        $this->assertStringContainsString('Baca Bukti', $html);
        $this->assertStringContainsString($p->uuid, $html);
    }

    public function test_ocr_endpoint_mengembalikan_saran_hitl(): void
    {
        config(['ai.api_key' => 'test-key', 'ai.provider' => 'gemini']);

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('visionText')
                ->once()
                ->andReturn([
                    'text' => '{"nama_pengirim":"Budi","tanggal":"2026-06-20","referensi":"BCA","nominal_teks":"Rp 150.000"}',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 10,
                    'completion_tokens' => 20,
                ]);
        });

        $bendahara = $this->makeUser('bendahara', 'bendahara_ocr_hitl');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);

        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 1, 'nominal' => 150000, 'status' => 'menunggu',
        ]);

        $response = $this->actingAs($bendahara)->postJson(route('keuangan.bendahara-ai.ocr', $p), [
            'bukti' => UploadedFile::fake()->image('bukti.jpg', 400, 600),
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('saran.nama_pengirim', 'Budi')
            ->assertJsonPath('saran.tanggal', '2026-06-20')
            ->assertJsonPath('saran.nominal_teks', 'Rp 150.000');

        $p->refresh();
        $this->assertSame('menunggu', $p->status);
        $this->assertDatabaseHas('spp_ocr_drafts', ['pembayaran_uuid' => $p->uuid]);
        $this->assertSame(1, SppOcrDraft::where('pembayaran_uuid', $p->uuid)->count());
    }

    public function test_ocr_endpoint_tidak_mengubah_status_pembayaran(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_ocr');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);

        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 1, 'nominal' => 150000, 'status' => 'menunggu',
        ]);

        // Tanpa file → validasi gagal, status tidak berubah
        $this->actingAs($bendahara)->postJson(route('keuangan.bendahara-ai.ocr', $p))
            ->assertStatus(422);

        $p->refresh();
        $this->assertSame('menunggu', $p->status);
    }

    public function test_ocr_pembayaran_uuid_tidak_ada_returns_404(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_ocr_404');

        $this->actingAs($bendahara)->postJson(
            route('keuangan.bendahara-ai.ocr', ['pembayaran' => '00000000-0000-4000-8000-000000000000']),
            ['bukti' => UploadedFile::fake()->image('bukti.jpg')],
        )->assertNotFound();
    }

    public function test_ocr_pembayaran_lunas_tidak_mengubah_status(): void
    {
        config(['ai.api_key' => 'test-key', 'ai.provider' => 'gemini']);

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('visionText')
                ->once()
                ->andReturn([
                    'text' => '{"nama_pengirim":"Budi","tanggal":"2026-06-20","referensi":"BCA","nominal_teks":"Rp 150.000"}',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 10,
                    'completion_tokens' => 20,
                ]);
        });

        $bendahara = $this->makeUser('bendahara', 'bendahara_ocr_lunas');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);

        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 1, 'nominal' => 150000, 'status' => 'lunas',
        ]);

        $this->actingAs($bendahara)->postJson(route('keuangan.bendahara-ai.ocr', $p), [
            'bukti' => UploadedFile::fake()->image('bukti.jpg', 400, 600),
        ])->assertOk();

        $p->refresh();
        $this->assertSame('lunas', $p->status);
    }
}
