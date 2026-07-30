# Progress — SIMS MW

> **Agent:** baca file ini + `PRD.md` + `features/*.md` di awal sesi. Status task di `features/NN-*.md` (suffix `[DONE]`) harus sinkron dengan checklist di bawah. Setelah selesai satu task, centang di sini dan update baris task di file fitur terkait.

**Verifikasi terakhir:** 2026-07-23 — `php artisan test --filter="GameQuiz|GameLive|GameTemplate|ArenaBelajar|MissionClassroom"` → **49 passed**; `--filter="Ludensa|SimsGemini"` → **15 passed**.

---

## Fase 1: Bank Soal & Kuis Async — SELESAI

Ref: `features/01-bank-soal-kuis-async.md` (task 1–11 `[DONE]`)

- [x] 1–5 UI dummy → navigasi → poles responsif
- [x] 6 Migration & model `game_*` (6 tabel)
- [x] 7 `GameQuizController` CRUD + assign + `DB::transaction()`
- [x] 8 `GameAttemptController` + auto-grading + monitor
- [x] 9 `GameQuizPolicy` + authorization
- [x] 10 Transfer nilai + `Audit::log`
- [x] 11 Seeder + `GameQuizTest` (19 tests)

## Fase 2: Live Session & Leaderboard — SELESAI

Ref: `features/02-live-session-leaderboard.md` (task 1–11 `[DONE]`)

- [x] 1–5 UI live lobby/podium + builder Match/Short Answer (dummy → poles)
- [x] 6 Migration `game_live_sessions` + model
- [x] 7 `GameLiveController` + leaderboard JSON + polling
- [x] 8 Grading Match Up & Short Answer
- [x] 9 Policy aksi live
- [x] 10 FCM `ArenaLiveStartedNotification` + activity log
- [x] 11 `GameLiveTest` (7 tests)

## Fase 3: Template Interaktif & Mode Tim — SELESAI

Ref: `features/03-template-interaktif.md` (task 1–11 `[DONE]`)

- [x] 1–5 Template switcher, mode tim, PDF preview, navigasi, poles
- [x] 6 Migration teams + template fields
- [x] 7 `GameTemplateController` + team scoring
- [x] 8 DomPDF worksheet + kunci guru
- [x] 9 Policy tim & export
- [x] 10 Offline queue localStorage + `syncOffline`
- [x] 11 `GameTemplateTest` (6 tests)

**Subtotal Arena Belajar (kuis):** 32 tests (+ `ArenaBelajarDemoFlowTest` 2, `GameQuizImporterLooksLikeTest` 1)

---

## Jagat Misi (terintegrasi Arena Belajar) — SELESAI

- [x] Fase 1–7: models, player, debrief, analytics, mission builder
- [x] Fase 8: `MissionClassroomController` + assign/play/transfer di Ruang Kelas
- [x] Merge branding: satu tab **Arena Belajar** (Kuis + Misi); `jagat_misi` → `arena_belajar`
- [x] `MissionClassroomTest` (14 tests)

### Sisa opsional

- [ ] Admin dashboard khusus JagatMISI (SIMS sudah punya admin sendiri)

---

## Grup Chat (Grup Kelas & Paguyuban Orang Tua) — SELESAI (uncommitted)

Chat otomatis per kelas (dua tipe: `kelas` & `paguyuban`), keanggotaan diturunkan dari
struktur sekolah lewat `GrupChatService`, tidak ada fitur PRD/features/*.md formal untuk
modul ini — dibangun langsung via sesi chat, dilacak di sini saja.

- [x] Fase 1: Model/migration (3 tabel), sync service, policy, command `grupchat:sinkron`
      (dijadwalkan 01:00), toggle modul `grup_chat`, menu sidebar
- [x] Fase 2: Reply pesan, lampiran foto/berkas (reuse `App\Support\ChatAttachments`),
      hapus/moderasi pesan (`GrupChatMessenger::hapus()`)
- [x] Code review (`/code-review`, 12 reviewer) — 1 P0 + 3 P1/P2 diterapkan langsung
      (kebocoran isi pesan lewat kutipan balasan, race preview grup saat hapus,
      urutan hapus-file-vs-transaksi, idempotensi hapus ganda); 3 temuan performa/data
      (N+1 sync kelulusan & rombel, cascade-delete kelas menghapus riwayat chat)
      diperbaiki setelahnya atas permintaan FL
- [x] Fase 4: Notifikasi digest — `grupchat:kirim-notif` (dijadwalkan tiap 15 menit),
      `GrupChatDigestNotification` (database + FCM), 1 notifikasi per user walau
      unread di beberapa grup, menghormati `muted_until` & grup `arsip`
- [x] Test: 70 test (`GrupChatAksesTest`, `GrupChatPollTest`, `GrupChatSinkronTest`,
      `GrupChatModulTest`, `GrupChatLampiranTest`, `GrupChatDigestTest`)
- [x] Fase 5: tiga sisa opsional dituntaskan —
      - Komposer kini membuka jalur balas walau `boleh_kirim` false: flag baru
        `bolehBalasPengumuman` (`GrupChatController::bolehBalasPengumuman()`) dikirim
        lewat `show()` & `poll()`, tombol "Balas" & textarea di `grup/show.blade.php`
        dikunci per pesan (`bolehBalas(m)` — hanya pesan staf yg boleh dibalas non-staf
        di mode pengumuman), 3 test baru di `GrupChatLampiranTest`.
      - `GrupChatService::syncGuru()` **dihapus** — dead code, diverifikasi tidak ada
        pemanggil: tiap mutasi nyata (Ngajar create/delete lewat `NgajarObserver`,
        reassign walikelas lewat `KelasController::walikelas()`) sudah memanggil
        `syncKelas()` langsung per-kelas; jalur lain (impor/SQL mentah) sudah tercakup
        rekonsiliasi malam `grupchat:sinkron`.
      - Route `grup.pesan.*` & `grup.lampiran.unduh` kini pakai `Route::scopeBindings()`
        (butuh alias relasi `GrupChat::pesans()` — nama method WAJIB hasil
        `Str::plural('pesan')`, bukan `pesan()`/`messages()`) — kombinasi
        `{grup}/{pesan}` yang tak nyambung sekarang 404 di level routing, bukan
        cuma lewat `abort_unless()` manual di controller (yang tetap dipertahankan
        sebagai guard redundan). 2 test baru untuk cross-grup 404.

### Sisa

- [ ] Commit & deploy — **tunggu approval FL**

---

## Integrasi Ludensa — DALAM PENGERJAAN (uncommitted)

Modul permainan edukatif SD (paket `ludensa/*`) terintegrasi ke SIMS via service provider.

- [x] `config/ludensa.php` + `LudensaIntegrationServiceProvider`
- [x] Adapter: `LudensaJenjang`, `LudensaSchool`, `InteractsWithLudensa`
- [x] `SimsGeminiAiJsonGenerator` (binding AI JSON ke Ludensa)
- [x] `ModulAktif` + toggle `fitur_ludensa_aktif` / middleware `modul:ludensa`
- [x] Tab **Fitur** di Pengaturan Sistem (on/off Arena Petualangan SD + modul lain)
- [x] Activity log migrations (Spatie)
- [x] `SimsLudensaSeeder` + `LudensaIntegrationTest` (10 tests)
- [x] Unit: `LudensaJenjangAnakTest`, `SimsGeminiAiJsonGeneratorTest` (5 tests)
- [ ] Commit & deploy ke staging — **tunggu approval FL**
- [x] Audit keamanan pre-rilis: **`laravel-security-audit`** — P1 avatar privat + tenant scope diperbaiki 2026-07-23
- [ ] Dokumentasi admin: cara aktifkan modul Ludensa per sekolah

---

## Ringkasan tes

| Area | Filter | Passed |
|------|--------|--------|
| Arena Belajar + Jagat Misi kelas | `GameQuiz\|GameLive\|GameTemplate\|ArenaBelajar\|MissionClassroom` | 49 |
| Ludensa | `Ludensa\|SimsGemini` | 15 |
| Grup Chat | `GrupChat` | 70 |

---

## Perintah kontrol (prd-generator)

| Perintah | Aksi |
|----------|------|
| `lanjut` | Task berikutnya di fitur aktif |
| `lanjut fase [n]` | Lompat ke fase/fitur |
| `ulangi task ini` | Revisi task terakhir |
| `skip` | Lewati task (sebut alasan) |

**Gate approval FL** wajib sebelum task: migration/schema baru, uang/pembayaran, auth/policy, hapus data produksi.
