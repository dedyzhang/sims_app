<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Status Aktif Modul Demo
    |--------------------------------------------------------------------------
    | Jika bernilai FALSE (default untuk keamanan), seluruh route /demo-access/*
    | tidak akan didaftarkan, mengembalikan 404 murni, dan sesi demo lama
    | yang masih aktif akan langsung ditendang keluar oleh DemoSessionGuard.
    |
    | Cukup set DEMO_ENABLED=true di file .env subdomain demo Anda.
    |
    */
    'enabled' => env('DEMO_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Pembatasan Domain / Subdomain Demo (Opsional tapi Direkomendasikan)
    |--------------------------------------------------------------------------
    | Jika diisi (contoh: 'demo.sekolah.id' atau 'demo.btive.sch.id'), maka
    | fitur demo HANYA bisa diakses saat request masuk via hostname tersebut.
    | Domain utama / production tidak akan pernah merespons route demo ini.
    |
    */
    'allowed_domain' => env('DEMO_ALLOWED_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Kunci Rahasia Katalog Demo
    |--------------------------------------------------------------------------
    | Digunakan untuk membuka halaman katalog/generator QR code bagi admin/marketing:
    | URL: /demo-access/showcase?key={secret_key}
    |
    */
    'secret_key' => env('DEMO_SECRET_KEY', 'btive-demo-fl-2026'),

    /*
    |--------------------------------------------------------------------------
    | Daftar Fitur Demo & Mapping Target URL
    |--------------------------------------------------------------------------
    | Masing-masing fitur memiliki slug unik, deskripsi, peran dummy yang dipakai,
    | serta URL tujuan (deep-link) langsung ke fitur tersebut.
    |
    */
    'features' => [
        'arena-belajar' => [
            'title'       => 'Arena Belajar & Jagat Misi',
            'badge'       => 'Siswa / Gamifikasi',
            'description' => 'Kuis gamifikasi interaktif, kustomisasi avatar, perolehan XP, dan leaderboard kelas.',
            'role'        => 'siswa',
            'target_url'  => '/jagat-misi',
            'icon'        => 'gamepad',
        ],
        'cbt-ujian' => [
            'title'       => 'CBT / Ujian Online Siswa',
            'badge'       => 'Siswa / Ujian',
            'description' => 'Antarmuka ujian online responsif anti-curang dari sudut pandang siswa yang sedang mengerjakan soal.',
            'role'        => 'siswa',
            'target_url'  => '/ujian/saya',
            'icon'        => 'clipboard-check',
        ],
        'ai-guru' => [
            'title'       => 'Asisten Guru AI & Generator RPP',
            'badge'       => 'Guru / Kecerdasan Buatan',
            'description' => 'Studio pembuatan modul ajar otomatis, ringkasan materi, soal kuis, dan slide presentasi AI.',
            'role'        => 'guru',
            'target_url'  => '/ai/teacher',
            'icon'        => 'sparkles',
        ],
        'ruang-kelas' => [
            'title'       => 'Ruang Kelas Digital & LMS',
            'badge'       => 'Guru / Pembelajaran',
            'description' => 'Manajemen materi bahan ajar, penugasan tugas kelas, dan interaksi pembelajaran daring.',
            'role'        => 'guru',
            'target_url'  => '/ruang-kelas',
            'icon'        => 'academic-cap',
        ],
        'piket-guru' => [
            'title'       => 'Piket Guru & Substitusi Kelas',
            'badge'       => 'Admin / Guru Piket',
            'description' => 'Alur pelaporan guru tidak hadir, penugasan guru pengganti harian, dan distribusi materi kelas.',
            'role'        => 'admin',
            'target_url'  => '/piket/penugasan',
            'icon'        => 'user-group',
        ],
        'kalender-absensi' => [
            'title'       => 'Presensi & Rekap Kehadiran',
            'badge'       => 'Kepala Sekolah / Kurikulum',
            'description' => 'Dashboard matriks kehadiran guru & siswa, rekapitulasi bulanan, dan audit kehadiran.',
            'role'        => 'kepala',
            'target_url'  => '/kalender-absensi',
            'icon'        => 'calendar',
        ],
        'keuangan-spp' => [
            'title'       => 'Keuangan & Tagihan SPP',
            'badge'       => 'Bendahara / Keuangan',
            'description' => 'Pencatatan pembayaran SPP siswa, monitoring tunggakan, rekap kwitansi, dan kas masuk.',
            'role'        => 'bendahara',
            'target_url'  => '/keuangan',
            'icon'        => 'banknotes',
        ],
    ],
];
