<?php

namespace App\Services\Keuangan;

use App\Models\SppPembayaran;
use App\Models\User;
use App\Notifications\BendaharaAntrianDigestNotification;
use App\Support\TahunAjaran;
use Illuminate\Support\Collection;

/**
 * Ringkasan antrian menumpuk & pengiriman notifikasi digest (Fase B3).
 */
class BendaharaAntrianDigest
{
    public function ringkasan(?string $tahunAjaran = null): array
    {
        $ta = $tahunAjaran ?? TahunAjaran::current();
        $cfg = config('keuangan-ai.digest', []);

        $menunggu = SppPembayaran::where('tahun_ajaran', $ta)
            ->where('status', SppPembayaran::STATUS_MENUNGGU)
            ->count();

        $terverifikasi = SppPembayaran::where('tahun_ajaran', $ta)
            ->where('status', SppPembayaran::STATUS_TERVERIFIKASI)
            ->count();

        $usiaMin = (int) ($cfg['usia_hari_min'] ?? 3);
        $menungguLama = SppPembayaran::where('tahun_ajaran', $ta)
            ->where('status', SppPembayaran::STATUS_MENUNGGU)
            ->where('updated_at', '<', now()->subDays($usiaMin))
            ->count();

        $ambang = (int) ($cfg['menunggu_min'] ?? 10);
        $menumpuk = $menunggu >= $ambang || $menungguLama > 0;

        return [
            'tahun_ajaran'   => $ta,
            'menunggu'       => $menunggu,
            'terverifikasi'  => $terverifikasi,
            'menunggu_lama'  => $menungguLama,
            'ambang'         => $ambang,
            'menumpuk'       => $menumpuk,
        ];
    }

    public function kirimDigest(): int
    {
        $ringkasan = $this->ringkasan();
        if (! $ringkasan['menumpuk']) {
            return 0;
        }

        $terkirim = 0;
        foreach ($this->bendaharaUsers() as $user) {
            if ($this->sudahDinotifikasiBaru($user)) {
                continue;
            }
            $user->notify(new BendaharaAntrianDigestNotification($ringkasan));
            $terkirim++;
        }

        return $terkirim;
    }

    /** @return Collection<int, User> */
    private function bendaharaUsers(): Collection
    {
        return User::query()
            ->where(function ($q) {
                $q->where('access', 'bendahara')
                    ->orWhere('access', 'admin')
                    ->orWhere('access', 'superadmin');
            })
            ->get()
            ->filter(fn (User $u) => $u->canAccess('manage_keuangan'));
    }

    private function sudahDinotifikasiBaru(User $user): bool
    {
        $jam = (int) config('keuangan-ai.digest.jeda_jam', 6);

        return $user->notifications()
            ->where('type', BendaharaAntrianDigestNotification::class)
            ->where('created_at', '>=', now()->subHours($jam))
            ->exists();
    }
}
