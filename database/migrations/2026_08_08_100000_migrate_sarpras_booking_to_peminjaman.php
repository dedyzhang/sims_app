<?php

use App\Sarpras\Models\BookingRuangan;
use Illuminate\Support\Facades\Schema;
use App\Sarpras\Models\Peminjaman;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sarpras_booking_ruangan')) {
            return;
        }

        BookingRuangan::query()->orderBy('created_at')->each(function (BookingRuangan $booking): void {
            $status = match ($booking->status) {
                'disetujui' => 'dipinjam',
                'ditolak' => 'ditolak',
                default => 'diajukan',
            };

            $exists = Peminjaman::query()
                ->where('ruangan_id', $booking->ruangan_id)
                ->where('peminjam_id', $booking->pemohon_id)
                ->where('mulai', $booking->mulai)
                ->where('selesai', $booking->selesai)
                ->exists();

            if ($exists) {
                return;
            }

            Peminjaman::create([
                'school_id' => $booking->school_id,
                'kode' => 'PJM-MIG-' . Str::upper(Str::substr($booking->id, 0, 8)),
                'peminjam_id' => $booking->pemohon_id,
                'ruangan_id' => $booking->ruangan_id,
                'keperluan' => $booking->keperluan,
                'mulai' => $booking->mulai,
                'selesai' => $booking->selesai,
                'tgl_pinjam' => $booking->mulai?->toDateString(),
                'tgl_kembali_rencana' => $booking->selesai?->toDateString(),
                'status' => $status,
            ]);
        });
    }

    public function down(): void
    {
        Peminjaman::query()
            ->where('kode', 'like', 'PJM-MIG-%')
            ->delete();
    }
};
