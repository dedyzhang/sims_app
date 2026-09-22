<?php

namespace App\Listeners;

use Illuminate\Notifications\Events\NotificationSent;
use App\Services\FirebaseRtdbService;

class PushFirebaseNotificationListener
{
    /**
     * Create the event listener.
     */
    public function __construct(private FirebaseRtdbService $firebase)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(NotificationSent $event): void
    {
        // $event->notifiable is usually a User model
        if ($event->notifiable instanceof \App\Models\User) {
            $this->firebase->pingUser($event->notifiable->uuid);
        }
    }
}
