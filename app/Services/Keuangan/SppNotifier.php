<?php

namespace App\Services\Keuangan;

use App\Models\Orangtua;
use App\Models\Siswa;
use App\Models\SppPembayaran;
use App\Models\User;
use App\Notifications\SppBuktiDiunggahNotification;
use App\Notifications\SppPembayaranDiperbaruiNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Pengiriman notifikasi in-app + FCM untuk alur pembayaran SPP.
 */
class SppNotifier
{
    public static function buktiDiunggah(Siswa $siswa, int $jumlahBulan, string $batchId): void
    {
        if ($jumlahBulan < 1) {
            return;
        }

        $siswa->loadMissing('kelas');

        foreach (self::bendaharaUsers() as $user) {
            $user->notify(new SppBuktiDiunggahNotification($siswa, $jumlahBulan, $batchId));
        }
    }

    /**
     * @param  Collection<int, SppPembayaran>  $pembayaran
     */
    public static function statusDiperbarui(Collection $pembayaran, string $event): void
    {
        if ($pembayaran->isEmpty() || ! in_array($event, ['terverifikasi', 'lunas', 'ditolak'], true)) {
            return;
        }

        $pembayaran->each(fn (SppPembayaran $p) => $p->loadMissing(
            'siswa.kelas',
            'siswa.orangtua.user',
            'siswa.user',
        ));

        foreach ($pembayaran->groupBy('id_siswa') as $group) {
            /** @var SppPembayaran $first */
            $first = $group->first();
            $siswa = $first->siswa;

            if (! $siswa) {
                continue;
            }

            $catatan = $event === 'ditolak' ? ($first->catatan ?? null) : null;
            $recipients = self::recipientsForSiswa($siswa);

            if ($recipients->isEmpty()) {
                Log::warning('SPP notifikasi: siswa belum punya akun orang tua/siswa terhubung', [
                    'siswa_id' => $siswa->uuid,
                    'siswa_nama' => $siswa->nama,
                    'event' => $event,
                ]);

                continue;
            }

            foreach ($recipients as $user) {
                $user->notify(new SppPembayaranDiperbaruiNotification(
                    $siswa,
                    $group->values(),
                    $event,
                    $catatan,
                ));
            }
        }
    }

    public static function userIsRecipient(User $user, Siswa $siswa): bool
    {
        if ($siswa->id_login && (string) $user->getKey() === (string) $siswa->id_login) {
            return true;
        }

        if ($user->access !== 'orangtua') {
            return false;
        }

        return Orangtua::query()
            ->where('id_login', $user->getKey())
            ->where('id_siswa', $siswa->uuid)
            ->exists();
    }

    /** @return Collection<int, User> */
    private static function recipientsForSiswa(Siswa $siswa): Collection
    {
        $users = collect();

        $parentUser = $siswa->orangtua?->user;
        if ($parentUser) {
            $users->push($parentUser);
        }

        if ($siswa->user) {
            $users->push($siswa->user);
        }

        return $users->unique('uuid')->values();
    }

    /** @return Collection<int, User> */
    private static function bendaharaUsers(): Collection
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
}
