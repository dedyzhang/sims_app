<?php

namespace Tests\Feature;

use App\Models\ForumComment;
use App\Models\ForumTopic;
use App\Models\Guru;
use App\Models\User;
use Database\Seeders\ForumPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ForumController::presence() (endpoint polling — dipanggil berkala selagi topik terbuka)
 * dulu memanggil private participants() DUA KALI dalam satu request yang sama (sekali utk
 * daftar peserta, sekali lagi cuma utk hitung 'online') — query yg identik dieksekusi ulang
 * tanpa perlu. participants() sendiri juga tak eager-load relasi guru/siswa tiap User,
 * sehingga displayName() per peserta jadi query lazy-load terpisah. Test ini mengunci baik
 * PERILAKU (hasil presence tetap benar) maupun jumlah query (tak boleh naik lagi).
 */
class ForumPresenceQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        $this->seed(ForumPermissionSeeder::class);
    }

    private function makeGuruUser(string $username): User
    {
        $user = User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access'   => 'guru',
        ]);
        Guru::create([
            'id_login' => $user->uuid,
            'nama'     => 'Guru ' . $username,
            'nik'      => '11122233' . random_int(10, 99),
            'jk'       => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);

        return $user;
    }

    public function test_presence_mengembalikan_peserta_dan_hitungan_online_yg_benar(): void
    {
        $admin = User::create([
            'username' => 'admin_presence',
            'password' => Hash::make('password'),
            'access'   => 'admin',
        ]);
        $author = $this->makeGuruUser('author_presence');
        $commenter = $this->makeGuruUser('commenter_presence');
        $commenter->forceFill(['last_seen_at' => now()])->save(); // online

        $topic = ForumTopic::create([
            'created_by' => $author->uuid,
            'title'      => 'Topik Presence',
            'slug'       => 'topik-presence-' . uniqid(),
            'body'       => 'Isi topik',
            'audience'   => 'guru',
            'category'   => 'umum',
        ]);
        ForumComment::create([
            'topic_id' => $topic->uuid,
            'user_id'  => $commenter->uuid,
            'body'     => 'Komentar pertama',
        ]);

        $resp = $this->actingAs($admin)->getJson("/forum/{$topic->slug}/presence");
        $resp->assertOk();

        $ids = collect($resp->json('participants'))->pluck('id')->all();
        $this->assertContains($author->uuid, $ids);
        $this->assertContains($commenter->uuid, $ids);
        $this->assertSame(1, $resp->json('online'));
    }

    public function test_presence_tidak_menjalankan_query_partisipan_dua_kali(): void
    {
        $admin = User::create([
            'username' => 'admin_presence2',
            'password' => Hash::make('password'),
            'access'   => 'admin',
        ]);
        $author = $this->makeGuruUser('author_presence2');

        $topic = ForumTopic::create([
            'created_by' => $author->uuid,
            'title'      => 'Topik Presence 2',
            'slug'       => 'topik-presence-2-' . uniqid(),
            'body'       => 'Isi topik',
            'audience'   => 'guru',
            'category'   => 'umum',
        ]);
        for ($i = 0; $i < 3; $i++) {
            $c = $this->makeGuruUser('commenter_presence2_' . $i);
            ForumComment::create([
                'topic_id' => $topic->uuid,
                'user_id'  => $c->uuid,
                'body'     => 'Komentar ' . $i,
            ]);
        }

        $this->actingAs($admin);
        $this->getJson("/forum/{$topic->slug}/presence"); // pemanasan

        DB::enableQueryLog();
        $resp = $this->getJson("/forum/{$topic->slug}/presence");
        $log = DB::getQueryLog();
        DB::disableQueryLog();
        $resp->assertOk();

        $commentQueries = array_filter($log, fn ($q) => str_contains($q['query'], 'forum_comments') && str_contains($q['query'], 'select'));
        $this->assertCount(
            1,
            $commentQueries,
            'participants() semestinya cuma dihitung SEKALI per request presence(), bukan dua kali (list + hitung online).'
        );
    }
}
