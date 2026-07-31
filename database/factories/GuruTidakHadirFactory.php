<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\GuruTidakHadir;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GuruTidakHadir> */
class GuruTidakHadirFactory extends Factory
{
    protected $model = GuruTidakHadir::class;

    public function definition(): array
    {
        return [
            'id_guru' => Guru::inRandomOrder()->value('uuid'),
            'tanggal' => fake()->dateTimeBetween('-1 week', 'now')->format('Y-m-d'),
            'sumber' => 'manual_piket',
            'alasan' => fake()->randomElement(['sakit', 'izin', 'dinas_luar', 'alpa']),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }

    public function otomatis(): static
    {
        return $this->state(fn () => ['sumber' => 'otomatis_presensi']);
    }
}
