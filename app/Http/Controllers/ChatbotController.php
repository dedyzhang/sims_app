<?php

namespace App\Http\Controllers;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\Setting;
use App\Services\Chatbot\ChatbotService;
use App\Support\ChatAttachments;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ChatbotController extends Controller
{
    public function __construct(private ChatbotService $chatbot) {}

    /** Render halaman widget chat. */
    public function show(Request $request): View
    {
        $user = $request->user();
        $conversation = $this->chatbot->resolveActiveConversation($user);

        // User membuka widget -> pesan bot & admin dianggap terbaca.
        $this->chatbot->markIncomingRead($conversation, ['bot', 'admin']);

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (ChatbotMessage $m) => $this->chatbot->serializeMessage($m));

        return view('chatbot.index', [
            'conversation' => $conversation,
            'messages' => $messages,
            'pollInterval' => config('chatbot.poll_interval_seconds') * 1000,
            'readWatermark' => $this->chatbot->ownReadWatermark($conversation, 'user'),
            'avatarType' => Setting::get('chatbot_avatar', 'default'),
            'quickQuestions' => $this->chatbot->quickQuestionLabels(),
        ]);
    }

    /** Jumlah pesan belum dibaca (untuk badge floating ball). Murni baca, tidak menandai read. */
    public function unread(Request $request): JsonResponse
    {
        return response()->json([
            'unread' => $this->chatbot->unreadForUser($request->user()),
        ]);
    }

    /** Terima pesan user (JSON), balas JSON. Lapis A. */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $result = $this->chatbot->handle($request->user(), $data['message']);

        return response()->json($result);
    }

    /** Terima gambar (sudah dikompres di sisi klien), simpan, kembalikan pesan JSON. */
    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'], // maks 5 MB
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $file = $data['image'];
        $path = ChatAttachments::storeImage($file);

        $result = $this->chatbot->handleImage(
            $request->user(),
            $path,
            $data['caption'] ?? '',
        );

        return response()->json($result);
    }

    /**
     * Terima lampiran file/dokumen (PDF/Word/Excel/dll). Tidak dikompres — hanya gambar
     * yang dikompres (di sisi klien). File dibatasi 5 MB & disimpan dengan nama asli yang
     * dirapikan di dalam folder unik agar tidak bentrok.
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv', 'max:5120'], // maks 5 MB
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $file = $data['file'];
        $path = ChatAttachments::storeFile($file);

        $result = $this->chatbot->handleFile(
            $request->user(),
            $path,
            $data['caption'] ?? '',
        );

        return response()->json($result);
    }

    /** User minta dihubungkan ke admin (bot -> human). */
    public function requestHuman(Request $request, ChatbotConversation $conversation): JsonResponse
    {
        $this->ensureOwner($request, $conversation);
        $conversation = $this->chatbot->requestHuman($conversation);

        return response()->json([
            'mode' => $conversation->mode,
            'status' => $conversation->status,
        ]);
    }

    /** User kembali ke bot (human -> bot). */
    public function backToBot(Request $request, ChatbotConversation $conversation): JsonResponse
    {
        $this->ensureOwner($request, $conversation);
        $conversation = $this->chatbot->backToBot($conversation);

        return response()->json([
            'mode' => $conversation->mode,
            'status' => $conversation->status,
        ]);
    }

    /** Unduh lampiran chat — hanya pemilik percakapan atau admin. */
    public function attachment(Request $request, ChatbotMessage $message): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless(ChatAttachments::userCanAccess($request->user(), $message), 403);

        return ChatAttachments::downloadResponse($message);
    }

    /** Percakapan harus milik user yang login. */
    private function ensureOwner(Request $request, ChatbotConversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->getKey(), 403);
    }

    /**
     * Lapis B — kembalikan pesan baru SETELAH cursor. Murni baca, tidak memicu bot.
     * Ter-scope: percakapan harus milik user yang login.
     */
    public function poll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'string'],
            'after' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        // Scope ketat: cek kepemilikan user_id.
        $conversation = ChatbotConversation::where('id', $data['conversation_id'])
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        // User sedang melihat percakapan -> tandai pesan bot & admin sudah dibaca.
        $this->chatbot->markIncomingRead($conversation, ['bot', 'admin']);

        $messages = $this->newMessagesAfter($conversation, $data['after'] ?? null);

        return response()->json([
            'conversation_id' => $conversation->id,
            'mode' => $conversation->mode,
            'status' => $conversation->status,
            'messages' => $messages,
            'read_watermark' => $this->chatbot->ownReadWatermark($conversation, 'user'),
        ]);
    }

    /**
     * Ambil pesan dengan created_at >= cursor (frontend dedup per id).
     * Pakai >= agar pesan pada detik yang sama tidak pernah terlewat.
     */
    private function newMessagesAfter(ChatbotConversation $conversation, ?string $after): array
    {
        $query = $conversation->messages()->orderBy('created_at')->orderBy('id')->limit(100);

        if ($after) {
            try {
                $query->where('created_at', '>=', Carbon::parse($after));
            } catch (\Exception) {
                // cursor tidak valid -> abaikan, kembalikan batch wajar.
            }
        }

        return $query->get()
            ->map(fn (ChatbotMessage $m) => $this->chatbot->serializeMessage($m))
            ->all();
    }
}
