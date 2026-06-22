<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesClassroomUploads;
use App\Http\Controllers\Concerns\HandlesContentLock;
use App\Http\Requests\StoreClassroomAssignmentRequest;
use App\Models\Classroom;
use App\Models\ClassroomAssignment;
use App\Models\ClassroomAssignmentFile;
use App\Models\ClassroomSubmission;
use App\Models\Materi;
use App\Models\NilaiFormatif;
use App\Models\NilaiSumatif;
use App\Models\TujuanPembelajaran;
use App\Support\Audit;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClassroomAssignmentController extends Controller
{
    use HandlesClassroomUploads, HandlesContentLock;

    public function __construct(private \App\Services\ClassroomService $service)
    {
    }

    public function create(Request $request, Classroom $classroom)
    {
        $this->authorize('manage', $classroom);

        return view('classroom.assignment_form', [
            'classroom'    => $classroom->load('pelajaran', 'rombel'),
            'kelasOptions' => $this->kelasOptions($classroom, $request->user()),
            'checked'      => [$classroom->id_kelas],
        ]);
    }

    public function store(StoreClassroomAssignmentRequest $request, Classroom $classroom)
    {
        $this->authorize('manage', $classroom);

        $assignment = ClassroomAssignment::create([
            'classroom_id' => $classroom->uuid,
            'created_by'   => $request->user()->uuid,
            'title'        => $request->title,
            'instructions' => RichText::clean($request->instructions),
            'type'         => $request->type,
            'max_score'    => $request->max_score,
            'allow_late'   => $request->boolean('allow_late'),
            'opens_at'     => $request->opens_at,
            'due_at'       => $request->due_at,
            'status'       => $request->boolean('publish_now') ? 'published' : 'draft',
            'hide_scores'  => $request->boolean('hide_scores'),
        ]);

        $this->service->linkToKelas($assignment, $request->kelas, $classroom, $request->user());

        if ($request->hasFile('files')) {
            $this->attachUploads($request->file('files'), 'classroom/assignments', ClassroomAssignmentFile::class, 'assignment_id', $assignment->uuid);
        }

        Audit::log('classroom_assignment_create', $assignment, ['title' => $assignment->title, 'kelas' => count($request->kelas)]);

        return redirect()->route('classroom.assignment.show', $assignment)->with('success', 'Tugas disimpan & ditautkan ke ' . $assignment->classrooms()->count() . ' kelas.');
    }

    public function edit(Request $request, ClassroomAssignment $assignment)
    {
        $this->authorize('manage', $assignment->classroom);

        return view('classroom.assignment_form', [
            'classroom'    => $assignment->classroom->load('pelajaran', 'rombel'),
            'assignment'   => $assignment->load('classrooms'),
            'kelasOptions' => $this->kelasOptions($assignment->classroom, $request->user()),
            'checked'      => $assignment->classrooms->pluck('id_kelas')->all(),
        ]);
    }

    /** Kelas tujuan: mapel sama, TINGKAT sama, dan (guru) hanya yang ia ampu. */
    private function kelasOptions(Classroom $classroom, \App\Models\User $user)
    {
        $q = \App\Models\Ngajar::where('id_pelajaran', $classroom->id_pelajaran);
        if (!$user->isAdmin() && $user->guru) {
            $q->where('id_guru', $user->guru->uuid);
        }
        $tingkat = $classroom->rombel?->tingkat ?? \App\Models\Kelas::find($classroom->id_kelas)?->tingkat;

        return \App\Models\Kelas::whereIn('uuid', $q->pluck('id_kelas')->filter()->unique())
            ->when($tingkat !== null, fn ($k) => $k->where('tingkat', $tingkat))
            ->orderBy('kelas')->get();
    }

    public function show(Request $request, ClassroomAssignment $assignment)
    {
        $classUuid = $request->query('class');
        $classroom = null;
        if ($classUuid) {
            $classroom = $assignment->classrooms()->where('uuid', $classUuid)->first();
        }
        if (!$classroom) {
            $user = $request->user();
            if ($user->access === 'siswa' && $user->siswa?->id_kelas) {
                $classroom = $assignment->classrooms()->where('id_kelas', $user->siswa->id_kelas)->first();
            }
            if (!$classroom && $user->guru) {
                $ids = \App\Models\Ngajar::where('id_guru', $user->guru->uuid)->pluck('id_kelas')->all();
                $classroom = $assignment->classrooms()->whereIn('id_kelas', $ids)->first();
            }
            if (!$classroom) {
                $classroom = $assignment->classroom;
            }
        }

        $this->authorize('view', $classroom);
        $user = $request->user();

        $lockStatus = $this->lockGetStatus($assignment, $classroom);
        $assignment->is_locked = $lockStatus['is_locked'];
        $assignment->access_token = $lockStatus['access_token'];

        $assignment->load(['files', 'author', 'classroom.pelajaran', 'classroom.rombel']);
        $comments = $assignment->comments()->whereNull('parent_id')->where('classroom_id', $classroom->uuid)->with(['user', 'replies'])->latest()->get();
        $canManage = $user->can('manage', $classroom);

        // Mode terkunci (token + layar penuh) untuk siswa.
        $isStudent  = $user->access === 'siswa';
        $gateLocked = $assignment->is_locked && $isStudent && !$canManage && !$this->lockIsUnlocked($assignment->uuid);
        $kioskMode  = $assignment->is_locked && $isStudent && !$canManage && $this->lockIsUnlocked($assignment->uuid);

        $mySubmission = null;
        $gradedSubmissions = collect();
        $unsubmittedStudents = collect();
        $materiList = collect();
        $ngajarUuid = null;

        if ($user->access === 'siswa') {
            $mySubmission = ClassroomSubmission::where('assignment_id', $assignment->uuid)
                ->where('student_id', $user->uuid)
                ->with('files')
                ->first();
        }

        if ($canManage) {
            // Get student UUIDs for the active classroom
            $studentUserUuids = [];
            $rombel = $classroom->rombel;
            if ($rombel) {
                $studentUserUuids = $rombel->siswa->pluck('id_login')->filter()->toArray();
            }

            // Load all submissions for this assignment in the active classroom
            $submissions = ClassroomSubmission::where('assignment_id', $assignment->uuid)
                ->whereIn('student_id', $studentUserUuids)
                ->with(['student.siswa', 'files'])
                ->get();

            // Graded submissions (Nilai yang sudah dikoreksi)
            $gradedSubmissions = $submissions->filter(fn($s) => $s->status === 'graded');

            // All students in the classroom rombel
            $rombel = $classroom->rombel;
            if ($rombel) {
                $allClassroomStudents = $rombel->siswa;

                // Students who submitted (either submitted or graded)
                $submittedStudentUserUuids = $submissions->filter(fn($s) => in_array($s->status, ['submitted', 'graded']))
                    ->pluck('student_id')
                    ->toArray();

                // Unsubmitted students (Belum kerjakan)
                $unsubmittedStudents = $allClassroomStudents->filter(fn($student) => !in_array($student->id_login, $submittedStudentUserUuids));
            }

            // Find matching Ngajar record to load academic materials and learning objectives (TP)
            $ngajar = \App\Models\Ngajar::where('id_kelas', $classroom->id_kelas)
                ->where('id_pelajaran', $classroom->id_pelajaran)
                ->first();

            if ($ngajar) {
                $ngajarUuid = $ngajar->uuid;
                $materiList = Materi::with('tujuan')
                    ->where('id_ngajar', $ngajar->uuid)
                    ->orderBy('urutan')
                    ->get();
            }
        }

        return view('classroom.assignment_show', compact(
            'assignment', 'classroom', 'comments', 'canManage', 'mySubmission',
            'gradedSubmissions', 'unsubmittedStudents', 'materiList', 'ngajarUuid',
            'gateLocked', 'kioskMode'
        ));
    }

    // ─── Kunci (token + layar penuh) — via HandlesContentLock ───
    public function toggleLock(Request $request, ClassroomAssignment $assignment)
    {
        return $this->lockToggle($request, $assignment);
    }

    public function unlock(Request $request, ClassroomAssignment $assignment)
    {
        return $this->lockDoUnlock($request, $assignment, 'classroom.assignment.show');
    }

    public function lockExit(Request $request, ClassroomAssignment $assignment)
    {
        return $this->lockDoExit($request, $assignment);
    }

    public function lockEvents(Request $request, ClassroomAssignment $assignment)
    {
        return $this->lockEventsJson($request, $assignment);
    }

    /** Memindahkan nilai dari classroom ke buku nilai formatif/sumatif akademik. */
    public function transferGrades(Request $request, ClassroomAssignment $assignment)
    {
        $this->authorize('manage', $assignment->classroom);

        $data = $request->validate([
            'type' => 'required|in:formatif,sumatif',
            'id_tupe' => 'nullable|required_if:type,formatif|exists:tujuan_pembelajaran,uuid',
            'id_materi' => 'nullable|required_if:type,sumatif|exists:materi,uuid',
        ]);

        // Tentukan materi tujuan → untuk tahu ngajar & semester-nya.
        $targetMateri = $data['type'] === 'formatif'
            ? Materi::find(TujuanPembelajaran::where('uuid', $data['id_tupe'])->value('id_materi'))
            : Materi::find($data['id_materi']);

        // BATALKAN bila rapor (ngajar + semester) sudah dikonfirmasi/terkunci.
        if ($targetMateri && \App\Models\RaporKonfirmasi::where('id_ngajar', $targetMateri->id_ngajar)
                ->where('id_semester', $targetMateri->id_semester)->exists()) {
            return back()->with('error', 'Transfer dibatalkan: nilai rapor untuk mata pelajaran & kelas ini sudah dikonfirmasi/terkunci. Batalkan konfirmasi rapor terlebih dahulu untuk memindahkan nilai.');
        }

        // Get all students in the classroom rombel
        $classroom = $assignment->classroom;
        $rombel = $classroom->rombel ?: \App\Models\Kelas::find($classroom->id_kelas);
        $students = $rombel ? $rombel->siswa : collect();

        if ($students->isEmpty()) {
            return back()->with('error', 'Tidak ada siswa di kelas ini untuk ditransfer nilainya.');
        }

        // Load all graded submissions for this assignment, keyed by student_id
        $submissions = ClassroomSubmission::where('assignment_id', $assignment->uuid)
            ->where('status', 'graded')
            ->get()
            ->keyBy('student_id');

        $count = 0;
        foreach ($students as $siswa) {
            // If student has a graded submission, use its score, otherwise default to 0
            $sub = $submissions->get($siswa->id_login);
            $score = $sub ? $sub->score : 0;

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

        $targetName = '';
        if ($data['type'] === 'formatif') {
            $tp = TujuanPembelajaran::where('uuid', $data['id_tupe'])->first();
            $targetName = "Formatif (TP " . ($tp ? $tp->urutan : '') . ": " . ($tp ? \Illuminate\Support\Str::limit($tp->tupe, 35) : '') . ")";
        } else {
            $m = Materi::where('uuid', $data['id_materi'])->first();
            $targetName = "Sumatif (Materi: " . ($m ? \Illuminate\Support\Str::limit($m->nama, 35) : '') . ")";
        }

        Audit::log('classroom_grades_transferred', $assignment, [
            'type' => $data['type'],
            'count' => $count,
            'target' => $targetName
        ]);

        return back()->with('success', "Berhasil mentransfer {$count} nilai siswa ke Nilai {$targetName}.");
    }

    public function update(StoreClassroomAssignmentRequest $request, ClassroomAssignment $assignment)
    {
        $this->authorize('manage', $assignment->classroom);

        $assignment->update([
            'title'        => $request->title,
            'instructions' => RichText::clean($request->instructions),
            'type'         => $request->type,
            'max_score'    => $request->max_score,
            'allow_late'   => $request->boolean('allow_late'),
            'opens_at'     => $request->opens_at,
            'due_at'       => $request->due_at,
            'status'       => $request->boolean('publish_now') ? 'published' : $assignment->status,
            'hide_scores'  => $request->boolean('hide_scores'),
        ]);

        $this->service->linkToKelas($assignment, $request->kelas, $assignment->classroom, $request->user());

        if ($request->hasFile('files')) {
            $this->attachUploads($request->file('files'), 'classroom/assignments', ClassroomAssignmentFile::class, 'assignment_id', $assignment->uuid);
        }

        Audit::log('classroom_assignment_update', $assignment);

        return redirect()->route('classroom.assignment.show', $assignment)->with('success', 'Tugas diperbarui untuk semua kelas tertaut.');
    }

    public function destroy(ClassroomAssignment $assignment)
    {
        $this->authorize('manage', $assignment->classroom);
        $assignment->delete();
        Audit::log('classroom_assignment_delete', $assignment);

        return back()->with('success', 'Tugas dihapus.');
    }

    /** Halaman penilaian: daftar submission per tugas. */
    public function submissions(Request $request, ClassroomAssignment $assignment)
    {
        $classUuid = $request->query('class');
        $classroom = null;
        if ($classUuid) {
            $classroom = $assignment->classrooms()->where('uuid', $classUuid)->first();
        }
        if (!$classroom) {
            $user = $request->user();
            if ($user->access === 'siswa' && $user->siswa?->id_kelas) {
                $classroom = $assignment->classrooms()->where('id_kelas', $user->siswa->id_kelas)->first();
            }
            if (!$classroom && $user->guru) {
                $ids = \App\Models\Ngajar::where('id_guru', $user->guru->uuid)->pluck('id_kelas')->all();
                $classroom = $assignment->classrooms()->whereIn('id_kelas', $ids)->first();
            }
            if (!$classroom) {
                $classroom = $assignment->classroom;
            }
        }

        $this->authorize('manage', $classroom);

        // Get student UUIDs for the active classroom
        $studentUserUuids = [];
        $rombel = $classroom->rombel;
        if ($rombel) {
            $studentUserUuids = $rombel->siswa->pluck('id_login')->filter()->toArray();
        }

        // Load only submissions for this assignment in the active classroom
        $submissions = ClassroomSubmission::where('assignment_id', $assignment->uuid)
            ->whereIn('student_id', $studentUserUuids)
            ->with(['student.siswa', 'files'])
            ->latest('submitted_at')
            ->get();

        return view('classroom.grading', compact('assignment', 'classroom', 'submissions'));
    }

    public function download(ClassroomAssignmentFile $file)
    {
        $this->authorize('view', $file->assignment->classroom);

        abort_unless(Storage::disk('public')->exists($file->path), 404);
        return Storage::disk('public')->download($file->path, $file->original_name);
    }
}
