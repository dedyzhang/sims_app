<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomSubmission;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Walikelas;
use App\Services\ClassroomService;
use Illuminate\Http\Request;

/**
 * Ruang Kelas (model baru): otomatis per rombel. Index = daftar kelas; masuk kelas =
 * daftar mapel (dari jam ngajar); masuk mapel = ruang materi/tugas (auto-provision).
 */
class ClassroomController extends Controller
{
    public function __construct(private ClassroomService $service)
    {
    }

    /** Daftar kelas (rombel) sesuai peran. */
    public function index(Request $request)
    {
        $user = $request->user();
        $kelasList = $this->kelasForUser($user);
        $ids = $kelasList->pluck('uuid')->all();

        $mapelCounts = $ids ? Ngajar::whereIn('id_kelas', $ids)->whereNotNull('id_pelajaran')
            ->selectRaw('id_kelas, COUNT(DISTINCT id_pelajaran) c')->groupBy('id_kelas')->pluck('c', 'id_kelas') : collect();
        $siswaCounts = $ids ? Siswa::whereIn('id_kelas', $ids)
            ->selectRaw('id_kelas, COUNT(*) c')->groupBy('id_kelas')->pluck('c', 'id_kelas') : collect();

        return view('classroom.index', compact('kelasList', 'mapelCounts', 'siswaCounts'));
    }

    /** Daftar mata pelajaran dalam satu kelas (dari penugasan mengajar). */
    public function kelas(Request $request, Kelas $kelas)
    {
        $user = $request->user();
        abort_unless($this->canSeeKelas($user, $kelas), 403);

        $query = Ngajar::with(['pelajaran', 'guru'])
            ->where('id_kelas', $kelas->uuid)->whereNotNull('id_pelajaran');

        // Jika guru (bukan admin/kepala/kurikulum), hanya tampilkan pelajaran yang diajar sendiri
        if (!$user->isAdmin() && !in_array($user->access, ['kepala', 'kurikulum'], true) && $user->guru) {
            $query->where('id_guru', $user->guru->uuid);
        }

        $ngajars = $query->get()
            ->sortBy(fn ($n) => [$n->pelajaran?->urutan ?? 99, $n->pelajaran?->nama])->values();

        // Mapel yang diampu user (guru) di kelas ini → boleh mengelola.
        $myPelajaran = [];
        if ($user->guru) {
            $myPelajaran = Ngajar::where('id_guru', $user->guru->uuid)->where('id_kelas', $kelas->uuid)
                ->pluck('id_pelajaran')->all();
        }

        return view('classroom.kelas', compact('kelas', 'ngajars', 'myPelajaran'));
    }

    /** Masuk ruang satu mapel: provision otomatis lalu tampilkan. */
    public function subject(Request $request, Kelas $kelas, Pelajaran $pelajaran)
    {
        $user = $request->user();
        abort_unless($this->canSeeKelas($user, $kelas), 403);

        // Jika guru (bukan admin/kepala/kurikulum), wajib mengajar pelajaran ini di kelas ini
        if (!$user->isAdmin() && !in_array($user->access, ['kepala', 'kurikulum'], true) && $user->guru) {
            $teaches = Ngajar::where('id_guru', $user->guru->uuid)
                ->where('id_kelas', $kelas->uuid)
                ->where('id_pelajaran', $pelajaran->uuid)
                ->exists();
            abort_unless($teaches, 403);
        }

        $classroom = $this->service->subjectRoom($kelas, $pelajaran, $user);

        return redirect()->route('classroom.show', $classroom);
    }

    public function show(Request $request, Classroom $classroom)
    {
        $this->authorize('view', $classroom);
        $user = $request->user();

        $classroom->load([
            'pelajaran', 'rombel', 'kelas', 'author', 'forumTopic',
            'members.user',
            'materials' => fn ($q) => $q->orderBy('sort_order')->latest()->withCount('comments'),
            'assignments' => fn ($q) => $q->latest()->withCount(['submissions', 'comments']),
        ]);

        $canManage = $user->can('manage', $classroom);

        $mySubmissions = [];
        if ($user->access === 'siswa') {
            $mySubmissions = ClassroomSubmission::where('student_id', $user->uuid)
                ->whereIn('assignment_id', $classroom->assignments->pluck('uuid'))
                ->get()->keyBy('assignment_id');
        }

        return view('classroom.show', compact('classroom', 'canManage', 'mySubmissions'));
    }

    // ─── Helper lingkup ───

    private function kelasForUser(User $user)
    {
        if ($user->isAdmin() || in_array($user->access, ['kepala', 'kurikulum'], true)) {
            return Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        }

        if ($user->guru) {
            $ids = $this->guruKelasIds($user);
            return $ids ? Kelas::whereIn('uuid', $ids)->orderBy('tingkat')->orderBy('kelas')->get() : collect();
        }

        $ids = match ($user->access) {
            'siswa'    => array_filter([$user->siswa?->id_kelas]),
            'orangtua' => $user->childrenClassroomIds(),
            default    => [],
        };
        return $ids ? Kelas::whereIn('uuid', $ids)->orderBy('tingkat')->orderBy('kelas')->get() : collect();
    }

    private function canSeeKelas(User $user, Kelas $kelas): bool
    {
        if ($user->isAdmin() || in_array($user->access, ['kepala', 'kurikulum'], true)) {
            return true;
        }

        if ($user->guru) {
            return in_array($kelas->uuid, $this->guruKelasIds($user), true);
        }

        return match ($user->access) {
            'siswa'    => $user->siswa?->id_kelas === $kelas->uuid,
            'orangtua' => in_array($kelas->uuid, $user->childrenClassroomIds(), true),
            default    => false,
        };
    }

    private function guruKelasIds(User $user): array
    {
        $guru = $user->guru;
        if (!$guru) {
            return [];
        }
        $ajar = Ngajar::where('id_guru', $guru->uuid)->pluck('id_kelas')->all();
        $wali = Walikelas::where('id_guru', $guru->uuid)->pluck('id_kelas')->all();
        return array_values(array_unique(array_filter(array_merge($ajar, $wali))));
    }
}
