<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Daftar Intent & Kata Kunci (rule-based, ZERO-COST, tanpa LLM)
    |--------------------------------------------------------------------------
    | Dipakai IntentMatcher untuk mendeteksi maksud pesan. Pada integrasi SIMS
    | (mode "handoff + chat"), bot tidak menjawab data (SPP/nilai/absensi/jadwal)
    | otomatis — semua diarahkan ke admin manusia. Intent tetap dipertahankan
    | agar bantuan/menu & deteksi maksud bisa dipakai bila nanti diwiring ke data.
    */
    'intents' => [
        'cek_spp' => ['spp', 'tagihan', 'bayar', 'tunggakan', 'biaya'],
        'cek_nilai' => ['nilai', 'rapor', 'raport', 'hasil', 'ujian'],
        'cek_absensi' => ['absen', 'absensi', 'kehadiran', 'hadir', 'alpha', 'alpa', 'izin', 'sakit', 'rekap'],
        'cek_jadwal' => ['jadwal', 'pelajaran', 'jam pelajaran', 'jadwal kelas'],
        'cek_biodata' => ['biodata', 'data diri', 'data saya', 'profil', 'profile', 'identitas', 'nis', 'nisn'],
        'cek_walikelas' => ['wali kelas', 'walikelas', 'wali kelasku', 'homeroom', 'info kelas'],
        'bantuan' => ['bantuan', 'help', 'bisa apa', 'menu'],
    ],

    // Frasa untuk meminta dihubungkan ke admin manusia (dipakai tombol, bukan IntentMatcher).
    'handoff_keywords' => ['hubungkan ke admin', 'admin', 'manusia', 'operator'],

    // Polling Lapis B (detik). Hanya dipakai frontend sebagai acuan.
    'poll_interval_seconds' => 5,
];
