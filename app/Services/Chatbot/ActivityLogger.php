<?php

namespace App\Services\Chatbot;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Abstraksi audit log ringan untuk chatbot. Bila spatie/laravel-activitylog
 * tersedia, gunakan itu; jika tidak, fallback ke Log facade agar tidak
 * mengikat dependency baru ke SIMS.
 */
class ActivityLogger
{
    public function log(string $event, User $causedBy, array $properties = []): void
    {
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
    }
}
