<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FaceScanFallbackTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Kelas $kelas;
    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'wajah']);
        Setting::set('kaih_wajib_sebelum_absen', '0');
        Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);

        $this->admin = User::create([
            'username' => 'admin_face_scan',
            'password' => Hash::make('x'),
            'access' => 'superadmin',
        ]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->siswa = Siswa::create([
            'id_kelas' => $this->kelas->uuid,
            'nama' => 'Siswa Barcode',
            'nis' => '123456',
            'jk' => 'L',
            'face_descriptor' => [[0.1, 0.2]],
            'face_registered_at' => now(),
        ]);
    }

    public function test_scan_page_includes_kelas_filter_and_barcode_fallback(): void
    {
        $this->actingAs($this->admin)
            ->get(route('absensi.scan', ['kelas' => $this->kelas->uuid]))
            ->assertOk()
            ->assertSee('Absensi Hadir', false)
            ->assertSee('mark-barcode', false)
            ->assertSee('Kartu tidak terbaca', false);
    }

    public function test_face_telemetry_accepts_reason(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('absensi.faceTelemetry'), [
                'reason' => 'small_margin',
                'top1' => 0.7,
                'gap' => 0.04,
                'support' => 1,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_mark_by_barcode_nis_marks_hadir(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('absensi.markBarcode'), [
                'barcode' => '123456',
                'tanggal' => now()->toDateString(),
                'id_kelas' => $this->kelas->uuid,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('via', 'barcode');

        $this->assertDatabaseHas('absensis', [
            'id_siswa' => $this->siswa->uuid,
            'status' => 'hadir',
            'keterangan' => 'Kartu pelajar (barcode)',
        ]);
    }

    public function test_mark_by_barcode_uuid_marks_hadir(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('absensi.markBarcode'), [
                'barcode' => $this->siswa->uuid,
                'tanggal' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('via', 'barcode')
            ->assertJsonPath('kelas', '7A');
    }

    public function test_mark_by_barcode_kedua_kali_ditolak_sekali_saja_per_hari(): void
    {
        // Kartu hanya berlaku SEKALI per hari — dulu scan ulang jatuh ke mark() yang
        // meng-update baris lama dan tetap membalas sukses (bisa "absen" berkali-kali).
        $payload = [
            'barcode' => '123456',
            'tanggal' => now()->toDateString(),
        ];

        $this->actingAs($this->admin)->postJson(route('absensi.markBarcode'), $payload)
            ->assertOk()->assertJsonPath('success', true);

        $this->actingAs($this->admin)->postJson(route('absensi.markBarcode'), $payload)
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('uuid', $this->siswa->uuid);

        // Setelah admin membatalkan absensinya, kartu boleh dipakai lagi.
        $this->actingAs($this->admin)->postJson(route('absensi.cancel'), [
            'id_siswa' => $this->siswa->uuid,
            'tanggal'  => now()->toDateString(),
        ])->assertOk();

        $this->actingAs($this->admin)->postJson(route('absensi.markBarcode'), $payload)
            ->assertOk()->assertJsonPath('success', true);
    }

    public function test_mark_by_barcode_rejects_when_siswa_sudah_izin(): void
    {
        \App\Models\Absensi::create([
            'id_siswa' => $this->siswa->uuid,
            'id_kelas' => $this->kelas->uuid,
            'tanggal' => now()->toDateString(),
            'status' => 'izin',
            'keterangan' => 'Manual',
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('absensi.markBarcode'), [
                'barcode' => '123456',
                'tanggal' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('success', false);
    }
}
