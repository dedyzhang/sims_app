<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Setting;
use App\Models\User;
use App\Models\Walikelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kartu ID Guru: generate PDF kartu identitas otomatis dari data guru —
 * jabatan mengikuti role akun, foto diunggah admin, QR berisi NIP/NIK.
 */
class KartuGuruTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Guru $guru;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'SMP Test Kartu']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'wajah']);

        $this->admin = User::create([
            'username' => 'admin_kartu_guru',
            'password' => Hash::make('x'),
            'access' => 'superadmin',
        ]);

        $guruUser = User::create([
            'username' => 'guru_kartu',
            'password' => Hash::make('x'),
            'access' => 'guru',
        ]);
        $this->guru = Guru::create([
            'id_login' => $guruUser->uuid,
            'nama' => 'Guru Kartu Test',
            'nik' => '9001',
            'nip' => '198801012020121001',
            'jk' => 'L',
            // tanpa wajah terdaftar, middleware paksa-daftar-wajah me-redirect sebelum cek izin
            'face_descriptor' => [0.1],
        ]);
    }

    public function test_halaman_kelola_tampil_dengan_jabatan_dari_role(): void
    {
        $this->actingAs($this->admin)
            ->get(route('kartu-guru.kelola'))
            ->assertOk()
            ->assertSee('Kartu ID Guru', false)
            ->assertSee('Guru Kartu Test', false)
            ->assertSee('GURU', false); // teks background sesuai role
    }

    public function test_guru_biasa_tidak_boleh_akses(): void
    {
        $guruUser = User::where('username', 'guru_kartu')->first();
        $this->actingAs($guruUser)->get(route('kartu-guru.kelola'))->assertForbidden();
        $this->actingAs($guruUser)->get(route('kartu-guru.lihat', $this->guru->uuid))->assertForbidden();
    }

    public function test_unggah_dan_hapus_foto_guru(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('kartu-guru.foto', $this->guru->uuid), [
                'foto' => UploadedFile::fake()->image('pasfoto.jpg', 400, 500),
            ])
            ->assertRedirect();

        $this->guru->refresh();
        $this->assertNotNull($this->guru->foto);
        Storage::disk('public')->assertExists($this->guru->foto);

        $path = $this->guru->foto;
        $this->actingAs($this->admin)
            ->delete(route('kartu-guru.foto.hapus', $this->guru->uuid))
            ->assertRedirect();

        $this->guru->refresh();
        $this->assertNull($this->guru->foto);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_unggah_foto_besar_otomatis_dikompres_dan_diresize(): void
    {
        // Simulasi foto langsung dari kamera ponsel (resolusi besar) — HARUS diresize ke
        // FotoKartu::MAX_DIM utk mencegah "Cetak Semua" lambat saat banyak guru sekaligus.
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('kartu-guru.foto', $this->guru->uuid), [
                'foto' => UploadedFile::fake()->image('kamera-hp.jpg', 3000, 4000),
            ])
            ->assertRedirect();

        $this->guru->refresh();
        $stored = Storage::disk('public')->get($this->guru->foto);
        [$w, $h] = getimagesizefromstring($stored);

        $this->assertLessThanOrEqual(\App\Support\FotoKartu::MAX_DIM, max($w, $h));
        // Rasio 3000:4000 (3:4) harus tetap terjaga (bukan dipotong/diregang).
        $this->assertEqualsWithDelta(3 / 4, $w / $h, 0.01);
    }

    public function test_unggah_foto_png_mempertahankan_transparansi(): void
    {
        Storage::fake('public');

        // Bikin PNG transparan asli (spt cutout admin) — fake()->image() Laravel selalu
        // menghasilkan JPEG solid, jadi bangun manual via GD utk uji alpha sungguhan.
        $im = imagecreatetruecolor(500, 600);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparent);
        imagealphablending($im, true);
        $opaque = imagecolorallocate($im, 200, 50, 50);
        imagefilledellipse($im, 250, 200, 200, 200, $opaque);
        ob_start();
        imagepng($im);
        $pngBinary = ob_get_clean();
        imagedestroy($im);

        $tmp = tempnam(sys_get_temp_dir(), 'kg').'.png';
        file_put_contents($tmp, $pngBinary);
        $uploaded = new UploadedFile($tmp, 'cutout.png', 'image/png', null, true);

        $this->actingAs($this->admin)
            ->post(route('kartu-guru.foto', $this->guru->uuid), ['foto' => $uploaded])
            ->assertRedirect();

        $this->guru->refresh();
        $this->assertStringEndsWith('.png', $this->guru->foto);
        $stored = Storage::disk('public')->get($this->guru->foto);
        $img = imagecreatefromstring($stored);
        $rgba = imagecolorsforindex($img, imagecolorat($img, 5, 5)); // sudut = area transparan
        $this->assertSame(127, $rgba['alpha'], 'sudut PNG harus tetap transparan (alpha 127) setelah diresize');
        imagedestroy($img);
        @unlink($tmp);
    }

    public function test_unggah_menolak_file_bukan_gambar(): void
    {
        $this->actingAs($this->admin)
            ->post(route('kartu-guru.foto', $this->guru->uuid), [
                'foto' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('foto');
    }

    public function test_pdf_kartu_tunggal_dan_massal_tergenerate(): void
    {
        $res = $this->actingAs($this->admin)->get(route('kartu-guru.lihat', $this->guru->uuid));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));

        // "Cetak Semua" langsung diunduh (attachment), bukan ditampilkan inline — dokumen bisa
        // berhalaman banyak dgn foto (walau sudah dikompres), lebih nyaman diunduh & di-print
        // offline drpd menunggu browser merender PDF besar.
        $res = $this->actingAs($this->admin)->get(route('kartu-guru.cetak'));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringContainsString('attachment', $res->headers->get('content-disposition'));
    }

    public function test_qr_dipindah_ke_sisi_belakang_kartu_utk_cetak_bolak_balik(): void
    {
        // Sisi depan TIDAK boleh lagi merender badge QR-nya sendiri (dipindah ke _card-back).
        // (Nama variabel $card['qrUri'] boleh tetap disebut di komentar dok bagian atas file.)
        $front = file_get_contents(resource_path('views/kartu-guru/_card.blade.php'));
        $this->assertStringNotContainsString("\$card['qrUri']", $front);
        $this->assertStringNotContainsString('kg-qr"', $front);

        // Sisi belakang berisi QR besar + instruksi.
        $back = file_get_contents(resource_path('views/kartu-guru/_card-back.blade.php'));
        $this->assertStringContainsString("qrUri", $back);
        $this->assertStringContainsString('kg-back-qr', $back);

        // PDF individual: 2 halaman (depan lalu belakang) via page-break-after.
        $pdfView = file_get_contents(resource_path('views/kartu-guru/pdf.blade.php'));
        $this->assertStringContainsString('page-break-after', $pdfView);
        $this->assertStringContainsString("_card-back", $pdfView);

        // Cetak massal: halaman depan semua dulu, baru halaman belakang dgn urutan slot dibalik
        // (array_reverse) per baris — supaya sejajar dgn depannya stlh duplex "flip on long edge".
        $massal = file_get_contents(resource_path('views/kartu-guru/cetak-massal.blade.php'));
        $this->assertStringContainsString('_card-back', $massal);
        $this->assertStringContainsString('array_reverse', $massal);
    }

    public function test_jabatan_kepala_sekolah_dan_walikelas_ikut_role(): void
    {
        $kepalaUser = User::create([
            'username' => 'kepala_kartu',
            'password' => Hash::make('x'),
            'access' => 'kepala',
        ]);
        $kepala = Guru::create([
            'id_login' => $kepalaUser->uuid,
            'nama' => 'Kepala Kartu Test',
            'nik' => '9002',
            'jk' => 'P',
        ]);
        $kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'B']);
        Walikelas::create(['id_guru' => $this->guru->uuid, 'id_kelas' => $kelas->uuid]);

        $res = $this->actingAs($this->admin)->get(route('kartu-guru.kelola'))->assertOk();
        $res->assertSee('Kepala Sekolah', false)
            ->assertSee('KEPSEK', false)
            ->assertSee('Wali Kelas 8B', false);
    }
}
