# Progress â€” SIMS MW

> **Agent:** baca file ini + `PRD.md` + `features/*.md` di awal sesi. Status task di `features/NN-*.md` (suffix `[DONE]`) harus sinkron dengan checklist di bawah. Setelah selesai satu task, centang di sini dan update baris task di file fitur terkait.

**Verifikasi terakhir:** 2026-07-23 â€” `php artisan test --filter="GameQuiz|GameLive|GameTemplate|ArenaBelajar|MissionClassroom"` â†’ **49 passed**; `--filter="Ludensa|SimsGemini"` â†’ **15 passed**.

---

## Fase 1: Bank Soal & Kuis Async â€” SELESAI

Ref: `features/01-bank-soal-kuis-async.md` (task 1â€“11 `[DONE]`)

- [x] 1â€“5 UI dummy â†’ navigasi â†’ poles responsif
- [x] 6 Migration & model `game_*` (6 tabel)
- [x] 7 `GameQuizController` CRUD + assign + `DB::transaction()`
- [x] 8 `GameAttemptController` + auto-grading + monitor
- [x] 9 `GameQuizPolicy` + authorization
- [x] 10 Transfer nilai + `Audit::log`
- [x] 11 Seeder + `GameQuizTest` (19 tests)

## Fase 2: Live Session & Leaderboard â€” SELESAI

Ref: `features/02-live-session-leaderboard.md` (task 1â€“11 `[DONE]`)

- [x] 1â€“5 UI live lobby/podium + builder Match/Short Answer (dummy â†’ poles)
- [x] 6 Migration `game_live_sessions` + model
- [x] 7 `GameLiveController` + leaderboard JSON + polling
- [x] 8 Grading Match Up & Short Answer
- [x] 9 Policy aksi live
- [x] 10 FCM `ArenaLiveStartedNotification` + activity log
- [x] 11 `GameLiveTest` (7 tests)

## Fase 3: Template Interaktif & Mode Tim â€” SELESAI

Ref: `features/03-template-interaktif.md` (task 1â€“11 `[DONE]`)

- [x] 1â€“5 Template switcher, mode tim, PDF preview, navigasi, poles
- [x] 6 Migration teams + template fields
- [x] 7 `GameTemplateController` + team scoring
- [x] 8 DomPDF worksheet + kunci guru
- [x] 9 Policy tim & export
- [x] 10 Offline queue localStorage + `syncOffline`
- [x] 11 `GameTemplateTest` (6 tests)

**Subtotal Arena Belajar (kuis):** 32 tests (+ `ArenaBelajarDemoFlowTest` 2, `GameQuizImporterLooksLikeTest` 1)

---

## Jagat Misi (terintegrasi Arena Belajar) â€” SELESAI

- [x] Fase 1â€“7: models, player, debrief, analytics, mission builder
- [x] Fase 8: `MissionClassroomController` + assign/play/transfer di Ruang Kelas
- [x] Merge branding: satu tab **Arena Belajar** (Kuis + Misi); `jagat_misi` â†’ `arena_belajar`
- [x] `MissionClassroomTest` (14 tests)

### Sisa opsional

- [ ] Admin dashboard khusus JagatMISI (SIMS sudah punya admin sendiri)

---

## Grup Chat (Grup Kelas & Paguyuban Orang Tua) â€” SELESAI (uncommitted)

Chat otomatis per kelas (dua tipe: `kelas` & `paguyuban`), keanggotaan diturunkan dari
struktur sekolah lewat `GrupChatService`, tidak ada fitur PRD/features/*.md formal untuk
modul ini â€” dibangun langsung via sesi chat, dilacak di sini saja.

- [x] Fase 1: Model/migration (3 tabel), sync service, policy, command `grupchat:sinkron`
      (dijadwalkan 01:00), toggle modul `grup_chat`, menu sidebar
- [x] Fase 2: Reply pesan, lampiran foto/berkas (reuse `App\Support\ChatAttachments`),
      hapus/moderasi pesan (`GrupChatMessenger::hapus()`)
- [x] Code review (`/code-review`, 12 reviewer) â€” 1 P0 + 3 P1/P2 diterapkan langsung
      (kebocoran isi pesan lewat kutipan balasan, race preview grup saat hapus,
      urutan hapus-file-vs-transaksi, idempotensi hapus ganda); 3 temuan performa/data
      (N+1 sync kelulusan & rombel, cascade-delete kelas menghapus riwayat chat)
      diperbaiki setelahnya atas permintaan FL
- [x] Fase 4: Notifikasi digest â€” `grupchat:kirim-notif` (dijadwalkan tiap 15 menit),
      `GrupChatDigestNotification` (database + FCM), 1 notifikasi per user walau
      unread di beberapa grup, menghormati `muted_until` & grup `arsip`
- [x] Test: 84 test (`GrupChatAksesTest`, `GrupChatPollTest`, `GrupChatSinkronTest`,
      `GrupChatModulTest`, `GrupChatLampiranTest`, `GrupChatDigestTest`,
      `PengumumanGrupChatTest`)
- [x] Fase 5: tiga sisa opsional dituntaskan â€”
      - Komposer kini membuka jalur balas walau `boleh_kirim` false: flag baru
        `bolehBalasPengumuman` (`GrupChatController::bolehBalasPengumuman()`) dikirim
        lewat `show()` & `poll()`, tombol "Balas" & textarea di `grup/show.blade.php`
        dikunci per pesan (`bolehBalas(m)` â€” hanya pesan staf yg boleh dibalas non-staf
        di mode pengumuman), 3 test baru di `GrupChatLampiranTest`.
      - `GrupChatService::syncGuru()` **dihapus** â€” dead code, diverifikasi tidak ada
        pemanggil: tiap mutasi nyata (Ngajar create/delete lewat `NgajarObserver`,
        reassign walikelas lewat `KelasController::walikelas()`) sudah memanggil
        `syncKelas()` langsung per-kelas; jalur lain (impor/SQL mentah) sudah tercakup
        rekonsiliasi malam `grupchat:sinkron`.
      - Route `grup.pesan.*` & `grup.lampiran.unduh` kini pakai `Route::scopeBindings()`
        (butuh alias relasi `GrupChat::pesans()` â€” nama method WAJIB hasil
        `Str::plural('pesan')`, bukan `pesan()`/`messages()`) â€” kombinasi
        `{grup}/{pesan}` yang tak nyambung sekarang 404 di level routing, bukan
        cuma lewat `abort_unless()` manual di controller (yang tetap dipertahankan
        sebagai guard redundan). 2 test baru untuk cross-grup 404.
- [x] Fase 6: perombakan aturan keanggotaan & kirim pesan Grup Kelas atas permintaan FL â€”
      - **Guru pengajar/mapel dikeluarkan dari Grup Kelas.** `GrupChatService::anggotaGrupKelas()`
        kini hanya wali kelas + siswa aktif. `NgajarObserver` tidak lagi memanggil
        `GrupChatService` sama sekali (dulu men-sync Grup Kelas tiap Ngajar
        dibuat/dihapus) â€” penugasan mengajar tak lagi berpengaruh ke grup chat.
      - **Grup Kelas SELALU mode pengumuman** (hanya wali kelas boleh kirim pesan baru,
        siswa hanya boleh membalas) â€” di-enforce di `provisionKelas()` (grup baru)
        dan `syncKelas()` (self-healing utk grup lama tiap kali sync jalan, termasu
        nightly `grupchat:sinkron`). Grup Paguyuban TETAP mode diskusi bebas (tidak
        diubah â€” walikelas & orang tua tetap bebas kirim pesan; keputusan FL).
      - DB dev lokal direkonsiliasi via `php artisan grupchat:sinkron` (26 grup,
        471 anggota â€” belum ada data grup chat produksi sebelumnya, jadi tidak perlu
        migrasi/backfill terpisah).
      - Test lama yang berasumsi guru pengajar jadi anggota / siswa bisa kirim pesan
        bebas di Grup Kelas ditulis ulang (`GrupChatAksesTest`,
        `GrupChatSinkronTest`, `GrupChatLampiranTest`, `GrupChatDigestTest`) + 2 test
        baru (guru tidak pernah masuk grup kelas; mode grup kelas vs paguyuban
        self-healing). Total sekarang 72 test grup chat.
      - `docs/PANDUAN_PENGGUNAAN_SIMS_APP.md` §16 + `docs/PANDUAN_VISUAL_PIKET_GURU_GRUP_CHAT.md` disinkronkan (digest/private chat/notifikasi, 2026-08-08).
- [x] Code review Fase 6 (`/compound-engineering:ce-code-review`, 9 reviewer) â€” semua temuan
      diterapkan langsung, tidak ada yang menunggu:
      - Update mode `syncKelas()` dipindah ke DALAM `DB::transaction()` yang sama dgn
        rekonsiliasi keanggotaan (dulu dua write terpisah, non-atomik).
      - `grupchat:sinkron` kini isolasi-per-kelas (try/catch + log) â€” satu kelas gagal
        tidak lagi menggagalkan sisa batch semalam.
      - **`GrupChat::PERAN_STAF` tidak lagi memuat `'guru'`** (sabuk & bretel): baris
        keanggotaan lama peran `guru` yang belum sempat direkonsiliasi kini otomatis
        diperlakukan sebagai NON-staf (tidak bisa kirim pesan baru di mode pengumuman),
        bukan diam-diam tetap punya hak staf sampai `syncKelas()` berikutnya jalan.
      - **`GrupChatMessenger::kirim()` & `hapus()` kini mengecek otorisasi sendiri**
        (`Gate::forUser(...)->authorize(...)`), bukan cuma dipercaya sudah dicek
        `GrupChatController` â€” primitive-nya sendiri jadi aman dipanggil pemanggil
        mana pun (command lain, tool/agent masa depan), bukan cuma lewat controller.
      - Komentar migration `create_grup_chats_table` yang masih bilang "guru pengajar"
        diperbaiki supaya tidak menyesatkan.
      - 5 test baru: `provisionKelas()` set mode tanpa bantuan `syncKelas()`;
        `NgajarObserver` terbukti tidak lagi memanggil `GrupChatService` sama sekali
        (mock `shouldNotReceive`); baris keanggotaan `guru` lama kehilangan hak staf lalu
        dikeluarkan; `GrupChatMessenger::kirim()` menolak panggilan langsung tanpa lewat
        controller untuk siswa, dan menerima untuk wali kelas. Total sekarang 82 test.
       - Polling backlog kini memakai `next_after`; batch >200 pesan tidak lagi terlewati
         saat klien mengejar cursor.
       - Riwayat lama kini bisa dimuat bertahap lewat endpoint `pesan-lama`, dengan batas
         `joined_seq` tetap ditegakkan.
       - Tombol hapus pesan tersedia sebagai target sentuh di mobile, tetap hover-only di desktop.
       - Presence anggota kini mengirim status `online/recent/offline` dan label last seen
         tanpa membocorkan timestamp mentah; modal anggota me-refresh data saat dibuka.
       - Daftar anggota di modal kini diurutkan alfabetis berdasarkan nama.
       - Sisa non-blocking (didokumentasikan, sengaja tidak diterapkan â€” taste call):
         `ModulAktif::aktif()` mulai menumpuk special-case per modul (`arena_belajar`,
         `ludensa`) â€” pertimbangkan ekstrak jadi peta default kalau ada modul ketiga
         yang butuh override serupa.

- [x] Private Chat wali kelas â€” migration percakapan/pesan, policy relasi kelas,
      akses dari nama/profil anggota Grup Chat, polling, notifikasi penerima, dan
      perlindungan IDOR (`PrivateChatTest`).

### Sisa

- [x] Commit & push ke `origin/main` (`791ca51`) â€” PR #51 dibuka ke `dedyzhang/sims_app`:
      https://github.com/dedyzhang/sims_app/pull/51
- [ ] Merge PR #51 & deploy â€” **tunggu review/merge dari dedyzhang, bukan tugas sesi ini**

---

## Integrasi Ludensa â€” DIJEDA (uncommitted)

**2026-07-31 â€” dijeda atas permintaan FL** sampai dilanjutkan manual: paket
`ludensa/ludensa` (composer path repo ke `../Ludensa GAMIFIKASI/packages/ludensa`)
belum terpasang di `vendor/` pada checkout ini, dan `config/ludensa.php` belum
lengkap (`jenjang_label`, `permainan`, dll. belum ada). Yang sudah diubah supaya
modul ini tidak "terpanggil" tanpa sengaja:

- `ModulAktif::aktif('ludensa')` sekarang default **NONAKTIF** (dulu default
  aktif seperti modul lain) â€” sekolah/dev yang sudah pasang paketnya tetap bisa
  menyalakan manual lewat Setting. Menu sidebar sendiri sebenarnya sudah aman
  (sudah ada guard `Route::has('ludensa.beranda')` sejak awal), perubahan ini
  cuma mempertegas defaultnya.
- 3 file test (`LudensaIntegrationTest`, `LudensaJenjangAnakTest`,
  `SimsGeminiAiJsonGeneratorTest` â€” 22 test) di-skip otomatis lewat
  `markTestSkipped()` berbasis `class_exists()`/`interface_exists()` ke kelas
  paket `Ludensa\*` â€” begitu `vendor/ludensa` terpasang, skip ini otomatis
  berhenti aktif tanpa perlu diedit manual.
- `ModulAktifTest::test_default_semua_modul_aktif` dikecualikan utk 'ludensa' +
  test baru `test_ludensa_default_nonaktif`.

**Untuk melanjutkan nanti:** pasang paket (`composer install` dgn path repo di
atas tersedia), lengkapi `config/ludensa.php`, lalu boleh langsung hapus semua
blok skip di atas â€” semuanya sudah ditandai jelas di tiap file.

Modul permainan edukatif SD (paket `ludensa/*`) terintegrasi ke SIMS via service provider.

- [x] `config/ludensa.php` + `LudensaIntegrationServiceProvider`
- [x] Adapter: `LudensaJenjang`, `LudensaSchool`, `InteractsWithLudensa`
- [x] `SimsGeminiAiJsonGenerator` (binding AI JSON ke Ludensa)
- [x] `ModulAktif` + toggle `fitur_ludensa_aktif` / middleware `modul:ludensa`
- [x] Tab **Fitur** di Pengaturan Sistem (on/off Arena Petualangan SD + modul lain)
- [x] Activity log migrations (Spatie)
- [x] `SimsLudensaSeeder` + `LudensaIntegrationTest` (10 tests)
- [x] Unit: `LudensaJenjangAnakTest`, `SimsGeminiAiJsonGeneratorTest` (5 tests)
- [ ] Commit & deploy ke staging â€” **tunggu approval FL**
- [x] Audit keamanan pre-rilis: **`laravel-security-audit`** â€” P1 avatar privat + tenant scope diperbaiki 2026-07-23
- [ ] Dokumentasi admin: cara aktifkan modul Ludensa per sekolah

---

## Modul AI SIMS (FASE 1â€“5) + migrasi Codex â†’ Cursor â€” SELESAI

Branch: `cursor/ai-fase-1-4` (merge `codex/sekretaris-absensi-scope` + stash Codex 2026-08-07).

- [x] **FASE 1â€“3:** Gateway, chatbot, Asisten Guru (soal, RPM, nalar guru, kirim ke Arena)
- [x] **FASE 4 â€” Narasi Data:** agregat server-side; key **pribadi guru dulu**, sekolah cadangan; label UI: `Narasi Data`, `Analisis Data`
- [x] **FASE 5 â€” Asisten Dokumen (RAG):** key pribadi dulu, mode Asisten Admin Sekolah, prompt `admin_system`
- [x] **Kisi-kisi / blueprint** Asisten Guru: `AiTeacherController@blueprint`, `BlueprintDocument`, export DOCX/PDF
- [x] Konvensi key dipusatkan di `InteractsWithAi` (`personalAiKey` â†’ `personalAiOptions` â†’ `aiConfiguredFor`)

**Verifikasi AI:** `php artisan test --filter="AiAnalyzeTest|AiRagTest|AiTeacherControllerTest"` â†’ **55 passed**

### 2026-08-15 - Upgrade Gemini AI Studio + TTS Asisten Guru

- [x] Model teks key pribadi guru: `gemini-3.7-flash`; fallback `3.6 Flash` -> `3.5 Flash` -> `2.5 Flash`.
- [x] Model audio khusus: `gemini-3.1-flash-tts-preview` (model teks 3.7 tidak dipakai untuk output audio).
- [x] MP3 128 kbps tetap menjadi format distribusi default; WAV lossless tetap didukung untuk kebutuhan arsip/editing.
- [x] TTS diproses lewat database queue dengan timeout 90 detik; kegagalan koneksi mencatat penyebab internal tanpa mengekspos API key.
- [x] Verifikasi fokus: `AiTeacherControllerTest|AiTeacherAudioTest|TeacherOutputLanguageTest` -> **63 passed, 358 assertions**.
- [x] Smoke test API AI Studio nyata: teks berhasil lewat `gemini-3.7-flash`; audio WAV 4 detik berhasil lewat `gemini-3.1-flash-tts-preview`.
- [x] Transport TTS memakai `:streamGenerateContent?alt=sse`, parser SSE incremental, validasi `finishReason=STOP`, dan retry per chunk tanpa menggandakan PCM parsial.
- [x] PCM ditulis ke temporary file lalu dikonversi satu kali menjadi MP3 128 kbps; temporary file selalu dibersihkan.
- [x] Batas narasi 8.000 karakter/1.200 kata, job timeout 1.800 detik, stale processing 35 menit, dan counter batas tampil di UI.
- [x] Free Tier quota-aware: chunk 1.000 karakter (maksimal sekitar 8 request untuk satu proses), pacing 21 detik, deteksi quota harian, serta fallback key sekolah hanya untuk quota harian bila dikonfigurasi.
- [x] Verifikasi streaming/retry/limit: `GeminiTtsSseParserTest|AiTeacherAudioTest` -> **25 passed, 78 assertions**.
- [x] Smoke nyata narasi pendek: MP3 85.293 byte, durasi 5,24 detik.
- [ ] Smoke nyata 2.740 karakter sampai MP3 final menunggu reset quota harian AI Studio. Chunk streaming berhasil, tetapi project mencapai batas resmi Free Tier **10 request/hari** saat pengujian berulang.
- [x] Panduan worker produksi: `docs/OPERASIONAL_TTS_ASISTEN_GURU.md`.

### 2026-08-18 - Pemulihan antrean TTS lokal

- [x] Akar masalah `Menunggu antrean`: job database `attempts=0` dan tidak ada proses `queue:work`; Gemini belum dipanggil.
- [x] Job `Cerita Siklus Latar` diproses nyata sampai `ready`: MP3 2.362.029 byte, durasi 147,54 detik, antrean kembali kosong.
- [x] Local Windows memakai `AI_TTS_DISPATCH=deferred` agar tidak bergantung pada terminal worker.
- [x] Production memakai connection/queue khusus `tts` dengan `retry_after=1900s`, lebih panjang daripada timeout job `1800s`.
- [x] Antrean `queued` lebih dari lima menit menjadi `failed` dengan pesan worker tidak aktif, bukan menunggu tanpa batas.
- [x] Gate TTS/Asisten Guru: **77 passed, 396 assertions**; PHP lint dan `git diff --check` lulus.
- [x] Playback MP3 diperbaiki dengan resolver ekstensi stream/download; target berbagi identik dideduplikasi dan label mapel/kelas tidak diulang.
- [x] Gate setelah perbaikan playback/target: **79 passed, 407 assertions**; MP3 nyata berhasil didekode FFmpeg tanpa error.
- [x] Audio multibahasa menerjemahkan narasi ke bahasa pilihan sebelum TTS; jalur Indonesia tetap tanpa request terjemahan.
- [x] Bahasa audio diperluas menjadi 15 pilihan resmi: Indonesia, Mandarin, Jepang, Arab, Inggris, Korea, Melayu, Spanyol, Prancis, Jerman, Hindi, Thai, Vietnam, Portugis, dan Rusia.
- [x] Smoke nyata Indonesia -> Mandarin: terjemahan 57 -> 14 karakter, MP3 69.165 byte/4,24 detik; gate akhir **81 passed, 416 assertions**.

---

## Modul Ujian + Bank Soal (dari Codex/dedyzhang) â€” SELESAI

Ref: merge commit `b4b5e4b` via `codex/sekretaris-absensi-scope`.

- [x] Modul `ujian` + `bank_soal`, authoring, token gate, lockout, grading, transfer nilai
- [x] **Verifikasi:** `php artisan test --filter="Ujian|BankSoal"` â†’ **72 passed**

---

## Absensi sekretaris kelas (dari Codex) â€” SELESAI

- [x] `76a0ddb` â€” batasi akses absensi sekretaris (hanya kelas sendiri, tanpa rekap)
- [x] **Verifikasi:** `php artisan test --filter=SekretarisAbsensiTest` â†’ **9 passed**

---


## Rencana AI Keuangan — Bendahara SPP (PRD)

Ref: `docs/keuangan-ai/PRD.md` · Fase A detail: `docs/keuangan-ai/features/01-ai-bendahara-spp-fase-a.md`

**Commit migrasi Codex→Cursor + dokumen PRD:** `0ab2e84`

| Fase | Ringkasan | Status |
|------|-----------|--------|
| **A** | A1 antrian prioritas (rule-based), A2 OCR saran bukti (HITL), A3 dashboard SPP bulanan, A4 parser rekening koran (luas BCA), A5 activity log transisi keuangan; akses bendahara — **bukan** duplikat Narasi Data pimpinan; nominal BIGINT tanpa AI hitung rupiah | [x] Implementasi Fase A |
| **B** | Matching mutasi ↔ tagihan (skor + konfirmasi), duplikat/anomali flag, notifikasi antrian | [x] Implementasi Fase B |
| **C** | Wawasan naratif non-nominal internal bendahara, ekspor paket verifikasi, integrasi gateway opsional | [x] Implementasi Fase C (C3 gateway deferred) |

**2026-08-08 — Integrasi UI:** Menu sidebar Asisten Bendahara dihapus; fitur A/B/C tersebar di **Pembayaran SPP** (dashboard + wawasan/ekspor), **Verifikasi** (antrian prioritas, rekonsiliasi, anomali, jejak audit), **Bank & Metode** (ringkasan rekonsiliasi). Route `bendahara-ai/*` lama redirect + OCR/wawasan/ekspor tetap aktif. Panduan: `docs/PANDUAN_PENGGUNAAN_SIMS_APP.md` §18 + `resources/panduan/visual.html` kartu Keuangan diperbarui.

---

## Ringkasan tes

### 2026-08-18 — Prototype AI Question Quality Checker

- [x] Service checker dengan output skor, status, Bloom, kesulitan, issues, saran, versi perbaikan, dan catatan guru
- [x] Fallback rule-based saat key kosong, provider gagal, atau JSON AI tidak valid; tanpa migration/database baru
- [x] Endpoint GET/POST di konteks Ruang Kelas dengan `ClassroomPolicy::manage`, validasi `FormRequest`, throttle, dan usage log AI
- [x] UI Blade/Alpine dari Arena Belajar; loading/error/result, mobile 320/375/430 px tanpa horizontal overflow, tombol utama 44 px
- [x] Code review hardening: AI rate limit terpusat, policy di FormRequest, schema output wajib, status konsisten dengan skor, prompt untrusted-data boundary, peringatan data pribadi, dan validasi per tipe soal
- [x] Regression edge case: opsi angka `0`, kunci PG kompleks, opsi tersembunyi, Menjodohkan kosong, serta Benar/Salah tanpa kunci
- [x] Smoke test AI nyata berhasil; gate `QuestionQualityChecker|GameQuiz|BankSoal|GeminiService` — 63 passed, 267 assertions
- [x] Security dependency: Guzzle `7.13.2` → `7.15.3` beserta Promises `2.5.2` dan PSR-7 `2.13.0`; advisory Guzzle hilang dan regression seluruh AI — 210 passed, 952 assertions, 3 skipped
- [x] Vite production build dan Pint untuk file baru lulus
- [x] Pratinjau soal Generator Soal kini memiliki tombol cek kualitas per butir; modal mengirim konteks soal ke endpoint checker yang sama, sementara export Word/PDF tetap bersih

### 2026-08-18 — Riwayat Audio Asisten Guru

- [x] History 20 audio terbaru per guru, termasuk status, bahasa, durasi, dan waktu pembuatan
- [x] Audio history dapat dibuka kembali untuk diputar, diunduh, dibagikan, atau dihapus
- [x] History diperbarui otomatis saat generate dan polling selesai tanpa mengubah pipeline Gemini TTS/MP3
- [x] Verifikasi fokus: `AiTeacherAudioTest` — 28 passed, 111 assertions

| Area | Filter | Passed |
|------|--------|--------|
| Arena Belajar + Jagat Misi kelas | `GameQuiz\|GameLive\|GameTemplate\|ArenaBelajar\|MissionClassroom` | 49 |
| Ludensa | `Ludensa\|SimsGemini` | 15 |
| Grup Chat + Pengumuman | `GrupChat\|Pengumuman` | 99 |
| Private Chat | `PrivateChat` | 5 |
| AI (analyze/RAG/teacher) | `AiAnalyze\|AiRag\|AiTeacher` | 55 |
| Keuangan AI Fase A + SPP | `KeuanganAi\|KeuanganSpp\|RekeningKoran` | 43+ |
| Ujian + Bank Soal | `Ujian\|BankSoal` | 72 |
| Absensi sekretaris | `SekretarisAbsensi` | 9 |

---

## Perintah kontrol (prd-generator)

| Perintah | Aksi |
|----------|------|
| `lanjut` | Task berikutnya di fitur aktif |
| `lanjut fase [n]` | Lompat ke fase/fitur |
| `ulangi task ini` | Revisi task terakhir |
| `skip` | Lewati task (sebut alasan) |

**Gate approval FL** wajib sebelum task: migration/schema baru, uang/pembayaran, auth/policy, hapus data produksi.

---

## Modul Sarpras — Refactor Penyederhanaan (2026-08-08)

Ref: `docs/sarpras/PRD.md`, `docs/sarpras/features/01-refactor-simplify.md`

- [x] Gabung booking → peminjaman terpadu + `RuanganJadwalService`
- [x] Sederhanakan denah (hapus route editor kanvas/hotspot)
- [x] Pengadaan → Usulan Kebutuhan + BA serah terima PDF
- [x] Menu sidebar 6 item; hapus tab internal modul
- [x] Export KIB/KIR, stok opname ATK, activity log ringan
- [x] Tes `php artisan test --filter=Sarpras`
- [x] Panduan: `docs/PANDUAN_PENGGUNAAN_SIMS_APP.md` §17 + `resources/panduan/visual.html` kartu Sarpras (6 menu, tanpa booking/kanvas).

---

## Survei Pembelajaran — dijalankan lokal 2026-11-13

Modul survei siswa tentang guru/wali kelas, masuk lewat QR + akun SIMS, sesi token kelas, hasil agregat setelah ditutup.

- [x] Periode, sasaran dari Jadwal/Walikelas, QR `survei.masuk`, satu kirim per siswa
- [x] Sesi token 6 digit (wajib), SEB/Kios opsional (default mati)
- [x] Analisis guru, PDF kelas, CSV, daftar responden (pembuat periode)
- [x] `php artisan test --filter=SurveiPembelajaranTest` → **17 passed / 334 assertions**
- [x] Alur lokal sqlite: admin buat periode → mulai sesi → siswa token + kirim → tutup → analisis
- [ ] Commit & deploy server sekolah — **tunggu approval FL**
- [ ] Konfigurasi SEB/Kios produksi (`SURVEI_SEB_*`, `SURVEI_KIOS_PUBLIC_KEYS`)


 # # #   F a s e   4 :   A b s e n s i   K e g i a t a n 
 -   [ x ]   T a s k   1 - 5 :   U I   T i r u a n   H a l a m a n   K e g i a t a n ,   D a f t a r ,   S c a n   H a d i r 
 -   [   ]   T a s k   6 :   M i g r a t i o n   &   M o d e l 
 -   [   ]   T a s k   7 - 1 0 :   C o n t r o l l e r   &   R o u t e   ( P r o s e s   B i s n i s ) 
 -   [   ]   T a s k   1 1 :   A u t h o r i z a t i o n   &   L a p o r a n   P D F 
 
 
 # # #   F a s e   4 :   A b s e n s i   K e g i a t a n 
 -   [ x ]   T a s k   1 - 5 :   U I   T i r u a n 
 -   [ x ]   T a s k   6 :   M i g r a t i o n   &   M o d e l 
 -   [ x ]   T a s k   7 - 1 0 :   C o n t r o l l e r   &   R o u t e   ( P r o s e s   B i s n i s ) 
 -   [ x ]   T a s k   1 2 :   S e e d e r 
 
 
## Eksperimen tema aplikasi — 2026-11-14 (lokal, tanpa commit/push)

- [x] Pilihan Windows 11, Mac OS, Fluent, macOS Minimal, Bento.
- [x] Tema seluruh layout aplikasi: sidebar, header, tombol, form, tabel, kartu, dialog; preferensi tersimpan per pengguna.
- [x] Pratinjau tema mengikuti navigasi tab; simpan/batal/reset melalui Studio Tampilan.
- [x] Spotlight dari katalog navigasi akun: Ctrl/Cmd+K, panah/Enter/Esc, tombol mobile.
- [x] Wallpaper pribadi JPG/PNG/WebP: validasi format/ukuran, optimasi foto, posisi, ukuran, redup, buram, simpan/batal/hapus.
- [x] Wallpaper disimpan dalam IndexedDB per akun pada browser ini; tidak dikirim ke server sekolah.
- [x] Wallpaper bawaan Aurora/Taman/Senja, tema terang/gelap, reduced motion, pembatasan personalisasi di kiosk/mode ujian siswa.
- [x] ProfilePreferenceTest: 6 lulus / 53 assertions; tes frontend wallpaper: 4 lulus.
- [x] Build Vite dan kompilasi Blade berhasil pada sesi implementasi; diff check bersih.
- [x] Pemeriksaan Safari pada akun demo: dashboard/profil, perubahan sidebar/header, unggah gambar contoh, simpan dan reload wallpaper, mode gelap, pencarian Spotlight.
- [ ] Pemeriksaan perangkat HP fisik belum dilakukan.

Catatan: tautan Jadwal Mengajar pada akun demo menghasilkan 403 dari otorisasi existing. Hak akses tidak diubah dalam eksperimen UI ini.

- [x] Perbaikan kontras Bento + sidebar gelap/Analyzer: teks, ikon, dan panah tombol grup mengikuti warna panel; hover tetap terbaca.
- [x] Hover/fokus menu sidebar memakai latar sidebar aktual (termasuk Analyzer/kustom); ikon mengikuti warna teks menu agar kontras konsisten.

## Penyempurnaan identitas tema dan performa — 2026-11-14

- [x] Lima wallpaper SVG lokal khusus tema; opsi Ikuti tema aplikasi dan override foto pribadi.
- [x] Komposisi berbeda: Windows/taskbar, Fluent/navy, macOS/jendela+dock, Minimal/panel kertas, Bento/kartu berbingkai.
- [x] Label pemilih Gaya Tampilan; halaman pengaturan Personalisasi; pratinjau tema diperbarui.
- [x] Dock memakai registry navigasi existing, tanpa query atau endpoint baru.
- [x] Polling ticker 60 detik sesuai TTL cache; satu timer berkunci, guard request berjalan, jeda tab tersembunyi, tidak aktif saat kiosk/mode ujian.
- [x] Memo TickerStats per-request mencegah pembacaan cache/database berulang; clear menghapus memo juga.
- [x] 17 tes fitur terkait preferensi/role/tampilan lulus; 8 tes frontend wallpaper/polling lulus; build berhasil.
- [x] Safari: wallpaper macOS, bingkai, dock, label baru, dan isi dashboard terlihat.
- [ ] Pemeriksaan query seluruh modul aplikasi dan HP fisik belum dilakukan.

## Penyatuan bingkai aplikasi — 2026-11-14

- [x] Sidebar, header, dan konten berada dalam satu bingkai; celah dan bayangan antarbagian dihapus.
- [x] Dock Windows/macOS dipindahkan ke alur layout di bawah konten, tanpa posisi mengambang atau menutup halaman.
- [x] Pemilih Gaya Tampilan menyatu sebagai toolbar halaman; wallpaper dan karakter tema dipertahankan.
- [x] Mobile memakai bingkai penuh layar dan sidebar overlay existing.
- [x] Perubahan hanya layout/CSS, tanpa penambahan query atau timer polling.
- [x] Fluent mode terang: latar sidebar memakai --sbg navy yang berpasangan dengan teks terang; wallpaper tidak lagi menggantinya dengan panel pucat.
- [x] Windows/Fluent: kembalikan identitas sidebar biru dan tambahkan lis kuning pada pemisah sidebar/header, indikator aktif, ikon aktif, dan pratinjau; kontras hover mengikuti palet biru.

## Review performa dan SIMS-NET ringan — 2026-11-14

- [x] Review kode tema/wallpaper/footer; temuan dan batas pengukuran di docs/REVIEW-PERFORMA-TEMA-SIMS-NET.md.
- [x] SIMS-NET statis, satu salinan statistik, geser horizontal; hapus animasi marquee/ping dan shadow besar.
- [x] Tetap polling tunggal 60 detik, cache/memo statistik existing, pembaruan textContent hanya jika berubah.
- [x] URL Blob wallpaper dipakai ulang selama foto sama; slider tidak lagi mengganti URL gambar.
- [x] Tes terarah, build Vite, dan kompilasi Blade lulus; tanpa migration/dependensi baru.
- [ ] Benchmark GPU/HP fisik dan uji beban banyak pengguna belum dilakukan.

## SIMS-NET ticker bursa 2D — 2026-11-14

- [x] Riset sumber primer web.dev/MDN/W3C; rekomendasi di docs/RISET-SIMS-NET-TICKER-2D.md.
- [x] CSS translateX linear, kecepatan sekitar 32 px/detik; satu track dan satu salinan visual aria-hidden di browser.
- [x] Tombol Jeda/Lanjut, hover/fokus, hidden tab, dan reduced motion; jeda manual melewati polling data.
- [x] Tanpa library, loop frame JS, query tambahan, atau interval data baru; polling tetap 60 detik.
- [x] Tes animasi/polling/wallpaper, fitur ticker, kompilasi Blade, dan build diperiksa.
- [ ] Pengukuran FPS/GPU dan HP fisik belum dilakukan.

## Perbaikan ticker diam dan integrasi tema — 2026-11-14

- [x] Safari terkonfirmasi memakai reduced motion; kontrol Gerakkan tetap tersedia sebagai pilihan eksplisit khusus ticker.
- [x] Pilihan gerak diingat per akun dalam tab via sessionStorage dan bertahan setelah refresh, tanpa mengubah pengaturan OS.
- [x] Tombol Lanjut tidak lagi tertahan karena fokus/hover pada tombol; fokus keyboard di area statistik tetap menyediakan pembacaan diam.
- [x] Skrip lokal ticker dimuat tepat setelah markup, terpisah dari antrean defer CDN.
- [x] Palet Windows/Fluent/macOS/Minimal/Bento serta mode gelap mengikuti tema melalui CSS; tinggi bar 32 px tanpa blur/shadow/3D.
- [x] Animasi CSS 2D tanpa request/query/timer frame baru; polling existing tetap 60 detik.
- [x] 12 tes frontend lulus; 6 tes fitur terkait diperiksa; Safari: Gerakkan → Jeda dan status aktif bertahan setelah refresh.

## Riwayat menu dan favorit pada bar bawah — 2026-11-14

- [x] Ganti pintasan tetap dengan gabungan favorit + riwayat menu, maksimal 10 entri unik.
- [x] Bintang dapat dipasang/dilepas; favorit didahulukan dan dipertahankan saat riwayat berganti; favorit maksimal 10.
- [x] Gunakan registry navigasi sesuai akses dan penyimpanan browser existing; subhalaman dipetakan ke menu induknya.
- [x] Riwayat sidebar existing tetap tersedia; dock di desktop seluruh tema, dapat digeser horizontal.
- [x] Tanpa endpoint, query, dependency, atau polling baru. Tes riwayat/favorit 3 lulus, tes preferensi dan build diperiksa.


## Detail survei compact — 2026-11-14

- [x] Panel sesi dan QR berdampingan di desktop; lebar konten mengikuti area utama, dengan susunan satu kolom pada layar sempit.
- [x] Kelompok tingkat kelas memakai grid rapat, pilihan kelas lebih kecil, serta tombol Semua/Lepas tetap berfungsi.
- [x] Total siswa yang sudah mengirim terlihat pada ringkasan; rincian per kelas dan tabel sasaran dapat dibuka saat diperlukan.
- [x] QR 160 px dengan warna hitam/putih eksplisit tetap terlihat pada mode terang maupun gelap; tautan masuk tetap tersedia.
- [x] Perubahan hanya Blade/CSS: tanpa endpoint, query, polling, dependency, migration, atau perubahan otorisasi baru.
- [x] Dua tes alur survei lulus (112 assertion), Blade cache dan build berhasil; Safari memverifikasi pilihan 3 kelas, lepas pilihan, rincian, serta mode terang/gelap. Tidak memulai sesi atau mengirim respons demo.
- [x] Perubahan tetap lokal, tanpa commit atau push.


## Sasaran survei per kelas — 2026-11-14

- [x] Khusus bagian Sasaran survei: ganti tabel panjang dengan grid ringkasan kelas yang langsung terlihat.
- [x] Header menampilkan total sasaran dan kelas; rincian guru, mata pelajaran, respons, serta tautan hasil dibuka per kelas.
- [x] Gunakan koleksi targets existing dan elemen details native, tanpa query, polling, atau JavaScript baru; aturan akses hasil dipertahankan.
- [x] Tes render survei lulus (16 assertion), diff bersih; Safari memverifikasi 20 sasaran dalam 10 kelas dan rincian kelas 7 A dapat dibuka/ditutup.
- [x] Perubahan tetap lokal tanpa commit atau push.


## Rekap otomatis skor dan catatan per guru — 2026-11-14

- [x] Perluas analisis existing dengan nilai skala 100 (skor × 20), cakupan kelas/isian, dan rincian indikator; skor berbobot dan pemisahan mapel/wali dipertahankan.
- [x] Rekap catatan berdasarkan sasaran guru, peran, serta pertanyaan; tulisan sama digabung dengan jumlah kemunculan, tanpa AI atau perubahan maksud teks.
- [x] Komentar tetap khusus admin pembuat periode; petugas berizin memperoleh versi skor tanpa komentar. Kelompok kecil tidak ikut rekap; identitas akun siswa tidak dimuat.
- [x] Tambahkan unduhan PDF seluruh guru untuk bahan kepala sekolah/waka, tombol pada analisis, Cache-Control no-store, dan audit unduhan.
- [x] Dua SELECT untuk seluruh rekap, tanpa N+1/polling/dependency baru. Tanpa migration, perubahan RBAC, atau perubahan data/rapor siswa.
- [x] 22 tes survei lulus (493 assertion), build berhasil, sintaks/diff diperiksa. Lima halaman PDF data uji dirender dan ditinjau secara visual.
- [x] Panduan fitur/perhitungan/akses tersedia di docs/REKAP-SKOR-DAN-CATATAN-GURU.md. Tidak ada commit atau push.


## Contoh rekap guru — 2026-11-14

- [x] Buat output/pdf/Contoh-Rekap-Survei-Guru.pdf dari template dan perhitungan aplikasi dengan model fiktif di memori (tanpa perubahan data survei).
- [x] Contoh 2 kelas, 12 isian, 120 jawaban: skor 4,30/5 = 86/100, 24 catatan dikelompokkan menjadi 4 tulisan. Tiga halaman diperiksa secara visual.


## Format laporan evaluasi guru — 2026-11-14

- [x] Susun ulang PDF dengan kop, identitas guru/peran, nilai utama, tabel indikator, ringkasan pembinaan, catatan/frekuensi, rencana tindakan, refleksi, dan ruang paraf.
- [x] Periksa panduan Kemendikdasmen; tandai sebagai evaluasi pendukung berbasis survei siswa, tanpa klaim formulir atau predikat PKG resmi.
- [x] Pisahkan hasil dan tindak lanjut; contoh satu guru sekarang dua halaman A4. Tambahkan nomor halaman pada unduhan PDF aplikasi.
- [x] Skor dan catatan contoh tetap 86/100 serta 24 catatan. Privasi, query, polling, dan otorisasi tidak berubah.
- [x] Tes laporan lulus (42 assertion), diff/sintaks diperiksa, dan kedua halaman contoh dirender serta diperiksa secara visual.
- [x] Perubahan tetap lokal, tanpa commit atau push.


## Tindak lanjut review kode lintas Claude — 2026-11-14

- [x] Rute survei tetap melewati auth dan gate wajib ganti password; pendaftaran wajah dikecualikan khusus nama route survei dan alasannya didokumentasikan.
- [x] Abaikan artefak lokal `output/` dan `tmp/` dari Git; hapus view survei lama yang tidak lagi dirender, pertahankan route redirect kompatibilitas.
- [x] PDF laporan per kelas kini menampilkan nomor halaman seperti laporan per guru.
- [x] Perubahan lokal saja; tanpa commit atau push.


## Audit query dan polling — 2026-11-14

- [x] Dashboard komposisi memakai agregat siswa yang sudah dihitung controller; hilangkan dua hitungan gender ulang.
- [x] Komentar kelas tidak membuat interval saat dinonaktifkan; fallback aktif memakai scheduler berkunci. Forum memakai scheduler dengan guard tab tersembunyi/permintaan berjalan.
- [x] Semua pemanggil simsPollInterval memakai key unik; inbox chatbot tidak menggandakan listener Firebase, fallback interval terdeduplikasi, dan request antrean/pesan dijaga agar tidak overlap. Poll chatbot siswa juga memakai guard in-flight.
- [x] Tes dashboard/survei/chatbot: 26 lulus / 553 assertions; tes frontend polling: 5 lulus; PHP lint dan diff check bersih.
- [x] Tidak mengubah migration, data, atau matriks izin; tanpa commit/push.

## Penyelesaian sisa review lintas Claude — 2026-11-14

- [x] Verifikasi ulang temuan #1–#5: pengecualian wajah survei terdokumentasi, view `isi` lama terhapus, `output/`/`tmp/` di-ignore, nomor halaman PDF kelas ada, theme-picker tampil di dashboard siswa/orang tua.
- [x] Tombol "Tutup periode survei" kini hanya tampil untuk admin (periode bertoken: hanya pembuat), sama dengan otorisasi `tutup()`.
- [x] Variabel `SURVEI_SEB_*`/`SURVEI_KIOS_*` di `.env.example` dipindah ke blok sendiri di akhir file.
- [x] Tambah 2 test: rute survei bebas gate wajah tetapi tetap wajib ganti password (termasuk orang tua); tombol tutup tersembunyi bagi surveyor non-admin. `SurveiPembelajaranTest` → **24 passed / 509 assertions**.
- [x] Perubahan lokal saja; tanpa commit atau push.

## Audit query & polling duplikat dashboard/survei — 2026-11-14

- [x] Probe sementara per request (SQL + binding identik) atas seluruh `SurveiPembelajaranTest` dan test dashboard/tampilan; dashboard admin/humas/siswa juga diukur di DB lokal (cache dingin & hangat). Probe sudah dihapus.
- [x] `Langganan::current()`: saat belum ada baris lisensi, `Cache::remember` tidak menyimpan null sehingga middleware + banner query 2× per request admin. Kini menyimpan penanda `false`.
- [x] Halaman Personalisasi memuat `user_preferences` 2× (controller + layout); kini `prefTampilan(segar: true)` sekali dan dimemo untuk layout.
- [x] `simsPollInterval`: guard cadence tanpa toleransi bisa melewatkan tick sah karena jitter timer (60 dtk jadi 120 dtk); tambah toleransi `min(1 dtk, ms/10)` + test frontend jitter.
- [x] Hasil di browser (admin): dashboard 32 query, survei 8–10 query, Personalisasi 7 query, semuanya tanpa duplikat; satu timer polling (`footer-ticker`), halaman survei tanpa polling.
- [x] Test: 212 lulus pada filter Survei/Dashboard/Profile/Setting/Ticker/Sarpras/ModeUjian/Auth; frontend 19/19. Kegagalan yang sudah ada sebelumnya dan tidak terkait: `LanggananTest` (3), `UjianAuthoringTest` (5, "mode skor field is required").
- [x] Dashboard Sarpras dirapikan: total aset & aset berisiko dari agregat kondisi yang sudah ada; tiga hitungan kerusakan terbuka jadi satu agregat `status, urgensi`; eager-load `pelapor`/`pengaju` yang tidak dipakai view dihapus; `ruangan` untuk booking + aset perlu tindakan dimuat sekali.
- [x] Bug tampilan: kartu "Jadwal Ruangan Hari Ini" memanggil relasi `pemohon` yang tidak ada di `Peminjaman`, jadi nama peminjam selalu "-". Kini `peminjam->name` dengan eager-load guru/siswa (tanpa N+1).
- [x] `SarprasDashboardTest` diperkuat: cek angka ringkasan, nama peminjam/ruangan, dan tidak ada query identik dalam satu render (gagal di kode lama). Sarpras/Ticker/Dashboard: 79 lulus. Browser: `/sarpras` 20 query, 0 duplikat.
- [x] Perubahan lokal saja; tanpa commit atau push.

## Pemilih tema dashboard minimalis — 2026-11-14

- [x] Hapus judul "Ruang kerja sekolah / Hari ini di SIMS" dan kalimat penjelas pratinjau; pemilih Gaya Tampilan kini kapsul kecil rata kanan (ikon palet + pilihan tema, label `sr-only`, petunjuk simpan di tooltip).
- [x] `theme-experiences.css`: toolbar dashboard tanpa garis bawah/padding.
- [x] Dicek di browser: terang/gelap, lebar HP 375 px tanpa scroll horizontal, pratinjau tema tetap berfungsi. Test dashboard/preferensi & frontend lulus. Tanpa commit/push.

## Dock "Baru dibuka" tanpa petunjuk favorit — 2026-11-14

- [x] Hapus baris "★ Favorit di depan" dan label "Baru dibuka" (beserta CSS-nya) dari dock riwayat; dock kini hanya berisi item aplikasi.
- [x] Nama aplikasi di dock tampil utuh (tanpa batas 100 px/ellipsis); item di tengah dengan `justify-content: safe center`, bisa digeser horizontal bila penuh. Dicek 10 item di 1024 px: tidak ada nama terpotong, item pertama tidak terpotong.
- [x] Label kembar di registri navigasi (dock/Baru dibuka/Favorit) diberi nama grup, mis. "Dashboard · Sarana & Prasarana", "Data Guru · Cetak Data"; sidebar grup tetap label asli.
- [x] Dicek di browser 1280 px tema macOS; test frontend 19/19 dan 61 test layout/dashboard/survei lulus. Tanpa commit/push.

## Kepatuhan Mode Ujian untuk tampilan, survei, sarpras — 2026-11-14

- [x] Audit: siswa saat Mode Ujian sudah dikunci ke `ujian.*` (survei, dashboard, Personalisasi, Sarpras dialihkan); tema/wallpaper, dock, spotlight, ticker, Firebase, dan semua widget polling sudah nonaktif.
- [x] Header: ikon "Tema dan wallpaper" dan toggle gaya Soft/Analyzer disembunyikan bagi siswa saat Mode Ujian (dulu tampil tetapi permintaannya ditolak 403).
- [x] Ticker SIMS-NET: endpoint `dashboard.ticker-stats` mengembalikan `{mode_ujian:true}` tanpa query statistik; tab staf yang terbuka sebelum Mode Ujian menghentikan timer `footer-ticker` dan menyembunyikan ticker.
- [x] Survei (keputusan FL: Mode Ujian = saklar induk): middleware `NonaktifSaatModeUjian` di 26 rute survei mematikan modul untuk SEMUA peran — staf diarahkan ke dashboard dengan toast "Informasi" (flash `info_mode_ujian`), JSON 423; siswa tetap dialihkan ke ruang ujian. Menu Survei disembunyikan dari sidebar. Keputusan FL: Sarpras dan tema/tampilan staf tetap aktif selama Mode Ujian.
- [x] Test: 2 test baru di `ModeUjianLockdownTest` + 1 test frontend; 191 test terkait lulus, frontend 20/20. Diverifikasi di browser (Mode Ujian dinyalakan lalu dimatikan lagi di DB lokal). Tanpa commit/push.
- [x] Siswa yang sedang mengisi survei di SEB/SIMS Kios saat Mode Ujian dinyalakan dialihkan ke ruang ujian (survei mati total, sesuai keputusan FL).
- [x] Test diperbarui; 252 test terkait lulus, frontend 20/20; dicek di browser lalu Mode Ujian lokal dimatikan lagi.

## Perbaikan tombol "Mulai layar penuh" survei macet — 2026-11-14

- [x] Akar masalah: `survei-fokus.js` menonaktifkan tombol lalu menunggu `requestFullscreen()`; di browser/WebView yang janjinya tidak pernah selesai, tombol abu-abu selamanya (halaman tampak freeze). iPhone tidak mendukung layar penuh sama sekali sehingga siswa tidak pernah bisa mengisi.
- [x] Permintaan layar penuh dibatasi 2,5 detik; bila tidak didukung/ditolak/habis waktu, survei tetap dibuka dalam mode fokus tanpa layar penuh (tetap dijeda saat pindah tab/aplikasi). Perangkat tanpa dukungan langsung melihat tombol "Mulai survei". SEB/SIMS Kios tidak berubah.
- [x] Test frontend baru `tests/Frontend/survei-fokus.test.cjs` (5 kasus); frontend 25/25, `SurveiPembelajaranTest` 24/24. Diverifikasi di browser sebagai siswa uji `siswa_coba_7a` (akun lokal, kredensial di `tmp/akun-demo-survei.txt`). Tanpa commit/push.

## Teks tautan-tombol rata tengah — 2026-11-14

- [x] Tema memberi `min-height` 40–44 px pada `.btn-*`; tautan `<a class="btn-primary">` (mis. "Unduh PDF rekap guru") teksnya menempel kiri-atas. `sims-appearance.css`: tautan bergaya tombol jadi `inline-flex` + rata tengah, dengan `:where()` (specificity 0) agar utilitas Tailwind tetap menang. Dicek di browser + 10 halaman tanpa regresi. Tanpa commit/push.
