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
        $ujianKelasIds = $ujian->kelas
            ->filter(fn ($uk) => app(UjianPolicy::class)->mengampuiKelas($request->user(), $uk))
            ->pluck('uuid');

        $attempts = UjianAttempt::whereIn('id_ujian_kelas', $ujianKelasIds)
            ->where('status', UjianAttempt::STATUS_SUBMITTED)
            ->where('butuh_penilaian_manual', true)
            ->get();

        $siswaByLogin = Siswa::whereIn('id_login', $attempts->pluck('id_siswa'))->get()->keyBy('id_login');

        return view('ujian.grading.index', compact('ujian', 'attempts', 'siswaByLogin'));
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
