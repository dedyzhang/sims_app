<?php

namespace Tests\Feature;

use App\Models\AiDocument;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use App\Support\FaceMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function guruUser(string $username = 'guru_ai'): User
    {
        $user = User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        Guru::create([
            'id_login' => $user->getKey(),
            'nama' => 'Guru AI',
            'nik' => 'GRU001',
            'jk' => 'L',
            'face_descriptor' => [array_fill(0, 64, 0.1)],
        ]);

        return $user;
    }

    private function siswaUser(string $username = 'siswa_ai'): User
    {
        static $counter = 0;
        $counter++;

        $user = User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);
        Siswa::create([
            'id_login' => $user->getKey(),
            'nama' => 'Siswa AI',
            'nis' => 'SIS'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'jk' => 'L',
            'face_descriptor' => [array_fill(0, 64, 0.2)],
        ]);

        return $user;
    }

    private function descriptors(int $count, float $seed): array
    {
        $descriptors = [];
        for ($i = 1; $i <= $count; $i++) {
            $descriptors[] = array_fill(0, 64, ($seed + $i) / 100);
        }

        return $descriptors;
    }

    public function test_siswa_ditolak_akses_ai_chat(): void
    {
        $siswa = $this->siswaUser();

        $this->actingAs($siswa)
            ->postJson('/ai/chat', ['message' => 'halo'])
            ->assertForbidden();
    }

    public function test_guru_boleh_akses_ai_chat(): void
    {
        $this->mock(\App\Services\GeminiService::class, function ($mock) {
            $mock->shouldReceive('generate')->once()->andReturn([
                'text' => 'Halo',
                'model' => 'gemini-2.5-flash',
                'prompt_tokens' => 1,
                'completion_tokens' => 1,
            ]);
        });

        $this->actingAs($this->guruUser())
            ->postJson('/ai/chat', ['message' => 'halo'])
            ->assertOk();
    }

    public function test_lampiran_chat_tidak_bisa_diunduh_tanpa_login(): void
    {
        Storage::fake('local');
        $siswa = $this->siswaUser();
        $conv = ChatbotConversation::create([
            'user_id' => $siswa->getKey(),
            'mode' => 'bot',
            'status' => 'active',
            'started_at' => now(),
        ]);
        $path = 'chat/test-file.pdf';
        Storage::disk('local')->put($path, 'secret');
        $message = ChatbotMessage::create([
            'conversation_id' => $conv->id,
            'sender' => 'user',
            'body' => 'lampiran',
            'attachment_path' => $path,
        ]);

        $this->get(route('chatbot.attachment', $message))->assertRedirect(route('login'));
    }

    public function test_lampiran_chat_ditolak_untuk_user_lain(): void
    {
        Storage::fake('local');
        $owner = $this->siswaUser('siswa_owner');
        $other = $this->siswaUser('siswa_other');
        $conv = ChatbotConversation::create([
            'user_id' => $owner->getKey(),
            'mode' => 'bot',
            'status' => 'active',
            'started_at' => now(),
        ]);
        $path = 'chat/private.pdf';
        Storage::disk('local')->put($path, 'secret');
        $message = ChatbotMessage::create([
            'conversation_id' => $conv->id,
            'sender' => 'user',
            'body' => 'lampiran',
            'attachment_path' => $path,
        ]);

        $this->actingAs($other)
            ->get(route('chatbot.attachment', $message))
            ->assertForbidden();
    }

    public function test_lampiran_chat_bisa_diunduh_pemilik(): void
    {
        Storage::fake('local');
        $siswa = $this->siswaUser();
        $conv = ChatbotConversation::create([
            'user_id' => $siswa->getKey(),
            'mode' => 'bot',
            'status' => 'active',
            'started_at' => now(),
        ]);
        $path = 'chat/owned.pdf';
        Storage::disk('local')->put($path, 'secret-content');
        $message = ChatbotMessage::create([
            'conversation_id' => $conv->id,
            'sender' => 'user',
            'body' => 'lampiran',
            'attachment_path' => $path,
        ]);

        $this->actingAs($siswa)
            ->get(route('chatbot.attachment', $message))
            ->assertOk();
    }

    public function test_upload_chatbot_menyimpan_ke_disk_privat(): void
    {
        Storage::fake('local');
        $siswa = $this->siswaUser();

        $this->actingAs($siswa)
            ->post('/chatbot/upload', [
                'image' => UploadedFile::fake()->image('bukti.jpg'),
                'caption' => 'bukti',
            ])
            ->assertOk();

        $message = ChatbotMessage::where('sender', 'user')->first();
        $this->assertNotNull($message);
        $this->assertStringStartsWith('chat/', $message->attachment_path);
        Storage::disk('local')->assertExists($message->attachment_path);
        $this->assertFalse(file_exists(public_path($message->attachment_path)));
    }

    public function test_face_photo_path_injection_ditolak(): void
    {
        $owner = (string) \Illuminate\Support\Str::uuid();
        $other = (string) \Illuminate\Support\Str::uuid();

        $this->assertNull(FaceMatch::photoUrl('faces/'.$other.'_20260101120000.jpg', $owner));
        $this->assertNull(FaceMatch::photoUrl('http://evil.test/track.jpg'));
        $this->assertNull(FaceMatch::saveFromDataUrl('uploads/chat/evil.jpg', $owner, null));
    }

    public function test_registrasi_wajah_duplikat_ditolak(): void
    {
        $existing = Siswa::create([
            'nama' => 'Siswa Lama',
            'nis' => 'DUP001',
            'jk' => 'L',
            'face_descriptor' => $this->descriptors(3, 0.5),
        ]);

        $user = User::create([
            'username' => 'siswa_dup',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);
        $siswa = Siswa::create([
            'id_login' => $user->getKey(),
            'nama' => 'Siswa Baru',
            'nis' => 'DUP002',
            'jk' => 'P',
        ]);

        $this->actingAs($user)
            ->postJson('/wajah-saya', [
                'descriptors' => $this->descriptors(3, 0.5),
                'photo' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('duplicate', true);

        $this->assertNull($siswa->fresh()->face_descriptor);
        $this->assertNotNull($existing->fresh()->face_descriptor);
    }

    public function test_force_true_tidak_bypass_duplikat_wajah(): void
    {
        Siswa::create([
            'nama' => 'Siswa Lama',
            'nis' => 'FRC001',
            'jk' => 'L',
            'face_descriptor' => $this->descriptors(3, 0.7),
        ]);

        $user = User::create([
            'username' => 'siswa_force',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);
        Siswa::create([
            'id_login' => $user->getKey(),
            'nama' => 'Siswa Baru',
            'nis' => 'FRC002',
            'jk' => 'P',
        ]);

        $this->actingAs($user)
            ->postJson('/wajah-saya', [
                'descriptors' => $this->descriptors(3, 0.7),
                'photo' => null,
                'force' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('duplicate', true);
    }

    public function test_rag_destroy_ditolak_untuk_dokumen_orang_lain(): void
    {
        $owner = User::create([
            'username' => 'rag_owner',
            'password' => Hash::make('password'),
            'access' => 'kurikulum',
        ]);
        $other = User::create([
            'username' => 'rag_other',
            'password' => Hash::make('password'),
            'access' => 'kurikulum',
        ]);

        $doc = AiDocument::create([
            'user_uuid' => $owner->uuid,
            'title' => 'Dokumen A',
            'file_path' => 'ai_documents/test.pdf',
            'status' => AiDocument::STATUS_PROCESSED,
        ]);

        $this->actingAs($other)
            ->deleteJson(route('ai.rag.destroy', $doc))
            ->assertForbidden();

        $this->assertDatabaseHas('ai_documents', ['uuid' => $doc->uuid]);
    }
}
