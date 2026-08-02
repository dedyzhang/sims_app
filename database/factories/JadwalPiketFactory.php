<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\JadwalPiket;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JadwalPiket> */
class JadwalPiketFactory extends Factory
{
    protected $model = JadwalPiket::class;

    public function definition(): array
    {
        return [
            'id_guru' => Guru::inRandomOrder()->value('uuid'),
            'hari' => fake()->numberBetween(1, 5),
            'is_ketua' => false,
        ];
    }

    public function ketua(): static
    {
        return $this->state(fn () => ['is_ketua' => true]);
    }
}
