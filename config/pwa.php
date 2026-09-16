<?php

return [
    /*
    | Splash screen iOS bawaan (public/splash/*.png) memuat logo + warna SATU sekolah.
    | SIMS dipasang di banyak sekolah dari basis kode yang sama, jadi aset ini hanya
    | boleh dipasang di deployment yang memang memilikinya. Default mati: sekolah lain
    | dapat splash putih standar iOS, bukan logo sekolah orang lain.
    */
    'splash_enabled' => filter_var(env('PWA_SPLASH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /* Warna tema PWA (status bar iOS + theme_color manifest). */
    'theme_color' => env('PWA_THEME_COLOR', '#1e1b4b'),
];
