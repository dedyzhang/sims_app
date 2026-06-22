<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesClassroomUploads;
use App\Http\Requests\GradeClassroomSubmissionRequest;
use App\Http\Requests\StoreClassroomSubmissionRequest;
use App\Models\ClassroomAssignment;
use App\Models\ClassroomSubmission;
use App\Models\ClassroomSubmissionFile;
use App\Support\Audit;
use Illuminate\Support\Facades\Storage;

class ClassroomSubmissionController extends Controller
{
    use HandlesClassroomUploads;

    /** Siswa mengumpulkan tugas (boleh banyak file). */
    public function store(StoreClassroomSubmissionRequest $request, ClassroomAssignment $assignment)
    {
        $classroom = $assignment->classroom;
        $this->authorize('submit', $classroom);

        abort_unless($assignment->status === 'published', 403, 'Tugas belum dibuka.');

        // 1 submission per (tugas, siswa).
        $submission = ClassroomSubmission::firstOrNew([
            'assignment_id' => $assignment->uuid,
            'student_id'    => $request->user()->uuid,
        ]);

        // Jawaban yang sudah dikumpulkan/dinilai tidak bisa direvisi oleh siswa secara langsung
        if ($submission->exists && in_array($submission->status, ['submitted', 'graded'])) {
            abort(403, 'Tugas yang sudah dikumpulkan tidak dapat diubah.');
        }

        $late = $assignment->due_at && now()->gt($assignment->due_at);
        abort_if($late && !$assignment->allow_late, 403, 'Batas waktu pengumpulan sudah lewat.');

        $submission->classroom_id = $classroom->uuid;
        $submission->body = $request->body;

        $isDraft = $request->input('submit_action') === 'draft';
        if ($isDraft) {
            $submission->status = 'draft';
        } else {
            $submission->status = 'submitted';
            $submission->submitted_at = now();
            $submission->is_late = (bool) $late;
        }

        $submission->save();

        if ($request->hasFile('files')) {
            $this->attachUploads($request->file('files'), 'classroom/submissions', ClassroomSubmissionFile::class, 'submission_id', $submission->uuid);
        }

        Audit::log('classroom_submission', $submission, [
            'assignment' => $assignment->title,
            'action' => $isDraft ? 'draft' : 'submit'
        ]);

        $msg = $isDraft ? 'Draf tugas berhasil disimpan.' : 'Tugas berhasil dikumpulkan.';
        return back()->with('success', $msg);
    }

    /** Guru memberi nilai + feedback. */
    public function grade(GradeClassroomSubmissionRequest $request, ClassroomSubmission $submission)
    {
        $this->authorize('manage', $submission->assignment->classroom);

        $max = $submission->assignment->max_score;
        $submission->update([
            'score'     => min((int) $request->score, $max),
            'feedback'  => $request->feedback,
            'graded_by' => $request->user()->uuid,
            'graded_at' => now(),
            'status'    => 'graded',
        ]);

        Audit::log('classroom_grade', $submission, ['score' => $submission->score]);

        return back()->with('success', 'Nilai disimpan.');
    }

    /** Guru membatalkan pengumpulan tugas siswa agar bisa direvisi. */
    public function returnSubmission(ClassroomSubmission $submission)
    {
        $this->authorize('manage', $submission->assignment->classroom);

        // Hanya bisa batalkan jika status submitted atau graded
        abort_unless(in_array($submission->status, ['submitted', 'graded']), 403, 'Tugas tidak dalam status dikumpulkan atau dinilai.');

        $submission->update([
            'status' => 'returned',
            'score'  => null,
        ]);

        Audit::log('classroom_submission_returned', $submission, ['assignment' => $submission->assignment->title]);

        return back()->with('success', 'Jawaban berhasil dibatalkan. Siswa sekarang dapat merevisi jawabannya.');
    }

    public function download(ClassroomSubmissionFile $file)
    {
        $submission = $file->submission;
        // Boleh: pengelola kelas ATAU pemilik submission.
        abort_unless(
            auth()->user()->can('manage', $submission->assignment->classroom) || $submission->student_id === auth()->id(),
            403
        );

        abort_unless(Storage::disk('public')->exists($file->path), 404);
        return Storage::disk('public')->download($file->path, $file->original_name);
    }
}
