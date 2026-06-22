<?php

namespace Database\Seeders;

use App\Models\ForumComment;
use App\Models\ForumTopic;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Orangtua;
use App\Models\User;
use App\Support\Forum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Contoh isi forum: 1 topik per kategori + balasan nested + 1 jawaban terbaik. */
class ForumContentSeeder extends Seeder
{
    public function run(): void
    {
        $kelas = Kelas::where('tingkat', 7)->where('kelas', 'A')->first() ?? Kelas::first();

        $ngajar   = $kelas ? Ngajar::with('guru.user')->where('id_kelas', $kelas->uuid)->whereNotNull('id_pelajaran')->first() : null;
        $guruUser = $ngajar?->guru?->user ?? User::where('access', 'guru')->first();
        $siswaU   = $kelas ? User::whereHas('siswa', fn ($q) => $q->where('id_kelas', $kelas->uuid))->first() : User::where('access', 'siswa')->first();
        $ortuU    = $kelas ? User::whereHas('orangtuaRecords', fn ($q) => $q->whereIn('id_siswa', \App\Models\Siswa::where('id_kelas', $kelas->uuid)->pluck('uuid')))->first() : null;
        $adminU   = User::whereIn('access', ['admin', 'superadmin'])->first();
        $kepalaU  = User::where('access', 'kepala')->first();
        $kesisU   = User::where('access', 'kesiswaan')->first();
        $saprasU  = User::where('access', 'sapras')->first();

        if (!$guruUser && !$adminU) {
            $this->command?->warn('ForumContentSeeder: tidak ada user guru/admin, dilewati.');
            return;
        }

        // 1) AKADEMIK (termasuk ortu) — dengan balasan nested + jawaban terbaik.
        $t1 = $this->topic([
            'id_kelas' => $kelas?->uuid, 'id_pelajaran' => $ngajar?->id_pelajaran,
            'created_by' => ($guruUser ?? $adminU)->uuid,
            'title' => 'Diskusi Materi: Operasi Bilangan Bulat',
            'body' => 'Selamat datang di forum mapel. Silakan tanyakan hal yang belum jelas dari materi minggu ini.',
            'audience' => 'termasuk_ortu', 'category' => 'akademik',
        ]);
        if ($t1) {
            $c1 = $this->comment($t1, $siswaU ?? $guruUser, 'Pak/Bu, untuk soal nomor 3 apakah hasilnya negatif?');
            if ($c1 && $guruUser) {
                $this->comment($t1, $guruUser, 'Betul, perhatikan tanda kurungnya ya.', $c1->uuid);
            }
            $best = $this->comment($t1, $guruUser ?? $adminU, 'Ringkasan: kerjakan dari dalam kurung dulu, lalu kalikan tandanya.');
            if ($best) {
                $best->update(['is_best_answer' => true]);
            }
            if ($ortuU) {
                $this->comment($t1, $ortuU, 'Terima kasih atas penjelasannya, akan kami dampingi belajar di rumah.');
            }
            $this->touch($t1);
        }

        // 2) KESISWAAN
        $t2 = $this->topic([
            'created_by' => ($kesisU ?? $adminU ?? $guruUser)->uuid,
            'title' => 'Pendaftaran Ekstrakurikuler Semester Ini',
            'body' => 'Pendaftaran ekskul dibuka. Silakan diskusikan pilihan kegiatan di sini.',
            'audience' => 'siswa_guru', 'category' => 'kesiswaan',
        ]);
        if ($t2) {
            $this->comment($t2, $siswaU ?? $guruUser ?? $adminU, 'Apakah boleh ikut lebih dari satu ekskul?');
            $this->touch($t2);
        }

        // 3) SARPRAS
        $t3 = $this->topic([
            'created_by' => ($saprasU ?? $adminU ?? $guruUser)->uuid,
            'title' => 'Laporan Kerusakan Proyektor Ruang 7A',
            'body' => 'Mohon dicek proyektor di ruang 7A kurang terang. Terima kasih.',
            'audience' => 'siswa_guru', 'category' => 'sarpras',
        ]);
        if ($t3) {
            if ($saprasU) {
                $this->comment($t3, $saprasU, 'Diterima, akan kami jadwalkan pengecekan.');
            }
            $this->touch($t3);
        }

        // 4) UMUM
        $t4 = $this->topic([
            'created_by' => ($adminU ?? $guruUser)->uuid,
            'title' => 'Selamat Datang di Forum Diskusi Sekolah',
            'body' => 'Gunakan forum ini dengan santun. Mari berdiskusi yang bermanfaat.',
            'audience' => 'siswa_guru', 'category' => 'umum',
        ]);
        $t4 && $this->touch($t4);

        // 5) PENGUMUMAN (pinned)
        $t5 = $this->topic([
            'created_by' => ($kepalaU ?? $adminU ?? $guruUser)->uuid,
            'title' => 'Pengumuman: Jadwal Penilaian Akhir Semester',
            'body' => 'PAS dilaksanakan mulai pekan depan. Detail menyusul dari wali kelas.',
            'audience' => 'termasuk_ortu', 'category' => 'pengumuman', 'is_pinned' => true,
        ]);
        $t5 && $this->touch($t5);
    }

    private function topic(array $attr): ?ForumTopic
    {
        if (empty($attr['created_by'])) {
            return null;
        }
        return ForumTopic::create(array_merge([
            'slug' => Str::slug(Str::limit($attr['title'], 50, '')) . '-' . Str::lower(Str::random(6)),
            'body' => Forum::sanitize($attr['body'] ?? ''),
            'last_activity_at' => now(),
        ], $attr, ['body' => Forum::sanitize($attr['body'] ?? '')]));
    }

    private function comment(ForumTopic $topic, ?User $user, string $body, ?string $parentId = null): ?ForumComment
    {
        if (!$user) {
            return null;
        }
        return ForumComment::create([
            'topic_id'  => $topic->uuid,
            'user_id'   => $user->uuid,
            'parent_id' => $parentId,
            'body'      => Forum::sanitize($body),
        ]);
    }

    private function touch(ForumTopic $topic): void
    {
        $topic->update([
            'replies_count'    => ForumComment::where('topic_id', $topic->uuid)->count(),
            'last_activity_at' => now(),
        ]);
    }
}
