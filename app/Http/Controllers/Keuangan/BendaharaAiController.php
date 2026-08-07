<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Http\Controllers\Controller;
use App\Models\SppPembayaran;
use App\Services\Keuangan\SppActivityLogger;
use App\Services\Keuangan\SppAnomalyDetector;
use App\Services\Keuangan\SppMonthlyDashboard;
use App\Services\Keuangan\SppMutasiMatchingService;
use App\Services\Keuangan\SppOcrAssistService;
use App\Services\Keuangan\SppVerificationQueue;
use App\Services\Keuangan\BendaharaAntrianDigest;
use App\Support\TahunAjaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/**
 * Asisten operasional Bendahara SPP (Fase A) — terpisah dari AiAnalyzeController pimpinan.
 *
 * A1 antrian prioritas · A2 OCR saran · A3 dashboard SPP · A5 jejak audit.
 * B1 rekonsiliasi matching · B2 anomali · B3 digest antrian.
 */
class BendaharaAiController extends Controller
{
    use InteractsWithAi;

    public function __construct(
        private SppVerificationQueue $queue,
        private SppMonthlyDashboard $dashboard,
        private SppOcrAssistService $ocr,
        private SppMutasiMatchingService $matching,
        private SppAnomalyDetector $anomaly,
        private BendaharaAntrianDigest $digest,
    ) {}

    /** Hub asisten bendahara. */
    public function index(Request $request): View
    {
        $ta = $this->resolveTahunAjaran($request);
        $ringkasanAntrian = $this->digest->ringkasan($ta);
        $anomaliCount = $this->anomaly->scan($ta)->count();

        return view('keuangan.bendahara-ai.index', [
            'ta'               => $ta,
            'taOptions'        => TahunAjaran::options(),
            'ringkasanAntrian' => $ringkasanAntrian,
            'anomaliCount'     => $anomaliCount,
        ]);
    }

    /** A1 — Antrian prioritas verifikasi. */
    public function antrian(Request $request): View
    {
        $ta = $this->resolveTahunAjaran($request);
        $q  = trim((string) $request->query('q', ''));

        $groups = $this->queue->prioritizedGroups($ta, $q !== '' ? $q : null);
        $anomaliMap = $this->anomaly->scan($ta)->keyBy(fn ($row) => $row['pembayaran']->uuid);

        $menunggu = $groups->filter(fn ($g) => $g->first()['pembayaran']->status === SppPembayaran::STATUS_MENUNGGU);
        $terverifikasi = $groups->filter(fn ($g) => $g->first()['pembayaran']->status === SppPembayaran::STATUS_TERVERIFIKASI);

        return view('keuangan.bendahara-ai.antrian', [
            'menungguGroups'      => $menunggu,
            'terverifikasiGroups' => $terverifikasi,
            'menungguCount'       => $menunggu->sum(fn ($g) => $g->count()),
            'terverifikasiCount'  => $terverifikasi->sum(fn ($g) => $g->count()),
            'anomaliMap'          => $anomaliMap,
            'q'                   => $q,
            'ta'                  => $ta,
            'taOptions'           => TahunAjaran::options(),
        ]);
    }

    /** A3 — Dashboard pendapatan SPP bulanan. */
    public function dashboard(Request $request): View
    {
        $ta = $this->resolveTahunAjaran($request);
        $ringkasan = $this->dashboard->ringkasanTahun($ta);

        $tahun = (int) $request->query('tahun', now()->year);
        $bulan = (int) $request->query('bulan', now()->month);
        $bulanIni = $this->dashboard->ringkasanBulanKalender($tahun, $bulan);

        return view('keuangan.bendahara-ai.dashboard', [
            'ta'         => $ta,
            'taOptions'  => TahunAjaran::options(),
            'ringkasan'  => $ringkasan,
            'bulanIni'   => $bulanIni,
            'filterTahun'=> $tahun,
            'filterBulan'=> $bulan,
        ]);
    }

    /** A5 — Jejak audit transisi keuangan. */
    public function log(Request $request): View
    {
        $logs = Activity::inLog(SppActivityLogger::LOG_NAME)
            ->latest()
            ->paginate(30);

        return view('keuangan.bendahara-ai.log', [
            'logs' => $logs,
            'ta'   => $this->resolveTahunAjaran($request),
        ]);
    }

    /** A2 — OCR asisten bukti (saran HITL, bukan auto-post). */
    public function ocrSuggest(Request $request, SppPembayaran $pembayaran): JsonResponse
    {
        if ($limited = $this->aiRateLimited('bendahara_ocr', $request->user()->uuid)) {
            return $limited;
        }

        $request->validate([
            'bukti' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], [
            'bukti.mimes' => 'File harus berupa gambar (JPG/PNG/WebP) atau PDF.',
        ]);

        if (! $this->aiConfiguredFor($request->user())) {
            return response()->json([
                'ok'      => false,
                'message' => 'OCR belum dikonfigurasi. Isi manual atau minta admin mengaktifkan AI.',
                'saran'   => null,
            ], 422);
        }

        try {
            $saran = $this->ocr->suggest($pembayaran, $request->file('bukti'), $request->user()->uuid);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Gagal membaca bukti: '.$e->getMessage().' — silakan isi manual.',
                'saran'   => null,
            ], 422);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Saran OCR — periksa dan konfirmasi sebelum menyimpan.',
            'saran'   => $saran,
        ]);
    }

    /** B1 — Rekonsiliasi: tagihan terverifikasi menunggu validasi bank. */
    public function rekonsiliasi(Request $request): View
    {
        $ta = $this->resolveTahunAjaran($request);
        $antrian = $this->matching->antrianValidasiBank($ta);

        return view('keuangan.bendahara-ai.rekonsiliasi', [
            'ta'        => $ta,
            'taOptions' => TahunAjaran::options(),
            'antrian'   => $antrian,
        ]);
    }

    /** B2 — Daftar anomali / flag peringatan. */
    public function anomali(Request $request): View
    {
        $ta = $this->resolveTahunAjaran($request);
        $items = $this->anomaly->scan($ta);

        return view('keuangan.bendahara-ai.anomali', [
            'ta'        => $ta,
            'taOptions' => TahunAjaran::options(),
            'items'     => $items,
        ]);
    }

    private function resolveTahunAjaran(Request $request): string
    {
        $ta = (string) $request->query('ta', '');

        return in_array($ta, TahunAjaran::options(), true) ? $ta : TahunAjaran::current();
    }
}
