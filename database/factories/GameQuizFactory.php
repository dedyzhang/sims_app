<?php

namespace Database\Factories;

use App\Models\GameQuiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GameQuiz> */
class GameQuizFactory extends Factory
{
    protected $model = GameQuiz::class;

    public function definition(): array
    {
        return [
            // classroom_id & created_by wajib di-set pemanggil
            'title'            => 'Kuis ' . fake()->words(3, true),
            'instructions'     => null,
            'mode'             => 'async',
            'scoring_mode'     => 'accuracy',
            'max_score'        => 100,
            'hide_scores'      => false,
            'show_leaderboard' => false,
            'instant_feedback' => true,
            'is_locked'        => false,
            'status'           => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published']);
    }
}
