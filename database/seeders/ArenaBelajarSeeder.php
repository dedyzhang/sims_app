<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\GameQuestion;
use App\Models\GameQuestionOption;
use App\Models\GameQuiz;
use App\Models\GameQuizAssignment;
use Illuminate\Database\Seeder;

/**
 * Contoh 1 kuis Arena Belajar (7 soal, campuran tipe) untuk classroom published pertama.
 * Jalankan: php artisan db:seed --class=ArenaBelajarSeeder
 */
class ArenaBelajarSeeder extends Seeder
{
    public function run(): void
    {
        $classroom = Classroom::where('status', 'published')->first();
        if (!$classroom) {
            $this->command?->warn('Tidak ada classroom published — skip ArenaBelajarSeeder.');

            return;
        }

        $quiz = GameQuiz::create([
            'classroom_id'     => $classroom->uuid,
            'created_by'       => $classroom->created_by,
            'title'            => 'Kuis Contoh Arena — Matematika Dasar',
            'instructions'     => 'Kuis demo Arena Belajar: 7 soal campuran (pilihan ganda, benar/salah, isian, pasangkan). Mode akurasi — tanpa bonus kecepatan. Feedback langsung aktif.',
            'mode'             => 'async',
            'scoring_mode'     => 'accuracy',
            'max_score'        => 100,
            'instant_feedback' => true,
            'show_leaderboard' => true,
            'status'           => 'published',
        ]);

        $sort = 0;

        // 1) MCQ
        $this->mcq($quiz, $sort++, 'Berapa hasil dari 12 x 8?', [
            ['84', false],
            ['96', true],
            ['108', false],
            ['86', false],
        ], '12 x 8 = 96. Ingat: 12 x 10 = 120, lalu kurangi 12 x 2 = 24 -> 96.');

        // 2) MCQ
        $this->mcq($quiz, $sort++, 'Bilangan prima di bawah ini adalah...', [
            ['15', false],
            ['21', false],
            ['17', true],
            ['27', false],
        ], '17 hanya habis dibagi 1 dan dirinya sendiri.');

        // 3) true_false
        $this->mcq($quiz, $sort++, 'Setiap bilangan genap habis dibagi 2.', [
            ['Benar', true],
            ['Salah', false],
        ], 'Definisi bilangan genap: dapat dibagi 2 tanpa sisa.', 'true_false');

        // 4) true_false
        $this->mcq($quiz, $sort++, 'Hasil dari (-3) + 5 adalah -8.', [
            ['Benar', false],
            ['Salah', true],
        ], '(-3) + 5 = 2, bukan -8.', 'true_false');

        // 5) short_answer
        GameQuestion::create([
            'quiz_id'       => $quiz->uuid,
            'type'          => 'short_answer',
            'question_text' => 'Berapa nilai dari 7^2 (tujuh kuadrat)?',
            'points'        => 1,
            'sort_order'    => $sort++,
            'meta'          => ['answers' => ['49']],
            'explanation'   => '7 x 7 = 49.',
        ]);

        // 6) short_answer
        GameQuestion::create([
            'quiz_id'       => $quiz->uuid,
            'type'          => 'short_answer',
            'question_text' => 'Sebutkan hasil dari 100 / 4.',
            'points'        => 1,
            'sort_order'    => $sort++,
            'meta'          => ['answers' => ['25']],
            'explanation'   => '100 dibagi 4 = 25.',
        ]);

        // 7) match
        GameQuestion::create([
            'quiz_id'       => $quiz->uuid,
            'type'          => 'match',
            'question_text' => 'Pasangkan operasi dengan hasilnya yang benar.',
            'points'        => 2,
            'sort_order'    => $sort++,
            'meta'          => [
                'pairs' => [
                    ['left' => '15 + 7', 'right' => '22'],
                    ['left' => '9 x 3', 'right' => '27'],
                    ['left' => '40 - 18', 'right' => '22'],
                    ['left' => '56 / 7', 'right' => '8'],
                ],
            ],
            'explanation'   => 'Hitung tiap operasi lalu cocokkan dengan hasilnya.',
        ]);

        GameQuizAssignment::firstOrCreate(
            ['quiz_id' => $quiz->uuid, 'classroom_id' => $classroom->uuid],
            ['status' => 'open']
        );

        $path = '/ruang-kelas/'.$classroom->class_code.'/arena-belajar/'.$quiz->uuid;
        $this->command?->info('Arena Belajar: "'.$quiz->title.'" ('.$sort.' soal) di '.$classroom->title);
        $this->command?->info('URL: '.$path);
        $this->command?->info('class_code='.$classroom->class_code.' quiz_uuid='.$quiz->uuid);
    }

    /** @param list<array{0:string,1:bool}> $options */
    private function mcq(GameQuiz $quiz, int $sort, string $text, array $options, ?string $explanation = null, string $type = 'mcq'): void
    {
        $q = GameQuestion::create([
            'quiz_id'       => $quiz->uuid,
            'type'          => $type,
            'question_text' => $text,
            'points'        => 1,
            'sort_order'    => $sort,
            'explanation'   => $explanation,
        ]);

        foreach ($options as $j => [$optText, $isCorrect]) {
            GameQuestionOption::create([
                'question_id' => $q->uuid,
                'option_text' => $optText,
                'is_correct'  => $isCorrect,
                'sort_order'  => $j,
            ]);
        }
    }
}
