<?php

namespace Tests\Feature;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Pelajaran;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke test integrasi chatbot ke SIMS (mode handoff + chat).
 * Memverifikasi akses berbasis role SIMS (access) + alur bot↔admin tanpa error.
 */
class ChatbotIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $access, string $username): User
    {
        return User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access' => $access,
        ]);
    }

    public function test_siswa_dapat_membuka_widget_dan_mendapat_balasan_bot(): void
    {
        $siswa = $this->makeUser('siswa', 'siswa_test');

        $this->actingAs($siswa)->get('/chatbot')->assertOk();

        $res = $this->actingAs($siswa)->postJson('/chatbot/send', ['message' => 'bantuan']);
        $res->assertOk()
            ->assertJsonPath('mode', 'bot')
            ->assertJsonStructure(['conversation_id', 'user_message', 'bot_message' => ['id', 'sender', 'body']]);

        $this->assertDatabaseHas('chatbot_messages', ['sender' => 'user', 'body' => 'bantuan']);
        $this->assertDatabaseHas('chatbot_messages', ['sender' => 'bot']);
    }

    public function test_guru_dan_role_non_admin_boleh_mengakses_widget(): void
    {
        // Semua role pengguna (guru, walikelas, waka/kurikulum, dll) boleh memakai widget.
        foreach (['guru', 'walikelas', 'kurikulum', 'kepala', 'orangtua'] as $i => $access) {
            $user = $this->makeUser($access, "user_{$access}_{$i}");

            $this->actingAs($user)->get('/chatbot')->assertOk();
            $this->actingAs($user)->postJson('/chatbot/send', ['message' => 'hai'])->assertOk();
        }
    }

    public function test_admin_tidak_boleh_mengakses_widget_penanya(): void
    {
        // Admin memegang Inbox, bukan widget penanya.
        $admin = $this->makeUser('admin', 'admin_widget');

        $this->actingAs($admin)->get('/chatbot')->assertForbidden();
        $this->actingAs($admin)->postJson('/chatbot/send', ['message' => 'hai'])->assertForbidden();
    }

    public function test_non_admin_tidak_boleh_mengakses_inbox(): void
    {
        $siswa = $this->makeUser('siswa', 'siswa_inbox');

        $this->actingAs($siswa)->get('/chatbot/admin/inbox')->assertForbidden();
    }

    public function test_admin_dapat_merender_halaman_inbox(): void
    {
        $admin = $this->makeUser('admin', 'admin_render');

        $this->actingAs($admin)->get('/chatbot/admin/inbox')
            ->assertOk()
            ->assertSee('Inbox', false);
    }

    public function test_alur_handoff_user_minta_admin_lalu_admin_balas(): void
    {
        $siswa = $this->makeUser('siswa', 'siswa_handoff');
        $admin = $this->makeUser('admin', 'admin_handoff');

        // 1. Siswa membuat percakapan via kirim pesan.
        $this->actingAs($siswa)->postJson('/chatbot/send', ['message' => 'halo'])->assertOk();
        $conversation = ChatbotConversation::where('user_id', $siswa->getKey())->firstOrFail();

        // 2. Siswa minta dihubungkan ke admin.
        $this->actingAs($siswa)
            ->postJson("/chatbot/{$conversation->id}/request-human")
            ->assertOk()
            ->assertJsonPath('mode', 'human')
            ->assertJsonPath('status', 'waiting');

        // 3. Admin melihat antrian.
        $this->actingAs($admin)->getJson('/chatbot/admin/queue')
            ->assertOk()
            ->assertJsonPath('waiting_count', 1);

        // 4. Admin mengambil & membalas.
        $this->actingAs($admin)->postJson("/chatbot/admin/{$conversation->id}/assign")->assertOk();
        $this->actingAs($admin)->postJson("/chatbot/admin/{$conversation->id}/reply", [
            'body' => 'Halo, ada yang bisa dibantu?',
        ])->assertOk()->assertJsonPath('status', 'assigned');

        $this->assertDatabaseHas('chatbot_messages', [
            'sender' => 'admin',
            'sender_user_id' => $admin->getKey(),
            'body' => 'Halo, ada yang bisa dibantu?',
        ]);

        // 5. Admin kembalikan ke bot.
        $this->actingAs($admin)->postJson("/chatbot/admin/{$conversation->id}/back-to-bot")
            ->assertOk()
            ->assertJsonPath('mode', 'bot');

        $this->assertSame('active', $conversation->refresh()->status);
    }

    public function test_unread_count_untuk_badge_floating_ball(): void
    {
        $siswa = $this->makeUser('siswa', 'siswa_unread');
        $admin = $this->makeUser('admin', 'admin_unread');

        // Belum ada percakapan → 0 (dan tidak membuat percakapan baru).
        $this->actingAs($siswa)->getJson('/chatbot/unread')
            ->assertOk()->assertJsonPath('unread', 0);

        // Siswa kirim pesan lalu minta dihubungkan ke admin.
        $this->actingAs($siswa)->postJson('/chatbot/send', ['message' => 'halo'])->assertOk();
        $conv = ChatbotConversation::where('user_id', $siswa->getKey())->firstOrFail();
        $this->actingAs($siswa)->postJson("/chatbot/{$conv->id}/request-human")->assertOk();

        // Siswa menyimak widget (poll) → semua balasan bot sebelumnya ditandai dibaca.
        $this->actingAs($siswa)->getJson("/chatbot/poll?conversation_id={$conv->id}")->assertOk();
        $this->actingAs($siswa)->getJson('/chatbot/unread')->assertOk()->assertJsonPath('unread', 0);

        // Admin membalas saat panel siswa tertutup → tepat 1 pesan masuk belum dibaca.
        $this->actingAs($admin)->postJson("/chatbot/admin/{$conv->id}/reply", ['body' => 'iya, ada apa?'])->assertOk();

        $this->actingAs($siswa)->getJson('/chatbot/unread')
            ->assertOk()->assertJsonPath('unread', 1);

        // Siswa membuka/menyimak widget (poll menandai sudah dibaca) → kembali 0.
        $this->actingAs($siswa)->getJson("/chatbot/poll?conversation_id={$conv->id}")->assertOk();
        $this->actingAs($siswa)->getJson('/chatbot/unread')
            ->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_admin_menutup_chat_pesan_tetap_tersimpan_sebagai_histori(): void
    {
        $siswa = $this->makeUser('siswa', 'siswa_close');
        $admin = $this->makeUser('admin', 'admin_close');

        // Percakapan + beberapa pesan.
        $this->actingAs($siswa)->postJson('/chatbot/send', ['message' => 'halo admin'])->assertOk();
        $conv = ChatbotConversation::where('user_id', $siswa->getKey())->firstOrFail();
        $jumlahSebelum = $conv->messages()->count();

        // Admin menutup percakapan.
        $this->actingAs($admin)->postJson("/chatbot/admin/{$conv->id}/close")
            ->assertOk()
            ->assertJsonPath('status', 'closed');

        $conv->refresh();
        $this->assertSame('closed', $conv->status);
        $this->assertNotNull($conv->closed_at);
        $this->assertSame($admin->getKey(), $conv->closed_by);

        // Pesan TIDAK hilang — malah bertambah (pesan sistem penutup).
        $this->assertGreaterThan($jumlahSebelum, $conv->messages()->count());

        // Percakapan tertutup muncul di histori admin.
        $this->actingAs($admin)->getJson('/chatbot/admin/history')
            ->assertOk()
            ->assertJsonPath('closed_count', 1)
            ->assertJsonPath('conversations.0.id', $conv->id)
            ->assertJsonPath('conversations.0.status', 'closed');

        // Tidak lagi muncul di antrian aktif.
        $this->actingAs($admin)->getJson('/chatbot/admin/queue')
            ->assertOk()
            ->assertJsonPath('waiting_count', 0)
            ->assertJsonCount(0, 'conversations');

        // Pesan baru dari user reuse percakapan (tidak membuat percakapan baru).
        $this->actingAs($siswa)->postJson('/chatbot/send', ['message' => 'halo lagi'])->assertOk();
        $this->assertSame(1, ChatbotConversation::where('user_id', $siswa->getKey())->count());
        $this->assertSame('active', $conv->refresh()->status);
    }

    public function test_lampiran_file_dokumen_ditolak_bila_tipe_terlarang(): void
    {
        $siswa = $this->makeUser('siswa', 'siswa_file_bad');

        // .exe bukan tipe dokumen yang diizinkan → validasi gagal.
        $this->actingAs($siswa)->postJson('/chatbot/upload-file', [
            'file' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertStatus(422);
    }

    public function test_lampiran_file_dokumen_disimpan_dan_dibedakan_dari_gambar(): void
    {
        $siswa = $this->makeUser('siswa', 'siswa_file_ok');

        // Uji inti penyimpanan lampiran file via service (tanpa menyentuh filesystem upload).
        $service = app(ChatbotService::class);
        $result = $service->handleFile($siswa, 'uploads/chat/abc123/laporan.pdf', 'ini laporannya');

        $um = $result['user_message'];
        $this->assertSame('laporan.pdf', $um['attachment_name']);
        $this->assertFalse($um['attachment_is_image']);   // dokumen, BUKAN gambar
        $this->assertSame('pdf', $um['attachment_ext']);

        // Pesan tersimpan di DB (bukti chat).
        $this->assertDatabaseHas('chatbot_messages', [
            'sender' => 'user',
            'attachment_path' => 'uploads/chat/abc123/laporan.pdf',
        ]);

        // Gambar terdeteksi sebagai gambar.
        $img = $service->handleImage($siswa, 'uploads/chat/foto.jpg', '');
        $this->assertTrue($img['user_message']['attachment_is_image']);
    }

    public function test_admin_dapat_membalas_dengan_file_komunikasi_dua_arah(): void
    {
        $siswa = $this->makeUser('siswa', 'siswa_2arah');
        $admin = $this->makeUser('admin', 'admin_2arah');

        // Siswa memulai percakapan.
        $this->actingAs($siswa)->postJson('/chatbot/send', ['message' => 'pak, kirim suratnya dong'])->assertOk();
        $conv = ChatbotConversation::where('user_id', $siswa->getKey())->firstOrFail();

        // Admin membalas dengan lampiran file (inti logika, tanpa menyentuh filesystem).
        $service = app(ChatbotService::class);
        $message = $service->replyAsAdmin($conv, $admin, 'ini suratnya ya', 'uploads/chat/xyz/surat-edaran.pdf');

        $this->assertSame('admin', $message->sender);
        $this->assertSame($admin->getKey(), $message->sender_user_id);

        $ser = $service->serializeMessage($message);
        $this->assertSame('surat-edaran.pdf', $ser['attachment_name']);
        $this->assertFalse($ser['attachment_is_image']); // dokumen dari admin

        $this->assertDatabaseHas('chatbot_messages', [
            'conversation_id' => $conv->id,
            'sender' => 'admin',
            'attachment_path' => 'uploads/chat/xyz/surat-edaran.pdf',
        ]);
    }

    public function test_reply_file_admin_menolak_tipe_terlarang(): void
    {
        $siswa = $this->makeUser('siswa', 'siswa_rf');
        $admin = $this->makeUser('admin', 'admin_rf');

        $this->actingAs($siswa)->postJson('/chatbot/send', ['message' => 'halo'])->assertOk();
        $conv = ChatbotConversation::where('user_id', $siswa->getKey())->firstOrFail();

        $this->actingAs($admin)->postJson("/chatbot/admin/{$conv->id}/reply-file", [
            'file' => UploadedFile::fake()->create('skrip.exe', 10),
        ])->assertStatus(422);
    }

    public function test_bot_menjawab_pertanyaan_howto_dari_faq(): void
    {
        $siswa = $this->makeUser('siswa', 'siswa_faq');

        $res = $this->actingAs($siswa)->postJson('/chatbot/send', [
            'message' => 'Bagaimana cara melakukan absensi QR?',
        ])->assertOk();

        $reply = $res->json('reply');
        // Bot menjawab how-to (bukan handoff generik "saya hubungkan kamu ke admin").
        $this->assertStringContainsString('Scan QR', $reply);
        $this->assertStringNotContainsString('hubungkan kamu ke admin', $reply);
    }

    public function test_bot_menjawab_biodata_siswa_sendiri(): void
    {
        $kelas = Kelas::create(['tingkat' => '11', 'kelas' => 'IPA 1']);
        $user = $this->makeUser('siswa', 'siswa_bio');
        Siswa::create([
            'id_login' => $user->getKey(),
            'nama' => 'Citra Lestari',
            'nis' => '12345',
            'nisn' => '0098765',
            'id_kelas' => $kelas->uuid,
            'jk' => 'P',
        ]);

        $reply = $this->actingAs($user->fresh())->postJson('/chatbot/send', [
            'message' => 'lihat biodata saya dong',
        ])->assertOk()->json('reply');

        $this->assertStringContainsString('Citra Lestari', $reply); // data sendiri muncul
        $this->assertStringContainsString('12345', $reply);          // NIS
        $this->assertStringContainsString('IPA 1', $reply);          // kelas
        $this->assertStringContainsString('Perempuan', $reply);      // jk dipetakan
    }

    public function test_bot_menjawab_rekap_kehadiran_siswa_sendiri(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00'));

        $user = $this->makeUser('siswa', 'siswa_absen');
        $siswa = Siswa::create(['id_login' => $user->getKey(), 'nama' => 'Dewi']);
        $lain = Siswa::create(['id_login' => $this->makeUser('siswa', 'siswa_lain_a')->getKey(), 'nama' => 'Eka']);

        // Kehadiran bulan ini milik siswa.
        \App\Models\Absensi::create(['id_siswa' => $siswa->uuid, 'tanggal' => '2026-06-02', 'status' => 'hadir']);
        \App\Models\Absensi::create(['id_siswa' => $siswa->uuid, 'tanggal' => '2026-06-03', 'status' => 'sakit']);
        // Milik siswa lain — tidak boleh terhitung.
        \App\Models\Absensi::create(['id_siswa' => $lain->uuid, 'tanggal' => '2026-06-02', 'status' => 'alpa']);

        $reply = $this->actingAs($user->fresh())->postJson('/chatbot/send', [
            'message' => 'rekap kehadiran saya bulan ini',
        ])->assertOk()->json('reply');

        $this->assertStringContainsString('Hadir: 1', $reply);
        $this->assertStringContainsString('Sakit: 1', $reply);
        $this->assertStringContainsString('Alpa: 0', $reply); // alpa siswa lain tak bocor

        Carbon::setTestNow();
    }

    public function test_bot_menjawab_info_wali_kelas(): void
    {
        $kelas = Kelas::create(['tingkat' => '12', 'kelas' => 'IPS 2']);
        $guruUser = $this->makeUser('guru', 'guru_wali');
        $guru = \App\Models\Guru::create(['id_login' => $guruUser->getKey(), 'nama' => 'Pak Hadi', 'nip' => '99887766']);
        \App\Models\Walikelas::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru->uuid]);

        $user = $this->makeUser('siswa', 'siswa_wali');
        Siswa::create(['id_login' => $user->getKey(), 'nama' => 'Fajar', 'id_kelas' => $kelas->uuid]);

        $reply = $this->actingAs($user->fresh())->postJson('/chatbot/send', [
            'message' => 'siapa wali kelas saya?',
        ])->assertOk()->json('reply');

        $this->assertStringContainsString('Pak Hadi', $reply);
        $this->assertStringContainsString('IPS 2', $reply);
    }

    public function test_bot_memblokir_nilai_demi_privasi(): void
    {
        $siswa = $this->makeUser('siswa', 'siswa_nilai');

        $reply = $this->actingAs($siswa)->postJson('/chatbot/send', [
            'message' => 'saya mau lihat nilai ujian dong',
        ])->assertOk()->json('reply');

        $this->assertStringContainsString('privasi', strtolower($reply));
        $this->assertStringContainsString('rapor', strtolower($reply));
    }

    public function test_bot_menjawab_jadwal_dari_data_nyata_dan_terscope(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-29 08:00:00')); // Senin (hari = 1)

        $kelas = Kelas::create(['tingkat' => '10', 'kelas' => 'A']);
        $kelasLain = Kelas::create(['tingkat' => '10', 'kelas' => 'B']);
        $mtk = Pelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        $bing = Pelajaran::create(['nama' => 'Bahasa Inggris', 'kode' => 'BING']);

        $user = $this->makeUser('siswa', 'siswa_jadwal');
        Siswa::create(['id_login' => $user->getKey(), 'nama' => 'Budi', 'id_kelas' => $kelas->uuid]);

        // Jadwal Senin untuk kelas siswa + kelas lain (tidak boleh bocor).
        Jadwal::create(['id_kelas' => $kelas->uuid, 'hari' => 1, 'jam_ke' => 1, 'jam_mulai' => '07:00', 'id_pelajaran' => $mtk->uuid]);
        Jadwal::create(['id_kelas' => $kelasLain->uuid, 'hari' => 1, 'jam_ke' => 1, 'jam_mulai' => '07:00', 'id_pelajaran' => $bing->uuid]);

        $reply = $this->actingAs($user->fresh())->postJson('/chatbot/send', [
            'message' => 'Kapan jadwal pelajaran hari ini?',
        ])->assertOk()->json('reply');

        $this->assertStringContainsString('Matematika', $reply);       // jadwal kelasnya muncul
        $this->assertStringNotContainsString('Bahasa Inggris', $reply); // kelas lain TIDAK bocor

        Carbon::setTestNow();
    }

    public function test_percakapan_terisolasi_milik_user_lain(): void
    {
        $a = $this->makeUser('siswa', 'siswa_a');
        $b = $this->makeUser('siswa', 'siswa_b');

        $this->actingAs($a)->postJson('/chatbot/send', ['message' => 'punya a'])->assertOk();
        $conv = ChatbotConversation::where('user_id', $a->getKey())->firstOrFail();

        // User B tidak boleh mengubah percakapan milik A.
        $this->actingAs($b)->postJson("/chatbot/{$conv->id}/request-human")->assertForbidden();
    }

    public function test_admin_dapat_menghapus_percakapan_secara_permanen(): void
    {
        $siswa = $this->makeUser('siswa', 'siswa_delete');
        $admin = $this->makeUser('admin', 'admin_delete');

        // Siswa mengirim pesan
        $this->actingAs($siswa)->postJson('/chatbot/send', ['message' => 'halo admin'])->assertOk();
        $conv = ChatbotConversation::where('user_id', $siswa->getKey())->firstOrFail();

        // Cek bahwa percakapan ada
        $this->assertDatabaseHas('chatbot_conversations', ['id' => $conv->id]);

        // Admin menghapus percakapan
        $this->actingAs($admin)->deleteJson("/chatbot/admin/{$conv->id}")->assertOk();

        // Cek bahwa percakapan dan pesan sudah terhapus di database
        $this->assertDatabaseMissing('chatbot_conversations', ['id' => $conv->id]);
        $this->assertDatabaseMissing('chatbot_messages', ['conversation_id' => $conv->id]);
    }

    public function test_admin_menghapus_percakapan_juga_menghapus_file_fisik(): void
    {
        $siswa = $this->makeUser('siswa', 'siswa_delete_file');
        $admin = $this->makeUser('admin', 'admin_delete_file');

        $this->actingAs($siswa)->postJson('/chatbot/send', ['message' => 'halo admin'])->assertOk();
        $conv = ChatbotConversation::where('user_id', $siswa->getKey())->firstOrFail();

        // Create a fake folder and file
        $folder = 'test-delete-folder-' . \Illuminate\Support\Str::uuid();
        $filePath = 'uploads/chat/' . $folder . '/document.pdf';
        
        $fullDirPath = public_path('uploads/chat/' . $folder);
        \Illuminate\Support\Facades\File::ensureDirectoryExists($fullDirPath);
        
        $fullFilePath = public_path($filePath);
        file_put_contents($fullFilePath, 'test content');

        $this->assertTrue(\Illuminate\Support\Facades\File::exists($fullFilePath));

        // Create a message in DB with attachment
        ChatbotMessage::create([
            'conversation_id' => $conv->id,
            'sender' => 'user',
            'body' => 'lampiran',
            'attachment_path' => $filePath,
        ]);

        // Admin menghapus percakapan
        $this->actingAs($admin)->deleteJson("/chatbot/admin/{$conv->id}")->assertOk();

        // Cek file dan foldernya sudah terhapus secara fisik
        $this->assertFalse(\Illuminate\Support\Facades\File::exists($fullFilePath));
        $this->assertFalse(\Illuminate\Support\Facades\File::exists($fullDirPath));
    }
}
