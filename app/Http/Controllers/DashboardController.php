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
use App\Support\UserRole;
use App\Services\Piket\PiketSyncService;
use App\Support\ModulAktif;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use InteractsWithAi;

    public function __construct(private PiketSyncService $piketSync) {}

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
        $aiQuotaUsage = in_array($user->access, ['superadmin', 'admin'], true) ? $this->aiPublicQuotaUsage() : null;

        // ── Data ringkas Sarpras (admin/kepala/sapras saja, 4 query → 1 blok data) ──
        $sarpras = null;
        $sarprasRoles = ['superadmin', 'admin', 'kepala', 'sarpras'];
        if (UserRole::matches((string) $user->access, ...$sarprasRoles) && $user->can('sarpras.dashboard.lihat')) {
            $sarpras = [
                'totalAset'        => Aset::count(),
                'nilaiTotalRp'     => Rupiah::format(Aset::sum('nilai_perolehan')),
                'kerusakanTerbuka' => LaporanKerusakan::whereIn('status', ['dilaporkan', 'diterima'])->count(),
                'kerusakanDarurat' => LaporanKerusakan::whereIn('status', ['dilaporkan', 'diterima'])->whereIn('urgensi', ['tinggi', 'darurat'])->count(),
                'peminjamanAktif'  => Peminjaman::whereIn('status', ['dipinjam', 'terlambat'])->count(),
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

        // ── Data Piket (Bila user adalah guru piket hari ini) ──
        $piketGuruTidakHadir = collect();
        if (
            ModulAktif::aktif('piket')
            && $user->guru
            && \App\Models\JadwalPiket::isPiketAktif($user->guru->uuid)
        ) {
            $tanggalPiket = now()->toDateString();
            $this->piketSync->syncHariIni($tanggalPiket);

            $piketGuruTidakHadir = \App\Models\GuruTidakHadir::with([
                'guru:uuid,nama',
                'penugasanPengganti.jadwal.pelajaran',
                'penugasanPengganti.jadwal.kelas',
                'penugasanPengganti.tugasKelas',
                'penugasanPengganti.guruPengganti:uuid,nama'
            ])
            ->whereDate('tanggal', now()->toDateString())
            ->get();
        }

        return view('dashboard', compact('user', 'semester', 'pref', 'stats', 'sosmed', 'siswaWidget', 'sarpras', 'aiQuotaUsage', 'piketGuruTidakHadir'));
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

        // $absensiBulan (bulan berjalan) & $riwayat60 (60 hari terakhir, dipakai utk streak di
        // bawah) rentangnya tumpang-tindih — dulu 2 query terpisah menarik data yg sama 2x. Kini
        // 1 query rentang gabungan [min(awalBulan, 60hariLalu) .. akhirBulan], lalu di-filter jadi
        // dua view di memori. Pakai $now->copy() konsisten utk hindari mutasi objek Carbon.
        $now = now();
        $awalBulan2 = $now->copy()->startOfMonth();
        $akhirBulan2 = $now->copy()->endOfMonth();
        $batas60 = $now->copy()->subDays(60)->startOfDay();
        $awalGabungan = $batas60->lt($awalBulan2) ? $batas60 : $awalBulan2;

        $absensiGabungan = Absensi::where('id_siswa', $siswa->uuid)
            ->whereBetween('tanggal', [$awalGabungan->toDateString(), $akhirBulan2->toDateString()])
            ->get()->keyBy(fn ($a) => $a->tanggal->format('Y-m-d'));

        $absensiBulan = $absensiGabungan->filter(fn ($a) => $a->tanggal->betweenIncluded($awalBulan2, $akhirBulan2));
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

        // Streak hadir berturut-turut (mundur dari hari ini, akhir pekan dilewati tanpa memutus
        // rentetan). $riwayat60 diturunkan dari $absensiGabungan yg sudah ditarik di atas — tak
        // query ulang. Streak cuma jalan mundur dari hari ini, jadi batas atas akhirBulan aman.
        $riwayat60 = $absensiGabungan->filter(fn ($a) => $a->tanggal->gte($batas60));
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
