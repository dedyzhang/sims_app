<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Terbitkan Ruang Kelas / materi / tugas terjadwal setiap menit.
Schedule::command('classroom:publish-scheduled')->everyMinute()->withoutOverlapping();

// Latihan Arena Belajar: sesi & data tamu sekali-pakai, tak ada nilai retensi jangka panjang.
Schedule::command('latihan:bersihkan-sesi')->dailyAt('02:00')->withoutOverlapping();

// Sarpras: pengingat jadwal pemeliharaan yang jatuh tempo (harian 07:00).
Schedule::command('sarpras:pemeliharaan-reminder')->dailyAt('07:00')->withoutOverlapping();

// Piket: pengingat H-1 ke guru yang dijadwalkan piket besok (harian 15:00 — akhir jam sekolah).
Schedule::command('piket:h1-reminder')->dailyAt('15:00')->withoutOverlapping();

// Jadwal mengajar: digest pagi berisi seluruh sesi guru hari ini (harian 06:00, sebelum jam masuk).
Schedule::command('jadwal:digest-harian')->dailyAt('06:00')->withoutOverlapping();

// Jadwal mengajar: pengingat ~10 menit sebelum tiap sesi (tiap 5 menit — lihat catatan window di command).
Schedule::command('jadwal:sesi-reminder')->everyFiveMinutes()->withoutOverlapping();

// Jadwal mengajar: pengingat isi agenda tepat setelah tiap sesi selesai (tiap 5 menit — lihat window di command).
Schedule::command('jadwal:agenda-reminder')->everyFiveMinutes()->withoutOverlapping();

// Grup Chat: rekonsiliasi keanggotaan (01:00). WAJIB — siswa.id_kelas bisa berubah
// lewat impor/SQL mentah yang tak memanggil GrupChatService, dan tanpa rekonsiliasi
// ex-siswa akan terus membaca grup kelas lamanya.
Schedule::command('grupchat:sinkron')->dailyAt('01:00')->withoutOverlapping();

// Grup Chat: digest notifikasi tiap 15 menit — bukan real-time per pesan (lihat
// catatan di GrupChatMessenger::kirim() soal FCM_QUEUE_CONNECTION=sync).
Schedule::command('grupchat:kirim-notif')->everyFifteenMinutes()->withoutOverlapping();

// Ujian: finalisasi paksa attempt yg lewat deadline tapi belum dikumpulkan (jaring
// pengaman server-side — auto-submit normal ditangani client-side JS). everyMinute()
// krn deadline ujian batas keras, bukan pengingat.
Schedule::command('ujian:auto-submit')->everyMinute()->withoutOverlapping();

// Langganan: sinkronkan status tersimpan setelah tanggal berakhir terlewati.
Schedule::call(static fn () => \App\Models\Langganan::sinkronkanStatusKadaluarsa())
    ->dailyAt('00:05')
    ->name('langganan.sinkronkan-status')
    ->withoutOverlapping();
