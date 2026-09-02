<?php

namespace App\Http\Controllers;

use App\Models\UjianSesi;
use App\Models\UjianRuangan;
use App\Models\UjianBeritaAcara;
use App\Models\UjianPaket;
use App\Models\Guru;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Support\TanggalIndo; 

class UjianRekapController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                if ($request->user() && !($request->user()->isAdmin() || $request->user()->canAccess('manage_ujian'))) {
                    abort(403, 'Akses ditolak.');
                }
                return $next($request);
            }),
        ];
    }

    /**
     * Sesi "yatim" (jadwalnya sudah dihapus admin) TANPA data apa pun (Berita Acara/Daftar
     * Hadir) sama sekali tak pernah "terjadi" apa-apa di situ — tak ada gunanya muncul jadi
     * baris hantu "AD-HOC/Tanpa Jadwal" di rekap. Sesi yatim yg SUDAH terlanjur py BA/daftar
     * hadir TETAP dipertahankan (data historis asli, jangan hilang dari laporan). Relasi
     * jadwal/beritaAcara/daftarHadir WAJIB sudah di-eager-load oleh caller sebelum filter
     * ini dipakai di Collection, supaya tak N+1.
     */
    private function sesiPunyaData(UjianSesi $sesi): bool
    {
        return $sesi->jadwal->isNotEmpty() || $sesi->beritaAcara->isNotEmpty() || $sesi->daftarHadir->isNotEmpty();
    }

    public function index(Request $request)
    {
        $tanggal = $request->input('tanggal') ? Carbon::parse($request->input('tanggal')) : now();
        $tanggalString = $tanggal->toDateString();
        $paketId = $request->input('paket_id');
        
        $paketList = UjianPaket::orderBy('created_at', 'desc')->get();
        if (!$paketId && $paketList->isNotEmpty()) {
            $paketId = $paketList->first()->uuid;
        }

        // Ambil semua sesi (termasuk adhoc yang dibuat tanpa jadwal) pada tanggal tersebut
        $sesiQuery = UjianSesi::with(['paket', 'jadwal.ujian.pelajaran', 'beritaAcara', 'daftarHadir'])
            ->whereDate('tanggal', $tanggalString);

        if ($paketId) {
            $sesiQuery->where('id_ujian_paket', $paketId);
        }
        // Sesi yatim (jadwal dihapus) yg tak py data apa pun dibuang di sini — lihat sesiPunyaData().
        $sesiList = $sesiQuery->get()->filter(fn ($s) => $this->sesiPunyaData($s))->values();

        // Kumpulkan paket ID dari sesi-sesi tersebut
        $paketIds = $sesiList->pluck('id_ujian_paket')->filter()->unique();
        if ($paketId) {
            $paketIds = collect([$paketId]);
        }

        // Ambil semua ruangan dari paket-paket tersebut
        $ruanganList = UjianRuangan::with(['paket', 'peserta'])
            ->whereIn('id_ujian_paket', $paketIds)
            ->get();
            
        // Ambil semua berita acara yang diisi pada hari itu
        $baQuery = UjianBeritaAcara::with(['pengawas', 'ujianList.pelajaran', 'sesi.jadwal'])
            ->whereDate('tanggal', $tanggalString);
            
        $beritaAcaraSemua = $baQuery->get();
        
        // Filter BA yang ruangannya masuk di paket terpilih
        $ruanganIds = $ruanganList->pluck('uuid');
        $beritaAcaraList = $beritaAcaraSemua->whereIn('id_ruangan', $ruanganIds);

        // Bangun matriks rekap
        $rekap = [];
        foreach ($ruanganList as $ruangan) {
            $sesiUntukRuangan = $sesiList->where('id_ujian_paket', $ruangan->id_ujian_paket);
            $baUntukRuangan = $beritaAcaraList->where('id_ruangan', $ruangan->uuid);
            
            $agendas = [];
            
            // Loop semua sesi yang seharusnya ada untuk ruangan ini
            foreach ($sesiUntukRuangan as $sesi) {
                $ba = $baUntukRuangan->where('id_sesi', $sesi->uuid)->first();
                $agendas[] = [
                    'tipe' => $sesi->jadwal->isEmpty() ? 'adhoc' : 'terjadwal',
                    'sesi' => $sesi,
                    'berita_acara' => $ba,
                ];
            }
            
            // Tambahkan BA Adhoc yang mungkin ID sesinya null (legacy) atau tidak terkait langsung dengan Sesi yang muncul di kueri
            $baAdhocLainnya = $baUntukRuangan->whereNotIn('id_sesi', $sesiUntukRuangan->pluck('uuid')->toArray());
            foreach ($baAdhocLainnya as $ba) {
                $agendas[] = [
                    'tipe' => 'adhoc',
                    'sesi' => null,
                    'berita_acara' => $ba,
                ];
            }
            
            // Urutkan agenda berdasarkan jam mulai
            usort($agendas, function($a, $b) {
                $jamA = $a['sesi'] ? $a['sesi']->jam_mulai : ($a['berita_acara'] ? $a['berita_acara']->jam_mulai_aktual : '99:99');
                $jamB = $b['sesi'] ? $b['sesi']->jam_mulai : ($b['berita_acara'] ? $b['berita_acara']->jam_mulai_aktual : '99:99');
                return strcmp($jamA ?? '99:99', $jamB ?? '99:99');
            });
            
            $rekap[$ruangan->uuid] = [
                'ruangan' => $ruangan,
                'agendas' => $agendas,
            ];
        }

        // Urutkan ruangan berdasarkan nama
        uasort($rekap, fn($a, $b) => strcmp($a['ruangan']->nama, $b['ruangan']->nama));

        return view('ujian.rekap.index', compact('rekap', 'tanggal', 'tanggalString', 'paketList', 'paketId'));
    }

    public function cetak(Request $request)
    {
        $tanggal = $request->input('tanggal') ? Carbon::parse($request->input('tanggal')) : now();
        $tanggalString = $tanggal->toDateString();
        $paketId = $request->input('paket_id');

        $sesiQuery = UjianSesi::with(['paket', 'jadwal.ujian.pelajaran', 'beritaAcara', 'daftarHadir'])
            ->whereDate('tanggal', $tanggalString);

        if ($paketId) {
            $sesiQuery->where('id_ujian_paket', $paketId);
        }
        $sesiList = $sesiQuery->get()->filter(fn ($s) => $this->sesiPunyaData($s))->values();

        $paketIds = $sesiList->pluck('id_ujian_paket')->filter()->unique();
        if ($paketId) {
            $paketIds = collect([$paketId]);
        }

        $ruanganList = UjianRuangan::with(['paket', 'peserta'])
            ->whereIn('id_ujian_paket', $paketIds)
            ->get();

        $baSemua = UjianBeritaAcara::with(['pengawas', 'ujianList.pelajaran', 'sesi.jadwal'])
            ->whereDate('tanggal', $tanggalString)
            ->get();

        $beritaAcaraList = $baSemua->whereIn('id_ruangan', $ruanganList->pluck('uuid'));

        $rekap = [];
        foreach ($ruanganList as $ruangan) {
            $sesiUntukRuangan = $sesiList->where('id_ujian_paket', $ruangan->id_ujian_paket);
            $baUntukRuangan = $beritaAcaraList->where('id_ruangan', $ruangan->uuid);

            $agendas = [];

            foreach ($sesiUntukRuangan as $sesi) {
                $ba = $baUntukRuangan->where('id_sesi', $sesi->uuid)->first();
                $agendas[] = [
                    'tipe' => $sesi->jadwal->isEmpty() ? 'adhoc' : 'terjadwal',
                    'sesi' => $sesi,
                    'berita_acara' => $ba,
                ];
            }
            
            $baAdhocLainnya = $baUntukRuangan->whereNotIn('id_sesi', $sesiUntukRuangan->pluck('uuid')->toArray());
            foreach ($baAdhocLainnya as $ba) {
                $agendas[] = [
                    'tipe' => 'adhoc',
                    'sesi' => null,
                    'berita_acara' => $ba,
                ];
            }
            
            usort($agendas, function($a, $b) {
                $jamA = $a['sesi'] ? $a['sesi']->jam_mulai : ($a['berita_acara'] ? $a['berita_acara']->jam_mulai_aktual : '99:99');
                $jamB = $b['sesi'] ? $b['sesi']->jam_mulai : ($b['berita_acara'] ? $b['berita_acara']->jam_mulai_aktual : '99:99');
                return strcmp($jamA ?? '99:99', $jamB ?? '99:99');
            });
            
            $rekap[$ruangan->uuid] = [
                'ruangan' => $ruangan,
                'agendas' => $agendas,
            ];
        }

        uasort($rekap, fn($a, $b) => strcmp($a['ruangan']->nama, $b['ruangan']->nama));

        $sekolah = (object) [
            'nama' => \App\Models\Setting::get('nama_sekolah', 'SEKOLAH KITA'),
            'alamat' => \App\Models\Setting::get('alamat_sekolah', '')
        ];

        $pdf = Pdf::loadView('ujian.rekap.cetak', [
            'rekap' => $rekap,
            'tanggal' => $tanggal,
            'tanggalString' => $tanggalString,
            'sekolah' => $sekolah,
            'paketTeks' => $paketId ? UjianPaket::find($paketId)?->nama : null,
        ]);
        
        return $pdf->setPaper('a4', 'landscape')->stream('Rekap-Berita-Acara-'.$tanggalString.'.pdf');
    }

    private function kopData(): array
    {
        $kepsek = Guru::whereHas('user', fn ($q) => $q->where('access', 'kepala'))->first();

        return [
            'namaSekolah'   => Setting::get('nama_sekolah', ''),
            'alamatSekolah' => Setting::get('alamat_sekolah', ''),
            'kopTeks'       => Setting::get('kop_teks'),
            'kopLogoKiri'   => $this->kopImgDataUri('kop_logo_kiri', 'img/tutwuri.png'),
            'kopLogoKanan'  => $this->kopImgDataUri('kop_logo_kanan', 'img/maitreyawira_square.png'),
            'kepsekNama'    => $kepsek?->nama ?? Setting::get('kepala_sekolah', ''),
        ];
    }

    private function kopImgDataUri(string $key, string $default): ?string
    {
        $v = Setting::get($key);
        if ($v && Storage::disk('public')->exists($v)) {
            return $this->fileToDataUri(Storage::disk('public')->path($v));
        }
        if (file_exists(public_path($default))) {
            return $this->fileToDataUri(public_path($default));
        }
        return null;
    }

    private function fileToDataUri(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $mime = @mime_content_type($path) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }
    
    private function jumlahPesertaSeharusnya(\App\Models\UjianRuangan $ruangan, UjianSesi $sesi): int
    {
        $idKelasSesiIni = \App\Models\UjianKelas::whereIn('id_ujian', $sesi->jadwal->pluck('id_ujian'))->pluck('id_kelas');
        return $ruangan->peserta()->with('siswa')->get()
            ->filter(fn ($p) => $idKelasSesiIni->contains($p->siswa?->id_kelas))
            ->count();
    }
    
    private function jumlahPesertaSeharusnyaAdhoc(\App\Models\UjianRuangan $ruangan, UjianBeritaAcara $ba): int
    {
        $idKelasBaIni = \App\Models\UjianKelas::whereIn('id_ujian', $ba->ujianList->pluck('uuid'))->pluck('id_kelas');
        return $ruangan->peserta()->with('siswa')->get()
            ->filter(fn ($p) => $idKelasBaIni->contains($p->siswa?->id_kelas))
            ->count();
    }

    public function cetakBulkBa(Request $request)
    {
        $tanggal = $request->input('tanggal') ? Carbon::parse($request->input('tanggal')) : now();
        $tanggalString = $tanggal->toDateString();
        $paketId = $request->input('paket_id');
        $paketAktif = $paketId ? UjianPaket::find($paketId) : null;

        $baQuery = UjianBeritaAcara::with(['ujianList.pelajaran', 'pengawas', 'sesi.jadwal', 'ruangan.paket.semester'])
            ->whereDate('tanggal', $tanggalString);
            
        if ($paketId) {
            $baQuery->whereHas('ruangan', function($q) use ($paketId) {
                $q->where('id_ujian_paket', $paketId);
            });
        }
            
        $beritaAcaraList = $baQuery->get();
            
        $pdfData = [];
        foreach ($beritaAcaraList as $ba) {
            $pdfData[] = [
                'ruangan' => $ba->ruangan,
                'paket' => $paketAktif ?? $ba->ruangan->paket,
                'beritaAcara' => $ba,
                'sesi' => $ba->sesi,
                'jumlahSeharusnya' => $ba->sesi ? $this->jumlahPesertaSeharusnya($ba->ruangan, $ba->sesi) : $this->jumlahPesertaSeharusnyaAdhoc($ba->ruangan, $ba),
            ];
        }

        if (empty($pdfData)) {
            return back()->with('error', 'Tidak ada Berita Acara yang ditemukan pada tanggal ' . ($paketAktif ? 'dan Paket ' : '') . 'tersebut.');
        }

        $pdf = Pdf::loadView('ujian.rekap.cetakBulkBa', [
            'pdfData' => $pdfData,
        ] + $this->kopData());
        
        return $pdf->setPaper('a4', 'portrait')->stream('Bulk-Berita-Acara-'.$tanggalString.'.pdf');
    }
    
    public function cetakBulkDh(Request $request)
    {
        $tanggal = $request->input('tanggal') ? Carbon::parse($request->input('tanggal')) : now();
        $tanggalString = $tanggal->toDateString();
        $paketId = $request->input('paket_id');
        $paketAktif = $paketId ? UjianPaket::find($paketId) : null;

        $sesiQuery = UjianSesi::with(['paket.semester', 'jadwal.ujian.pelajaran', 'beritaAcara', 'daftarHadir'])
            ->whereDate('tanggal', $tanggalString);

        if ($paketId) {
            $sesiQuery->where('id_ujian_paket', $paketId);
        }

        $sesiList = $sesiQuery->get()->filter(fn ($s) => $this->sesiPunyaData($s))->values();

        $paketIds = $sesiList->pluck('id_ujian_paket')->filter()->unique();
        if ($paketId) {
            $paketIds = collect([$paketId]);
        }
        
        $ruanganList = UjianRuangan::with(['paket.semester', 'peserta.siswa.kelas'])
            ->whereIn('id_ujian_paket', $paketIds)
            ->get();
            
        // Also get adhoc BAs
        $baAdhocQuery = UjianBeritaAcara::with(['ruangan.paket.semester', 'ujianList.pelajaran', 'pengawas'])
            ->whereDate('tanggal', $tanggalString)
            ->where(function($q) use ($sesiList) {
                if ($sesiList->isEmpty()) {
                    $q->whereNull('id_sesi')->orWhereNotNull('id_sesi');
                } else {
                    $q->whereNull('id_sesi')->orWhereNotIn('id_sesi', $sesiList->pluck('uuid'));
                }
            });
            
        if ($paketId) {
            $baAdhocQuery->whereHas('ruangan', function($q) use ($paketId) {
                $q->where('id_ujian_paket', $paketId);
            });
        }
        $baAdhoc = $baAdhocQuery->get();
            
        $pdfData = [];
        foreach ($ruanganList as $ruangan) {
            $sesiUntukRuangan = $sesiList->where('id_ujian_paket', $ruangan->id_ujian_paket);
            foreach ($sesiUntukRuangan as $sesi) {
                $hadirBySiswa = \App\Models\UjianDaftarHadir::where('id_ruangan', $ruangan->uuid)
                    ->whereDate('tanggal', $tanggalString)->get()->keyBy('id_siswa');
                $pdfData[] = [
                    'ruangan' => $ruangan,
                    'paket' => $paketAktif ?? $ruangan->paket,
                    'sesi' => $sesi,
                    'tanggal' => $tanggalString,
                    'hadirBySiswa' => $hadirBySiswa,
                    'isAdhoc' => false,
                    'beritaAcara' => null,
                ];
            }
        }
        
        foreach ($baAdhoc as $ba) {
            $hadirBySiswa = \App\Models\UjianDaftarHadir::where('id_ruangan', $ba->id_ruangan)
                ->whereDate('tanggal', $tanggalString)->get()->keyBy('id_siswa');
            $pdfData[] = [
                'ruangan' => $ba->ruangan,
                'paket' => $paketAktif ?? $ba->ruangan->paket,
                'sesi' => null,
                'tanggal' => $tanggalString,
                'hadirBySiswa' => $hadirBySiswa,
                'isAdhoc' => true,
                'beritaAcara' => $ba,
            ];
        }

        if (empty($pdfData)) {
            return back()->with('error', 'Tidak ada agenda/sesi yang ditemukan pada tanggal ' . ($paketAktif ? 'dan Paket ' : '') . 'tersebut.');
        }

        $pdf = Pdf::loadView('ujian.rekap.cetakBulkDh', [
            'pdfData' => $pdfData,
        ] + $this->kopData());
        
        return $pdf->setPaper('a4', 'portrait')->stream('Bulk-Daftar-Hadir-'.$tanggalString.'.pdf');
    }
}
