<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Materi;
use App\Models\Ngajar;
use App\Models\NilaiFormatif;
use App\Models\NilaiPas;
use App\Models\NilaiRapor;
use App\Models\NilaiSumatif;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TujuanPembelajaran;
use App\Support\RaporHitung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Optimasi #3: RaporHitung::olahBanyak() menghitung SEMUA ngajar sekaligus (5 query flat)
 * menggantikan olah() yg dipanggil dlm loop (5 query × N ngajar) di Rekap Nilai/Cetak Rapor.
 * Test ini WAJIB hijau SEBELUM call-site produksi diganti: mengunci bahwa hasil per
 * (ngajar, siswa) IDENTIK byte-for-byte dgn olah() satu-per-satu, utk data campuran
 * (formatif+sumatif+pas+override rapor, 2 mapel kktp berbeda, tupe milik mapel masing2).
 */
class RaporHitungBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_olah_banyak_identik_dgn_olah_satu_per_satu(): void
    {
        $sem = Semester::create(['semester' => 1, 'tahun' => '2026/2027', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $guru = Guru::create(['nama' => 'Guru Rapor', 'nik' => '9999999999', 'jk' => 'L', 'face_descriptor' => [0.1]]);

        // 2 mapel dgn KKM berbeda → kktp berbeda per ngajar.
        $mtk = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $ipa = Pelajaran::create(['nama' => 'IPA', 'kkm' => 70]);
        $ngMtk = Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $mtk->uuid, 'id_kelas' => $kelas->uuid, 'kkm' => 75]);
        $ngIpa = Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $ipa->uuid, 'id_kelas' => $kelas->uuid, 'kkm' => 70]);

        // Tiap ngajar: 2 materi, tiap materi 2 tupe.
        $tupeByNgajar = [];
        $materiByNgajar = [];
        foreach (['mtk' => $ngMtk, 'ipa' => $ngIpa] as $tag => $ng) {
            for ($mi = 1; $mi <= 2; $mi++) {
                $materi = Materi::create(['id_ngajar' => $ng->uuid, 'nama' => "{$tag} Bab {$mi}", 'id_semester' => $sem->id, 'urutan' => $mi, 'aktif' => true]);
                $materiByNgajar[$tag][] = $materi;
                for ($ti = 1; $ti <= 2; $ti++) {
                    $tupeByNgajar[$tag][] = TujuanPembelajaran::create(['id_materi' => $materi->uuid, 'tupe' => "{$tag} TP {$mi}.{$ti}", 'urutan' => $ti, 'aktif' => true]);
                }
            }
        }

        // 4 siswa: berbagai kombinasi nilai (lengkap, sebagian, kosong, override rapor).
        $siswas = collect();
        for ($i = 1; $i <= 4; $i++) {
            $siswas->push(Siswa::create(['nama' => "Siswa {$i}", 'nis' => "RP00{$i}", 'jk' => 'L', 'id_kelas' => $kelas->uuid]));
        }

        // Formatif per tupe (nilai bervariasi), sumatif per materi, PAS per ngajar.
        foreach (['mtk' => $ngMtk, 'ipa' => $ngIpa] as $tag => $ng) {
            foreach ($siswas as $idx => $s) {
                // siswa ke-4 sengaja TANPA nilai apa pun (uji jalur kosong).
                if ($idx === 3) continue;
                foreach ($tupeByNgajar[$tag] as $k => $tupe) {
                    NilaiFormatif::create(['id_materi' => $tupe->id_materi, 'id_tupe' => $tupe->uuid, 'id_siswa' => $s->uuid, 'nilai' => 70 + $idx * 5 + $k * 2]);
                }
                foreach ($materiByNgajar[$tag] as $k => $materi) {
                    NilaiSumatif::create(['id_materi' => $materi->uuid, 'id_siswa' => $s->uuid, 'nilai' => 65 + $idx * 4 + $k * 3]);
                }
                NilaiPas::create(['id_ngajar' => $ng->uuid, 'id_siswa' => $s->uuid, 'id_semester' => $sem->id, 'nilai' => 80 + $idx]);
            }
        }
        // Override rapor + deskripsi kustom utk siswa 1 di MTK saja.
        NilaiRapor::create([
            'id_ngajar' => $ngMtk->uuid, 'id_siswa' => $siswas[0]->uuid, 'id_semester' => $sem->id,
            'nilai' => 88, 'deskripsi_positif' => 'Sangat baik dlm bilangan', 'deskripsi_negatif' => 'Perlu latihan pecahan',
        ]);

        $rumus = 'bagi4';
        $ngajars = collect([$ngMtk, $ngIpa]);

        // Expected: olah() per ngajar.
        $expected = [];
        foreach ($ngajars as $ng) {
            $expected[$ng->uuid] = RaporHitung::olah($ng, $siswas, $sem->id, $rumus, $ng->kktp);
        }

        // Actual: olahBanyak() sekaligus — dan buktikan query flat (bukan 5×N).
        DB::enableQueryLog();
        $actual = RaporHitung::olahBanyak($ngajars, $siswas, $sem->id, $rumus);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($expected, $actual, 'olahBanyak() harus IDENTIK dgn olah() satu-per-satu.');

        // olah() per ngajar = 5 query; 2 ngajar = 10. olahBanyak() harus jauh lebih sedikit
        // (5 query flat terlepas jumlah ngajar) — beri sedikit kelonggaran, yg penting < 10.
        $this->assertLessThanOrEqual(6, $queryCount, "olahBanyak() harus flat ~5 query, bukan 5×N (dapat {$queryCount}).");
    }
}
