<?php

namespace App\Policies;

use App\Models\SppPembayaran;
use App\Models\User;

/**
 * Otorisasi aksi bendahara atas baris tagihan SPP (verifikasi, OCR, revisi).
 */
class SppPembayaranPolicy
{
    public function verify(User $user, SppPembayaran $pembayaran): bool
    {
        return $user->canAccess('manage_keuangan');
    }
}
