<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\JadwalPemeliharaan;
use App\Sarpras\Models\LaporanKerusakan;
use App\Sarpras\Models\Peminjaman;
use App\Sarpras\Models\Perbaikan;
use App\Sarpras\Models\Pengadaan;
use App\Sarpras\Support\Rupiah;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

        $nilaiBuku = '0';
        foreach (Aset::query()->select(['nilai_perolehan', 'tgl_perolehan', 'masa_manfaat_tahun'])->cursor() as $aset) {
            $nilaiBuku = Rupiah::add($nilaiBuku, $aset->nilaiBuku($today));
        }

        // Satu agregat untuk semua angka kerusakan terbuka (total, darurat, perlu diterima).
        $kerusakanTerbukaRekap = LaporanKerusakan::query()
            ->whereIn('status', ['dilaporkan', 'diterima'])
            ->selectRaw('status, urgensi, count(*) as jml')
            ->groupBy('status', 'urgensi')
            ->get();

        $perbaikanBerjalanQuery = Perbaikan::query()
            ->whereIn('status', ['antri', 'dikerjakan']);

        $jadwalJatuhTempoQuery = JadwalPemeliharaan::query()
            ->where('aktif', true)
            ->whereNotNull('tgl_berikutnya')
            ->whereDate('tgl_berikutnya', '<=', $today);

        $pengadaanPendingQuery = Pengadaan::query()
            ->where('status', 'diajukan');

        $peminjamanMenunggu = Peminjaman::where('status', 'diajukan')->count();
        $pengadaanPending = (clone $pengadaanPendingQuery)->count();
        $perbaikanBerjalan = (clone $perbaikanBerjalanQuery)->count();
        $jadwalJatuhTempo = (clone $jadwalJatuhTempoQuery)->count();
        $kerusakanTerbuka = (int) $kerusakanTerbukaRekap->sum('jml');

        // Ruangan dipakai booking & aset perlu tindakan: muat sekali untuk keduanya.
        $bookingHariIni = Peminjaman::with(['peminjam:uuid,username', 'peminjam.guru:uuid,id_login,nama', 'peminjam.siswa:uuid,id_login,nama'])
            ->whereNotNull('ruangan_id')
            ->whereIn('status', ['diajukan', 'dipinjam'])
            ->whereDate('mulai', $today)
            ->orderBy('mulai')
            ->get();
        $asetPerluTindakan = Aset::with('kategori:id,nama')
            ->where(function ($query) {
                $query->whereIn('kondisi', ['rusak_ringan', 'rusak_berat', 'hilang'])
                    ->orWhereIn('status', ['perbaikan', 'dipinjam']);
            })
            ->latest()
            ->limit(6)
            ->get(['id', 'kode', 'nama', 'kategori_id', 'ruangan_id', 'kondisi', 'status']);
        $this->muatRuanganBersama($bookingHariIni, $asetPerluTindakan);

        $data = [
            'totalAset' => (int) $asetPerKondisi->sum(),
            'asetPerKondisi' => $asetPerKondisi,
            'nilaiBukuRp' => Rupiah::format($nilaiBuku),
            'kerusakanTerbuka' => $kerusakanTerbuka,
            'kerusakanDarurat' => (int) $kerusakanTerbukaRekap->whereIn('urgensi', ['tinggi', 'darurat'])->sum('jml'),
            'persetujuanMenunggu' => $peminjamanMenunggu + $pengadaanPending,
            'peminjamanMenunggu' => $peminjamanMenunggu,
            'bookingMenunggu' => 0,
            'pengadaanPending' => $pengadaanPending,
            'perbaikanBerjalan' => $perbaikanBerjalan,
            'jadwalJatuhTempo' => $jadwalJatuhTempo,
            'asetBerisiko' => (int) $asetPerKondisi->only(['rusak_ringan', 'rusak_berat', 'hilang'])->sum(),
            'kerusakanTerbaru' => LaporanKerusakan::with('aset:id,nama')
                ->latest()->limit(5)->get(),
            'bookingHariIni' => $bookingHariIni,
            'antreanKerja' => [
                [
                    'label' => 'Laporan baru perlu diterima',
                    'count' => (int) $kerusakanTerbukaRekap->where('status', 'dilaporkan')->sum('jml'),
                    'icon' => 'siren',
                    'tone' => 'rose',
                    'url' => route('sarpras.kerusakan.index', ['status' => 'dilaporkan']),
                ],
                [
                    'label' => 'Peminjaman menunggu approval',
                    'count' => $peminjamanMenunggu,
                    'icon' => 'clipboard-check',
                    'tone' => 'blue',
                    'url' => route('sarpras.peminjaman.index', ['status' => 'diajukan']),
                ],
                [
                    'label' => 'Usulan kebutuhan perlu diputuskan',
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
            'asetPerluTindakan' => $asetPerluTindakan,
            'jadwalMendatang' => JadwalPemeliharaan::with('aset:id,kode,nama')
                ->where('aktif', true)
                ->whereNotNull('tgl_berikutnya')
                ->whereDate('tgl_berikutnya', '<=', $today->copy()->addDays(14))
                ->orderBy('tgl_berikutnya')
                ->limit(6)
                ->get(),
            'pengadaanTerbaru' => Pengadaan::query()
                ->whereIn('status', ['diajukan', 'disetujui'])
                ->latest()
                ->limit(5)
                ->get(['id', 'kode', 'judul', 'status', 'total_estimasi', 'created_at']),
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

    /** Pasang relasi `ruangan` ke beberapa koleksi dengan satu query DenahRuangan. */
    private function muatRuanganBersama(Collection ...$koleksi): void
    {
        $ids = collect($koleksi)->flatMap->pluck('ruangan_id')->filter()->unique()->values();
        $ruangan = $ids->isEmpty()
            ? collect()
            : DenahRuangan::whereIn('id', $ids)->get(['id', 'nama', 'kode'])->keyBy('id');

        foreach ($koleksi as $items) {
            foreach ($items as $item) {
                $item->setRelation('ruangan', $ruangan->get($item->ruangan_id));
            }
        }
    }
}
