<?php

use App\Providers\AppServiceProvider;
use App\Sarpras\SarprasServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\LudensaIntegrationServiceProvider::class,
    SarprasServiceProvider::class,
];
