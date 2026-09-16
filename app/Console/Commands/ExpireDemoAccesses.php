<?php

namespace App\Console\Commands;

use App\Models\DemoAccess;
use App\Services\DemoCallbackClient;
use Illuminate\Console\Command;

class ExpireDemoAccesses extends Command
{
    protected $signature = 'demo:expire-accesses';

    protected $description = 'Tandai akses demo yang kedaluwarsa dan tutup sesinya';

    public function handle(DemoCallbackClient $callbacks): int
    {
        $expired = DemoAccess::query()
            ->where('status', DemoAccess::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())
            ->get();

        $notified = 0;
        foreach ($expired as $access) {
            if ($access->markExpiredAndInvalidateSessions()) {
                $callbacks->notify($access->fresh() ?? $access, 'access.expired');
                $notified++;
            }
        }

        $this->info($notified.' akses demo kedaluwarsa.');

        return self::SUCCESS;
    }
}
