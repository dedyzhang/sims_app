<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\UserPreference;

class DashboardController extends Controller
{
    public function index()
    {
        $user      = auth()->user();
        $semester  = Semester::aktif();
        $pref      = $user->preference()->firstOrCreate(
            ['user_uuid' => $user->uuid],
            UserPreference::defaults()
        );

        $stats = [];
        if (in_array($user->access, ['superadmin', 'admin', 'kurikulum', 'kesiswaan', 'kepala'])) {
            $stats = [
                'total_siswa' => Siswa::count(),
                'total_guru'  => Guru::count(),
                'total_kelas' => Kelas::count(),
            ];
        }

        return view('dashboard', compact('user', 'semester', 'pref', 'stats'));
    }
}
