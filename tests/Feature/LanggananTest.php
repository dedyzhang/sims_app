<?php

namespace Tests\Feature;

use App\Models\Langganan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LanggananTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $access, string $username): User
    {
        return User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access' => $access,
        ]);
    }

    private function buatLangganan(int $durasi = 3, ?string $mulai = null): Langganan
    {
        $mulaiPada = $mulai ? \Illuminate\Support\Carbon::parse($mulai) : now();

        return Langganan::create([
            'durasi_bulan' => $durasi,
            'mulai_pada' => $mulaiPada->toDateString(),
            'berakhir_pada' => $mulaiPada->copy()->addMonths($durasi)->toDateString(),
            'status' => 'aktif',
        ]);
    }

    // ─── FR-7: set durasi ─────────────────────────────────────────────────────

    public function test_superadmin_bisa_menetapkan_langganan_3_bulan(): void
    {
        $superadmin = $this->makeUser('superadmin', 'sa_langganan');

        $response = $this->actingAs($superadmin)->post('/langganan', [
            'durasi_bulan' => 3,
            'mulai_pada' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('langganan.index'));
        $langganan = Langganan::current();
        $this->assertSame(3, $langganan->durasi_bulan);
        $this->assertSame(
            now()->startOfDay()->addMonths(3)->toDateString(),
            $langganan->berakhir_pada->toDateString()
        );

        // M3: set 3 bulan hari ini → sisa ≈ 90–92 hari sesuai kalender.
        $sisa = Langganan::current()->sisaHari();
        $this->assertGreaterThanOrEqual(89, $sisa);
        $this->assertLessThanOrEqual(92, $sisa);
    }

    public function test_durasi_selain_3_6_12_ditolak(): void
    {
        $superadmin = $this->makeUser('superadmin', 'sa_durasi');

        $this->actingAs($superadmin)->post('/langganan', [
            'durasi_bulan' => 5,
            'mulai_pada' => now()->toDateString(),
        ])->assertSessionHasErrors('durasi_bulan');

        $this->assertDatabaseCount('langganan', 0);
    }

    public function test_role_lain_tidak_bisa_akses_halaman_langganan(): void
    {
        $admin = $this->makeUser('admin', 'admin_langganan');

        $this->actingAs($admin)->get('/langganan')->assertForbidden();
        $this->actingAs($admin)->post('/langganan', [
            'durasi_bulan' => 3,
            'mulai_pada' => now()->toDateString(),
        ])->assertForbidden();
    }

    // ─── FR-8: perpanjang ─────────────────────────────────────────────────────

    public function test_perpanjang_menambah_dari_tanggal_berakhir_bila_masih_aktif(): void
    {
        $superadmin = $this->makeUser('superadmin', 'sa_perpanjang');
        $langganan = $this->buatLangganan(3);
        $berakhirLama = $langganan->berakhir_pada->copy();

        $this->actingAs($superadmin)->post('/langganan/perpanjang', ['durasi_bulan' => 6])
            ->assertRedirect(route('langganan.index'));

        $this->assertSame(
            $berakhirLama->addMonths(6)->toDateString(),
            $langganan->fresh()->berakhir_pada->toDateString()
        );
    }

    public function test_perpanjang_dari_hari_ini_bila_sudah_kadaluarsa(): void
    {
        $superadmin = $this->makeUser('superadmin', 'sa_perpanjang2');
        $this->buatLangganan(3, now()->subMonths(4)->toDateString()); // berakhir ± 1 bulan lalu

        $this->actingAs($superadmin)->post('/langganan/perpanjang', ['durasi_bulan' => 3])
            ->assertRedirect(route('langganan.index'));

        $this->assertSame(
            now()->startOfDay()->addMonths(3)->toDateString(),
            Langganan::current()->berakhir_pada->toDateString()
        );
    }

    // ─── FR-9/FR-10: sisa hari & peringatan bertingkat ────────────────────────

    public function test_tingkat_peringatan_mengikuti_ambang(): void
    {
        $kasus = [
            [30, null],
            [14, 'info'],
            [7,  'kuning'],
            [3,  'merah'],
            [0,  'kadaluarsa'],
            [-5, 'kadaluarsa'],
        ];

        foreach ($kasus as [$sisa, $tingkat]) {
            $l = new Langganan([
                'durasi_bulan' => 3,
                'mulai_pada' => now()->subMonth()->toDateString(),
                'berakhir_pada' => now()->addDays($sisa)->toDateString(),
            ]);
            $this->assertSame($sisa, $l->sisaHari(), "sisa hari untuk offset {$sisa}");
            $this->assertSame($tingkat, $l->tingkatPeringatan(), "tingkat untuk sisa {$sisa}");
        }
    }

    public function test_total_hari_mengikuti_kalender_durasi_3_6_12(): void
    {
        foreach ([3, 6, 12] as $durasi) {
            $mulai = now()->startOfDay();
            $langganan = $this->buatLangganan($durasi, $mulai->toDateString());
            $expected = (int) $mulai->diffInDays($mulai->copy()->addMonths($durasi));

            $this->assertSame($expected, $langganan->totalHari(), "total hari untuk {$durasi} bulan");
            $this->assertSame($expected, Langganan::hariUntukDurasi($durasi, $mulai->toDateString()));
        }
    }

    public function test_halaman_langganan_menampilkan_sisa_dan_total_hari(): void
    {
        $superadmin = $this->makeUser('superadmin', 'sa_hari');
        $langganan = $this->buatLangganan(6);
        $sisa = $langganan->sisaHari();
        $total = $langganan->totalHari();

        $this->actingAs($superadmin)->get('/langganan')
            ->assertOk()
            ->assertSee("Langganan aktif · sisa {$sisa} hari", false)
            ->assertSee("Durasi 6 bulan ({$total} hari)", false)
            ->assertSee('Pratinjau masa langganan')
            ->assertSee('3 bulan')
            ->assertSee('6 bulan')
            ->assertSee('12 bulan');
    }

    public function test_halaman_langganan_menampilkan_peringatan_expired(): void
    {
        $superadmin = $this->makeUser('superadmin', 'sa_peringatan');
        // sisa ± 3 hari → tingkat merah
        $this->buatLangganan(3, now()->subMonths(3)->addDays(3)->toDateString());

        $this->actingAs($superadmin)->get('/langganan')
            ->assertOk()
            ->assertSee('Peringatan kritis — sisa ≤ 3 hari')
            ->assertSee('akan berakhir dalam');
    }

    public function test_banner_sisa_hari_tampil_untuk_superadmin(): void
    {
        $superadmin = $this->makeUser('superadmin', 'sa_banner');
        $this->buatLangganan(3, now()->subMonths(3)->addDays(7)->toDateString()); // sisa ± 7 hari

        $this->actingAs($superadmin)->get('/masukan')
            ->assertOk()
            ->assertSee('Langganan SIMS')
            ->assertSee('akan berakhir')
            ->assertSee('sisa')
            ->assertSee('hari');
    }

    public function test_banner_tidak_tampil_untuk_non_superadmin(): void
    {
        $guru = $this->makeUser('admin', 'admin_banner');
        $this->buatLangganan(3, now()->subMonths(3)->addDays(7)->toDateString());

        $this->actingAs($guru)->get('/masukan')
            ->assertOk()
            ->assertDontSee('akan berakhir');
    }

    public function test_flash_setelah_set_menampilkan_hari(): void
    {
        $superadmin = $this->makeUser('superadmin', 'sa_flash');

        $response = $this->actingAs($superadmin)->post('/langganan', [
            'durasi_bulan' => 3,
            'mulai_pada' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('langganan.index'));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('hari', session('success'));
        $this->assertStringContainsString('sisa', session('success'));
    }

    // ─── FR-11: penguncian saat kadaluarsa ────────────────────────────────────

    public function test_non_superadmin_terkunci_saat_kadaluarsa(): void
    {
        $admin = $this->makeUser('admin', 'admin_kunci');
        $this->buatLangganan(3, now()->subMonths(4)->toDateString());

        $this->actingAs($admin)->get('/masukan')
            ->assertRedirect(route('langganan.berakhir'));

        $this->actingAs($admin)->get('/langganan-berakhir')
            ->assertOk()
            ->assertSee('Langganan SIMS Berakhir');
    }

    public function test_superadmin_tetap_bisa_masuk_dan_memperpanjang_saat_kadaluarsa(): void
    {
        $superadmin = $this->makeUser('superadmin', 'sa_kunci');
        $this->buatLangganan(3, now()->subMonths(4)->toDateString());

        $this->actingAs($superadmin)->get('/masukan')->assertOk();
        $this->actingAs($superadmin)->get('/langganan')->assertOk();

        $this->actingAs($superadmin)->post('/langganan/perpanjang', ['durasi_bulan' => 12])
            ->assertRedirect(route('langganan.index'));
        $this->assertFalse(Langganan::current()->kadaluarsa());
    }

    public function test_halaman_login_tetap_bisa_diakses_saat_kadaluarsa(): void
    {
        $this->buatLangganan(3, now()->subMonths(4)->toDateString());

        $this->get('/login')->assertOk();
    }

    public function test_app_tidak_terkunci_bila_belum_ada_langganan(): void
    {
        $admin = $this->makeUser('admin', 'admin_tanpa_lisensi');

        $this->actingAs($admin)->get('/masukan')->assertOk();
    }

    public function test_scheduler_sinkronkan_status_menandai_langganan_kadaluarsa(): void
    {
        $langganan = $this->buatLangganan(3, now()->subMonths(4)->toDateString());

        $this->assertSame('aktif', $langganan->fresh()->status);
        $this->assertSame(1, Langganan::sinkronkanStatusKadaluarsa());
        $this->assertSame(
            Langganan::STATUS_KADALUARSA,
            $langganan->fresh()->status
        );
    }
}
