<?php

namespace Tests\Feature;

use App\Jobs\SendFcmNotificationJob;
use App\Models\User;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\KategoriAset;
use App\Sarpras\Models\LaporanKerusakan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SarprasKerusakanNotificationTest extends TestCase
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

    public function test_laporan_kerusakan_terkirim_hanya_ke_waka_sarpras_dengan_payload_fcm_aman(): void
    {
        Queue::fake();

        $pelapor = $this->makeUser('guru', 'guru_lapor_kerusakan');
        $wakaSarpras = $this->makeUser('sarpras', 'waka_sarpras_penerima');
        $adminUmum = $this->makeUser('admin', 'admin_bukan_sarpras');
        $guruLain = $this->makeUser('guru', 'guru_bukan_penerima');

        $kategori = KategoriAset::create(['kode' => 'KAT-RUSAK', 'nama' => 'Elektronik']);
        $aset = Aset::create([
            'kode' => 'AC-7A',
            'nama' => 'AC Kelas 7A',
            'kategori_id' => $kategori->id,
            'kondisi' => 'baik',
            'status' => 'aktif',
            'nilai_perolehan' => 2500000,
            'tgl_perolehan' => '2026-01-01',
            'masa_manfaat_tahun' => 5,
        ]);
        $denah = Denah::create(['nama' => 'Gedung A']);
        $ruangan = DenahRuangan::create([
            'denah_id' => $denah->id,
            'kode' => '7A',
            'nama' => 'Kelas 7A',
            'status' => 'tersedia',
        ]);

        $deskripsiSensitif = 'AC kelas 7A tidak dingin, ada bunyi aneh sejak kemarin.';

        $this->actingAs($pelapor)->post('/sarpras/kerusakan', [
            'aset_id' => $aset->id,
            'ruangan_id' => $ruangan->id,
            'urgensi' => 'tinggi',
            'deskripsi' => $deskripsiSensitif,
        ])->assertRedirect();

        $laporan = LaporanKerusakan::query()->first();
        $this->assertNotNull($laporan);
        $this->assertSame($aset->id, $laporan->aset_id);
        $this->assertSame($ruangan->id, $laporan->ruangan_id);
        $this->assertSame($pelapor->uuid, $laporan->pelapor_id);
        $this->assertSame($deskripsiSensitif, $laporan->deskripsi);

        $this->assertSame(1, $wakaSarpras->fresh()->notifications()->count());
        $this->assertSame(0, $adminUmum->fresh()->notifications()->count());
        $this->assertSame(0, $guruLain->fresh()->notifications()->count());

        Queue::assertPushed(SendFcmNotificationJob::class, 1);
        Queue::assertPushed(SendFcmNotificationJob::class, function (SendFcmNotificationJob $job) use ($wakaSarpras, $deskripsiSensitif) {
            return $job->userUuid === $wakaSarpras->uuid
                && ($job->payload['type'] ?? null) === 'sarpras_kerusakan'
                && str_contains((string) ($job->payload['message'] ?? ''), 'Urgensi tinggi')
                && ! str_contains((string) ($job->payload['message'] ?? ''), $deskripsiSensitif)
                && ! array_key_exists('deskripsi', $job->payload)
                && ! array_key_exists('foto', $job->payload);
        });

        Queue::assertNotPushed(SendFcmNotificationJob::class, fn (SendFcmNotificationJob $job) => $job->userUuid === $adminUmum->uuid);
        Queue::assertNotPushed(SendFcmNotificationJob::class, fn (SendFcmNotificationJob $job) => $job->userUuid === $guruLain->uuid);
    }

    public function test_laporan_kerusakan_bisa_dikirim_tanpa_memilih_aset_atau_ruangan(): void
    {
        Queue::fake();

        $pelapor = $this->makeUser('guru', 'guru_lapor_tanpa_objek');
        $wakaSarpras = $this->makeUser('sarpras', 'waka_sarpras_tanpa_objek');

        $this->actingAs($pelapor)->post('/sarpras/kerusakan', [
            'aset_id' => '',
            'ruangan_id' => '',
            'urgensi' => 'sedang',
            'deskripsi' => 'Keterangan rusak saja, lokasi akan disusulkan.',
        ])->assertRedirect();

        $laporan = LaporanKerusakan::query()->first();
        $this->assertNotNull($laporan);
        $this->assertNull($laporan->aset_id);
        $this->assertNull($laporan->ruangan_id);
        $this->assertSame($pelapor->uuid, $laporan->pelapor_id);
        $this->assertSame('Keterangan rusak saja, lokasi akan disusulkan.', $laporan->deskripsi);

        $this->assertSame(1, $wakaSarpras->fresh()->notifications()->count());
        Queue::assertPushed(SendFcmNotificationJob::class, 1);
    }

    public function test_laporan_kerusakan_redirect_ke_detail_dan_foto_bisa_dirender(): void
    {
        Queue::fake();
        Storage::fake('public');

        $pelapor = $this->makeUser('guru', 'guru_lapor_foto_detail');
        $guruLain = $this->makeUser('guru', 'guru_tidak_boleh_lihat_foto');
        $this->makeUser('sarpras', 'waka_sarpras_foto_detail');

        $response = $this->actingAs($pelapor)->post('/sarpras/kerusakan', [
            'aset_id' => '',
            'ruangan_id' => '',
            'urgensi' => 'sedang',
            'deskripsi' => 'Lampu kelas berkedip dan perlu dicek.',
            'foto' => [
                UploadedFile::fake()->image('lampu-rusak.jpg', 800, 600),
            ],
        ]);

        $laporan = LaporanKerusakan::query()->with('foto')->first();
        $this->assertNotNull($laporan);
        $this->assertSame($laporan->id, $response->headers->get('Location') ? basename($response->headers->get('Location')) : null);
        $this->assertCount(1, $laporan->foto);

        $foto = $laporan->foto->first();
        Storage::disk('public')->assertExists($foto->foto_path);
        $this->assertNotEmpty($foto->url);
        $this->assertStringStartsWith('/sarpras/kerusakan-foto/', $foto->url);

        $this->actingAs($pelapor)
            ->get('/sarpras/kerusakan/' . $laporan->id)
            ->assertOk()
            ->assertSee($laporan->kode)
            ->assertSee($foto->url, false);

        $this->actingAs($pelapor)
            ->get($foto->url)
            ->assertOk()
            ->assertHeader('content-type', 'image/webp');

        $this->actingAs($guruLain)
            ->get($foto->url)
            ->assertNotFound();
    }

    public function test_detail_laporan_kerusakan_hilang_kembali_ke_daftar_dengan_pesan(): void
    {
        $pelapor = $this->makeUser('guru', 'guru_lapor_hilang');

        $this->actingAs($pelapor)
            ->get('/sarpras/kerusakan/00000000-0000-0000-0000-000000000404')
            ->assertRedirect('/sarpras/kerusakan')
            ->assertSessionHas('gagal', 'Laporan kerusakan tidak ditemukan atau sudah tidak tersedia.');
    }
}
