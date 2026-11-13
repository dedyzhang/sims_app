<?php
namespace App\Survei;
final class Pertanyaan {
    public static function untuk(string $jenis): array {
        return $jenis === 'wali' ? [
            'Wali kelas menyampaikan informasi kelas dengan jelas.',
            'Wali kelas mendengarkan siswa yang menyampaikan masalah di kelas.',
            'Wali kelas membantu menyelesaikan masalah yang terjadi di kelas.',
            'Wali kelas memperlakukan siswa dengan adil.',
            'Wali kelas membantu menjaga suasana kelas agar nyaman.',
            'Saya tahu cara menghubungi wali kelas jika membutuhkan bantuan.',
        ] : [
            'Guru memulai pelajaran tepat waktu.',
            'Guru menjelaskan hal yang akan dipelajari.',
            'Guru menjelaskan materi dengan cara yang mudah saya pahami.',
            'Guru memberikan contoh yang membantu saya memahami materi.',
            'Guru memberi kesempatan kepada siswa untuk bertanya.',
            'Guru menjelaskan kembali materi ketika siswa belum memahaminya.',
            'Guru menjelaskan tugas atau latihan dengan jelas.',
            'Guru membantu siswa memahami kesalahan dalam tugas atau latihan.',
            'Guru berbicara dengan sopan kepada siswa.',
            'Guru menghargai siswa yang bertanya atau menyampaikan pendapat.',
            'Guru memperlakukan siswa dengan adil.',
            'Guru membantu menjaga suasana kelas agar siswa dapat belajar dengan nyaman.',
        ];
    }
    public static function terbuka(string $jenis): array {
        return $jenis === 'wali' ? ['Apa yang kamu sukai dari pendampingan wali kelas?', 'Apa saranmu untuk wali kelas?'] : [
            'Apa yang kamu sukai dari cara guru mengajar?', 'Apa saranmu agar pembelajaran ini lebih mudah kamu pahami?', 'Kesulitan apa yang kamu alami dalam pelajaran ini?'
        ];
    }
}
