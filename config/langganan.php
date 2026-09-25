<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ambang Peringatan Langganan (dalam hari, dari yang paling dini)
    |--------------------------------------------------------------------------
    | Banner peringatan superadmin naik keparahan saat sisa hari menyentuh
    | ambang berikut: info (H-14), kuning (H-7), merah (H-3).
    */
    'peringatan' => [
        'info'   => (int) env('LANGGANAN_PERINGATAN_INFO', 30),
        'kuning' => (int) env('LANGGANAN_PERINGATAN_KUNING', 7),
        'merah'  => (int) env('LANGGANAN_PERINGATAN_MERAH', 3),
    ],

    // Pilihan durasi yang sah (bulan). Validasi controller mengacu ke sini.
    'durasi' => [3, 6, 12],
];
