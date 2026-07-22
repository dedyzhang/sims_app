<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GameAttempt;
use App\Models\GameQuiz;
use App\Models\User;

/**
 * Arena Belajar: manage mengikuti ClassroomPolicy::manage;
 * play hanya siswa anggota classroom assignment.
 */
class GameQuizPolicy
{
    public function view(User $user, GameQuiz $quiz): bool
    {
        $classroom = $quiz->classroom;
        if (!$classroom) {
            return false;
        }
        if (!app(ClassroomPolicy::class)->view($user, $classroom)) {
            return false;
        }
        // Draf hanya untuk yang bisa manage; published + closed boleh dilihat anggota
        if (! $quiz->isPublished() && ! $quiz->isClosed() && ! $this->manage($user, $quiz)) {
            return false;
        }

        return true;
    }

    public function manage(User $user, GameQuiz $quiz): bool
    {
        $classroom = $quiz->classroom;
        if (!$classroom) {
            return false;
        }

        return app(ClassroomPolicy::class)->manage($user, $classroom);
    }

    public function create(User $user, Classroom $classroom): bool
    {
        return app(ClassroomPolicy::class)->manage($user, $classroom);
    }

    /** Siswa anggota (role_in_class=siswa) boleh mulai/mengerjakan kuis published. */
    public function play(User $user, GameQuiz $quiz, ?Classroom $classroom = null): bool
    {
        if ($user->access !== 'siswa') {
            return false;
        }
        $classroom = $classroom ?? $quiz->classroom;
        if (!$classroom || !$quiz->isPublished()) {
            return false;
        }

        return ClassroomMember::where('classroom_id', $classroom->uuid)
            ->where('user_id', $user->uuid)
            ->where('role_in_class', 'siswa')
            ->exists();
    }

    public function viewAttempt(User $user, GameAttempt $attempt): bool
    {
        if ($attempt->student_id === $user->uuid) {
            return true;
        }
        $quiz = $attempt->assignment?->quiz;

        return $quiz ? $this->manage($user, $quiz) : false;
    }

    public function monitor(User $user, GameQuiz $quiz): bool
    {
        return $this->manage($user, $quiz)
            || ($user->isAdmin() || in_array($user->access, ['kepala', 'kurikulum'], true));
    }
}
