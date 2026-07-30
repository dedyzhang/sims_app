<?php

namespace App\Observers;

use App\Models\Ngajar;
use App\Models\GameQuiz;
use App\Models\User;
use App\Services\ClassroomService;
use App\Services\GrupChatService;

class NgajarObserver
{
    /**
     * Kelas yang sudah disinkron grup chat-nya dalam proses ini. Menugaskan
     * guru yang sama ke beberapa mapel di kelas yang SAMA (mis. lewat impor
     * massal) memicu beberapa baris Ngajar untuk satu kelas — tanpa dedup ini
     * tiap baris memicu syncKelas() penuh ulang padahal keanggotaan kelas
     * tersebut sudah benar sejak baris pertama.
     *
     * @var array<string, true>
     */
    private static array $sudahSinkron = [];

    /**
     * Handle the Ngajar "created" event.
     */
    public function created(Ngajar $ngajar): void
    {
        // 1. Pastikan Classroom dibuat otomatis
        if ($ngajar->kelas && $ngajar->pelajaran) {
            $service = app(ClassroomService::class);
            
            // Tentukan guru / pembuat
            $admin = User::where('access', 'admin')->first();
            $user = ($ngajar->guru && $ngajar->guru->user) ? $ngajar->guru->user : $admin;
            
            if ($user) {
                // subjectRoom akan membuat classroom jika belum ada
                $classroom = $service->subjectRoom($ngajar->kelas, $ngajar->pelajaran, $user);
                
                // 2. Buat Arena Belajar (GameQuiz) otomatis jika belum ada di classroom tersebut
                $existingQuiz = GameQuiz::where('classroom_id', $classroom->uuid)->first();
                if (!$existingQuiz) {
                    GameQuiz::create([
                        'classroom_id' => $classroom->uuid,
                        'created_by' => $classroom->created_by,
                        'title' => 'Arena Belajar — ' . $classroom->pelajaran?->nama,
                        'instructions' => 'Selamat datang di Arena Belajar! Tambahkan soal interaktif di sini.',
                        'mode' => 'async',
                        'scoring_mode' => 'accuracy',
                        'max_score' => 100,
                        'instant_feedback' => true,
                        'show_leaderboard' => true,
                        'status' => 'draft', // Biarkan draft agar guru bisa edit dulu
                    ]);
                }
            }
        }

        // 3. Guru pengajar baru ikut masuk Grup Kelas (bukan grup paguyuban).
        $this->syncGrupChatSekaliPerKelas($ngajar);
    }

    /**
     * Handle the Ngajar "updated" event.
     */
    public function updated(Ngajar $ngajar): void
    {
        // Optional: Jika guru / pelajaran diubah, kita mungkin perlu melakukan penyesuaian
        // Tapi untuk saat ini cukup handle "created" sesuai instruksi.
    }

    /**
     * Penugasan dicabut → guru dikeluarkan dari Grup Kelas, kecuali ia masih
     * mengajar mapel lain di kelas itu (dicek ulang oleh rekonsiliasi penuh).
     */
    public function deleted(Ngajar $ngajar): void
    {
        $this->syncGrupChat($ngajar);
    }

    /**
     * Dipakai khusus oleh created(): beberapa Ngajar baru untuk kelas yang SAMA
     * (guru diberi banyak mapel sekaligus) murni menambah anggota, jadi aman
     * di-dedup dalam satu proses. TIDAK dipakai oleh deleted() — urutan
     * create+delete untuk kelas yang sama dalam satu request tetap harus
     * memicu rekonsiliasi ulang setelah baris yang dihapus benar-benar hilang,
     * kalau tidak anggota yang seharusnya keluar bisa tertinggal.
     */
    private function syncGrupChatSekaliPerKelas(Ngajar $ngajar): void
    {
        if (! $ngajar->kelas || isset(self::$sudahSinkron[$ngajar->id_kelas])) {
            return;
        }
        self::$sudahSinkron[$ngajar->id_kelas] = true;

        app(GrupChatService::class)->syncKelas($ngajar->kelas);
    }

    private function syncGrupChat(Ngajar $ngajar): void
    {
        if ($ngajar->kelas) {
            app(GrupChatService::class)->syncKelas($ngajar->kelas);
        }
    }
}
