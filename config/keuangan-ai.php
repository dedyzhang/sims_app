<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi AI Bendahara SPP (Fase A)
|--------------------------------------------------------------------------
| Aturan prioritas antrian verifikasi (deterministik, bukan LLM).
| Bobot disimpan di config agar perubahan formula dapat diaudit.
*/

return [

    /*
    | Skor prioritas antrian verifikasi (v1). Total maksimum = 100.
    | Formula: hari_terlambat * w_terlambat + nominal_skor * w_nominal +
    |          usia_bukti * w_usia + status_skor * w_status
    */
    'prioritas' => [
        'versi' => 1,
        'bobot' => [
            'hari_terlambat' => 40,  // jatuh tempo lewat → lebih mendesak
            'nominal'        => 30,  // nominal lebih besar → lebih prioritas
            'usia_bukti'     => 20,  // bukti lama menunggu → lebih prioritas
            'status'         => 10,  // menunggu > terverifikasi
        ],
        'nominal_referensi' => 500000, // nominal referensi utk normalisasi skor (BIGINT rupiah)
        'usia_maks_hari'    => 14,     // usia bukti di atas ini = skor penuh
    ],

    /*
    | OCR asisten bukti (A2) — saran HITL, bukan auto-post.
    */
    'ocr' => [
        'ttl_hari' => 30,
        'prompt' => 'Ekstrak informasi dari bukti transfer berikut. '
            .'Keluarkan HANYA JSON valid dengan kunci: '
            .'"nama_pengirim" (string|null), "tanggal" (YYYY-MM-DD|null), '
            .'"referensi" (string|null), "nominal_teks" (string|null, teks nominal apa adanya tanpa hitung). '
            .'Jangan hitung total atau konversi mata uang. '
            .'Jika tidak terbaca, null. Tanpa markdown.',
    ],

    /*
    | Parser rekening koran — daftar parser terdaftar (urutan deteksi).
    */
    'parser_rekening_koran' => [
        \App\Support\RekeningKoran\RekeningKoranBcaParser::class,
        \App\Support\RekeningKoran\RekeningKoranMandiriParser::class,
    ],

    /*
    | Skor pencocokan mutasi rekening ↔ tagihan SPP (Fase B1).
    */
    'matching' => [
        'versi' => 1,
        'bobot' => [
            'va'      => 40,
            'nominal' => 35,
            'tanggal' => 15,
            'nama'    => 10,
        ],
        'tanggal_max_hari'   => 7,
        'skor_otomatis_min'  => 70,
    ],

    /*
    | Digest antrian verifikasi menumpuk (Fase B3).
    */
    'digest' => [
        'menunggu_min'  => 10,
        'usia_hari_min' => 3,
        'jeda_jam'      => 6,
    ],

    /*
    | Wawasan operasional bendahara (Fase C1) — narasi non-nominal, terpisah dari Narasi Data pimpinan.
    */
    'wawasan' => [
        'prompt' => <<<'TXT'
            Kamu asisten operasional bendahara sekolah. Tugasmu menarasikan POLA dan TREN
            dari angka non-nominal yang sudah dihitung sistem (jumlah antrian, persentase
            keterlambatan waktu, pola hari bayar). ATURAN KERAS:
            - JANGAN menyebut atau menghitung nominal rupiah, total tagihan, atau jumlah uang.
            - JANGAN menghitung ulang persentase — gunakan angka yang diberikan.
            - Fokus saran operasional untuk bendahara (prioritas antrian, timing pengingat).
            - Bahasa Indonesia, 2–3 paragraf ringkas, tanpa tabel, tanpa nama siswa individu.
            TXT,
    ],

];
