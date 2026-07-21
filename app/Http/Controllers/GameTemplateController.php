<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GameAnswer;
use App\Models\GameAttempt;
use App\Models\GameQuiz;
use App\Models\GameQuizAssignment;
use App\Models\GameTeam;
use App\Models\GameTeamMember;
use App\Services\GameAnswerGrader;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GameTemplateController extends Controller implements HasMiddleware
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

    public function setTemplate(Request $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $data = $request->validate([
            'template' => ['required', Rule::in(['quiz', 'match', 'flashcard', 'crossword', 'unjumble', 'ular_tangga'])],
        ]);

        $quiz->update(['template' => $data['template']]);
        Audit::log('arena_template_set', $quiz, $data);

        $labels = [
            'quiz' => 'Quiz',
            'match' => 'Pasangkan',
            'flashcard' => 'Flashcard',
            'crossword' => 'Teka-teki',
            'unjumble' => 'Susun kata',
            'ular_tangga' => 'Ular tangga',
        ];

        return back()->with('success', 'Template diganti ke '.($labels[$data['template']] ?? $data['template']).'.');
    }

    public function playTemplate(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('view', $quiz);

        $template = $quiz->template ?: 'quiz';
        // Template berisi kunci jawaban — hanya guru/manager
        if (in_array($template, ['flashcard', 'crossword', 'unjumble', 'ular_tangga'], true)) {
            $this->authorize('manage', $quiz);
        }

        $quiz->load(['questions.options']);

        if ($template === 'crossword' && ! $this->canCrossword($quiz)) {
            $template = 'quiz';
        }

        return view('arena-belajar.templates.'.$template, compact('classroom', 'quiz', 'template'));
    }

    public function teams(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $teams = GameTeam::where('quiz_id', $quiz->uuid)
            ->where('classroom_id', $classroom->uuid)
            ->with('members.user')
            ->orderBy('sort_order')
            ->get();

        $members = ClassroomMember::with('user')
            ->where('classroom_id', $classroom->uuid)
            ->where('role_in_class', 'siswa')
            ->get();

        $assigned = $teams->flatMap(fn ($t) => $t->members->pluck('user_id'))->all();

        return view('arena-belajar.teams', compact('classroom', 'quiz', 'teams', 'members', 'assigned'));
    }

    public function saveTeams(Request $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $data = $request->validate([
            'teams'                => ['required', 'array', 'min:1'],
            'teams.*.name'         => ['required', 'string', 'max:80'],
            'teams.*.member_ids'   => ['nullable', 'array'],
            'teams.*.member_ids.*' => ['uuid'],
        ]);

        DB::transaction(function () use ($data, $quiz, $classroom) {
            GameTeam::where('quiz_id', $quiz->uuid)->where('classroom_id', $classroom->uuid)->each(function (GameTeam $t) {
                $t->members()->delete();
                $t->delete();
            });

            $seenMembers = [];
            foreach ($data['teams'] as $i => $row) {
                $team = GameTeam::create([
                    'quiz_id'      => $quiz->uuid,
                    'classroom_id' => $classroom->uuid,
                    'name'         => $row['name'],
                    'sort_order'   => $i,
                ]);
                foreach ($row['member_ids'] ?? [] as $uid) {
                    if (isset($seenMembers[$uid])) {
                        continue;
                    }
                    $isSiswa = ClassroomMember::where('classroom_id', $classroom->uuid)
                        ->where('user_id', $uid)
                        ->where('role_in_class', 'siswa')
                        ->exists();
                    if (! $isSiswa) {
                        continue;
                    }
                    GameTeamMember::firstOrCreate([
                        'team_id' => $team->uuid,
                        'user_id' => $uid,
                    ]);
                    $seenMembers[$uid] = true;
                }
            }
        });

        Audit::log('arena_teams_saved', $quiz, ['count' => count($data['teams'])]);

        return back()->with('success', 'Tim disimpan.');
    }

    public function teamLeaderboard(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('view', $quiz);

        $assignment = $quiz->assignmentFor($classroom);
        $teams = GameTeam::where('quiz_id', $quiz->uuid)
            ->where('classroom_id', $classroom->uuid)
            ->with('members')
            ->orderBy('sort_order')
            ->get();

        $attempts = $assignment
            ? $assignment->attempts()->with('answers')->get()->keyBy('student_id')
            : collect();

        $board = $teams->map(function (GameTeam $team) use ($attempts) {
            $scores = $team->members->map(function ($m) use ($attempts) {
                $a = $attempts->get($m->user_id);

                return $a ? (int) ($a->total_score ?: $a->answers->sum('points_awarded')) : 0;
            });

            return [
                'team'    => $team->name,
                'score'   => (int) $scores->sum(),
                'avg'     => $scores->count() ? (int) round($scores->avg()) : 0,
                'members' => $team->members->count(),
            ];
        })->sortByDesc('score')->values();

        return response()->json(['ok' => true, 'teams' => $board]);
    }

    public function pdf(Request $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('view', $quiz);
        $quiz->load(['questions.options']);

        $withKey = $request->boolean('kunci');
        if ($withKey) {
            $this->authorize('manage', $quiz);
        }

        $pdf = Pdf::loadView('arena-belajar.pdf.worksheet', [
            'quiz'      => $quiz,
            'classroom' => $classroom,
            'withKey'   => $withKey,
        ])->setPaper('a4');

        $name = 'arena-'.str($quiz->title)->slug().($withKey ? '-kunci' : '').'.pdf';

        return $pdf->download($name);
    }

    /** Sync offline queue — idempotent per question. */
    public function syncOffline(Request $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('play', [$quiz, $classroom]);
        abort_unless(! $quiz->hasActiveLiveSession($classroom), 403, 'Sedang ada sesi live.');

        if ($quiz->is_locked) {
            return response()->json(['ok' => false, 'message' => 'Kuis terkunci — offline sync dinonaktifkan.'], 422);
        }

        $data = $request->validate([
            'answers'                      => ['required', 'array'],
            'answers.*.question_id'        => ['required', 'uuid'],
            'answers.*.selected_option_id' => ['nullable', 'uuid'],
            'answers.*.answer_text'        => ['nullable', 'string', 'max:10000'],
            'duration_ms'                  => ['nullable', 'integer', 'min:0'],
            'submit'                       => ['sometimes', 'boolean'],
        ]);

        $assignment = GameQuizAssignment::firstOrCreate(
            ['quiz_id' => $quiz->uuid, 'classroom_id' => $classroom->uuid],
            ['status' => 'open', 'opens_at' => $quiz->opens_at, 'due_at' => $quiz->due_at]
        );
        abort_unless($quiz->isOpenNow($assignment), 403, 'Kuis belum dibuka atau sudah ditutup.');

        $attempt = GameAttempt::firstOrCreate(
            [
                'assignment_id' => $assignment->uuid,
                'student_id'    => $request->user()->uuid,
                'source'        => GameAttempt::SOURCE_ASYNC,
            ],
            ['status' => 'in_progress', 'started_at' => now()]
        );

        if ($attempt->isSubmitted()) {
            return response()->json([
                'ok'       => false,
                'message'  => 'Attempt sudah dikumpulkan di server — sync diabaikan.',
                'conflict' => true,
            ], 409);
        }

        foreach ($data['answers'] as $row) {
            $q = $quiz->questions()->where('uuid', $row['question_id'])->first();
            if (! $q) {
                continue;
            }
            $optId = $row['selected_option_id'] ?? null;
            if ($optId && ! $q->options()->where('uuid', $optId)->exists()) {
                $optId = null;
            }
            GameAnswer::updateOrCreate(
                ['attempt_id' => $attempt->uuid, 'question_id' => $q->uuid],
                [
                    'selected_option_id' => $optId,
                    'answer_text'        => $row['answer_text'] ?? null,
                    'answered_at'        => now(),
                ]
            );
        }

        if (! empty($data['duration_ms'])) {
            $attempt->update(['duration_ms' => $data['duration_ms']]);
        }

        if ($request->boolean('submit')) {
            $grader = app(GameAnswerGrader::class);
            $result = $grader->gradeAttempt($attempt->fresh('answers'), $quiz);
            $attempt->update([
                'total_score'   => $result['total_score'],
                'correct_count' => $result['correct_count'],
                'status'        => 'submitted',
                'submitted_at'  => now(),
            ]);
            Audit::log('arena_offline_sync_submit', $attempt, ['quiz_id' => $quiz->uuid]);
        }

        return response()->json([
            'ok'         => true,
            'attempt_id' => $attempt->uuid,
            'submitted'  => $attempt->fresh()->isSubmitted(),
        ]);
    }

    private function canCrossword(GameQuiz $quiz): bool
    {
        $words = $quiz->questions->filter(fn ($q) => $q->type === 'short_answer')
            ->flatMap(fn ($q) => $q->meta['answers'] ?? [])
            ->filter(fn ($w) => mb_strlen((string) $w) >= 3);

        return $words->count() >= 3;
    }
}
