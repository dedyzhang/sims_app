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
            'tanggal' => fake()->dateTimeBetween('-1 week', '+2 weeks')->format('Y-m-d'),
            'status' => 'aktif',
        ];
    }

    public function ditukar(): static
    {
        return $this->state(fn () => ['status' => 'ditukar']);
    }
}
