<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\BookingRuangan;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\JadwalPemeliharaan;
use App\Sarpras\Models\LaporanKerusakan;
use App\Sarpras\Models\Peminjaman;
use App\Sarpras\Models\Perbaikan;
use App\Sarpras\Models\Pengadaan;
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

        // Nilai buku dihitung per-baris di PHP (logika penyusutan di model Aset),
        // hanya memuat 3 kolom yang dibutuhkan.
        $nilaiBuku = '0';
        foreach (Aset::query()->get(['nilai_perolehan', 'tgl_perolehan', 'masa_manfaat_tahun']) as $aset) {
            $nilaiBuku = Rupiah::add($nilaiBuku, $aset->nilaiBuku($today));
        }

        $kerusakanTerbukaQuery = LaporanKerusakan::query()
            ->whereIn('status', ['dilaporkan', 'diterima']);

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

        $peminjamanMenunggu = Peminjaman::where('status', 'diajukan')->count();
        $bookingMenunggu = (clone $bookingMenungguQuery)->count();
        $pengadaanPending = (clone $pengadaanPendingQuery)->count();
        $perbaikanBerjalan = (clone $perbaikanBerjalanQuery)->count();
        $jadwalJatuhTempo = (clone $jadwalJatuhTempoQuery)->count();
        $kerusakanTerbuka = (clone $kerusakanTerbukaQuery)->count();

        $data = [
            'totalAset' => Aset::count(),
            'asetPerKondisi' => $asetPerKondisi,
            'nilaiBukuRp' => Rupiah::format($nilaiBuku),
            'kerusakanTerbuka' => $kerusakanTerbuka,
            'kerusakanDarurat' => (clone $kerusakanTerbukaQuery)->whereIn('urgensi', ['tinggi', 'darurat'])->count(),
            'persetujuanMenunggu' => $peminjamanMenunggu + $bookingMenunggu + $pengadaanPending,
            'peminjamanMenunggu' => $peminjamanMenunggu,
            'bookingMenunggu' => $bookingMenunggu,
            'pengadaanPending' => $pengadaanPending,
            'perbaikanBerjalan' => $perbaikanBerjalan,
            'jadwalJatuhTempo' => $jadwalJatuhTempo,
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
                    'label' => 'Peminjaman Barang menunggu approval',
                    'count' => $peminjamanMenunggu,
                    'icon' => 'clipboard-check',
                    'tone' => 'blue',
                    'url' => route('sarpras.peminjaman.index', ['status' => 'diajukan']),
                ],
                [
                    'label' => 'Booking Ruangan menunggu approval',
                    'count' => $bookingMenunggu,
                    'icon' => 'calendar-clock',
                    'tone' => 'cyan',
                    'url' => route('sarpras.booking.index', ['status' => 'diajukan']),
                ],
                [
                    'label' => 'Pengadaan perlu diputuskan',
                    'count' => $pengadaanPending,
                    'icon' => 'shopping-cart',
                    'tone' => 'amber',
                    'url' => route('sarpras.pengadaan.index', ['status' => 'diajukan']),
                ],
                [
                    'label' => 'Pemeliharaan jatuh tempo',
                    'count' => $jadwalJatuhTempo,
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
            'denahPeta' => Denah::withCount('ruangan')
                ->orderBy('gedung')
                ->orderBy('lantai')
                ->orderBy('nama')
                ->limit(8)
                ->get(['id', 'nama', 'gedung', 'lantai', 'gambar_path']),
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
