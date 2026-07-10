<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi AsistenAI SIMS (Gateway Gemini)
|--------------------------------------------------------------------------
| Semua fitur AI SIMS memanggil Google Gemini LEWAT backend (GeminiService),
| tidak pernah dari browser. GEMINI_API_KEY hanya hidup di .env server.
| Model bisa di-switch tanpa ubah kode — cukup ganti AI_MODEL di .env.
*/

return [

    // Kredensial — HANYA di server. Bila kosong, GeminiService->enabled() = false
    // dan seluruh fitur AI dilewati diam-diam (tak error keras).
    'api_key' => env('GEMINI_API_KEY'),

    // Model default (free tier). Bisa dioverride per-request oleh controller.
    // Catatan: gemini-2.0-flash sudah SHUT DOWN oleh Google (per 2026) — jangan dipakai.
    'model' => env('AI_MODEL', 'gemini-2.5-flash'),

    // Endpoint REST Gemini (v1beta). Jarang diubah.
    'base_url' => env('AI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    // Ketahanan panggilan HTTP.
    'timeout'     => (int) env('AI_TIMEOUT', 30),   // detik per attempt
    'retries'     => (int) env('AI_RETRIES', 2),    // percobaan ulang bila gagal transien
    'retry_delay' => (int) env('AI_RETRY_DELAY', 500), // ms antar retry

    /*
    | Guard biaya — mencegah free tier jebol & abuse tagihan.
    | - rate_limit: maksimum request AI per user per menit.
    | - max_input_chars: batas panjang prompt yang diterima controller (validasi).
    | - max_output_tokens: batas token keluaran yang diminta ke Gemini.
    */
    'rate_limit'        => (int) env('AI_RATE_LIMIT', 15),
    'max_input_chars'   => (int) env('AI_MAX_INPUT_CHARS', 8000),
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 1024),

    /*
    | Grounding Google Search — AI boleh mencari di web untuk pertanyaan yang
    | butuh info terkini/faktual, lalu menautkan sumber. Hanya didukung model
    | Gemini 2.0+ (tool google_search) / 1.5 (google_search_retrieval).
    | Matikan (AI_GROUNDING=false) bila ingin jawaban murni dari pengetahuan model.
    */
    'grounding' => (bool) env('AI_GROUNDING', true),

    /*
    | Hemat kuota free tier: saat grounding aktif, pencarian web HANYA dipakai
    | bila pesan mengandung salah satu kata kunci di bawah (sinyal butuh info
    | terkini/faktual). Pertanyaan biasa (mis. "buatkan soal", "jelaskan materi")
    | dijawab dari pengetahuan model — gratis, tanpa memakai kuota grounding.
    | Kosongkan array ini bila ingin SELALU grounding saat aktif.
    */
    'grounding_triggers' => [
        'terbaru', 'terkini', 'terupdate', 'update', 'sekarang', 'saat ini',
        'hari ini', 'minggu ini', 'bulan ini', 'tahun ini', 'berita', 'kabar',
        'harga', 'kurs', 'nilai tukar', 'saham', 'crypto', 'bitcoin',
        'cuaca', 'ramalan', 'skor', 'hasil pertandingan', 'klasemen', 'juara',
        'pemenang', 'rilis', 'launching', 'viral', 'trending', 'tren',
        'di internet', 'menurut google', 'cari di internet', 'browsing',
        '2024', '2025', '2026', '2027', '2028',
    ],

    // Kreativitas default. 0.0 = deterministik, 1.0 = kreatif.
    'temperature' => (float) env('AI_TEMPERATURE', 0.7),

    /*
    | Gaya jawaban global. Instruksi ini ditempel ke semua system prompt agar
    | jawaban AsistenAI konsisten, mudah dipindai, dan enak dibaca di UI.
    */
    'answer_style' => <<<'TXT'
        Format jawaban:
        - Gunakan Markdown ringan yang rapi: paragraf pendek, bullet list, numbered list,
          tabel sederhana, dan heading seperlunya.
        - Dahulukan jawaban inti. Hindari pembuka basa-basi.
        - Gunakan cetak tebal hanya untuk label penting, bukan setiap kalimat.
        - Jika menjelaskan langkah, pakai daftar bernomor yang jelas.
        - Jika membuat soal, pisahkan bagian soal, opsi, dan kunci jawaban dengan rapi.
        - Jangan menulis blok teks panjang tanpa jeda baris.
        TXT,

    /*
    | Chatbot (FASE 2)
    | - history_limit: jumlah pesan terakhir yang dikirim sebagai konteks (kontrol biaya).
    | - faq: ringkasan info sekolah (jam, kontak, prosedur). Ditambahkan ke system
    |   prompt agar jawaban relevan. Edit sesuai sekolah, atau override via .env.
    */
    /*
    | Asisten Guru (FASE 3) — system prompt tiap tool. Bahasa Indonesia.
    */
    'teacher' => [
        'quiz' => <<<'TXT'
            Kamu asisten guru penyusun soal. Buat soal yang jelas, sesuai kaidah
            penulisan soal, dan bebas ambigu. Ikuti persis jumlah, jenis, dan tingkat
            kesulitan yang diminta. Untuk pilihan ganda: beri opsi A–D, hanya satu
            jawaban benar, dan tulis KUNCI JAWABAN di bagian paling bawah. Untuk esai:
            sertakan poin-poin jawaban ideal/rubrik singkat. Gunakan Bahasa Indonesia
            baku. Jangan menambah pengantar berlebihan — langsung ke soal.
            TXT,
        'summary' => <<<'TXT'
            Kamu asisten guru perangkum materi. Ringkas materi yang diberikan menjadi
            poin-poin padat, terstruktur, dan mudah dipahami siswa. Pertahankan istilah
            penting, jangan menambah fakta yang tidak ada di materi, dan jangan mengarang.
            Gunakan Bahasa Indonesia yang sederhana sesuai jenjang sekolah.
            TXT,
        'feedback' => <<<'TXT'
            Kamu asisten guru penyusun draf umpan balik untuk siswa. Dari konteks jawaban
            atau kondisi siswa yang diberikan guru, susun komentar yang membangun, spesifik,
            dan memotivasi — sebutkan yang sudah baik dan yang perlu diperbaiki beserta
            saran konkret. Nada sopan dan mendukung. Ini DRAF untuk diedit guru; jangan
            mengarang nilai/angka yang tidak diberikan.
            TXT,
    ],

    /*
    | Narasi Analisis Data (FASE 4). AI HANYA menarasikan angka yang sudah
    | dihitung controller — TIDAK menghitung ulang, khususnya uang.
    */
    'analyze' => [
        'base' => <<<'TXT'
            Kamu asisten analis data sekolah. Tugasmu MENARASIKAN angka yang sudah
            dihitung sistem menjadi laporan naratif Bahasa Indonesia yang mudah dibaca
            pimpinan sekolah dan orang tua. ATURAN KERAS:
            - Gunakan HANYA angka yang diberikan. JANGAN menghitung ulang, menjumlah,
              atau mengarang angka/persentase baru — khususnya nominal uang.
            - Jangan menyebut nama siswa individu bila tidak diberikan.
            - Sorot hal penting (tren, capaian, dan area yang perlu perhatian) secara
              objektif dan sopan. Ringkas, 2–4 paragraf, tanpa tabel.
            TXT,
        'nilai'    => 'Konteks: ringkasan nilai/rapor satu kelas. Soroti capaian umum, sebaran, dan mata pelajaran yang menonjol atau perlu perhatian.',
        'absensi'  => 'Konteks: rekap kehadiran. Jelaskan tren kehadiran dan soroti anomali (mis. angka alpa/sakit yang tinggi).',
        'keuangan' => 'Konteks: rekap pembayaran SPP. Narasikan status pelunasan dan tunggakan secara faktual. JANGAN menghitung ulang rupiah.',
    ],

    /*
    | RAG Dokumen Sekolah (FASE 5). Embedding via Gemini, disimpan sebagai JSON
    | (SQLite — cosine dihitung manual di PHP, bukan pgvector).
    */
    'rag' => [
        'embed_model' => env('AI_EMBED_MODEL', 'gemini-embedding-001'),
        'chunk_chars' => (int) env('AI_RAG_CHUNK', 900),   // ukuran target per chunk
        'chunk_overlap' => (int) env('AI_RAG_OVERLAP', 150),
        'max_chunks'  => (int) env('AI_RAG_MAX_CHUNKS', 300), // batas chunk per dokumen
        'top_k'       => (int) env('AI_RAG_TOPK', 5),        // chunk termirip yang dipakai
        'system' => <<<'TXT'
            Kamu asisten dokumen sekolah. Jawab pertanyaan HANYA berdasarkan KONTEKS
            kutipan dokumen yang diberikan di bawah. Jika jawabannya tidak ada di dalam
            konteks, katakan dengan jujur bahwa informasi itu tidak ditemukan di dokumen
            — JANGAN mengarang. Jawab ringkas dalam Bahasa Indonesia. Bila relevan,
            sebutkan dari dokumen mana informasi itu berasal.
            TXT,
    ],

    'chat' => [
        'history_limit' => (int) env('AI_CHAT_HISTORY', 10),
        'faq' => env('AI_CHAT_FAQ', <<<'TXT'
            Konteks aplikasi: kamu hidup di dalam SIMS (Sistem Informasi Manajemen
            Sekolah) yang dipakai siswa, orang tua, guru, wali kelas, dan staf. Namun
            kamu adalah asisten SERBA BISA, bukan sekadar bot sekolah.

            Perilakumu sebagai chatbot:
            - Jawab APAPUN yang pengguna tanyakan dengan sebaik mungkin: materi pelajaran,
              contoh soal & latihan beserta pembahasan, tugas, penjelasan konsep, menulis,
              menerjemahkan, coding, ide, maupun pertanyaan umum di luar sekolah.
            - Bila pengguna minta "cari soal" atau latihan, susun soal yang relevan
              dengan topik/jenjang yang diminta, lalu sertakan kunci jawaban/pembahasan
              singkat kecuali diminta lain.
            - Kamu BOLEH dan SEHARUSNYA menggunakan informasi yang pengguna sampaikan
              sendiri di dalam percakapan ini (mis. nama, kelas, atau hal yang tadi
              ia sebutkan) untuk menjawab secara natural dan berkesinambungan.
            - Yang dilarang hanyalah MENGARANG rekaman resmi sekolah — angka nilai/rapor,
              nominal tagihan SPP, data absensi, atau jadwal individu — yang TIDAK ada
              di percakapan. Untuk itu, arahkan pengguna membuka menu terkait di SIMS
              atau menghubungi pihak sekolah/admin, jangan menebak angkanya.
            TXT),
    ],

    /*
    | System prompt dasar (Bahasa Indonesia). Ditambahkan di server ke setiap
    | panggilan; fitur spesifik boleh menambah instruksi sendiri di atas ini.
    | Menekankan kejujuran: AI tak boleh mengarang data sekolah yang tak diberikan.
    */
    'system_prompt' => env('AI_SYSTEM_PROMPT', <<<'TXT'
        Kamu adalah AsistenAI, asisten cerdas serba bisa yang tersedia di dalam
        aplikasi sekolah SIMS. Kamu BOLEH membantu segala macam topik — bukan hanya
        seputar sekolah. Contohnya: menjelaskan materi & konsep, membuat atau mencari
        contoh soal/latihan beserta pembahasannya, membantu tugas, menulis, menerjemahkan,
        berhitung, memberi ide, sampai pertanyaan pengetahuan umum sehari-hari.
        Jawab dalam Bahasa Indonesia yang jelas, sopan, dan ringkas (kecuali diminta lain).
        Satu-satunya batasan keras: JANGAN pernah mengarang DATA RESMI SEKOLAH milik
        pengguna — nilai/rapor, nominal SPP/keuangan, absensi, atau jadwal individu —
        yang tidak diberikan secara eksplisit di dalam prompt. Untuk data seperti itu,
        arahkan pengguna membuka menu terkait di SIMS atau menghubungi pihak sekolah.
        Untuk topik sensitif (medis, hukum, finansial), boleh memberi penjelasan umum
        dan edukatif, tetapi ingatkan agar keputusan penting dikonsultasikan ke ahli.
        TXT),

];
