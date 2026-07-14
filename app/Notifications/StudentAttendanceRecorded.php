<?php

namespace App\Notifications;

use App\Models\Absensi;
use App\Models\Siswa;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi untuk orang tua saat anak berhasil absen masuk via wajah atau QR/geolocation.
 */
class StudentAttendanceRecorded extends Notification
{
    use Queueable;

    public function __construct(public Siswa $siswa, public Absensi $absensi) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toFcm(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title' => $data['title'],
            'message' => $data['message'],
            'url' => '/dashboard',
            'type' => 'absensi_siswa',
            'sound' => 'notif_sims',
        ];
    }

    public function toArray(object $notifiable): array
    {
        $jam = substr((string) $this->absensi->jam_masuk, 0, 5);
        $kelas = $this->siswa->kelas
            ? trim($this->siswa->kelas->tingkat.' '.$this->siswa->kelas->kelas)
            : null;

        return [
            'type' => 'absensi_siswa',
            'title' => 'Anak sudah masuk sekolah',
            'message' => 'Ananda '.$this->siswa->nama.' sudah tercatat hadir pukul '.$jam.'.',
            'siswa_id' => $this->siswa->uuid,
            'siswa_nama' => $this->siswa->nama,
            'kelas' => $kelas,
            'tanggal' => $this->absensi->tanggal?->format('Y-m-d'),
            'jam_masuk' => $jam,
            'status' => $this->absensi->status,
        ];
    }
}
