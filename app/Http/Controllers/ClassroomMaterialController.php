<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesClassroomUploads;
use App\Http\Controllers\Concerns\HandlesContentLock;
use App\Http\Requests\StoreClassroomMaterialRequest;
use App\Models\Classroom;
use App\Models\ClassroomLockEvent;
use App\Models\ClassroomMaterial;
use App\Models\ClassroomMaterialFile;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\User;
use App\Services\ClassroomService;
use App\Support\Audit;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClassroomMaterialController extends Controller
{
    use HandlesClassroomUploads, HandlesContentLock;

    public function __construct(private ClassroomService $service)
    {
    }

    public function create(Request $request, Classroom $classroom)
    {
        $this->authorize('manage', $classroom);

        return view('classroom.material_form', [
            'classroom'    => $classroom->load('pelajaran', 'rombel'),
            'kelasOptions' => $this->kelasOptions($classroom, $request->user()),
            'checked'      => [$classroom->id_kelas],
        ]);
    }

    public function store(StoreClassroomMaterialRequest $request, Classroom $classroom)
    {
        $this->authorize('manage', $classroom);

        $locked = $request->boolean('is_locked');
        $material = ClassroomMaterial::create([
            'classroom_id' => $classroom->uuid,
            'uploaded_by'  => $request->user()->uuid,
            'title'        => $request->title,
            'description'  => $request->description,
            'body'         => RichText::clean($request->body),
            'link_url'     => $request->link_url,
            'meet_url'     => $this->normalizeMeet($request->meet_url),
            'is_published' => true,
            'is_locked'    => $locked,
            'access_token' => $locked ? trim($request->access_token) : null,
            'published_at' => now(),
        ]);

        $this->service->linkToKelas($material, $request->kelas, $classroom, $request->user());

        if ($request->hasFile('files')) {
            $this->attachUploads($request->file('files'), 'classroom/materials', ClassroomMaterialFile::class, 'material_id', $material->uuid, withSort: true);
        }

        Audit::log('classroom_material_create', $material, ['title' => $material->title, 'kelas' => count($request->kelas)]);

        return redirect()->route('classroom.material.show', $material)->with('success', 'Materi disimpan & ditautkan ke ' . $material->classrooms()->count() . ' kelas.');
    }

    public function show(Request $request, ClassroomMaterial $material)
    {
        $classUuid = $request->query('class');
        $classroom = null;
        if ($classUuid) {
            $classroom = $material->classrooms()->where('uuid', $classUuid)->first();
        }
        if (!$classroom) {
            $user = $request->user();
            if ($user->access === 'siswa' && $user->siswa?->id_kelas) {
                $classroom = $material->classrooms()->where('id_kelas', $user->siswa->id_kelas)->first();
            }
            if (!$classroom && $user->guru) {
                $ids = Ngajar::where('id_guru', $user->guru->uuid)->pluck('id_kelas')->all();
                $classroom = $material->classrooms()->whereIn('id_kelas', $ids)->first();
            }
            if (!$classroom) {
                $classroom = $material->classroom;
            }
        }

        $this->authorize('view', $classroom);
        $user = $request->user();

        $lockStatus = $this->lockGetStatus($material, $classroom);
        $material->is_locked = $lockStatus['is_locked'];
        $material->access_token = $lockStatus['access_token'];

        $material->load(['files', 'uploader', 'classroom.pelajaran', 'classroom.rombel', 'classrooms.rombel']);
        $comments = $material->comments()->whereNull('parent_id')->where('classroom_id', $classroom->uuid)->with(['user', 'replies'])->latest()->get();

        $canManage = $user->can('manage', $classroom);
        $isStudent = $user->access === 'siswa';
        // Materi terkunci → siswa harus buka token dulu (kecuali guru/admin pengelola).
        $gateLocked = $material->is_locked && $isStudent && !$canManage && !$this->lockIsUnlocked($material->uuid);
        $kioskMode  = $material->is_locked && $isStudent && !$canManage && $this->lockIsUnlocked($material->uuid);

        return view('classroom.material_show', compact('material', 'classroom', 'comments', 'canManage', 'gateLocked', 'kioskMode'));
    }

    public function edit(Request $request, ClassroomMaterial $material)
    {
        $this->authorize('manage', $material->classroom);

        return view('classroom.material_form', [
            'classroom'    => $material->classroom->load('pelajaran', 'rombel'),
            'material'     => $material->load('classrooms'),
            'kelasOptions' => $this->kelasOptions($material->classroom, $request->user()),
            'checked'      => $material->classrooms->pluck('id_kelas')->all(),
        ]);
    }

    public function update(StoreClassroomMaterialRequest $request, ClassroomMaterial $material)
    {
        $this->authorize('manage', $material->classroom);

        $locked = $request->boolean('is_locked');
        $material->update([
            'title'        => $request->title,
            'description'  => $request->description,
            'body'         => RichText::clean($request->body),
            'link_url'     => $request->link_url,
            'meet_url'     => $this->normalizeMeet($request->meet_url),
            'is_locked'    => $locked,
            'access_token' => $locked ? trim($request->access_token) : null,
        ]);

        // Edit sekaligus ke beberapa kelas (re-sinkron taut).
        $this->service->linkToKelas($material, $request->kelas, $material->classroom, $request->user());

        if ($request->hasFile('files')) {
            $this->attachUploads($request->file('files'), 'classroom/materials', ClassroomMaterialFile::class, 'material_id', $material->uuid, withSort: true);
        }

        Audit::log('classroom_material_update', $material);

        return redirect()->route('classroom.material.show', $material)->with('success', 'Materi diperbarui untuk semua kelas tertaut.');
    }

    public function destroy(ClassroomMaterial $material)
    {
        $this->authorize('manage', $material->classroom);
        $classroom = $material->classroom;
        $material->delete();
        Audit::log('classroom_material_delete', $material);

        return redirect()->route('classroom.show', $classroom)->with('success', 'Materi dihapus.');
    }

    public function download(ClassroomMaterialFile $file)
    {
        $this->authorize('view', $file->material->classroom);
        abort_unless(Storage::disk('public')->exists($file->path), 404);

        return Storage::disk('public')->download($file->path, $file->original_name);
    }

    // ─────────────── Materi terkunci (token + layar penuh) — via HandlesContentLock ───────────────

    public function toggleLock(Request $request, ClassroomMaterial $material)
    {
        return $this->lockToggle($request, $material);
    }

    public function unlock(Request $request, ClassroomMaterial $material)
    {
        return $this->lockDoUnlock($request, $material, 'classroom.material.show');
    }

    public function lockExit(Request $request, ClassroomMaterial $material)
    {
        return $this->lockDoExit($request, $material);
    }

    public function lockEvents(Request $request, ClassroomMaterial $material)
    {
        return $this->lockEventsJson($request, $material);
    }

    /** Tutup kelas online: hapus link Google Meet dari materi. */
    public function closeMeet(Request $request, ClassroomMaterial $material)
    {
        $this->authorize('manage', $material->classroom);
        $material->update(['meet_url' => null]);
        Audit::log('classroom_meet_closed', $material);

        return back()->with('success', 'Kelas online ditutup — link Google Meet dihapus.');
    }

    /** Normalisasi input Google Meet (URL penuh atau kode xxx-xxxx-xxx) → URL bersih. */
    private function normalizeMeet(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (preg_match('#meet\.google\.com/([a-z0-9-]+)#i', $v, $m)) {
            return 'https://meet.google.com/' . $m[1];
        }
        if (preg_match('/^[a-z]{3}-[a-z]{4}-[a-z]{3}$/i', $v)) {
            return 'https://meet.google.com/' . strtolower($v);
        }
        return null;
    }

    /**
     * Kelas tujuan taut/duplikat: mapel SAMA, TINGKAT SAMA, dan (untuk guru) hanya
     * kelas yang ia ampu sendiri. Mis. dari Matematika 7A → hanya 7B/7C/7D.
     */
    private function kelasOptions(Classroom $classroom, User $user)
    {
        $q = Ngajar::where('id_pelajaran', $classroom->id_pelajaran);
        if (!$user->isAdmin() && $user->guru) {
            $q->where('id_guru', $user->guru->uuid);
        }
        $ids = $q->pluck('id_kelas')->filter()->unique();

        $tingkat = $classroom->rombel?->tingkat ?? Kelas::find($classroom->id_kelas)?->tingkat;

        return Kelas::whereIn('uuid', $ids)
            ->when($tingkat !== null, fn ($k) => $k->where('tingkat', $tingkat))
            ->orderBy('kelas')->get();
    }
}
