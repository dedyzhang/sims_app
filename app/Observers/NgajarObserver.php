<?php

namespace App\Observers;

use App\Models\Ngajar;
use App\Models\GameQuiz;
use App\Models\User;
use App\Services\ClassroomService;

class NgajarObserver
{
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
     * Handle the Ngajar "deleted" event.
     *
     * Tidak ada apa pun untuk Grup Chat di sini — guru pengajar/mapel bukan
     * anggota Grup Kelas (lihat GrupChatService), jadi penugasan Ngajar
     * dicabut/ditambah tidak pernah memengaruhi keanggotaan grup chat.
     */
    public function deleted(Ngajar $ngajar): void
    {
        //
    }
}
