<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\GameAnswer;
use App\Models\GameAttempt;
use App\Models\GameQuiz;
use App\Models\GameQuizAssignment;
use App\Policies\GameQuizPolicy;
use App\Services\GameAnswerGrader;
use App\Support\ArenaAccessToken;
use App\Support\ArenaSoloShuffle;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GameAttemptController extends Controller implements HasMiddleware
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

    public function start(Request $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('play', [$quiz, $classroom]);
        abort_unless($quiz->allowsSolo(), 403, 'Kuis ini disetel "Live saja" — main solo tidak tersedia.');
        abort_unless(!$quiz->hasActiveLiveSession($classroom), 403, 'Sedang ada sesi live. Kerjakan lewat Live Arena.');

        if ($denied = $this->assertSoloTokenOrUnlock($request, $quiz)) {
            return $denied;
        }

        $assignment = $this->resolveOpenAssignment($quiz, $classroom);

        $attempt = GameAttempt::firstOrCreate(
            [
                'assignment_id' => $assignment->uuid,
                'student_id'    => auth()->user()->uuid,
                'source'        => GameAttempt::SOURCE_ASYNC,
            ],
            [
                'status'     => 'in_progress',
                'started_at' => now(),
            ]
        );

        if ($attempt->isSubmitted()) {
            return redirect()->route('classroom.arena.result', [$classroom, $quiz, $attempt]);
        }

        if (!$attempt->started_at) {
            $attempt->update(['started_at' => now()]);
        }

        return redirect()->route('classroom.arena.play', [$classroom, $quiz, $attempt]);
    }

    public function play(Classroom $classroom, GameQuiz $quiz, GameAttempt $attempt)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        abort_unless($attempt->student_id === auth()->user()->uuid, 403);
        abort_unless(($attempt->source ?? GameAttempt::SOURCE_ASYNC) === GameAttempt::SOURCE_ASYNC, 403);
        $this->authorize('play', [$quiz, $classroom]);
        abort_unless($quiz->allowsSolo(), 403, 'Kuis ini disetel "Live saja" — main solo tidak tersedia.');
        abort_unless(!$quiz->hasActiveLiveSession($classroom), 403, 'Sedang ada sesi live. Kerjakan lewat Live Arena.');

        if ($quiz->requiresSoloToken() && !$this->hasValidSoloUnlock($quiz)) {
            return redirect()
                ->route('classroom.arena.show', [$classroom, $quiz])
                ->with('error', 'Masukkan token solo dari guru mapel untuk melanjutkan.');
        }

        if ($attempt->isSubmitted()) {
            return redirect()->route('classroom.arena.result', [$classroom, $quiz, $attempt]);
        }

        $assignment = $attempt->assignment;
        abort_unless($assignment && $assignment->quiz_id === $quiz->uuid, 404);
        abort_unless($quiz->isOpenNow($assignment), 403, 'Kuis belum dibuka atau sudah ditutup.');

        $quiz->load(['questions' => fn ($q) => $q->orderBy('sort_order'), 'questions.options']);

        // Solo: acak soal + opsi per attempt (tetangga beda urutan; refresh tetap sama).
        $seedBase = 'solo|'.$attempt->uuid;
        $orderedQuestions = ArenaSoloShuffle::shuffle($quiz->questions, $seedBase.'|questions');

        $questionsPayload = $orderedQuestions->map(function ($q) use ($seedBase) {
            $options = ArenaSoloShuffle::shuffle($q->options, $seedBase.'|opts|'.$q->uuid);
            $payload = [
                'uuid'          => $q->uuid,
                'type'          => $q->type,
                'question_text' => $q->question_text,
                'points'        => $q->points,
                'options'       => $options->map(fn ($o) => [
                    'uuid'        => $o->uuid,
                    'option_text' => $o->option_text,
                ])->values(),
                'meta' => null,
            ];
            if ($q->type === 'match') {
                $pairs = $q->meta['pairs'] ?? [];
                $payload['meta'] = [
                    'lefts'  => collect($pairs)->pluck('left')->values(),
                    'rights' => ArenaSoloShuffle::shuffle(
                        collect($pairs)->pluck('right')->values(),
                        $seedBase.'|match|'.$q->uuid
                    )->values(),
                ];
            }

            return $payload;
        })->values();

        $savedAnswers = $attempt->answers()->get()->mapWithKeys(fn ($a) => [
            $a->question_id => [
                'selected_option_id' => $a->selected_option_id,
                'answer_text'        => $a->answer_text,
            ],
        ]);

        return view('arena-belajar.play', [
            'classroom'        => $classroom,
            'quiz'             => $quiz,
            'attempt'          => $attempt,
            'questionsPayload' => $questionsPayload,
            'savedAnswers'     => $savedAnswers,
            'soloShuffled'     => true,
        ]);
    }

    public function saveAnswer(Request $request, Classroom $classroom, GameQuiz $quiz, GameAttempt $attempt)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        abort_unless($attempt->student_id === auth()->user()->uuid, 403);
        abort_unless(($attempt->source ?? GameAttempt::SOURCE_ASYNC) === GameAttempt::SOURCE_ASYNC, 403);
        abort_unless(!$attempt->isSubmitted(), 403, 'Attempt sudah dikumpulkan.');
        $this->authorize('play', [$quiz, $classroom]);
        abort_unless($quiz->allowsSolo(), 403, 'Kuis ini disetel "Live saja" — main solo tidak tersedia.');
        abort_unless(!$quiz->hasActiveLiveSession($classroom), 403, 'Sedang ada sesi live.');
        abort_unless($this->hasValidSoloUnlock($quiz), 403, 'Token solo wajib. Buka ulang halaman main dan masukkan token.');

        $assignment = $attempt->assignment;
        abort_unless($assignment && $assignment->quiz_id === $quiz->uuid, 404);
        abort_unless($quiz->isOpenNow($assignment), 403, 'Kuis belum dibuka atau sudah ditutup.');

        $data = $request->validate([
            'question_id'        => ['required', 'uuid', 'exists:game_questions,uuid'],
            'selected_option_id' => ['nullable', 'uuid', 'exists:game_question_options,uuid'],
            'answer_text'        => ['nullable', 'string', 'max:10000'],
        ]);

        $question = $quiz->questions()->where('uuid', $data['question_id'])->firstOrFail();

        if (!empty($data['selected_option_id'])) {
            $belongs = $question->options()->where('uuid', $data['selected_option_id'])->exists();
            abort_unless($belongs, 422, 'Opsi tidak valid.');
        }

        $answer = GameAnswer::updateOrCreate(
            [
                'attempt_id'  => $attempt->uuid,
                'question_id' => $question->uuid,
            ],
            [
                'selected_option_id' => $data['selected_option_id'] ?? null,
                'answer_text'        => $data['answer_text'] ?? null,
                'answered_at'        => now(),
            ]
        );

        $payload = ['ok' => true, 'answer_id' => $answer->uuid];

        if ($quiz->instant_feedback) {
            $grader = app(GameAnswerGrader::class);
            $question->load('options');
            $isCorrect = $grader->isCorrect($question, $data['selected_option_id'] ?? null, $data['answer_text'] ?? null);
            $payload['is_correct'] = $isCorrect;
            $payload['explanation'] = $question->explanation;
        }

        return response()->json($payload);
    }

    public function submit(Request $request, Classroom $classroom, GameQuiz $quiz, GameAttempt $attempt, GameAnswerGrader $grader)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        abort_unless($attempt->student_id === auth()->user()->uuid, 403);
        abort_unless(($attempt->source ?? GameAttempt::SOURCE_ASYNC) === GameAttempt::SOURCE_ASYNC, 403);
        abort_unless(!$attempt->isSubmitted(), 403, 'Attempt sudah dikumpulkan.');
        $this->authorize('play', [$quiz, $classroom]);
        abort_unless($quiz->allowsSolo(), 403, 'Kuis ini disetel "Live saja" — main solo tidak tersedia.');
        abort_unless(!$quiz->hasActiveLiveSession($classroom), 403, 'Sedang ada sesi live.');
        abort_unless($this->hasValidSoloUnlock($quiz), 403, 'Token solo wajib. Buka ulang halaman main dan masukkan token.');

        $assignment = $attempt->assignment;
        abort_unless($assignment && $assignment->quiz_id === $quiz->uuid, 404);
        abort_unless($quiz->isOpenNow($assignment), 403, 'Batas waktu kuis sudah lewat.');

        $data = $request->validate([
            'answers'                      => ['nullable', 'array'],
            'answers.*.question_id'        => ['required_with:answers', 'uuid'],
            'answers.*.selected_option_id' => ['nullable', 'uuid'],
            'answers.*.answer_text'        => ['nullable', 'string', 'max:10000'],
            'duration_ms'                  => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $attempt, $quiz, $grader) {
            $locked = GameAttempt::where('uuid', $attempt->uuid)->lockForUpdate()->first();
            abort_unless($locked && !$locked->isSubmitted(), 403, 'Attempt sudah dikumpulkan.');

            foreach ($data['answers'] ?? [] as $row) {
                $question = $quiz->questions()->where('uuid', $row['question_id'])->first();
                if (!$question) {
                    continue;
                }
                $optId = $row['selected_option_id'] ?? null;
                if ($optId && !$question->options()->where('uuid', $optId)->exists()) {
                    $optId = null;
                }
                GameAnswer::updateOrCreate(
                    [
                        'attempt_id'  => $locked->uuid,
                        'question_id' => $question->uuid,
                    ],
                    [
                        'selected_option_id' => $optId,
                        'answer_text'        => $row['answer_text'] ?? null,
                        'answered_at'        => now(),
                    ]
                );
            }

            $locked->update([
                'duration_ms' => $data['duration_ms'] ?? $locked->duration_ms,
            ]);

            $result = $grader->gradeAttempt($locked->fresh('answers'), $quiz);

            $locked->update([
                'total_score'   => $result['total_score'],
                'correct_count' => $result['correct_count'],
                'status'        => 'submitted',
                'submitted_at'  => now(),
            ]);
        });

        Audit::log('arena_attempt_submit', $attempt, [
            'quiz_id' => $quiz->uuid,
            'score'   => $attempt->fresh()->total_score,
        ]);

        return redirect()
            ->route('classroom.arena.result', [$classroom, $quiz, $attempt])
            ->with('success', 'Jawaban dikumpulkan.');
    }

    public function result(Classroom $classroom, GameQuiz $quiz, GameAttempt $attempt)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        abort_unless($attempt->assignment?->quiz_id === $quiz->uuid, 404);
        abort_unless(app(GameQuizPolicy::class)->viewAttempt(auth()->user(), $attempt), 403);

        $attempt->load(['answers.selectedOption', 'answers.question.options']);
        $quiz->load(['questions.options']);

        $showScore = !$quiz->hide_scores || auth()->user()->can('manage', $quiz);
        $leaderboard = collect();
        if ($quiz->show_leaderboard && $attempt->assignment) {
            $leaderboard = $attempt->assignment->attempts()
                ->where('source', $attempt->source ?? GameAttempt::SOURCE_ASYNC)
                ->whereIn('status', ['submitted', 'graded'])
                ->with('student')
                ->orderByDesc('total_score')
                ->orderBy('duration_ms')
                ->limit(20)
                ->get();
        }

        return view('arena-belajar.result', compact(
            'classroom', 'quiz', 'attempt', 'showScore', 'leaderboard'
        ));
    }

    private function resolveOpenAssignment(GameQuiz $quiz, Classroom $classroom): GameQuizAssignment
    {
        $assignment = GameQuizAssignment::firstOrCreate(
            ['quiz_id' => $quiz->uuid, 'classroom_id' => $classroom->uuid],
            [
                'opens_at' => $quiz->opens_at,
                'due_at'   => $quiz->due_at,
                'status'   => 'open',
            ]
        );

        abort_unless($quiz->isOpenNow($assignment), 403, 'Kuis belum dibuka atau sudah ditutup.');

        return $assignment;
    }

    private function soloUnlockSessionKey(GameQuiz $quiz): string
    {
        return ArenaAccessToken::sessionKey($quiz, 'solo');
    }

    private function hasValidSoloUnlock(GameQuiz $quiz): bool
    {
        if (! $quiz->requiresSoloToken()) {
            return true;
        }

        return ArenaAccessToken::hasUnlock($quiz);
    }

    private function grantSoloUnlock(GameQuiz $quiz): void
    {
        ArenaAccessToken::grantUnlock($quiz);
    }

    /** Validasi token solo; return redirect error atau null jika OK. */
    private function assertSoloTokenOrUnlock(Request $request, GameQuiz $quiz)
    {
        if (! $quiz->requiresSoloToken()) {
            return null;
        }

        if ($this->hasValidSoloUnlock($quiz)) {
            return null;
        }

        if ($message = ArenaAccessToken::validateAndGrant($request, $quiz)) {
            return back()->with('error', $message);
        }

        if (! ArenaAccessToken::hasUnlock($quiz)) {
            return back()->with('error', 'Token salah atau belum diisi. Minta token ke guru mapel.');
        }

        return null;
    }
}
