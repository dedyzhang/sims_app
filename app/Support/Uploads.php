<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

final class Uploads
{
    /**
     * Ekstensi aman untuk menyimpan file upload. Ekstensi dari nama file klien
     * hanya dipakai bila termasuk daftar yang diizinkan; selain itu pakai tebakan
     * dari isi file, dan terakhir fallback. Mencegah file valid (lolos validasi
     * `mimes:`) tapi bernama *.php tersimpan — lalu dieksekusi — di folder publik.
     */
    public static function safeExtension(UploadedFile $file, array $allowed, string $fallback): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, $allowed, true)) {
            return $ext;
        }

        $guessed = strtolower((string) $file->guessExtension());

        return in_array($guessed, $allowed, true) ? $guessed : $fallback;
    }
}
