<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreForumCommentRequest;
use App\Models\ForumComment;
use App\Models\ForumTopic;
use App\Models\User;
use App\Notifications\ForumReplyNotification;
use App\Support\Forum;
use Illuminate\Http\Request;

class ForumCommentController extends Controller
{
    public function store(StoreForumCommentRequest $request, ForumTopic $topic)
    {
        $this->authorize('reply', $topic);

        // Nested maksimal 1 level: jika membalas sebuah balasan, datarkan ke induk teratas.
        $parentId = null;
        if ($request->parent_id) {
            $parent = ForumComment::where('topic_id', $topic->uuid)->find($request->parent_id);
            if ($parent) {
                $parentId = $parent->parent_id ?: $parent->uuid;
            }
        }

        $comment = ForumComment::create([
            'topic_id'  => $topic->uuid,
            'user_id'   => $request->user()->uuid,
            'parent_id' => $parentId,
            'body'      => Forum::sanitize($request->body),
        ]);

        $topic->update([
            'replies_count'    => ForumComment::where('topic_id', $topic->uuid)->count(),
            'last_activity_at' => now(),
        ]);

        $this->notifyParticipants($topic, $comment, $parentId);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'comment' => $comment,
            ]);
        }

        return redirect()->route('forum.show', $topic)->withFragment('c-' . $comment->uuid)
            ->with('success', 'Komentar terkirim.');
    }

    public function update(Request $request, ForumComment $comment)
    {
        $this->authorize('update', $comment);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);

        $comment->update(['body' => Forum::sanitize($data['body']), 'edited_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Komentar diperbarui.');
    }

    public function destroy(ForumComment $comment)
    {
        $this->authorize('delete', $comment);
        $topic = $comment->topic;
        $comment->delete();

        $topic?->update(['replies_count' => ForumComment::where('topic_id', $topic->uuid)->count()]);
        Forum::audit('delete_comment', $comment);

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Komentar dihapus.');
    }

    public function best(ForumComment $comment)
    {
        $topic = $comment->topic;
        $this->authorize('markBestAnswer', $topic);

        // Hanya 1 jawaban terbaik per topik.
        ForumComment::where('topic_id', $topic->uuid)->where('is_best_answer', true)
            ->update(['is_best_answer' => false]);
        $comment->update(['is_best_answer' => true]);

        Forum::audit('best_answer', $comment, ['topic' => $topic->title]);

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Ditandai sebagai jawaban terbaik.');
    }

    public function commentsHtml(ForumTopic $topic)
    {
        $this->authorize('view', $topic);

        $likedComments = [];
        if (auth()->check()) {
            $likedComments = \App\Models\ForumReaction::where('user_id', auth()->id())
                ->where('target_type', 'comment')
                ->pluck('target_id')
                ->toArray();
        }

        $html = '';
        $comments = $topic->comments()
            ->whereNull('parent_id')
            ->with(['user', 'replies.user', 'reactions'])
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($comments as $comment) {
            $html .= view('forum.partials.comment', [
                'comment' => $comment,
                'topic' => $topic,
                'likedComments' => $likedComments
            ])->render();
        }

        return response($html);
    }

    private function notifyParticipants(ForumTopic $topic, ForumComment $comment, ?string $parentId): void
    {
        $recipients = collect([$topic->created_by]);
        if ($parentId && ($p = ForumComment::find($parentId))) {
            $recipients->push($p->user_id);
        }
        $recipients = $recipients->filter()->unique()->reject(fn ($id) => $id === $comment->user_id);

        if ($recipients->isEmpty()) {
            return;
        }
        User::whereIn('uuid', $recipients->all())->get()
            ->each(fn ($u) => $u->notify(new ForumReplyNotification($comment)));
    }
}
