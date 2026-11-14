<?php

namespace App\Survei;

final class Pertanyaan
{
    public const SKALA = [
        '1' => 'Tidak pernah',
        '3' => 'Kadang-kadang',
        '5' => 'Selalu',
        '0' => 'Tidak tahu',
    ];

    // Tetap terima skala lama untuk formulir yang sudah dibuka sebelum pembaruan.
    public const NILAI_JAWABAN = [0, 1, 2, 3, 4, 5];

    public const KENDALA = [
        'penjelasan' => 'Penjelasan sulit dipahami',
        'materi' => 'Materi terlalu sulit',
        'tugas' => 'Tugas terlalu banyak',
        'ribut' => 'Kelas terlalu ribut',
        'fasilitas' => 'Alat atau fasilitas kurang',
        'tidak_ada' => 'Tidak ada kendala',
        'lainnya' => 'Lainnya',
    ];

    public const KENDALA_WALI = [
        'ya' => 'Ya, ada kendala',
        'tidak' => 'Tidak ada kendala',
    ];

    public static function kendala(string $jenis): array
    {
        return $jenis === 'wali' ? self::KENDALA_WALI : self::KENDALA;
    }

    public static function untuk(string $jenis): array
    {
        return $jenis === 'wali' ? [
            'Wali kelas hadir tepat waktu saat pembinaan pagi.',
            'Wali kelas hadir tepat waktu saat jam pulang.',
            'Wali kelas mengawasi siswa pada kegiatan setelah pulang sekolah (misalnya piket kelas).',
            'Wali kelas memberi semangat kepada kelas saat pembinaan.',
            'Wali kelas mendengarkan saya ketika saya menceritakan masalah.',
            'Wali kelas meneruskan informasi sekolah ke grup kelas dengan cepat.',
        ] : [
            'Guru masuk kelas paling lambat 5 menit setelah bel.',
            'Di awal pelajaran, guru menjelaskan apa yang akan dipelajari hari itu.',
            'Guru menjelaskan materi dengan cara yang mudah saya pahami.',
            'Guru memakai cara yang bervariasi (contoh, gambar, alat, praktik, atau diskusi).',
            'Guru memberi kesempatan bertanya dan menjawabnya dengan sabar.',
            'Guru mengembalikan dan membahas tugas atau ulangan saya.',
            'Guru memberi semangat atau pujian ketika saya berusaha belajar.',
            'Guru berbicara dengan nada marah, membentak, atau mempermalukan siswa di depan kelas.',
            'Guru lebih memperhatikan siswa tertentu saja dibanding siswa lain.',
            'Guru membiarkan kelas ribut sehingga sulit belajar.',
        ];
    }

    public static function dibalik(string $jenis): array
    {
        return $jenis === 'mapel' ? [7, 8, 9] : [];
    }

    public static function skor(string $jenis, int $index, int $jawaban, ?array $dibalik = null): ?int
    {
        if ($jawaban < 1 || $jawaban > 5) {
            return null;
        }

        return in_array($index, $dibalik ?? self::dibalik($jenis), true) ? 6 - $jawaban : $jawaban;
    }

    public static function terbuka(string $jenis): array
    {
        return $jenis === 'wali'
            ? ['Jika ada kendala dengan wali kelasmu, ceritakan kendalamu.']
            : ['Satu hal yang sudah baik dari cara guru mengajar.', 'Satu hal yang perlu diperbaiki dari cara guru mengajar.'];
    }
}
