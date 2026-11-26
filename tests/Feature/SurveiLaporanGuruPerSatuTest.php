<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\User;
use App\Models\Walikelas;
use App\Survei\AnalisisGuru;
use App\Survei\Periode;
use App\Survei\Pertanyaan;
use App\Survei\Target;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SurveiLaporanGuruPerSatuTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, string $role): User
    {
        return User::create(['username' => $name, 'password' => Hash::make('password'), 'access' => $role]);
    }

    private function setupData(): array
    {
        Setting::set('nama_sekolah', 'SMP Bintang');
        $admin = $this->user('admin_test', 'admin');
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $mapel = Pelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);

        $guruUser1 = $this->user('guru_user_1', 'guru');
        $guru1 = Guru::create(['id_login' => $guruUser1->uuid, 'nama' => 'Budi Santoso, S.Pd.']);

        $guruUser2 = $this->user('guru_user_2', 'guru');
        $guru2 = Guru::create(['id_login' => $guruUser2->uuid, 'nama' => 'Siti Rahma, M.Pd.']);

        Ngajar::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru1->uuid, 'id_pelajaran' => $mapel->uuid]);
        Ngajar::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru2->uuid, 'id_pelajaran' => $mapel->uuid]);

        $periode = Periode::create([
            'judul' => 'Semester Ganjil 2026',
            'mulai' => now()->subDays(5),
            'selesai' => now()->subDay(),
            'dibuat_oleh' => 'admin',
            'ditutup' => true,
            'pertanyaan' => [
                'mapel' => Pertanyaan::untuk('mapel'),
                'wali' => Pertanyaan::untuk('wali'),
                'komentar_mapel' => Pertanyaan::terbuka('mapel'),
                'komentar_wali' => Pertanyaan::terbuka('wali'),
                'dibalik_mapel' => Pertanyaan::dibalik('mapel'),
                'dibalik_wali' => Pertanyaan::dibalik('wali'),
            ],
        ]);

        $target1 = $periode->targets()->create([
            'kelas_id' => $kelas->uuid,
            'guru_id' => $guru1->uuid,
            'pelajaran_id' => $mapel->uuid,
            'jenis' => 'mapel',
            'kelas_nama' => 'Kelas 7 A',
            'guru_nama' => $guru1->nama,
            'pelajaran_nama' => 'Matematika',
        ]);

        $target2 = $periode->targets()->create([
            'kelas_id' => $kelas->uuid,
            'guru_id' => $guru2->uuid,
            'pelajaran_id' => $mapel->uuid,
            'jenis' => 'mapel',
            'kelas_nama' => 'Kelas 7 A',
            'guru_nama' => $guru2->nama,
            'pelajaran_nama' => 'Matematika',
        ]);

        // Berikan respon untuk guru 1
        for ($i = 0; $i < 5; $i++) {
            $target1->respons()->create([
                'siswa_id' => 'siswa-test-'.$i,
                'jawaban' => [4, 5, 4, 5, 4, 5, 4, 5, 4, 5],
                'komentar' => ['Penjelasan sangat jelas dan mudah dipahami'],
                'karakter' => 'Sabar dan ramah',
            ]);
        }

        // Berikan respon untuk guru 2
        for ($i = 0; $i < 5; $i++) {
            $target2->respons()->create([
                'siswa_id' => 'siswa-test-'.$i,
                'jawaban' => [5, 5, 5, 5, 5, 5, 5, 5, 5, 5],
                'komentar' => ['Guru hebat dan inspiratif'],
                'karakter' => 'Tegas dan disiplin',
            ]);
        }

        return compact('admin', 'guru1', 'guru2', 'periode', 'target1', 'target2');
    }

    public function test_laporan_guru_dapat_diunduh_per_satu_satu_guru_terpisah(): void
    {
        $d = $this->setupData();

        // 1. Unduh laporan khusus Guru 1
        $response1 = $this->actingAs($d['admin'])
            ->get(route('survei.laporan-guru', ['periode' => $d['periode'], 'guru' => $d['guru1']->uuid]));

        $response1->assertOk();
        $response1->assertHeader('Content-Type', 'application/pdf');

        // Pastikan nama file spesifik guru 1
        $disposition1 = $response1->headers->get('content-disposition');
        $this->assertStringContainsString('laporan-survei-guru-budi-santoso-spd', $disposition1);

        // 2. Unduh laporan khusus Guru 2
        $response2 = $this->actingAs($d['admin'])
            ->get(route('survei.laporan-guru', ['periode' => $d['periode'], 'guru' => $d['guru2']->uuid]));

        $response2->assertOk();
        $response2->assertHeader('Content-Type', 'application/pdf');

        // Pastikan nama file spesifik guru 2
        $disposition2 = $response2->headers->get('content-disposition');
        $this->assertStringContainsString('laporan-survei-guru-siti-rahma-mpd', $disposition2);
    }

    public function test_view_laporan_guru_tunggal_melewatkan_tabel_ringkasan_seluruh_guru(): void
    {
        $d = $this->setupData();
        $analisis = app(AnalisisGuru::class);

        // Rekap hanya 1 guru
        $gurus1 = $analisis->rekap($d['periode'], guruId: $d['guru1']->uuid, sertakanCatatan: true);
        $this->assertCount(1, $gurus1);

        $html1 = view('survei.laporan-guru-pdf', [
            'periode' => $d['periode'],
            'sekolah' => 'SMP Bintang',
            'gurus' => $gurus1,
            'lihatCatatan' => true,
            'minimum' => AnalisisGuru::MINIMUM,
            'tingkat' => null,
        ])->render();

        // Halaman rekap seluruh guru tidak boleh muncul jika hanya 1 guru
        $this->assertStringNotContainsString('A. Ringkasan seluruh guru', $html1);
        $this->assertStringNotContainsString('REKAP EVALUASI GURU', $html1);

        // Harus langsung merender Lembar 1 dan Lembar 2 untuk guru tersebut
        $this->assertStringContainsString('LAPORAN EVALUASI GURU', $html1);
        $this->assertStringContainsString('Budi Santoso, S.Pd.', $html1);
        $this->assertStringNotContainsString('Siti Rahma, M.Pd.', $html1);
        $this->assertStringContainsString('Penjelasan sangat jelas dan mudah dipahami', $html1);
        $this->assertStringContainsString('Rencana tindak lanjut yang disepakati', $html1);
        $this->assertStringContainsString('Verifikasi hasil diskusi', $html1);
    }

    public function test_halaman_analisis_menampilkan_tombol_unduh_pdf_per_guru(): void
    {
        $d = $this->setupData();

        $page = $this->actingAs($d['admin'])
            ->get(route('survei.analisis', $d['periode']))
            ->assertOk();

        // Cek tautan unduh PDF untuk guru aktif di header profil
        $urlGuru1 = route('survei.laporan-guru', ['periode' => $d['periode'], 'guru' => $d['guru1']->uuid]);
        $page->assertSee($urlGuru1);
        $page->assertSee('Unduh PDF: Budi Santoso, S.Pd.');
        $page->assertSee('Unduh PDF Guru Ini');

        // Cek section Laporan PDF per Guru (Satu per Satu)
        $urlGuru2 = route('survei.laporan-guru', ['periode' => $d['periode'], 'guru' => $d['guru2']->uuid]);
        $page->assertSee($urlGuru2);
        $page->assertSee('Laporan PDF per Guru (Satu per Satu');
    }

    public function test_laporan_guru_dengan_guru_id_tidak_ditemukan_mengembalikan_404(): void
    {
        $d = $this->setupData();
        $randomUuid = (string) Str::uuid();

        $response = $this->actingAs($d['admin'])
            ->get(route('survei.laporan-guru', ['periode' => $d['periode'], 'guru' => $randomUuid]));

        $response->assertNotFound();
    }
}
