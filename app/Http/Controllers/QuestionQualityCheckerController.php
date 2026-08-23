<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Http\Requests\CheckQuestionQualityRequest;
use App\Http\Requests\CheckQuestionQualityBatchRequest;
use App\Models\Classroom;
use App\Models\GameQuiz;
use App\Services\QuestionQualityCheckerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuestionQualityCheckerController extends Controller
{
    use InteractsWithAi;

    public function __construct(private QuestionQualityCheckerService $checker) {}

    public function index(Request $request, Classroom $classroom): View
    {
        $this->authorize('manage', $classroom);
        $classroom->loadMissing(['rombel', 'pelajaran']);

        return view('arena-belajar.question-quality-checker', [
            'classroom' => $classroom,
            'defaultGradeLevel' => $this->gradeLabel($classroom),
            'defaultSubject' => (string) ($classroom->pelajaran?->nama ?? ''),
            'aiAvailable' => $this->aiConfiguredFor($request->user()),
        ]);
    }

    public function page(Classroom $classroom, GameQuiz $quiz): View
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $classroom->loadMissing(['rombel', 'pelajaran']);
        $quiz->load(['questions.options']);

        return view('arena-belajar.quality-batch', [
            'classroom' => $classroom,
            'quiz' => $quiz,
            'defaultGradeLevel' => $this->gradeLabel($classroom),
            'defaultSubject' => (string) ($classroom->pelajaran?->nama ?? ''),
        ]);
    }

    public function check(CheckQuestionQualityRequest $request, Classroom $classroom): JsonResponse
    {
        $this->authorize('manage', $classroom);

        if ($this->aiConfiguredFor($request->user())
            && $limited = $this->aiRateLimited('question_quality_checker', $request->user()->uuid)) {
            return $limited;
        }

        $result = $this->checker->check(
            $request->validated(),
            $this->personalAiKey($request->user()),
        );

        if (($result['source'] ?? null) === 'ai' && isset($result['_usage'])) {
            $this->logAiGenerationUsage(
                $request->user()->uuid,
                'question_quality_checker',
                (array) $result['_usage'],
                'success',
            );
        }
        unset($result['_usage']);

        return response()->json([
            'ok' => true,
            'data' => $result,
        ]);
    }

    public function checkBatch(CheckQuestionQualityBatchRequest $request, Classroom $classroom): JsonResponse
    {
        $this->authorize('manage', $classroom);

        return $this->runBatch($request);
    }

    public function checkBatchForTeacher(CheckQuestionQualityBatchRequest $request): JsonResponse
    {
        return $this->runBatch($request);
    }

    private function runBatch(CheckQuestionQualityBatchRequest $request): JsonResponse
    {

        if ($this->aiConfiguredFor($request->user())
            && $limited = $this->aiRateLimited('question_quality_checker', $request->user()->uuid)) {
            return $limited;
        }

        $questions = $request->validated('questions');
        $batch = $this->checker->checkBatch($questions, $this->personalAiKey($request->user()));
        $summary = [
            'total' => count($questions),
            'layak' => 0,
            'perlu_revisi' => 0,
            'tidak_layak' => 0,
            'score_total' => 0,
        ];

        if (isset($batch['_usage'])) {
            $this->logAiGenerationUsage(
                $request->user()->uuid,
                'question_quality_checker',
                (array) $batch['_usage'],
                'success',
            );
        }

        $results = [];
        foreach ((array) ($batch['results'] ?? []) as $index => $result) {
            $status = $result['status'] ?? 'perlu_revisi';
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }
            $summary['score_total'] += (int) ($result['score'] ?? 0);
            $results[] = [
                'index' => $index,
                'question_text' => $questions[$index]['question_text'] ?? '',
                'data' => $result,
            ];
        }

        $summary['average_score'] = $summary['total'] > 0
            ? (int) round($summary['score_total'] / $summary['total'])
            : 0;
        unset($summary['score_total']);

        return response()->json([
            'ok' => true,
            'data' => [
                'summary' => $summary,
                'results' => $results,
            ],
        ]);
    }

    private function gradeLabel(Classroom $classroom): string
    {
        $rombel = $classroom->rombel;
        if (! $rombel) {
            return '';
        }

        return trim('Kelas '.($rombel->tingkat ?? '').($rombel->kelas ? ' '.$rombel->kelas : ''));
    }
}
