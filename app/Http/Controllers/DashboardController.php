<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\UserPreference;
use Illuminate\Http\Request;

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

        $sosmed = $this->sosmedLinks();

        return view('dashboard', compact('user', 'semester', 'pref', 'stats', 'sosmed'));
    }

    /** Bangun daftar tautan media sosial sekolah yang aktif untuk dashboard. */
    private function sosmedLinks(): array
    {
        $s = Setting::pluck('value', 'key');

        if (($s['sosmed_aktif'] ?? '1') !== '1') {
            return [];
        }

        $links = [];
        foreach (config('sosmed') as $key => $meta) {
            if (($s["sosmed_{$key}_on"] ?? '0') !== '1') {
                continue;
            }
            $val = trim((string) ($s["sosmed_{$key}_url"] ?? ''));
            if ($val === '') {
                continue;
            }
            $links[$key] = [
                'label' => $meta['label'],
                'href'  => match ($meta['type']) {
                    'wa'    => 'https://wa.me/' . preg_replace('/\D/', '', $val),
                    'email' => 'mailto:' . $val,
                    default => preg_match('#^https?://#i', $val) ? $val : 'https://' . $val,
                },
            ];
        }

        return $links;
    }

    /** Simpan urutan blok dashboard hasil drag & drop. */
    public function saveLayout(Request $request)
    {
        $allowed = implode(',', UserPreference::DASHBOARD_BLOCKS);
        $data = $request->validate([
            'layout'   => ['required', 'array'],
            'layout.*' => ['string', 'in:' . $allowed],
            'hidden'   => ['nullable', 'array'],
            'hidden.*' => ['string', 'in:' . $allowed],
        ]);

        // Saring duplikat & jaga hanya blok yang dikenal, urutannya sesuai kiriman.
        $layout = array_values(array_unique($data['layout']));
        $hidden = array_values(array_unique($data['hidden'] ?? []));

        auth()->user()->preference()->updateOrCreate(
            ['user_uuid' => auth()->id()],
            ['dashboard_layout' => $layout, 'dashboard_hidden' => $hidden]
        );

        return response()->json(['success' => true, 'layout' => $layout, 'hidden' => $hidden]);
    }
}
