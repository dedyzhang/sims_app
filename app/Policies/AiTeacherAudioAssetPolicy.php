<?php

namespace App\Policies;

use App\Models\AiTeacherAudioAsset;
use App\Models\User;

class AiTeacherAudioAssetPolicy
{
    public function view(User $user, AiTeacherAudioAsset $audio): bool
    {
        return $audio->user_uuid === $user->uuid;
    }

    public function manage(User $user, AiTeacherAudioAsset $audio): bool
    {
        return $audio->user_uuid === $user->uuid;
    }

    public function delete(User $user, AiTeacherAudioAsset $audio): bool
    {
        return $audio->user_uuid === $user->uuid;
    }
}