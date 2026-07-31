<?php

namespace Database\Factories;

use App\Models\PenugasanPengganti;
use App\Models\TugasKelas;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TugasKelas> */
class TugasKelasFactory extends Factory
{
    protected $model = TugasKelas::class;

    public function definition(): array
    {
        return [
            'id_penugasan_pengganti' => PenugasanPengganti::inRandomOrder()->value('uuid'),
            'jenis' => 'titip_manual_piket',
            'judul' => 'Tugas '.fake()->words(2, true),
            'deskripsi' => fake()->sentence(12),
        ];
    }

    public function uploadGuruAsli(): static
    {
        return $this->state(fn () => ['jenis' => 'upload_guru_asli']);
    }
}
