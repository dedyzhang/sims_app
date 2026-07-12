<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Models\Absensi;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Orangtua;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\UserPreference;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\LaporanKerusakan;
use App\Sarpras\Models\Peminjaman;
use App\Sarpras\Models\Pengadaan;
use App\Sarpras\Support\Rupiah;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use InteractsWithAi;
    public function index()
    {
        $user      = auth()->user();
        $semester  = Semester::aktif();
        $pref      = $user->preference()->firstOrCreate(
            ['user_uuid' => $user->uuid],
            UserPreference::defaults()
        );

        $stats = [];
        if (in_array($user->access, ['superadmin', 'admin', 'kepala'])) {
            $stats = [
                'total_siswa' => Siswa::count(),
                'total_guru'  => Guru::count(),
                'total_kelas' => Kelas::count(),
            ];
        }

        $sosmed = $this->sosmedLinks();
        $aiQuotaUsage = in_array($user->access, ['superadmin', 'admin'], true) ? $this->aiFreeTierUsage() : null;

        // ── Data ringkas Sarpras (admin/kepala/sapras saja, 4 query → 1 blok data) ──
        $sarpras = null;
        $sarprasRoles = ['superadmin', 'admin', 'kepala', 'sapras'];
        if (in_array($user->access, $sarprasRoles) && $user->can('sarpras.dashboard.lihat')) {
            $sarpras = [
                'totalAset'        => Aset::count(),
                'nilaiTotalRp'     => Rupiah::format(Aset::sum('nilai_perolehan')),
                'kerusakanTerbuka' => LaporanKerusakan::whereIn('status', ['dilaporkan', 'diterima'])->count(),
                'kerusakanDarurat' => LaporanKerusakan::whereIn('status', ['dilaporkan', 'diterima'])->whereIn('urgensi', ['tinggi', 'darurat'])->count(),
                'peminjamanAktif'  => Peminjaman::whereIn('status', ['disetujui', 'dipinjam', 'terlambat'])->count(),
                'peminjamanMenunggu' => Peminjaman::where('status', 'diajukan')->count(),
                'pengadaanPending' => Pengadaan::where('status', 'diajukan')->count(),
                'pengadaanDisetujui' => Pengadaan::where('status', 'disetujui')->count(),
            ];
        }

        $siswaWidget = match ($user->access) {
            'siswa'    => $this->buildSiswaWidget($user->siswa),
            'orangtua' => $this->buildSiswaWidget(Orangtua::where('id_login', $user->uuid)->first()?->siswa),
            default    => null,
        };

        return view('dashboard', compact('user', 'semester', 'pref', 'stats', 'sosmed', 'siswaWidget', 'sarpras', 'aiQuotaUsage'));
    }

    /** Data widget dashboard khusus siswa: jadwal hari ini, poin/P3, absensi, podium sekolah. */
    private function buildSiswaWidget(?Siswa $siswa): ?array
    {
        if (!$siswa) {
            return null;
        }

        $hariIni = (int) now()->isoWeekday(); // 1=Senin ... 7=Minggu
        $jadwals = Jadwal::with(['pelajaran', 'guru'])
            ->where('id_kelas', $siswa->id_kelas)
            ->where('hari', $hariIni)
            ->orderBy('jam_mulai')
            ->get();

        $absensiHariIni = Absensi::where('id_siswa', $siswa->uuid)
            ->whereDate('tanggal', now()->toDateString())
            ->first();

        $absensiBulan = Absensi::where('id_siswa', $siswa->uuid)
            ->whereBetween('tanggal', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->get()->keyBy(fn ($a) => $a->tanggal->format('Y-m-d'));
        $rekapAbsensi = [
            'hadir' => $absensiBulan->where('status', 'hadir')->count(),
            'izin'  => $absensiBulan->where('status', 'izin')->count(),
            'sakit' => $absensiBulan->where('status', 'sakit')->count(),
            'alpa'  => $absensiBulan->where('status', 'alpa')->count(),
        ];
        $totalTercatat = array_sum($rekapAbsensi);
        $persenHadir = $totalTercatat > 0 ? (int) round($rekapAbsensi['hadir'] / $totalTercatat * 100) : null;

        // Kalender mini bulan berjalan: setiap tanggal dipetakan ke status (atau null bila belum ada catatan).
        $awalBulan = now()->startOfMonth();
        $kalenderBulan = [];
        for ($i = 0; $i < $awalBulan->daysInMonth; $i++) {
            $tgl = $awalBulan->copy()->addDays($i);
            $rec = $absensiBulan->get($tgl->format('Y-m-d'));
            $kalenderBulan[] = [
                'tanggal'  => $tgl,
                'status'   => $rec?->status,
                'isToday'  => $tgl->isToday(),
                'isWeekend' => $tgl->isWeekend(),
                'isFuture' => $tgl->isFuture(),
            ];
        }
        $offsetAwal = $awalBulan->dayOfWeekIso - 1; // 0 = Senin, kosongkan sel sebelum tanggal 1

        // Streak hadir berturut-turut (mundur dari hari ini, akhir pekan dilewati tanpa memutus rentetan).
        $riwayat60 = Absensi::where('id_siswa', $siswa->uuid)
            ->where('tanggal', '>=', now()->subDays(60)->toDateString())
            ->get()->keyBy(fn ($a) => $a->tanggal->format('Y-m-d'));
        $streakHadir = 0;
        $cursor = now()->startOfDay();
        while (true) {
            if ($cursor->isWeekend()) {
                $cursor->subDay();
                continue;
            }
            $rec = $riwayat60->get($cursor->format('Y-m-d'));
            if (!$rec || $rec->status !== 'hadir') {
                break;
            }
            $streakHadir++;
            $cursor->subDay();
        }

        $jenisAturan = Setting::get('jenis_aturan', 'p3');
        $poin = $jenisAturan === 'poin'
            ? PoinController::hitung($siswa->uuid)
            : P3Controller::totalsFor($siswa->uuid);
        $podium = $jenisAturan === 'poin' ? PoinController::top3Sekolah() : null;

        return compact(
            'siswa', 'jadwals', 'hariIni', 'absensiHariIni', 'rekapAbsensi', 'persenHadir',
            'kalenderBulan', 'offsetAwal', 'streakHadir', 'jenisAturan', 'poin', 'podium'
        );
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
