<?php

namespace App\Jobs;

use App\Models\UserFcmToken;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;

/*
| Kirim push FCM ke semua token milik satu user via queue, supaya request user
| tidak menunggu HTTP call ke Firebase. Loop token satu-satu; token invalid /
| expired otomatis dihapus dan TIDAK menghentikan pengiriman ke token lain.
*/
class SendFcmNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $userUuid, public array $payload)
    {
    }

    public function handle(FcmService $fcm): void
    {
        if (! $fcm->enabled()) {
            return; // kredensial Firebase belum dipasang — lewati diam-diam
        }

        $tokens = UserFcmToken::where('user_uuid', $this->userUuid)->get();

        foreach ($tokens as $row) {
            try {
                $fcm->send($row->token, $this->payload);
            } catch (NotFound|InvalidArgument $e) {
                // Token tidak terdaftar / kadaluarsa / format salah → hapus baris ini.
                $row->delete();
                Log::info('FCM token invalid dihapus', [
                    'user'  => $this->userUuid,
                    'token' => substr($row->token, 0, 12).'…',
                ]);
            } catch (\Throwable $e) {
                // Kegagalan lain (jaringan/kuota) → catat saja, jangan lempar.
                Log::warning('FCM gagal kirim: '.$e->getMessage(), ['user' => $this->userUuid]);
            }
        }
    }
}
