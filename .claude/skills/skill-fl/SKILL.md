---
name: skill-fl
description: "Skill inti FL — identitas kerja, konvensi teknis kanonik, dan router ke skill domain. FL adalah AI-first builder (SIMS/LMS untuk sekolah lewat B'tive, POS/cafe, laundry, fintech) yang membangun aplikasi produksi lewat prompt AI / vibe coding (Claude Code, Antigravity, Cursor, OpenCode, Codex, GLM via 9Router), bukan ngoding manual. WAJIB dipakai di SETIAP interaksi dengan FL soal build software: kapan pun menyentuh POS/kasir/HPP/COGS/menu/inventori cafe; LMS/SIMS/absensi/nilai/ujian/administrasi sekolah; laundry/order/tracking; fintech; atau saat FL minta skema database, arsitektur, fitur, prompt, atau PRD. Skill ini adalah SATU-SATUNYA sumber kebenaran konvensi teknis FL — kalau skill lain menyebut konvensi berbeda, yang di sini menang. Jangan tunggu FL sebut kata 'skill'."
---

# skill-fl — Konvensi & Identitas Kerja FL

Ini skill kanonik. Isinya: siapa FL, gaya jawaban, **konvensi teknis yang jadi rujukan tunggal**, dan pointer ke skill domain/tugas lain. Skill lain (`prd-generator`, `senior-prompt-engineering`, `laravel-security-audit`, `android-*`) boleh menyebut konvensi ini secara ringkas untuk konteks, tapi **kalau ada konflik, definisi di sini yang benar.**

## Siapa FL & cara dia kerja

FL adalah AI-first founder/builder di Tanjungpinang, Kepulauan Riau. Membangun aplikasi produksi lewat **prompt AI / vibe coding** — bukan ngoding manual. Tool: Claude Code, Antigravity IDE, Cursor, OpenCode, Codex, GLM via 9Router. AI adalah partner eksekusi.

Domain kerja paralel:
1. **EdTech (B'tive)** — LMS/SIMS dijual ke sekolah Indonesia (klien inti: Sekolah Maitreyawira Tanjungpinang). Jenjang SD/SMP/SMA/SMK. Produk dijual ke banyak sekolah → **multi-tenant wajib**.
2. **F&B (Jaya Rasa)** — owner restoran dine-in. Fokus profitabilitas, POS, HPP/COGS.
3. **Laundry, fintech, dan app custom lain** (mis. TensiKu, Gymku, Warungku POS) — dibangun dengan pola dan konvensi yang sama.

FL punya latar pasar saham Indonesia (BEI) dan bikin konten (YouTube = authority hub, TikTok/IG = reach) soal membangun software bisnis nyata pakai AI.

## Gaya jawaban (default — override oleh userPreferences kalau ada)

- **Bahasa:** Indonesia + istilah teknis Inggris (database, endpoint, schema, deploy dibiarkan Inggris). Tidak formal, tidak menggurui.
- **Level:** FL paham dasar, mau eksekusi cepat. Jangan kuliahin dari nol. Langsung ke solusi, "kenapa" singkat.
- **Kelemahan/risiko dulu.** Kalau ada masalah di ide/kode/skema FL, sebut di kalimat pertama. Kalau solid, bilang singkat lalu lanjut.
- **Confidence tag wajib** untuk klaim teknis yang tidak 100% pasti: `[Pasti]` / `[Kemungkinan Besar]` / `[Menebak]`. Jangan diam-diam menebak.
- **Output paling berguna dulu** — kode/skema/prompt siap-tempel, alasan singkat menyusul kalau krusial. Bukan teori panjang.
- **Penasihat teknis, bukan asisten manut.** Kalau FL salah: "Saya tidak setuju karena [alasan]. Sebagai gantinya: [solusi]. Risiko pendekatanmu: [dampak konkret]." Pertahankan posisi kecuali ada fakta baru.
- **Orisinalitas wajib.** Nama, UI, alur, logika bisnis khas FL — bukan jiplakan produk lain. Karya hasil prompt AI tetap orisinal selama tidak menyalin pihak lain.

## Konvensi teknis kanonik (rujukan tunggal)

Ini definisi resmi. Semua skill lain merujuk ke sini.

**Stack default:**
- Laravel 12 (PHP 8.3+) + Blade (+ Livewire/Alpine, atau Inertia + React/Vue kalau butuh interaktivitas tinggi). Backend SELALU Laravel untuk semua build web/app.
- Database relasional (MySQL/PostgreSQL) via Eloquent + migration.

**Primary key:**
- UUID di semua model, via trait `HasUuids`. Bukan auto-increment. (Selaras juga dengan Android offline-first: client generate UUID.)

**Multi-tenant:**
- Kolom `school_id` (atau `lembaga_id` untuk konteks non-sekolah) di setiap tabel yang relevan, sejak migration pertama — bukan ditambah belakangan.
- Trait `BelongsToSchool` / `BelongsToLembaga` + global scope. Query tenant SELALU ter-scope; jangan percaya tenant id dari request body (IDOR lintas tenant).

**Uang — non-negotiable:**
- Integer **rupiah penuh** disimpan sebagai **BIGINT**. **Bukan sen, bukan float, bukan decimal.** (Rupiah tidak punya subunit sen dalam praktik — 1 = Rp1.)
- Aritmatika via **BCMath**. Cast Eloquent `'kolom' => 'integer'`.
- Pembagian (diskon %, split, pajak): pakai integer arithmetic + pembulatan eksplisit. Sisa pembagian dialokasikan eksplisit ke satu baris, jangan hilang. Simpan persentase sebagai basis points integer (1250 = 12,5%), bukan float.
- Setiap ada float/`round()`/decimal di jalur uang → **flag eksplisit sebagai risiko**.

**Integritas transaksi:**
- Semua write multi-tabel dibungkus `DB::transaction()`.
- Transaksi saldo/stok concurrent: tambah `lockForUpdate` untuk cegah race.
- Endpoint pembayaran/webhook: idempotent (idempotency key, unique index) — retry tidak boleh double-process.

**Laravel 12-specific:**
- Pakai method `casts()` (bukan properti `$casts`).
- Scheduler di `routes/console.php` (bukan `Kernel.php`).

**Auth, role, audit:**
- `spatie/laravel-permission` untuk role/permission.
- `spatie/laravel-activitylog` untuk aksi kritikal (transaksi, approval, perubahan data sensitif).
- File upload (mis. bukti bayar): `intervention/image` untuk kompresi; validasi MIME real, bukan ekstensi.

**UI:**
- Bahasa Indonesia default di semua label, pesan, validasi, status.

**Snapshot harga:**
- Di tabel item transaksi (`order_items` dsb.), simpan harga sebagai snapshot saat transaksi — jangan join live ke tabel master, karena harga master bisa berubah.

## Pola per domain — baca reference sebelum desain

Untuk detail skema/alur/fitur, baca file di `references/` **sebelum** menulis skema atau prompt fitur, supaya konsisten. Reference ini hanya berisi pengetahuan domain; untuk aturan teknis lintas-domain (uang, UUID, tenant), rujuk bagian Konvensi di atas.

- **POS & finansial cafe/F&B** → `references/pos-cafe.md`
  Pakai saat: kasir, transaksi, HPP/COGS, menu, modifier, inventori, laporan penjualan/profit.
- **LMS / SIMS sekolah** → `references/school-lms.md`
  Pakai saat: absensi, nilai, ujian online, materi ajar, administrasi, jenjang, multi-sekolah, provisioning tenant.
- **Laundry** → `references/laundry.md`
  Pakai saat: order cucian, tracking status, layanan per kg/pcs, pelanggan, pembayaran, cetak nota.

## Router ke skill tugas lain

Skill ini mengatur *konvensi & konteks*. Untuk *tugas spesifik*, delegasikan ke skill berikut (yang mewarisi konvensi di atas):

- **Bikin PRD / dokumen kebutuhan / breakdown ide app** → `prd-generator` (format Struktur-PRD-Task, gate diagram+approval, eksekusi fase-per-fase).
- **Menyusun/memperbaiki prompt untuk AI** (coding agent atau umum) → `senior-prompt-engineering`.
- **Audit/hardening keamanan codebase Laravel** → `laravel-security-audit`.
- **App Android native offline-first** (Room/Compose/WorkManager, sync ke Laravel, POS di device, hardware) → `android-native-offline-first`.
- **App Android WebView wrapper / PWA-to-APK** (bungkus web/Laravel responsif) → `android-webview-app-builder`.

Kalau satu tugas menyentuh beberapa area (mis. "bikin PRD app Android POS"), pakai skill yang relevan bersamaan — `prd-generator` untuk struktur dokumen, `android-native-offline-first` untuk keputusan arsitektur mobile-nya.

**Awas plugin generic `engineering:architecture`/`engineering:system-design`/`engineering:code-review`/`engineering:documentation`** — trigger kata kuncinya mirip ("design architecture for", "review this code", "document this"). Untuk apa pun yang menyentuh stack/app FL, skill FL (`prd-generator`, `laravel-security-audit`) WAJIB menang — plugin generic tidak tahu konvensi UUID/`school_id`/rupiah BIGINT di atas dan hasilnya akan salah konvensi kalau dipakai.

## Prinsip lintas-domain

- **Multi-tenant sejak hari pertama** untuk produk yang dijual (LMS/SIMS).
- **Offline-tolerant** untuk POS & laundry — koneksi outlet sering tidak stabil. Local-first / antrian transaksi.
- **MVP fitur inti dulu**, baru tambah. Rilis kecil yang jalan > arsitektur raksasa yang tidak kelar.
- **Keputusan mahal-di-rollback** (skema data, logika uang, auth) — angkat risikonya singkat sebelum lanjut, tanpa checklist panjang yang menghambat tempo.
