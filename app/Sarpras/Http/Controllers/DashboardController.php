<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\BookingRuangan;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\JadwalPemeliharaan;
use App\Sarpras\Models\LaporanKerusakan;
use App\Sarpras\Models\Peminjaman;
use App\Sarpras\Models\Perbaikan;
use App\Sarpras\Models\Pengadaan;
use App\Sarpras\Models\Penghapusan;
use App\Sarpras\Support\Rupiah;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = Carbon::today();

        $asetPerKondisi = Aset::query()
            ->selectRaw('kondisi, count(*) as jml')
            ->groupBy('kondisi')
            ->pluck('jml', 'kondisi');

        $asetPerStatus = Aset::query()
            ->selectRaw('status, count(*) as jml')
            ->groupBy('status')
            ->pluck('jml', 'status');

        $asetPerKategori = Aset::query()
            ->with('kategori:id,nama')
            ->selectRaw('kategori_id, count(*) as jml')
            ->groupBy('kategori_id')
            ->orderByDesc('jml')
            ->limit(8)
            ->get();

        $ruanganPerStatus = DenahRuangan::query()
            ->selectRaw('status, count(*) as jml')
            ->groupBy('status')
            ->pluck('jml', 'status');

        // Nilai total & biaya perbaikan: jumlahkan di SQL (1 query, tanpa load semua row).
        $nilaiTotal = (string) (Aset::sum('nilai_perolehan') ?: 0);

        // Nilai buku tetap dihitung per-baris di PHP (logika penyusutan ada di model Aset),
        // tapi hanya memuat 3 kolom yang dibutuhkan — bukan seluruh model terhidrasi.
        $nilaiBuku = '0';
        foreach (Aset::query()->get(['nilai_perolehan', 'tgl_perolehan', 'masa_manfaat_tahun']) as $aset) {
            $nilaiBuku = Rupiah::add($nilaiBuku, $aset->nilaiBuku($today));
        }

        $biayaPerbaikanBulanIni = (string) (Perbaikan::query()
            ->where('status', 'selesai')
            ->whereMonth('tgl_selesai', $today->month)
            ->whereYear('tgl_selesai', $today->year)
            ->sum('biaya') ?: 0);

        $kerusakanTerbukaQuery = LaporanKerusakan::query()
            ->whereIn('status', ['dilaporkan', 'diterima']);

        $peminjamanAktifQuery = Peminjaman::query()
            ->whereIn('status', ['dipinjam', 'terlambat']);

        $perbaikanBerjalanQuery = Perbaikan::query()
            ->whereIn('status', ['antri', 'dikerjakan']);

        $jadwalJatuhTempoQuery = JadwalPemeliharaan::query()
            ->where('aktif', true)
            ->whereNotNull('tgl_berikutnya')
            ->whereDate('tgl_berikutnya', '<=', $today);

        $pengadaanPendingQuery = Pengadaan::query()
            ->where('status', 'diajukan');

        $bookingMenungguQuery = BookingRuangan::query()
            ->where('status', 'diajukan')
            ->where('mulai', '>=', $today->copy()->startOfDay());

        $data = [
            'totalAset' => Aset::count(),
            'asetPerKondisi' => $asetPerKondisi,
            'asetPerStatus' => $asetPerStatus,
            'asetPerKategori' => $asetPerKategori,
            'ruanganPerStatus' => $ruanganPerStatus,
            'nilaiTotal' => $nilaiTotal,
            'nilaiTotalRp' => Rupiah::format($nilaiTotal),
            'nilaiBukuRp' => Rupiah::format($nilaiBuku),
            'biayaPerbaikanBulanIniRp' => Rupiah::format($biayaPerbaikanBulanIni),
            'kerusakanTerbuka' => (clone $kerusakanTerbukaQuery)->count(),
            'kerusakanDarurat' => (clone $kerusakanTerbukaQuery)->whereIn('urgensi', ['tinggi', 'darurat'])->count(),
            'peminjamanAktif' => (clone $peminjamanAktifQuery)->count(),
            'peminjamanMenunggu' => Peminjaman::where('status', 'diajukan')->count(),
            'pengadaanPending' => (clone $pengadaanPendingQuery)->count(),
            'pengadaanDisetujui' => Pengadaan::where('status', 'disetujui')->count(),
            'bookingMenunggu' => (clone $bookingMenungguQuery)->count(),
            'perbaikanBerjalan' => (clone $perbaikanBerjalanQuery)->count(),
            'jadwalJatuhTempo' => (clone $jadwalJatuhTempoQuery)->count(),
            'penghapusanPending' => Penghapusan::where('status', 'diajukan')->count(),
            'asetTanpaLokasi' => Aset::whereNull('ruangan_id')->count(),
            'asetBerisiko' => Aset::whereIn('kondisi', ['rusak_ringan', 'rusak_berat', 'hilang'])->count(),
            'kerusakanTerbaru' => LaporanKerusakan::with(['pelapor:uuid,username', 'aset:id,nama'])
                ->latest()->limit(5)->get(),
            'bookingHariIni' => BookingRuangan::with(['ruangan:id,nama,kode', 'pemohon'])
                ->whereIn('status', ['diajukan', 'disetujui'])
                ->whereDate('mulai', $today)
                ->orderBy('mulai')
                ->get(),
            'antreanKerja' => [
                [
                    'label' => 'Laporan baru perlu diterima',
                    'count' => LaporanKerusakan::where('status', 'dilaporkan')->count(),
                    'icon' => 'siren',
                    'tone' => 'rose',
                    'url' => route('sarpras.kerusakan.index', ['status' => 'dilaporkan']),
                ],
                [
                    'label' => 'Peminjaman menunggu approval',
                    'count' => Peminjaman::where('status', 'diajukan')->count(),
                    'icon' => 'clipboard-check',
                    'tone' => 'blue',
                    'url' => route('sarpras.peminjaman.index', ['status' => 'diajukan']),
                ],
                [
                    'label' => 'Booking ruangan menunggu approval',
                    'count' => (clone $bookingMenungguQuery)->count(),
                    'icon' => 'calendar-clock',
                    'tone' => 'cyan',
                    'url' => route('sarpras.booking.index', ['status' => 'diajukan']),
                ],
                [
                    'label' => 'Pengadaan perlu diputuskan',
                    'count' => (clone $pengadaanPendingQuery)->count(),
                    'icon' => 'shopping-cart',
                    'tone' => 'amber',
                    'url' => route('sarpras.pengadaan.index', ['status' => 'diajukan']),
                ],
                [
                    'label' => 'Pemeliharaan jatuh tempo',
                    'count' => (clone $jadwalJatuhTempoQuery)->count(),
                    'icon' => 'calendar-clock',
                    'tone' => 'emerald',
                    'url' => route('sarpras.perbaikan.index'),
                ],
            ],
            'asetPerluTindakan' => Aset::with(['kategori:id,nama', 'ruangan:id,nama,kode'])
                ->where(function ($query) {
                    $query->whereIn('kondisi', ['rusak_ringan', 'rusak_berat', 'hilang'])
                        ->orWhereIn('status', ['perbaikan', 'dipinjam']);
                })
                ->latest()
                ->limit(6)
                ->get(['id', 'kode', 'nama', 'kategori_id', 'ruangan_id', 'kondisi', 'status']),
            'jadwalMendatang' => JadwalPemeliharaan::with('aset:id,kode,nama')
                ->where('aktif', true)
                ->whereNotNull('tgl_berikutnya')
                ->whereDate('tgl_berikutnya', '<=', $today->copy()->addDays(14))
                ->orderBy('tgl_berikutnya')
                ->limit(6)
                ->get(),
            'pengadaanTerbaru' => Pengadaan::with('pengaju:uuid,username')
                ->whereIn('status', ['diajukan', 'disetujui'])
                ->latest()
                ->limit(5)
                ->get(['id', 'kode', 'judul', 'status', 'total_estimasi', 'diajukan_oleh', 'created_at']),
            // Mini-map denah: ambil denah dengan gambar, group per gedung, hitung kerusakan terbuka per denah.
            'denahPeta' => Denah::withCount('ruangan')
                ->orderBy('gedung')
                ->orderBy('lantai')
                ->orderBy('nama')
                ->limit(8)
                ->get(['id', 'nama', 'gedung', 'lantai', 'gambar_path']),
            // Jumlah kerusakan terbuka (status dilaporkan/diterima) per denah_id, via relasi ruangan.
            'kerusakanPerDenah' => LaporanKerusakan::query()
                ->whereIn('sarpras_laporan_kerusakan.status', ['dilaporkan', 'diterima'])
                ->join('sarpras_denah_ruangan', 'sarpras_laporan_kerusakan.ruangan_id', '=', 'sarpras_denah_ruangan.id')
                ->selectRaw('sarpras_denah_ruangan.denah_id, count(*) as jml')
                ->groupBy('sarpras_denah_ruangan.denah_id')
                ->pluck('jml', 'denah_id'),
        ];

        return view('sarpras.dashboard', $data);
    }
}
