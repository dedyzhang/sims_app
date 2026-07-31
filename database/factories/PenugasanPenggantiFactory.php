<?php

namespace Database\Factories;

use App\Models\GuruTidakHadir;
use App\Models\PenugasanPengganti;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PenugasanPengganti> */
class PenugasanPenggantiFactory extends Factory
{
    protected $model = PenugasanPengganti::class;

    public function definition(): array
    {
        return [
            'id_guru_tidak_hadir' => GuruTidakHadir::inRandomOrder()->value('uuid'),
            'status' => 'menunggu',
        ];
    }

    public function ditugaskan(): static
    {
        return $this->state(fn () => ['status' => 'ditugaskan']);
    }

    public function selesai(): static
    {
        return $this->state(fn () => ['status' => 'selesai']);
    }
}
