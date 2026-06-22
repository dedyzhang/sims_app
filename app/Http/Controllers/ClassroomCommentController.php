<?php

namespace App\Http\Controllers;

use App\Models\ClassroomAssignment;
use App\Models\ClassroomComment;
use App\Models\ClassroomMaterial;
use App\Models\User;
use App\Notifications\ClassroomCommentNotification;
use App\Support\Forum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Komentar + balasan (1 level) untuk materi & latihan/tugas. */
class ClassroomCommentController extends Controller
{
    public function storeMaterial(Request $request, ClassroomMaterial $material)
    {
        return $this->create($request, $material);
    }

    public function storeAssignment(Request $request, ClassroomAssignment $assignment)
    {
        return $this->create($request, $assignment);
    }

    public function destroy(ClassroomComment $comment)
    {
        $classroom = $comment->commentable?->classroom;
        abort_unless($classroom, 404);
        // Pemilik komentar atau yang mengelola ruang.
        abort_unless($comment->user_id === auth()->id() || auth()->user()->can('manage', $classroom), 403);

        $comment->delete();

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Komentar dihapus.');
    }

    private function create(Request $request, Model $commentable)
    {
        $classUuid = $request->input('class') ?: $request->query('class');
        $classroom = null;
        if ($classUuid) {
            $classroom = $commentable->classrooms()->where('uuid', $classUuid)->first();
        }
        if (!$classroom) {
            $user = $request->user();
            if ($user->access === 'siswa' && $user->siswa?->id_kelas) {
                $classroom = $commentable->classrooms()->where('id_kelas', $user->siswa->id_kelas)->first();
            }
            if (!$classroom && $user->guru) {
                $ids = \App\Models\Ngajar::where('id_guru', $user->guru->uuid)->pluck('id_kelas')->all();
                $classroom = $commentable->classrooms()->whereIn('id_kelas', $ids)->first();
            }
            if (!$classroom) {
                $classroom = $commentable->classroom;
            }
        }

        $this->authorize('view', $classroom);

        $data = $request->validate([
            'body'      => ['required', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'string', 'exists:classroom_comments,uuid'],
        ]);

        // Datarkan balasan ke 1 level (komentar induk teratas).
        $parentId = null;
        if (!empty($data['parent_id'])) {
            $parent = ClassroomComment::find($data['parent_id']);
            if ($parent) {
                $parentId = $parent->parent_id ?: $parent->uuid;
            }
        }

        $comment = ClassroomComment::create([
            'commentable_type' => $commentable::class,
            'commentable_id'   => $commentable->uuid,
            'classroom_id'     => $classroom->uuid,
            'user_id'          => $request->user()->uuid,
            'parent_id'        => $parentId,
            'body'             => Forum::sanitize($data['body']),
        ]);

        $this->notifyParticipants($commentable, $comment, $parentId);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'comment' => $comment,
            ]);
        }

        return back()->with('success', 'Komentar terkirim.');
    }

    /** Ambil komentar dalam format JSON untuk AlpineJS (Live Polling) */
    public function fetch(Request $request, string $type, string $uuid)
    {
        $commentable = null;
        if ($type === 'material') {
            $commentable = ClassroomMaterial::find($uuid);
        } elseif ($type === 'assignment') {
            $commentable = ClassroomAssignment::find($uuid);
        }
        abort_unless($commentable, 404);

        $classUuid = $request->query('class');
        $classroom = null;
        if ($classUuid) {
            $classroom = $commentable->classrooms()->where('uuid', $classUuid)->first();
        }
        if (!$classroom) {
            $user = $request->user();
            if ($user->access === 'siswa' && $user->siswa?->id_kelas) {
                $classroom = $commentable->classrooms()->where('id_kelas', $user->siswa->id_kelas)->first();
            }
            if (!$classroom && $user->guru) {
                $ids = \App\Models\Ngajar::where('id_guru', $user->guru->uuid)->pluck('id_kelas')->all();
                $classroom = $commentable->classrooms()->whereIn('id_kelas', $ids)->first();
            }
            if (!$classroom) {
                $classroom = $commentable->classroom;
            }
        }

        $this->authorize('view', $classroom);

        $comments = ClassroomComment::where('commentable_type', $commentable::class)
            ->where('commentable_id', $commentable->uuid)
            ->where('classroom_id', $classroom->uuid)
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->orderBy('created_at', 'asc')
            ->get();

        $formatted = $comments->map(function ($c) use ($classroom, $commentable) {
            return [
                'uuid' => $c->uuid,
                'body' => $c->body,
                'user_id' => $c->user_id,
                'created_at' => $c->created_at->locale('id')->diffForHumans(),
                'user_name' => $c->user?->displayName() ?? '?',
                'user_initial' => $c->user?->initial() ?? '?',
                'user_role' => $c->user?->roleLabel() ?? '?',
                'can_delete' => auth()->user()->can('manage', $classroom) || $c->user_id === auth()->id(),
                'replies' => $c->replies->map(function ($r) use ($commentable, $classroom) {
                    return [
                        'uuid' => $r->uuid,
                        'body' => $r->body,
                        'user_id' => $r->user_id,
                        'created_at' => $r->created_at->locale('id')->diffForHumans(),
                        'user_name' => $r->user?->displayName() ?? '?',
                        'user_initial' => $r->user?->initial() ?? '?',
                        'user_role' => $r->user?->roleLabel() ?? '?',
                        'can_delete' => auth()->user()->can('manage', $classroom) || $r->user_id === auth()->id(),
                    ];
                })
            ];
        });

        return response()->json([
            'ok' => true,
            'comments' => $formatted,
        ]);
    }

    /** Kirim notifikasi ke pembuat postingan, guru pengampu kelas aktif, & pembuat komentar induk */
    private function notifyParticipants(Model $commentable, ClassroomComment $comment, ?string $parentId): void
    {
        $recipients = collect();

        // 1. Pembuat postingan (material / assignment)
        $authorId = $commentable instanceof ClassroomMaterial
            ? $commentable->uploaded_by
            : $commentable->created_by;
        
        if ($authorId) {
            $recipients->push([
                'user_id' => $authorId,
                'type' => 'new_comment'
            ]);
        }

        // 2. Guru pengampu pelajaran di kelas aktif (terkait komentar)
        $activeClassroom = null;
        if ($comment->classroom_id) {
            $activeClassroom = \App\Models\Classroom::find($comment->classroom_id);
            if ($activeClassroom) {
                $teacherGuruUuids = \App\Models\Ngajar::where('id_kelas', $activeClassroom->id_kelas)
                    ->where('id_pelajaran', $activeClassroom->id_pelajaran)
                    ->pluck('id_guru')
                    ->filter()
                    ->toArray();
                
                $teacherUserUuids = \App\Models\Guru::whereIn('uuid', $teacherGuruUuids)
                    ->pluck('id_login')
                    ->filter()
                    ->toArray();

                foreach ($teacherUserUuids as $tuid) {
                    $recipients->push([
                        'user_id' => $tuid,
                        'type' => 'new_comment'
                    ]);
                }
            }
        }

        // 3. Pembuat komentar induk (jika balasan)
        if ($parentId && ($p = ClassroomComment::find($parentId))) {
            if ($p->user_id) {
                $recipients->push([
                    'user_id' => $p->user_id,
                    'type' => 'reply'
                ]);
            }
        }

        // Saring penerima duplikat dan jangan kirim ke diri sendiri
        $uniqueRecipients = [];
        foreach ($recipients as $r) {
            $uid = $r['user_id'];
            if ($uid === $comment->user_id) continue;
            
            if (!isset($uniqueRecipients[$uid]) || $r['type'] === 'reply') {
                $uniqueRecipients[$uid] = $r['type'];
            }
        }

        foreach ($uniqueRecipients as $uid => $type) {
            $u = User::find($uid);
            if (!$u) continue;

            // Jika tipe notifikasi adalah 'new_comment' dan user adalah admin/superadmin/guru/kesiswaan:
            // Semua peran selain siswa harus mengampu pelajaran tersebut di kelas aktif tersebut untuk menerima,
            // KECUALI jika user adalah pembuat ruang kelas itu sendiri.
            if ($type === 'new_comment' && $u->access !== 'siswa') {
                $classroom = $activeClassroom ?: $commentable->classroom;
                $allowed = false;
                if ($classroom) {
                    if ($u->uuid === $classroom->created_by) {
                        $allowed = true;
                    } elseif ($u->guru) {
                        $allowed = \App\Models\Ngajar::where('id_guru', $u->guru->uuid)
                            ->where('id_kelas', $classroom->id_kelas)
                            ->where('id_pelajaran', $classroom->id_pelajaran)
                            ->exists();
                    }
                }
                if (!$allowed) {
                    continue; // Lewati, tidak mengampu pelajaran ini di kelas ini
                }
            }

            $u->notify(new ClassroomCommentNotification($comment, $type));
        }
    }
}
