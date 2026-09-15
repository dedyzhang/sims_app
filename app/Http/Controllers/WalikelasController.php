<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Sekretaris;
use App\Models\Siswa;
use App\Support\TableSort;
use Illuminate\Http\Request;

/**
 * Fitur Wali Kelas: akses data siswa kelasnya sendiri (biodata, reset password,
 * set sekretaris). Absensi & Poin/P3 kelasnya sudah ditangani lewat guard scoped
 * di AbsensiController/PoinController/P3Controller masing-masing.
 */
class WalikelasController extends Controller
{
    /** Kelas homeroom milik guru yang sedang login. */
    private function kelasSaya(): Kelas
    {
        $kelas = auth()->user()->guru?->walikelas?->kelas;
        abort_unless($kelas, 403, 'Anda bukan wali kelas.');
        return $kelas;
    }

    /** Siswa target, dipastikan benar siswa kelas wali kelas ini. */
    private function siswaSaya(string $uuid): Siswa
    {
        $siswa = Siswa::with(['kelas', 'user', 'orangtua.user'])->findOrFail($uuid);
        abort_unless($siswa->id_kelas === $this->kelasSaya()->uuid, 403, 'Siswa ini bukan siswa kelas Anda.');
        return $siswa;
    }

    public function siswaIndex(Request $request)
    {
        $kelas = $this->kelasSaya();
        [$sort, $dir] = TableSort::resolve($request, ['nama', 'nis', 'jk'], 'nama');

        $siswas = Siswa::where('id_kelas', $kelas->uuid)
            ->when($request->search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('nama', 'like', '%' . $request->search . '%')
                ->orWhere('nis', 'like', '%' . $request->search . '%')))
            ->orderBy($sort, $dir)->paginate(20)->withQueryString();

        $sekretarisIds = Sekretaris::where('id_kelas', $kelas->uuid)->pluck('id_siswa')->all();

        return view('walikelas.siswa.index', compact('kelas', 'siswas', 'sekretarisIds'));
    }

    public function siswaShow(string $uuid)
    {
        $siswa = $this->siswaSaya($uuid);
        return view('walikelas.siswa.show', compact('siswa'));
    }

    public function resetSiswa(string $uuid)
    {
        $this->siswaSaya($uuid);
        return app(SiswaController::class)->resetSiswa($uuid);
    }

    public function resetOrangtua(string $uuid)
    {
        $this->siswaSaya($uuid);
        return app(SiswaController::class)->resetOrangtua($uuid);
    }

    public function sekretarisForm()
    {
        $kelas = $this->kelasSaya();
        $siswas = Siswa::where('id_kelas', $kelas->uuid)->orderBy('nama')->get();
        $sekretarisIds = Sekretaris::where('id_kelas', $kelas->uuid)->pluck('id_siswa')->all();
        return view('walikelas.sekretaris', compact('kelas', 'siswas', 'sekretarisIds'));
    }

    public function sekretarisStore(Request $request)
    {
        $kelas = $this->kelasSaya();
        $data = $request->validate([
            'id_siswa'   => 'nullable|array|max:2',
            'id_siswa.*' => 'exists:siswa,uuid',
        ], [
            'id_siswa.max' => 'Maksimal 2 siswa sebagai sekretaris kelas.',
        ]);

        // Jaga-jaga: pastikan semua id yang dikirim benar siswa kelas ini.
        $valid = Siswa::where('id_kelas', $kelas->uuid)->whereIn('uuid', $data['id_siswa'] ?? [])->pluck('uuid');

        Sekretaris::where('id_kelas', $kelas->uuid)->delete();
        foreach ($valid as $uuid) {
            Sekretaris::create(['id_kelas' => $kelas->uuid, 'id_siswa' => $uuid]);
        }

        return back()->with('success', 'Sekretaris kelas berhasil disimpan.');
    }
    public function ruangKelasIndex(Request $request)
    {
        $kelas = $this->kelasSaya();
        $classrooms = \App\Models\Classroom::where('status', '!=', 'archived')
            ->where(function($q) use ($kelas) {
                $q->where('id_kelas', $kelas->uuid)
                  ->orWhereHas('kelas', function($q2) use ($kelas) {
                      $q2->where('kelas.uuid', $kelas->uuid);
                  });
            })
            ->with(['pelajaran', 'author'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('walikelas.ruang-kelas.index', compact('kelas', 'classrooms'));
    }

    public function ruangKelasShow(string $classCode)
    {
        $kelas = $this->kelasSaya();
        $classroom = \App\Models\Classroom::where('class_code', $classCode)->firstOrFail();
        
        $isForMyClass = $classroom->id_kelas === $kelas->uuid || $classroom->kelas()->where('kelas.uuid', $kelas->uuid)->exists();
        abort_unless($isForMyClass, 403, 'Ruang kelas ini bukan untuk kelas Anda.');

        $assignments = $classroom->assignments()->orderBy('created_at', 'desc')->get();
        $studentUserIds = Siswa::where('id_kelas', $kelas->uuid)->pluck('id_login')->filter()->all();
        $totalStudents = count($studentUserIds);

        $assignmentIds = $assignments->pluck('uuid')->all();
        $submissions = collect();
        if (!empty($assignmentIds) && !empty($studentUserIds)) {
            $submissions = \App\Models\ClassroomSubmission::whereIn('assignment_id', $assignmentIds)
                ->whereIn('student_id', $studentUserIds)
                ->get()
                ->groupBy('assignment_id');
        }

        foreach ($assignments as $assignment) {
            $subs = $submissions->get($assignment->uuid, collect());
            $assignment->submitted_count = $subs->whereIn('status', ['submitted', 'graded'])->count();
            $assignment->total_students = $totalStudents;
        }

        return view('walikelas.ruang-kelas.show', compact('kelas', 'classroom', 'assignments'));
    }

    public function ruangKelasAssignment(string $classCode, string $assignmentUuid)
    {
        $kelas = $this->kelasSaya();
        $classroom = \App\Models\Classroom::where('class_code', $classCode)->firstOrFail();
        $isForMyClass = $classroom->id_kelas === $kelas->uuid || $classroom->kelas()->where('kelas.uuid', $kelas->uuid)->exists();
        abort_unless($isForMyClass, 403, 'Akses ditolak.');

        $assignment = $classroom->assignments()->where('classroom_assignments.uuid', $assignmentUuid)->firstOrFail();

        $siswas = Siswa::where('id_kelas', $kelas->uuid)->orderBy('nama')->get();
        $studentUserIds = $siswas->pluck('id_login')->filter()->all();

        $submissions = collect();
        if (!empty($studentUserIds)) {
            $submissions = \App\Models\ClassroomSubmission::where('assignment_id', $assignment->uuid)
                ->whereIn('student_id', $studentUserIds)
                ->get()
                ->keyBy('student_id');
        }

        return view('walikelas.ruang-kelas.assignment', compact('kelas', 'classroom', 'assignment', 'siswas', 'submissions'));
    }
}
