---
name: prd-generator
description: "Skill untuk generate PRD (Product Requirements Document) siap-pakai untuk AI coding (Claude Code/Codex/Antigravity/Cursor/OpenCode), memakai format baku bernama Struktur-PRD-Task: 1 file PRD.md utama + folder features/ berisi 1 file per fitur dengan Spesifikasi → Sub-fitur → Task breakdown. WAJIB pakai skill ini setiap kali FL minta 'buatkan PRD', 'bikin PRD untuk [nama app]', 'requirement doc', 'dokumen kebutuhan', 'pakai Struktur-PRD-Task', atau minta breakdown ide aplikasi jadi rencana development. Semua PRD yang dihasilkan otomatis pakai stack default FL (Laravel 12 + Blade, UUID, multi-tenant school_id/lembaga_id kalau relevan, integer rupiah + BCMath, spatie/permission, spatie/activitylog) — jangan tanya stack dari nol, langsung generate. Jangan tunggu FL sebut kata 'skill' — begitu dia kasih ide aplikasi/fitur dan minta didokumentasikan, pakai skill ini. BATAS vs senior-prompt-engineering: skill INI menang kalau output = dokumen perencanaan (PRD, breakdown fitur, spec multi-file), termasuk kalau PRD itu berisi prompt eksekusi fase-per-fase. Kalau output cuma SATU blok prompt untuk ditempel ke agent (tanpa struktur PRD/features), pakai senior-prompt-engineering. BATAS vs plugin generic engineering:architecture/engineering:system-design/engineering:documentation: skill INI WAJIB menang setiap kali requestnya soal app/fitur FL (LMS/SIMS/POS/laundry/fintech) atau menyebut 'requirement doc'/'dokumen kebutuhan' — plugin generic itu tidak tahu stack Laravel/UUID/school_id/rupiah BIGINT milik FL dan akan menghasilkan dokumen yang salah konvensi. Plugin generic hanya relevan untuk pertanyaan arsitektur non-FL yang benar-benar generik/di luar stack FL."
---

# PRD Generator — Struktur-PRD-Task

> **Sumber konvensi: `skill-fl`.** Konvensi teknis (uang integer rupiah BIGINT+BCMath, UUID `HasUuids`, `school_id`/`lembaga_id` scope, `spatie/*`, `casts()`, `DB::transaction()`) di-restate di §7 Tech Stack supaya PRD self-contained saat dibaca agent lain (Codex/Cursor/OpenCode). Kalau ada beda antara file ini dan `skill-fl`, `skill-fl` yang benar.

**Struktur-PRD-Task** = nama baku pola PRD ini: `PRD.md` (Struktur — overview, arsitektur, roadmap fase) + `features/*.md` (Task — breakdown Spesifikasi → Sub-fitur → Task per fitur). Begitu FL bilang "pakai Struktur-PRD-Task" atau minta PRD tanpa embel-embel lain, ini formatnya — tidak ada versi lain, tidak diimprovisasi.

Skill ini nge-generate PRD dengan struktur **identik** setiap kali: 1 file `PRD.md` (overview + arsitektur) + folder `features/` (1 file markdown per fitur, breakdown sampai level task). Formatnya dikunci berdasarkan pola yang sudah divalidasi, disesuaikan ke stack default FL. Jangan improvisasi struktur — ikuti template di bawah persis, isinya yang berubah sesuai proyek.

## Kapan generate apa

- Ide aplikasi baru dari nol → generate PRD lengkap (PRD.md + semua feature file).
- FL minta tambah 1 fitur ke app yang sudah ada → cukup generate 1 file `features/NN-nama-fitur.md` baru, jangan tulis ulang PRD.md kecuali dia minta update roadmap.
- FL kasih ide masih kasar (cuma 1-2 kalimat) → jangan tanya balik dulu, isi Overview/Requirements pakai asumsi paling masuk akal berdasarkan domain (F&B/EdTech/laundry/fintech), sebutkan asumsi itu singkat di luar PRD sebagai catatan, lalu tetap generate lengkap.

## Output wajib berupa file

Generate sebagai file asli (bukan cuma teks di chat): `PRD.md` di root, `features/01-nama-fitur.md`, `features/02-nama-fitur.md`, dst. Urutan file = urutan fase di roadmap. Nama file: nomor 2 digit + slug lowercase-dengan-strip dari nama fitur (contoh: `03-riwayat-booking.md`).

---

## Mode eksekusi: jalankan task/fase setelah PRD approved

Skill ini tidak berhenti di dokumen. Begitu FL approve dan `PRD.md`/`features/*.md` sudah final, lanjut ke eksekusi coding-nya sendiri — task demi task, fase demi fase — bukan cuma nunggu FL copy-paste manual ke tool lain.

**Urutan default:**
1. Mulai dari fase 1, task 1 (task pertama di `features/01-*.md`). Tampilkan task yang mau dikerjakan (judul + isi singkat) sebelum eksekusi.
2. Kerjakan task itu (tulis/edit kode sesuai konvensi FL: UUID, `school_id`/`lembaga_id` scope, integer rupiah+BCMath, `DB::transaction()`, dst).
3. Tandai task selesai di file fitur terkait (checklist tercentang atau suffix `[DONE]`) — supaya progress kebaca walau sesi/tool ganti.
4. Lanjut ke task berikutnya **tanpa nunggu approval** kalau task itu UI murni dengan data tiruan (biasanya task 1-5 di tiap fitur). **Stop dan tunggu approval FL** sebelum lanjut kalau task berikutnya menyentuh: migration/schema, uang/pembayaran, auth/policy, atau hapus/replace data — ini titik yang mahal kalau salah.
5. Selesai satu fitur penuh (semua task di 1 file `features/NN-*.md`) → selalu stop, laporkan ringkas apa yang jadi, minta lanjut ke fitur berikutnya atau revisi.

**Command yang FL bisa pakai untuk kontrol:**
- `lanjut` → lanjut ke task berikutnya di fitur yang sama
- `lanjut fase [n]` / `lanjut fitur [nama]` → lompat ke fase/fitur tertentu (skip fitur sebelumnya, tandai alasan skip)
- `ulangi task ini` → revisi task barusan, jangan lanjut dulu
- `skip` → lewati task ini (sebutkan alasan), lanjut ke berikutnya

**Kalau ada blocker** (migration conflict, package belum terpasang, konvensi FL tidak jelas untuk kasus tertentu, dll) — stop, laporkan blocker-nya, jangan asumsikan dan lanjut otomatis.

**Progress tracking:** buat/`update` `PROGRESS.md` di root project — checklist semua fase → fitur → task, sinkron dengan status di `features/*.md`. Ini supaya kalau FL ganti tool (Claude Code → Codex → Cursor → OpenCode) atau conversation di-reset, agent berikutnya tetap tahu sudah sampai mana tanpa FL jelasin ulang dari nol.

Mode ini berlaku sama persis di semua code agent (Claude Code, Codex, Antigravity, Cursor, OpenCode) — bukan fitur khusus satu tool, karena cuma soal urutan kerja & command convention yang ditulis di `SKILL.md`/`AGENTS.md`, bukan API khusus.

## Alur wajib: diagram + approval task sebelum file final

Jangan langsung tulis `PRD.md` + `features/*.md` final. Urutannya:

1. **Susun draft konten** (Overview, Core Features per fase, User Flow, Database Schema) di kepala dulu — belum jadi file.
2. **Tampilkan diagram** (pakai Visualizer, bukan cuma teks) untuk salah satu dari: alur utama antar role (flowchart) atau arsitektur/ER schema — pilih yang paling membantu FL cepat lihat kesalahan struktur. Untuk app dengan banyak role/state, prioritaskan flowchart alur; untuk app dengan schema kompleks (>4 tabel relasi), prioritaskan ER diagram.
3. **Tampilkan ringkasan task** — daftar fase + fitur + jumlah task per fitur (bukan detail task satu-satu, cukup judul task), dalam bentuk list singkat supaya FL bisa scan cepat.
4. **Minta approval** — pakai `ask_user_input_v0` dengan pilihan singkat: "Setuju, generate file" / "Revisi dulu". Jangan lanjut generate file final sebelum dapat jawaban.
5. **Kalau revisi**: tanya bagian mana yang direvisi (fase, sub-fitur, atau schema), update draft, ulangi dari langkah 2 — jangan langsung nulis file dengan asumsi revisi sudah benar.
6. **Kalau setuju**: baru generate `PRD.md` + semua `features/*.md` sesuai template di bawah, lalu present file-nya.

Pengecualian: kalau FL cuma minta tambah **1 fitur** ke project yang PRD.md-nya sudah ada (bukan project baru), cukup tampilkan ringkasan task fitur itu saja + minta approval sebelum generate 1 file `features/NN-nama-fitur.md` — nggak perlu diagram ulang untuk seluruh app kecuali fitur itu mengubah alur/schema besar.

## Template `PRD.md`

```markdown
# PRD — Product Requirements Document

## 1. Overview
[2-4 kalimat: masalah yang mau diselesaikan (proses manual/pain point saat ini), siapa penggunanya, dan value utama aplikasi ini. Bahasa Indonesia, natural, bukan bullet.]

## 2. Requirements
* **[Nama requirement non-fungsional]:** [1 kalimat penjelasan kenapa ini penting untuk konteks bisnis FL — misal real-time, multi-role, mobile-friendly, akurasi keuangan, dsb.]
* (ulangi 4-6 poin — selalu sertakan minimal satu poin soal akurasi data uang kalau app ada transaksi, dan satu poin soal role/akses kalau app multi-role)

## 3. Core Features
Sesuai roadmap, fitur dikembangkan bertahap:

### Fase 1: [Nama Fitur] [High/Medium/Low]
[1 kalimat ringkasan fitur]
* **[Sub-fitur A]:** [1 kalimat]
* **[Sub-fitur B]:** [1 kalimat]
* **[Sub-fitur C]:** [1 kalimat]

(ulangi untuk tiap fase — biasanya 4-7 fase: mulai dari fitur inti/read-only dulu, lalu fitur transaksional, lalu riwayat/laporan, lalu auth & role, ditutup fitur dashboard admin/owner)

## 4. User Flow
**Alur [Role utama, mis. Pelanggan/Siswa/Kasir]:**
1. [langkah]
2. [langkah]
...

**Alur [Role kedua, mis. Admin/Guru/Owner]:**
1. [langkah]
...

(tambah alur ke-3 kalau ada role ke-3, mis. Orang Tua/Wali di app sekolah)

## 5. Architecture
Aplikasi ini [monolith Laravel Blade / Laravel + Livewire / Laravel API + Inertia — pilih sesuai kompleksitas UI]. Server-rendered, controller menangani request, model Eloquent bicara ke database MySQL/PostgreSQL. [Kalau multi-tenant: tambahkan 1 kalimat soal school_id/lembaga_id scoping di level query lewat global scope.]

```mermaid
sequenceDiagram
    participant [Role]
    participant Browser
    participant Laravel Controller
    participant Database

    [Role]->>Browser: [aksi awal]
    Browser->>Laravel Controller: [request]
    Laravel Controller->>Database: [query, sebutkan scope school_id/lembaga_id kalau relevan]
    Database-->>Laravel Controller: [hasil]
    Laravel Controller-->>Browser: [render/redirect]

    [ulangi block untuk alur transaksi utama, sertakan DB::transaction() kalau multi-tabel]
```

## 6. Database Schema
Tabel utama beserta kolom:

* **[nama_tabel]** ([fungsi tabel])
    * `id` (UUID): Primary Key — pakai `HasUuids` trait.
    * `school_id` / `lembaga_id` (UUID, FK): [WAJIB kalau app multi-tenant/dijual ke banyak sekolah/lembaga — scope pakai global scope, jangan taruh logic filter manual di tiap controller]
    * [kolom lain sesuai domain]
    * `[nominal_uang]` (BIGINT): [Selalu sebutkan eksplisit: disimpan dalam rupiah penuh sebagai integer, hitung pakai BCMath, JANGAN pakai float/decimal untuk uang]
    * `status` (String/Enum): [state relevan]
    * `created_at`, `updated_at` (Timestamp)

(ulangi per tabel)

```mermaid
erDiagram
    [TABEL_A] ||--o{ [TABEL_B] : [relasi]

    [TABEL_A] {
        uuid id PK
        uuid school_id FK
        ...
    }

    [TABEL_B] {
        uuid id PK
        uuid [tabel_a]_id FK
        bigint [nominal_uang]
        string status
    }
```

## 7. Tech Stack
Stack default FL (jangan ganti kecuali FL minta lain):
* **Backend & Framework:** Laravel 12 (PHP 8.3+).
* **Frontend:** Blade + Livewire/Alpine (atau Inertia + React/Vue kalau app butuh interaktivitas tinggi — sebutkan mana yang dipilih dan alasannya 1 kalimat).
* **Database:** MySQL/PostgreSQL via Eloquent ORM + migration.
* **Primary Key:** UUID (`HasUuids` trait di semua model).
* **Multi-tenant (kalau relevan):** `school_id`/`lembaga_id` + trait `BelongsToSchool`/`BelongsToLembaga` + global scope.
* **Uang:** Integer rupiah (BIGINT), kalkulasi via BCMath — tidak pernah float.
* **Auth & Role:** Laravel Breeze/Fortify + `spatie/laravel-permission` untuk role/permission.
* **Audit trail:** `spatie/laravel-activitylog` untuk aksi kritikal (transaksi, approval, perubahan data sensitif).
* **File upload (kalau ada, mis. bukti bayar):** `intervention/image` untuk kompresi.
* **UI Language:** Bahasa Indonesia (default, semua label/pesan/validasi).
```

---

## Template `features/NN-nama-fitur.md`

Setiap file fitur ikuti pola ini persis — level detail dan urutan section sama seperti PRD.md fase-nya:

```markdown
# [Nama Fitur]

[1 kalimat ringkasan — sama seperti di Core Features PRD.md]

## Spesifikasi

### Tujuan
[2-3 kalimat: kenapa fitur ini penting, masalah apa yang diselesaikan di level fitur ini]

### Selesai bila
- [Kriteria selesai/acceptance criteria, konkret dan bisa dicek — 3-5 poin]
- [...]

## Sub-fitur: [Nama Sub-fitur A]

[1 kalimat ringkasan sub-fitur]

### Tujuan
[1-2 kalimat]

### Selesai bila
- [kriteria konkret]
- [kriteria konkret]

## Sub-fitur: [Nama Sub-fitur B]

(ulangi pola yang sama — biasanya 3-4 sub-fitur per fitur)

## Task

[Urutan task WAJIB ikuti pola vertical-slice ini — jangan diacak:]

### 1. Buat halaman/view [nama] dengan data tiruan
Bangun Blade view/komponen dengan data hardcode/dummy array dulu, tanpa query database, supaya UI bisa direview sebelum backend jadi.

### 2. [Tambah interaksi UI: form, filter, kalender, dll — masih data tiruan]

### 3. [Halaman/state lanjutan kalau ada alur multi-step — masih data tiruan]

### 4. Integrasikan navigasi antar halaman/state

### 5. Poles tampilan dan responsivitas

### 6. Buat migration & model Eloquent untuk [tabel terkait]
Sertakan `HasUuids`, `school_id`/`lembaga_id` scope kalau multi-tenant, kolom uang sebagai BIGINT kalau ada.

### 7. Buat controller + route untuk [aksi utama]
Ganti data tiruan di view dengan query Eloquent asli. Bungkus write multi-tabel dengan `DB::transaction()`.

### 8. [Endpoint/aksi tambahan sesuai sub-fitur — approve, cancel, upload, dsb.]

### 9. Tambahkan policy/authorization
Pastikan role yang berhak saja yang bisa akses (pakai `spatie/laravel-permission` gate/policy).

### 10. [Kalau ada aksi sensitif: log activity via spatie/laravel-activitylog]

### 11. Buat seeder/factory
Isi data contoh secukupnya untuk testing fitur ini.
```

Catatan urutan task: **selalu mulai dari UI dengan data tiruan** (biar AI coding agent bisa langsung render sesuatu yang terlihat), baru masuk ke migration/model, lalu controller/route yang menggantikan data tiruan dengan query asli, baru authorization dan logging, ditutup seeder. Ini pola vertical-slice yang sudah tervalidasi — jangan mulai dari database schema duluan.

---

## Kompatibilitas lintas code agent (GLM, 9Router, Cursor, OpenCode, Codex, dll)

[Pasti] `PRD.md` + `features/*.md` isinya plain Markdown — tidak ada tag/format proprietary Claude di dalamnya, jadi filenya otomatis portable ke semua tool di bawah tanpa perlu versi berbeda-beda. Yang beda cuma **cara tiap tool menemukan file ini**, bukan isi PRD-nya:

* **GLM (model, bukan tool)** — GLM dipakai lewat CLI/IDE lain (Cursor, OpenCode, Claude Code compat layer, atau lewat 9Router). PRD tidak perlu diubah sama sekali; ini soal model di belakang layar, bukan format context.
* **9Router** — cuma local routing proxy (`localhost:20128/v1`) yang meneruskan request ke provider/model apa pun (GLM, MiniMax, Claude, dst) lewat tool yang sudah kamu pakai (Cursor, Claude Code, OpenCode, Codex). Dia tidak membaca/parse PRD sendiri — transparan. Tidak perlu penyesuaian apa pun di PRD untuk 9Router.
* **OpenCode** — baca `AGENTS.md` di root project sebagai standing instruction (fallback ke `CLAUDE.md` kalau `AGENTS.md` tidak ada). [Kemungkinan Besar] paling efektif: taruh 1 baris pointer di `AGENTS.md` yang eksplisit nyuruh baca `PRD.md` dan `features/` sebelum mulai task, contoh:
  ```
  Baca PRD.md dan seluruh isi folder features/ sebelum mengerjakan task apa pun di project ini. Urutan pengerjaan ikuti nomor fase di PRD.md.
  ```
* **Codex (OpenAI CLI)** — [Kemungkinan Besar] sama seperti OpenCode: baca `AGENTS.md` di root project sebagai standing instruction, bukan `CLAUDE.md`. Pointer `AGENTS.md` yang sama di atas berfungsi untuk Codex tanpa perlu file terpisah — cukup 1 `AGENTS.md` yang dishare OpenCode + Codex. Kalau project sudah punya `CLAUDE.md` dari sesi Claude Code sebelumnya, tetap tambahkan `AGENTS.md` terpisah (jangan andalkan Codex baca `CLAUDE.md`, riskan diabaikan) [Menebak — perilaku fallback Codex ke file lain belum divalidasi, cek dulu kalau ragu].
* **Cursor** — pakai `.cursor/rules/*.mdc` (atau legacy `.cursorrules`) untuk project rules. Tambahkan rule pendek yang sama: instruksikan Cursor baca `PRD.md` + `features/` di awal sesi.
* **Claude Code / Antigravity** — sudah pakai skill ini langsung (`skill-fl`, `laravel-security-audit`, dan skill ini sendiri ada di config skills), tidak butuh pointer tambahan.

**Rekomendasi:** setiap kali generate PRD untuk project baru, sekalian tawarkan 1 file pointer kecil (`AGENTS.md` isi 1-2 baris seperti contoh di atas) supaya PRD ini otomatis kepakai di OpenCode/Cursor/tool manapun tanpa FL harus setup manual tiap kali ganti tool/model.

## Checklist sebelum dianggap selesai

- [ ] Semua nominal uang di schema: BIGINT + catatan BCMath (tidak ada float/decimal untuk uang).
- [ ] Kalau app dijual ke banyak sekolah/lembaga/cabang: `school_id`/`lembaga_id` ada di setiap tabel yang relevan sejak PRD pertama, bukan ditambah belakangan.
- [ ] Nama aplikasi, branding, alur — orisinal, bukan nama generic/jiplakan produk lain.
- [ ] UI berbahasa Indonesia di semua contoh copy (tombol, pesan error, status).
- [ ] Task list tiap fitur ikut pola: UI tiruan → integrasi → migration/model → controller/route asli → authorization → (activity log kalau sensitif) → seeder.
- [ ] Kalau ada tabel dengan write ke >1 tabel dalam satu aksi (mis. booking + update status slot), sebutkan eksplisit "bungkus dengan `DB::transaction()`" di task terkait.
- [ ] Kalau FL kerja di tool selain Claude Code/Antigravity (OpenCode, Codex, Cursor, atau lewat 9Router), tawarkan pointer file (`AGENTS.md` atau `.cursor/rules`) 1-2 baris supaya PRD ini otomatis kebaca di sesi pertama.
- [ ] Diagram + ringkasan task sudah ditampilkan dan FL sudah approve sebelum file `PRD.md`/`features/*.md` final ditulis — bukan generate langsung tanpa konfirmasi.
- [ ] Setelah approve, eksekusi jalan task-per-task/fase-per-fase sesuai Mode Eksekusi — stop di task migration/uang/auth, lanjut otomatis di task UI-tiruan, dan `PROGRESS.md` ke-update tiap task selesai.
