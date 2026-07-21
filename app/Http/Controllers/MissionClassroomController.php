<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Kelas;
use App\Models\Materi;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionAttempt;
use App\Models\NilaiFormatif;
use App\Models\NilaiSumatif;
use App\Models\RaporKonfirmasi;
use App\Models\TujuanPembelajaran;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MissionClassroomController extends Controller
{
    public function index(Classroom $classroom): RedirectResponse
    {
        $this->authorize('view', $classroom);

        return redirect()->route('classroom.arena.index', ['classroom' => $classroom, 'mode' => 'misi']);
    }

    public function assign(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorize('manage', $classroom);

        $data = $request->validate([
            'mission_id' => ['required', 'uuid', 'exists:missions,uuid'],
            'opens_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
        ]);

        $mission = Mission::withCount('steps')->findOrFail($data['mission_id']);

        if (! $mission->isPublished()) {
            return redirect()
                ->route('classroom.arena.index', ['classroom' => $classroom, 'mode' => 'misi'])
                ->with('error', 'Misi belum diterbitkan.');
        }

        if (! $mission->isPlayable()) {
            return redirect()
                ->route('classroom.arena.index', ['classroom' => $classroom, 'mode' => 'misi'])
                ->with('error', 'Misi belum punya langkah permainan. Pilih misi dari katalog yang siap dimainkan.');
        }

        MissionAssignment::updateOrCreate(
            ['mission_id' => $mission->uuid, 'classroom_id' => $classroom->uuid],
            [
                'assigned_by' => $request->user()->uuid,
                'opens_at' => $data['opens_at'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'status' => 'open',
            ]
        );

        Audit::log('jagat_misi_assigned', $mission, ['classroom_id' => $classroom->uuid]);

        return redirect()
            ->route('classroom.arena.index', ['classroom' => $classroom, 'mode' => 'misi'])
            ->with('success', 'Misi berhasil ditugaskan ke kelas.');
    }

    public function show(Classroom $classroom, Mission $mission): View
    {
        abort_unless($mission->assignmentFor($classroom), 404);
        $this->authorize('viewInClassroom', [$mission, $classroom]);

        $assignment = $mission->assignmentFor($classroom);
        $canManage = auth()->user()->can('manage', $classroom);

        $myAttempt = null;
        if (auth()->user()->access === 'siswa') {
            $myAttempt = MissionAttempt::query()
                ->where('assignment_id', $assignment->uuid)
                ->where('user_id', auth()->id())
                ->latest()
                ->first();
        }

        return view('classroom.jagat-misi.show', compact(
            'classroom', 'mission', 'assignment', 'canManage', 'myAttempt'
        ));
    }

    public function play(Classroom $classroom, Mission $mission): View
    {
        $this->authorize('playInClassroom', [$mission, $classroom]);
        $assignment = $mission->assignmentFor($classroom);
        abort_unless($assignment, 404);

        $mission->load(['steps' => fn ($q) => $q->orderBy('position')]);
        abort_unless($mission->isPlayable(), 404, 'Misi belum punya langkah permainan.');

        $isPlayer = str_contains($mission->mechanic_type, 'recall')
            || str_contains($mission->mechanic_type, 'quiz');

        return view($isPlayer ? 'jagat-misi.player' : 'jagat-misi.nalar', compact(
            'mission', 'classroom', 'assignment'
        ));
    }

    public function results(Classroom $classroom, Mission $mission): View
    {
        abort_unless($mission->assignmentFor($classroom), 404);
        $this->authorize('manage', $classroom);

        $assignment = $mission->assignmentFor($classroom);
        $attempts = MissionAttempt::query()
            ->with('user')
            ->where('assignment_id', $assignment->uuid)
            ->where('status', 'completed')
            ->orderByDesc('score')
            ->get();

        $rombel = $classroom->rombel ?: Kelas::find($classroom->id_kelas);
        $memberCount = $rombel ? $rombel->siswa()->count() : $classroom->members->count();
        $doneCount = $attempts->unique('user_id')->count();

        $materiList = collect();
        $tupeList = collect();
        if ($classroom->id_pelajaran && $classroom->id_kelas) {
            $materiList = Materi::whereHas('ngajar', function ($q) use ($classroom) {
                $q->where('id_kelas', $classroom->id_kelas)
                    ->where('id_pelajaran', $classroom->id_pelajaran);
            })->orderBy('urutan')->get();
            $tupeList = TujuanPembelajaran::whereIn('id_materi', $materiList->pluck('uuid'))->orderBy('urutan')->get();
        }

        return view('classroom.jagat-misi.results', compact(
            'classroom', 'mission', 'assignment', 'attempts',
            'memberCount', 'doneCount', 'materiList', 'tupeList'
        ));
    }

    public function transferGrades(Request $request, Classroom $classroom, Mission $mission): RedirectResponse
    {
        abort_unless($mission->assignmentFor($classroom), 404);
        $this->authorize('manage', $classroom);

        $data = $request->validate([
            'type' => ['required', 'in:formatif,sumatif'],
            'id_tupe' => ['nullable', 'required_if:type,formatif', 'uuid'],
            'id_materi' => ['nullable', 'required_if:type,sumatif', 'uuid'],
        ]);

        $assignment = $mission->assignmentFor($classroom);

        $allowedMateri = collect();
        $allowedTupe = collect();
        if ($classroom->id_pelajaran && $classroom->id_kelas) {
            $allowedMateri = Materi::whereHas('ngajar', function ($q) use ($classroom) {
                $q->where('id_kelas', $classroom->id_kelas)
                    ->where('id_pelajaran', $classroom->id_pelajaran);
            })->pluck('uuid');
            $allowedTupe = TujuanPembelajaran::whereIn('id_materi', $allowedMateri)->pluck('uuid');
        }

        if ($data['type'] === 'formatif') {
            abort_unless($allowedTupe->contains($data['id_tupe']), 422, 'TP tidak termasuk mapel kelas ini.');
        } else {
            abort_unless($allowedMateri->contains($data['id_materi']), 422, 'Materi tidak termasuk mapel kelas ini.');
        }

        $targetMateri = $data['type'] === 'formatif'
            ? Materi::find(TujuanPembelajaran::where('uuid', $data['id_tupe'])->value('id_materi'))
            : Materi::find($data['id_materi']);

        if ($targetMateri && RaporKonfirmasi::where('id_ngajar', $targetMateri->id_ngajar)
            ->where('id_semester', $targetMateri->id_semester)->exists()) {
            return back()->with('error', 'Transfer dibatalkan: rapor sudah dikunci.');
        }

        $rombel = $classroom->rombel ?: Kelas::find($classroom->id_kelas);
        $students = $rombel ? $rombel->siswa : collect();
        if ($students->isEmpty()) {
            return back()->with('error', 'Tidak ada siswa di kelas ini.');
        }

        $attempts = $assignment->attempts()
            ->where('status', 'completed')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($group) => $group->sortByDesc('score')->first());

        $count = 0;
        DB::transaction(function () use ($data, $students, $attempts, &$count) {
            foreach ($students as $siswa) {
                $attempt = $attempts->get($siswa->id_login);
                if (! $attempt) {
                    continue;
                }

                $score = $attempt->score;

                if ($data['type'] === 'formatif') {
                    $idTupe = $data['id_tupe'];
                    $idMateri = TujuanPembelajaran::where('uuid', $idTupe)->value('id_materi');
                    NilaiFormatif::updateOrCreate(
                        ['id_tupe' => $idTupe, 'id_siswa' => $siswa->uuid],
                        ['id_materi' => $idMateri, 'nilai' => $score]
                    );
                } else {
                    NilaiSumatif::updateOrCreate(
                        ['id_materi' => $data['id_materi'], 'id_siswa' => $siswa->uuid],
                        ['nilai' => $score]
                    );
                }
                $count++;
            }
        });

        Audit::log('jagat_misi_grades_transferred', $mission, [
            'type' => $data['type'],
            'count' => $count,
            'classroom_id' => $classroom->uuid,
        ]);

        return back()->with('success', "Berhasil mentransfer {$count} nilai siswa ke buku nilai.");
    }
}
