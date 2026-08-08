<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\SppPembayaran;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\Keuangan\BendaharaWawasanService;
use App\Support\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Fase C — Wawasan non-nominal & ekspor paket verifikasi.
 */
class KeuanganAiFaseCTest extends TestCase
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

    private function makeSiswa(Kelas $kelas, int $spp = 150000): Siswa
    {
        return Siswa::create([
            'nama'     => 'Citra Wawasan',
            'nis'      => (string) random_int(10000, 99999),
            'id_kelas' => $kelas->uuid,
            'jk'       => 'P',
            'spp'      => (string) $spp,
            'va'       => '8810999888',
        ]);
    }

    // ─── C1: Wawasan non-nominal ─────────────────────────────────────────

    public function test_bendahara_bisa_buka_halaman_wawasan(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_wawasan');
        $this->actingAs($bendahara)->get(route('keuangan.bendahara-ai.wawasan'))->assertOk();
    }

    public function test_wawasan_hitung_pola_keterlambatan_tanpa_nominal_agregat(): void
    {
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        $ta = TahunAjaran::current();
        $jatuh = TahunAjaran::tanggal($ta, 1)->endOfMonth();

        SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => $ta, 'bulan' => 1,
            'nominal' => 150000, 'status' => 'lunas',
            'tanggal_bayar' => $jatuh->copy()->addDays(5),
            'jatuh_tempo' => $jatuh,
        ]);
        SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => $ta, 'bulan' => 2,
            'nominal' => 150000, 'status' => 'lunas',
            'tanggal_bayar' => $jatuh->copy()->subDays(2),
            'jatuh_tempo' => TahunAjaran::tanggal($ta, 2)->endOfMonth(),
        ]);

        $ringkasan = app(BendaharaWawasanService::class)->ringkasan($ta);

        $this->assertSame(2, $ringkasan['keterlambatan']['total_lunas']);
        $this->assertSame(1, $ringkasan['keterlambatan']['terlambat']);
        $this->assertArrayHasKey('poin_narasi', $ringkasan);
        $this->assertArrayNotHasKey('grand_total', $ringkasan, 'Wawasan tidak agregat rupiah');
    }

    public function test_wawasan_narasi_ai_mengembalikan_teks_dari_metrik_non_nominal(): void
    {
        config(['ai.api_key' => 'test-key', 'ai.provider' => 'gemini']);

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => 'Antrian verifikasi perlu diprioritaskan di awal minggu.',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 50,
                    'completion_tokens' => 30,
                ]);
        });

        $bendahara = $this->makeUser('bendahara', 'bendahara_narasi');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        $ta = TahunAjaran::current();

        SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => $ta, 'bulan' => 1,
            'nominal' => 150000, 'status' => 'menunggu',
        ]);

        $response = $this->actingAs($bendahara)->postJson(route('keuangan.bendahara-ai.wawasan.narasi'), [
            'tahun_ajaran' => $ta,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['data' => ['antrian', 'keterlambatan', 'poin_narasi'], 'answer']);
    }

    public function test_guru_dilarang_akses_wawasan(): void
    {
        $guru = $this->makeUser('guru', 'guru_wawasan');
        $this->actingAs($guru)->get(route('keuangan.bendahara-ai.wawasan'))->assertForbidden();
    }

    // ─── C2: Ekspor paket verifikasi ─────────────────────────────────────

    public function test_export_excel_paket_verifikasi(): void
    {
        Excel::fake();

        $bendahara = $this->makeUser('bendahara', 'bendahara_export');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        $ta = TahunAjaran::current();

        SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => $ta, 'bulan' => 1,
            'nominal' => 150000, 'status' => 'menunggu',
        ]);

        $this->actingAs($bendahara)
            ->get(route('keuangan.bendahara-ai.export-paket', ['ta' => $ta, 'format' => 'excel']))
            ->assertOk();

        Excel::assertDownloaded('paket-verifikasi-spp-'.str_replace('/', '-', $ta).'.xlsx');
    }

    public function test_export_pdf_paket_verifikasi(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_pdf');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        $ta = TahunAjaran::current();

        SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => $ta, 'bulan' => 1,
            'nominal' => 175000, 'status' => 'terverifikasi',
        ]);

        $response = $this->actingAs($bendahara)
            ->get(route('keuangan.bendahara-ai.export-paket', ['ta' => $ta, 'format' => 'pdf']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_export_filter_status_tidak_valid_ditolak(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_export_bad');
        $ta = TahunAjaran::current();

        $this->actingAs($bendahara)
            ->get(route('keuangan.bendahara-ai.export-paket', ['ta' => $ta, 'status' => 'hack']))
            ->assertStatus(422);
    }
}
