<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotAdminSetting;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChatbotAdminController extends Controller
{
    public function __construct(private ChatbotService $chatbot) {}

    /** Halaman inbox admin. */
    public function inbox(Request $request): View
    {
        $admin = $request->user();

        return view('chatbot.admin.inbox', [
            'pollInterval' => config('chatbot.poll_interval_seconds') * 1000,
            'settings' => $this->settingsFor($admin),
            'admin' => $admin,
            'avatarType' => Setting::get('chatbot_avatar', 'default'),
            'quickQuestions' => $this->chatbot->faqItems(), // [['q'=>,'a'=>], ...] untuk modal
        ]);
    }

    /** Update preferensi notifikasi/suara milik admin yang login. */
    public function settings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notif_enabled' => ['required', 'boolean'],
            'sound_enabled' => ['required', 'boolean'],
            'message_notif_enabled' => ['required', 'boolean'],
        ]);

        $admin = $request->user();

        $settings = DB::transaction(function () use ($admin, $data) {
            $settings = $this->settingsFor($admin);
            // Hanya boleh mengubah setting milik sendiri.
            $settings->update([
                'notif_enabled' => $data['notif_enabled'],
                'sound_enabled' => $data['sound_enabled'],
                'message_notif_enabled' => $data['message_notif_enabled'],
            ]);

            return $settings;
        });

        return response()->json([
            'notif_enabled' => $settings->notif_enabled,
            'sound_enabled' => $settings->sound_enabled,
            'message_notif_enabled' => $settings->message_notif_enabled,
        ]);
    }

    /** Ambil atau buat (default) setting admin. Dibuat otomatis saat pertama buka inbox. */
    private function settingsFor(User $admin): ChatbotAdminSetting
    {
        return ChatbotAdminSetting::firstOrCreate(
            ['admin_user_id' => $admin->getKey()],
            [
                'notif_enabled' => true,
                'sound_enabled' => true,
                'message_notif_enabled' => true,
            ],
        );
    }

    /**
     * Data antrian untuk polling inbox (JSON).
     * Sertakan waiting_count agar badge notif numpang polling ini (tanpa interval baru).
     */
    public function queue(Request $request): JsonResponse
    {
        $conversations = ChatbotConversation::with('user')
            ->whereIn('status', ['waiting', 'assigned'])
            ->orderByRaw("CASE status WHEN 'waiting' THEN 0 ELSE 1 END")
            ->orderBy('updated_at')
            ->get()
            ->map(fn (ChatbotConversation $c) => $this->summarize($c));

        return response()->json([
            'conversations' => $conversations,
            'waiting_count' => ChatbotConversation::where('status', 'waiting')->count(),
            'unread_count' => ChatbotMessage::where('sender', 'user')
                ->whereNull('read_at')
                ->whereHas('conversation', fn ($q) => $q->whereIn('status', ['waiting', 'assigned']))
                ->count(),
        ]);
    }

    /** Pesan dalam satu percakapan untuk panel chat admin (poll pesan user baru). */
    public function messages(Request $request, ChatbotConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'after' => ['nullable', 'string'],
        ]);

        // Admin membuka/melihat percakapan -> pesan user dianggap terbaca.
        $this->chatbot->markIncomingRead($conversation, ['user']);

        $query = $conversation->messages()->orderBy('created_at')->orderBy('id')->limit(200);
        if (! empty($data['after'])) {
            try {
                $query->where('created_at', '>=', Carbon::parse($data['after']));
            } catch (\Exception) {
            }
        }

        return response()->json([
            'conversation_id' => $conversation->id,
            'mode' => $conversation->mode,
            'status' => $conversation->status,
            'messages' => $query->get()->map(fn (ChatbotMessage $m) => $this->chatbot->serializeMessage($m)),
            'read_watermark' => $this->chatbot->ownReadWatermark($conversation, 'admin'),
        ]);
    }

    /** Admin mengambil percakapan dari antrian. */
    public function assign(Request $request, ChatbotConversation $conversation): JsonResponse
    {
        $conversation = $this->chatbot->assignToAdmin($conversation, $request->user());

        return response()->json($this->summarize($conversation->load('user')));
    }

    /** Admin membalas sebagai manusia. */
    public function reply(Request $request, ChatbotConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = $this->chatbot->replyAsAdmin($conversation, $request->user(), $data['body']);

        return response()->json([
            'message' => $this->chatbot->serializeMessage($message),
            'status' => $conversation->refresh()->status,
        ]);
    }

    /** Admin membalas dengan gambar (sudah dikompres di sisi klien). */
    public function replyImage(Request $request, ChatbotConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ]);

        $file = $data['image'];
        $name = (string) Str::uuid().'.'.Uploads::safeExtension($file, ['jpeg', 'jpg', 'png', 'webp'], 'jpg');
        $dir = public_path('uploads/chat');
        File::ensureDirectoryExists($dir);
        $file->move($dir, $name);

        $message = $this->chatbot->replyAsAdmin(
            $conversation,
            $request->user(),
            $data['caption'] ?? '',
            'uploads/chat/'.$name,
        );

        return response()->json([
            'message' => $this->chatbot->serializeMessage($message),
            'status' => $conversation->refresh()->status,
        ]);
    }

    /** Admin membalas dengan lampiran file/dokumen (tanpa kompresi, dibatasi ukuran). */
    public function replyFile(Request $request, ChatbotConversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ]);

        $file = $data['file'];
        $ext = Uploads::safeExtension($file, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'], 'bin');
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $folder = (string) Str::uuid();

        $dir = public_path('uploads/chat/'.$folder);
        File::ensureDirectoryExists($dir);
        $file->move($dir, $base.'.'.$ext);

        $message = $this->chatbot->replyAsAdmin(
            $conversation,
            $request->user(),
            $data['caption'] ?? '',
            'uploads/chat/'.$folder.'/'.$base.'.'.$ext,
        );

        return response()->json([
            'message' => $this->chatbot->serializeMessage($message),
            'status' => $conversation->refresh()->status,
        ]);
    }

    /** Admin mengembalikan percakapan ke bot. */
    public function backToBot(Request $request, ChatbotConversation $conversation): JsonResponse
    {
        $conversation = $this->chatbot->backToBot($conversation);

        return response()->json([
            'mode' => $conversation->mode,
            'status' => $conversation->status,
        ]);
    }

    /** Admin menutup/menyelesaikan percakapan (pesan tetap tersimpan sebagai histori). */
    public function close(Request $request, ChatbotConversation $conversation): JsonResponse
    {
        $conversation = $this->chatbot->closeConversation($conversation, $request->user());

        return response()->json([
            'status' => $conversation->status,
            'closed_at' => $conversation->closed_at?->toIso8601String(),
        ]);
    }

    /** Admin menghapus percakapan secara permanen beserta semua pesan dan lampirannya. */
    public function destroy(Request $request, ChatbotConversation $conversation): JsonResponse
    {
        $this->chatbot->deleteConversation($conversation, $request->user());

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Daftar percakapan tertutup (arsip/histori bukti chat). Terpisah dari queue aktif
     * agar tidak ikut polling 5 detik. Mendukung pencarian nama/pesan via ?q=.
     */
    public function history(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $conversations = ChatbotConversation::with('user')
            ->where('status', 'closed')
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('user', function ($u) use ($q) {
                    $u->where('username', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('closed_at')
            ->limit(200)
            ->get()
            ->map(fn (ChatbotConversation $c) => $this->summarize($c));

        return response()->json([
            'conversations' => $conversations,
            'closed_count' => ChatbotConversation::where('status', 'closed')->count(),
        ]);
    }

    private function summarize(ChatbotConversation $c): array
    {
        $last = $c->messages()->orderByDesc('created_at')->first();
        $unread = $c->messages()->where('sender', 'user')->whereNull('read_at')->count();

        return [
            'id' => $c->id,
            'user_name' => $c->user?->displayName() ?? 'Pengguna',
            // SIMS tidak punya kolom email — pakai username sebagai identitas pencarian.
            'user_email' => $c->user?->username,
            'user_role' => $c->user?->access,
            'user_class' => null,
            'mode' => $c->mode,
            'status' => $c->status,
            'assigned_admin_id' => $c->assigned_admin_id,
            'last_message' => $last?->body,
            'last_sender' => $last?->sender,
            'last_at' => $last?->created_at?->toIso8601String(),
            'unread_count' => $unread,
            'created_at' => ($c->started_at ?? $c->created_at)?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
            'closed_at' => $c->closed_at?->toIso8601String(),
        ];
    }

    /** Update maskot / avatar CS global. */
    public function updateAvatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'avatar_type' => ['required', 'string', 'in:default,robot,owl,cs,cat,fox,panda,bear'],
        ]);

        Setting::set('chatbot_avatar', $data['avatar_type']);

        return response()->json([
            'success' => true,
            'avatar_type' => $data['avatar_type'],
            'avatar_url' => $this->chatbot->getAvatarUrl($data['avatar_type']),
        ]);
    }

    /** Update pertanyaan cepat (pasangan pertanyaan + jawaban) global. */
    public function updateQuickQuestions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'questions' => ['required', 'array'],
            'questions.*.q' => ['required', 'string', 'max:255'],
            'questions.*.a' => ['nullable', 'string', 'max:2000'],
        ]);

        // Buang baris tanpa pertanyaan; simpan sebagai [{q, a}].
        $questions = [];
        foreach ($data['questions'] as $item) {
            $q = trim($item['q'] ?? '');
            if ($q === '') {
                continue;
            }
            $questions[] = ['q' => $q, 'a' => trim($item['a'] ?? '')];
        }

        Setting::set('chatbot_quick_questions', json_encode($questions, JSON_UNESCAPED_UNICODE));

        return response()->json([
            'success' => true,
            'questions' => $questions,
        ]);
    }
}
