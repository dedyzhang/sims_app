<?php

namespace App\Services\Keuangan;

use App\Models\SppPembayaran;

/**
 * Activity log transisi keuangan SPP (A5) via Spatie activitylog.
 */
class SppActivityLogger
{
    public const LOG_NAME = 'keuangan_spp';

    public static function logStatusChange(
        SppPembayaran $pembayaran,
        string $event,
        ?string $statusSebelum,
        ?string $statusSesudah,
        ?string $actorUuid = null,
    ): void {
        activity(self::LOG_NAME)
            ->causedBy($actorUuid ? \App\Models\User::find($actorUuid) : auth()->user())
            ->performedOn($pembayaran)
            ->event($event)
            ->withProperties([
                'pembayaran_uuid' => $pembayaran->uuid,
                'id_siswa'        => $pembayaran->id_siswa,
                'bulan'           => $pembayaran->bulan,
                'tahun_ajaran'    => $pembayaran->tahun_ajaran,
                'status_sebelum'  => $statusSebelum,
                'status_sesudah'  => $statusSesudah,
                'nominal'         => $pembayaran->nominal,
            ])
            ->log(self::eventLabel($event));
    }

    public static function logImport(string $bank, int $berhasil, int $dilewati, ?string $actorUuid = null): void
    {
        activity(self::LOG_NAME)
            ->causedBy($actorUuid ? \App\Models\User::find($actorUuid) : auth()->user())
            ->event('mutasi_rekening_diimpor')
            ->withProperties([
                'bank'     => $bank,
                'berhasil' => $berhasil,
                'dilewati' => $dilewati,
            ])
            ->log("Impor rekening koran {$bank}: {$berhasil} berhasil, {$dilewati} dilewati");
    }

    private static function eventLabel(string $event): string
    {
        return match ($event) {
            'spp_verifikasi_diajukan'  => 'Bukti pembayaran diajukan (menunggu)',
            'spp_verifikasi_disetujui' => 'Bukti diverifikasi (terverifikasi)',
            'spp_verifikasi_ditolak'   => 'Bukti ditolak',
            'spp_validasi_lunas'       => 'Pembayaran divalidasi lunas',
            'spp_status_diubah'        => 'Status pembayaran diubah',
            default                    => $event,
        };
    }
}
