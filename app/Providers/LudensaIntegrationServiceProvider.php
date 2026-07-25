<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class LudensaIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/ludensa.php'), 'ludensa');
    }

    public function boot(): void
    {
        //
    }
}
