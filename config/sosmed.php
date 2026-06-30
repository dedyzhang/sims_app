<?php

/*
|--------------------------------------------------------------------------
| Daftar platform media sosial sekolah
|--------------------------------------------------------------------------
| Dipakai oleh halaman Pengaturan (untuk form) dan Dashboard (untuk
| menampilkan ikon). Nilai/URL tiap platform disimpan di tabel settings
| dengan key: sosmed_{key}_url dan sosmed_{key}_on.
|
| type:
|   url   → tautan biasa (otomatis diberi https:// bila perlu)
|   wa    → nomor WhatsApp (diubah menjadi https://wa.me/<angka>)
|   email → alamat email (diubah menjadi mailto:)
*/

return [
    'youtube'   => ['label' => 'YouTube',     'type' => 'url',   'ph' => 'https://youtube.com/@sekolah'],
    'instagram' => ['label' => 'Instagram',   'type' => 'url',   'ph' => 'https://instagram.com/sekolah'],
    'tiktok'    => ['label' => 'TikTok',       'type' => 'url',   'ph' => 'https://tiktok.com/@sekolah'],
    'whatsapp'  => ['label' => 'WhatsApp',     'type' => 'wa',    'ph' => '6281234567890'],
    'facebook'  => ['label' => 'Facebook',     'type' => 'url',   'ph' => 'https://facebook.com/sekolah'],
    'twitter'   => ['label' => 'X (Twitter)',  'type' => 'url',   'ph' => 'https://x.com/sekolah'],
    'website'   => ['label' => 'Website',      'type' => 'url',   'ph' => 'https://sekolah.sch.id'],
    'email'     => ['label' => 'Email',        'type' => 'email', 'ph' => 'info@sekolah.sch.id'],
];
