<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameQuizRequest;
use App\Models\Classroom;
use App\Models\GameQuestion;
use App\Models\GameQuestionOption;
use App\Models\GameQuiz;
use App\Models\GameQuizAssignment;
use App\Models\Kelas;
use App\Models\Materi;
use App\Models\NilaiFormatif;
use App\Models\NilaiSumatif;
use App\Models\RaporKonfirmasi;
use App\Models\TujuanPembelajaran;
use App\Services\GameQuizImporter;
use App\Support\Audit;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;

class GameQuizController extends Controller implements HasMiddleware
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

    public function index(Classroom $classroom)
    {
        $this->authorize('view', $classroom);

        $quizzes = GameQuiz::where('classroom_id', $classroom->uuid)
            ->withCount('questions')
            ->with(['assignments' => fn ($q) => $q->where('classroom_id', $classroom->uuid)])
            ->latest()
            ->get();

        // Siswa hanya lihat published
        if (auth()->user()->access === 'siswa') {
            $quizzes = $quizzes->where('status', 'published')->values();
        }

        $canManage = auth()->user()->can('manage', $classroom);

        return view('arena-belajar.index', compact('classroom', 'quizzes', 'canManage'));
    }

    public function create(Classroom $classroom)
    {
        $this->authorize('manage', $classroom);

        return view('arena-belajar.form', [
            'classroom' => $classroom,
            'quiz'      => null,
        ]);
    }

    public function store(StoreGameQuizRequest $request, Classroom $classroom)
    {
        $this->authorize('manage', $classroom);

        $quiz = DB::transaction(function () use ($request, $classroom) {
            $quiz = GameQuiz::create([
                'classroom_id'      => $classroom->uuid,
                'created_by'        => $request->user()->uuid,
                'title'             => $request->title,
                'instructions'      => RichText::clean($request->instructions),
                'mode'              => 'async',
                'template'          => $request->input('template', 'quiz'),
                'scoring_mode'      => $request->scoring_mode,
                'max_score'         => $request->max_score,
                'hide_scores'       => $request->boolean('hide_scores'),
                'show_leaderboard'  => $request->boolean('show_leaderboard'),
                'instant_feedback'  => $request->boolean('instant_feedback'),
                'opens_at'          => $request->opens_at,
                'due_at'            => $request->due_at,
                'status'            => $request->boolean('publish_now') ? 'published' : 'draft',
            ]);

            $this->syncQuestions($quiz, $request->input('questions', []));

            if ($request->boolean('assign_self', true) || $request->boolean('publish_now')) {
                GameQuizAssignment::firstOrCreate(
                    ['quiz_id' => $quiz->uuid, 'classroom_id' => $classroom->uuid],
                    [
                        'opens_at' => $quiz->opens_at,
                        'due_at'   => $quiz->due_at,
                        'status'   => 'open',
                    ]
                );
            }

            return $quiz;
        });

        Audit::log('arena_quiz_create', $quiz, ['title' => $quiz->title, 'questions' => $quiz->questions()->count()]);

        return redirect()
            ->route('classroom.arena.show', [$classroom, $quiz])
            ->with('success', 'Kuis Arena Belajar disimpan.');
    }

    public function show(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('view', $quiz);

        $quiz->load(['questions.options', 'author', 'assignments']);
        $assignment = $quiz->assignmentFor($classroom);
        $canManage = auth()->user()->can('manage', $quiz);

        $myAttempt = null;
        if (auth()->user()->access === 'siswa' && $assignment) {
            $myAttempt = $assignment->attempts()
                ->where('student_id', auth()->user()->uuid)
                ->where('source', \App\Models\GameAttempt::SOURCE_ASYNC)
                ->first();
        }

        return view('arena-belajar.show', compact('classroom', 'quiz', 'assignment', 'canManage', 'myAttempt'));
    }

    public function edit(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $quiz->load(['questions.options']);

        return view('arena-belajar.form', [
            'classroom' => $classroom,
            'quiz'      => $quiz,
        ]);
    }

    public function update(StoreGameQuizRequest $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $questionsLocked = false;

        DB::transaction(function () use ($request, $quiz, $classroom, &$questionsLocked) {
            $quiz->update([
                'title'            => $request->title,
                'instructions'     => RichText::clean($request->instructions),
                'scoring_mode'     => $request->scoring_mode,
                'template'         => $request->input('template', $quiz->template ?? 'quiz'),
                'max_score'        => $request->max_score,
                'hide_scores'      => $request->boolean('hide_scores'),
                'show_leaderboard' => $request->boolean('show_leaderboard'),
                'instant_feedback' => $request->boolean('instant_feedback'),
                'opens_at'         => $request->opens_at,
                'due_at'           => $request->due_at,
                'status'           => $request->boolean('publish_now') ? 'published' : $quiz->status,
            ]);

            $questionIds = $quiz->questions()->pluck('uuid');
            $questionsLocked = $questionIds->isNotEmpty()
                && \App\Models\GameAnswer::whereIn('question_id', $questionIds)->exists();

            if (! $questionsLocked) {
                $quiz->questions()->each(function (GameQuestion $q) {
                    $q->options()->delete();
                    $q->delete();
                });
                $this->syncQuestions($quiz, $request->input('questions', []));
            }

            $assignment = GameQuizAssignment::firstOrCreate(
                ['quiz_id' => $quiz->uuid, 'classroom_id' => $classroom->uuid],
                ['status' => 'open']
            );
            $assignment->update([
                'opens_at' => $quiz->opens_at,
                'due_at'   => $quiz->due_at,
            ]);
        });

        Audit::log('arena_quiz_update', $quiz);

        return redirect()
            ->route('classroom.arena.show', [$classroom, $quiz])
            ->with(
                'success',
                $questionsLocked
                    ? 'Pengaturan kuis diperbarui. Soal tidak diubah karena sudah ada jawaban siswa.'
                    : 'Kuis diperbarui.'
            );
    }

    public function publish(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        if ($quiz->questions()->count() < 1) {
            return back()->with('error', 'Tambahkan minimal satu soal sebelum menerbitkan.');
        }

        DB::transaction(function () use ($quiz, $classroom) {
            $quiz->update(['status' => 'published']);
            GameQuizAssignment::firstOrCreate(
                ['quiz_id' => $quiz->uuid, 'classroom_id' => $classroom->uuid],
                [
                    'opens_at' => $quiz->opens_at,
                    'due_at'   => $quiz->due_at,
                    'status'   => 'open',
                ]
            );
        });

        Audit::log('arena_quiz_publish', $quiz);

        return back()->with('success', 'Kuis diterbitkan. Siswa dapat mengerjakan.');
    }

    public function destroy(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        Audit::log('arena_quiz_delete', $quiz, ['title' => $quiz->title]);

        DB::transaction(function () use ($quiz, $classroom) {
            \App\Models\GameLiveSession::where('quiz_id', $quiz->uuid)
                ->whereIn('status', ['lobby', 'question', 'reveal'])
                ->update(['status' => 'ended', 'ended_at' => now()]);

            GameQuizAssignment::where('quiz_id', $quiz->uuid)
                ->where('classroom_id', $classroom->uuid)
                ->update(['status' => 'closed']);

            $quiz->delete();
        });

        return redirect()
            ->route('classroom.arena.index', $classroom)
            ->with('success', 'Kuis dihapus.');
    }

    public function importPreview(Request $request, Classroom $classroom, GameQuizImporter $importer)
    {
        $this->authorize('manage', $classroom);

        $request->validate(['raw_text' => ['required', 'string', 'max:50000']]);
        $questions = $importer->parse($request->raw_text);

        return response()->json([
            'ok'        => true,
            'count'     => count($questions),
            'questions' => $questions,
        ]);
    }

    public function results(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('monitor', $quiz);

        $assignment = $quiz->assignmentFor($classroom);
        $quiz->load(['questions.options']);

        $attempts = $assignment
            ? $assignment->attempts()->with('student')->orderByDesc('total_score')->get()
            : collect();

        $memberCount = $classroom->members()->where('role_in_class', 'siswa')->count();
        $doneCount = $attempts->whereIn('status', ['submitted', 'graded'])->count();

        $questionStats = $quiz->questions->map(function (GameQuestion $q) use ($attempts) {
            $answerRows = \App\Models\GameAnswer::where('question_id', $q->uuid)
                ->whereIn('attempt_id', $attempts->pluck('uuid'))
                ->get();
            $answered = $answerRows->count();
            $correct = $answerRows->where('is_correct', true)->count();

            return [
                'question'  => $q,
                'answered'  => $answered,
                'correct'   => $correct,
                'accuracy'  => $answered > 0 ? round(($correct / $answered) * 100) : null,
            ];
        });

        // Data transfer nilai (TP / materi)
        $materiList = collect();
        $tupeList = collect();
        if ($classroom->id_pelajaran && $classroom->id_kelas) {
            $materiList = Materi::whereHas('ngajar', function ($q) use ($classroom) {
                $q->where('id_kelas', $classroom->id_kelas)
                    ->where('id_pelajaran', $classroom->id_pelajaran);
            })->orderBy('urutan')->get();
            $tupeList = TujuanPembelajaran::whereIn('id_materi', $materiList->pluck('uuid'))->orderBy('urutan')->get();
        }

        return view('arena-belajar.results', compact(
            'classroom', 'quiz', 'assignment', 'attempts',
            'memberCount', 'doneCount', 'questionStats', 'materiList', 'tupeList'
        ));
    }

    public function transferGrades(Request $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $data = $request->validate([
            'type'      => 'required|in:formatif,sumatif',
            'id_tupe'   => 'nullable|required_if:type,formatif|uuid',
            'id_materi' => 'nullable|required_if:type,sumatif|uuid',
        ]);

        $assignment = $quiz->assignmentFor($classroom);
        if (!$assignment) {
            return back()->with('error', 'Kuis belum ditugaskan ke kelas ini.');
        }

        // Scope materi/tupe ke mapel+kelas classroom (cegah IDOR cross-mapel)
        $allowedMateri = collect();
        $allowedTupe = collect();
        if ($classroom->id_pelajaran && $classroom->id_kelas) {
            $allowedMateri = Materi::whereHas('ngajar', function ($q) use ($classroom) {
                $q->where('id_kelas', $classroom->id_kelas)
                    ->where('id_pelajaran', $classroom->id_pelajaran);
            })->pluck('uuid');
            $allowedTupe = TujuanPembelajaran::whereIn('id_materi', $allowedMateri)->pluck('uuid');
        }

        if ($data['type'] === 'formatif') {
            abort_unless($allowedTupe->contains($data['id_tupe']), 422, 'TP tidak termasuk mapel kelas ini.');
        } else {
            abort_unless($allowedMateri->contains($data['id_materi']), 422, 'Materi tidak termasuk mapel kelas ini.');
        }

        $targetMateri = $data['type'] === 'formatif'
            ? Materi::find(TujuanPembelajaran::where('uuid', $data['id_tupe'])->value('id_materi'))
            : Materi::find($data['id_materi']);

        if ($targetMateri && RaporKonfirmasi::where('id_ngajar', $targetMateri->id_ngajar)
            ->where('id_semester', $targetMateri->id_semester)->exists()) {
            return back()->with('error', 'Transfer dibatalkan: rapor untuk mapel & semester ini sudah dikunci.');
        }

        $rombel = $classroom->rombel ?: Kelas::find($classroom->id_kelas);
        $students = $rombel ? $rombel->siswa : collect();
        if ($students->isEmpty()) {
            return back()->with('error', 'Tidak ada siswa di kelas ini.');
        }

        $attempts = $assignment->attempts()
            ->whereIn('status', ['submitted', 'graded'])
            ->get()
            ->groupBy('student_id')
            ->map(fn ($group) => $group->sortByDesc('total_score')->first());

        $count = 0;
        DB::transaction(function () use ($data, $students, $attempts, &$count) {
            foreach ($students as $siswa) {
                $attempt = $attempts->get($siswa->id_login);
                $score = $attempt ? $attempt->total_score : 0;

                if ($data['type'] === 'formatif') {
                    $idTupe = $data['id_tupe'];
                    $idMateri = TujuanPembelajaran::where('uuid', $idTupe)->value('id_materi');
                    NilaiFormatif::updateOrCreate(
                        ['id_tupe' => $idTupe, 'id_siswa' => $siswa->uuid],
                        ['id_materi' => $idMateri, 'nilai' => $score]
                    );
                } else {
                    NilaiSumatif::updateOrCreate(
                        ['id_materi' => $data['id_materi'], 'id_siswa' => $siswa->uuid],
                        ['nilai' => $score]
                    );
                }

                if ($attempt && $attempt->status === 'submitted') {
                    $attempt->update(['status' => 'graded']);
                }
                $count++;
            }
        });

        Audit::log('arena_grades_transferred', $quiz, [
            'type'  => $data['type'],
            'count' => $count,
        ]);

        return back()->with('success', "Berhasil mentransfer {$count} nilai siswa ke buku nilai.");
    }

    private function syncQuestions(GameQuiz $quiz, array $questions): void
    {
        $totalPoints = collect($questions)->sum(fn ($q) => max(1, (int) ($q['points'] ?? 1)));
        if ($totalPoints < 1) {
            $totalPoints = count($questions);
        }

        foreach ($questions as $i => $qData) {
            $points = max(1, (int) ($qData['points'] ?? 1));
            $question = GameQuestion::create([
                'quiz_id'       => $quiz->uuid,
                'type'          => $qData['type'],
                'question_text' => $qData['question_text'],
                'points'        => $points,
                'sort_order'    => $i,
                'explanation'   => $qData['explanation'] ?? null,
                'meta'          => $this->normalizeQuestionMeta($qData),
            ]);

            if (in_array($qData['type'], ['mcq', 'true_false'], true)) {
                foreach ($qData['options'] ?? [] as $j => $opt) {
                    if (trim((string) ($opt['option_text'] ?? '')) === '') {
                        continue;
                    }
                    GameQuestionOption::create([
                        'question_id' => $question->uuid,
                        'option_text' => $opt['option_text'],
                        'is_correct'  => !empty($opt['is_correct']),
                        'sort_order'  => $j,
                    ]);
                }
            }
        }
    }

    private function normalizeQuestionMeta(array $qData): ?array
    {
        $type = $qData['type'] ?? 'mcq';
        if ($type === 'short_answer') {
            $answers = collect($qData['meta']['answers'] ?? [])
                ->map(fn ($a) => trim((string) $a))
                ->filter()
                ->values()
                ->all();

            return ['answers' => $answers];
        }
        if ($type === 'match') {
            $pairs = collect($qData['meta']['pairs'] ?? [])
                ->map(fn ($p) => [
                    'left'  => trim((string) ($p['left'] ?? '')),
                    'right' => trim((string) ($p['right'] ?? '')),
                ])
                ->filter(fn ($p) => $p['left'] !== '' && $p['right'] !== '')
                ->values()
                ->all();

            return ['pairs' => $pairs];
        }

        return null;
    }
}
