<?php

namespace App\Integrations\Arkas;

use App\Integrations\Arkas\Contracts\RkasTransport;
use App\Models\RkasPlan;

/**
 * Boundary aman untuk MVP: tidak memanggil ARKAS/MARKAS dan tidak membaca database lokal ARKAS.
 */
class ManualArkasTransport implements RkasTransport
{
    public function status(RkasPlan $plan): string
    {
        return 'manual';
    }
}
