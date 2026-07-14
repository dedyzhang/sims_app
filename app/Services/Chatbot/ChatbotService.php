<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\User;
use App\Notifications\ChatbotAdminReplyReceived;
use App\Notifications\ChatbotInboxMessageReceived;
use App\Support\ChatAttachments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Orkestrasi inti chatbot SIMS — mode "handoff + chat".
 *
 * Pada integrasi SIMS, bot TIDAK menjawab data (SPP/nilai/absensi/jadwal) secara
 * otomatis: semua pertanyaan substantif diarahkan ke admin manusia lewat tombol
 * "Hubungkan ke Admin". Fitur inti yang dibawa: percakapan + handoff bot↔admin,
 * upload gambar (bukti transaksi) dua arah, dan read-receipt.
 *
 * Identitas pengguna memakai kunci users.uuid lewat $user->getKey().
 */
class ChatbotService
{
    public function __construct(
        private IntentMatcher $matcher,
        private ActivityLogger $activity,
        private SchoolDataService $schoolData,
    ) {
    }

    /**
     * FAQ bawaan (pertanyaan + jawaban) bila admin belum mengatur sendiri.
     * Jawaban KOSONG = ditangani sistem lewat data nyata (mis. jadwal) — bukan teks baku.
     */
    public const DEFAULT_FAQ = [
        ['q' => 'Bagaimana cara menghubungi admin sekolah?', 'a' => 'Klik tombol "Hubungkan ke Admin" di bagian atas chat ini. Pesanmu akan diteruskan ke petugas sekolah dan dibalas langsung oleh admin. Kamu juga bisa mengirim foto/dokumen lewat tombol lampiran.'],
        ['q' => 'Bagaimana cara melakukan absensi QR?', 'a' => "Untuk absensi QR:\n1. Buka menu Absensi di aplikasi.\n2. Pilih \"Scan QR\".\n3. Izinkan akses kamera & lokasi bila diminta.\n4. Arahkan kamera ke kode QR yang ditampilkan guru/petugas di kelas.\nKehadiranmu otomatis tercatat setelah scan berhasil. ✅"],
        ['q' => 'Kapan jadwal pelajaran hari ini?', 'a' => ''],
        ['q' => 'Lihat biodata saya', 'a' => ''],
        ['q' => 'Rekap kehadiran saya', 'a' => ''],
        ['q' => 'Siapa wali kelas saya?', 'a' => ''],
        ['q' => 'Bagaimana cara melihat nilai ujian?', 'a' => 'Nilai bisa kamu lihat di menu Rapor/Nilai pada aplikasi. Demi privasi, nilai tidak ditampilkan lewat chat ini. Jika ada pertanyaan soal nilai, silakan hubungi wali kelasmu ya. 🙏'],
    ];

    /**
     * Orkestrasi utama: simpan pesan user, jalankan handler intent (jika mode bot),
     * simpan balasan bot, kembalikan ringkasan. Atomic via DB::transaction.
     */
    public function handle(User $user, string $message): array
    {
        return DB::transaction(function () use ($user, $message) {
            $conversation = $this->resolveActiveConversation($user);

            if ($conversation->status === 'closed') {
                $conversation->update([
                    'status' => 'active',
                    'mode' => 'bot',
                    'closed_at' => null,
                    'closed_by' => null,
                ]);
            }

            $userMessage = $this->storeMessage($conversation, 'user', $message, $user->getKey());

            $this->activity->log('user_message_sent', $user, [
                'conversation_id' => $conversation->id,
            ]);

            // Saat mode human, bot DIAM. Pesan hanya disimpan menunggu admin.
            if ($conversation->isHumanMode()) {
                $this->notifyChatAdmins($conversation, $userMessage);

                return [
                    'conversation_id' => $conversation->id,
                    'mode' => $conversation->mode,
                    'status' => $conversation->status,
                    'user_message' => $this->serializeMessage($userMessage),
                    'bot_message' => null,
                    'reply' => null,
                ];
            }

            // 1) Coba jawab dari FAQ (pertanyaan cepat ber-jawaban yang dikelola admin).
            $faq = $this->matchFaq($message);
            if ($faq !== null) {
                $reply = $faq;
                $intent = 'faq';
            } else {
                // 2) Selain itu, deteksi intent → jawab data nyata (jadwal) / arahkan admin.
                $intent = $this->matcher->match($message);
                $reply = $this->runIntent($intent, $user);
            }

            $botMessage = $this->storeMessage($conversation, 'bot', $reply, null, $intent);

            // Pesan user dianggap "dibaca" begitu bot menjawabnya (mode bot).
            $userMessage->forceFill(['read_at' => Carbon::now()])->save();

            return [
                'conversation_id' => $conversation->id,
                'mode' => $conversation->mode,
                'status' => $conversation->status,
                'user_message' => $this->serializeMessage($userMessage),
                'bot_message' => $this->serializeMessage($botMessage),
                'reply' => $reply,
                'matched_intent' => $intent,
            ];
        });
    }

    // ------------------------------------------------------------------
    // Handoff bot <-> manusia
    // ------------------------------------------------------------------

    /** User minta dihubungkan ke admin. Bot berhenti menjawab percakapan ini. */
    public function requestHuman(ChatbotConversation $conversation): ChatbotConversation
    {
        return DB::transaction(function () use ($conversation) {
            $conversation->update([
                'mode' => 'human',
                'status' => 'waiting',
                'closed_at' => null,
                'closed_by' => null,
            ]);

            $this->storeMessage(
                $conversation,
                'bot',
                'Kamu akan dihubungkan ke admin sekolah. Mohon tunggu sebentar ya.',
                null,
                'sistem_handoff'
            );

            $this->notifyChatAdmins($conversation, null, 'handoff');

            return $conversation->refresh();
        });
    }

    /** Admin mengambil percakapan dari antrian. */
    public function assignToAdmin(ChatbotConversation $conversation, User $admin): ChatbotConversation
    {
        return DB::transaction(function () use ($conversation, $admin) {
            $conversation->update([
                'status' => 'assigned',
                'assigned_admin_id' => $admin->getKey(),
            ]);

            return $conversation->refresh();
        });
    }

    /** Admin membalas sebagai manusia. Bisa berupa teks dan/atau gambar. */
    public function replyAsAdmin(ChatbotConversation $conversation, User $admin, string $body, ?string $attachmentPath = null): ChatbotMessage
    {
        return DB::transaction(function () use ($conversation, $admin, $body, $attachmentPath) {
            if ($conversation->status !== 'assigned') {
                $conversation->update([
                    'status' => 'assigned',
                    'assigned_admin_id' => $admin->getKey(),
                ]);
            }

            $message = $this->storeMessage($conversation, 'admin', $body, $admin->getKey(), null, $attachmentPath);

            $this->activity->log('admin_reply_sent', $admin, [
                'conversation_id' => $conversation->id,
            ]);

            $this->notifyChatUser($conversation, $message);

            return $message;
        });
    }

    /**
     * Admin menutup/menyelesaikan percakapan. Status → 'closed' & dicatat siapa/kapan.
     * Pesan TIDAK dihapus — percakapan tetap tersimpan sebagai histori/bukti. Pesan baru
     * dari user nanti otomatis membuka percakapan BARU (resolveActiveConversation mengabaikan
     * yang berstatus closed).
     */
    public function closeConversation(ChatbotConversation $conversation, User $admin): ChatbotConversation
    {
        return DB::transaction(function () use ($conversation, $admin) {
            $this->storeMessage(
                $conversation,
                'bot',
                'Percakapan ini telah ditutup oleh admin. Terima kasih sudah menghubungi sekolah 🙏 '
                . 'Kirim pesan baru kapan saja jika butuh bantuan lagi.',
                null,
                'sistem_close'
            );

            $conversation->update([
                'status' => 'closed',
                'mode' => 'bot',
                'closed_at' => Carbon::now(),
                'closed_by' => $admin->getKey(),
            ]);

            $this->activity->log('conversation_closed', $admin, [
                'conversation_id' => $conversation->id,
            ]);

            return $conversation->refresh();
        });
    }

    /** Kembalikan percakapan ke bot. Bisa dipicu user atau admin. */
    public function backToBot(ChatbotConversation $conversation): ChatbotConversation
    {
        return DB::transaction(function () use ($conversation) {
            $conversation->update([
                'mode' => 'bot',
                'status' => 'active',
                'assigned_admin_id' => null,
            ]);

            $this->storeMessage(
                $conversation,
                'bot',
                'Kamu kembali terhubung dengan asisten otomatis. Ada yang bisa saya bantu?',
                null,
                'sistem_handoff'
            );

            return $conversation->refresh();
        });
    }

    /** Admin menghapus percakapan secara permanen beserta semua pesan dan lampirannya. */
    public function deleteConversation(ChatbotConversation $conversation, User $admin): void
    {
        DB::transaction(function () use ($conversation, $admin) {
            // 1. Cari semua pesan yang memiliki lampiran
            $messagesWithAttachments = $conversation->messages()
                ->whereNotNull('attachment_path')
                ->get();

            // 2. Hapus file fisik lampiran
            foreach ($messagesWithAttachments as $message) {
                ChatAttachments::delete($message->attachment_path);
            }

            // 3. Hapus percakapan dari database (cascade delete akan menghapus pesan di DB)
            $conversation->delete();

            // 4. Log aktivitas penghapusan
            $this->activity->log('conversation_deleted', $admin, [
                'conversation_id' => $conversation->id,
            ]);
        });
    }

    // ------------------------------------------------------------------
    // Persistence helpers
    // ------------------------------------------------------------------

    /**
     * Jumlah pesan masuk (bot/admin) yang belum dibaca user pada percakapan aktif.
     * Ringan & TIDAK membuat percakapan baru — aman dipakai polling badge di tiap halaman.
     */
    public function unreadForUser(User $user): int
    {
        $conversation = ChatbotConversation::where('user_id', $user->getKey())
            ->latest('created_at')
            ->first();

        if (! $conversation) {
            return 0;
        }

        return $conversation->messages()
            ->whereIn('sender', ['bot', 'admin'])
            ->whereNull('read_at')
            ->count();
    }

    public function resolveActiveConversation(User $user): ChatbotConversation
    {
        $conversation = ChatbotConversation::where('user_id', $user->getKey())
            ->latest('created_at')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        return ChatbotConversation::create([
            'user_id' => $user->getKey(),
            'mode' => 'bot',
            'status' => 'active',
            'started_at' => Carbon::now(),
        ]);
    }

    private function notifyChatAdmins(ChatbotConversation $conversation, ?ChatbotMessage $message = null, string $event = 'message'): void
    {
        $admins = $this->chatInboxAdmins($conversation);
        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new ChatbotInboxMessageReceived($conversation->loadMissing('user'), $message, $event));
    }

    /** Admin penerima notifikasi inbox — hanya peran admin/superadmin. */
    private function chatInboxAdmins(ChatbotConversation $conversation)
    {
        if ($conversation->assigned_admin_id) {
            return User::query()
                ->whereKey($conversation->assigned_admin_id)
                ->whereIn('access', ['admin', 'superadmin'])
                ->get();
        }

        return User::query()
            ->whereIn('access', ['admin', 'superadmin'])
            ->where(function ($query) {
                $query->whereHas('chatbotAdminSetting', fn ($q) => $q->where('notif_enabled', true))
                    ->orWhereDoesntHave('chatbotAdminSetting');
            })
            ->get();
    }

    private function notifyChatUser(ChatbotConversation $conversation, ChatbotMessage $message): void
    {
        $user = $conversation->user()->first();
        if (! $user) {
            return;
        }

        $user->notify(new ChatbotAdminReplyReceived($conversation, $message->loadMissing('senderUser')));
    }

    private function storeMessage(
        ChatbotConversation $conversation,
        string $sender,
        string $body,
        ?string $senderUserId = null,
        ?string $matchedIntent = null,
        ?string $attachmentPath = null,
    ): ChatbotMessage {
        return ChatbotMessage::create([
            'conversation_id' => $conversation->id,
            'sender' => $sender,
            'sender_user_id' => $senderUserId,
            'body' => $body,
            'attachment_path' => $attachmentPath,
            'matched_intent' => $matchedIntent,
        ]);
    }

    /**
     * Simpan pesan gambar dari user (gambar WAJIB sudah dikompres di sisi klien sebelum
     * diunggah). Bot tidak bisa "membaca" gambar → di mode bot balas pesan baku; di mode
     * human bot diam menunggu admin.
     */
    public function handleImage(User $user, string $attachmentPath, string $caption = ''): array
    {
        $reply = 'Terima kasih, gambarnya sudah saya terima. 🙏 Saya belum bisa membaca isi gambar — '
            . 'kalau butuh ditindaklanjuti, silakan tekan tombol "Hubungkan ke Admin" di atas.';

        return $this->handleAttachment($user, $attachmentPath, $caption, $reply, 'user_image_sent');
    }

    /**
     * Simpan pesan lampiran file/dokumen (PDF/Word/Excel/dll) dari user. Tidak dikompres
     * (kompresi browser hanya untuk gambar) — dibatasi ukuran di controller.
     */
    public function handleFile(User $user, string $attachmentPath, string $caption = ''): array
    {
        $reply = 'Terima kasih, filenya sudah saya terima. 🙏 Saya belum bisa membuka isi file — '
            . 'kalau butuh ditindaklanjuti, silakan tekan tombol "Hubungkan ke Admin" di atas.';

        return $this->handleAttachment($user, $attachmentPath, $caption, $reply, 'user_file_sent');
    }

    /** Inti penyimpanan lampiran (gambar/file): simpan pesan user, balas bot bila mode bot. */
    private function handleAttachment(User $user, string $attachmentPath, string $caption, string $botReply, string $event): array
    {
        return DB::transaction(function () use ($user, $attachmentPath, $caption, $botReply, $event) {
            $conversation = $this->resolveActiveConversation($user);

            if ($conversation->status === 'closed') {
                $conversation->update([
                    'status' => 'active',
                    'mode' => 'bot',
                    'closed_at' => null,
                    'closed_by' => null,
                ]);
            }

            $userMessage = $this->storeMessage($conversation, 'user', $caption, $user->getKey(), null, $attachmentPath);

            $this->activity->log($event, $user, [
                'conversation_id' => $conversation->id,
            ]);

            if ($conversation->isHumanMode()) {
                $this->notifyChatAdmins($conversation, $userMessage);

                return [
                    'conversation_id' => $conversation->id,
                    'mode' => $conversation->mode,
                    'status' => $conversation->status,
                    'user_message' => $this->serializeMessage($userMessage),
                    'bot_message' => null,
                ];
            }

            $botMessage = $this->storeMessage($conversation, 'bot', $botReply);

            $userMessage->forceFill(['read_at' => Carbon::now()])->save();

            return [
                'conversation_id' => $conversation->id,
                'mode' => $conversation->mode,
                'status' => $conversation->status,
                'user_message' => $this->serializeMessage($userMessage),
                'bot_message' => $this->serializeMessage($botMessage),
            ];
        });
    }

    public function serializeMessage(ChatbotMessage $m): array
    {
        $ext = $m->attachment_path ? strtolower(pathinfo($m->attachment_path, PATHINFO_EXTENSION)) : null;
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true);

        return [
            'id' => $m->id,
            'sender' => $m->sender,
            'sender_user_id' => $m->sender_user_id,
            'body' => $m->body,
            'attachment_url' => ChatAttachments::attachmentUrl($m),
            'attachment_name' => $m->attachment_path ? basename($m->attachment_path) : null,
            'attachment_ext' => $ext,
            'attachment_is_image' => $m->attachment_path ? $isImage : null,
            'matched_intent' => $m->matched_intent,
            'created_at' => $m->created_at->toIso8601String(),
            'is_read' => $m->read_at !== null,
        ];
    }

    /**
     * Tandai pesan masuk (dari pihak lain) sebagai sudah dibaca oleh penonton saat ini.
     * Hanya meng-update baris yang belum dibaca → murah & idempoten untuk dipakai di polling.
     */
    public function markIncomingRead(ChatbotConversation $conversation, array $senders): void
    {
        $conversation->messages()
            ->whereIn('sender', $senders)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    /**
     * Batas waktu "sudah dibaca" untuk pesan milik pengirim sendiri (untuk centang biru).
     * Mengembalikan created_at ISO8601 dari pesan-sendiri terbaru yang sudah dibaca peer, atau null.
     */
    public function ownReadWatermark(ChatbotConversation $conversation, string $ownSender): ?string
    {
        $raw = $conversation->messages()
            ->where('sender', $ownSender)
            ->whereNotNull('read_at')
            ->max('created_at');

        return $raw ? Carbon::parse($raw)->toIso8601String() : null;
    }

    // ------------------------------------------------------------------
    // Intent dispatch (mode handoff + chat: arahkan ke admin manusia)
    // ------------------------------------------------------------------

    private function runIntent(?string $intent, User $user): string
    {
        return match ($intent) {
            'bantuan' => $this->handleBantuan(),
            'cek_jadwal' => $this->handleJadwal($user),
            'cek_biodata' => $this->handleBiodata($user),
            'cek_absensi' => $this->handleAbsensi($user),
            'cek_walikelas' => $this->handleWaliKelas($user),
            'cek_nilai' => $this->handleNilaiBlocked(),
            'cek_spp' => $this->handleNeedAdmin('cek_spp'),
            default => $this->handleFallback(),
        };
    }

    /** Jadwal pelajaran hari ini dari data nyata (scoped ke siswa/guru). Role lain → handoff. */
    private function handleJadwal(User $user): string
    {
        return $this->schoolData->jadwalHariIni($user) ?? $this->handleNeedAdmin('cek_jadwal');
    }

    /** Biodata pengguna sendiri (data yang memang bisa dilihat siswa/guru). Role lain → handoff. */
    private function handleBiodata(User $user): string
    {
        return $this->schoolData->biodata($user) ?? $this->handleNeedAdmin('cek_biodata');
    }

    /** Rekap kehadiran siswa sendiri (data yang bisa dilihat siswa). Role lain → handoff. */
    private function handleAbsensi(User $user): string
    {
        return $this->schoolData->rekapKehadiran($user) ?? $this->handleNeedAdmin('cek_absensi');
    }

    /** Info kelas & wali kelas siswa sendiri. Role lain → handoff. */
    private function handleWaliKelas(User $user): string
    {
        return $this->schoolData->infoWaliKelas($user) ?? $this->handleNeedAdmin('cek_walikelas');
    }

    /** Nilai TIDAK dipaparkan lewat chat (privasi siswa) — arahkan ke menu rapor / wali kelas. */
    private function handleNilaiBlocked(): string
    {
        return implode("\n", [
            'Maaf, informasi nilai/rapor tidak bisa ditampilkan lewat chat demi privasi. 🙏',
            'Nilai resmi bisa kamu lihat di menu Rapor/Nilai pada aplikasi, atau tanyakan langsung ke wali kelasmu.',
            'Butuh bantuan lain? Klik "Hubungkan ke Admin" di atas.',
        ]);
    }

    // ------------------------------------------------------------------
    // FAQ (pertanyaan cepat ber-jawaban, dikelola admin)
    // ------------------------------------------------------------------

    /** Daftar FAQ ternormalisasi [['q'=>, 'a'=>], ...] dari Setting, fallback ke default. */
    public function faqItems(): array
    {
        $raw = \App\Models\Setting::get('chatbot_quick_questions');
        $items = $raw ? json_decode($raw, true) : null;

        if (! is_array($items) || $items === []) {
            return self::DEFAULT_FAQ;
        }

        // Peta jawaban default (untuk backfill pertanyaan lama yang belum punya jawaban).
        $defaults = [];
        foreach (self::DEFAULT_FAQ as $d) {
            $defaults[$this->normalizeText($d['q'])] = $d['a'];
        }

        $out = [];
        foreach ($items as $it) {
            if (is_array($it)) {
                $q = trim((string) ($it['q'] ?? ''));
                $a = trim((string) ($it['a'] ?? ''));
            } else {
                $q = trim((string) $it); // back-compat: dulu hanya string pertanyaan
                $a = '';
            }
            if ($q === '') {
                continue;
            }
            // Backfill: pertanyaan tanpa jawaban yang cocok FAQ default → pakai jawaban default.
            if ($a === '' && isset($defaults[$this->normalizeText($q)])) {
                $a = $defaults[$this->normalizeText($q)];
            }
            $out[] = ['q' => $q, 'a' => $a];
        }

        return $out ?: self::DEFAULT_FAQ;
    }

    /** Hanya teks pertanyaan (untuk chip & welcome screen di widget). */
    public function quickQuestionLabels(): array
    {
        return array_map(fn ($it) => $it['q'], $this->faqItems());
    }

    /** Cari jawaban FAQ untuk pesan user. Jawaban kosong dilewati (ditangani data/intent). */
    private function matchFaq(string $message): ?string
    {
        $msg = $this->normalizeText($message);
        if ($msg === '') {
            return null;
        }

        foreach ($this->faqItems() as $it) {
            $q = $this->normalizeText($it['q']);
            $a = trim($it['a']);
            if ($q === '' || $a === '') {
                continue; // pertanyaan tanpa jawaban → biar ditangani data nyata/intent
            }
            // Cocok bila sama persis, atau salah satu memuat lainnya (klik chip = sama persis).
            if ($msg === $q || str_contains($msg, $q) || str_contains($q, $msg)) {
                return $a;
            }
        }

        return null;
    }

    private function normalizeText(string $s): string
    {
        return trim(mb_strtolower(preg_replace('/\s+/', ' ', $s)));
    }

    /**
     * Topik yang dikenali tapi butuh data resmi → arahkan ke admin manusia.
     * (Wiring ke data nyata SIMS bisa menyusul; untuk saat ini handoff.)
     */
    private function handleNeedAdmin(string $intent): string
    {
        $topik = match ($intent) {
            'cek_spp' => 'tagihan/SPP',
            'cek_nilai' => 'nilai/rapor',
            'cek_absensi' => 'absensi/kehadiran',
            'cek_jadwal' => 'jadwal pelajaran',
            'cek_biodata' => 'biodata/data diri',
            'cek_walikelas' => 'wali kelas/kelas',
            default => 'informasi itu',
        };

        return implode("\n", [
            "Untuk informasi {$topik}, saya hubungkan kamu ke admin sekolah ya. 🙏",
            'Klik tombol "Hubungkan ke Admin" di atas agar dibantu langsung oleh petugas.',
        ]);
    }

    private function handleBantuan(): string
    {
        return implode("\n", [
            'Halo! Saya asisten sekolah. Saya bisa bantu langsung:',
            '• 📚 Jadwal pelajaran hari ini',
            '• 🧑‍🎓 Biodata kamu',
            '• 🗓️ Rekap kehadiran kamu',
            '• 🏫 Info kelas & wali kelas',
            '',
            'Untuk 💰 SPP/tagihan atau hal lain yang perlu petugas, klik "Hubungkan ke Admin".',
            'Catatan: 📊 nilai/rapor tidak ditampilkan lewat chat demi privasi.',
            'Kamu juga bisa mengirim foto/dokumen lewat tombol lampiran.',
        ]);
    }

    private function handleFallback(): string
    {
        return implode("\n", [
            'Maaf, saya belum mengerti maksudmu. 🙏',
            'Untuk dibantu langsung oleh petugas sekolah, klik tombol "Hubungkan ke Admin" di atas.',
            'Ketik "bantuan" untuk melihat hal-hal yang bisa dibantu.',
        ]);
    }

    public function getAvatarUrl(?string $type = null): ?string
    {
        if (null === $type) {
            $type = \App\Models\Setting::get('chatbot_avatar', 'default');
        }

        return match ($type) {
            'robot' => asset('images/chatbot/avatar_robot.png'),
            'owl' => asset('images/chatbot/avatar_owl.png'),
            'cs' => asset('images/chatbot/avatar_cs.png'),
            'cat' => asset('images/chatbot/avatar_cat.png'),
            'fox' => asset('images/chatbot/avatar_fox.png'),
            'panda' => asset('images/chatbot/avatar_panda.png'),
            'bear' => asset('images/chatbot/avatar_bear.png'),
            default => null,
        };
    }
}
