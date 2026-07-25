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

---

## Perintah kontrol (prd-generator)

| Perintah | Aksi |
|----------|------|
| `lanjut` | Task berikutnya di fitur aktif |
| `lanjut fase [n]` | Lompat ke fase/fitur |
| `ulangi task ini` | Revisi task terakhir |
| `skip` | Lewati task (sebut alasan) |

**Gate approval FL** wajib sebelum task: migration/schema baru, uang/pembayaran, auth/policy, hapus data produksi.
