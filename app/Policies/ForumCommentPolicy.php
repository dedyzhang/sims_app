<?php

namespace App\Policies;

use App\Models\ForumComment;
use App\Models\User;

/** Deny-by-default. Berbasis canForum() + kepemilikan. */
class ForumCommentPolicy
{
    public function update(User $user, ForumComment $comment): bool
    {
        return $comment->user_id === $user->uuid || $user->canForum('forum.moderate');
    }

    public function delete(User $user, ForumComment $comment): bool
    {
        return $comment->user_id === $user->uuid || $user->canForum('forum.moderate');
    }
}
