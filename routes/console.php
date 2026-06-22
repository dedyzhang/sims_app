<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Terbitkan Ruang Kelas / materi / tugas terjadwal setiap menit.
Schedule::command('classroom:publish-scheduled')->everyMinute()->withoutOverlapping();
