<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\GameQuestion;
use App\Models\GameQuestionOption;
use App\Models\GameQuiz;
use App\Models\GameQuizAssignment;
use Illuminate\Database\Seeder;

/**
 * Contoh kuis PAI / Agama Islam kelas 8 untuk Arena Belajar.
 * Jalankan: php artisan db:seed --class=ArenaAgamaKelas8Seeder
 */
class ArenaAgamaKelas8Seeder extends Seeder
{
    public function run(): void
    {
        $classroom = Classroom::query()
            ->where('status', 'published')
            ->where(function ($q) {
                $q->where('title', 'like', '%Agama%')
                    ->orWhere('title', 'like', '%PAI%')
                    ->orWhere('title', 'like', '%Qur%')
                    ->orWhere('title', 'like', '%Islam%');
            })
            ->first()
            ?? Classroom::where('status', 'published')->first();

        if (! $classroom) {
            $this->command?->warn('Tidak ada classroom published — skip ArenaAgamaKelas8Seeder.');

            return;
        }

        $title = 'Kuis PAI Kelas 8 — Iman, Ibadah & Akhlak';

        $existing = GameQuiz::where('classroom_id', $classroom->uuid)
            ->where('title', $title)
            ->first();
        if ($existing) {
            $path = '/ruang-kelas/'.$classroom->class_code.'/arena-belajar/'.$existing->uuid;
            $this->command?->info('Sudah ada: "'.$title.'"');
            $this->command?->info('URL: '.$path);

            return;
        }

        $quiz = GameQuiz::create([
            'classroom_id'     => $classroom->uuid,
            'created_by'       => $classroom->created_by,
            'title'            => $title,
            'instructions'     => "Kuis contoh Pendidikan Agama Islam kelas 8.\nBaca setiap soal dengan teliti. Mode akurasi — cocok untuk latihan formatif.\nSetelah menjawab, baca pembahasan untuk memperkuat pemahaman.",
            'mode'             => 'async',
            'scoring_mode'     => 'accuracy',
            'max_score'        => 100,
            'instant_feedback' => true,
            'show_leaderboard' => true,
            'status'           => 'published',
        ]);

        $sort = 0;

        // 1) MCQ — rukun iman
        $this->mcq($quiz, $sort++, 'Rukun iman yang keenam adalah iman kepada…', [
            ['Allah SWT', false],
            ['Kitab-kitab Allah', false],
            ['Rasul-rasul Allah', false],
            ['Qada dan Qadar', true],
        ], 'Urutan rukun iman: Allah, malaikat, kitab, rasul, hari akhir, qada dan qadar.');

        // 2) MCQ — Al-Qur'an
        $this->mcq($quiz, $sort++, 'Kitab suci umat Islam yang diturunkan kepada Nabi Muhammad SAW adalah…', [
            ['Taurat', false],
            ['Zabur', false],
            ['Injil', false],
            ['Al-Qur\'an', true],
        ], 'Al-Qur\'an diturunkan kepada Nabi Muhammad SAW sebagai pedoman hidup umat Islam.');

        // 3) MCQ — shalat
        $this->mcq($quiz, $sort++, 'Shalat sunnah yang dikerjakan sebelum shalat fardu disebut…', [
            ['Shalat qabliyah', true],
            ['Shalat ba\'diyah', false],
            ['Shalat tahajud', false],
            ['Shalat istikharah', false],
        ], 'Qabliyah = sebelum; ba\'diyah = sesudah shalat fardu.');

        // 4) MCQ — zakat
        $this->mcq($quiz, $sort++, 'Zakat yang wajib dikeluarkan setiap bulan Ramadan menjelang Idul Fitri disebut…', [
            ['Zakat mal', false],
            ['Zakat fitrah', true],
            ['Zakat pertanian', false],
            ['Zakat perdagangan', false],
        ], 'Zakat fitrah membersihkan jiwa orang yang berpuasa dan membantu fakir miskin.');

        // 5) true_false
        $this->mcq($quiz, $sort++, 'Puasa Ramadan termasuk rukun Islam yang keempat.', [
            ['Benar', true],
            ['Salah', false],
        ], 'Rukun Islam: syahadat, shalat, zakat, puasa Ramadan, haji bagi yang mampu.', 'true_false');

        // 6) true_false
        $this->mcq($quiz, $sort++, 'Berbohong termasuk akhlak terpuji (mahmudah).', [
            ['Benar', false],
            ['Salah', true],
        ], 'Berbohong adalah akhlak tercela (mazmumah). Akhlak terpuji contohnya jujur dan amanah.', 'true_false');

        // 7) short_answer
        GameQuestion::create([
            'quiz_id'       => $quiz->uuid,
            'type'          => 'short_answer',
            'question_text' => 'Sebutkan jumlah rukun Islam.',
            'points'        => 1,
            'sort_order'    => $sort++,
            'meta'          => ['answers' => ['5', 'lima', 'Lima']],
            'explanation'   => 'Rukun Islam berjumlah lima.',
        ]);

        // 8) short_answer
        GameQuestion::create([
            'quiz_id'       => $quiz->uuid,
            'type'          => 'short_answer',
            'question_text' => 'Siapakah nabi dan rasul terakhir yang diutus Allah SWT?',
            'points'        => 1,
            'sort_order'    => $sort++,
            'meta'          => [
                'answers' => [
                    'Muhammad',
                    'Muhammad SAW',
                    'Nabi Muhammad',
                    'Nabi Muhammad SAW',
                    'Rasulullah',
                    'Rasulullah SAW',
                ],
            ],
            'explanation'   => 'Nabi Muhammad SAW adalah nabi dan rasul penutup (khatamun nabiyyin).',
        ]);

        // 9) match
        GameQuestion::create([
            'quiz_id'       => $quiz->uuid,
            'type'          => 'match',
            'question_text' => 'Pasangkan istilah keislaman dengan artinya yang tepat.',
            'points'        => 2,
            'sort_order'    => $sort++,
            'meta'          => [
                'pairs' => [
                    ['left' => 'Thaharah', 'right' => 'Bersuci'],
                    ['left' => 'Shadaqah', 'right' => 'Sedekah / memberi'],
                    ['left' => 'Amanah', 'right' => 'Dapat dipercaya'],
                    ['left' => 'Taqwa', 'right' => 'Takut & patuh kepada Allah'],
                ],
            ],
            'explanation'   => 'Memahami istilah dasar membantu memahami materi PAI dengan lebih baik.',
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
