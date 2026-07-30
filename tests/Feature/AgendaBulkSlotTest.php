<?php

namespace Tests\Feature;

use App\Models\Agenda;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Pelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bug nyata terukur: /agenda (riwayat N hari guru) & /agenda/rekap (admin, rentang custom)
 * sempat memicu ratusan query — slotHari() dipanggil di dalam loop HARIAN, tiap panggilan
 * = 2 query (jadwal hari itu + agenda tanggal itu). Terukur 177 query di /agenda/rekap utk
 * rentang sebulan. Fix: slotHariBulk() memuat jadwal (per hari-dlm-minggu, TAK berubah
 * per tanggal) & agenda (whereBetween) SEKALI utk seluruh rentang, lalu dikelompokkan di PHP.
 * Test ini mengunci baik KEBENARAN datanya maupun jumlah query-nya (harus tetap sama brp pun
 * panjang rentang tanggalnya).
 */
class AgendaBulkSlotTest extends TestCase
{
    use RefreshDatabase;

    private function buatGuruDenganJadwal(): array
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $userGuru = User::create([
            'username' => 'guru_agenda_bulk',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        $guru = Guru::create([
            'id_login' => $userGuru->uuid,
            'nama' => 'Guru Agenda Bulk',
            'nik' => 'GAB001',
            'face_descriptor' => [array_map(fn ($i) => $i % 2 === 0 ? 1.0 : -1.0, range(0, 63))],
        ]);

        // Jadwal tetap: mengajar tiap Senin (hari=1) & Rabu (hari=3), 07:00-08:00.
        Jadwal::create([
            'id_kelas' => $kelas->uuid, 'hari' => 1, 'jam_mulai' => '07:00', 'jam_selesai' => '08:00',
            'id_pelajaran' => $pelajaran->uuid, 'id_guru' => $guru->uuid,
        ]);
        Jadwal::create([
            'id_kelas' => $kelas->uuid, 'hari' => 3, 'jam_mulai' => '07:00', 'jam_selesai' => '08:00',
            'id_pelajaran' => $pelajaran->uuid, 'id_guru' => $guru->uuid,
        ]);

        return [$guru, $kelas, $pelajaran, $userGuru];
    }

    public function test_riwayat_jadwal_guru_konsisten_dgn_jumlah_slot_terjadwal(): void
    {
        [$guru, $kelas, $pelajaran, $userGuru] = $this->buatGuruDenganJadwal();

        // Isi 1 agenda pada salah satu Senin dlm 14 hari terakhir, supaya bisa dicek slot
        // itu benar2 muncul dgn status "sudah terisi".
        $seninTerdekat = now()->startOfDay();
        while ($seninTerdekat->dayOfWeekIso !== 1) {
            $seninTerdekat->subDay();
        }
        Agenda::create([
            'tanggal' => $seninTerdekat->toDateString(), 'id_guru' => $guru->uuid,
            'id_kelas' => $kelas->uuid, 'id_pelajaran' => $pelajaran->uuid,
            'pembahasan' => 'Aljabar', 'metode' => 'Ceramah', 'proses' => 'selesai',
            'kegiatan' => 'Latihan soal', 'kendala' => '-', 'validasi' => 'belum', 'semester' => 1,
        ]);

        $response = $this->actingAs($userGuru)->get('/agenda?hari=14')->assertOk();
        $response->assertSee('Matematika');
        // Tanggal Senin yg sudah diisi harus menampilkan pembahasannya (bukti data slot benar).
        $response->assertSee('Aljabar');
    }

    public function test_riwayat_jadwal_jumlah_query_tidak_naik_dari_7_ke_30_hari(): void
    {
        [$guru, , , $userGuru] = $this->buatGuruDenganJadwal();
        $this->actingAs($userGuru);

        $countQuery = function (int $hari) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get("/agenda?hari={$hari}")->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $countQuery(7); // pemanasan (efek samping auth request pertama)
        $with7Hari = $countQuery(7);
        $with30Hari = $countQuery(30);

        $this->assertSame(
            $with7Hari,
            $with30Hari,
            'Jumlah query /agenda harus SAMA persis walau rentang naik dari 7 ke 30 hari — kalau naik, N+1 balik lagi.'
        );
    }

    public function test_rekap_admin_jumlah_query_tidak_naik_seiring_panjang_rentang(): void
    {
        [$guru] = $this->buatGuruDenganJadwal();
        $admin = User::create([
            'username' => 'admin_agenda_bulk',
            'password' => Hash::make('password'),
            'access' => 'superadmin',
        ]);
        $this->actingAs($admin);

        $countQuery = function (string $dari, string $sampai) use ($guru) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get("/agenda/rekap?guru={$guru->uuid}&dari={$dari}&sampai={$sampai}")->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $awal = now()->startOfMonth()->toDateString();
        $seminggu = now()->startOfMonth()->addDays(6)->toDateString();
        $sebulan = now()->startOfMonth()->addDays(29)->toDateString();

        $countQuery($awal, $seminggu); // pemanasan
        $with1Minggu = $countQuery($awal, $seminggu);
        $with1Bulan = $countQuery($awal, $sebulan);

        $this->assertSame(
            $with1Minggu,
            $with1Bulan,
            'Jumlah query /agenda/rekap harus SAMA persis walau rentang naik dari 1 minggu ke 1 bulan — kalau naik, N+1 balik lagi.'
        );
    }
}
