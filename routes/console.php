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

// Grup Chat: rekonsiliasi keanggotaan (01:00). WAJIB — siswa.id_kelas bisa berubah
// lewat impor/SQL mentah yang tak memanggil GrupChatService, dan tanpa rekonsiliasi
// ex-siswa akan terus membaca grup kelas lamanya.
Schedule::command('grupchat:sinkron')->dailyAt('01:00')->withoutOverlapping();

// Grup Chat: digest notifikasi tiap 15 menit — bukan real-time per pesan (lihat
// catatan di GrupChatMessenger::kirim() soal FCM_QUEUE_CONNECTION=sync).
Schedule::command('grupchat:kirim-notif')->everyFifteenMinutes()->withoutOverlapping();

// Langganan: sinkronkan status tersimpan setelah tanggal berakhir terlewati.
Schedule::call(static fn () => \App\Models\Langganan::sinkronkanStatusKadaluarsa())
    ->dailyAt('00:05')
    ->name('langganan.sinkronkan-status')
    ->withoutOverlapping();
