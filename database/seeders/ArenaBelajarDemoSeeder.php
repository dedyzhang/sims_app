<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\GameQuestion;
use App\Models\GameQuestionOption;
use App\Models\GameQuiz;
use App\Models\GameQuizAssignment;
use Illuminate\Database\Seeder;

/**
 * Demo Arena Belajar: 2 kuis published (Matematika + IPA) dengan campuran tipe soal.
 * Idempotent — aman dijalankan ulang (hapus & buat ulang judul DEMO).
 *
 * Jalankan: php artisan db:seed --class=ArenaBelajarDemoSeeder
 */
class ArenaBelajarDemoSeeder extends Seeder
{
    use \Database\Seeders\Concerns\ResolvesDemoClassroom;

    public function run(): void
    {
        $classroom = $this->resolveDemoClassroom();
        if (! $classroom) {
            $this->command?->warn('Tidak ada classroom published — skip ArenaBelajarDemoSeeder.');

            return;
        }

        $this->purgeDemoQuizzes();

        $math = $this->seedMathQuiz($classroom);
        $ipa = $this->seedIpaQuiz($classroom);

        $this->command?->info('═══ Arena Belajar DEMO siap dicoba ═══');
        $this->command?->info('Kelas: '.$classroom->title.' ('.$classroom->class_code.')');
        $this->command?->info('1) '.$math->title.' — '.$math->questions()->count().' soal');
        $this->command?->info('2) '.$ipa->title.' — '.$ipa->questions()->count().' soal');
        $this->command?->warn('Login siswa → Ruang Kelas → tab Arena Belajar → Mulai kuis.');
        $this->command?->info('URL hub: /ruang-kelas/'.$classroom->class_code.'/arena-belajar');
    }

    private function seedMathQuiz(Classroom $classroom): GameQuiz
    {
        $quiz = GameQuiz::create([
            'classroom_id' => $classroom->uuid,
            'created_by' => $classroom->created_by,
            'title' => '[DEMO] Matematika Dasar — Arena',
            'instructions' => 'Kuis demo campuran: pilihan ganda, benar/salah, isian, dan pasangkan. Mode akurasi, feedback langsung aktif.',
            'mode' => 'async',
            'scoring_mode' => 'accuracy',
            'max_score' => 100,
            'instant_feedback' => true,
            'show_leaderboard' => true,
            'status' => 'published',
            'is_locked' => true,
            'access_token' => 'DEMO',
        ]);

        $sort = 0;

        $this->mcq($quiz, $sort++, 'Berapa hasil dari 12 × 8?', [
            ['84', false],
            ['96', true],
            ['108', false],
            ['86', false],
        ], '12 × 8 = 96.');

        $this->mcq($quiz, $sort++, 'Bilangan prima di bawah ini adalah…', [
            ['15', false],
            ['21', false],
            ['17', true],
            ['27', false],
        ], '17 hanya habis dibagi 1 dan dirinya sendiri.');

        $this->mcq($quiz, $sort++, 'Setiap bilangan genap habis dibagi 2.', [
            ['Benar', true],
            ['Salah', false],
        ], 'Definisi bilangan genap.', 'true_false');

        $this->mcq($quiz, $sort++, 'Hasil dari (−3) + 5 adalah −8.', [
            ['Benar', false],
            ['Salah', true],
        ], '(−3) + 5 = 2.', 'true_false');

        GameQuestion::create([
            'quiz_id' => $quiz->uuid,
            'type' => 'short_answer',
            'question_text' => 'Berapa nilai dari 7² (tujuh kuadrat)?',
            'points' => 1,
            'sort_order' => $sort++,
            'meta' => ['answers' => ['49']],
            'explanation' => '7 × 7 = 49.',
        ]);

        GameQuestion::create([
            'quiz_id' => $quiz->uuid,
            'type' => 'short_answer',
            'question_text' => 'Sebutkan hasil dari 100 ÷ 4.',
            'points' => 1,
            'sort_order' => $sort++,
            'meta' => ['answers' => ['25']],
            'explanation' => '100 ÷ 4 = 25.',
        ]);

        GameQuestion::create([
            'quiz_id' => $quiz->uuid,
            'type' => 'match',
            'question_text' => 'Pasangkan operasi dengan hasilnya yang benar.',
            'points' => 2,
            'sort_order' => $sort++,
            'meta' => [
                'pairs' => [
                    ['left' => '15 + 7', 'right' => '22'],
                    ['left' => '9 × 3', 'right' => '27'],
                    ['left' => '40 − 18', 'right' => '22'],
                    ['left' => '56 ÷ 7', 'right' => '8'],
                ],
            ],
            'explanation' => 'Hitung tiap operasi lalu cocokkan.',
        ]);

        $this->assign($quiz, $classroom);

        return $quiz;
    }

    private function seedIpaQuiz(Classroom $classroom): GameQuiz
    {
        $quiz = GameQuiz::create([
            'classroom_id' => $classroom->uuid,
            'created_by' => $classroom->created_by,
            'title' => '[DEMO] IPA — Rantai Makanan',
            'instructions' => 'Kuis singkat IPA tentang ekosistem. Cocok untuk uji async atau live review.',
            'mode' => 'async',
            'scoring_mode' => 'accuracy',
            'max_score' => 100,
            'instant_feedback' => true,
            'show_leaderboard' => true,
            'status' => 'published',
            'is_locked' => true,
            'access_token' => 'DEMO',
        ]);

        $sort = 0;

        $this->mcq($quiz, $sort++, 'Makhluk hidup yang membuat makanan sendiri disebut…', [
            ['Konsumen', false],
            ['Produsen', true],
            ['Pengurai', false],
            ['Predator', false],
        ], 'Produsen (autotrof) membuat makanan sendiri, misalnya tumbuhan.');

        $this->mcq($quiz, $sort++, 'Jamur dan bakteri dalam ekosistem berperan sebagai…', [
            ['Produsen', false],
            ['Konsumen tingkat 1', false],
            ['Pengurai', true],
            ['Herbivora', false],
        ], 'Pengurai menguraikan sisa makhluk hidup.');

        $this->mcq($quiz, $sort++, 'Dalam rantai makanan, energi mengalir dari produsen ke konsumen.', [
            ['Benar', true],
            ['Salah', false],
        ], 'Energi mengalir satu arah: produsen → konsumen → pengurai.', 'true_false');

        GameQuestion::create([
            'quiz_id' => $quiz->uuid,
            'type' => 'short_answer',
            'question_text' => 'Sebutkan satu contoh produsen di darat.',
            'points' => 1,
            'sort_order' => $sort++,
            'meta' => ['answers' => ['padi', 'rumput', 'pohon', 'tumbuhan', 'tanaman']],
            'explanation' => 'Contoh: padi, rumput, pohon.',
        ]);

        GameQuestion::create([
            'quiz_id' => $quiz->uuid,
            'type' => 'match',
            'question_text' => 'Pasangkan peran dengan contohnya.',
            'points' => 2,
            'sort_order' => $sort++,
            'meta' => [
                'pairs' => [
                    ['left' => 'Produsen', 'right' => 'Padi'],
                    ['left' => 'Konsumen', 'right' => 'Belalang'],
                    ['left' => 'Pengurai', 'right' => 'Jamur'],
                ],
            ],
            'explanation' => 'Padi = produsen, belalang = konsumen, jamur = pengurai.',
        ]);

        $this->assign($quiz, $classroom);

        return $quiz;
    }

    private function assign(GameQuiz $quiz, Classroom $classroom): void
    {
        GameQuizAssignment::firstOrCreate(
            ['quiz_id' => $quiz->uuid, 'classroom_id' => $classroom->uuid],
            ['status' => 'open']
        );
    }

    private function purgeDemoQuizzes(): void
    {
        $titles = [
            '[DEMO] Matematika Dasar — Arena',
            '[DEMO] IPA — Rantai Makanan',
        ];

        $quizIds = GameQuiz::withTrashed()->whereIn('title', $titles)->pluck('uuid');
        if ($quizIds->isEmpty()) {
            return;
        }

        $assignmentIds = GameQuizAssignment::whereIn('quiz_id', $quizIds)->pluck('uuid');
        if ($assignmentIds->isNotEmpty()) {
            \App\Models\GameAnswer::whereIn(
                'attempt_id',
                \App\Models\GameAttempt::whereIn('assignment_id', $assignmentIds)->pluck('uuid')
            )->delete();
            \App\Models\GameAttempt::whereIn('assignment_id', $assignmentIds)->delete();
            GameQuizAssignment::whereIn('uuid', $assignmentIds)->delete();
        }

        GameQuestionOption::whereIn(
            'question_id',
            GameQuestion::whereIn('quiz_id', $quizIds)->pluck('uuid')
        )->delete();
        GameQuestion::whereIn('quiz_id', $quizIds)->delete();
        GameQuiz::withTrashed()->whereIn('uuid', $quizIds)->forceDelete();
    }

    /** @param list<array{0:string,1:bool}> $options */
    private function mcq(GameQuiz $quiz, int $sort, string $text, array $options, ?string $explanation = null, string $type = 'mcq'): void
    {
        $q = GameQuestion::create([
            'quiz_id' => $quiz->uuid,
            'type' => $type,
            'question_text' => $text,
            'points' => 1,
            'sort_order' => $sort,
            'explanation' => $explanation,
        ]);

        foreach ($options as $j => [$optText, $isCorrect]) {
            GameQuestionOption::create([
                'question_id' => $q->uuid,
                'option_text' => $optText,
                'is_correct' => $isCorrect,
                'sort_order' => $j,
            ]);
        }
    }
}
