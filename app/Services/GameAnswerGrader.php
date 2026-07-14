<?php

namespace App\Services;

use App\Models\GameAnswer;
use App\Models\GameAttempt;
use App\Models\GameQuestion;
use App\Models\GameQuiz;

/**
 * Hitung skor attempt Arena Belajar di server.
 * Mode accuracy: poin penuh jika benar.
 * Mode competitive: poin dasar + bonus kecepatan (maks 20% dari poin soal).
 *
 * Match: proporsional (pasangan benar / total × points).
 * Short answer: normalisasi + fuzzy Levenshtein (threshold 1 untuk jawaban ≤5 char, 2 untuk lebih panjang).
 */
class GameAnswerGrader
{
    public function gradeAttempt(GameAttempt $attempt, GameQuiz $quiz): array
    {
        $attempt->loadMissing('answers');
        $questions = $quiz->questions()->with('options')->get()->keyBy('uuid');
        $answers = $attempt->answers->keyBy('question_id');

        $totalPoints = max(1, (int) $questions->sum('points'));
        $earnedRaw = 0;
        $correctCount = 0;
        $durationMs = max(0, (int) ($attempt->duration_ms ?? 0));
        $perQuestionMs = $questions->count() > 0 ? (int) floor($durationMs / $questions->count()) : 0;

        foreach ($questions as $question) {
            $answer = $answers->get($question->uuid);
            if (!$answer) {
                continue;
            }

            $result = $this->scoreQuestion($question, $answer->selected_option_id, $answer->answer_text, $quiz, $perQuestionMs);
            $answer->update([
                'is_correct'     => $result['is_correct'],
                'points_awarded' => $result['points'],
            ]);
            $earnedRaw += $result['points'];
            if ($result['is_correct']) {
                $correctCount++;
            }
        }

        $maxRaw = $quiz->scoring_mode === 'competitive'
            ? (int) ceil($totalPoints * 1.2)
            : $totalPoints;

        $scaled = (int) round(($earnedRaw / max(1, $maxRaw)) * $quiz->max_score);
        $scaled = max(0, min($quiz->max_score, $scaled));

        return [
            'total_score'   => $scaled,
            'correct_count' => $correctCount,
        ];
    }

    /**
     * Grade satu soal (live / autosave) dan update baris GameAnswer.
     * @return array{is_correct:bool,points:int}
     */
    public function gradeAndPersistAnswer(
        GameAnswer $answer,
        GameQuestion $question,
        GameQuiz $quiz,
        ?int $elapsedMs = null
    ): array {
        $result = $this->scoreQuestion(
            $question,
            $answer->selected_option_id,
            $answer->answer_text,
            $quiz,
            $elapsedMs ?? 0
        );
        $answer->update([
            'is_correct'     => $result['is_correct'],
            'points_awarded' => $result['points'],
        ]);

        return $result;
    }

    /**
     * @return array{is_correct:bool,points:int}
     */
    public function scoreQuestion(
        GameQuestion $question,
        ?string $selectedOptionId,
        ?string $answerText,
        GameQuiz $quiz,
        int $elapsedMs = 0
    ): array {
        $base = (int) $question->points;
        $isCorrect = false;
        $points = 0;

        if (in_array($question->type, ['mcq', 'true_false'], true)) {
            $isCorrect = $this->isCorrect($question, $selectedOptionId, $answerText);
            if ($isCorrect) {
                $points = $base;
            }
        } elseif ($question->type === 'short_answer') {
            $ratio = $this->shortAnswerRatio($question, $answerText);
            $isCorrect = $ratio >= 1.0;
            $points = (int) floor($base * $ratio);
        } elseif ($question->type === 'match') {
            $ratio = $this->matchRatio($question, $answerText);
            $isCorrect = $ratio >= 1.0;
            $points = (int) floor($base * $ratio);
        }

        if ($points > 0 && $quiz->scoring_mode === 'competitive') {
            $budget = 30000;
            $used = min($budget, max(0, $elapsedMs));
            $speedRatio = max(0, ($budget - $used) / $budget);
            $points += (int) floor($base * 0.2 * $speedRatio);
        }

        return ['is_correct' => $isCorrect, 'points' => $points];
    }

    public function isCorrect(GameQuestion $question, ?string $selectedOptionId, ?string $answerText = null): bool
    {
        if (in_array($question->type, ['mcq', 'true_false'], true)) {
            if (!$selectedOptionId) {
                return false;
            }
            $option = $question->relationLoaded('options')
                ? $question->options->firstWhere('uuid', $selectedOptionId)
                : $question->options()->where('uuid', $selectedOptionId)->first();

            return $option && $option->is_correct;
        }

        if ($question->type === 'short_answer') {
            return $this->shortAnswerRatio($question, $answerText) >= 1.0;
        }

        if ($question->type === 'match') {
            return $this->matchRatio($question, $answerText) >= 1.0;
        }

        return false;
    }

    /** 0–1: proporsi pasangan benar. */
    public function matchRatio(GameQuestion $question, ?string $answerText): float
    {
        $pairs = $question->meta['pairs'] ?? [];
        if (!is_array($pairs) || count($pairs) < 1) {
            return 0.0;
        }

        $submitted = $this->decodeJsonMap($answerText);
        if (!$submitted) {
            return 0.0;
        }

        $correct = 0;
        foreach ($pairs as $pair) {
            $left = (string) ($pair['left'] ?? '');
            $right = $this->normalize((string) ($pair['right'] ?? ''));
            $got = $this->normalize((string) ($submitted[$left] ?? ''));
            if ($left !== '' && $got !== '' && $got === $right) {
                $correct++;
            }
        }

        return $correct / count($pairs);
    }

    /** 0 atau 1 (fuzzy dianggap penuh jika lolos threshold). */
    public function shortAnswerRatio(GameQuestion $question, ?string $answerText): float
    {
        $accepted = $question->meta['answers'] ?? [];
        if (!is_array($accepted) || count($accepted) < 1) {
            return 0.0;
        }

        $given = $this->normalize((string) $answerText);
        if ($given === '') {
            return 0.0;
        }

        foreach ($accepted as $ans) {
            $target = $this->normalize((string) $ans);
            if ($target === '') {
                continue;
            }
            if ($given === $target) {
                return 1.0;
            }
            $threshold = mb_strlen($target) <= 5 ? 1 : 2;
            if (levenshtein($given, $target) <= $threshold) {
                return 1.0;
            }
        }

        return 0.0;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return $s;
    }

    /** @return array<string,string>|null */
    private function decodeJsonMap(?string $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $out = [];
        foreach ($decoded as $k => $v) {
            if (is_array($v) && isset($v['left'], $v['right'])) {
                $out[(string) $v['left']] = (string) $v['right'];
            } else {
                $out[(string) $k] = is_scalar($v) ? (string) $v : '';
            }
        }

        return $out;
    }
}
