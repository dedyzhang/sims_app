<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\MissionActivityLog;
use App\Models\MissionAttempt;
use App\Services\MissionAttemptCompletionService;
use App\Services\MissionNalarScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use App\Support\MissionStepPayloadPresenter;

class MissionNalarController extends Controller
{
    public function index(): View
    {
        $missions = Mission::query()
            ->where('is_published', true)
            ->whereHas('steps')
            ->withCount('steps')
            ->orderBy('title')
            ->get();

        // Kuis & Live per kelas (GameQuiz) sekarang dijangkau dari sini juga (bukan
        // lagi tab di Ruang Kelas) — tampilkan kelas yang boleh dilihat user, sama
        // persis aturan ClassroomPolicy::view() supaya tak ada yang bocor/hilang.
        $user = auth()->user();
        $classrooms = \App\Models\Classroom::with(['rombel', 'pelajaran'])
            ->orderBy('title')
            ->get()
            ->filter(fn ($c) => Gate::forUser($user)->allows('view', $c))
            ->values();

        return view('jagat-misi.index', compact('missions', 'classrooms'));
    }

    public function play(Mission $mission): View
    {
        Gate::authorize('view', $mission);
        $mission->load(['steps' => fn ($q) => $q->orderBy('position')]);
        abort_unless($mission->isPlayable(), 404, 'Misi belum punya langkah permainan.');

        return view('jagat-misi.nalar', compact('mission'));
    }

    public function show(Request $request, Mission $mission): JsonResponse
    {
        Gate::authorize('view', $mission);
        $mission->load(['steps' => fn ($query) => $query->orderBy('position')]);

        return response()->json([
            'data' => [
                'mission' => $this->missionPayload($mission),
                'steps' => $mission->steps->map(fn ($step) => $this->stepPayload($step))->values(),
            ],
        ]);
    }

    public function store(Request $request, Mission $mission, MissionNalarScoringService $scoringService, MissionAttemptCompletionService $completionService): JsonResponse
    {
        Gate::authorize('evaluate', $mission);

        $validated = $request->validate([
            'assignment_id' => ['nullable', 'uuid', 'exists:mission_assignments,uuid'],
            'responses' => ['required', 'array'],
            'responses.interactive_narrative.path' => ['required', 'array'],
            'responses.interactive_narrative.path.*' => ['string'],
            'responses.interactive_narrative.final_node' => ['required', 'string'],
            'responses.strategic_decision.choices' => ['required', 'array'],
            'responses.strategic_decision.choices.*' => ['string'],
            'responses.strategic_decision.stats' => ['nullable', 'array'],
            'responses.strategic_decision.stats.stability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'responses.strategic_decision.stats.trust' => ['nullable', 'integer', 'min:0', 'max:100'],
            'responses.strategic_decision.stats.budget' => ['nullable', 'integer', 'min:0', 'max:100'],
            'responses.puzzle_sequencing.order' => ['required', 'array'],
            'responses.puzzle_sequencing.order.*' => ['string'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $user = $request->user();
        $mission->load(['steps' => fn ($query) => $query->orderBy('position'), 'reflectionPrompts']);

        $result = DB::transaction(function () use ($mission, $user, $validated, $scoringService, $completionService) {
            $assignmentId = $completionService->resolveAssignmentId(
                $mission,
                $user,
                $validated['assignment_id'] ?? null,
            );
            $scoreData = $scoringService->score($mission, $validated['responses']);
            $status = $completionService->resolveStatusAfterScore($mission);

            $attempt = MissionAttempt::create([
                'mission_id' => $mission->uuid,
                'assignment_id' => $assignmentId,
                'user_id' => $user->uuid,
                'status' => $status,
                'started_at' => now()->subSeconds((int) ($validated['duration_seconds'] ?? 0)),
                'completed_at' => $status === 'completed' ? now() : null,
                'score' => $scoreData['percentage'],
                'duration_seconds' => (int) ($validated['duration_seconds'] ?? 0),
                'result_meta' => [
                    'points_awarded' => $scoreData['points_awarded'],
                    'max_points' => $scoreData['max_points'],
                    'module_scores' => $scoreData['module_scores'],
                    'completed_modules' => $scoreData['completed_modules'],
                ],
            ]);

            foreach ($mission->steps as $step) {
                $moduleResponse = data_get($validated['responses'], $step->module_key, []);
                $moduleScore = $scoreData['module_scores'][$step->module_key] ?? [
                    'points_awarded' => 0,
                    'is_correct' => false,
                    'details' => [],
                ];

                $attempt->responses()->create([
                    'mission_step_id' => $step->uuid,
                    'module_key' => $step->module_key,
                    'response_payload' => is_array($moduleResponse) ? $moduleResponse : [],
                    'is_correct' => $moduleScore['is_correct'],
                    'points_awarded' => $moduleScore['points_awarded'],
                    'evaluated_at' => now(),
                ]);
            }

            MissionActivityLog::create([
                'action' => $status === 'completed' ? 'mission_attempt.completed' : 'mission_attempt.scored',
                'subject_type' => MissionAttempt::class,
                'subject_id' => $attempt->uuid,
                'causer_type' => $user::class,
                'causer_id' => $user->uuid,
                'properties' => [
                    'mission_id' => $mission->uuid,
                    'score' => $scoreData['percentage'],
                    'points_awarded' => $scoreData['points_awarded'],
                    'modules' => $scoreData['completed_modules'],
                ],
            ]);

            return $attempt->load(['responses', 'mission.steps', 'user']);
        });

        return response()->json([
            'message' => $result->status === 'awaiting_reflection'
                ? 'Skor tersimpan. Lanjut ke debrief refleksi.'
                : 'Misi nalar berhasil dinilai.',
            'data' => [
                'attempt' => $this->attemptPayload($result),
                'debrief_url' => $result->status === 'awaiting_reflection'
                    ? route('jagat-misi.debrief', $result)
                    : null,
            ],
        ], 201);
    }

    private function missionPayload(Mission $mission): array
    {
        return [
            'id' => $mission->uuid,
            'slug' => $mission->slug,
            'title' => $mission->title,
            'subject' => $mission->subject,
            'grade_level' => $mission->grade_level,
            'mechanic_type' => $mission->mechanic_type,
            'summary' => $mission->summary,
            'duration_minutes' => $mission->duration_minutes,
            'max_score' => $mission->max_score,
            'is_published' => $mission->is_published,
            'meta' => $mission->meta,
        ];
    }

    private function stepPayload($step): array
    {
        return [
            'id' => $step->uuid,
            'module_key' => $step->module_key,
            'position' => $step->position,
            'title' => $step->title,
            'prompt' => $step->prompt,
            'body' => $step->body,
            'payload' => MissionStepPayloadPresenter::forClient($step),
            'max_points' => $step->max_points,
        ];
    }

    private function attemptPayload(MissionAttempt $attempt): array
    {
        return [
            'id' => $attempt->uuid,
            'mission_id' => $attempt->mission_id,
            'user_id' => $attempt->user_id,
            'status' => $attempt->status,
            'score' => $attempt->score,
            'duration_seconds' => $attempt->duration_seconds,
            'result_meta' => $attempt->result_meta,
            'completed_at' => optional($attempt->completed_at)->toISOString(),
            'responses' => $attempt->responses->map(fn ($response) => [
                'module_key' => $response->module_key,
                'points_awarded' => $response->points_awarded,
                'is_correct' => $response->is_correct,
                'response_payload' => $response->response_payload,
            ])->values(),
        ];
    }
}
