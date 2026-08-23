# Brainstorm: Dashboard & Sidebar Declutter — SIMS

**Date:** 2026-08-08
**Status:** Requirements ready for implementation (Fase 1–2)
**Scope:** UX declutter beranda admin/kepala + sidebar navigasi — tanpa rewrite modul

---

## Summary

Dashboard SIMS saat ini memuat **16 widget statistik/grafik** plus **7 tautan cepat** sekaligus, sementara sidebar menampilkan puluhan entri menu tanpa hierarki visual yang jelas. Pengguna baru kewalahan; pengguna lama sudah menyesuaikan tata letak manual. Solusi: **kurangi noise default**, pertahankan kustomisasi, dan selaraskan dengan pola SIS/LMS internasional — ringkasan operasional di atas, aksi harian terbatas, detail mendalam satu klik lagi.

---

## Diagnosis (kondisi saat ini)

### Beranda admin / kepala

| Area | Masalah |
|------|---------|
| **Widget grid** | 4 ringkasan + 4 presensi + 4 sarpras + 3 grafik = 15–16 kartu sebelum scroll |
| **Tautan Cepat** | 7 ikon sejajar (admin) / 6 (kepala); tidak ada prioritas visual |
| **Urutan default** | Sarpras & grafik muncul sebelum atau bersamaan dengan aksi harian |
| **Kepala sekolah** | Blok quicklinks disembunyikan dari layout meski shortcut didefinisikan |
| **Kustomisasi** | Drag-hide sudah ada, tetapi default terlalu ramai sehingga kebanyakan user tidak pernah merapikan |

### Sidebar

| Area | Masalah |
|------|---------|
| **Kepadatan** | Semua modul aktif ditampilkan datar; scroll panjang di layar kecil |
| **Duplikasi mental** | Beberapa aksi ada di dashboard *dan* sidebar tanpa perbedaan peran |
| **Tanpa kelompok** | Tidak ada collapse per domain (Akademik, Kesiswaan, Sarpras, dll.) |
| **Peran campur** | Admin vs kepala vs guru melihat pola serupa dengan jumlah item berbeda saja |

### Dampak pengguna

- **Time-to-action** lambat: admin harus scan 20+ elemen sebelum menemukan "Absensi" atau "Tambah Siswa"
- **Cognitive load** tinggi di pagi hari operasional (jam masuk, presensi)
- **Preferensi tersimpan** sudah investasi user — reset default akan menimbulkan keluhan

---

## Prior art (referensi 2025–2026)

| Platform | Pola beranda | Pola navigasi | Relevansi SIMS |
|----------|--------------|---------------|----------------|
| **PowerSchool** | KPI ringkas + feed aktivitas; aksi via "Quick Actions" terbatas | Menu domain dengan sub-nav kontekstual | Model KPI + quick action untuk admin sekolah |
| **Infinite Campus** | Dashboard per peran; widget opsional | Sidebar collapsible per modul | Role-based default layout |
| **Canvas LMS** | Course cards + to-do; minimal di home | Global nav 4–5 item + konteks dalam course | "To-do first" — tugas hari ini di atas |
| **Google Classroom** | Stream + class chips; FAB untuk aksi utama | Hamburger / rail tipis; kelas sebagai unit | Google Education visual language (sudah dipakai SIMS) |
| **Clever** | Portal app launcher — **maks ~6 tile** per sekolah | Katalog aplikasi, bukan 40 link | Bukti bahwa launcher >12 item menurun adoption |
| **Dapodik / e-Gov ID** | Form-heavy, bukan dashboard; menu instansi bertingkat | Sidebar instansi dengan grup | Pengguna SIMS sudah terbiasa menu bertingkat pemerintah |

**Pola bersama:** (1) maks 4–6 aksi primer di permukaan, (2) ringkasan numerik di atas lipatan, (3) grafik/analitik opsional atau tab sekunder, (4) navigasi berkelompok dengan collapse, (5) tidak menghapus fitur — hanya mengubah default visibility.

---

## Requirements (stable IDs)

### R1 — Beranda (dashboard)

- **R1.1** Ganti label "Tautan Cepat" → **"Aksi Cepat"** (Bahasa Indonesia).
- **R1.2** Tampilkan **maksimal 4 aksi primer per peran** di grid; sisanya di **"Lainnya"** (popover/drawer Alpine).
- **R1.3** Aksi primer admin: Tambah Siswa, Absensi, Set Kelas, Data Siswa.
- **R1.4** Aksi primer kepala: Data Siswa, Absensi, Presensi Guru, Laporan Sarpras.
- **R1.5** Urutan blok default: **ringkasan → presensi → aksi cepat → grafik**; sarpras setelah grafik.
- **R1.6** Widget sekunder (`sarpras_*`, `recent_*`, `sebaran`) **tersembunyi by default hanya untuk user baru** (record preferensi baru); jangan reset preferensi user existing.
- **R1.7** Pertahankan mode edit layout (drag, hide, collapse) dan penyimpanan ke `user_preferences`.
- **R1.8** Pertahankan tema Google Education (palet B/R/Y/G, kartu, Lucide icons).

### R2 — Sidebar (navigasi)

- **R2.1** Kelompokkan menu ke domain collapsible (Akademik, Kesiswaan, Kepegawaian, Sarpras, Pengaturan, dll.).
- **R2.2** Sembunyikan entri yang tidak relevan untuk peran (policy/permission existing).
- **R2.3** Pin 4–6 item global (Beranda, Absensi, Data Siswa, dll.) di atas grup.
- **R2.4** Indikator modul aktif / badge notifikasi tetap berfungsi.
- **R2.5** Responsif: rail tipis + drawer di mobile; tidak menambah scroll horizontal.

### R3 — Preferensi & non-breaking

- **R3.1** `UserPreference::defaults()` menyertakan `dashboard_hidden` untuk blok sekunder (user baru saja).
- **R3.2** `dashboard_layout` tersimpan user tidak di-overwrite saat deploy.
- **R3.3** Tombol "Reset tata letak" di dashboard mengembalikan ke default **baru** (semua tampil), bukan migrasi paksa hidden.
- **R3.4** Helper `defaultDashboardHiddenBlocks()` terpusat di model, bukan hardcode di blade.
- **R3.5** Tidak ada migration schema baru kecuali benar-benar diperlukan.

---

## Out of scope

- Redesign visual penuh / rebrand di luar tema Google Education existing
- Menghapus widget atau modul dari codebase
- Sidebar mobile app / PWA native
- Personalisasi aksi cepat per-user (drag pin) — fase berikutnya
- Dashboard peran guru/siswa/orangtua (sudah terpisah; hanya sentuh jika regresi)
- Analytics usage tracking untuk A/B test declutter
- Perubahan permission, policy, atau routing backend

---

## Implementasi terbagi

| Fase | Task | Owner |
|------|------|-------|
| **1** | Dokumen requirements (ini) | Agent brainstorm |
| **2** | Aksi Cepat + default hidden + urutan blok | Agent dashboard |
| **3** | Sidebar kelompok & collapse | Agent sidebar (terpisah) |

---

## Success criteria

- **S1:** Beranda admin baru menampilkan ≤12 kartu visible tanpa edit manual (4 ringkasan + 4 presensi + 1 aksi cepat).
- **S2:** Aksi harian utama dapat dijangkau dalam 1 klik dari Aksi Cepat.
- **S3:** User dengan `dashboard_layout` / `dashboard_hidden` tersimpan tidak berubah setelah deploy.
- **S4:** Sidebar dapat di-scroll <50% tinggi layar laptop untuk admin tipikal (setelah Fase 3).

---

## Evidence (repo)

- `resources/views/dashboard.blade.php` — layout blok, label, filter peran
- `resources/views/dashboard/blocks/quicklinks.blade.php` — tautan cepat 7 item
- `app/Models/UserPreference.php` — `DASHBOARD_BLOCKS`, `defaults()`
- `app/Http/Controllers/DashboardController.php` — `saveLayout()`
- `resources/views/layouts/app.blade.php` — sidebar menu (Fase 3)
