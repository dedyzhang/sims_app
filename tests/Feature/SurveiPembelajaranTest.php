<?php
namespace Tests\Feature;

use App\Models\{Guru,Kelas,Ngajar,Pelajaran,Siswa,User,Walikelas};
use App\Survei\{Periode,Pertanyaan,Respons,Target};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SurveiPembelajaranTest extends TestCase
{
    use RefreshDatabase;
    private function user(string $name,string $role): User { return User::create(['username'=>$name,'password'=>Hash::make('password'),'access'=>$role]); }
    private function student(string $name,Kelas $kelas): Siswa { $u=$this->user($name,'siswa'); return Siswa::create(['id_login'=>$u->uuid,'nama'=>$name,'nis'=>$name,'id_kelas'=>$kelas->uuid,'jk'=>'L']); }
    private function fixture(): array {
        \App\Models\Setting::set('wajib_daftar_wajah', '0');
        $admin=$this->user('survei_admin','admin');
        $kelas=Kelas::create(['tingkat'=>7,'kelas'=>'A']);
        $guruUser=$this->user('survei_guru','guru');
        $guru=Guru::create(['id_login'=>$guruUser->uuid,'nama'=>'Guru Contoh']);
        $mapel=Pelajaran::create(['nama'=>'Matematika','kode'=>'MTK']);
        Ngajar::create(['id_kelas'=>$kelas->uuid,'id_guru'=>$guru->uuid,'id_pelajaran'=>$mapel->uuid]);
        Walikelas::create(['id_kelas'=>$kelas->uuid,'id_guru'=>$guru->uuid]);
        $siswa=$this->student('survei_siswa',$kelas);
        return compact('admin','kelas','guruUser','guru','mapel','siswa');
    }
    private function periode(): Periode {
        return Periode::create(['judul'=>'Semester','mulai'=>now()->subDay(),'selesai'=>now()->addDay(),'dibuat_oleh'=>'admin','pertanyaan'=>['mapel'=>Pertanyaan::untuk('mapel'),'wali'=>Pertanyaan::untuk('wali'),'komentar_mapel'=>Pertanyaan::terbuka('mapel'),'komentar_wali'=>Pertanyaan::terbuka('wali')]]);
    }
    private function target(Periode $p, array $f): Target { return $p->targets()->create(['kelas_id'=>$f['kelas']->uuid,'guru_id'=>$f['guru']->uuid,'pelajaran_id'=>$f['mapel']->uuid,'jenis'=>'mapel','kelas_nama'=>'Kelas 7 A','guru_nama'=>'Guru Contoh','pelajaran_nama'=>'Matematika']); }
    public function test_admin_membuat_sasaran_dan_qr_tersedia(): void {
        $f=$this->fixture();
        $this->actingAs($f['admin'])->post(route('survei.store'),['judul'=>'Semester','mulai'=>now()->subHour()->format('Y-m-d H:i:s'),'selesai'=>now()->addDay()->format('Y-m-d H:i:s'),'kelas'=>[$f['kelas']->uuid]])->assertRedirect();
        $p=Periode::firstOrFail(); $this->assertCount(2,$p->targets); $this->actingAs($f['admin'])->get(route('survei.periode',$p))->assertOk()->assertSee('svg',false)->assertSee(route('survei.masuk',$p));
    }
    public function test_siswa_hanya_kelasnya_kirim_sekali_dan_tidak_bisa_setelah_tutup(): void {
        $f=$this->fixture(); $p=$this->periode(); $t=$this->target($p,$f);
        $other=$this->student('kelas_lain',Kelas::create(['tingkat'=>7,'kelas'=>'B']));
        $this->actingAs($other->user)->get(route('survei.isi',$t))->assertForbidden();
        $this->actingAs($f['siswa']->user)->get(route('survei.masuk',$p))->assertRedirect(route('survei.index'));
        $this->get(route('survei.isi',$t))->assertOk()->assertSee('Tidak dapat menilai');
        $answers=array_fill(0,12,'4');
        $this->post(route('survei.kirim',$t),['jawaban'=>$answers,'komentar'=>['Bagus']])->assertRedirect();
        $this->post(route('survei.kirim',$t),['jawaban'=>$answers])->assertRedirect();
        $this->assertSame(1,Respons::count());
        $p->update(['ditutup'=>true]); $this->post(route('survei.kirim',$t),['jawaban'=>$answers])->assertForbidden();
    }
    public function test_scope_sekolah_dan_siswa_tidak_bisa_kelola_periode(): void {
        $f=$this->fixture(); $p=$this->periode(); $t=$this->target($p,$f);
        $this->actingAs($f['siswa']->user)->post(route('survei.store'),[])->assertForbidden();
        \Illuminate\Support\Facades\DB::table('survei_targets')->where('uuid',$t->uuid)->update(['school_id'=>'99999999-9999-9999-9999-999999999999']);
        $this->get(route('survei.isi',$t))->assertNotFound();
    }
    public function test_input_jawaban_wajib_lengkap_dan_tidak_menerima_nilai_tak_sah(): void {
        $f=$this->fixture(); $p=$this->periode(); $t=$this->target($p,$f);
        $this->actingAs($f['siswa']->user)->post(route('survei.kirim',$t),['jawaban'=>array_fill(0,12,'9')])->assertSessionHasErrors();
        $this->assertSame(0,Respons::count());
    }
    public function test_qr_mengarahkan_kembali_siswa_setelah_login(): void {
        $f=$this->fixture(); $p=$this->periode(); $this->target($p,$f);
        $this->get(route('survei.masuk',$p))->assertRedirect(route('login'));
        $this->post(route('login.post'),['credential'=>'survei_siswa','password'=>'password'])->assertRedirect(route('survei.masuk',$p));
    }
    public function test_ekspor_hanya_admin_setelah_tutup_dan_tidak_bocor_kelompok_kecil(): void {
        $f=$this->fixture(); $p=$this->periode(); $t=$this->target($p,$f);
        $this->actingAs($f['admin'])->get(route('survei.export',$p))->assertForbidden();
        $p->update(['ditutup'=>true]);
        for ($i=0;$i<5;$i++) Respons::create(['target_id'=>$t->uuid,'siswa_id'=>'export-'.$i,'jawaban'=>array_fill(0,12,'4'),'komentar'=>['KOMENTAR RAHASIA']]);
        $this->actingAs($f['guruUser'])->get(route('survei.export',$p))->assertForbidden();
        $csv=$this->actingAs($f['admin'])->get(route('survei.export',$p))->assertOk()->streamedContent();
        $this->assertStringContainsString('Matematika',$csv); $this->assertStringNotContainsString('KOMENTAR RAHASIA',$csv);
    }
    public function test_hasil_tertutup_dan_terbatas_untuk_guru_yang_berhak(): void {
        $f=$this->fixture(); $p=$this->periode(); $t=$this->target($p,$f);
        $other=$this->user('guru_lain','guru'); Guru::create(['id_login'=>$other->uuid,'nama'=>'Guru Lain']);
        $this->actingAs($other)->get(route('survei.hasil',$t))->assertForbidden();
        $this->actingAs($f['guruUser'])->get(route('survei.hasil',$t))->assertForbidden();
        $p->update(['ditutup'=>true]);
        $this->get(route('survei.hasil',$t))->assertOk()->assertSee('kurang dari lima');
        for ($i=0;$i<5;$i++) Respons::create(['target_id'=>$t->uuid,'siswa_id'=>'siswa-'.$i,'jawaban'=>array_fill(0,12,$i===0?'0':'4'),'komentar'=>['pribadi']]);
        $this->get(route('survei.hasil',$t))->assertOk()->assertSee('Belum cukup jawaban')->assertDontSee('pribadi');
        $this->actingAs($f['admin'])->get(route('survei.hasil',$t))->assertOk()->assertSee('pribadi');
    }
}
