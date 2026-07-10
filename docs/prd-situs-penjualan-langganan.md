# PRD — Situs Penjualan SIMS + Modul Langganan

| | |
|---|---|
| **Produk** | SIMS — Sistem Informasi Manajemen Sekolah |
| **Dokumen** | Product Requirements Document (PRD) + Rencana Fase |
| **Versi** | 1.0 — **FINAL / LOCKED** 🔒 |
| **Tanggal** | 11 Juli 2026 |
| **Status** | Disetujui — siap eksekusi (semua keputusan terkunci, lihat §12) |
| **Pemilik** | Felly (fellyluo) |

> Dokumen ini adalah spesifikasi untuk dua pekerjaan yang saling terkait, di **dua
> lokasi kode berbeda**:
> - **Deliverable A — Situs Penjualan** → **proyek baru terpisah** (folder baru),
>   disajikan di subdomain **`www.`**.
> - **Deliverable B — Modul Langganan (lisensi)** → di dalam **repo `sims_app`**
>   yang ada, disajikan di subdomain **`app.`**.
>
> PRD ini adalah sumber kebenaran. Semua *Open Question* telah diputuskan (§12).

---

## 1. Latar Belakang & Masalah

SIMS sudah menjadi produk matang (100+ tabel, ~520 route, 11 peran pengguna) yang
berjalan produksi di satu sekolah (SMP Maitreyawira Tanjungpinang). Aset ini belum
punya:

1. **Etalase publik.** Route `/` app langsung redirect ke login — calon pembeli dari
   sekolah lain tidak punya tempat melihat produk, fitur, dan harga.
2. **Mekanisme langganan.** Tidak ada cara bagi superadmin menetapkan & memantau masa
   aktif lisensi, dan menegakkannya (mengunci app saat berakhir) — prasyarat menjual
   SIMS sebagai layanan berlangganan (SaaS).

## 2. Tujuan (Goals)

- **G1.** Situs penjualan kredibel & meyakinkan → calon pembeli paham nilai SIMS dan
  terdorong meminta demo.
- **G2.** Menangkap *lead* lewat form → tersimpan + memberi tahu admin via email.
- **G3.** Harga jelas: tier **Dasar/Pro/Enterprise** × durasi **3/6/12 bulan**, dalam
  Rupiah dengan PPN.
- **G4.** Superadmin menetapkan durasi langganan (3/6/12 bulan), sistem menampilkan
  **sisa masa aktif dalam hari**, memperingatkan bertingkat, lalu **mengunci app**
  saat kadaluarsa.

## 3. Non-Goals (Di Luar Cakupan)

- **Pembayaran online otomatis** (payment gateway, invoice otomatis, auto-renew).
  Langganan diaktifkan/diperpanjang **manual** oleh superadmin.
- **Multi-tenant.** SIMS tetap single-tenant; modul langganan mengelola **satu**
  lisensi untuk instans ini.
- **Portal self-service pelanggan** (login pembeli, kelola langganan sendiri).
- Perombakan modul aplikasi mana pun yang sudah ada (selain titik integrasi §10).

## 4. Target Pengguna & Persona

| Persona | Peran | Kebutuhan utama |
|---|---|---|
| **Kepala Sekolah / Yayasan** | Pengambil keputusan | Bukti nilai, kredibilitas, harga jelas, mudah minta demo |
| **Admin IT / Operator sekolah** | Evaluator teknis | Rincian fitur, keamanan, cara implementasi |
| **Superadmin SIMS** | Operator internal (penjual) | Set & pantau masa langganan dalam hari, tegakkan lisensi |

## 5. Metrik Keberhasilan

- **M1.** Situs `www.` live, semua halaman 200 OK, skor mobile baik.
- **M2.** Form kontak: lead tersimpan **dan** email notifikasi terkirim.
- **M3.** Superadmin set durasi 3/6/12 bulan; banner "sisa N hari" akurat (uji: set 3
  bulan hari ini → sisa ≈ 90–92 hari sesuai kalender); saat kadaluarsa non-superadmin
  terkunci, superadmin tetap bisa masuk untuk memperpanjang.
- **M4.** Nol regresi pada aplikasi eksisting (`php artisan test` tetap hijau).

---

## 6. Deliverable A — Situs Penjualan Publik (proyek baru, subdomain `www.`)

Proyek berdiri sendiri di folder baru. Stack konsisten dengan keahlian tim: **Laravel
12 + Blade + Tailwind v4** (mudah membuat form lead + email + SEO server-rendered).

### 6.1 Halaman & Requirement Fungsional

| ID | Halaman / Endpoint | Requirement |
|---|---|---|
| FR-1 | `GET /` (landing) | Hero (value proposition + CTA "Minta Demo" & "Masuk ke Aplikasi" → `https://app.<domain>/login`), trust bar, showcase fitur (§8), blok "Cara Kerja", highlight AsistenAI, testimoni *placeholder*, ringkasan harga (tier × durasi), FAQ, CTA akhir + form demo ringkas. |
| FR-2 | `GET /fitur` | Rincian seluruh modul nyata dikelompokkan (Akademik, Kehadiran, Keuangan, Sarpras, AI, Pendukung). |
| FR-3 | `GET /harga` | 3 kartu tier **Dasar / Pro / Enterprise** + toggle durasi **3 / 6 / 12 bulan** yang mengubah harga; 12 bulan bertanda "paling hemat"; Enterprise = "Hubungi Kami". Harga Rupiah + **rincian PPN** (§6.4). Tabel perbandingan fitur per tier (§6.3). FAQ harga. |
| FR-4 | `GET /kontak` | Form demo/kontak + info kontak (email, WA, alamat). |
| FR-5 | `POST /kontak` | Validasi server-side, throttle (5/menit), honeypot anti-spam, simpan ke tabel `leads`, kirim email ke admin, redirect + flash sukses. Dipakai juga oleh form ringkas di landing. |

### 6.2 Model Harga

- Dua sumbu: **tier fitur** (Dasar/Pro/Enterprise) × **durasi langganan** (3/6/12
  bulan). Durasi lebih panjang → lebih hemat per bulan; 12 bulan ditonjolkan.
- Enterprise memakai CTA "Hubungi Kami" (tanpa harga publik).
- Semua angka harga adalah **placeholder eksplisit** (`Rp — · contoh`) agar mudah
  diganti sebelum publikasi.

### 6.3 Matriks Fitur per Tier (proposal awal — dapat disesuaikan)

| Modul | Dasar | Pro | Enterprise |
|---|:--:|:--:|:--:|
| Data master (siswa/guru/kelas/mapel) | ✅ | ✅ | ✅ |
| Absensi & Presensi (wajah, QR+GPS, kiosk) | ✅ | ✅ | ✅ |
| Penilaian Kurikulum Merdeka + Rapor PDF | ✅ | ✅ | ✅ |
| Pengumuman, Kalender, Agenda, Jadwal | ✅ | ✅ | ✅ |
| Ruang Kelas digital (materi/tugas + anti-cheat) | — | ✅ | ✅ |
| Forum Diskusi Kelas | — | ✅ | ✅ |
| Keuangan / SPP (bendahara, VA, verifikasi) | — | ✅ | ✅ |
| Kedisiplinan (Poin / P3) | — | ✅ | ✅ |
| Kartu Pelajar, Chatbot Helpdesk | — | ✅ | ✅ |
| Notifikasi Push Android (FCM) + App Android | — | ✅ | ✅ |
| Sarpras lengkap (aset, denah, booking, dll.) | — | — | ✅ |
| AsistenAI (Gemini): chatbot, asisten guru, RAG, narasi data | — | — | ✅ |
| Login WebAuthn (sidik jari / Face ID) | — | — | ✅ |
| Dukungan prioritas & kustomisasi | — | — | ✅ |

### 6.4 Mata Uang & PPN

- **Mata uang:** Rupiah (IDR), format `Rp` pemisah ribuan titik.
- **PPN:** tampilkan eksplisit. Default **11%** (tarif standar berlaku saat ini; dibuat
  **konfigurabel** karena ada rencana pemerintah ke 12%). Kartu harga menampilkan:
  **Harga dasar + PPN (11%) = Total**, atau label "belum termasuk PPN 11%" bila lebih
  ringkas. Nilai tarif PPN disimpan sebagai konstanta/config agar mudah diubah.

## 7. Deliverable B — Modul Langganan (Lisensi) di `sims_app` (subdomain `app.`)

Modul **baru & terisolasi**. Menyentuh app hanya lewat 3 titik integrasi (§10).

| ID | Requirement |
|---|---|
| FR-6 | Tabel `langganan` menyimpan satu lisensi aktif (single-tenant). |
| FR-7 | Halaman **superadmin** "Langganan": pilih durasi **3 / 6 / 12 bulan** (+ tanggal mulai, default hari ini) → sistem hitung & simpan `berakhir_pada`. Hanya role `superadmin`. |
| FR-8 | Aksi **Perpanjang**: menambah durasi ke `berakhir_pada` bila masih aktif, atau dari hari ini bila sudah lewat. |
| FR-9 | **Tampilan sisa dalam HARI**: `sisa_hari = now()->startOfDay()->diffInDays(berakhir_pada, false)` (negatif = kadaluarsa). Tampilkan `"Langganan aktif · sisa N hari"` / `"Kadaluarsa N hari lalu"` + indikator. |
| FR-10 | **Peringatan bertingkat** (banner khusus superadmin, tingkat keparahan naik): Peringatan 1 saat `sisa_hari ≤ 14` (info), Peringatan 2 saat `≤ 7` (kuning), Peringatan 3 saat `≤ 3` (merah). Ambang batas konfigurabel. |
| FR-11 | **Penguncian app saat kadaluarsa**: bila `sisa_hari ≤ 0`, seluruh pengguna **non-superadmin** diblokir ke halaman "Langganan berakhir" (hubungi pengelola). **Superadmin dikecualikan** agar tetap bisa masuk & memperpanjang. Route login/logout/langganan tetap dapat diakses. |

### 7.1 Aturan Perhitungan (kritis)

- `berakhir_pada = mulai_pada->copy()->addMonths(durasi_bulan)` — **kalender nyata**,
  BUKAN `30 × bulan` (akurat di bulan 31 hari & Februari).
- Tampilan selalu dalam **hari** (bukan bulan), sesuai permintaan.
- `durasi_bulan` **hanya menerima 3, 6, atau 12** (validasi `in:3,6,12`, server-side).

## 8. Inventaris Fitur Nyata (Sumber Copy — Dilarang Mengarang di Luar Ini)

1. **Absensi & Presensi** — absensi wajah, absen QR harian + validasi GPS, mode kiosk
   piket (tanpa login), presensi guru.
2. **Penilaian Kurikulum Merdeka** — formatif, sumatif, PTS, PAS, KKTP, materi &
   Tujuan Pembelajaran, penjabaran, rapor + deskripsi, konfirmasi/kunci rapor.
3. **Rapor** — rekap nilai, cetak rapor PDF, kop rapor kustom.
4. **Ruang Kelas digital** — materi, tugas, pengumpulan, penilaian & transfer nilai,
   komentar, mode kunci ujian + pemantauan (anti-cheat).
5. **Forum Diskusi Kelas** — topik, komentar, reaksi, pin/lock, komentar terbaik,
   kehadiran real-time.
6. **Keuangan / SPP** — bendahara, Virtual Account manual, tagihan 12 bulan, upload
   bukti, verifikasi dua tahap, tahun ajaran Juli–Juni.
7. **Sarpras** — inventaris aset, kategori, denah ruangan interaktif, booking ruangan,
   peminjaman, lapor kerusakan, perbaikan & teknisi, jadwal pemeliharaan, mutasi,
   pengadaan, penghapusan, supplier, laporan.
8. **AsistenAI (Google Gemini)** — chatbot semua peran, asisten guru (buat soal/kuis +
   ekspor Word, rangkum, feedback), narasi data, tanya-jawab dokumen (RAG) + sitasi,
   grounding web.
9. **Kedisiplinan** — poin siswa & pencatatan pelanggaran (P3).
10. **Pendukung** — pengumuman, kalender, agenda, ekskul, cetak kartu pelajar, jadwal
    pelajaran, chatbot helpdesk dua arah, push Android (FCM), aplikasi Android
    (WebView) unduh APK/AAB, login WebAuthn (sidik jari/Face ID).
11. **Keamanan** — rate-limit login, RBAC, file sensitif di disk privat, header
    keamanan (anti-clickjacking, HSTS), CSRF.

**Proof point nyata:** 11 peran pengguna, 100+ tabel database.

## 9. Model Data

### 9.1 Tabel `leads` (di proyek marketing)
| Kolom | Tipe | Catatan |
|---|---|---|
| uuid | uuid, PK | HasUuids |
| nama | string | wajib |
| sekolah | string | wajib |
| jabatan | string | opsional |
| email | string | wajib, tervalidasi |
| no_hp | string | opsional |
| perkiraan_siswa | integer | opsional |
| tier_diminati | string | opsional (dasar/pro/enterprise) |
| pesan | text | opsional |
| sumber | string | 'landing' / 'kontak' |
| created_at / updated_at | timestamp | |

### 9.2 Tabel `langganan` (di `sims_app`)
| Kolom | Tipe | Catatan |
|---|---|---|
| uuid | uuid, PK | HasUuids |
| paket | string | tier: dasar/pro/enterprise (opsional) |
| durasi_bulan | integer | **hanya 3 / 6 / 12** |
| mulai_pada | date | default hari ini |
| berakhir_pada | date | = mulai_pada + durasi_bulan (kalender) |
| status | string | aktif / kadaluarsa |
| catatan | text | opsional |
| diatur_oleh | uuid | user (superadmin) |
| created_at / updated_at | timestamp | |

## 10. Batasan, Penempatan & Titik Integrasi

- **Penempatan (subdomain terpisah):**
  - `www.<domain>` → **situs penjualan** (proyek baru). Route publik tanpa auth.
  - `app.<domain>` → **SIMS** (repo `sims_app` yang ada). Perilaku `/` app **tidak
    diubah** (tetap redirect ke login) — karena marketing kini di subdomain sendiri.
- **Stack wajib (kedua deliverable):** Laravel 12, Blade + Alpine, Tailwind v4
  (`@theme` di `resources/css/app.css`, tanpa `tailwind.config.js`), Vite 7, ikon
  Lucide, font Instrument Sans, dark mode class-based. **Tanpa dependensi npm/composer
  baru** di `sims_app`. (Proyek marketing baru bebas memilih dependensi minimalnya
  sendiri, tetap dalam stack di atas.)
- **Konvensi:** UUID via HasUuids, `casts()` (bukan `$casts`), write dalam
  `DB::transaction()`, seluruh UI Bahasa Indonesia.
- **3 titik integrasi ke `sims_app`** (modul langganan) — hanya ini yang boleh
  menyentuh app:
  1. Menu "Langganan" di area superadmin.
  2. Blade partial banner "sisa N hari / peringatan bertingkat" di layout app, khusus
     superadmin.
  3. **Middleware penegak lisensi** pada grup `web`: bila kadaluarsa, blokir
     non-superadmin ke halaman "Langganan berakhir"; kecualikan superadmin & route
     login/logout/langganan.
- Notifikasi email lead memakai pola `Notification::route('mail', $email)->notify(...)`
  seperti `FeedbackController`.

## 11. Requirement Non-Fungsional

- **Responsif** mobile-first; **Aksesibilitas** (kontras, alt, fokus keyboard, aria
  pada toggle/accordion); **SEO** (title/meta unik + Open Graph per halaman
  marketing); **Performa** (ikon Lucide, gambar placeholder ringan rasio tetap);
  **Dark mode** di seluruh situs marketing.
- **Keamanan form:** validasi server-side, throttle, honeypot.
- **Nol regresi:** `php artisan test` di `sims_app` tetap hijau setelah pekerjaan.

---

## 12. Keputusan Final (Open Questions — TERKUNCI 🔒)

| ID | Keputusan |
|---|---|
| **OQ-1 — Perilaku kadaluarsa** | **3 tahap peringatan lalu app dikunci.** Peringatan pada H-14, H-7, H-3 (naik keparahan); saat `sisa_hari ≤ 0`, non-superadmin diblokir ke halaman "Langganan berakhir", superadmin tetap bisa masuk untuk memperpanjang. |
| **OQ-2 — Struktur harga** | **Tier Dasar / Pro / Enterprise** × durasi **3/6/12 bulan**. Enterprise = "Hubungi Kami". Matriks fitur §6.3. |
| **OQ-3 — Penempatan** | **Subdomain terpisah:** `www.` = situs penjualan (proyek/folder baru), `app.` = SIMS. Perilaku `/` app tidak diubah. |
| **OQ-4 — Mata uang & PPN** | **Rupiah (IDR)** + tampilkan **PPN 11%** (konfigurabel; rencana 12%). Kartu harga menampilkan harga dasar + PPN = total. |

---

## 13. Rencana Fase

Dua jalur (**Track A: Marketing** di folder baru, **Track B: Langganan** di
`sims_app`) yang **independen & boleh paralel** setelah Fase 0. Setiap fase punya
**kriteria selesai (Done)** yang harus lolos sebelum lanjut.

### Fase 0 — Keputusan & Persiapan (bersama)
- **Aktivitas:** keputusan §12 sudah terkunci. Siapkan: nama domain + subdomain
  (`www`/`app`) & DNS, angka harga per tier×durasi, tarif PPN, aset logo/gambar
  placeholder, email penerima lead, nama folder proyek marketing.
- **Done:** semua input siap; DNS/subdomain direncanakan.

---
### Track B — Modul Langganan (di `sims_app`)

#### Fase B1 — Fondasi Data Langganan
- **Deliverable:** migration + model `Langganan` (HasUuids, `casts()`, guard
  `durasi_bulan in:3,6,12`).
- **Done:** `php artisan migrate` sukses; tabel ada; test suite hijau.

#### Fase B2 — Manajemen Langganan Superadmin
- **Deliverable:** `LanggananController` (role `superadmin`) + view: set durasi 3/6/12
  + tanggal mulai → hitung `berakhir_pada` (addMonths); aksi Perpanjang; menu di area
  superadmin (**titik integrasi 1**).
- **Done:** superadmin bisa set/ubah/perpanjang; role lain 403; durasi selain 3/6/12
  ditolak.

#### Fase B3 — Sisa Hari, Peringatan Bertingkat & Penguncian
- **Deliverable:** partial banner sisa-hari + 3 tingkat peringatan (**titik integrasi
  2**); middleware penegak lisensi yang mengunci non-superadmin saat kadaluarsa +
  halaman "Langganan berakhir" (**titik integrasi 3**).
- **Done:** set 3 bulan hari ini → "sisa ≈ 90–92 hari"; ambang H-14/7/3 memicu banner
  yang benar; saat kadaluarsa non-superadmin terkunci, superadmin lolos & bisa
  memperpanjang; `php artisan test` hijau (tambah test untuk gate lisensi).

---
### Track A — Situs Penjualan (folder/proyek baru, subdomain `www.`)

#### Fase A1 — Bootstrap Proyek Marketing
- **Deliverable:** skeleton Laravel 12 baru + Tailwind v4 + layout marketing (nav +
  footer sendiri, dark mode); routing di-scope domain `www.`; migration + model
  `leads`.
- **Done:** proyek jalan lokal; `/` membuka landing kerangka; `migrate` sukses.

#### Fase A2 — Landing & Halaman Fitur
- **Deliverable:** isi landing `/` (hero, trust bar, showcase fitur, cara kerja,
  highlight AsistenAI, testimoni placeholder, FAQ, CTA) + `/fitur`.
- **Done:** copy hanya dari §8; responsif & dark mode OK; SEO meta terisi; tombol
  "Masuk" menuju `app.<domain>/login`.

#### Fase A3 — Halaman Harga (Tier × Durasi + PPN)
- **Deliverable:** `/harga` — 3 kartu tier Dasar/Pro/Enterprise + toggle 3/6/12 bulan
  (harga placeholder Rupiah + rincian PPN 11%); Enterprise "Hubungi Kami"; tabel
  perbandingan (§6.3); FAQ harga; ringkasan harga di landing.
- **Done:** toggle mengubah harga; 12 bulan "paling hemat"; PPN tampil; aksesibel
  (aria pada toggle).

#### Fase A4 — Penangkapan Lead (Kontak/Demo)
- **Deliverable:** `/kontak` + `POST /kontak` (validasi, throttle, honeypot, simpan
  `leads`, email notif via pola Feedback), flash sukses; form ringkas di landing.
- **Done:** submit valid → baris `leads` bertambah **dan** email terkirim; honeypot &
  throttle bekerja.

---
### Fase 8 — Poles, Uji, Rilis (bersama)
- **Deliverable:** audit SEO/aksesibilitas/responsif/dark mode; isi teks & harga final
  (ganti placeholder, tetapkan PPN); konfigurasi DNS `www`/`app`; checklist go-live
  kedua deliverable.
- **Done:** semua halaman `www.` 200 OK; `sims_app` test hijau; langganan+kunci teruji
  end-to-end; placeholder harga/teks terganti; checklist produksi terpenuhi.

---

## 14. Ketertelusuran (Requirement → Fase)

| Requirement | Fase |
|---|---|
| FR-1 Landing | A2 |
| FR-2 Fitur | A2 |
| FR-3 Harga (tier × durasi + PPN) | A3 |
| FR-4 / FR-5 Kontak + Lead | A4 |
| FR-6 Tabel langganan | B1 |
| FR-7 / FR-8 Set & Perpanjang | B2 |
| FR-9 Sisa hari | B3 |
| FR-10 Peringatan bertingkat | B3 |
| FR-11 Penguncian saat kadaluarsa | B3 |
| Non-fungsional (SEO/a11y/perf/dark) | A2–A4, 8 |

---

## 15. Catatan Sinkronisasi Prompt

Prompt coding agent yang dibuat sebelumnya perlu **disinkronkan** dengan keputusan
terkunci di §12 (khususnya: pemisahan folder `www`/`app`, tier harga, PPN, dan
middleware penguncian). Gunakan PRD ini sebagai acuan utama bila terjadi selisih.
