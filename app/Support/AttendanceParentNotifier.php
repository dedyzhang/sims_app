<?php

namespace App\Support;

use App\Models\Absensi;
use App\Models\Siswa;
use App\Notifications\StudentAttendanceRecorded;

class AttendanceParentNotifier
{
    public static function notify(Absensi $absensi): void
    {
        $siswa = Siswa::with(['kelas', 'orangtua.user'])->find($absensi->id_siswa);
        $parentUser = $siswa?->orangtua?->user;

        if (! $siswa || ! $parentUser) {
            return;
        }

        $parentUser->notify(new StudentAttendanceRecorded($siswa, $absensi));
    }
}
