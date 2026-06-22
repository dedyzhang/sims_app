<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreForumTopicRequest;
use App\Models\ForumComment;
use App\Models\ForumReaction;
use App\Models\ForumTopic;
use App\Models\ForumTopicRead;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\User;
use App\Models\Walikelas;
use App\Support\Forum;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ForumController extends Controller
{
    /**
     * Query topik yang BOLEH dilihat user — difilter di level DB (bukan disembunyikan
     * di view), sesuai matriks izin & lingkup.
     */
    private function visibleQuery(User $u)
    {
        $q = ForumTopic::query();

        if ($u->canForum('forum.view.all')) {
            return $q;
        }
        if (!$u->canForum('forum.view.scope')) {
            return $q->whereRaw('1 = 0');
        }

        return $q->where(function ($w) use ($u) {
            $w->where('created_by', $u->uuid);

            switch ($u->access) {
                case 'siswa':
                    $w->orWhereNull('id_kelas');
                    if ($k = $u->siswa?->id_kelas) {
                        $w->orWhere('id_kelas', $k);
                    }
                    break;

                case 'orangtua':
                    $ids = $u->childrenClassroomIds();
                    if ($ids) {
                        $w->orWhere(fn ($x) => $x->whereIn('id_kelas', $ids)->where('audience', 'termasuk_ortu'));
                    }
                    break;

                case 'guru':
                    $w->orWhereNull('id_kelas');
                    if ($ids = $this->guruKelasIds($u)) {
                        $w->orWhereIn('id_kelas', $ids);
                    }
                    break;

                default:
                    if ($cats = Forum::categoryScope((string) $u->access)) {
                        $w->orWhereIn('category', $cats);
                    }
            }
        });
    }

    private function guruKelasIds(User $u): array
    {
        $guru = $u->guru;
        if (!$guru) {
            return [];
        }
        $ajar = Ngajar::where('id_guru', $guru->uuid)->pluck('id_kelas')->all();
        $wali = Walikelas::where('id_guru', $guru->uuid)->pluck('id_kelas')->all();
        return array_values(array_unique(array_filter(array_merge($ajar, $wali))));
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', ForumTopic::class);
        $user = $request->user();

        $topics = $this->visibleQuery($user)
            ->with(['author', 'kelas', 'pelajaran'])
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('kelas'), fn ($q) => $q->where('id_kelas', $request->kelas))
            ->when($request->filled('q'), function ($q) use ($request) {
                $s = $request->q;
                $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('body', 'like', "%{$s}%"));
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        // Badge "balasan baru": last_activity_at > last_read_at
        $reads = ForumTopicRead::where('user_id', $user->uuid)
            ->whereIn('topic_id', $topics->pluck('uuid'))
            ->pluck('last_read_at', 'topic_id');

        $popular = $this->visibleQuery($user)
            ->with('author')
            ->orderByDesc('replies_count')
            ->limit(5)->get();

        $kelasList = $this->scopedKelas($user);

        return view('forum.index', [
            'topics'     => $topics,
            'reads'      => $reads,
            'popular'    => $popular,
            'kelasList'  => $kelasList,
            'categories' => Forum::CATEGORIES,
            'filter'     => $request->only(['category', 'kelas', 'q']),
        ]);
    }

    public function show(Request $request, ForumTopic $topic)
    {
        $this->authorize('view', $topic);
        $user = $request->user();

        $topic->load([
            'author', 'kelas', 'pelajaran',
            'comments' => fn ($q) => $q->whereNull('parent_id')
                ->with(['user', 'reactions', 'replies' => fn ($r) => $r->with(['user', 'reactions'])->orderBy('created_at')])
                ->orderByDesc('is_best_answer')->orderBy('created_at'),
        ]);

        // Tandai sudah dibaca.
        ForumTopicRead::updateOrCreate(
            ['user_id' => $user->uuid, 'topic_id' => $topic->uuid],
            ['last_read_at' => now()]
        );

        // Reaksi milik user (untuk state tombol Suka).
        $myReactions = ForumReaction::where('user_id', $user->uuid)
            ->where(fn ($q) => $q->where('topic_id', $topic->uuid)
                ->orWhereIn('comment_id', $this->allCommentIds($topic)))
            ->get();
        $likedTopic = $myReactions->firstWhere('topic_id', $topic->uuid) !== null;
        $likedComments = $myReactions->whereNotNull('comment_id')->pluck('comment_id')->all();

        $participants = $this->participants($topic);
        $popular = $this->visibleQuery($user)->where('uuid', '!=', $topic->uuid)
            ->orderByDesc('replies_count')->limit(5)->get();

        return view('forum.show', compact('topic', 'participants', 'popular', 'likedTopic', 'likedComments'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', ForumTopic::class);
        $user = $request->user();

        return view('forum.create', [
            'kelasList'     => $this->scopedKelas($user),
            'pelajaranList' => Pelajaran::orderBy('urutan')->orderBy('nama')->get(),
            'categories'    => Forum::CATEGORIES,
            'audiences'     => Forum::AUDIENCES,
            'canAnnounce'   => $user->canForum('forum.announce'),
        ]);
    }

    public function store(StoreForumTopicRequest $request)
    {
        $this->authorize('create', ForumTopic::class);

        if ($request->category === 'pengumuman') {
            $this->authorize('announce', ForumTopic::class);
        }

        $topic = ForumTopic::create([
            'id_kelas'         => $request->id_kelas ?: null,
            'id_pelajaran'     => $request->id_pelajaran ?: null,
            'created_by'       => $request->user()->uuid,
            'title'            => $request->title,
            'slug'             => Str::slug(Str::limit($request->title, 60, '')) . '-' . Str::lower(Str::random(6)),
            'body'             => Forum::sanitize($request->body),
            'audience'         => $request->audience,
            'category'         => $request->category,
            'last_activity_at' => now(),
        ]);

        Forum::audit('create_topic', $topic, ['title' => $topic->title]);

        return redirect()->route('forum.show', $topic)->with('success', 'Topik berhasil dibuat.');
    }

    public function edit(ForumTopic $topic)
    {
        $this->authorize('update', $topic);

        return view('forum.create', [
            'topic'         => $topic,
            'kelasList'     => $this->scopedKelas(auth()->user()),
            'pelajaranList' => Pelajaran::orderBy('urutan')->orderBy('nama')->get(),
            'categories'    => Forum::CATEGORIES,
            'audiences'     => Forum::AUDIENCES,
            'canAnnounce'   => auth()->user()->canForum('forum.announce'),
        ]);
    }

    public function update(StoreForumTopicRequest $request, ForumTopic $topic)
    {
        $this->authorize('update', $topic);

        $topic->update([
            'id_kelas'     => $request->id_kelas ?: null,
            'id_pelajaran' => $request->id_pelajaran ?: null,
            'title'        => $request->title,
            'body'         => Forum::sanitize($request->body),
            'audience'     => $request->audience,
            'category'     => $request->category,
        ]);

        Forum::audit('edit_topic', $topic);

        return redirect()->route('forum.show', $topic)->with('success', 'Topik diperbarui.');
    }

    public function destroy(ForumTopic $topic)
    {
        $this->authorize('delete', $topic);
        $topic->delete();
        Forum::audit('delete_topic', $topic, ['title' => $topic->title]);

        return redirect()->route('forum.index')->with('success', 'Topik dihapus.');
    }

    public function togglePin(ForumTopic $topic)
    {
        $this->authorize('moderate', $topic);
        $topic->update(['is_pinned' => !$topic->is_pinned]);
        Forum::audit($topic->is_pinned ? 'pin' : 'unpin', $topic);

        return back()->with('success', $topic->is_pinned ? 'Topik disematkan.' : 'Sematan dilepas.');
    }

    public function toggleLock(ForumTopic $topic)
    {
        $this->authorize('moderate', $topic);
        $topic->update(['is_locked' => !$topic->is_locked]);
        Forum::audit($topic->is_locked ? 'lock' : 'unlock', $topic);

        return back()->with('success', $topic->is_locked ? 'Topik dikunci.' : 'Kunci dibuka.');
    }

    /** Endpoint polling presence (JSON aman — tanpa last_seen_at mentah). */
    public function presence(Request $request, ForumTopic $topic)
    {
        $this->authorize('view', $topic);

        return response()->json([
            'participants' => collect($this->participants($topic))->map(fn ($u) => [
                'id'     => $u->uuid,
                'nama'   => $u->displayName(),
                'inisial' => $u->initial(),
                'role'   => $u->roleLabel(),
                'status' => $u->presenceStatus(),
                'label'  => $u->presenceLabel(),
            ])->values(),
            'online'  => collect($this->participants($topic))->filter->isOnline()->count(),
            'replies' => $topic->replies_count,
        ]);
    }

    /** Peserta aktif di topik: pembuat + pengomentar, urut online dulu, limit 10. */
    private function participants(ForumTopic $topic)
    {
        $ids = ForumComment::where('topic_id', $topic->uuid)->pluck('user_id')
            ->push($topic->created_by)->filter()->unique()->all();

        return User::whereIn('uuid', $ids)->get()
            ->sortByDesc(fn ($u) => $u->last_seen_at?->timestamp ?? 0)
            ->sortByDesc(fn ($u) => $u->isOnline() ? 1 : 0)
            ->take(10)->values();
    }

    private function allCommentIds(ForumTopic $topic): array
    {
        return ForumComment::where('topic_id', $topic->uuid)->pluck('uuid')->all();
    }

    /** Daftar kelas sesuai lingkup user (untuk filter & form). */
    private function scopedKelas(User $user)
    {
        if ($user->canForum('forum.view.all')) {
            return Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        }
        $ids = match ($user->access) {
            'guru'     => $this->guruKelasIds($user),
            'siswa'    => array_filter([$user->siswa?->id_kelas]),
            'orangtua' => $user->childrenClassroomIds(),
            default    => [],
        };
        return $ids ? Kelas::whereIn('uuid', $ids)->orderBy('tingkat')->orderBy('kelas')->get() : collect();
    }
}
