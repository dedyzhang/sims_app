<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\User;
use App\Support\FotoKartu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * kartu-guru:optimize-foto — merapikan foto guru yang TERLANJUR diunggah mentah sebelum
 * FotoKartu::resize() dipasang di fotoStore(), supaya "Cetak Semua" tidak lambat.
 *
 * CATATAN: TIDAK pakai Storage::fake('public') di sini — controller/command ini membaca file
 * lewat storage_path('app/public/...') LANGSUNG (pola yang sama dgn KartuGuruController::
 * fileToDataUri(), bukan via facade Storage::disk()), sehingga Storage::fake() (yang mengarahkan
 * disk 'public' ke folder testing terpisah) tidak akan pernah "terlihat" oleh path mentah itu —
 * pakai file NYATA di bawah prefix ZZTEST_ lalu dibersihkan di tearDown(), spt pola tinker manual
 * yg dipakai sesi ini utk verifikasi browser.
 */
class GuruOptimizeFotoCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $createdPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->createdPaths as $p) {
            @unlink(storage_path('app/public/'.$p));
        }
        parent::tearDown();
    }

    private function makeGuruWithRawPhoto(string $nama, int $w, int $h): Guru
    {
        $user = User::create(['username' => 'u_'.Str::random(8), 'password' => Hash::make('x'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $user->uuid, 'nama' => $nama, 'nik' => Str::random(6), 'jk' => 'L']);

        // Piksel ACAK (bukan warna solid) — JPEG mengompres area rata warna sampai sangat kecil
        // walau resolusinya besar, jadi fixture "foto mentah" harus bising spt foto kamera asli
        // supaya benar2 di atas ambang 300KB dan menguji jalur kompresi yang sebenarnya.
        $im = imagecreatetruecolor($w, $h);
        mt_srand(42);
        for ($y = 0; $y < $h; $y += 10) {
            for ($x = 0; $x < $w; $x += 10) {
                imagefilledrectangle($im, $x, $y, $x + 9, $y + 9, imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }
        ob_start();
        imagejpeg($im, null, 100); // kualitas 100 = besar, spt foto mentah
        $binary = ob_get_clean();
        imagedestroy($im);

        $dir = storage_path('app/public/guru-foto');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $rel = 'guru-foto/ZZTEST_'.$guru->uuid.'.jpg';
        file_put_contents(storage_path('app/public/'.$rel), $binary);
        $this->createdPaths[] = $rel;
        $guru->update(['foto' => $rel]);

        return $guru->fresh();
    }

    public function test_command_mengompres_foto_besar_dan_melewati_yang_sudah_kecil(): void
    {
        $besar = $this->makeGuruWithRawPhoto('ZZTEST Guru Foto Besar', 2500, 3000);
        $kecil = $this->makeGuruWithRawPhoto('ZZTEST Guru Foto Kecil', 100, 120); // otomatis kecil

        $sizeBesarSebelum = filesize(storage_path('app/public/'.$besar->foto));
        $sizeKecilSebelum = filesize(storage_path('app/public/'.$kecil->foto));
        $this->assertGreaterThan(300 * 1024, $sizeBesarSebelum, 'fixture "besar" harus di atas ambang skip');
        $this->assertLessThanOrEqual(300 * 1024, $sizeKecilSebelum, 'fixture "kecil" harus benar2 di bawah ambang skip');

        $this->artisan('kartu-guru:optimize-foto')->assertSuccessful();

        $besar->refresh();
        $kecil->refresh();
        [$w, $h] = getimagesize(storage_path('app/public/'.$besar->foto));
        $this->assertLessThanOrEqual(FotoKartu::MAX_DIM, max($w, $h));

        // Yang sudah kecil TIDAK disentuh (byte-identik) — hindari kompresi ulang tanpa perlu.
        $sizeKecilSesudah = filesize(storage_path('app/public/'.$kecil->foto));
        $this->assertSame($sizeKecilSebelum, $sizeKecilSesudah);
    }

    public function test_command_force_memproses_ulang_walau_sudah_kecil(): void
    {
        $kecil = $this->makeGuruWithRawPhoto('ZZTEST Guru Foto Kecil Force', 100, 120);

        $this->artisan('kartu-guru:optimize-foto', ['--force' => true])->assertSuccessful();

        $after = file_get_contents(storage_path('app/public/'.$kecil->foto));
        $this->assertNotFalse(getimagesizefromstring($after));
    }
}
