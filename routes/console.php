<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Terbitkan Ruang Kelas / materi / tugas terjadwal setiap menit.
Schedule::command('classroom:publish-scheduled')->everyMinute()->withoutOverlapping();

// Sarpras: pengingat jadwal pemeliharaan yang jatuh tempo (harian 07:00).
Schedule::command('sarpras:pemeliharaan-reminder')->dailyAt('07:00')->withoutOverlapping();
