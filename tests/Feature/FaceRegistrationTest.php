<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Walikelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['username' => 'face_admin'],
            [
                'password' => Hash::make('password'),
                'access'   => 'superadmin',
            ]
        );
    }

    private function studentUser(): array
    {
        $user = User::create([
            'username' => 'face_student',
            'password' => Hash::make('password'),
            'access'   => 'siswa',
        ]);

        $siswa = Siswa::create([
            'id_login' => $user->getKey(),
            'nama'     => 'Siswa Face',
            'nis'      => 'FACE001',
            'jk'       => 'L',
        ]);

        return [$user, $siswa];
    }

    /** Guru yang jadi wali kelas $kelas. */
    private function walikelasUser(Kelas $kelas): User
    {
        $user = User::create([
            'username' => 'face_walikelas_' . $kelas->uuid,
            'password' => Hash::make('password'),
            'access'   => 'guru',
        ]);
        $guru = Guru::create([
            'id_login'        => $user->getKey(),
            'nama'            => 'Wali Kelas Face',
            'nik'             => 'WKFACE' . $kelas->uuid,
            // guru sendiri wajib sudah daftar wajah, kalau tidak EnsureFaceRegistered
            // akan redirect ke /wajah-saya sebelum sempat menyentuh route yang diuji.
            // Vektor descriptors() siswa konstan di semua dimensi (arah selalu sama persis
            // setelah dinormalisasi, berapa pun skalanya) — cosine similarity-nya SELALU 1.0
            // terhadap vektor konstan lain, jadi tetap kedeteksi "wajah ganda" walau skalanya beda.
            // Pola alternating +1/-1 di sini punya ARAH yang beda jauh, supaya tidak match.
            'face_descriptor' => [array_map(fn ($i) => $i % 2 === 0 ? 1.0 : -1.0, range(0, 63))],
        ]);
        Walikelas::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru->uuid]);

        return $user;
    }

    private function descriptors(int $count): array
    {
        $descriptors = [];
        for ($sample = 1; $sample <= $count; $sample++) {
            $descriptors[] = array_fill(0, 64, $sample / 100);
        }

        return $descriptors;
    }

    public function test_wajah_saya_menolak_kurang_dari_tiga_sampel(): void
    {
        [$user, $siswa] = $this->studentUser();

        $this->actingAs($user)
            ->postJson('/wajah-saya', [
                'descriptors' => $this->descriptors(2),
                'photo'       => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('descriptors');

        $this->assertNull($siswa->fresh()->face_descriptor);
    }

    public function test_wajah_saya_menyimpan_tiga_sampel(): void
    {
        [$user, $siswa] = $this->studentUser();

        $this->actingAs($user)
            ->postJson('/wajah-saya', [
                'descriptors' => $this->descriptors(3),
                'photo'       => null,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(3, $siswa->fresh()->face_descriptor);
    }

    public function test_admin_registrasi_wajah_siswa_wajib_tiga_sampel(): void
    {
        $siswa = Siswa::create([
            'nama' => 'Siswa Admin Face',
            'nis'  => 'FACE002',
            'jk'   => 'P',
        ]);

        $this->actingAs($this->admin())
            ->postJson("/siswa/{$siswa->uuid}/wajah", [
                'descriptors' => $this->descriptors(2),
                'photo'       => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('descriptors');

        $this->actingAs($this->admin())
            ->postJson("/siswa/{$siswa->uuid}/wajah", [
                'descriptors' => $this->descriptors(3),
                'photo'       => null,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(3, $siswa->fresh()->face_descriptor);
    }

    public function test_admin_registrasi_wajah_guru_wajib_tiga_sampel(): void
    {
        $guru = Guru::create([
            'nama' => 'Guru Face',
            'nik'  => 'GFACE001',
            'jk'   => 'L',
        ]);

        $this->actingAs($this->admin())
            ->postJson("/guru/{$guru->uuid}/wajah", [
                'descriptors' => $this->descriptors(2),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('descriptors');

        $this->actingAs($this->admin())
            ->postJson("/guru/{$guru->uuid}/wajah", [
                'descriptors' => $this->descriptors(3),
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(3, $guru->fresh()->face_descriptor);
    }

    public function test_walikelas_bisa_registrasi_wajah_siswa_kelasnya(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'C']);
        $wali = $this->walikelasUser($kelas);
        $siswa = Siswa::create(['nama' => 'Siswa Kelas Wali', 'nis' => 'FACE003', 'jk' => 'L', 'id_kelas' => $kelas->uuid]);

        $this->actingAs($wali)
            ->postJson("/siswa/{$siswa->uuid}/wajah", [
                'descriptors' => $this->descriptors(3),
                'photo'       => null,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(3, $siswa->fresh()->face_descriptor);
    }

    public function test_walikelas_tidak_bisa_registrasi_wajah_siswa_kelas_lain(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'D']);
        $kelasLain = Kelas::create(['tingkat' => 7, 'kelas' => 'E']);
        $wali = $this->walikelasUser($kelas);
        $siswaLain = Siswa::create(['nama' => 'Siswa Kelas Lain', 'nis' => 'FACE004', 'jk' => 'P', 'id_kelas' => $kelasLain->uuid]);

        $this->actingAs($wali)
            ->postJson("/siswa/{$siswaLain->uuid}/wajah", [
                'descriptors' => $this->descriptors(3),
                'photo'       => null,
            ])
            ->assertForbidden();

        $this->assertNull($siswaLain->fresh()->face_descriptor);
    }

    /** 1x1 PNG transparan — dipakai membuktikan foto BENAR-BENAR tersimpan (bukan cuma cek non-null path lama). */
    private function fotoDataUrl(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }

    public function test_registrasi_ulang_lewat_insightface_tetap_perbarui_foto_profil(): void
    {
        // Regresi: siswa yg SUDAH terdaftar (mis. lewat Human.js, face_registered_at & face_photo
        // sudah terisi), lalu mesin dipindah ke InsightFace & didaftar ULANG — foto profil dulu
        // TIDAK PERNAH ikut diperbarui (guard lama hanya mengisi foto saat descCol==='face_descriptor'
        // ATAU pendaftaran PERTAMA kali), padahal client sudah mengirim foto baru yg valid.
        $siswaAwal = Siswa::create([
            'nama' => 'Siswa Foto Lama', 'nis' => 'FACE005', 'jk' => 'L',
            'face_descriptor' => $this->descriptors(3),
            'face_registered_at' => now()->subDays(30),
            'face_photo' => 'faces/placeholder_20260101000000.jpg',
        ]);

        Setting::set('face_engine', 'insightface');

        $this->actingAs($this->admin())
            ->postJson("/siswa/{$siswaAwal->uuid}/wajah", [
                'descriptors' => $this->descriptors(3),
                'photo'       => $this->fotoDataUrl(),
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $siswaAwal->refresh();
        $this->assertNotNull($siswaAwal->face_descriptor_if, 'Descriptor InsightFace harus tersimpan.');
        $this->assertNotSame('faces/placeholder_20260101000000.jpg', $siswaAwal->face_photo, 'Foto profil harus diperbarui ke foto baru, bukan tetap foto lama.');
        $this->assertNotNull($siswaAwal->face_photo);
        $this->assertTrue($siswaAwal->face_registered_at->greaterThan(now()->subMinute()), 'Tanggal daftar harus ikut diperbarui ke waktu registrasi ulang.');
    }

    public function test_registrasi_ulang_menghapus_file_foto_lama_sebelum_menyimpan_yang_baru(): void
    {
        // Diminta user: foto lama dihapus DULU, baru foto baru disimpan (App\Support\FaceMatch::
        // saveFromDataUrl) — supaya storage faces/ tak menumpuk file lama tiap registrasi ulang.
        Storage::fake('public');

        $siswaAwal = Siswa::create([
            'nama' => 'Siswa Foto Dihapus', 'nis' => 'FACE006', 'jk' => 'P',
            'face_descriptor' => $this->descriptors(3),
            'face_registered_at' => now()->subDays(10),
        ]);
        // Path harus match pola faces/{uuid}_{14digit}.ext (App\Support\FaceMatch::isValidStoredPath)
        // supaya benar2 dianggap file lama milik siswa ini & memicu penghapusan.
        $oldPath = 'faces/' . $siswaAwal->uuid . '_20260101000000.jpg';
        Storage::disk('public')->put($oldPath, 'foto-lama');
        $siswaAwal->update(['face_photo' => $oldPath]);

        $this->actingAs($this->admin())
            ->postJson("/siswa/{$siswaAwal->uuid}/wajah", [
                'descriptors' => $this->descriptors(3),
                'photo'       => $this->fotoDataUrl(),
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $siswaAwal->refresh();
        Storage::disk('public')->assertMissing($oldPath);
        $this->assertNotNull($siswaAwal->face_photo);
        Storage::disk('public')->assertExists($siswaAwal->face_photo);
    }
}