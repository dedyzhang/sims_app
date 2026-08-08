<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Http\Controllers\Controller;
use App\Models\SppPembayaran;
use App\Exports\Keuangan\BendaharaVerifikasiPaketExport;
use App\Services\GeminiService;
use App\Services\Keuangan\BendaharaWawasanService;
use App\Services\Keuangan\SppOcrAssistService;
use App\Services\Keuangan\SppVerifikasiPaketService;
use App\Support\TahunAjaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

/**
 * Asisten operasional Bendahara SPP — route legacy redirect ke halaman Keuangan terintegrasi.
 *
 * Wawasan, ekspor paket, dan OCR tetap di route bendahara-ai.* untuk API/bookmark.
 */
class BendaharaAiController extends Controller
{
    use InteractsWithAi;

    public function __construct(
        private GeminiService $gemini,
        private SppOcrAssistService $ocr,
        private BendaharaWawasanService $wawasan,
        private SppVerifikasiPaketService $paket,
    ) {}

    /** @deprecated Terintegrasi ke Pembayaran SPP — redirect untuk bookmark/notifikasi. */
    public function index(Request $request): RedirectResponse
    {
        return redirect()
            ->route('keuangan.index', ['ta' => $this->resolveTahunAjaran($request)])
            ->with('info', 'Asisten Bendahara kini terintegrasi di halaman Pembayaran SPP.');
    }

    /** @deprecated Terintegrasi ke Verifikasi — antrian prioritas. */
    public function antrian(Request $request): RedirectResponse
    {
        return redirect()
            ->route('keuangan.verifikasi', [
                'ta'        => $this->resolveTahunAjaran($request),
                'prioritas' => 1,
                'q'         => $request->query('q'),
            ])
            ->with('info', 'Antrian prioritas kini di halaman Verifikasi.');
    }

    /** @deprecated Dashboard terintegrasi di Pembayaran SPP. */
    public function dashboard(Request $request): RedirectResponse
    {
        return redirect()
            ->route('keuangan.index', ['ta' => $this->resolveTahunAjaran($request)])
            ->with('info', 'Dashboard pendapatan SPP kini di halaman Pembayaran SPP.');
    }

    /** @deprecated Jejak audit terintegrasi di Verifikasi (tab Audit). */
    public function log(Request $request): RedirectResponse
    {
        return redirect()
            ->route('keuangan.verifikasi', [
                'ta'  => $this->resolveTahunAjaran($request),
                'tab' => 'audit',
            ])
            ->with('info', 'Jejak audit kini di halaman Verifikasi.');
    }

    /** A2 — OCR asisten bukti (saran HITL, bukan auto-post). */
    public function ocrSuggest(Request $request, SppPembayaran $pembayaran): JsonResponse
    {
        $this->authorize('verify', $pembayaran);

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

    /** @deprecated Rekonsiliasi terintegrasi di Verifikasi (tab Validasi). */
    public function rekonsiliasi(Request $request): RedirectResponse
    {
        return redirect()
            ->route('keuangan.verifikasi', [
                'ta'  => $this->resolveTahunAjaran($request),
                'tab' => 'validasi',
            ])
            ->withFragment('validasi')
            ->with('info', 'Rekonsiliasi mutasi kini di halaman Verifikasi.');
    }

    /** @deprecated Anomali terintegrasi di Verifikasi (filter anomali). */
    public function anomali(Request $request): RedirectResponse
    {
        return redirect()
            ->route('keuangan.verifikasi', [
                'ta'       => $this->resolveTahunAjaran($request),
                'filter'   => 'anomali',
                'prioritas'=> 1,
            ])
            ->with('info', 'Daftar anomali kini di halaman Verifikasi.');
    }

    /** C1 — Wawasan operasional non-nominal (rule-based + narasi AI opsional). */
    public function wawasan(Request $request): View
    {
        $ta = $this->resolveTahunAjaran($request);
        $ringkasan = $this->wawasan->ringkasan($ta);

        return view('keuangan.bendahara-ai.wawasan', [
            'ta'        => $ta,
            'taOptions' => TahunAjaran::options(),
            'ringkasan' => $ringkasan,
        ]);
    }

    /** C1 — Narasi AI dari metrik non-nominal (bukan Narasi Data pimpinan). */
    public function wawasanNarasi(Request $request): JsonResponse
    {
        if ($limited = $this->aiRateLimited('bendahara_wawasan', $request->user()->uuid)) {
            return $limited;
        }

        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
        ]);

        if (! in_array($data['tahun_ajaran'], TahunAjaran::options(), true)) {
            return response()->json(['ok' => false, 'message' => 'Tahun ajaran tidak valid.'], 422);
        }

        $metrics = $this->wawasan->ringkasan($data['tahun_ajaran']);

        if ($metrics['keterlambatan']['total_lunas'] === 0
            && array_sum($metrics['antrian']) === 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Belum ada data operasional SPP untuk dinarasikan.',
            ], 422);
        }

        if (! $this->aiConfiguredFor($request->user())) {
            return response()->json([
                'ok'      => false,
                'message' => 'Narasi AI belum dikonfigurasi. Gunakan poin wawasan aturan di atas.',
                'data'    => $metrics,
            ], 422);
        }

        $prompt = $this->wawasan->promptNarasi($metrics);
        $system = config('keuangan-ai.wawasan.prompt');

        try {
            $result = $this->gemini->generate($prompt, [
                'system'            => $system,
                'temperature'       => 0.35,
                'max_output_tokens' => 1024,
            ] + $this->personalAiOptions($request->user()));
        } catch (RuntimeException $e) {
            $this->logAiUsage($request->user()->uuid, 'bendahara_wawasan', config('ai.model'), 0, 0, 'error');

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 502);
        }

        $this->logAiUsage(
            $request->user()->uuid,
            'bendahara_wawasan',
            $result['model'],
            $result['prompt_tokens'],
            $result['completion_tokens'],
            'success',
        );

        return response()->json([
            'ok'     => true,
            'data'   => $metrics,
            'source' => $prompt,
            'answer' => $result['text'],
        ]);
    }

    /** C2 — Ekspor paket kerja verifikasi (Excel atau PDF). */
    public function exportPaket(Request $request)
    {
        $ta = $this->resolveTahunAjaran($request);
        $format = (string) $request->query('format', 'excel');
        $status = $request->query('status');
        $allowed = [
            SppPembayaran::STATUS_MENUNGGU,
            SppPembayaran::STATUS_TERVERIFIKASI,
            SppPembayaran::STATUS_LUNAS,
            SppPembayaran::STATUS_DITOLAK,
        ];

        if ($status !== null && ! in_array($status, $allowed, true)) {
            abort(422, 'Filter status tidak valid.');
        }

        $rows = $this->paket->baris($ta, $status);
        $slugTa = str_replace('/', '-', $ta);
        $suffix = $status ? "-{$status}" : '';
        $basename = "paket-verifikasi-spp-{$slugTa}{$suffix}";

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('keuangan.bendahara-ai.exports.paket-pdf', [
                'ta'     => $ta,
                'rows'   => $rows,
                'labels' => $this->paket,
                'status' => $status,
            ])->setPaper('a4', 'landscape');

            return $pdf->download("{$basename}.pdf");
        }

        return Excel::download(
            new BendaharaVerifikasiPaketExport($rows, $ta, $this->paket),
            "{$basename}.xlsx"
        );
    }

    private function resolveTahunAjaran(Request $request): string
    {
        $ta = (string) $request->query('ta', '');

        return in_array($ta, TahunAjaran::options(), true) ? $ta : TahunAjaran::current();
    }
}
