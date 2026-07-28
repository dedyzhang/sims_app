<?php

namespace App\Services\Chatbot;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Abstraksi audit log ringan untuk chatbot. Bila spatie/laravel-activitylog
 * tersedia, gunakan itu; jika tidak, fallback ke Log facade agar tidak
 * mengikat dependency baru ke SIMS.
 */
class ActivityLogger
{
    public function log(string $event, User $causedBy, array $properties = []): void
    {
        // Audit log ini SAMPINGAN, bukan bagian inti kirim/tutup/hapus percakapan — kegagalan
        // di sini (mis. tabel activity_log belum ada krn migration paketnya belum jalan/paket
        // belum terpasang penuh) TIDAK BOLEH ikut membatalkan transaksi pemanggilnya (pesan
        // chat yg sudah berhasil disimpan jadi ikut di-rollback & user dapat error generik
        // "Gagal mengirim balasan", padahal isu sebenarnya cuma di logging-nya).
        try {
            if (function_exists('activity')) {
                activity('chatbot')
                    ->causedBy($causedBy)
                    ->withProperties($properties)
                    ->log($event);

                return;
            }

            Log::channel(config('logging.default'))->info("[chatbot] {$event}", array_merge([
                'user_id' => $causedBy->getKey(),
            ], $properties));
        } catch (Throwable $e) {
            Log::warning("[chatbot] gagal mencatat activity log: {$e->getMessage()}", [
                'event' => $event,
                'user_id' => $causedBy->getKey(),
            ]);
        }
    }
}
