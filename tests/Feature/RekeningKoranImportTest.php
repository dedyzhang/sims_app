<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\SppPembayaran;
use App\Models\User;
use App\Support\RekeningKoranBcaParser;
use App\Support\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Import laporan transaksi VA BCA (.txt, format "R-5401") untuk otomatisasi tahap
 * 2 verifikasi SPP ("Validasi Rekening Koran": terverifikasi/belum/menunggu/ditolak
 * → lunas). Sample di sini persis file asli yg dikirim user (R-5401_01272_20260620_rpt.txt),
 * supaya parser teruji terhadap format nyata, bukan data buatan yg terlalu rapi.
 */
class RekeningKoranImportTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE = <<<'TXT'
1RETENSI         : RA.1B/6B/10T                LAPORAN TRANSAKSI VIA E-BANKING & COUNTER                         FREKWENSI :   HARIAN
 LAPORAN         : R-5401                             UNTUK PERUSAHAAN KERJASAMA                                 TANGGAL   : 20/06/26
 CABANG          : 0000380-KCU TANJUNG PINANG                                                                    HALAMAN   :        1
 NAMA PERUSAHAAN : 01272-YAYASAN BUMI MAITRI
 ====================================================================================================================================
 NO.   NO.PELANGGAN/NO.TXN   NAMA PELANGGAN         NILAI TRANSAKSI      TGL. TXN    WAKTU   LOKASI     KETERANGAN1     KETERANGAN2
 ====================================================================================================================================

 SUB-COMP 00000
     1  402353              BRYAN DOMINIC TI  IDR            770,000.00  20/06/26  06:51:52  9527N  -                -
     2  402388              ELINE CAROLINA    IDR            770,000.00  20/06/26  08:00:15  9503N  Eline Carolina   -
     3  900197              EDWARD MAGNUSSON  IDR            900,000.00  20/06/26  12:16:14  9503N  Kenaikan kelas   -
     4  402259              CALLESTA ANN ELD  IDR            750,000.00  20/06/26  13:36:43  9503N  Uang kenaikan k  s
     5  900118              RECKSON CHRISTIA  IDR            625,000.00  20/06/26  16:02:59  9523B  20260620I128087  -
     6  402330              DESTIN PEARL CAR  IDR          1,690,000.00  20/06/26  16:07:55  9503N  Destin           -
     7  402232              ELDRIC FAUSTINO   IDR            750,000.00  20/06/26  18:46:34  9527N  -                -
      SUB TOTAL TRANSAKSI    IDR        :       7
      SUB TOTAL NILAI TRANSAKSI         :  IDR        6,255,000.00

      TOTAL TRANSAKSI        IDR        :       7
      TOTAL NILAI TRANSAKSI             :  IDR        6,255,000.00

TXT;

    private function makeUser(string $access, string $username): User
    {
        return User::create(['username' => $username, 'password' => Hash::make('password'), 'access' => $access]);
    }

    private function makeKelas(): Kelas
    {
        return Kelas::create(['tingkat' => '7', 'kelas' => 'A']);
    }

    private function makeSiswa(Kelas $kelas, string $va, int $spp = 150000): Siswa
    {
        return Siswa::create([
            'nama' => 'Siswa ' . $va, 'nis' => (string) random_int(10000, 99999),
            'id_kelas' => $kelas->uuid, 'jk' => 'L', 'spp' => (string) $spp, 'va' => $va,
        ]);
    }

    public function test_parser_membaca_7_transaksi_dari_file_asli(): void
    {
        $rows = RekeningKoranBcaParser::parse(self::SAMPLE);

        $this->assertCount(7, $rows);

        $this->assertSame('402353', $rows[0]['no_pelanggan']);
        $this->assertSame(770000, $rows[0]['nominal']);
        $this->assertSame('2026-06-20', $rows[0]['tanggal']->toDateString());

        // Nominal jutaan dgn koma ribuan ("1,690,000.00") harus terparsir benar.
        $this->assertSame(1690000, $rows[5]['nominal']);

        // Baris header/subtotal/footer TIDAK ikut kebaca sbg transaksi.
        $this->assertSame(['402353', '402388', '900197', '402259', '900118', '402330', '402232'],
            array_column($rows, 'no_pelanggan'));
    }

    public function test_import_menandai_lunas_siswa_yg_cocok_va_dan_nominal(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_rk1');
        $kelas = $this->makeKelas();

        // Cocok persis: VA 402353, nominal 770.000, status terverifikasi (tahap 2).
        $siswa = $this->makeSiswa($kelas, '402353');
        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 3, 'nominal' => 770000, 'status' => 'terverifikasi',
        ]);

        $file = UploadedFile::fake()->createWithContent('rekening_koran.txt', self::SAMPLE);
        $this->actingAs($bendahara)
            ->post(route('keuangan.import-rekening-koran'), ['file' => $file])
            ->assertRedirect();

        $p->refresh();
        $this->assertSame('lunas', $p->status);
        $this->assertSame('BCA', $p->bank);
        $this->assertSame('2026-06-20', $p->tanggal_bayar->toDateString());
        $this->assertSame($bendahara->uuid, $p->diverifikasi_oleh);
        $this->assertNotNull($p->diverifikasi_pada);
    }

    public function test_import_juga_melunaskan_pembayaran_yg_belum_diunggah_buktinya(): void
    {
        // Transfer langsung tanpa lewat alur unggah bukti ortu — bank sudah cukup jadi bukti.
        $bendahara = $this->makeUser('bendahara', 'bendahara_rk2');
        $kelas = $this->makeKelas();

        $siswa = $this->makeSiswa($kelas, '900197');
        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 3, 'nominal' => 900000, 'status' => 'belum',
        ]);

        $file = UploadedFile::fake()->createWithContent('rekening_koran.txt', self::SAMPLE);
        $this->actingAs($bendahara)->post(route('keuangan.import-rekening-koran'), ['file' => $file]);

        $p->refresh();
        $this->assertSame('lunas', $p->status);
    }

    public function test_import_tidak_menebak_kalau_nominal_tak_cocok(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_rk3');
        $kelas = $this->makeKelas();

        // VA 402259 ada di file dgn nominal 750.000, tapi tagihan siswa ini nominalnya beda.
        $siswa = $this->makeSiswa($kelas, '402259');
        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 3, 'nominal' => 500000, 'status' => 'menunggu',
        ]);

        $file = UploadedFile::fake()->createWithContent('rekening_koran.txt', self::SAMPLE);
        $res = $this->actingAs($bendahara)->post(route('keuangan.import-rekening-koran'), ['file' => $file]);

        $p->refresh();
        $this->assertSame('menunggu', $p->status, 'Nominal beda tidak boleh dipaksakan lunas — harus tinjau manual.');
        $res->assertSessionHas('error');
    }

    public function test_import_tidak_menyentuh_apapun_kalau_va_dipakai_dua_siswa(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_rk4');
        $kelas = $this->makeKelas();

        // Dua siswa kebetulan berbagi 6 digit belakang VA yg sama (data kotor).
        $siswaA = $this->makeSiswa($kelas, '1402330');
        $siswaB = $this->makeSiswa($kelas, '9402330');
        $pA = SppPembayaran::create([
            'id_siswa' => $siswaA->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 3, 'nominal' => 1690000, 'status' => 'terverifikasi',
        ]);
        $pB = SppPembayaran::create([
            'id_siswa' => $siswaB->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 3, 'nominal' => 1690000, 'status' => 'terverifikasi',
        ]);

        $file = UploadedFile::fake()->createWithContent('rekening_koran.txt', self::SAMPLE);
        $this->actingAs($bendahara)->post(route('keuangan.import-rekening-koran'), ['file' => $file]);

        $pA->refresh();
        $pB->refresh();
        $this->assertSame('terverifikasi', $pA->status);
        $this->assertSame('terverifikasi', $pB->status);
    }

    public function test_import_ulang_file_yg_sama_tidak_double_proses(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_rk5');
        $kelas = $this->makeKelas();

        $siswa = $this->makeSiswa($kelas, '402353');
        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid, 'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 3, 'nominal' => 770000, 'status' => 'terverifikasi',
        ]);

        $file1 = UploadedFile::fake()->createWithContent('rekening_koran.txt', self::SAMPLE);
        $this->actingAs($bendahara)->post(route('keuangan.import-rekening-koran'), ['file' => $file1]);
        $p->refresh();
        $this->assertSame('lunas', $p->status);
        $tanggalVerifPertama = $p->diverifikasi_pada;

        // Upload file yg SAMA lagi — tak boleh ada baris lain yg ikut berubah / error.
        $file2 = UploadedFile::fake()->createWithContent('rekening_koran.txt', self::SAMPLE);
        $res = $this->actingAs($bendahara)->post(route('keuangan.import-rekening-koran'), ['file' => $file2]);
        $res->assertSessionHas('error');

        $p->refresh();
        $this->assertSame('lunas', $p->status);
        $this->assertTrue($tanggalVerifPertama->equalTo($p->diverifikasi_pada), 'Import ulang tidak boleh menulis ulang baris yg sudah lunas dari transaksi yg sama.');
    }

    public function test_hanya_txt_yg_diterima(): void
    {
        $bendahara = $this->makeUser('bendahara', 'bendahara_rk6');

        $file = UploadedFile::fake()->create('laporan.pdf', 10, 'application/pdf');
        $this->actingAs($bendahara)
            ->post(route('keuangan.import-rekening-koran'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }
}
