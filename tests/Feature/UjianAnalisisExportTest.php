<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianJawaban;
use App\Models\UjianKelas;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use App\Exports\Ujian\UjianAnalisisExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Fase 6 modul Ujian: unduh Excel "Analisis Hasil Ujian" per kelas — dibatasi ke
 * admin/pengelola dan guru PENGAMPU kelas itu saja (mengampuiKelas), roster-driven
 * (siswa tanpa attempt tetap masuk sebagai baris '-'), dan generate-nya harus benar2
 * jalan (bukan cuma di-fake) supaya nyata2 ketahuan kalau ada bug di array-building.
 */
class UjianAnalisisExportTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;
    private Kelas $kelas;
    private Ujian $ujian;
    private UjianKelas $ujianKelas;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->admin = User::create(['username' => 'admin_analisis', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $this->admin->uuid,
            'judul' => 'PAS Analisis', 'jenis' => 'pas', 'target_nilai' => 'pas',
            'durasi_menit' => 90, 'status' => 'published',
        ]);
        $this->ujianKelas = UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => 'ANALISIS1']);

        $this->buatSoalCampuran();
    }

    private function buatSoalCampuran(): void
    {
        $mcq = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => 'Soal MCQ', 'poin' => 10, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $mcq->uuid, 'teks_opsi' => 'Salah', 'is_benar' => false, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $mcq->uuid, 'teks_opsi' => 'Benar', 'is_benar' => true, 'urutan' => 2]);
        $this->mcqSoal = $mcq;
        $this->mcqOpsiBenar = $mcq->opsi()->where('is_benar', true)->first();

        $tf = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'true_false', 'teks_soal' => 'Soal Benar/Salah', 'poin' => 10, 'urutan' => 2]);
        UjianSoalOpsi::create(['id_soal' => $tf->uuid, 'teks_opsi' => 'Benar', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $tf->uuid, 'teks_opsi' => 'Salah', 'is_benar' => false, 'urutan' => 2]);

        $complex = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq_complex', 'teks_soal' => 'Soal Kompleks', 'poin' => 10, 'urutan' => 3, 'skor_mode' => 'all_or_nothing']);
        UjianSoalOpsi::create(['id_soal' => $complex->uuid, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $complex->uuid, 'teks_opsi' => 'B', 'is_benar' => true, 'urutan' => 2]);
        UjianSoalOpsi::create(['id_soal' => $complex->uuid, 'teks_opsi' => 'C', 'is_benar' => false, 'urutan' => 3]);

        UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'match', 'teks_soal' => 'Soal Mencocokkan', 'poin' => 10, 'urutan' => 4, 'skor_mode' => 'proporsional', 'meta' => ['pairs' => [['left' => 'a', 'right' => 'b']]]]);

        UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'essay', 'teks_soal' => 'Soal Esai', 'poin' => 10, 'urutan' => 5]);
    }

    private $mcqSoal;
    private $mcqOpsiBenar;

    private function buatSiswa(string $username): User
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'id_kelas' => $this->kelas->uuid, 'nama' => ucfirst($username), 'nis' => (string) random_int(1000, 9999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        return $user;
    }

    private function buatGuru(string $username): array
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $user->uuid, 'nama' => ucfirst($username), 'nik' => (string) random_int(1000000000, 9999999999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        return [$user, $guru];
    }

    public function test_admin_bisa_unduh_excel_dengan_data_campuran_dan_siswa_belum_mulai(): void
    {
        $siswaJawab = $this->buatSiswa('siswa_jawab');
        $this->buatSiswa('siswa_belum_mulai');

        $attempt = UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelas->uuid, 'id_siswa' => $siswaJawab->uuid,
            'urutan_soal' => [], 'urutan_opsi' => [], 'status' => UjianAttempt::STATUS_DINILAI,
            'total_skor' => 20, 'skor_objektif' => 20,
        ]);
        UjianJawaban::create([
            'id_attempt' => $attempt->uuid, 'id_soal' => $this->mcqSoal->uuid,
            'id_opsi_dipilih' => $this->mcqOpsiBenar->uuid, 'is_benar' => true, 'skor_diperoleh' => 10,
        ]);

        $res = $this->actingAs($this->admin)->get(route('ujian.analisis.unduh', [$this->ujian, $this->ujianKelas]));
        $res->assertOk();
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_guru_pengampu_bisa_unduh_guru_lain_ditolak(): void
    {
        [$guruAmpuUser, $guruAmpu] = $this->buatGuru('guru_ampu');
        Ngajar::create(['id_guru' => $guruAmpu->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        $this->ujianKelas->update(['id_guru_pengampu' => $guruAmpu->uuid]);

        [$guruLainUser, $guruLain] = $this->buatGuru('guru_lain_analisis');

        $this->actingAs($guruAmpuUser)
            ->get(route('ujian.analisis.unduh', [$this->ujian, $this->ujianKelas]))
            ->assertOk();

        $this->actingAs($guruLainUser)
            ->get(route('ujian.analisis.unduh', [$this->ujian, $this->ujianKelas]))
            ->assertForbidden();
    }

    public function test_index_hanya_menampilkan_kelas_yang_bisa_diunduh_guru(): void
    {
        [$guruAmpuUser, $guruAmpu] = $this->buatGuru('guru_ampu_index');
        Ngajar::create(['id_guru' => $guruAmpu->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        $this->ujianKelas->update(['id_guru_pengampu' => $guruAmpu->uuid]);

        $res = $this->actingAs($guruAmpuUser)->get(route('ujian.analisis.index', $this->ujian));
        $res->assertOk();
        $res->assertSee('Unduh Excel');
    }

    /**
     * Regresi utk 2 bug nyata yg pernah ditemukan di sini: (1) Maatwebsite/PhpSpreadsheet
     * MEMBUANG baris array kosong `[]`, menggeser semua baris setelahnya naik 1 kalau
     * spacer ditulis salah; (2) tanpa WithStrictNullComparison, nilai literal 0 (mis. TL,
     * jawaban salah) ikut dianggap "kosong" (0 == null di PHP) dan gagal ditulis. Test ini
     * baca ULANG file xlsx yg BENERAN di-generate (bukan Excel::fake) dan cek isi selnya
     * persis, supaya kedua bug itu tak bisa lolos diam-diam lagi.
     *
     * Layout kolom Bagian A utk buatSoalCampuran() (urutan soal 1-5, SEMUA 1 kolom per soal
     * — mcq_complex & match TAK dipecah per-item di Bagian A, lihat class docblock):
     * No,Nama(A,B) + mcq(C) + true_false(D) + mcq_complex(E) + match(F) + esai(G) +
     * Jumlah(H, skor mentah dijumlah ulang) + Rata-rata(I, attempt->total_skor) + L(J) + TL(K).
     */
    public function test_isi_sel_sesuai_posisi_dan_angka_nol_tidak_hilang(): void
    {
        $siswaTidakTuntas = $this->buatSiswa('siswa_nilai_rendah');
        UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelas->uuid, 'id_siswa' => $siswaTidakTuntas->uuid,
            'urutan_soal' => [], 'urutan_opsi' => [], 'status' => UjianAttempt::STATUS_DINILAI,
            'total_skor' => 0, 'skor_objektif' => 0,
        ]);

        $export = new UjianAnalisisExport($this->ujian, $this->ujianKelas);
        $raw = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);

        $tmp = tempnam(sys_get_temp_dir(), 'analisis') . '.xlsx';
        file_put_contents($tmp, $raw);
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        unlink($tmp);

        // Baris 3 sengaja kosong (spacer) — baris 4 harus tetap "Mata Pelajaran", BUKAN tergeser jadi "Kelas".
        $this->assertNull($sheet->getCell('A3')->getValue());
        $this->assertStringStartsWith('Mata Pelajaran', (string) $sheet->getCell('A4')->getValue());
        $this->assertStringStartsWith('Kelas :', (string) $sheet->getCell('A5')->getValue());
        $this->assertSame('A. Analisis Nilai', $sheet->getCell('A8')->getValue());
        $this->assertSame('No', $sheet->getCell('A9')->getValue());
        $this->assertSame('Jumlah', $sheet->getCell('H9')->getValue());
        $this->assertSame('Rata-rata', $sheet->getCell('I9')->getValue());

        // Siswa ini tak jawab apa-apa & skor 0 (< KKM) → Jumlah mentah 0, TL harus benar-benar
        // 0/1 tertulis, bukan sel kosong.
        $dataRow = 10;
        $this->assertSame('siswa_nilai_rendah', strtolower($sheet->getCell("B{$dataRow}")->getValue()));
        $this->assertEquals(0, $sheet->getCell("H{$dataRow}")->getValue());
        $this->assertEquals(0, $sheet->getCell("I{$dataRow}")->getValue());
        $this->assertNotNull($sheet->getCell("I{$dataRow}")->getValue());
        $this->assertEquals(1, $sheet->getCell("K{$dataRow}")->getValue());

        // KKM/Tuntas/Tidak Tuntas SEJAJAR dgn Jumlah/Rata-rata/L/TL Bagian A (bukan didorong
        // jauh ke kanan oleh lebar Bagian B) — kolomTerakhirA=11(K), info box mulai K+2=M(label)/O(nilai).
        $this->assertSame('KKM', $sheet->getCell('M4')->getValue());
        $this->assertEquals(75, $sheet->getCell('O4')->getValue());
    }

    /**
     * Esai tampil skor mentah (bukan 1/0) di Bagian A, dikecualikan dari baris footer
     * salah/persentase; mcq_complex & match di Bagian A tetap 1 kolom (TAK dipecah), TAPI
     * kolomnya menampilkan SKOR yg didapat (bukan 1/0) — sedangkan baris footer salah/
     * persentase tetap hitung berdasarkan is_benar (semua-atau-tidak), terpisah dari nilai
     * yg tertulis. Di Bagian B, mcq_complex/match dipecah jadi sub-kolom per-opsi/pasangan
     * benar — breakdown per-item SENGAJA hanya muncul di Bagian B. Kolom "Jumlah" (skor
     * mentah dijumlah ulang, TAK terikat mode_skor_ujian) muncul SEBELUM "Rata-rata"
     * (attempt->total_skor apa adanya).
     */
    public function test_esai_skor_mentah_dan_subkolom_kompleks_match_bagian_a_dan_b(): void
    {
        $siswa = $this->buatSiswa('siswa_lengkap');
        $attempt = UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelas->uuid, 'id_siswa' => $siswa->uuid,
            'urutan_soal' => [], 'urutan_opsi' => [], 'status' => UjianAttempt::STATUS_DINILAI,
            'total_skor' => 87, 'skor_objektif' => 79.5,
        ]);

        UjianJawaban::create([
            'id_attempt' => $attempt->uuid, 'id_soal' => $this->mcqSoal->uuid,
            'id_opsi_dipilih' => $this->mcqOpsiBenar->uuid, 'is_benar' => true, 'skor_diperoleh' => 10,
        ]);

        // mcq_complex: cuma pilih opsi benar PERTAMA (A), opsi benar KEDUA (B) tidak dipilih.
        $complexSoal = $this->ujian->soal()->where('tipe', 'mcq_complex')->first();
        $opsiComplex = $complexSoal->opsi()->orderBy('urutan')->get();
        UjianJawaban::create([
            'id_attempt' => $attempt->uuid, 'id_soal' => $complexSoal->uuid,
            'opsi_dipilih_multi' => [$opsiComplex[0]->uuid], 'is_benar' => false, 'skor_diperoleh' => 10,
        ]);

        $matchSoal = $this->ujian->soal()->where('tipe', 'match')->first();
        UjianJawaban::create([
            'id_attempt' => $attempt->uuid, 'id_soal' => $matchSoal->uuid,
            'jawaban_pasangan' => ['a' => 'b'], 'is_benar' => true, 'skor_diperoleh' => 10,
        ]);

        $essaySoal = $this->ujian->soal()->where('tipe', 'essay')->first();
        UjianJawaban::create([
            'id_attempt' => $attempt->uuid, 'id_soal' => $essaySoal->uuid,
            'jawaban_esai' => 'Jawaban esai', 'skor_diperoleh' => 7.5,
        ]);

        $export = new UjianAnalisisExport($this->ujian, $this->ujianKelas);
        $raw = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        $tmp = tempnam(sys_get_temp_dir(), 'analisis') . '.xlsx';
        file_put_contents($tmp, $raw);
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        unlink($tmp);

        // ----- Bagian A (baris data = 10) — mcq_complex & match TETAP 1 kolom (TAK dipecah),
        // TAPI kolomnya menampilkan SKOR yg didapat (bukan 1/0): mcq_complex skor_diperoleh=10
        // (meski is_benar=false, parsial); match skor_diperoleh=10 (is_benar=true). -----
        $dataRowA = 10;
        $this->assertEquals(1, $sheet->getCell("C{$dataRowA}")->getValue()); // mcq benar
        $this->assertEquals(0, $sheet->getCell("D{$dataRowA}")->getValue()); // true_false tak dijawab
        $this->assertEquals(10, (float) $sheet->getCell("E{$dataRowA}")->getValue()); // mcq_complex: skor MENTAH (parsial), bukan 1/0
        $this->assertEquals(10, (float) $sheet->getCell("F{$dataRowA}")->getValue()); // match: skor MENTAH, bukan 1/0
        $this->assertEquals(7.5, (float) $sheet->getCell("G{$dataRowA}")->getValue()); // esai: skor MENTAH, bukan 1/0
        // Jumlah (H) = penjumlahan mentah skor_diperoleh semua soal: mcq(10)+true_false(0,tak
        // dijawab)+mcq_complex(10)+match(10)+esai(7.5) = 37.5. Rata-rata (I) = attempt->total_skor apa adanya.
        $this->assertEquals(37.5, (float) $sheet->getCell("H{$dataRowA}")->getValue());
        $this->assertEquals(87, $sheet->getCell("I{$dataRowA}")->getValue());

        // Footer salah/persentase (baris 11/12): tetap berdasarkan is_benar (bukan nilai skor
        // yg ditampilkan) — kolom esai (G) HARUS kosong (dikecualikan).
        $this->assertEquals(1, $sheet->getCell('D11')->getValue()); // true_false salah
        $this->assertEquals(1, $sheet->getCell('E11')->getValue()); // mcq_complex salah (is_benar=false, walau skor tertulis 10)
        $this->assertEquals(0, $sheet->getCell('F11')->getValue()); // match benar (0 salah)
        $this->assertNull($sheet->getCell('G11')->getValue());
        $this->assertNull($sheet->getCell('G12')->getValue());

        // ----- Bagian B (header=15, kunci=16, data=17) — esai TIDAK muncul (cuma sampai kolom G) -----
        $this->assertSame('B. Objektif (Jawaban)', $sheet->getCell('A14')->getValue());
        $this->assertSame('Kunci', $sheet->getCell('B16')->getValue());
        $this->assertSame('B', $sheet->getCell('C16')->getValue()); // kunci mcq (opsi "Benar" = urutan ke-2)
        $this->assertSame('A', $sheet->getCell('D16')->getValue()); // kunci true_false (opsi "Benar" = urutan ke-1)
        $this->assertSame('A', $sheet->getCell('E16')->getValue()); // kunci complex sub-A
        $this->assertSame('B', $sheet->getCell('F16')->getValue()); // kunci complex sub-B
        $this->assertSame('A', $sheet->getCell('G16')->getValue()); // kunci match sub-A

        $dataRowB = 17;
        $this->assertSame('B', $sheet->getCell("C{$dataRowB}")->getValue()); // mcq: jawab benar
        $this->assertSame('-', $sheet->getCell("D{$dataRowB}")->getValue()); // true_false: tak dijawab
        $this->assertSame('A', $sheet->getCell("E{$dataRowB}")->getValue()); // complex sub-A: didapat
        $this->assertSame('-', $sheet->getCell("F{$dataRowB}")->getValue()); // complex sub-B: tak didapat
        $this->assertSame('A', $sheet->getCell("G{$dataRowB}")->getValue()); // match sub-A: didapat
    }

    /** Poin #6: halaman landscape A4 + page break persis sebelum "B. Objektif (Jawaban)". */
    public function test_halaman_landscape_a4_dan_page_break_sebelum_bagian_b(): void
    {
        $export = new UjianAnalisisExport($this->ujian, $this->ujianKelas);
        $export->array();
        $ref = new \ReflectionClass($export);
        $prop = $ref->getProperty('rowSeksiB');
        $prop->setAccessible(true);
        $rowSeksiB = $prop->getValue($export);

        $raw = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        $tmp = tempnam(sys_get_temp_dir(), 'analisis') . '.xlsx';
        file_put_contents($tmp, $raw);
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        unlink($tmp);

        $this->assertSame(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE, $sheet->getPageSetup()->getOrientation());
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4, $sheet->getPageSetup()->getPaperSize());

        $breaks = $sheet->getBreaks();
        $this->assertArrayHasKey("A{$rowSeksiB}", $breaks);
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW, $breaks["A{$rowSeksiB}"]);
    }

    public function test_index_kosong_utk_guru_yang_bukan_pengampu_kelas_manapun(): void
    {
        [$guruAmpuUser, $guruAmpu] = $this->buatGuru('guru_ampu_lain_index');
        Ngajar::create(['id_guru' => $guruAmpu->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        $this->ujianKelas->update(['id_guru_pengampu' => $guruAmpu->uuid]);

        [$guruLainUser, $guruLain] = $this->buatGuru('guru_bukan_pengampu_index');
        Ngajar::create(['id_guru' => $guruLain->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);

        $res = $this->actingAs($guruLainUser)->get(route('ujian.analisis.index', $this->ujian));
        $res->assertOk();
        $res->assertDontSee('Unduh Excel');
        $res->assertSee('Tidak ada kelas yang bisa diunduh');
    }
}
