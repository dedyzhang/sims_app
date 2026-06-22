<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\ForumTopic;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Support\Audit;
use App\Support\Forum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClassroomService
{
    /**
     * Buat Ruang Kelas secara ATOMIK: classroom + pivot rombel + auto-enroll siswa +
     * forum diskusi otomatis. Gagal di mana pun → rollback semua.
     */
    public function create(array $data, User $author): Classroom
    {
        return DB::transaction(function () use ($data, $author) {
            [$status, $publishedAt, $scheduledAt] = $this->resolveStatus($data);

            $classroom = Classroom::create([
                'id_semester'          => $data['id_semester'] ?? null,
                'id_pelajaran'         => $data['id_pelajaran'] ?? null,
                'created_by'           => $author->uuid,
                'title'                => $data['title'],
                'description'          => $data['description'] ?? null,
                'cover_color'          => $data['cover_color'] ?? '#2563eb',
                'status'               => $status,
                'published_at'         => $publishedAt,
                'scheduled_publish_at' => $scheduledAt,
                'class_code'           => $this->generateCode(),
            ]);

            $kelasIds = array_values(array_unique($data['kelas'] ?? []));
            $this->syncKelas($classroom, $kelasIds);
            $this->enrollKelasStudents($classroom, $kelasIds);

            // Forum diskusi otomatis ter-link (classroom_id ter-set).
            ForumTopic::create([
                'classroom_id'     => $classroom->uuid,
                'id_kelas'         => $kelasIds[0] ?? null,
                'id_pelajaran'     => $classroom->id_pelajaran,
                'created_by'       => $author->uuid,
                'title'            => 'Diskusi — ' . $classroom->title,
                'slug'             => Str::slug(Str::limit('diskusi-' . $classroom->title, 50, '')) . '-' . Str::lower(Str::random(6)),
                'body'             => Forum::sanitize('Ruang diskusi untuk kelas digital "' . $classroom->title . '". Silakan berdiskusi di sini.'),
                'audience'         => 'siswa_guru',
                'category'         => 'akademik',
                'last_activity_at' => now(),
            ]);

            Audit::log('classroom_create', $classroom, ['title' => $classroom->title, 'status' => $status]);

            return $classroom;
        });
    }

    public function update(Classroom $classroom, array $data): Classroom
    {
        return DB::transaction(function () use ($classroom, $data) {
            $classroom->update([
                'id_semester'  => $data['id_semester'] ?? $classroom->id_semester,
                'id_pelajaran' => $data['id_pelajaran'] ?? null,
                'title'        => $data['title'],
                'description'  => $data['description'] ?? null,
                'cover_color'  => $data['cover_color'] ?? $classroom->cover_color,
            ]);

            if (isset($data['kelas'])) {
                $kelasIds = array_values(array_unique($data['kelas']));
                $this->syncKelas($classroom, $kelasIds);
                $this->enrollKelasStudents($classroom, $kelasIds); // tambah anggota baru saja (anti-duplikat)
            }

            Audit::log('classroom_update', $classroom);
            return $classroom;
        });
    }

    /**
     * Ambil/provision ruang kelas untuk satu (kelas, mapel) — otomatis dibuat saat
     * pertama diakses, langsung terbit. Pembuat = guru pengampu (ngajar) bila ada.
     */
    public function subjectRoom(Kelas $kelas, Pelajaran $pelajaran, User $actor): Classroom
    {
        $existing = Classroom::where('id_kelas', $kelas->uuid)->where('id_pelajaran', $pelajaran->uuid)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($kelas, $pelajaran, $actor) {
            $ngajar = Ngajar::where('id_kelas', $kelas->uuid)->where('id_pelajaran', $pelajaran->uuid)->first();
            $creator = $ngajar?->guru?->id_login ?: $actor->uuid;  // id_login = users.uuid
            $colors = ['#2563eb', '#0891b2', '#059669', '#d97706', '#dc2626', '#7c3aed', '#db2777', '#475569'];

            $classroom = Classroom::create([
                'id_kelas'     => $kelas->uuid,
                'id_pelajaran' => $pelajaran->uuid,
                'id_semester'  => Semester::aktif()?->id,
                'created_by'   => $creator,
                'title'        => $pelajaran->nama . ' — Kelas ' . $kelas->tingkat . $kelas->kelas,
                'cover_color'  => $colors[abs(crc32($pelajaran->uuid)) % count($colors)],
                'status'       => 'published',
                'published_at' => now(),
                'class_code'   => $this->generateCode(),
            ]);

            $this->syncKelas($classroom, [$kelas->uuid]);
            $this->enrollKelasStudents($classroom, [$kelas->uuid]);

            // Catatan: forum diskusi per-mapel TIDAK dibuat lagi (diskusi kini per
            // materi & latihan via classroom_comments).

            Audit::log('classroom_auto_create', $classroom, ['title' => $classroom->title]);

            return $classroom;
        });
    }

    /**
     * Tautkan satu konten (materi/latihan) ke beberapa kelas: pastikan ruang tiap
     * kelas ada (subjectRoom) lalu sinkronkan pivot. Kelas asal selalu disertakan.
     */
    public function linkToKelas(\Illuminate\Database\Eloquent\Model $content, array $kelasIds, Classroom $origin, User $user): void
    {
        $pelajaran = $origin->pelajaran ?: Pelajaran::find($origin->id_pelajaran);
        if (!$pelajaran) {
            $content->classrooms()->sync([$origin->uuid]);
            return;
        }

        $kelasIds = array_values(array_unique(array_filter(array_merge([$origin->id_kelas], $kelasIds))));
        $roomIds = [];
        foreach ($kelasIds as $kid) {
            $k = Kelas::find($kid);
            if ($k) {
                $roomIds[] = $this->subjectRoom($k, $pelajaran, $user)->uuid;
            }
        }
        $content->classrooms()->sync($roomIds);
    }

    /** Terbitkan sekarang. */
    public function publish(Classroom $classroom): void
    {
        $classroom->update(['status' => 'published', 'published_at' => now(), 'scheduled_publish_at' => null]);
        Audit::log('classroom_publish', $classroom);
    }

    /**
     * Sinkron pivot rombel. Pivot pakai UUID PK yang TIDAK diisi otomatis oleh sync(),
     * jadi insert manual dengan uuid (set ulang penuh: hapus lalu masukkan).
     */
    private function syncKelas(Classroom $classroom, array $kelasIds): void
    {
        DB::table('classroom_kelas')->where('classroom_id', $classroom->uuid)->delete();
        if (empty($kelasIds)) {
            return;
        }
        $rows = array_map(fn ($id) => [
            'uuid'         => (string) Str::uuid(),
            'classroom_id' => $classroom->uuid,
            'id_kelas'     => $id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ], $kelasIds);
        DB::table('classroom_kelas')->insert($rows);
    }

    /** Auto-enroll seluruh siswa pada rombel terpilih (anti-duplikat via firstOrCreate). */
    private function enrollKelasStudents(Classroom $classroom, array $kelasIds): void
    {
        if (empty($kelasIds)) {
            return;
        }
        $userIds = Siswa::whereIn('id_kelas', $kelasIds)->pluck('id_login')->filter()->unique();
        foreach ($userIds as $uid) {
            ClassroomMember::firstOrCreate(
                ['classroom_id' => $classroom->uuid, 'user_id' => $uid],
                ['role_in_class' => 'siswa', 'joined_at' => now()]
            );
        }
    }

    /** @return array{0:string,1:?\Illuminate\Support\Carbon,2:?\Illuminate\Support\Carbon} [status, published_at, scheduled_at] */
    private function resolveStatus(array $data): array
    {
        $mode = $data['publish_mode'] ?? 'draft';

        if ($mode === 'now') {
            return ['published', now(), null];
        }
        if ($mode === 'schedule' && !empty($data['scheduled_publish_at'])) {
            $at = \Illuminate\Support\Carbon::parse($data['scheduled_publish_at']);
            return $at->isFuture() ? ['scheduled', null, $at] : ['published', now(), null];
        }
        return ['draft', null, null];
    }

    private function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(3) . '-' . Str::random(4));
        } while (Classroom::where('class_code', $code)->exists());

        return $code;
    }
}
