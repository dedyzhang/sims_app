<?php

namespace App\Integrations\Arkas\Contracts;

use App\Models\RkasPlan;

interface RkasTransport
{
    public function status(RkasPlan $plan): string;
}
