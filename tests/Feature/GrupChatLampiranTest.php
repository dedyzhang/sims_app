<?php

namespace Tests\Feature;

use App\Models\GrupChat;
use App\Models\GrupChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MembangunSekolahGrupChat;
use Tests\TestCase;

class GrupChatLampiranTest extends TestCase
{
    use RefreshDatabase, MembangunSekolahGrupChat;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->bangunSekolah();
    }

    // ─────────────────────── Balas ───────────────────────

    public function test_balas_pesan_menyimpan_kutipan(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $asli = $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $grup), ['body' => 'Besok libur ya?'])
            ->json('message');

        $this->actingAs($this->siswa7a)
            ->postJson(route('grup.pesan', $grup), [
                'body' => 'Iya betul bu',
                'reply_to_id' => $asli['uuid'],
            ])
            ->assertCreated()
            ->assertJsonPath('message.reply_snippet', 'Besok libur ya?')
            ->assertJsonPath('message.reply_nama', 'Bu Ani');
    }

    public function test_balas_pesan_dari_grup_lain_ditolak(): void
    {
        $a = $this->grupKelas($this->kelas7a);
        $b = $this->grupKelas($this->kelas7b);

        $asli = $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $a), ['body' => 'punya kelas A'])
            ->json('message');

        $this->actingAs($this->wali7b)
            ->postJson(route('grup.pesan', $b), [
                'body' => 'coba balas lintas grup',
                'reply_to_id' => $asli['uuid'],
            ])
            ->assertStatus(422);
    }

    public function test_balas_pesan_yang_sudah_dihapus_ditolak(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $asli = $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $grup), ['body' => 'akan dihapus'])
            ->json('message');

        $pesan = GrupChatMessage::where('uuid', $asli['uuid'])->first();
        $pesan->update(['deleted_at' => now(), 'deleted_by' => $this->wali7a->uuid]);

        $this->actingAs($this->siswa7a)
            ->postJson(route('grup.pesan', $grup), [
                'body' => 'balas pesan terhapus',
                'reply_to_id' => $asli['uuid'],
            ])
            ->assertStatus(422);
    }

    public function test_mode_pengumuman_membatasi_balas_ke_pesan_staf_saja(): void
    {
        $grup = $this->grupKelas($this->kelas7a);
        $grup->update(['mode' => GrupChat::MODE_PENGUMUMAN]);

        $pengumuman = $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $grup), ['body' => 'Pengumuman penting'])
            ->json('message');

        // Siswa boleh balas pesan staf walau mode pengumuman.
        $this->actingAs($this->siswa7a)
            ->postJson(route('grup.pesan', $grup), [
                'body' => 'siap bu',
                'reply_to_id' => $pengumuman['uuid'],
            ])
            ->assertCreated();

        $balasanSiswa = GrupChatMessage::where('body', 'siap bu')->first();

        // Siswa lain TIDAK boleh balas pesan sesama siswa di mode pengumuman.
        $this->actingAs($this->siswa7a)
            ->postJson(route('grup.pesan', $grup), [
                'body' => 'coba balas siswa lain',
                'reply_to_id' => $balasanSiswa->uuid,
            ])
            ->assertStatus(403);
    }

    /**
     * Komposer harus ikut membuka jalur balas walau boleh_kirim false — ini yang
     * membuat celah lama (server mengizinkan balas, tapi UI tak pernah menampilkan
     * kotak tulisnya) tidak terulang. Lihat GrupChatController::bolehBalasPengumuman().
     */
    public function test_poll_mengizinkan_balas_pengumuman_untuk_non_staf(): void
    {
        $grup = $this->grupKelas($this->kelas7a);
        $grup->update(['mode' => GrupChat::MODE_PENGUMUMAN]);

        // Siswa: tidak boleh kirim bebas, tapi boleh balas pesan staf.
        $this->actingAs($this->siswa7a)
            ->getJson(route('grup.poll', $grup).'?after=0')
            ->assertOk()
            ->assertJsonPath('boleh_kirim', false)
            ->assertJsonPath('boleh_balas_pengumuman', true);

        // Staf: boleh_kirim sudah true, jadi flag balas-pengumuman tidak relevan (false).
        $this->actingAs($this->wali7a)
            ->getJson(route('grup.poll', $grup).'?after=0')
            ->assertOk()
            ->assertJsonPath('boleh_kirim', true)
            ->assertJsonPath('boleh_balas_pengumuman', false);

        // Mode biasa (bukan pengumuman): siswa boleh kirim bebas, flag tetap false.
        $grupBiasa = $this->grupKelas($this->kelas7b);
        $this->actingAs($this->siswa7b)
            ->getJson(route('grup.poll', $grupBiasa).'?after=0')
            ->assertOk()
            ->assertJsonPath('boleh_kirim', true)
            ->assertJsonPath('boleh_balas_pengumuman', false);
    }

    // ─────────────────────── Lampiran ───────────────────────

    public function test_kirim_lampiran_gambar_tersimpan_dan_bisa_diunduh(): void
    {
        $grup = $this->grupKelas($this->kelas7a);
        $file = File::image('foto.jpg', 800, 600);

        $res = $this->actingAs($this->wali7a)
            ->postJson(route('grup.lampiran', $grup), ['file' => $file, 'body' => 'lihat ini'])
            ->assertCreated();

        $res->assertJsonPath('message.lampiran.tipe', 'image')
            ->assertJsonPath('message.body', 'lihat ini');

        $pesan = GrupChatMessage::first();
        $this->assertNotNull($pesan->attachment_path);
        Storage::disk('local')->assertExists($pesan->attachment_path);

        $this->actingAs($this->siswa7a)
            ->get(route('grup.lampiran.unduh', [$grup, $pesan]))
            ->assertOk();
    }

    public function test_kirim_lampiran_berkas_dokumen(): void
    {
        $grup = $this->grupKelas($this->kelas7a);
        $file = File::create('surat.pdf', 100, 'application/pdf');

        $this->actingAs($this->wali7a)
            ->postJson(route('grup.lampiran', $grup), ['file' => $file])
            ->assertCreated()
            ->assertJsonPath('message.lampiran.tipe', 'file')
            ->assertJsonPath('message.lampiran.nama', 'surat.pdf')
            ->assertJsonPath('message.body', null);
    }

    public function test_lampiran_terlalu_besar_ditolak(): void
    {
        $grup = $this->grupKelas($this->kelas7a);
        $file = File::image('besar.jpg')->size(6000);

        $this->actingAs($this->wali7a)
            ->postJson(route('grup.lampiran', $grup), ['file' => $file])
            ->assertStatus(422);

        $this->assertDatabaseCount('grup_chat_messages', 0);
    }

    public function test_bukan_anggota_tidak_bisa_mengunduh_lampiran(): void
    {
        $grup = $this->grupKelas($this->kelas7a);
        $file = File::image('foto.jpg');

        $this->actingAs($this->wali7a)
            ->postJson(route('grup.lampiran', $grup), ['file' => $file])
            ->assertCreated();

        $pesan = GrupChatMessage::first();

        // Ortu bukan anggota grup kelas (hanya paguyuban).
        $this->actingAs($this->ortu7a)
            ->get(route('grup.lampiran.unduh', [$grup, $pesan]))
            ->assertForbidden();
    }

    // ─────────────────────── Hapus ───────────────────────

    public function test_pengirim_bisa_menghapus_pesan_sendiri(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $pesan = $this->actingAs($this->siswa7a)
            ->postJson(route('grup.pesan', $grup), ['body' => 'salah ketik'])
            ->json('message');

        $this->actingAs($this->siswa7a)
            ->deleteJson(route('grup.pesan.hapus', [$grup, $pesan['uuid']]))
            ->assertOk()
            ->assertJsonPath('message.dihapus', true)
            ->assertJsonPath('message.body', 'Pesan ini dihapus.');
    }

    public function test_siswa_tidak_bisa_menghapus_pesan_orang_lain(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $pesan = $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $grup), ['body' => 'pesan wali'])
            ->json('message');

        $this->actingAs($this->siswa7a)
            ->deleteJson(route('grup.pesan.hapus', [$grup, $pesan['uuid']]))
            ->assertForbidden();

        $this->assertDatabaseHas('grup_chat_messages', ['uuid' => $pesan['uuid'], 'deleted_at' => null]);
    }

    public function test_walikelas_moderasi_bisa_menghapus_pesan_siswa(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $pesan = $this->actingAs($this->siswa7a)
            ->postJson(route('grup.pesan', $grup), ['body' => 'kata kasar'])
            ->json('message');

        $this->actingAs($this->wali7a)
            ->deleteJson(route('grup.pesan.hapus', [$grup, $pesan['uuid']]))
            ->assertOk()
            ->assertJsonPath('message.dihapus', true);
    }

    /**
     * Route::scopeBindings() (lihat routes/web.php) harus menolak kombinasi
     * {grup}/{pesan} yang tidak nyambung SEBELUM controller dijalankan — bukan
     * cuma diblokir oleh abort_unless() manual di dalam hapus()/unduhLampiran().
     */
    public function test_hapus_pesan_dari_grup_lain_dikembalikan_404(): void
    {
        $a = $this->grupKelas($this->kelas7a);
        $b = $this->grupKelas($this->kelas7b);

        $pesan = $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $a), ['body' => 'punya kelas A'])
            ->json('message');

        $this->actingAs($this->wali7b)
            ->deleteJson(route('grup.pesan.hapus', [$b, $pesan['uuid']]))
            ->assertNotFound();
    }

    public function test_unduh_lampiran_dari_grup_lain_dikembalikan_404(): void
    {
        $a = $this->grupKelas($this->kelas7a);
        $b = $this->grupKelas($this->kelas7b);

        $pesan = $this->actingAs($this->wali7a)
            ->postJson(route('grup.lampiran', $a), ['file' => File::image('foto.jpg')])
            ->json('message');

        $this->actingAs($this->wali7b)
            ->get(route('grup.lampiran.unduh', [$b, $pesan['uuid']]))
            ->assertNotFound();
    }

    public function test_hapus_pesan_terakhir_memperbarui_preview_grup(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $pesan = $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $grup), ['body' => 'pesan terakhir'])
            ->json('message');

        $this->actingAs($this->wali7a)
            ->deleteJson(route('grup.pesan.hapus', [$grup, $pesan['uuid']]))
            ->assertOk();

        $grup->refresh();
        $this->assertSame('Pesan ini dihapus.', $grup->last_message_preview);
    }

    public function test_hapus_lampiran_menghapus_berkas_fisik_dari_disk(): void
    {
        $grup = $this->grupKelas($this->kelas7a);
        $file = File::image('foto.jpg');

        $data = $this->actingAs($this->wali7a)
            ->postJson(route('grup.lampiran', $grup), ['file' => $file])
            ->json('message');

        $pesan = GrupChatMessage::where('uuid', $data['uuid'])->first();
        $path = $pesan->attachment_path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->wali7a)
            ->deleteJson(route('grup.pesan.hapus', [$grup, $pesan->uuid]))
            ->assertOk()
            ->assertJsonPath('message.lampiran', null);

        Storage::disk('local')->assertMissing($path);
    }

    public function test_hapus_pesan_yang_sudah_dibalas_membersihkan_kutipan(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $asli = $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $grup), ['body' => 'akan dimoderasi'])
            ->json('message');

        $this->actingAs($this->siswa7a)
            ->postJson(route('grup.pesan', $grup), [
                'body' => 'siap bu',
                'reply_to_id' => $asli['uuid'],
            ])
            ->assertJsonPath('message.reply_snippet', 'akan dimoderasi');

        $this->actingAs($this->wali7a)
            ->deleteJson(route('grup.pesan.hapus', [$grup, $asli['uuid']]))
            ->assertOk();

        $balasan = GrupChatMessage::where('body', 'siap bu')->first();
        $this->assertSame('Pesan ini dihapus.', $balasan->reply_snippet);
        $this->assertNull($balasan->reply_nama);
    }

    public function test_pesan_yang_sudah_dihapus_tidak_bisa_dihapus_lagi(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $pesan = $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $grup), ['body' => 'hapus dulu'])
            ->json('message');

        $this->actingAs($this->wali7a)
            ->deleteJson(route('grup.pesan.hapus', [$grup, $pesan['uuid']]))
            ->assertOk();

        $this->actingAs($this->wali7a)
            ->deleteJson(route('grup.pesan.hapus', [$grup, $pesan['uuid']]))
            ->assertForbidden();
    }
}
