<?php

namespace Database\Seeders;

use App\Integrations\Ludensa\LudensaSchool;
use Illuminate\Database\Seeder;
use Ludensa\Database\Seeders\LudensaSeeder;
use Ludensa\Models\School;

class SimsLudensaSeeder extends Seeder
{
    public function run(): void
    {
        LudensaSchool::ensureMapelsFromPelajaran();

        $school = School::query()->findOrFail(LudensaSchool::id());

        (new LudensaSeeder)->seedKonten($school);
    }
}
