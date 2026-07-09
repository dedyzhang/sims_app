<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\NilaiRapor;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\SppPembayaran;
use App\Services\GeminiService;
use App\Support\TahunAjaran;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/*
| Narasi Analisis Data (FASE 4). Controller MENGHITUNG agregat dari DB
| (server-side), lalu mengirim ANGKA-nya ke Gemini untuk dinarasikan. AI tidak
| pernah menyentuh DB — mencegah kebocoran data. Setiap endpoint mengembalikan
| `data` (angka terhitung, untuk ditampilkan & diverifikasi) + `answer` (narasi).
*/
class AiAnalyzeController extends Controller
{
    use InteractsWithAi;

    public function __construct(private GeminiService $gemini) {}

    /** GET /ai/analyze — halaman narasi data. */
    public function index(): View
    {
        return view('ai.analyze', [
            'kelasList'    => Kelas::orderBy('tingkat')->orderBy('kelas')->get(),
            'semesterList' => Semester::orderByDesc('tahun')->orderByDesc('semester')->get(),
            'tahunAjaran'  => TahunAjaran::options(),
            'taAktif'      => TahunAjaran::current(),
        ]);
    }

    /** POST /ai/analyze/nilai — narasi ringkasan nilai satu kelas. */
    public function nilai(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kelas_id'    => ['required', 'string', 'exists:kelas,uuid'],
            'semester_id' => ['required'],
        ]);

        $kelas    = Kelas::findOrFail($data['kelas_id']);
        $semester = Semester::findOrFail($data['semester_id']);
        $siswaIds = Siswa::where('id_kelas', $kelas->uuid)->pluck('uuid');

        $nilai = NilaiRapor::whereIn('id_siswa', $siswaIds)
            ->where('id_semester', $semester->id)
            ->pluck('nilai');

        if ($nilai->isEmpty()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Belum ada data nilai rapor untuk kelas & semester ini.',
            ], 422);
        }

        $bands = ['<70' => 0, '70–79' => 0, '80–89' => 0, '90–100' => 0];
        foreach ($nilai as $n) {
            if ($n < 70)      $bands['<70']++;
            elseif ($n < 80)  $bands['70–79']++;
            elseif ($n < 90)  $bands['80–89']++;
            else              $bands['90–100']++;
        }

        $perMapel = DB::table('nilai_rapor')
            ->join('ngajars', 'nilai_rapor.id_ngajar', '=', 'ngajars.uuid')
            ->join('pelajarans', 'ngajars.id_pelajaran', '=', 'pelajarans.uuid')
            ->whereIn('nilai_rapor.id_siswa', $siswaIds)
            ->where('nilai_rapor.id_semester', $semester->id)
            ->groupBy('pelajarans.nama')
            ->selectRaw('pelajarans.nama as mapel, ROUND(AVG(nilai_rapor.nilai),1) as rata')
            ->orderBy('pelajarans.nama')
            ->get();

        $metrics = [
            'kelas'        => $kelas->nama_lengkap,
            'semester'     => $semester->nama_lengkap,
            'jumlah_siswa' => $siswaIds->count(),
            'jumlah_nilai' => $nilai->count(),
            'rata'         => round($nilai->avg(), 1),
            'min'          => $nilai->min(),
            'max'          => $nilai->max(),
            'sebaran'      => $bands,
            'per_mapel'    => $perMapel->map(fn ($m) => ['mapel' => $m->mapel, 'rata' => (float) $m->rata])->all(),
        ];

        $sebaranTxt = collect($bands)->map(fn ($v, $k) => "$k: $v nilai")->implode('; ');
        $mapelTxt   = $perMapel->map(fn ($m) => "{$m->mapel} {$m->rata}")->implode('; ');

        $prompt = "{$metrics['semester']}, {$metrics['kelas']}:\n"
            ."- Jumlah siswa: {$metrics['jumlah_siswa']}\n"
            ."- Jumlah nilai terekam: {$metrics['jumlah_nilai']}\n"
            ."- Rata-rata kelas: {$metrics['rata']} (terendah {$metrics['min']}, tertinggi {$metrics['max']})\n"
            ."- Sebaran nilai: {$sebaranTxt}\n"
            ."- Rata-rata per mata pelajaran: {$mapelTxt}";

        return $this->narrate($request, 'analyze_nilai', config('ai.analyze.nilai'), $prompt, $metrics);
    }

    /** POST /ai/analyze/absensi — narasi tren kehadiran. */
    public function absensi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'string', 'exists:kelas,uuid'],
            'dari'     => ['required', 'date_format:Y-m-d'],
            'sampai'   => ['required', 'date_format:Y-m-d'],
        ]);

        // Perbandingan manual (bukan rule after_or_equal) — hindari Carbon parse
        // nama-field yang memicu warning di lingkungan tanpa tz-database.
        if ($data['sampai'] < $data['dari']) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tanggal "sampai" harus sama atau setelah "dari".',
            ], 422);
        }

        $query = Absensi::whereBetween('tanggal', [$data['dari'], $data['sampai']]);
        if (!empty($data['kelas_id'])) {
            $query->where('id_kelas', $data['kelas_id']);
        }

        $counts = (clone $query)->select('status', DB::raw('COUNT(*) as jml'))
            ->groupBy('status')->pluck('jml', 'status');
        $total = (int) $counts->sum();

        if ($total === 0) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tidak ada catatan absensi pada rentang ini.',
            ], 422);
        }

        $lingkup = !empty($data['kelas_id'])
            ? Kelas::find($data['kelas_id'])?->nama_lengkap ?? 'Kelas terpilih'
            : 'Semua kelas';

        $rincian = [];
        foreach (Absensi::STATUS as $key => $label) {
            $jml = (int) ($counts[$key] ?? 0);
            $rincian[$label] = ['jumlah' => $jml, 'persen' => round($jml / $total * 100, 1)];
        }

        $metrics = [
            'lingkup' => $lingkup,
            'dari'    => $data['dari'],
            'sampai'  => $data['sampai'],
            'total'   => $total,
            'rincian' => $rincian,
        ];

        $rincianTxt = collect($rincian)
            ->map(fn ($v, $k) => "{$k}: {$v['jumlah']} ({$v['persen']}%)")->implode('; ');

        $prompt = "Rekap kehadiran — {$lingkup}, periode {$data['dari']} s/d {$data['sampai']}:\n"
            ."- Total catatan kehadiran: {$total}\n"
            ."- Rincian: {$rincianTxt}";

        return $this->narrate($request, 'analyze_absensi', config('ai.analyze.absensi'), $prompt, $metrics);
    }

    /** POST /ai/analyze/keuangan — narasi rekap SPP satu tahun ajaran. */
    public function keuangan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tahun_ajaran' => ['required', 'string', 'max:20'],
        ]);

        $rows = SppPembayaran::where('tahun_ajaran', $data['tahun_ajaran'])
            ->select('status', DB::raw('COUNT(*) as jml'), DB::raw('SUM(nominal) as total'))
            ->groupBy('status')->get()->keyBy('status');

        $totalTagihan = (int) SppPembayaran::where('tahun_ajaran', $data['tahun_ajaran'])->sum('nominal');
        $jumlahTrx    = (int) SppPembayaran::where('tahun_ajaran', $data['tahun_ajaran'])->count();

        if ($jumlahTrx === 0) {
            return response()->json([
                'ok'      => false,
                'message' => 'Belum ada data pembayaran SPP untuk tahun ajaran ini.',
            ], 422);
        }

        $labels = [
            SppPembayaran::STATUS_LUNAS         => 'Lunas',
            SppPembayaran::STATUS_TERVERIFIKASI => 'Terverifikasi (menunggu validasi bank)',
            SppPembayaran::STATUS_MENUNGGU      => 'Menunggu verifikasi',
            SppPembayaran::STATUS_BELUM         => 'Belum bayar',
            SppPembayaran::STATUS_DITOLAK       => 'Ditolak',
        ];

        $rincian = [];
        foreach ($labels as $key => $label) {
            $rincian[$label] = [
                'jumlah' => (int) ($rows[$key]->jml ?? 0),
                'total'  => (int) ($rows[$key]->total ?? 0),
            ];
        }

        $lunasNominal = (int) ($rows[SppPembayaran::STATUS_LUNAS]->total ?? 0);
        $pelunasan    = $totalTagihan > 0 ? round($lunasNominal / $totalTagihan * 100, 1) : 0;

        $metrics = [
            'tahun_ajaran'  => $data['tahun_ajaran'],
            'total_tagihan' => $totalTagihan,
            'jumlah_trx'    => $jumlahTrx,
            'pelunasan'     => $pelunasan,
            'rincian'       => $rincian,
        ];

        $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
        $rincianTxt = collect($rincian)
            ->map(fn ($v, $k) => "{$k}: {$rp($v['total'])} ({$v['jumlah']} transaksi)")->implode('; ');

        $prompt = "Rekap SPP Tahun Ajaran {$data['tahun_ajaran']}:\n"
            ."- Total tagihan: {$rp($totalTagihan)} ({$jumlahTrx} transaksi)\n"
            ."- Rincian per status: {$rincianTxt}\n"
            ."- Tingkat pelunasan (nominal lunas / total tagihan): {$pelunasan}%";

        return $this->narrate($request, 'analyze_keuangan', config('ai.analyze.keuangan'), $prompt, $metrics);
    }

    /** Pipeline bersama: rate limit → Gemini (base+kind prompt) → audit → JSON. */
    private function narrate(Request $request, string $feature, string $kindPrompt, string $prompt, array $metrics): JsonResponse
    {
        $userId = $request->user()->uuid;

        if ($limited = $this->aiRateLimited($feature, $userId)) {
            return $limited;
        }

        $system = config('ai.analyze.base')."\n\n".$kindPrompt;

        try {
            $result = $this->gemini->generate($prompt, [
                'system'            => $system,
                'temperature'       => 0.4, // lebih faktual untuk laporan
                'max_output_tokens' => 1536,
            ]);
        } catch (RuntimeException $e) {
            $this->logAiUsage($userId, $feature, config('ai.model'), 0, 0, 'error');

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 502);
        }

        $this->logAiUsage(
            $userId,
            $feature,
            $result['model'],
            $result['prompt_tokens'],
            $result['completion_tokens'],
            'success',
        );

        return response()->json([
            'ok'     => true,
            'data'   => $metrics,
            'source' => $prompt, // angka mentah yang dikirim ke AI (untuk verifikasi)
            'answer' => $result['text'],
        ]);
    }
}
