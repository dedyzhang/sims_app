<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\GameAnswer;
use App\Models\GameAttempt;
use App\Models\GameLiveSession;
use App\Models\GameQuestion;
use App\Models\GameQuestionOption;
use App\Models\GameQuiz;
use App\Models\GameQuizAssignment;
use App\Models\GameTeam;
use App\Models\GameTeamMember;
use Illuminate\Database\Seeder;

/**
 * Hapus kuis demo Arena terkait, lalu seed kuis Pendidikan Agama Buddha kelas 8.
 * Jalankan: php artisan db:seed --class=ArenaBuddhaKelas8Seeder
 */
class ArenaBuddhaKelas8Seeder extends Seeder
{
    public function run(): void
    {
        $this->clearAllArenaData();

        $classroom = Classroom::query()
            ->where('status', 'published')
            ->where(function ($q) {
                $q->where('title', 'like', '%Buddha%')
                    ->orWhere('title', 'like', '%Agama%')
                    ->orWhere('title', 'like', '%Budi Pekerti%');
            })
            ->where(function ($q) {
                $q->where('title', 'like', '%8%')
                    ->orWhere('title', 'like', '%VIII%');
            })
            ->first()
            ?? Classroom::where('status', 'published')->where('title', 'like', '%Agama%')->first()
            ?? Classroom::where('status', 'published')->first();

        if (! $classroom) {
            $this->command?->warn('Tidak ada classroom published — skip ArenaBuddhaKelas8Seeder.');

            return;
        }

        $quiz = GameQuiz::create([
            'classroom_id'     => $classroom->uuid,
            'created_by'       => $classroom->created_by,
            'title'            => 'Kuis Agama Buddha Kelas 8 — Tri Ratna & Empat Kebenaran Mulia',
            'instructions'     => "Kuis contoh Pendidikan Agama Buddha kelas 8.\nBaca soal dengan saksama. Mode akurasi — cocok untuk latihan formatif.\nSetelah menjawab, baca pembahasan untuk memperkuat pemahaman Dhamma.",
            'mode'             => 'async',
            'scoring_mode'     => 'accuracy',
            'max_score'        => 100,
            'instant_feedback' => true,
            'show_leaderboard' => true,
            'status'           => 'published',
        ]);

        $sort = 0;

        // 1) MCQ — Tri Ratna
        $this->mcq($quiz, $sort++, 'Tri Ratna dalam ajaran Buddha terdiri atas…', [
            ['Buddha, Dhamma, dan Sangha', true],
            ['Sila, Samadhi, dan Panna', false],
            ['Metta, Karuna, dan Mudita', false],
            ['Karma, Vipaka, dan Nibbana', false],
        ], 'Tri Ratna (Tiga Permata): Buddha (guru), Dhamma (ajaran), dan Sangha (komunitas).');

        // 2) MCQ — Empat Kebenaran Mulia
        $this->mcq($quiz, $sort++, 'Kebenaran Mulia yang pertama (Dukkha Ariya Sacca) menyatakan bahwa…', [
            ['Hidup adalah penderitaan / ketidakpuasan', true],
            ['Semua makhluk akan mencapai Nibbana otomatis', false],
            ['Hanya orang kaya yang bisa berbuat baik', false],
            ['Karma tidak memengaruhi kehidupan', false],
        ], 'Kebenaran Mulia pertama: adanya dukkha (penderitaan/ketidakpuasan) dalam kehidupan.');

        // 3) MCQ — Jalan Mulia Berunsur Delapan
        $this->mcq($quiz, $sort++, 'Jalan untuk mengakhiri dukkha disebut…', [
            ['Pancasila Buddhis', false],
            ['Ariya Atthangika Magga (Jalan Mulia Berunsur Delapan)', true],
            ['Tri Ratna', false],
            ['Upacara Kathina', false],
        ], 'Kebenaran Mulia keempat mengajarkan Jalan Mulia Berunsur Delapan sebagai jalan pembebasan.');

        // 4) MCQ — Pancasila Buddhis
        $this->mcq($quiz, $sort++, 'Sila pertama dalam Pancasila Buddhis adalah…', [
            ['Menghindari berkata dusta', false],
            ['Menghindari membunuh makhluk hidup', true],
            ['Menghindari minum minuman keras', false],
            ['Menghindari mengambil milik orang lain', false],
        ], 'Pancasila Buddhis sila ke-1: Panatipata veramani — menghindari membunuh/menyakiti makhluk hidup.');

        // 5) true_false
        $this->mcq($quiz, $sort++, 'Nama lahir Buddha historis adalah Siddhartha Gautama.', [
            ['Benar', true],
            ['Salah', false],
        ], 'Pangeran Siddhartha Gautama kemudian menjadi Buddha setelah mencapai pencerahan.', 'true_false');

        // 6) true_false
        $this->mcq($quiz, $sort++, 'Karma berarti perbuatan; vipaka adalah akibat dari perbuatan itu.', [
            ['Benar', true],
            ['Salah', false],
        ], 'Hukum karma: setiap perbuatan (karma) membawa akibat (vipaka) sesuai kualitasnya.', 'true_false');

        // 7) short_answer
        GameQuestion::create([
            'quiz_id'       => $quiz->uuid,
            'type'          => 'short_answer',
            'question_text' => 'Berapa jumlah Kebenaran Mulia (Cattari Ariya Saccani)?',
            'points'        => 1,
            'sort_order'    => $sort++,
            'meta'          => ['answers' => ['4', 'empat', 'Empat']],
            'explanation'   => 'Ada empat Kebenaran Mulia yang diajarkan Buddha.',
        ]);

        // 8) short_answer
        GameQuestion::create([
            'quiz_id'       => $quiz->uuid,
            'type'          => 'short_answer',
            'question_text' => 'Sebutkan salah satu unsur Tri Ratna (selain Buddha dan Dhamma).',
            'points'        => 1,
            'sort_order'    => $sort++,
            'meta'          => ['answers' => ['Sangha', 'sangha', 'Komunitas Sangha', 'umat Sangha']],
            'explanation'   => 'Tri Ratna: Buddha, Dhamma, dan Sangha.',
        ]);

        // 9) match
        GameQuestion::create([
            'quiz_id'       => $quiz->uuid,
            'type'          => 'match',
            'question_text' => 'Pasangkan istilah Buddhis dengan artinya yang tepat.',
            'points'        => 2,
            'sort_order'    => $sort++,
            'meta'          => [
                'pairs' => [
                    ['left' => 'Metta', 'right' => 'Cinta kasih'],
                    ['left' => 'Karuna', 'right' => 'Belas kasih'],
                    ['left' => 'Nibbana', 'right' => 'Pembebasan dari dukkha'],
                    ['left' => 'Sila', 'right' => 'Kemoralan / disiplin etika'],
                ],
            ],
            'explanation'   => 'Metta & karuna termasuk Brahmavihara; sila adalah dasar praktik; Nibbana adalah tujuan tertinggi.',
        ]);

        GameQuizAssignment::firstOrCreate(
            ['quiz_id' => $quiz->uuid, 'classroom_id' => $classroom->uuid],
            ['status' => 'open']
        );

        $path = '/ruang-kelas/'.$classroom->class_code.'/arena-belajar/'.$quiz->uuid;
        $this->command?->info('Semua kuis Arena sebelumnya dihapus.');
        $this->command?->info('Arena Belajar: "'.$quiz->title.'" ('.$sort.' soal) di '.$classroom->title);
        $this->command?->info('URL: '.$path);
        $this->command?->info('class_code='.$classroom->class_code.' quiz_uuid='.$quiz->uuid);
    }

    private function clearAllArenaData(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('ArenaBuddhaKelas8Seeder tidak boleh menghapus data di production.');
        }

        // Hanya hapus kuis demo Arena di classroom target — bukan seluruh tabel
        $titles = [
            'Kuis PAI Kelas 8 — Iman, Ibadah & Akhlak',
            'Kuis Contoh Arena — Matematika Dasar',
            'Contoh Kuis Arena Belajar',
            'Kuis Agama Buddha Kelas 8 — Tri Ratna & Empat Kebenaran Mulia',
        ];

        $quizIds = GameQuiz::withTrashed()->whereIn('title', $titles)->pluck('uuid');
        if ($quizIds->isEmpty()) {
            return;
        }

        $questionIds = GameQuestion::whereIn('quiz_id', $quizIds)->pluck('uuid');
        $assignmentIds = GameQuizAssignment::whereIn('quiz_id', $quizIds)->pluck('uuid');
        $attemptIds = GameAttempt::whereIn('assignment_id', $assignmentIds)->pluck('uuid');
        $teamIds = GameTeam::whereIn('quiz_id', $quizIds)->pluck('uuid');

        GameAnswer::whereIn('attempt_id', $attemptIds)->delete();
        GameAttempt::whereIn('uuid', $attemptIds)->delete();
        GameTeamMember::whereIn('team_id', $teamIds)->delete();
        GameTeam::whereIn('uuid', $teamIds)->delete();
        GameLiveSession::whereIn('quiz_id', $quizIds)->delete();
        GameQuestionOption::whereIn('question_id', $questionIds)->delete();
        GameQuestion::whereIn('uuid', $questionIds)->delete();
        GameQuizAssignment::whereIn('uuid', $assignmentIds)->delete();
        GameQuiz::withTrashed()->whereIn('uuid', $quizIds)->forceDelete();
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
