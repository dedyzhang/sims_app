<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GameAnswer;
use App\Models\GameAttempt;
use App\Models\GameLiveSession;
use App\Models\GameQuiz;
use App\Models\GameQuizAssignment;
use App\Models\User;
use App\Notifications\ArenaLiveStartedNotification;
use App\Policies\GameQuizPolicy;
use App\Services\GameAnswerGrader;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;

class GameLiveController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new \Illuminate\Routing\Controllers\Middleware(function ($request, $next) {
                if ($request->user() && $request->user()->access === 'orangtua') {
                    abort(403, 'Akses ditolak.');
                }

                return $next($request);
            }),
        ];
    }

    public function show(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('view', $quiz);
        abort_unless($quiz->isPublished(), 403, 'Kuis belum diterbitkan.');

        $session = $quiz->activeLiveSession($classroom);
        $canHost = auth()->user()->can('manage', $quiz);
        $quiz->load(['questions' => fn ($q) => $q->orderBy('sort_order')]);

        return view('arena-belajar.live', compact('classroom', 'quiz', 'session', 'canHost'));
    }

    public function start(Request $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);
        abort_unless($quiz->isPublished() && $quiz->questions()->exists(), 422, 'Kuis harus terbit dan punya soal.');

        $session = DB::transaction(function () use ($quiz, $classroom, $request) {
            GameLiveSession::where('quiz_id', $quiz->uuid)
                ->where('classroom_id', $classroom->uuid)
                ->whereIn('status', ['lobby', 'question', 'reveal'])
                ->lockForUpdate()
                ->get()
                ->each(fn (GameLiveSession $s) => $s->update(['status' => 'ended', 'ended_at' => now()]));

            GameQuizAssignment::firstOrCreate(
                ['quiz_id' => $quiz->uuid, 'classroom_id' => $classroom->uuid],
                ['status' => 'open', 'opens_at' => $quiz->opens_at, 'due_at' => $quiz->due_at]
            );

            $quiz->update(['mode' => 'live']);

            return GameLiveSession::create([
                'quiz_id'        => $quiz->uuid,
                'classroom_id'   => $classroom->uuid,
                'hosted_by'      => $request->user()->uuid,
                'status'         => 'lobby',
                'started_at'     => now(),
                'question_index' => 0,
            ]);
        });

        Audit::log('arena_live_start', $quiz, ['session' => $session->uuid]);

        try {
            $memberIds = ClassroomMember::where('classroom_id', $classroom->uuid)
                ->where('role_in_class', 'siswa')
                ->pluck('user_id');
            User::whereIn('uuid', $memberIds)->get()
                ->each(fn (User $u) => $u->notify(new ArenaLiveStartedNotification($quiz, $classroom)));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('classroom.arena.live', [$classroom, $quiz])
            ->with('success', 'Sesi live dimulai. Siswa bisa bergabung.');
    }

    public function advance(Classroom $classroom, GameQuiz $quiz, GameAnswerGrader $grader)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $questions = $quiz->questions()->orderBy('sort_order')->get();
        abort_unless($questions->isNotEmpty(), 422);

        $session = DB::transaction(function () use ($quiz, $classroom, $questions, $grader) {
            $session = GameLiveSession::where('quiz_id', $quiz->uuid)
                ->where('classroom_id', $classroom->uuid)
                ->whereIn('status', ['lobby', 'question', 'reveal'])
                ->lockForUpdate()
                ->latest()
                ->first();
            abort_unless($session, 404, 'Tidak ada sesi live aktif.');

            if ($session->status === 'lobby') {
                $q = $questions->first();
                $session->update([
                    'status'              => 'question',
                    'current_question_id' => $q->uuid,
                    'question_index'      => 0,
                    'question_started_at' => now(),
                ]);

                return $session->fresh();
            }

            if ($session->status === 'reveal') {
                $next = $session->question_index + 1;
                if ($next >= $questions->count()) {
                    $session->update([
                        'status'              => 'ended',
                        'ended_at'            => now(),
                        'current_question_id' => null,
                    ]);
                    $this->finalizeLiveAttempts($quiz, $classroom, $grader);

                    return $session->fresh();
                }
                $q = $questions[$next];
                $session->update([
                    'status'              => 'question',
                    'current_question_id' => $q->uuid,
                    'question_index'      => $next,
                    'question_started_at' => now(),
                ]);

                return $session->fresh();
            }

            if ($session->status === 'question') {
                $session->update(['status' => 'reveal']);
            }

            return $session->fresh();
        });

        Audit::log('arena_live_advance', $quiz, [
            'session' => $session->uuid,
            'status'  => $session->status,
            'index'   => $session->question_index,
        ]);

        return response()->json(['ok' => true, 'session' => $this->sessionPayload($session, $quiz)]);
    }

    public function end(Classroom $classroom, GameQuiz $quiz, GameAnswerGrader $grader)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $session = GameLiveSession::where('quiz_id', $quiz->uuid)
            ->where('classroom_id', $classroom->uuid)
            ->whereIn('status', ['lobby', 'question', 'reveal'])
            ->latest()
            ->first();

        if ($session) {
            $session->update(['status' => 'ended', 'ended_at' => now()]);
            $this->finalizeLiveAttempts($quiz, $classroom, $grader);
            Audit::log('arena_live_end', $quiz, ['session' => $session->uuid]);
        }

        return redirect()->route('classroom.arena.results', [$classroom, $quiz])
            ->with('success', 'Sesi live diakhiri.');
    }

    public function state(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        abort_unless(app(GameQuizPolicy::class)->view(auth()->user(), $quiz)
            || app(GameQuizPolicy::class)->play(auth()->user(), $quiz, $classroom), 403);

        $session = GameLiveSession::where('quiz_id', $quiz->uuid)
            ->where('classroom_id', $classroom->uuid)
            ->latest()
            ->first();

        return response()->json([
            'ok'      => true,
            'session' => $session ? $this->sessionPayload($session, $quiz, auth()->user()) : null,
        ]);
    }

    public function leaderboard(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('view', $quiz);

        $canManage = auth()->user()->can('manage', $quiz);
        $hideScores = $quiz->hide_scores && !$canManage;

        $assignment = $quiz->assignmentFor($classroom);
        $rows = collect();
        $me = null;

        if ($assignment) {
            $rows = $assignment->attempts()
                ->with(['student', 'answers'])
                ->where('source', GameAttempt::SOURCE_LIVE)
                ->whereIn('status', ['in_progress', 'submitted', 'graded'])
                ->get()
                ->map(function (GameAttempt $a) use ($hideScores) {
                    $row = [
                        'student_id' => $a->student_id,
                        'name'       => $a->student?->displayName() ?? 'Siswa',
                    ];
                    if (!$hideScores) {
                        $row['score'] = (int) ($a->total_score ?: $a->answers->sum('points_awarded'));
                        $row['correct'] = (int) ($a->correct_count ?: $a->answers->where('is_correct', true)->count());
                    }

                    return $row;
                })
                ->when(!$hideScores, fn ($c) => $c->sortByDesc('score'))
                ->values()
                ->take(20)
                ->values();

            $me = $rows->firstWhere('student_id', auth()->user()->uuid);
        }

        return response()->json([
            'ok'            => true,
            'leaderboard'   => $rows,
            'me'            => $me,
            'scoring_mode'  => $quiz->scoring_mode,
            'scores_hidden' => $hideScores,
        ]);
    }

    public function answer(Request $request, Classroom $classroom, GameQuiz $quiz, GameAnswerGrader $grader)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('play', [$quiz, $classroom]);

        $data = $request->validate([
            'question_id'        => ['required', 'uuid'],
            'selected_option_id' => ['nullable', 'uuid'],
            'answer_text'        => ['nullable', 'string', 'max:10000'],
        ]);

        $result = DB::transaction(function () use ($request, $classroom, $quiz, $grader, $data) {
            $session = GameLiveSession::where('quiz_id', $quiz->uuid)
                ->where('classroom_id', $classroom->uuid)
                ->whereIn('status', ['lobby', 'question', 'reveal'])
                ->lockForUpdate()
                ->latest()
                ->first();
            abort_unless($session, 404, 'Tidak ada sesi live aktif.');
            abort_unless($session->status === 'question', 422, 'Belum ada soal aktif.');
            abort_unless($data['question_id'] === $session->current_question_id, 422, 'Soal tidak aktif.');

            $question = $quiz->questions()->with('options')->where('uuid', $data['question_id'])->firstOrFail();

            if (!empty($data['selected_option_id'])) {
                abort_unless(
                    $question->options->contains('uuid', $data['selected_option_id']),
                    422,
                    'Opsi tidak valid.'
                );
            }

            $assignment = GameQuizAssignment::firstOrCreate(
                ['quiz_id' => $quiz->uuid, 'classroom_id' => $classroom->uuid],
                ['status' => 'open', 'opens_at' => $quiz->opens_at, 'due_at' => $quiz->due_at]
            );

            $attempt = GameAttempt::firstOrCreate(
                [
                    'assignment_id' => $assignment->uuid,
                    'student_id'    => $request->user()->uuid,
                    'source'        => GameAttempt::SOURCE_LIVE,
                ],
                ['status' => 'in_progress', 'started_at' => now()]
            );
            abort_unless(!$attempt->isSubmitted(), 403, 'Attempt sudah dikunci.');

            $existing = GameAnswer::where('attempt_id', $attempt->uuid)
                ->where('question_id', $question->uuid)
                ->lockForUpdate()
                ->first();
            if ($existing && $existing->answered_at) {
                return [null, false, false, true]; // already locked
            }

            $elapsed = $session->question_started_at
                ? (int) abs(now()->diffInMilliseconds($session->question_started_at))
                : 0;

            $answer = GameAnswer::updateOrCreate(
                ['attempt_id' => $attempt->uuid, 'question_id' => $question->uuid],
                [
                    'selected_option_id' => $data['selected_option_id'] ?? null,
                    'answer_text'        => $data['answer_text'] ?? null,
                    'answered_at'        => now(),
                ]
            );

            $graded = $grader->gradeAndPersistAnswer($answer->fresh(), $question, $quiz, $elapsed);

            $attempt->load('answers');
            $sum = (int) $attempt->answers->sum('points_awarded');
            $correct = (int) $attempt->answers->where('is_correct', true)->count();
            $totalPts = max(1, (int) $quiz->questions()->sum('points'));
            $maxRaw = $quiz->scoring_mode === 'competitive' ? (int) ceil($totalPts * 1.2) : $totalPts;
            $scaled = (int) round(($sum / $maxRaw) * $quiz->max_score);
            $attempt->update([
                'total_score'   => max(0, min($quiz->max_score, $scaled)),
                'correct_count' => $correct,
            ]);

            return [$graded, $quiz->instant_feedback, $quiz->hide_scores && !auth()->user()->can('manage', $quiz), false];
        });

        [$graded, $instantFeedback, $hideScores, $alreadyLocked] = $result;

        if ($alreadyLocked) {
            return response()->json(['ok' => false, 'message' => 'Jawaban untuk soal ini sudah dikunci.'], 409);
        }

        $payload = ['ok' => true];
        if ($instantFeedback && !$hideScores) {
            $payload['is_correct'] = $graded['is_correct'];
            $payload['points'] = $graded['points'];
        } else {
            $payload['saved'] = true;
        }

        return response()->json($payload);
    }

    private function finalizeLiveAttempts(GameQuiz $quiz, Classroom $classroom, GameAnswerGrader $grader): void
    {
        $assignment = $quiz->assignmentFor($classroom);
        if (!$assignment) {
            return;
        }

        $assignment->attempts()
            ->where('source', GameAttempt::SOURCE_LIVE)
            ->where('status', 'in_progress')
            ->get()
            ->each(function (GameAttempt $attempt) use ($grader, $quiz) {
                $result = $grader->gradeAttempt($attempt->fresh('answers'), $quiz);
                $attempt->update([
                    'total_score'   => $result['total_score'],
                    'correct_count' => $result['correct_count'],
                    'status'        => 'submitted',
                    'submitted_at'  => now(),
                ]);
            });

        if ($quiz->mode === 'live') {
            $quiz->update(['mode' => 'async']);
        }
    }

    private function sessionPayload(GameLiveSession $session, GameQuiz $quiz, ?User $user = null): array
    {
        $questions = $quiz->questions()->with('options')->orderBy('sort_order')->get();
        $current = $session->current_question_id
            ? $questions->firstWhere('uuid', $session->current_question_id)
            : null;

        $questionPayload = null;
        if ($current && in_array($session->status, ['question', 'reveal'], true)) {
            $questionPayload = [
                'uuid'          => $current->uuid,
                'type'          => $current->type,
                'question_text' => $current->question_text,
                'points'        => $current->points,
                'meta'          => $this->publicMeta($current),
                'options'       => $current->options->map(fn ($o) => [
                    'uuid'        => $o->uuid,
                    'option_text' => $o->option_text,
                    'is_correct'  => $session->status === 'reveal' ? (bool) $o->is_correct : null,
                ])->values(),
                'explanation'   => $session->status === 'reveal' ? $current->explanation : null,
                'correct_meta'  => $session->status === 'reveal' ? ($current->meta ?? null) : null,
            ];
        }

        return [
            'uuid'                => $session->uuid,
            'status'              => $session->status,
            'status_label'        => $session->statusLabel(),
            'question_index'      => $session->question_index,
            'question_total'      => $questions->count(),
            'current_question_id' => $session->current_question_id,
            'question'            => $questionPayload,
            'question_started_at' => optional($session->question_started_at)?->toIso8601String(),
            'can_answer'          => $session->status === 'question' && $user && $user->access === 'siswa',
        ];
    }

    private function publicMeta($question): ?array
    {
        if ($question->type === 'match') {
            $pairs = $question->meta['pairs'] ?? [];
            $lefts = collect($pairs)->pluck('left')->values();
            $rights = collect($pairs)->pluck('right')->shuffle()->values();

            return [
                'lefts'  => $lefts,
                'rights' => $rights,
            ];
        }

        return null;
    }
}
