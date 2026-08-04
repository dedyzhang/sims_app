<?php

namespace App\Http\Controllers;

use App\Models\GrupChat;
use App\Models\PrivateChatConversation;
use App\Models\User;
use App\Services\PrivateChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivateChatController extends Controller
{
    public function __construct(private PrivateChatService $chat) {}

    public function start(Request $request, GrupChat $grup, string $target)
    {
        $user = User::whereKey($target)->firstOrFail();
        $conversation = $this->chat->open($request->user(), $user, $grup);

        return redirect()->route('private-chat.show', $conversation);
    }

    public function show(Request $request, PrivateChatConversation $conversation)
    {
        $this->authorize('view', $conversation);

        $user = $request->user();
        $messages = $conversation->messages()
            ->orderByDesc('seq')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();
        $other = $conversation->otherParticipant($user->getKey());

        $this->chat->markRead($conversation, $user);

        return view('private-chat.show', [
            'conversation' => $conversation,
            'other' => $other,
            'messages' => $messages->map(fn ($message) => $this->chat->serialize($message)),
            'lastSeq' => (int) $conversation->last_seq,
        ]);
    }

    public function poll(Request $request, PrivateChatConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $after = max(0, (int) $request->query('after', 0));
        $messages = $conversation->messages()
            ->where('seq', '>', $after)
            ->orderBy('seq')
            ->limit(200)
            ->get();

        $this->chat->markRead($conversation, $request->user());

        return response()->json([
            'last_seq' => (int) $conversation->last_seq,
            'messages' => $messages->map(fn ($message) => $this->chat->serialize($message))->values()->all(),
        ]);
    }

    public function send(Request $request, PrivateChatConversation $conversation): JsonResponse
    {
        $this->authorize('send', $conversation);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.PrivateChatService::MAX_BODY],
        ], [], ['body' => 'pesan']);

        $message = $this->chat->send($conversation, $request->user(), $data['body']);

        return response()->json([
            'message' => $this->chat->serialize($message),
            'last_seq' => (int) $message->seq,
        ], 201);
    }
}
