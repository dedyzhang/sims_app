<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Optimasi #7: dua query Absensi di DashboardController::buildSiswaWidget() (rekap
 * bulan-berjalan + riwayat 60 hari utk streak) dulu ditarik terpisah dgn rentang
 * tumpang-tindih (data sama 2x). Kini digabung jadi 1 query lalu di-filter di memori.
 * Diuji langsung di method-nya (via reflection) — bukan lewat HTTP dashboard penuh yg
 * menyeret banyak widget lain (piket/AI-quota) tak relevan dgn perubahan ini.
 */
class DashboardSiswaAbsensiWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function widget(Siswa $siswa): array
    {
        $ctrl = app(DashboardController::class);
        $m = new \ReflectionMethod($ctrl, 'buildSiswaWidget');
        $m->setAccessible(true);

        return $m->invoke($ctrl, $siswa);
    }

    public function test_rekap_bulan_dan_streak_benar_dan_query_absensi_tak_dobel(): void
    {
        // Bekukan "hari ini" ke Rabu 2026-08-19 (weekday) supaya streak deterministik.
        Carbon::setTestNow(Carbon::parse('2026-08-19 09:00:00'));

        Semester::create(['semester' => 1, 'tahun' => '2026/2027', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $user = User::create(['username' => 'siswa_dash_widget', 'password' => Hash::make('x'), 'access' => 'siswa']);
        $siswa = Siswa::create([
            'id_login' => $user->uuid, 'id_kelas' => $kelas->uuid,
            'nama' => 'Siswa Dash Widget', 'nis' => 'DW-001', 'jk' => 'L', 'face_descriptor' => [0.1],
        ]);

        // Bulan berjalan (Agustus): 3 hadir berturut mundur dari hari ini (17 Sen, 18 Sel, 19 Rab)
        // + 1 izin. Streak harus = 3.
        foreach ([
            '2026-08-17' => 'hadir',
            '2026-08-18' => 'hadir',
            '2026-08-19' => 'hadir',
            '2026-08-10' => 'izin',
        ] as $tgl => $status) {
            Absensi::create(['id_siswa' => $siswa->uuid, 'id_kelas' => $kelas->uuid, 'tanggal' => $tgl, 'status' => $status]);
        }
        // Di luar bulan berjalan TAPI dalam 60 hari (Juli) — tak boleh masuk rekap bulan.
        Absensi::create(['id_siswa' => $siswa->uuid, 'id_kelas' => $kelas->uuid, 'tanggal' => '2026-07-15', 'status' => 'alpa']);

        DB::enableQueryLog();
        $widget = $this->widget($siswa->fresh());
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        // Rekap HANYA bulan berjalan (Agustus): 3 hadir, 1 izin, 0 alpa (alpa 15 Juli tak dihitung).
        $this->assertSame(3, $widget['rekapAbsensi']['hadir']);
        $this->assertSame(1, $widget['rekapAbsensi']['izin']);
        $this->assertSame(0, $widget['rekapAbsensi']['alpa'], 'Alpa 15 Juli di luar bulan berjalan tak boleh masuk rekap.');

        // Streak: 19,18,17 Agustus semua hadir & berturut (weekday) → 3.
        $this->assertSame(3, $widget['streakHadir']);

        // Optimasi #7: tabel absensi diquery utk widget ini hanya "hari ini" (->first()) +
        // satu rentang gabungan — TIDAK ADA lagi query bulan & 60-hari terpisah (dulu 3).
        $absensiSelects = array_filter(
            $log,
            fn ($q) => str_contains($q['query'], 'from "absensis"') || str_contains($q['query'], 'from `absensis`')
        );
        $this->assertLessThanOrEqual(
            2,
            count($absensiSelects),
            'Query tabel absensi utk widget siswa tak boleh > 2 (hari-ini + rentang gabungan) — dulu 3 krn rentang dobel.'
        );
    }
}
