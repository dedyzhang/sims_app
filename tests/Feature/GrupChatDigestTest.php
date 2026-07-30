<?php

namespace Tests\Feature;

use App\Console\Commands\GrupChatKirimNotif;
use App\Models\GrupChatMember;
use App\Notifications\GrupChatDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\MembangunSekolahGrupChat;
use Tests\TestCase;

class GrupChatDigestTest extends TestCase
{
    use RefreshDatabase, MembangunSekolahGrupChat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bangunSekolah();
    }

    public function test_anggota_yang_belum_baca_dapat_digest(): void
    {
        Notification::fake();
        $grup = $this->grupKelas($this->kelas7a);

        $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => 'ada tugas baru']);

        $this->artisan('grupchat:kirim-notif')->assertExitCode(0);

        Notification::assertSentTo($this->siswa7a, GrupChatDigestNotification::class, function ($n) use ($grup) {
            return $n->groups[0]['grup_id'] === $grup->uuid && $n->groups[0]['unread'] === 1;
        });
    }

    public function test_pengirim_tidak_menerima_digest_pesannya_sendiri(): void
    {
        Notification::fake();
        $grup = $this->grupKelas($this->kelas7a);

        $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => 'halo semua']);

        $this->artisan('grupchat:kirim-notif');

        Notification::assertNotSentTo($this->wali7a, GrupChatDigestNotification::class);
    }

    public function test_anggota_yang_sudah_membuka_grup_tidak_dinotif_lagi(): void
    {
        Notification::fake();
        $grup = $this->grupKelas($this->kelas7a);

        $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => 'pesan satu']);
        // Siswa membuka grup (poll) sebelum digest sempat berjalan -- watermark
        // sudah maju lewat GrupChatController::tandaiTerbaca().
        $this->actingAs($this->siswa7a)->getJson(route('grup.poll', $grup).'?after=0');

        $this->artisan('grupchat:kirim-notif');

        Notification::assertNotSentTo($this->siswa7a, GrupChatDigestNotification::class);
    }

    public function test_digest_menaikkan_watermark_supaya_tidak_dikirim_dua_kali(): void
    {
        Notification::fake();
        $grup = $this->grupKelas($this->kelas7a);

        $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => 'pesan satu']);

        $this->artisan('grupchat:kirim-notif');
        Notification::assertSentTo($this->siswa7a, GrupChatDigestNotification::class);

        $member = GrupChatMember::where('grup_id', $grup->uuid)->where('user_id', $this->siswa7a->uuid)->first();
        $this->assertSame(1, (int) $member->last_notified_seq);

        // Jalankan lagi tanpa pesan baru -- tidak boleh mengirim digest kedua.
        Notification::fake();
        $this->artisan('grupchat:kirim-notif');
        Notification::assertNotSentTo($this->siswa7a, GrupChatDigestNotification::class);
    }

    public function test_anggota_yang_mematikan_notifikasi_tidak_menerima_digest(): void
    {
        Notification::fake();
        $grup = $this->grupKelas($this->kelas7a);

        GrupChatMember::where('grup_id', $grup->uuid)->where('user_id', $this->siswa7a->uuid)
            ->update(['muted_until' => now()->addDay()]);

        $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => 'diam-diam bu']);
        $this->artisan('grupchat:kirim-notif');

        Notification::assertNotSentTo($this->siswa7a, GrupChatDigestNotification::class);
    }

    public function test_grup_arsip_tidak_memicu_digest(): void
    {
        Notification::fake();
        $grup = $this->grupKelas($this->kelas7a);
        // Pesan dari wali7a: siswa7a (bukan pengirim) tetap punya unread di seq 1.
        $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => 'sebelum diarsipkan']);

        $grup->update(['status' => 'arsip']);
        $this->artisan('grupchat:kirim-notif');

        Notification::assertNotSentTo($this->siswa7a, GrupChatDigestNotification::class);
    }

    public function test_satu_user_dengan_unread_di_dua_grup_dapat_satu_digest(): void
    {
        Notification::fake();
        $grupKelas = $this->grupKelas($this->kelas7a);
        $grupPaguyuban = $this->grupPaguyuban($this->kelas7a);

        // wali7a anggota KEDUA grup kelas 7a sekaligus (walikelas masuk grup
        // kelas & paguyuban yang sama) -- pesan dari orang lain di masing-masing
        // grup membuatnya punya unread di dua tempat sekaligus. Grup Kelas kini
        // selalu mode pengumuman, jadi siswa harus MEMBALAS pesan wali7a dulu
        // (tidak bisa mengirim pesan bebas) -- lihat GrupChatService.
        $awal = $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $grupKelas), ['body' => 'ada tugas hari ini?'])
            ->json('message');
        $this->actingAs($this->siswa7a)
            ->postJson(route('grup.pesan', $grupKelas), ['body' => 'tugas kelas', 'reply_to_id' => $awal['uuid']]);
        $this->actingAs($this->ortu7a)->postJson(route('grup.pesan', $grupPaguyuban), ['body' => 'izin ortu']);

        $this->artisan('grupchat:kirim-notif');

        Notification::assertSentToTimes($this->wali7a, GrupChatDigestNotification::class, 1);
        Notification::assertSentTo($this->wali7a, GrupChatDigestNotification::class, function ($n) {
            return count($n->groups) === 2;
        });
    }
}
