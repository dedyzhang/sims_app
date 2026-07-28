<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Chatbot\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Bug nyata dilaporkan user: balasan admin di chat gagal terkirim ("Gagal mengirim balasan,
 * coba lagi") — ternyata bukan soal koneksi/sesi, tapi ActivityLogger::log() (audit log
 * SAMPINGAN, dipanggil di dalam DB::transaction() ChatbotService::replyAsAdmin() dkk) melempar
 * error kalau tabel activity_log belum ada (migration-nya sempat gagal krn config/activitylog.php
 * tak pernah ter-publish — lihat komentar di file config itu). Error di langkah logging itu ikut
 * membatalkan SELURUH transaksi, termasuk pesan chat yg sebenarnya sudah mau berhasil disimpan.
 * Pastikan ActivityLogger::log() tak pernah melempar error apa pun ke pemanggilnya.
 */
class ChatbotActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_tidak_melempar_error_walau_tabel_activity_log_tidak_ada(): void
    {
        $user = User::create([
            'username' => 'activity_logger_test',
            'password' => Hash::make('password'),
            'access' => 'admin',
        ]);

        // Simulasikan persis skenario bug aslinya: paket spatie/laravel-activitylog tersedia
        // (function_exists('activity') true) TAPI tabel activity_log belum ada — persis kondisi
        // saat migration-nya sempat gagal krn config/activitylog.php tak pernah ter-publish.
        Schema::dropIfExists('activity_log');

        $logger = new ActivityLogger();

        // Tak boleh melempar exception apa pun — audit log gagal (tabel hilang, dsb) tak boleh
        // menghentikan proses pemanggilnya (mis. transaksi simpan pesan chat).
        $logger->log('admin_reply_sent', $user, ['conversation_id' => 'dummy']);

        $this->assertTrue(true); // sampai baris ini tanpa exception = lolos
    }
}
