<?php

namespace Tests\Feature;

use App\Models\BankSoal;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Ujian;
use App\Models\UjianSoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bank Soal — kumpulan soal per-mapel yg bisa dipakai ulang & disisipkan ke Ujian.
 * Dibagi rata per-mapel (bukan per-guru) sama spt kolaborasi soal Ujian sendiri.
 */
class BankSoalTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;
    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
    }

    private function buatGuru(string $username): array
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $user->uuid, 'nama' => ucfirst($username), 'nik' => (string) random_int(1000000000, 9999999999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        return [$user, $guru];
    }

    private function ngajarMilik(Guru $guru): Ngajar
    {
        return Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
    }

    public function test_guru_pengampu_bisa_menambah_soal_ke_bank(): void
    {
        [$user, $guru] = $this->buatGuru('guru_bank1');
        $this->ngajarMilik($guru);

        $this->actingAs($user)->post(route('bank-soal.soal.store', $this->pelajaran), [
            'tipe' => 'mcq', 'teks_soal' => 'Berapa 2+2?', 'poin' => 5,
            'opsi' => [['teks' => '3', 'benar' => ''], ['teks' => '4', 'benar' => '1']],
        ])->assertRedirect();

        $this->assertDatabaseHas('bank_soal', ['id_pelajaran' => $this->pelajaran->uuid, 'teks_soal' => 'Berapa 2+2?']);
    }

    public function test_guru_yg_tak_mengajar_mapel_ditolak_akses_bank_soal(): void
    {
        [$user] = $this->buatGuru('guru_bank2'); // sengaja TANPA Ngajar

        $this->actingAs($user)->get(route('bank-soal.show', $this->pelajaran))->assertForbidden();
        $this->actingAs($user)->post(route('bank-soal.soal.store', $this->pelajaran), [
            'tipe' => 'essay', 'teks_soal' => 'Jelaskan sesuatu', 'poin' => 5,
        ])->assertForbidden();
    }

    public function test_guru_lain_pengampu_mapel_sama_bisa_kelola_bank_soal_bersama(): void
    {
        [$pembuat, $guruPembuat] = $this->buatGuru('guru_bank3');
        $this->ngajarMilik($guruPembuat);
        $soal = BankSoal::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $pembuat->uuid,
            'tipe' => 'essay', 'teks_soal' => 'Soal lama', 'poin' => 5,
        ]);

        [$guruLain, $guruLainModel] = $this->buatGuru('guru_bank4');
        $kelasLain = Kelas::create(['tingkat' => 8, 'kelas' => 'B']);
        Ngajar::create(['id_guru' => $guruLainModel->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $kelasLain->uuid]);

        $this->actingAs($guruLain)->post(route('bank-soal.soal.update', [$this->pelajaran, $soal]), [
            'tipe' => 'essay', 'teks_soal' => 'Soal direvisi guru lain', 'poin' => 8,
        ])->assertRedirect();

        $this->assertDatabaseHas('bank_soal', ['uuid' => $soal->uuid, 'teks_soal' => 'Soal direvisi guru lain']);
    }

    public function test_sisipkan_dari_bank_membuat_salinan_independen_di_ujian(): void
    {
        [$user, $guru] = $this->buatGuru('guru_bank5');
        $this->ngajarMilik($guru);

        $bankSoal = BankSoal::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $user->uuid,
            'tipe' => 'mcq', 'teks_soal' => 'Soal dari bank', 'poin' => 7,
        ]);
        \App\Models\BankSoalOpsi::create(['id_soal' => $bankSoal->uuid, 'teks_opsi' => 'A', 'is_benar' => false, 'urutan' => 1]);
        \App\Models\BankSoalOpsi::create(['id_soal' => $bankSoal->uuid, 'teks_opsi' => 'B', 'is_benar' => true, 'urutan' => 2]);

        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $user->uuid,
            'judul' => 'PTS Sisip Bank', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90,
        ]);

        $this->actingAs($user)->post(route('ujian.soal.sisipkanBank', $ujian), [
            'soal' => [$bankSoal->uuid],
        ])->assertRedirect();

        $soalUjian = UjianSoal::where('id_ujian', $ujian->uuid)->with('opsi')->firstOrFail();
        $this->assertSame('Soal dari bank', $soalUjian->teks_soal);
        $this->assertSame(7, $soalUjian->poin);
        $this->assertCount(2, $soalUjian->opsi);

        // Salinan independen: ubah bank TIDAK mengubah ujian yg sudah disisipkan.
        $bankSoal->update(['teks_soal' => 'Sudah diubah di bank']);
        $this->assertSame('Soal dari bank', $soalUjian->fresh()->teks_soal);
    }

    public function test_sisipkan_dari_bank_mengabaikan_soal_mapel_lain(): void
    {
        [$user, $guru] = $this->buatGuru('guru_bank6');
        $this->ngajarMilik($guru);

        $pelajaranLain = Pelajaran::create(['nama' => 'IPA', 'kkm' => 75]);
        $soalMapelLain = BankSoal::create([
            'id_pelajaran' => $pelajaranLain->uuid, 'created_by' => $user->uuid,
            'tipe' => 'essay', 'teks_soal' => 'Soal IPA', 'poin' => 5,
        ]);

        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $user->uuid,
            'judul' => 'PTS Matematika', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90,
        ]);

        $this->actingAs($user)->post(route('ujian.soal.sisipkanBank', $ujian), [
            'soal' => [$soalMapelLain->uuid],
        ])->assertRedirect();

        $this->assertSame(0, UjianSoal::where('id_ujian', $ujian->uuid)->count());
    }

    public function test_simpan_soal_ujian_ke_bank_membuat_salinan_independen(): void
    {
        [$user, $guru] = $this->buatGuru('guru_bank7');
        $this->ngajarMilik($guru);

        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $user->uuid,
            'judul' => 'PTS Simpan Bank', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90,
        ]);
        $soal = UjianSoal::create(['id_ujian' => $ujian->uuid, 'tipe' => 'essay', 'teks_soal' => 'Soal esai bagus', 'poin' => 10, 'urutan' => 1]);

        $this->actingAs($user)->post(route('ujian.soal.simpanBank', [$ujian, $soal]), [])->assertRedirect();

        $bankSoal = BankSoal::where('id_pelajaran', $this->pelajaran->uuid)->firstOrFail();
        $this->assertSame('Soal esai bagus', $bankSoal->teks_soal);
        $this->assertSame(10, $bankSoal->poin);

        // Salinan independen: ubah soal ujian TIDAK mengubah salinan di bank.
        $soal->update(['teks_soal' => 'Sudah diubah di ujian']);
        $this->assertSame('Soal esai bagus', $bankSoal->fresh()->teks_soal);
    }
}
