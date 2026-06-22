<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ClassroomLockEvent;
use App\Models\ClassroomMember;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Logika "konten terkunci" (token + mode layar penuh + pemantauan) yang dipakai
 * bersama oleh materi & latihan/tugas.
 */
trait HandlesContentLock
{
    /** Guru menyalakan/mematikan kunci untuk kelas aktif. */
    protected function lockToggle(Request $request, Model $model)
    {
        $classroom = $this->resolveClassroom($request, $model);
        $this->authorize('manage', $classroom);

        $pivot = $this->getPivot($model, $classroom);
        $isLocked = $pivot ? (bool) $pivot->is_locked : false;

        if ($isLocked) {
            $this->updatePivot($model, $classroom, ['is_locked' => false, 'access_token' => null]);
            Audit::log('content_unlock', $model, ['classroom_id' => $classroom->uuid]);
            return back()->with('success', 'Kunci dibuka.');
        }

        $request->validate(['access_token' => ['nullable', 'string', 'max:16']]);
        $token = trim((string) $request->input('access_token'))
            ?: (($pivot && $pivot->access_token) ? $pivot->access_token : Str::upper(Str::random(4)));

        $this->updatePivot($model, $classroom, ['is_locked' => true, 'access_token' => $token]);
        Audit::log('content_lock', $model, ['token' => $token, 'classroom_id' => $classroom->uuid]);

        return back()->with('success', 'Dikunci. Token siswa: ' . $token);
    }

    /** Siswa membuka dengan token untuk kelas aktif. */
    protected function lockDoUnlock(Request $request, Model $model, string $showRoute)
    {
        $classroom = $this->resolveClassroom($request, $model);
        $this->authorize('view', $classroom);

        $pivot = $this->getPivot($model, $classroom);
        $isLocked = $pivot ? (bool) $pivot->is_locked : false;
        abort_unless($isLocked, 404);

        $request->validate(['token' => ['required', 'string']]);

        if (!hash_equals((string) $pivot->access_token, trim($request->token))) {
            return back()->with('error', 'Token salah. Minta token yang benar kepada guru.');
        }

        $u = session('lock_unlock', []);
        $u[$model->uuid] = now()->timestamp;
        session(['lock_unlock' => $u]);

        ClassroomLockEvent::create([
            'lockable_type' => $model::class, 'lockable_id' => $model->uuid,
            'student_id' => $request->user()->uuid, 'type' => 'masuk',
        ]);

        return redirect()->route($showRoute, [$model, 'class' => $classroom->uuid]);
    }

    /** Klien melapor siswa keluar dari layar penuh / pindah tab. */
    protected function lockDoExit(Request $request, Model $model)
    {
        if ($request->user()->access !== 'siswa') {
            return response()->noContent();
        }
        $u = session('lock_unlock', []);
        if (isset($u[$model->uuid])) {
            ClassroomLockEvent::create([
                'lockable_type' => $model::class, 'lockable_id' => $model->uuid,
                'student_id' => $request->user()->uuid, 'type' => 'keluar',
                'reason' => substr((string) $request->input('reason', 'keluar layar'), 0, 100),
            ]);
            unset($u[$model->uuid]);
            session(['lock_unlock' => $u]);
        }
        return response()->noContent();
    }

    /** Pemantauan guru (JSON): SEMUA siswa di kelas aktif + status terkini. */
    protected function lockEventsJson(Request $request, Model $model)
    {
        $classroom = $this->resolveClassroom($request, $model);
        $this->authorize('manage', $classroom);

        $members = ClassroomMember::with('user')
            ->where('classroom_id', $classroom->uuid)
            ->where('role_in_class', 'siswa')->get();
        $memberIds = $members->pluck('user_id')->all();

        $events = ClassroomLockEvent::with('student')
            ->where('lockable_type', $model::class)->where('lockable_id', $model->uuid)
            ->whereIn('student_id', $memberIds ?: ['-'])
            ->orderByDesc('created_at')->limit(500)->get();

        $latest = [];
        foreach ($events as $e) {
            if (!isset($latest[$e->student_id])) $latest[$e->student_id] = $e;
        }

        $rank = ['keluar' => 0, 'masuk' => 1, 'belum' => 2];
        $peserta = $members->map(function ($m) use ($latest) {
            $e = $latest[$m->user_id] ?? null;
            return [
                'id' => $m->user_id, 'nama' => $m->user?->displayName() ?? '-',
                'status' => $e ? $e->type : 'belum',
                'reason' => $e?->reason, 'waktu' => $e?->created_at?->locale('id')->diffForHumans(),
            ];
        })->sortBy(fn ($p) => [$rank[$p['status']] ?? 3, $p['nama']])->values();

        return response()->json([
            'peserta'  => $peserta,
            'total'    => $peserta->count(),
            'di_dalam' => $peserta->where('status', 'masuk')->count(),
            'keluar'   => $peserta->where('status', 'keluar')->count(),
            'belum'    => $peserta->where('status', 'belum')->count(),
            'keluar_baru' => $events->where('type', 'keluar')->where('created_at', '>=', now()->subSeconds(30))
                ->map(fn ($e) => ['id' => $e->uuid, 'nama' => $e->student?->displayName(), 'reason' => $e->reason])->values(),
        ]);
    }

    /** Resolusi kelas aktif secara dinamis berdasarkan request. */
    protected function resolveClassroom(Request $request, Model $model)
    {
        $classUuid = $request->query('class') ?: $request->input('class');
        $classroom = null;
        if ($classUuid) {
            $classroom = $model->classrooms()->where('uuid', $classUuid)->first();
        }
        if (!$classroom) {
            $user = $request->user();
            if ($user->access === 'siswa' && $user->siswa?->id_kelas) {
                $classroom = $model->classrooms()->where('id_kelas', $user->siswa->id_kelas)->first();
            }
            if (!$classroom && $user->guru) {
                $ids = \App\Models\Ngajar::where('id_guru', $user->guru->uuid)->pluck('id_kelas')->all();
                $classroom = $model->classrooms()->whereIn('id_kelas', $ids)->first();
            }
            if (!$classroom) {
                $classroom = $model->classroom;
            }
        }
        return $classroom;
    }

    protected function lockIsUnlocked(string $id): bool
    {
        return array_key_exists($id, session('lock_unlock', []));
    }

    /** Ambil row pivot relasi konten ke kelas. */
    protected function getPivot(Model $model, \App\Models\Classroom $classroom)
    {
        $table = $model instanceof \App\Models\ClassroomAssignment
            ? 'classroom_assignment_links'
            : 'classroom_material_links';
        $foreignKey = $model instanceof \App\Models\ClassroomAssignment
            ? 'assignment_id'
            : 'material_id';
        
        return DB::table($table)
            ->where($foreignKey, $model->uuid)
            ->where('classroom_id', $classroom->uuid)
            ->first();
    }

    /** Update row pivot relasi konten ke kelas. */
    protected function updatePivot(Model $model, \App\Models\Classroom $classroom, array $attributes)
    {
        $table = $model instanceof \App\Models\ClassroomAssignment
            ? 'classroom_assignment_links'
            : 'classroom_material_links';
        $foreignKey = $model instanceof \App\Models\ClassroomAssignment
            ? 'assignment_id'
            : 'material_id';

        DB::table($table)
            ->where($foreignKey, $model->uuid)
            ->where('classroom_id', $classroom->uuid)
            ->update($attributes);
    }

    /** Dapatkan status lock konten per kelas aktif. */
    protected function lockGetStatus(Model $model, \App\Models\Classroom $classroom)
    {
        $pivot = $this->getPivot($model, $classroom);
        return [
            'is_locked' => $pivot ? (bool) $pivot->is_locked : false,
            'access_token' => $pivot ? $pivot->access_token : null,
        ];
    }
}
