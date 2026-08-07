<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Validasi+sanitasi field soal (tipe/teks_soal/opsi/pasangan/kunci_esai) yg DIPAKAI
 * BERSAMA oleh UjianSoalController & BankSoalController — keduanya punya bentuk soal
 * identik (mcq/mcq_complex/true_false/match/essay), cuma beda induk (ujian vs mapel).
 * Disatukan di sini supaya perbaikan aturan (mis. sanitasi RichText, required_if utk
 * ConvertEmptyStringsToNull) otomatis berlaku di kedua tempat, tak perlu disalin manual.
 */
class SoalValidator
{
    /**
     * teks_soal/opsi.*.teks datang dari TinyMCE (bisa berisi rumus sbg <img> SVG data-uri
     * + gambar upload) — batas jauh lebih longgar drpd teks polos, meniru batas 'body' materi
     * Ruang Kelas (StoreClassroomMaterialRequest::rules(), max:200000) tapi lebih hemat krn
     * kolom DB di sini masih `text` (~64KB), bukan `longText`.
     */
    public static function validate(Request $request): array
    {
        $data = $request->validate([
            'tipe'                  => 'required|in:mcq,mcq_complex,true_false,match,essay',
            'teks_soal'             => 'required|string|max:60000',
            'poin'                  => 'required|integer|min:1|max:100',
            'penjelasan'            => 'nullable|string|max:2000',
            // mcq/mcq_complex/true_false. Field2 opsi/pasangan LAIN-tipe tetap ada di DOM
            // (cuma disembunyikan CSS lewat x-show, bukan dihapus) jadi tetap ikut ter-submit
            // form asli browser walau kosong — dan middleware bawaan Laravel
            // (ConvertEmptyStringsToNull) mengubah string kosong itu jadi NULL sebelum sampai
            // ke sini. Makanya pakai required_if senada dgn field array-nya sendiri (BUKAN
            // required_with, yg gagal krn keynya tetap "ada") DIBARENGI nullable (supaya
            // 'string' tak ikut gagal utk nilai NULL saat required_if TIDAK sedang berlaku).
            'opsi'                  => 'required_if:tipe,mcq,mcq_complex,true_false|array|min:2',
            'opsi.*.teks'           => 'nullable|required_if:tipe,mcq,mcq_complex,true_false|string|max:15000',
            'opsi.*.benar'          => 'nullable|boolean',
            // match — juga dari TinyMCE (bisa rumus), sama spt opsi.*.teks
            'pasangan'              => 'required_if:tipe,match|array|min:2',
            'pasangan.*.kiri'       => 'nullable|required_if:tipe,match|string|max:15000',
            'pasangan.*.kanan'      => 'nullable|required_if:tipe,match|string|max:15000',
            // essay
            'kunci_esai'            => 'nullable|string|max:3000',
        ]);

        // Sanitasi HTML dari editor SEBELUM disimpan (defense in depth — juga dibersihkan
        // ulang saat render). Penulis = guru/admin tepercaya, sama seperti materi Ruang Kelas
        // (lihat App\Support\RichText).
        $data['teks_soal'] = RichText::clean($data['teks_soal']);
        if (!empty($data['opsi'])) {
            foreach ($data['opsi'] as $i => $o) {
                $data['opsi'][$i]['teks'] = RichText::clean($o['teks']);
            }
        }

        if (in_array($data['tipe'], ['mcq', 'true_false'], true)) {
            $benar = collect($data['opsi'] ?? [])->filter(fn ($o) => !empty($o['benar']));
            if ($benar->count() !== 1) {
                abort(422, 'Pilihan Ganda / Benar-Salah wajib punya TEPAT SATU opsi benar.');
            }
        } elseif ($data['tipe'] === 'mcq_complex') {
            $benar = collect($data['opsi'] ?? [])->filter(fn ($o) => !empty($o['benar']));
            if ($benar->count() < 1) {
                abort(422, 'Pilihan Ganda Kompleks wajib punya minimal satu opsi benar.');
            }
        } elseif ($data['tipe'] === 'match') {
            $data['meta'] = ['pairs' => collect($data['pasangan'])->map(fn ($p) => ['left' => RichText::clean($p['kiri']), 'right' => RichText::clean($p['kanan'])])->all()];
        } elseif ($data['tipe'] === 'essay' && !empty($data['kunci_esai'])) {
            $data['meta'] = ['kunci_jawaban' => $data['kunci_esai']];
        }

        return $data;
    }
}
