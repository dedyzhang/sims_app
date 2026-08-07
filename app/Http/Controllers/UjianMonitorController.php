<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianPelanggaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard pemantauan live guru/panitia saat ujian berlangsung — status tiap
 * siswa, sisa waktu, pelanggaran, dan dua aksi reset: buka-kunci (lanjut dari
 * titik terakhir) vs reset-ulang (soft-cancel, mulai dari nol dgn token sama).
 */
class UjianMonitorController extends Controller
{
    public function index(Request $request, Ujian $ujian)
    {
        $this->authorize('monitor', $ujian);
        $ujian->load('kelas.kelas');

        return view('ujian.monitor.index', compact('ujian'));
    }

    public function poll(Request $request, Ujian $ujian)
    {
        $this->authorize('monitor', $ujian);

        $ujianKelasList = $ujian->kelas()->with('kelas')->get()->keyBy('uuid');
        $attempts = UjianAttempt::whereIn('id_ujian_kelas', $ujianKelasList->pluck('uuid'))
            ->where('status', '!=', UjianAttempt::STATUS_DIBATALKAN)
            ->get();
        $siswaByLogin = Siswa::whereIn('id_login', $attempts->pluck('id_siswa'))->get()->keyBy('id_login');
        $pelanggaranCount = UjianPelanggaran::whereIn('id_attempt', $attempts->pluck('uuid'))
            ->selectRaw('id_attempt, count(*) as jumlah')->groupBy('id_attempt')->pluck('jumlah', 'id_attempt');

        $data = $attempts->map(function (UjianAttempt $a) use ($siswaByLogin, $ujianKelasList, $pelanggaranCount) {
            $siswa = $siswaByLogin->get($a->id_siswa);
            $uk = $ujianKelasList->get($a->id_ujian_kelas);

            return [
                'attempt_uuid'     => $a->uuid,
                'nama'             => $siswa?->nama ?? 'Siswa tidak dikenal',
                'kelas'            => trim(($uk?->kelas?->tingkat ?? '') . ($uk?->kelas?->kelas ?? '')),
                'status'           => $a->status,
                'status_label'     => $a->statusLabel(),
                'dikunci'          => $a->isLocked(),
                'batas_waktu_pada' => $a->batas_waktu_pada?->toIso8601String(),
                'pelanggaran'      => (int) ($pelanggaranCount[$a->uuid] ?? 0),
            ];
        })->sortBy('nama')->values();

        return response()->json(['attempts' => $data]);
    }

    public function resetLock(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        $this->authorize('resetLock', $ujian);
        abort_unless($attempt->ujianKelas->id_ujian === $ujian->uuid, 404);
        abort_unless($attempt->isLocked(), 422, 'Attempt ini tidak sedang terkunci.');

        DB::transaction(function () use ($attempt) {
            $attempt->update(['dikunci' => false]);
            UjianPelanggaran::create(['id_attempt' => $attempt->uuid, 'id_siswa' => $attempt->id_siswa, 'tipe' => 'reset_oleh_guru']);
        });

        return back()->with('success', 'Kunci dibuka — siswa bisa melanjutkan dari titik terakhir, jawaban tersimpan tidak hilang.');
    }

    /**
     * Reset PENUH (bukan buka-kunci) — utk kegagalan teknis total. Soft-cancel:
     * attempt lama TETAP ada (status='dibatalkan') utk audit, siswa lalu bisa
     * start() lagi dgn token yg sama & dapat attempt baru (urutan diacak ulang).
     */
    public function resetAttempt(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        $this->authorize('resetAttempt', $ujian);
        abort_unless($attempt->ujianKelas->id_ujian === $ujian->uuid, 404);
        abort_if($attempt->status === UjianAttempt::STATUS_DIBATALKAN, 422, 'Attempt ini sudah direset sebelumnya.');

        DB::transaction(function () use ($attempt) {
            $attempt->update(['status' => UjianAttempt::STATUS_DIBATALKAN, 'dikunci' => false]);
            UjianPelanggaran::create(['id_attempt' => $attempt->uuid, 'id_siswa' => $attempt->id_siswa, 'tipe' => 'direset_admin']);
        });

        return back()->with('success', 'Attempt direset — siswa bisa memulai ujian ini lagi dari awal dengan token yang sama.');
    }
}
