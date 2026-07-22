<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameQuizRequest;
use App\Models\Classroom;
use App\Models\GameFocusEvent;
use App\Models\GameQuestion;
use App\Models\GameQuestionOption;
use App\Models\GameQuiz;
use App\Models\GameQuizAssignment;
use App\Models\GameLiveSession;
use App\Models\Kelas;
use App\Models\Materi;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionAttempt;
use App\Models\NilaiFormatif;
use App\Models\NilaiSumatif;
use App\Models\RaporKonfirmasi;
use App\Models\TujuanPembelajaran;
use App\Services\GameQuizImporter;
use App\Support\ArenaAccessToken;
use App\Support\ArenaJoinQr;
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

        // Siswa lihat terbit + ditutup (hasil/hub); draf hanya guru
        if (auth()->user()->access === 'siswa') {
            $quizzes = $quizzes->whereIn('status', ['published', 'closed'])->values();
        }

        $canManage = auth()->user()->can('manage', $classroom);

        $missionAssignments = MissionAssignment::query()
            ->where('classroom_id', $classroom->uuid)
            ->with(['mission' => fn ($q) => $q->withCount('steps')])
            ->latest()
            ->get();

        if (auth()->user()->access === 'siswa') {
            $missionAssignments = $missionAssignments
                ->filter(fn ($a) => $a->mission?->isPublished()
                    && $a->mission->isPlayable()
                    && $a->isOpen())
                ->values();
        }

        $availableMissions = collect();
        $katalogMisi = collect();
        if ($canManage) {
            $katalogMisi = Mission::query()
                ->where('is_published', true)
                ->whereHas('steps')
                ->where(function ($q) use ($classroom) {
                    $q->where('classroom_id', $classroom->uuid)
                        ->orWhere('visible_to_teachers', true)
                        ->orWhereNull('classroom_id');
                })
                ->withCount('steps')
                ->orderBy('title')
                ->get();

            $availableMissions = $katalogMisi
                ->reject(fn ($m) => $missionAssignments->contains(fn ($a) => $a->mission_id === $m->uuid))
                ->values();
        }

        $myMissionAttempts = [];
        if (auth()->user()->access === 'siswa') {
            $myMissionAttempts = MissionAttempt::query()
                ->where('user_id', auth()->id())
                ->whereIn('assignment_id', $missionAssignments->pluck('uuid'))
                ->orderByDesc('completed_at')
                ->orderByDesc('created_at')
                ->get()
                ->unique('assignment_id')
                ->keyBy('assignment_id');
        }

        $ideJenjang = \App\Support\ArenaJenjang::rekomendasi();
        $ideTren = \App\Support\ArenaJenjang::trenRekomendasi();

        $liveQuizIds = \App\Models\GameLiveSession::query()
            ->where('classroom_id', $classroom->uuid)
            ->whereIn('status', ['lobby', 'question', 'reveal', 'standings'])
            ->pluck('quiz_id')
            ->unique()
            ->values()
            ->all();

        return view('arena-belajar.index', compact(
            'classroom',
            'quizzes',
            'canManage',
            'missionAssignments',
            'availableMissions',
            'katalogMisi',
            'myMissionAttempts',
            'ideJenjang',
            'ideTren',
            'liveQuizIds'
        ));
    }

    public function create(Classroom $classroom)
    {
        $this->authorize('manage', $classroom);

        $aiImport = session()->pull('arena_ai_import');

        return view('arena-belajar.form', [
            'classroom' => $classroom,
            'quiz' => null,
            'aiImportText' => is_array($aiImport) ? ($aiImport['raw_text'] ?? null) : null,
            'aiImportTitle' => is_array($aiImport) ? ($aiImport['title'] ?? null) : null,
            'asistenGuruAktif' => \App\Support\ModulAktif::aktif('asisten_guru'),
        ]);
    }

    public function store(StoreGameQuizRequest $request, Classroom $classroom)
    {
        $this->authorize('manage', $classroom);

        $quiz = DB::transaction(function () use ($request, $classroom) {
            $publishNow = $request->boolean('publish_now');
            $attrs = [
                'classroom_id'      => $classroom->uuid,
                'created_by'        => $request->user()->uuid,
                'title'             => $request->title,
                'instructions'      => RichText::clean($request->instructions),
                'mode'              => 'async',
                'play_mode'         => $request->input('play_mode', 'bebas'),
                'template'          => $request->input('template', 'quiz'),
                'scoring_mode'      => $request->scoring_mode,
                'max_score'         => $request->max_score,
                'hide_scores'       => $request->boolean('hide_scores'),
                'show_leaderboard'  => $request->boolean('show_leaderboard'),
                'instant_feedback'  => $request->boolean('instant_feedback'),
                'opens_at'          => $request->opens_at,
                'due_at'            => $request->due_at,
                'status'            => $publishNow ? 'published' : 'draft',
            ];
            if ($publishNow) {
                $attrs['is_locked'] = true;
                $attrs['access_token'] = GameQuiz::generateAccessToken();
            }

            $quiz = GameQuiz::create($attrs);

            $this->syncQuestions($quiz, $request->input('questions', []));

            if ($request->boolean('assign_self', true) || $publishNow) {
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

        if ($canManage) {
            $quiz->ensureAccessTokenForPublished();
            $quiz->refresh();
        }

        $myAttempt = null;
        if (auth()->user()->access === 'siswa' && $assignment) {
            $myAttempt = $assignment->attempts()
                ->where('student_id', auth()->user()->uuid)
                ->where('source', \App\Models\GameAttempt::SOURCE_ASYNC)
                ->first();
        }

        $copyTargets = collect();
        if ($canManage) {
            $copyTargets = Classroom::query()
                ->with(['rombel', 'pelajaran'])
                ->where('uuid', '!=', $classroom->uuid)
                ->when($classroom->id_pelajaran, fn ($q) => $q->where('id_pelajaran', $classroom->id_pelajaran))
                ->orderBy('title')
                ->get()
                ->filter(fn (Classroom $c) => auth()->user()->can('manage', $c))
                ->values();
        }

        $soloJoinUrl = ($canManage && $quiz->allowsSolo() && $quiz->access_token)
            ? ArenaJoinQr::soloJoinUrl($classroom, $quiz)
            : null;
        $liveJoinUrl = ArenaJoinQr::liveJoinUrl($classroom, $quiz);
        $soloJoinQrSvg = $soloJoinUrl ? ArenaJoinQr::svg($soloJoinUrl) : null;
        $liveJoinQrSvg = ($canManage && $quiz->allowsLive() && $quiz->access_token)
            ? ArenaJoinQr::svg($liveJoinUrl)
            : null;
        $soloBarcodePayload = ($canManage && $quiz->allowsSolo() && $quiz->access_token)
            ? ArenaJoinQr::soloBarcodePayload($quiz)
            : null;
        $liveBarcodePayload = ($canManage && $quiz->allowsLive() && $quiz->access_token)
            ? ArenaJoinQr::liveBarcodePayload($quiz)
            : null;
        $prefillSoloToken = request('join') === 'solo'
            ? strtoupper(substr((string) request('t', ''), 0, 8))
            : '';

        $requiresJoinToken = ArenaAccessToken::requiresToken($quiz);
        $hasJoinUnlock = ArenaAccessToken::hasUnlock($quiz);
        $prefillLiveToken = request('join') === 'live'
            ? strtoupper(substr((string) request('t', ''), 0, 8))
            : '';

        return view('arena-belajar.show', compact(
            'classroom',
            'quiz',
            'assignment',
            'canManage',
            'myAttempt',
            'copyTargets',
            'soloJoinUrl',
            'liveJoinUrl',
            'soloJoinQrSvg',
            'liveJoinQrSvg',
            'soloBarcodePayload',
            'liveBarcodePayload',
            'prefillSoloToken',
            'requiresJoinToken',
            'hasJoinUnlock',
            'prefillLiveToken'
        ));
    }

    /** Validasi token gabung (solo/live) lalu redirect kembali. */
    public function unlockJoinToken(Request $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('play', [$quiz, $classroom]);

        if ($message = ArenaAccessToken::validateAndGrant($request, $quiz)) {
            return back()->with('error', $message);
        }

        $redirect = $request->input('redirect');
        if (! is_string($redirect) || ! str_starts_with($redirect, url('/'))) {
            $redirect = route('classroom.arena.show', [$classroom, $quiz]);
        }

        return redirect($redirect)->with('success', 'Token diterima. Selamat bermain!');
    }

    public function edit(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $quiz->load(['questions.options']);

        return view('arena-belajar.form', [
            'classroom' => $classroom,
            'quiz' => $quiz,
            'aiImportText' => null,
            'aiImportTitle' => null,
            'asistenGuruAktif' => \App\Support\ModulAktif::aktif('asisten_guru'),
        ]);
    }

    public function update(StoreGameQuizRequest $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $questionsLocked = false;

        DB::transaction(function () use ($request, $quiz, $classroom, &$questionsLocked) {
            $publishNow = $request->boolean('publish_now');
            $attrs = [
                'title'            => $request->title,
                'instructions'     => RichText::clean($request->instructions),
                'scoring_mode'     => $request->scoring_mode,
                'play_mode'        => $request->input('play_mode', $quiz->play_mode ?? 'bebas'),
                'template'         => $request->input('template', $quiz->template ?? 'quiz'),
                'max_score'        => $request->max_score,
                'hide_scores'      => $request->boolean('hide_scores'),
                'show_leaderboard' => $request->boolean('show_leaderboard'),
                'instant_feedback' => $request->boolean('instant_feedback'),
                'opens_at'         => $request->opens_at,
                'due_at'           => $request->due_at,
                'status'           => $publishNow ? 'published' : $quiz->status,
            ];
            if ($publishNow && $quiz->status !== 'published') {
                $attrs['is_locked'] = true;
                $attrs['access_token'] = $quiz->access_token ?: GameQuiz::generateAccessToken();
            }

            $quiz->update($attrs);

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
            $token = $quiz->access_token ?: GameQuiz::generateAccessToken();
            $quiz->update([
                'status' => 'published',
                'is_locked' => true,
                'access_token' => $token,
            ]);
            $assignment = GameQuizAssignment::firstOrCreate(
                ['quiz_id' => $quiz->uuid, 'classroom_id' => $classroom->uuid],
                [
                    'opens_at' => $quiz->opens_at,
                    'due_at'   => $quiz->due_at,
                    'status'   => 'open',
                ]
            );
            if ($assignment->status !== 'open') {
                $assignment->update(['status' => 'open']);
            }
        });

        Audit::log('arena_quiz_publish', $quiz);

        $quiz->refresh();

        return back()->with(
            'success',
            'Kuis diterbitkan. Token solo: '.$quiz->access_token.' — berikan ke siswa sebelum main solo.'
        );
    }

    /** Tutup kuis untuk siswa (published → closed). Hasil lama tetap ada. */
    public function close(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);
        abort_unless($quiz->isPublished(), 422, 'Hanya kuis terbit yang bisa ditutup.');

        DB::transaction(function () use ($quiz, $classroom) {
            $quiz->update(['status' => 'closed']);
            GameQuizAssignment::where('quiz_id', $quiz->uuid)
                ->where('classroom_id', $classroom->uuid)
                ->update(['status' => 'closed']);

            // Tutup sesi live aktif agar poll/jawab siswa tidak 403 di tengah party
            GameLiveSession::where('quiz_id', $quiz->uuid)
                ->where('classroom_id', $classroom->uuid)
                ->whereIn('status', ['lobby', 'question', 'reveal', 'standings'])
                ->update(['status' => 'ended', 'ended_at' => now()]);
        });

        Audit::log('arena_quiz_close', $quiz);

        return back()->with('success', 'Kuis ditutup. Siswa tidak bisa main lagi. Hasil lama tetap tersimpan.');
    }

    /** Buka lagi untuk siswa (closed → published). Solo tetap wajib token. */
    public function reopen(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);
        abort_unless($quiz->isClosed(), 422, 'Hanya kuis ditutup yang bisa dibuka lagi.');

        if ($quiz->questions()->count() < 1) {
            return back()->with('error', 'Tambahkan minimal satu soal sebelum membuka lagi.');
        }

        DB::transaction(function () use ($quiz, $classroom) {
            $token = $quiz->access_token ?: GameQuiz::generateAccessToken();
            $quiz->update([
                'status' => 'published',
                'is_locked' => true,
                'access_token' => $token,
            ]);
            $assignment = GameQuizAssignment::firstOrCreate(
                ['quiz_id' => $quiz->uuid, 'classroom_id' => $classroom->uuid],
                [
                    'opens_at' => $quiz->opens_at,
                    'due_at' => $quiz->due_at,
                    'status' => 'open',
                ]
            );
            $assignment->update(['status' => 'open']);
        });

        Audit::log('arena_quiz_reopen', $quiz);
        $quiz->refresh();

        return back()->with(
            'success',
            'Kuis dibuka lagi untuk siswa. Token solo: '.$quiz->access_token
        );
    }

    /** Kembalikan ke draf hanya jika belum ada jawaban siswa. */
    public function unpublishToDraft(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);
        abort_unless(in_array($quiz->status, ['published', 'closed'], true), 422);

        $questionIds = $quiz->questions()->pluck('uuid');
        $hasAnswers = $questionIds->isNotEmpty()
            && \App\Models\GameAnswer::whereIn('question_id', $questionIds)->exists();

        $hasAttempts = GameQuizAssignment::where('quiz_id', $quiz->uuid)
            ->whereHas('attempts')
            ->exists();

        if ($hasAnswers || $hasAttempts) {
            return back()->with(
                'error',
                'Sudah ada pengerjaan siswa. Gunakan Tutup kuis, bukan kembalikan ke draf.'
            );
        }

        DB::transaction(function () use ($quiz, $classroom) {
            $quiz->update(['status' => 'draft']);
            GameQuizAssignment::where('quiz_id', $quiz->uuid)
                ->where('classroom_id', $classroom->uuid)
                ->update(['status' => 'closed']);
        });

        Audit::log('arena_quiz_unpublish_draft', $quiz);

        return back()->with('success', 'Kuis dikembalikan ke draf. Siswa tidak melihatnya.');
    }

    /** Generate ulang token solo (membatalkan unlock session token lama). Solo selalu wajib token. */
    public function regenerateSoloToken(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $token = GameQuiz::generateAccessToken();
        $quiz->update([
            'is_locked' => true,
            'access_token' => $token,
        ]);

        Audit::log('arena_quiz_solo_token', $quiz, ['action' => 'regenerate']);

        return back()->with('success', 'Token solo baru: '.$token.' — beritahu siswa token lama sudah tidak berlaku.');
    }

    /**
     * Salin soal kuis ke ruang kelas lain yang bisa dikelola guru (mis. 8A → 8B/8C/8D).
     */
    public function copyToClassrooms(Request $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        if ($quiz->questions()->count() < 1) {
            return back()->with('error', 'Kuis belum punya soal untuk disalin.');
        }

        $data = $request->validate([
            'classroom_ids'   => ['required', 'array', 'min:1', 'max:20'],
            'classroom_ids.*' => ['uuid', 'distinct', 'exists:classrooms,uuid'],
            'publish_copies'  => ['nullable', 'boolean'],
        ]);

        $quiz->load(['questions.options']);
        $publish = $request->boolean('publish_copies');
        $copied = 0;
        $skippedUnauthorized = 0;
        $skippedMapel = 0;

        DB::transaction(function () use ($request, $quiz, $classroom, $data, $publish, &$copied, &$skippedUnauthorized, &$skippedMapel) {
            foreach ($data['classroom_ids'] as $targetId) {
                if ($targetId === $classroom->uuid) {
                    continue;
                }

                $target = Classroom::query()->findOrFail($targetId);
                if (! $request->user()->can('manage', $target)) {
                    $skippedUnauthorized++;

                    continue;
                }

                // Samakan filter UI: hanya kelas dengan mapel yang sama.
                if ($classroom->id_pelajaran && $target->id_pelajaran !== $classroom->id_pelajaran) {
                    $skippedMapel++;

                    continue;
                }

                $clone = GameQuiz::create([
                    'classroom_id' => $target->uuid,
                    'created_by' => $request->user()->uuid,
                    'title' => $quiz->title,
                    'instructions' => $quiz->instructions,
                    'mode' => $quiz->mode ?? 'async',
                    'play_mode' => $quiz->play_mode ?? 'bebas',
                    'template' => $quiz->template ?? 'quiz',
                    'scoring_mode' => $quiz->scoring_mode,
                    'max_score' => $quiz->max_score,
                    'hide_scores' => $quiz->hide_scores,
                    'show_leaderboard' => $quiz->show_leaderboard,
                    'instant_feedback' => $quiz->instant_feedback,
                    'opens_at' => null,
                    'due_at' => null,
                    'status' => $publish ? 'published' : 'draft',
                    'is_locked' => $publish,
                    'access_token' => $publish ? GameQuiz::generateAccessToken() : null,
                ]);

                foreach ($quiz->questions as $question) {
                    $newQuestion = GameQuestion::create([
                        'quiz_id' => $clone->uuid,
                        'type' => $question->type,
                        'question_text' => $question->question_text,
                        'points' => $question->points,
                        'sort_order' => $question->sort_order,
                        'time_limit_seconds' => $question->time_limit_seconds,
                        'explanation' => $question->explanation,
                        'meta' => $question->meta,
                    ]);

                    foreach ($question->options as $option) {
                        GameQuestionOption::create([
                            'question_id' => $newQuestion->uuid,
                            'option_text' => $option->option_text,
                            'is_correct' => $option->is_correct,
                            'sort_order' => $option->sort_order,
                        ]);
                    }
                }

                if ($publish) {
                    GameQuizAssignment::firstOrCreate(
                        ['quiz_id' => $clone->uuid, 'classroom_id' => $target->uuid],
                        ['status' => 'open']
                    );
                }

                Audit::log('arena_quiz_copy', $clone, [
                    'from_quiz' => $quiz->uuid,
                    'from_classroom' => $classroom->uuid,
                    'to_classroom' => $target->uuid,
                ]);

                $copied++;
            }
        });

        if ($copied < 1) {
            $parts = [];
            if ($skippedUnauthorized > 0) {
                $parts[] = "{$skippedUnauthorized} tanpa akses kelola";
            }
            if ($skippedMapel > 0) {
                $parts[] = "{$skippedMapel} beda mapel";
            }
            $msg = $parts !== []
                ? 'Tidak ada kelas yang disalin ('.implode(', ', $parts).').'
                : 'Pilih minimal satu kelas tujuan.';

            return back()->with('error', $msg);
        }

        $msg = "Soal disalin ke {$copied} kelas".($publish ? ' (langsung terbit).' : ' sebagai draf.');
        if ($skippedUnauthorized > 0 || $skippedMapel > 0) {
            $bits = [];
            if ($skippedUnauthorized > 0) {
                $bits[] = "{$skippedUnauthorized} tanpa akses";
            }
            if ($skippedMapel > 0) {
                $bits[] = "{$skippedMapel} beda mapel";
            }
            $msg .= ' Dilewati: '.implode(', ', $bits).'.';
        }

        return back()->with('success', $msg);
    }

    public function destroy(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        Audit::log('arena_quiz_delete', $quiz, ['title' => $quiz->title]);

        DB::transaction(function () use ($quiz, $classroom) {
            \App\Models\GameLiveSession::where('quiz_id', $quiz->uuid)
                ->whereIn('status', ['lobby', 'question', 'reveal', 'standings'])
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

    /** Siswa melapor keluar mode fokus (fullscreen / pindah tab). */
    public function focusExit(Request $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);

        if ($request->user()->access !== 'siswa') {
            return response()->noContent();
        }

        $this->authorize('play', [$quiz, $classroom]);

        $data = $request->validate([
            'context' => ['required', 'in:solo,live,template'],
            'reason' => ['nullable', 'string', 'max:100'],
            'attempt_id' => ['nullable', 'uuid'],
            'session_id' => ['nullable', 'uuid'],
        ]);

        $attemptId = $data['attempt_id'] ?? null;
        if ($attemptId) {
            $owns = \App\Models\GameAttempt::query()
                ->where('uuid', $attemptId)
                ->where('student_id', $request->user()->uuid)
                ->whereHas('assignment', fn ($q) => $q->where('quiz_id', $quiz->uuid)->where('classroom_id', $classroom->uuid))
                ->exists();
            if (! $owns) {
                $attemptId = null;
            }
        }

        $sessionId = $data['session_id'] ?? null;
        if ($sessionId) {
            $ok = GameLiveSession::where('uuid', $sessionId)
                ->where('quiz_id', $quiz->uuid)
                ->where('classroom_id', $classroom->uuid)
                ->exists();
            if (! $ok) {
                $sessionId = null;
            }
        }

        GameFocusEvent::create([
            'quiz_id' => $quiz->uuid,
            'classroom_id' => $classroom->uuid,
            'student_id' => $request->user()->uuid,
            'context' => $data['context'],
            'attempt_id' => $attemptId,
            'session_id' => $sessionId,
            'type' => 'keluar',
            'reason' => substr((string) ($data['reason'] ?? 'keluar fokus'), 0, 100),
        ]);

        return response()->json(['ok' => true]);
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

        $focusExitCounts = GameFocusEvent::query()
            ->where('quiz_id', $quiz->uuid)
            ->where('classroom_id', $classroom->uuid)
            ->where('type', 'keluar')
            ->selectRaw('student_id, count(*) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

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
            'memberCount', 'doneCount', 'questionStats', 'materiList', 'tupeList',
            'focusExitCounts'
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
                'quiz_id'             => $quiz->uuid,
                'type'                => $qData['type'],
                'question_text'       => $qData['question_text'],
                'points'              => $points,
                'sort_order'          => $i,
                'time_limit_seconds'  => $qData['time_limit_seconds'] ?? null,
                'explanation'         => $qData['explanation'] ?? null,
                'meta'                => $this->normalizeQuestionMeta($qData),
            ]);

            if (in_array($qData['type'], ['mcq', 'mcq_complex', 'true_false'], true)) {
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
