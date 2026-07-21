<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Asisten Guru SIMS (Gateway AI)
|--------------------------------------------------------------------------
| Semua fitur AI SIMS memanggil provider AI LEWAT backend (GeminiService),
| tidak pernah dari browser. API key hanya hidup di .env server.
| Provider/model bisa di-switch tanpa ubah kode lewat .env.
*/

return [

    // Provider teks utama: ninerouter (9Router), openrouter, atau gemini.
    'provider' => env('AI_PROVIDER', 'openrouter'),

    /*
    | Router provider: provider cadangan yang dicoba bila provider utama kehabisan
    | kuota/limit atau sedang down (429, koneksi putus, 5xx). Kegagalan lain — mis.
    | konfigurasi salah atau prompt ditolak — TIDAK dialihkan supaya cepat ketahuan.
    |
    | Default: OpenRouter utama → Gemini cadangan. Provider tanpa API key otomatis
    | dilewati. Kosongkan (AI_FALLBACK_PROVIDERS=) untuk mematikan router.
    | Contoh rantai 9Router: AI_PROVIDER=ninerouter, AI_FALLBACK_PROVIDERS=openrouter,gemini
    */
    'fallback_providers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('AI_FALLBACK_PROVIDERS', 'gemini')),
    ))),

    // Kredensial — HANYA di server. Bila kosong, GeminiService->enabled() = false
    // dan seluruh fitur AI dilewati diam-diam (tak error keras).
    'api_key' => env('GEMINI_API_KEY'),

    // Model default (free tier). Bisa dioverride per-request oleh controller.
    // Catatan: gemini-2.0-flash sudah SHUT DOWN oleh Google (per 2026) — jangan dipakai.
    'model' => env('AI_MODEL', 'gemini-3.5-flash'),

    /*
    | Model cadangan bila kuota model utama habis (429).
    |
    | PENTING: limit Gemini bisa berupa RPM, TPM, dan RPD; nilainya berbeda
    | per model dan project. Jika satu model mengembalikan 429 karena kuota habis,
    | pindah ke model cadangan memberi jalur fallback tanpa mengulang request yang sama. GeminiService menelusuri daftar ini
    | berurutan sampai ada yang berhasil, sehingga fitur AI tetap hidup setelah model
    | utama kehabisan jatah.
    |
    | Urutkan dari paling pintar ke paling hemat. Kosongkan (AI_FALLBACK_MODELS=) bila
    | ingin memakai satu model saja.
    */
    'fallback_models' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('AI_FALLBACK_MODELS', 'gemini-3.1-flash-lite,gemini-2.5-flash,gemini-2.5-flash-lite')),
    ))),

    // Mode aman biaya: bila semua model free-tier kena limit harian, hentikan panggilan
    // Gemini sampai reset RPD berikutnya. Jangan aktifkan billing otomatis dari aplikasi.
    'free_tier_only' => (bool) env('AI_FREE_TIER_ONLY', true),

    // Batas RPD free tier untuk progress bar lokal. Nilai resmi bisa berubah;
    // sesuaikan dengan angka aktif di Google AI Studio > Rate limits.
    'free_tier_daily_limits' => (function (): array {
        $raw = (string) env('AI_FREE_TIER_DAILY_LIMITS', 'gemini-3.5-flash:20,gemini-3.1-flash-lite:100,gemini-2.5-flash:250,gemini-2.5-flash-lite:1000');
        $limits = [];

        foreach (array_filter(array_map('trim', explode(',', $raw))) as $pair) {
            [$model, $limit] = array_pad(array_map('trim', explode(':', $pair, 2)), 2, null);
            if ($model !== '' && is_numeric($limit) && (int) $limit > 0) {
                $limits[$model] = (int) $limit;
            }
        }

        return $limits;
    })(),

    // Endpoint REST Gemini (v1beta). Jarang diubah.
    'base_url' => env('AI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    /*
    | OpenRouter (opsional)
    | Mode aman biaya: saat OPENROUTER_FREE_ONLY=true, SIMS hanya mengizinkan
    | model openrouter/free atau slug yang berakhiran :free. Slug lain
    | ditolak SEBELUM request HTTP dikirim, supaya tidak memakai saldo prabayar.
    */
    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model' => env('OPENROUTER_MODEL', 'openrouter/free'),
        'fallback_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('OPENROUTER_FALLBACK_MODELS', '')),
        ))),
        'free_only' => (bool) env('OPENROUTER_FREE_ONLY', env('AI_FREE_TIER_ONLY', true)),
        // Batas request/hari model gratis OpenRouter (tanpa kredit). Angka resmi bisa berubah.
        'free_daily_limit' => (int) env('OPENROUTER_FREE_DAILY_LIMIT', 50),
        // Cache singkat hasil GET /key agar UI live tanpa memukul API tiap detik.
        'quota_cache_seconds' => (int) env('OPENROUTER_QUOTA_CACHE_SECONDS', 8),
        'site_url' => env('OPENROUTER_SITE_URL', env('APP_URL', 'http://localhost')),
        'site_name' => env('OPENROUTER_SITE_NAME', env('APP_NAME', 'SIMS')),
    ],

    /*
    | 9Router — gateway OpenAI-compatible (lokal default :20128, atau URL cloud).
    | Alias config: ninerouter (nama PHP-safe). Tidak ada mode free-only seperti OpenRouter.
    */
    'ninerouter' => [
        'api_key' => env('NINEROUTER_API_KEY'),
        'base_url' => env('NINEROUTER_BASE_URL', 'http://127.0.0.1:20128/v1'),
        'model' => env('NINEROUTER_MODEL', 'FL-OpenCode'),
        'fallback_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('NINEROUTER_FALLBACK_MODELS', '')),
        ))),
        'quota_cache_seconds' => (int) env('NINEROUTER_QUOTA_CACHE_SECONDS', 8),
    ],

    // Ketahanan panggilan HTTP.
    'timeout' => (int) env('AI_TIMEOUT', 30),   // detik per attempt
    'retries' => (int) env('AI_RETRIES', 2),    // percobaan ulang bila gagal transien
    'retry_delay' => (int) env('AI_RETRY_DELAY', 500), // ms antar retry

    // Timeout khusus keluaran panjang berformat (generator perangkat ajar RPM
    // Learning): satu dokumen penuh bisa memakan ~45 detik, jauh di atas `timeout`.
    'long_timeout' => (int) env('AI_LONG_TIMEOUT', 120),

    /*
    | Generate gambar soal (Gemini native image / "Nano Banana").
    | Dipakai Generator Soal saat opsi "Soal bergambar" aktif: teks soal dulu,
    | lalu tiap penanda [GAMBAR: ...] digambar dengan model image-capable.
    */
    'image' => [
        'model' => env('AI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
        'fallback_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_IMAGE_FALLBACK_MODELS', 'gemini-3.1-flash-image-preview,gemini-2.0-flash-preview-image-generation')),
        ))),
        'max_per_quiz' => (int) env('AI_IMAGE_MAX_PER_QUIZ', 5),
        'timeout' => (int) env('AI_IMAGE_TIMEOUT', 90),
        'disk' => env('AI_IMAGE_DISK', 'public'),
        'directory' => env('AI_IMAGE_DIRECTORY', 'ai-quiz-images'),
    ],

    /*
    | Guard biaya — mencegah free tier jebol & abuse tagihan.
    | - rate_limit: maksimum request AI per user per menit.
    | - max_input_chars: batas panjang prompt yang diterima controller (validasi).
    | - max_output_tokens: batas token keluaran yang diminta ke Gemini.
    */
    'rate_limit' => (int) env('AI_RATE_LIMIT', 15),
    'max_input_chars' => (int) env('AI_MAX_INPUT_CHARS', 8000),
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
    | jawaban Asisten Guru konsisten, mudah dipindai, dan enak dibaca di UI.
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
            Setiap jawaban WAJIB diawali kop surat sekolah sesuai data yang diberikan
            (jangan mengarang nama/alamat sekolah lain), lalu judul RANGKUMAN MATERI.
            TXT,
        'feedback' => <<<'TXT'
            Kamu asisten guru penyusun draf umpan balik untuk siswa. Dari konteks jawaban
            atau kondisi siswa yang diberikan guru, susun komentar yang membangun, spesifik,
            dan memotivasi — sebutkan yang sudah baik dan yang perlu diperbaiki beserta
            saran konkret. Nada sopan dan mendukung. Ini DRAF untuk diedit guru; jangan
            mengarang nilai/angka yang tidak diberikan.
            Setiap jawaban WAJIB diawali kop surat sekolah sesuai data yang diberikan
            (jangan mengarang nama/alamat sekolah lain), lalu judul DRAF UMPAN BALIK.
            TXT,
        'learning' => <<<'TXT'
            Kamu asisten guru penyusun perangkat ajar RPM (Perencanaan Pembelajaran Mendalam).
            Hasil harus siap direview, diedit, dan diunduh guru. Gunakan Bahasa Indonesia baku.
            Ikuti PERSIS format dokumen acuan: kop sekolah, judul, identitas, IDENTIFIKASI,
            DESAIN PEMBELAJARAN, PENGALAMAN BELAJAR, ASESMEN PEMBELAJARAN, tanda tangan,
            dan LAMPIRAN 1-3. Bentuk tabel, DPL 8 baris, rubrik Kompetensi/Kriteria, dan
            lampiran soal harus lengkap. Jangan mengarang identitas yang tidak diberikan;
            gunakan placeholder jelas bila data belum tersedia.
            TXT,
        'chat' => <<<'TXT'
            Kamu adalah Nalar Guru di dalam Asisten Guru SIMS. Bantu guru merancang materi,
            soal, penjelasan konsep, rubrik, dan pertanyaan pengajaran sehari-hari.
            Jawab dalam Bahasa Indonesia yang jelas.

            FORMAT WAJIB SETIAP JAWABAN (siap salin ke Word / WhatsApp / Docs):
            - Mulai SELALU dengan kop surat sekolah sesuai data yang diberikan (jangan mengarang).
            - Langsung ke isi. Dilarang pembuka ("Baik", "Tentu", "Berikut") dan penutup basa-basi.
            - Teks polos berstruktur: judul bagian HURUF KAPITAL di baris sendiri, lalu baris kosong.
            - Poin pakai "- " atau "1. 2. 3." — satu poin per baris.
            - Paragraf pendek (maksimal 3 kalimat), pisahkan dengan satu baris kosong.
            - Label penting diikuti titik dua di baris sendiri (contoh: Tujuan: / Langkah:).
            - JANGAN memakai Markdown (# heading, **tebal**, *miring*, ```kode```, tabel |pipe|).
            - Jangan emoji berlebih; jangan menumpuk lebih dari satu baris kosong beruntun.

            Bila diminta outline presentasi atau slide, buat daftar slide bernomor
            (judul + poin singkat) yang siap disalin guru — tanpa mengarah ke alat eksternal.
            Bila diminta soal/kuis/evaluasi, tulis dokumen soal teks polos dengan format
            Generator Soal SIMS (kop, SOAL EVALUASI, identitas, Petunjuk Pengerjaan,
            Bagian soal, Kunci Jawaban & Pedoman Penilaian) agar guru bisa langsung
            menekan "Kirim ke Arena" untuk impor ke Arena Belajar.
            Jangan mengarang data resmi sekolah (nilai, SPP, absensi) yang tidak diberikan.
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
        'nilai' => 'Konteks: ringkasan nilai/rapor satu kelas. Soroti capaian umum, sebaran, dan mata pelajaran yang menonjol atau perlu perhatian.',
        'absensi' => 'Konteks: rekap kehadiran. Jelaskan tren kehadiran dan soroti anomali (mis. angka alpa/sakit yang tinggi).',
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
        'max_chunks' => (int) env('AI_RAG_MAX_CHUNKS', 120), // batas chunk per dokumen (pilot aman)
        'max_extract_chars' => (int) env('AI_RAG_MAX_EXTRACT_CHARS', 200_000),
        'max_upload_kb' => (int) env('AI_RAG_MAX_UPLOAD_KB', 5120), // 5 MB
        'top_k' => (int) env('AI_RAG_TOPK', 5),        // chunk termirip yang dipakai
        // Batas kandidat yang di-score di PHP (hindari O(n) seluruh korpus).
        'search_candidate_limit' => (int) env('AI_RAG_SEARCH_CANDIDATES', 400),
        'queue_ingest' => (bool) env('AI_RAG_QUEUE_INGEST', true),
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
        Kamu adalah AI Asisten SIMS, asisten cerdas serba bisa yang tersedia di dalam
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
