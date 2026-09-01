<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\GamePracticeAnswer;
use App\Models\GamePracticeAttempt;
use App\Models\GamePracticeParticipant;
use App\Models\GamePracticeSession;
use App\Models\GameQuiz;
use App\Models\User;
use App\Support\ArenaSoloShuffle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * State machine "Latihan" (rehearsal Arena Belajar) — replikasi SENGAJA dari
 * GameLiveController (lobby->question->reveal->standings->ended), termasuk pola performa
 * yg baru diperbaiki di sana sesi ini: pre-check TANPA lock dulu (mightNeedAdvance) sebelum
 * buka DB::transaction()+lockForUpdate(), query soal aktif SAJA (bukan seluruh kuis), dan
 * shuffle opsi 'match' deterministic (ArenaSoloShuffle, di-seed per sesi+soal — BUKAN
 * shuffle() polos yg diacak ulang tiap poll, itu bug yg sudah diperbaiki di live jangan
 * diulang di sini). Dipakai BERSAMA oleh GamePracticeController (guru, login) dan
 * GamePracticeJoinController (tamu, tanpa login) — satu tempat, supaya poll dari kedua sisi
 * selalu memicu transisi yg identik pada baris sesi yg sama.
 */
class GamePracticeSessionService
{
    private const REVEAL_SECONDS = 4;
    private const STANDINGS_SECONDS = 6;
    private const ONLINE_SECONDS = 12;
    private const ACTIVE_STATUSES = ['lobby', 'question', 'reveal', 'standings'];
    public const MAX_PARTICIPANTS = 60;

    public function startSession(GameQuiz $quiz, Classroom $classroom, User $hostedBy): GamePracticeSession
    {
        return DB::transaction(function () use ($quiz, $classroom, $hostedBy) {
            GamePracticeSession::where('quiz_id', $quiz->uuid)
                ->where('classroom_id', $classroom->uuid)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->get()
                ->each(fn (GamePracticeSession $s) => $s->update(['status' => 'ended', 'ended_at' => now()]));

            return GamePracticeSession::create([
                'quiz_id'        => $quiz->uuid,
                'classroom_id'   => $classroom->uuid,
                'hosted_by'      => $hostedBy->uuid,
                'join_token'     => $this->generateJoinToken(),
                'status'         => 'lobby',
                'started_at'     => now(),
                'question_index' => 0,
            ]);
        });
    }

    private function generateJoinToken(): string
    {
        do {
            $token = Str::upper(Str::random(8));
        } while (GamePracticeSession::where('join_token', $token)->exists());

        return $token;
    }

    public function advance(GamePracticeSession $session, GameQuiz $quiz): GamePracticeSession
    {
        $questions = $quiz->questions()->orderBy('sort_order')->get();
        abort_if($questions->isEmpty(), 422);

        return DB::transaction(function () use ($session, $questions) {
            $locked = GamePracticeSession::where('uuid', $session->uuid)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->first();
            abort_unless($locked, 404, 'Tidak ada sesi latihan aktif.');

            return $this->transitionState($locked, $questions);
        });
    }

    public function end(GamePracticeSession $session): void
    {
        if (!$session->isActive()) {
            return;
        }
        $session->update(['status' => 'ended', 'ended_at' => now()]);
        $this->finalizePracticeAttempts($session);
    }

    /** Dipanggil dari poll guru MAUPUN poll tamu — join a real participant row's last_seen. */
    public function touchParticipant(GamePracticeParticipant $participant): void
    {
        $participant->forceFill(['last_seen_at' => now()])->save();
    }

    public function autoAdvanceIfNeeded(GamePracticeSession $session, GameQuiz $quiz): GamePracticeSession
    {
        if (!$session->isActive()) {
            return $session;
        }
        if (!$this->mightNeedAdvance($session)) {
            return $session;
        }

        $questions = $quiz->questions()->orderBy('sort_order')->get();
        if ($questions->isEmpty()) {
            return $session;
        }

        return DB::transaction(function () use ($session, $questions) {
            $locked = GamePracticeSession::where('uuid', $session->uuid)->lockForUpdate()->first();
            if (!$locked || !$locked->isActive()) {
                return $locked ?? $session;
            }
            if (!$this->mightNeedAdvance($locked)) {
                return $locked;
            }

            return $this->transitionState($locked, $questions);
        });
    }

    private function mightNeedAdvance(GamePracticeSession $session): bool
    {
        return match ($session->status) {
            'question' => ($session->question_deadline_at && now()->greaterThanOrEqualTo($session->question_deadline_at))
                || $this->allJoinedHaveAnswered($session),
            'reveal' => $session->phase_started_at
                && now()->greaterThanOrEqualTo($session->phase_started_at->copy()->addSeconds(self::REVEAL_SECONDS)),
            'standings' => $session->phase_started_at
                && now()->greaterThanOrEqualTo($session->phase_started_at->copy()->addSeconds(self::STANDINGS_SECONDS)),
            default => false,
        };
    }

    private function allJoinedHaveAnswered(GamePracticeSession $session): bool
    {
        $joinedCount = GamePracticeParticipant::where('session_id', $session->uuid)->count();
        if ($joinedCount === 0) {
            return false;
        }

        return $this->answeredCountFor($session, $session->current_question_id) >= $joinedCount;
    }

    private function answeredCountFor(GamePracticeSession $session, ?string $questionId): int
    {
        if (!$questionId) {
            return 0;
        }

        return GamePracticeAnswer::where('question_id', $questionId)
            ->whereHas('attempt', fn ($q) => $q->where('session_id', $session->uuid))
            ->count();
    }

    private function transitionState(GamePracticeSession $session, Collection $questions): GamePracticeSession
    {
        if ($session->status === 'lobby') {
            $q = $questions->first();
            $session->update([
                'status'               => 'question',
                'current_question_id' => $q->uuid,
                'question_index'      => 0,
                'question_started_at' => now(),
                'question_deadline_at' => $q->time_limit_seconds ? now()->addSeconds($q->time_limit_seconds) : null,
                'phase_started_at'     => now(),
            ]);

            return $session->fresh();
        }

        if ($session->status === 'question') {
            $session->update(['status' => 'reveal', 'phase_started_at' => now()]);

            return $session->fresh();
        }

        if ($session->status === 'reveal') {
            $session->update(['status' => 'standings', 'phase_started_at' => now()]);

            return $session->fresh();
        }

        if ($session->status === 'standings') {
            $next = $session->question_index + 1;
            if ($next >= $questions->count()) {
                $session->update([
                    'status'               => 'ended',
                    'ended_at'             => now(),
                    'current_question_id' => null,
                    'question_deadline_at' => null,
                    'phase_started_at'     => null,
                ]);
                $this->finalizePracticeAttempts($session);

                return $session->fresh();
            }
            $q = $questions[$next];
            $session->update([
                'status'               => 'question',
                'current_question_id' => $q->uuid,
                'question_index'      => $next,
                'question_started_at' => now(),
                'question_deadline_at' => $q->time_limit_seconds ? now()->addSeconds($q->time_limit_seconds) : null,
                'phase_started_at'     => now(),
            ]);

            return $session->fresh();
        }

        return $session;
    }

    private function finalizePracticeAttempts(GamePracticeSession $session): void
    {
        GamePracticeAttempt::where('session_id', $session->uuid)
            ->where('status', 'in_progress')
            ->update(['status' => 'submitted', 'submitted_at' => now()]);
    }

    /**
     * @return array{ok:true,status:string}|array{ok:false,status:string,message:string}
     */
    public function recordAnswer(
        GamePracticeParticipant $participant,
        GameQuiz $quiz,
        string $questionId,
        ?string $selectedOptionId,
        ?string $answerText,
        GameAnswerGrader $grader
    ): array {
        return DB::transaction(function () use ($participant, $quiz, $questionId, $selectedOptionId, $answerText, $grader) {
            $session = GamePracticeSession::where('uuid', $participant->session_id)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->first();
            if (!$session) {
                return ['ok' => false, 'status' => 'closed', 'message' => 'Sesi latihan tidak aktif.'];
            }
            if ($session->status !== 'question') {
                return ['ok' => false, 'status' => 'closed', 'message' => 'Belum ada soal aktif.'];
            }
            if ($questionId !== $session->current_question_id) {
                return ['ok' => false, 'status' => 'closed', 'message' => 'Soal tidak aktif.'];
            }

            $question = $quiz->questions()->with('options')->where('uuid', $questionId)->firstOrFail();
            if (!empty($selectedOptionId)) {
                abort_unless($question->options->contains('uuid', $selectedOptionId), 422, 'Opsi tidak valid.');
            }

            $attempt = GamePracticeAttempt::firstOrCreate(
                ['session_id' => $session->uuid, 'participant_id' => $participant->uuid],
                ['status' => 'in_progress', 'started_at' => now()]
            );

            $existing = GamePracticeAnswer::where('attempt_id', $attempt->uuid)
                ->where('question_id', $question->uuid)
                ->lockForUpdate()
                ->first();
            if ($existing && $existing->answered_at) {
                return ['ok' => false, 'status' => 'locked', 'message' => 'Jawaban untuk soal ini sudah dikunci.'];
            }

            $elapsed = $session->question_started_at
                ? (int) abs(now()->diffInMilliseconds($session->question_started_at))
                : 0;

            $result = $grader->scoreQuestion($question, $selectedOptionId, $answerText, $quiz, $elapsed);

            GamePracticeAnswer::updateOrCreate(
                ['attempt_id' => $attempt->uuid, 'question_id' => $question->uuid],
                [
                    'selected_option_id' => $selectedOptionId,
                    'answer_text'        => $answerText,
                    'is_correct'         => $result['is_correct'],
                    'points_awarded'     => $result['points'],
                    'answered_at'        => now(),
                ]
            );

            $attempt->load('answers');
            $attempt->update([
                'total_score'   => (int) $attempt->answers->sum('points_awarded'),
                'correct_count' => (int) $attempt->answers->where('is_correct', true)->count(),
            ]);

            return ['ok' => true, 'status' => 'saved', 'is_correct' => $result['is_correct'], 'points' => $result['points']];
        });
    }

    public function leaderboardRows(GamePracticeSession $session, int $limit = 20): Collection
    {
        return GamePracticeAttempt::where('session_id', $session->uuid)
            ->with('participant:uuid,guest_name')
            ->orderByDesc('total_score')
            ->orderBy('updated_at')
            ->orderBy('uuid')
            ->get()
            ->map(fn (GamePracticeAttempt $a) => [
                'participant_id' => $a->participant_id,
                'name'           => $a->participant?->guest_name ?? 'Tamu',
                'score'          => (int) $a->total_score,
                'correct'        => (int) $a->correct_count,
            ])
            ->take($limit)
            ->values();
    }

    public function sessionPayload(GamePracticeSession $session, GameQuiz $quiz, ?GamePracticeParticipant $viewer = null): array
    {
        $questionTotal = $quiz->questions()->count();
        $current = $session->current_question_id
            ? $quiz->questions()->with('options')->where('uuid', $session->current_question_id)->first()
            : null;

        $questionPayload = null;
        if ($current && in_array($session->status, ['question', 'reveal'], true)) {
            $questionPayload = [
                'uuid'          => $current->uuid,
                'type'          => $current->type,
                'question_text' => $current->question_text,
                'points'        => $current->points,
                'meta'          => $this->publicMeta($current, $session),
                'options'       => $current->options->map(fn ($o) => [
                    'uuid'        => $o->uuid,
                    'option_text' => $o->option_text,
                    'is_correct'  => $session->status === 'reveal' ? (bool) $o->is_correct : null,
                ])->values(),
                'explanation'   => $session->status === 'reveal' ? $current->explanation : null,
                'correct_meta'  => $session->status === 'reveal' ? ($current->meta ?? null) : null,
            ];
        }

        $phaseEndsAt = null;
        if ($session->status === 'question' && $session->question_deadline_at) {
            $phaseEndsAt = $session->question_deadline_at;
        } elseif ($session->status === 'reveal' && $session->phase_started_at) {
            $phaseEndsAt = $session->phase_started_at->copy()->addSeconds(self::REVEAL_SECONDS);
        } elseif ($session->status === 'standings' && $session->phase_started_at) {
            $phaseEndsAt = $session->phase_started_at->copy()->addSeconds(self::STANDINGS_SECONDS);
        }

        $joinedCount = null;
        $answeredCount = null;
        if ($session->status === 'question') {
            $joinedCount = GamePracticeParticipant::where('session_id', $session->uuid)->count();
            $answeredCount = $this->answeredCountFor($session, $session->current_question_id);
        }

        $onlineCutoff = now()->subSeconds(self::ONLINE_SECONDS);
        $participants = $session->isActive()
            ? GamePracticeParticipant::where('session_id', $session->uuid)
                ->orderBy('joined_at')
                ->get()
                ->map(function (GamePracticeParticipant $p) use ($onlineCutoff) {
                    $online = $p->last_seen_at && $p->last_seen_at->gte($onlineCutoff);

                    return [
                        'participant_id' => $p->uuid,
                        'name'    => $p->guest_name,
                        'role'    => $p->claimed_role,
                        'online'  => $online,
                        'joined_at' => optional($p->joined_at)?->toIso8601String(),
                    ];
                })
                ->values()
                ->all()
            : [];

        $onlineCount = collect($participants)->where('online', true)->count();

        return [
            'uuid'                 => $session->uuid,
            'join_token'           => $session->join_token,
            'status'               => $session->status,
            'status_label'         => $session->statusLabel(),
            'question_index'       => $session->question_index,
            'question_total'       => $questionTotal,
            'current_question_id' => $session->current_question_id,
            'question'             => $questionPayload,
            'question_started_at' => optional($session->question_started_at)?->toIso8601String(),
            'question_deadline_at' => optional($session->question_deadline_at)?->toIso8601String(),
            'phase_ends_at'        => optional($phaseEndsAt)?->toIso8601String(),
            'joined_count'         => $joinedCount,
            'answered_count'       => $answeredCount,
            'participants'         => $participants,
            'online_count'         => $onlineCount,
            'can_answer'           => $session->status === 'question' && $viewer !== null,
        ];
    }

    private function publicMeta($question, GamePracticeSession $session): ?array
    {
        if ($question->type === 'match') {
            $pairs = $question->meta['pairs'] ?? [];
            $lefts = collect($pairs)->pluck('left')->values();
            $rights = ArenaSoloShuffle::shuffle(
                collect($pairs)->pluck('right')->values(),
                'latihan|'.$session->uuid.'|match|'.$question->uuid
            );

            return ['lefts' => $lefts, 'rights' => $rights];
        }

        return null;
    }
}
