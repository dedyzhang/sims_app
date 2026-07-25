<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Mesin pengenalan wajah yang aktif: 'human' (Human.js, default — dipakai bertahun-tahun,
 * data face_descriptor sudah ada utk semua siswa/guru) atau 'insightface' (ArcFace via ONNX,
 * dicoba 22 Jul 2026 atas permintaan eksplisit utk akurasi lebih tinggi).
 *
 * PENTING — lisensi: model InsightFace (buffalo_s) berlisensi non-komersial (riset saja) dari
 * proyek InsightFace asli. Karena aplikasi ini rencananya DIJUAL ke sekolah lain (produk
 * B'tive LMS), mesin 'insightface' HANYA boleh dipakai utk pemakaian internal SMP Maitreyawira
 * sendiri — JANGAN ikut disertakan/diaktifkan kalau aplikasi ini didistribusikan/dijual ke
 * sekolah lain, sampai ada model pengganti berlisensi komersial.
 *
 * Data descriptor kedua mesin disimpan di KOLOM TERPISAH (face_descriptor vs
 * face_descriptor_if) — pindah setting ini TIDAK PERNAH menghapus data mesin yang lain,
 * jadi bisa dibalik ke 'human' kapan saja tanpa kehilangan wajah yang sudah terdaftar lewat
 * Human.js.
 */
class FaceEngine
{
    public const ENGINES = [
        'human'       => 'Human.js (default)',
        'insightface' => 'InsightFace / ArcFace',
    ];

    public static function aktif(): string
    {
        $e = Setting::get('face_engine', 'human');

        return array_key_exists($e, self::ENGINES) ? $e : 'human';
    }

    public static function isInsightFace(): bool
    {
        return self::aktif() === 'insightface';
    }

    /** Kolom face_descriptor yang relevan utk mesin yang sedang aktif. */
    public static function kolomDescriptor(): string
    {
        return self::isInsightFace() ? 'face_descriptor_if' : 'face_descriptor';
    }
}
