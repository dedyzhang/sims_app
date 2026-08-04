<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\GuruTidakHadir;
use App\Models\Jadwal;
use App\Models\JadwalPiket;
use App\Models\JamPelajaran;
use App\Models\Pelajaran;
use App\Models\PenugasanPengganti;
use App\Services\Piket\JamKosongService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MembangunSekolahGrupChat;
use Tests\TestCase;

class PiketPenugasanTest extends TestCase
{
    use RefreshDatabase, MembangunSekolahGrupChat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bangunSekolah();
    }

    public function test_guru_tersedia_mengikuti_id_jam_sekolah_dan_mengecualikan_konflik(): void
    {
        $tanggal = now()->toDateString();
        $hari = Carbon::parse($tanggal)->dayOfWeekIso;
        $jam = $this->buatJam($hari, 1, '07:00', '07:40');
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $free = Guru::create(['nama' => 'Guru Free']);
        $busy = Guru::create(['nama' => 'Guru Busy']);
        $absent = Guru::create(['nama' => 'Guru Absent']);
        $occupied = Guru::create(['nama' => 'Guru Occupied']);

        $slot = $this->buatJadwal($hari, $jam, $pelajaran, $this->kelas7a, $this->wali7b->guru);
        $this->buatJadwal($hari, $jam, $pelajaran, $this->kelas7b, $busy);
        // Jam ke sama tetapi id_jam berbeda harus dianggap slot berbeda.
        $jamLain = $this->buatJam($hari, 1, '08:00', '08:40');
        $this->buatJadwal($hari, $jamLain, $pelajaran, $this->kelas7b, $free);

        $absen = GuruTidakHadir::create([
            'id_guru' => $absent->uuid,
            'tanggal' => $tanggal,
            'sumber' => 'manual_piket',
            'alasan' => 'izin',
        ]);
        $slotLain = $this->buatJadwal($hari, $jam, $pelajaran, $this->kelas7b, $this->wali7a->guru);
        PenugasanPengganti::create([
            'id_guru_tidak_hadir' => $absen->uuid,
            'id_jadwal' => $slotLain->uuid,
            'id_guru_pengganti' => $occupied->uuid,
            'status' => 'ditugaskan',
        ]);

        $hasil = app(JamKosongService::class)->guruTersediaUntuk($slot, $tanggal)->pluck('uuid');

        $this->assertTrue($hasil->contains($free->uuid));
        $this->assertFalse($hasil->contains($busy->uuid));
        $this->assertFalse($hasil->contains($absent->uuid));
        $this->assertFalse($hasil->contains($occupied->uuid));
    }

    public function test_endpoint_assign_menolak_guru_yang_bentrok_dan_menerima_guru_kosong(): void
    {
        $tanggal = now()->toDateString();
        $hari = Carbon::parse($tanggal)->dayOfWeekIso;
        $jam = $this->buatJam($hari, 1, '07:00', '07:40');
        $pelajaran = Pelajaran::create(['nama' => 'IPA', 'kkm' => 75]);
        $busy = Guru::create(['nama' => 'Guru Bentrok']);
        $free = Guru::create(['nama' => 'Guru Pengganti']);
        $slot = $this->buatJadwal($hari, $jam, $pelajaran, $this->kelas7a, $this->wali7b->guru);
        $this->buatJadwal($hari, $jam, $pelajaran, $this->kelas7b, $busy);

        $absen = GuruTidakHadir::create([
            'id_guru' => $this->wali7b->guru->uuid,
            'tanggal' => $tanggal,
            'sumber' => 'manual_piket',
            'alasan' => 'sakit',
        ]);
        $penugasan = PenugasanPengganti::create([
            'id_guru_tidak_hadir' => $absen->uuid,
            'id_jadwal' => $slot->uuid,
            'status' => 'menunggu',
        ]);
        JadwalPiket::create(['id_guru' => $this->wali7a->guru->uuid, 'hari' => $hari, 'is_ketua' => true]);

        $this->actingAs($this->wali7b)
            ->postJson(route('piket.penugasan.assign', $penugasan), ['id_guru_pengganti' => $free->uuid])
            ->assertForbidden();

        $this->actingAs($this->wali7a)
            ->postJson(route('piket.penugasan.assign', $penugasan), ['id_guru_pengganti' => $busy->uuid])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Guru tersebut sedang mengajar, tidak hadir, atau sudah ditugaskan pada jam yang sama.');

        $this->actingAs($this->wali7a)
            ->postJson(route('piket.penugasan.assign', $penugasan), ['id_guru_pengganti' => $free->uuid])
            ->assertOk()
            ->assertJsonPath('status', 'ditugaskan')
            ->assertJsonPath('guru_pengisi', 'Guru Pengganti');
    }

    private function buatJam(int $hari, int $jamKe, string $mulai, string $selesai): JamPelajaran
    {
        return JamPelajaran::create([
            'hari' => $hari,
            'jam_ke' => $jamKe,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis' => 'pelajaran',
            'urutan' => $jamKe,
        ]);
    }

    private function buatJadwal(int $hari, JamPelajaran $jam, Pelajaran $pelajaran, $kelas, Guru $guru): Jadwal
    {
        return Jadwal::create([
            'id_kelas' => $kelas->uuid,
            'hari' => $hari,
            'id_jam' => $jam->uuid,
            'jam_ke' => $jam->jam_ke,
            'jam_mulai' => $jam->jam_mulai,
            'jam_selesai' => $jam->jam_selesai,
            'id_pelajaran' => $pelajaran->uuid,
            'id_guru' => $guru->uuid,
        ]);
    }
}
