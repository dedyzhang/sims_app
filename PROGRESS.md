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
- [x] Test: 84 test (`GrupChatAksesTest`, `GrupChatPollTest`, `GrupChatSinkronTest`,
      `GrupChatModulTest`, `GrupChatLampiranTest`, `GrupChatDigestTest`,
      `PengumumanGrupChatTest`)
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
- [x] Fase 6: perombakan aturan keanggotaan & kirim pesan Grup Kelas atas permintaan FL —
      - **Guru pengajar/mapel dikeluarkan dari Grup Kelas.** `GrupChatService::anggotaGrupKelas()`
        kini hanya wali kelas + siswa aktif. `NgajarObserver` tidak lagi memanggil
        `GrupChatService` sama sekali (dulu men-sync Grup Kelas tiap Ngajar
        dibuat/dihapus) — penugasan mengajar tak lagi berpengaruh ke grup chat.
      - **Grup Kelas SELALU mode pengumuman** (hanya wali kelas boleh kirim pesan baru,
        siswa hanya boleh membalas) — di-enforce di `provisionKelas()` (grup baru)
        dan `syncKelas()` (self-healing utk grup lama tiap kali sync jalan, termasu
        nightly `grupchat:sinkron`). Grup Paguyuban TETAP mode diskusi bebas (tidak
        diubah — walikelas & orang tua tetap bebas kirim pesan; keputusan FL).
      - DB dev lokal direkonsiliasi via `php artisan grupchat:sinkron` (26 grup,
        471 anggota — belum ada data grup chat produksi sebelumnya, jadi tidak perlu
        migrasi/backfill terpisah).
      - Test lama yang berasumsi guru pengajar jadi anggota / siswa bisa kirim pesan
        bebas di Grup Kelas ditulis ulang (`GrupChatAksesTest`,
        `GrupChatSinkronTest`, `GrupChatLampiranTest`, `GrupChatDigestTest`) + 2 test
        baru (guru tidak pernah masuk grup kelas; mode grup kelas vs paguyuban
        self-healing). Total sekarang 72 test grup chat.
      - `docs/PANDUAN_PENGGUNAAN_SIMS_APP.md` bagian 15 diperbarui (keanggotaan +
        siapa boleh kirim pesan baru vs hanya balas).
- [x] Code review Fase 6 (`/compound-engineering:ce-code-review`, 9 reviewer) — semua temuan
      diterapkan langsung, tidak ada yang menunggu:
      - Update mode `syncKelas()` dipindah ke DALAM `DB::transaction()` yang sama dgn
        rekonsiliasi keanggotaan (dulu dua write terpisah, non-atomik).
      - `grupchat:sinkron` kini isolasi-per-kelas (try/catch + log) — satu kelas gagal
        tidak lagi menggagalkan sisa batch semalam.
      - **`GrupChat::PERAN_STAF` tidak lagi memuat `'guru'`** (sabuk & bretel): baris
        keanggotaan lama peran `guru` yang belum sempat direkonsiliasi kini otomatis
        diperlakukan sebagai NON-staf (tidak bisa kirim pesan baru di mode pengumuman),
        bukan diam-diam tetap punya hak staf sampai `syncKelas()` berikutnya jalan.
      - **`GrupChatMessenger::kirim()` & `hapus()` kini mengecek otorisasi sendiri**
        (`Gate::forUser(...)->authorize(...)`), bukan cuma dipercaya sudah dicek
        `GrupChatController` — primitive-nya sendiri jadi aman dipanggil pemanggil
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
       - Sisa non-blocking (didokumentasikan, sengaja tidak diterapkan — taste call):
         `ModulAktif::aktif()` mulai menumpuk special-case per modul (`arena_belajar`,
         `ludensa`) — pertimbangkan ekstrak jadi peta default kalau ada modul ketiga
         yang butuh override serupa.

- [x] Private Chat wali kelas — migration percakapan/pesan, policy relasi kelas,
      akses dari nama/profil anggota Grup Chat, polling, notifikasi penerima, dan
      perlindungan IDOR (`PrivateChatTest`).

### Sisa

- [x] Commit & push ke `origin/main` (`791ca51`) — PR #51 dibuka ke `dedyzhang/sims_app`:
      https://github.com/dedyzhang/sims_app/pull/51
- [ ] Merge PR #51 & deploy — **tunggu review/merge dari dedyzhang, bukan tugas sesi ini**

---

## Integrasi Ludensa — DIJEDA (uncommitted)

**2026-07-31 — dijeda atas permintaan FL** sampai dilanjutkan manual: paket
`ludensa/ludensa` (composer path repo ke `../Ludensa GAMIFIKASI/packages/ludensa`)
belum terpasang di `vendor/` pada checkout ini, dan `config/ludensa.php` belum
lengkap (`jenjang_label`, `permainan`, dll. belum ada). Yang sudah diubah supaya
modul ini tidak "terpanggil" tanpa sengaja:

- `ModulAktif::aktif('ludensa')` sekarang default **NONAKTIF** (dulu default
  aktif seperti modul lain) — sekolah/dev yang sudah pasang paketnya tetap bisa
  menyalakan manual lewat Setting. Menu sidebar sendiri sebenarnya sudah aman
  (sudah ada guard `Route::has('ludensa.beranda')` sejak awal), perubahan ini
  cuma mempertegas defaultnya.
- 3 file test (`LudensaIntegrationTest`, `LudensaJenjangAnakTest`,
  `SimsGeminiAiJsonGeneratorTest` — 22 test) di-skip otomatis lewat
  `markTestSkipped()` berbasis `class_exists()`/`interface_exists()` ke kelas
  paket `Ludensa\*` — begitu `vendor/ludensa` terpasang, skip ini otomatis
  berhenti aktif tanpa perlu diedit manual.
- `ModulAktifTest::test_default_semua_modul_aktif` dikecualikan utk 'ludensa' +
  test baru `test_ludensa_default_nonaktif`.

**Untuk melanjutkan nanti:** pasang paket (`composer install` dgn path repo di
atas tersedia), lengkapi `config/ludensa.php`, lalu boleh langsung hapus semua
blok skip di atas — semuanya sudah ditandai jelas di tiap file.

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
| Grup Chat + Pengumuman | `GrupChat\|Pengumuman` | 99 |
| Private Chat | `PrivateChat` | 5 |

---

## Perintah kontrol (prd-generator)

| Perintah | Aksi |
|----------|------|
| `lanjut` | Task berikutnya di fitur aktif |
| `lanjut fase [n]` | Lompat ke fase/fitur |
| `ulangi task ini` | Revisi task terakhir |
| `skip` | Lewati task (sebut alasan) |

**Gate approval FL** wajib sebelum task: migration/schema baru, uang/pembayaran, auth/policy, hapus data produksi.
