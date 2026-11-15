<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianJawaban;
use App\Policies\UjianPolicy;
use App\Services\UjianGrader;
use Illuminate\Http\Request;

/** Penilaian manual jawaban esai — satu-satunya tipe soal yang tak bisa auto-grade. */
class UjianGradingController extends Controller
{
    public function index(Request $request, Ujian $ujian)
    {
        $this->authorize('gradeEssay', $ujian);

        // Dibatasi ke kelas yg user ini jadi PENGAMPU-nya saja (bukan semua kelas ujian) —
        // supaya guru lain yg cuma sama2 mengajar mapel ini tak ikut lihat/nilai esai kelas
        // rekan mengajarnya. Admin/manage_ujian/pembuat ujian tetap lihat semua kelas.
        $ujianKelasList = $ujian->kelas()->with('kelas', 'guruPengampu')->get()
            ->filter(fn ($uk) => app(UjianPolicy::class)->mengampuiKelas($request->user(), $uk))
            ->sortBy(fn ($uk) => [$uk->kelas?->tingkat, $uk->kelas?->kelas])
            ->values();

        $ujianKelasIds = $ujianKelasList->pluck('uuid');
        $kelasFilter = $request->string('kelas')->toString();

        // Jika hanya ada 1 kelas yang bisa dinilai user, otomatis langsung buka kelas tersebut
        if (! $kelasFilter && $ujianKelasList->count() === 1) {
            $kelasFilter = $ujianKelasList->first()->id_kelas;
        }

        // TAMPILAN 1: PILIH KELAS (Jika multi-kelas dan belum ada kelas yang dipilih)
        // Hitung ringkasan cepat per-kelas tanpa meload seluruh siswa ke memori sekaligus
        if (! $kelasFilter && $ujianKelasList->count() > 1) {
            $attemptCounts = UjianAttempt::whereIn('id_ujian_kelas', $ujianKelasIds)
                ->where('status', UjianAttempt::STATUS_SUBMITTED)
                ->where('butuh_penilaian_manual', true)
                ->selectRaw('id_ujian_kelas, count(*) as total')
                ->groupBy('id_ujian_kelas')
                ->pluck('total', 'id_ujian_kelas');

            $selesaiCounts = UjianAttempt::whereIn('id_ujian_kelas', $ujianKelasIds)
                ->where('status', UjianAttempt::STATUS_DINILAI)
                ->selectRaw('id_ujian_kelas, count(*) as total')
                ->groupBy('id_ujian_kelas')
                ->pluck('total', 'id_ujian_kelas');

            $kelasSummary = $ujianKelasList->map(function ($uk) use ($attemptCounts, $selesaiCounts) {
                return [
                    'ujianKelas'      => $uk,
                    'kelas'           => $uk->kelas,
                    'butuh_dinilai'   => $attemptCounts->get($uk->uuid, 0),
                    'selesai_dinilai' => $selesaiCounts->get($uk->uuid, 0),
                ];
            });

            $attempts = collect();
            $siswaByLogin = collect();

            return view('ujian.grading.index', compact('ujian', 'ujianKelasList', 'kelasSummary', 'kelasFilter', 'attempts', 'siswaByLogin'));
        }

        // TAMPILAN 2: DAFTAR SISWA KELAS TERPILIH
        $targetKelasIds = $kelasFilter
            ? $ujianKelasList->where('id_kelas', $kelasFilter)->pluck('uuid')
            : $ujianKelasIds;

        $attempts = UjianAttempt::whereIn('id_ujian_kelas', $targetKelasIds)
            ->where('status', UjianAttempt::STATUS_SUBMITTED)
            ->where('butuh_penilaian_manual', true)
            ->with('ujianKelas.kelas')
            ->orderBy('selesai_pada', 'desc')
            ->get();

        // FIX N+1 Query: Eager load relasi kelas pada profil siswa
        $siswaByLogin = Siswa::with('kelas')
            ->whereIn('id_login', $attempts->pluck('id_siswa'))
            ->get()
            ->keyBy('id_login');

        return view('ujian.grading.index', compact('ujian', 'ujianKelasList', 'attempts', 'siswaByLogin', 'kelasFilter'));
    }

    public function show(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        $this->authorize('gradeEssay', $ujian);
        abort_unless($attempt->ujianKelas->id_ujian === $ujian->uuid, 404);
        $this->authorize('mengampuiKelas', $attempt->ujianKelas);

        $soalEssai = $ujian->soal()->where('tipe', 'essay')->get();
        $jawaban = UjianJawaban::where('id_attempt', $attempt->uuid)
            ->whereIn('id_soal', $soalEssai->pluck('uuid'))->get()->keyBy('id_soal');
        $siswa = Siswa::where('id_login', $attempt->id_siswa)->first();

        return view('ujian.grading.show', compact('ujian', 'attempt', 'soalEssai', 'jawaban', 'siswa'));
    }

    public function store(Request $request, Ujian $ujian, UjianAttempt $attempt, UjianGrader $grader)
    {
        $this->authorize('gradeEssay', $ujian);
        abort_unless($attempt->ujianKelas->id_ujian === $ujian->uuid, 404);
        $this->authorize('mengampuiKelas', $attempt->ujianKelas);
        abort_unless($attempt->status === UjianAttempt::STATUS_SUBMITTED, 422, 'Attempt ini belum bisa dinilai.');

        $data = $request->validate([
            'skor'      => 'required|array',
            'skor.*'    => 'required|numeric|min:0',
            'catatan'   => 'nullable|array',
            'catatan.*' => 'nullable|string|max:2000',
        ]);

        // Batasi hanya ke soal esai MILIK ujian ini — cegah IDOR menimpa jawaban esai
        // di ujian lain lewat kunci array id_soal yang dimanipulasi.
        $soalEssai = $ujian->soal()->where('tipe', 'essay')->get()->keyBy('uuid');

        foreach ($data['skor'] as $idSoal => $skor) {
            $soal = $soalEssai->get($idSoal);
            if (!$soal) {
                continue;
            }
            UjianJawaban::updateOrCreate(
                ['id_attempt' => $attempt->uuid, 'id_soal' => $idSoal],
                [
                    'skor_diperoleh' => min((float) $skor, (float) $soal->poin),
                    'dinilai_manual' => true,
                    'dinilai_oleh'   => $request->user()->uuid,
                    'catatan_guru'   => $data['catatan'][$idSoal] ?? null,
                ]
            );
        }

        $grader->selesaikanPenilaianEssai($attempt->fresh());

        return redirect()->route('ujian.grading.index', $ujian)->with('success', 'Penilaian esai tersimpan.');
    }
}
