<?php

namespace App\Http\Controllers;

use App\Models\ForumComment;
use App\Models\ForumReaction;
use App\Models\ForumTopic;
use Illuminate\Http\Request;

class ForumReactionController extends Controller
{
    /** Toggle reaksi "Suka" pada topik ATAU komentar (unique per user). */
    public function toggle(Request $request)
    {
        $data = $request->validate([
            'target' => ['required', 'in:topic,comment'],
            'id'     => ['required', 'string'],
        ]);

        if ($data['target'] === 'topic') {
            $topic = ForumTopic::findOrFail($data['id']);
            $this->authorize('view', $topic);

            $existing = ForumReaction::where('user_id', $request->user()->uuid)->where('topic_id', $topic->uuid)->first();
            $existing
                ? $existing->delete()
                : ForumReaction::create(['user_id' => $request->user()->uuid, 'topic_id' => $topic->uuid, 'type' => 'suka']);

            $topic->update(['reactions_count' => ForumReaction::where('topic_id', $topic->uuid)->count()]);
        } else {
            $comment = ForumComment::findOrFail($data['id']);
            $this->authorize('view', $comment->topic);

            $existing = ForumReaction::where('user_id', $request->user()->uuid)->where('comment_id', $comment->uuid)->first();
            $existing
                ? $existing->delete()
                : ForumReaction::create(['user_id' => $request->user()->uuid, 'comment_id' => $comment->uuid, 'type' => 'suka']);
        }

        return back();
    }
}
