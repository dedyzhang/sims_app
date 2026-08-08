# Progress — Piket Guru & Substitusi Kelas

> **Agent:** baca file ini + `PRD.md` + `features/*.md` di folder ini sebelum mengerjakan task modul Piket Guru. Status task di `features/NN-*.md` (suffix `[DONE]`) harus sinkron dengan checklist di bawah. Jangan lompat fase tanpa approval FL untuk task yang menyentuh migration, auth/policy, atau alur nilai/agenda.

**Verifikasi terakhir:** 2026-08-08 — **Fase 1-4 selesai**; perbaikan integrasi dashboard sync + H1 reminder (lihat catatan di bawah). Fase 5 dihapus dari navigasi.

**Perbaikan 2026-08-08:**
- **Dashboard sync:** `PiketSyncService` dipakai di `DashboardController` agar widget ketidakhadiran guru piket otomatis menyinkronkan `presensi_gurus` → `guru_tidak_hadir` saat dashboard dibuka (uji: `PiketDashboardSyncTest`).
- **H1 reminder:** `PiketH1Reminder` memakai query `hari` ISO (bukan tanggal absolut) selaras skema rotasi per hari; notifikasi idempoten via `jadwal_piket_id` + `tanggal_piket` (uji: `PiketH1ReminderTest`).
- **Menu vs policy:** sidebar "Jadwal Piket" hanya untuk admin (`JadwalPiketPolicy::manage`); kepala/kurikulum tetap akses operasional read-only.
- **Route orphan:** `piket.dashboard` / `piket.rekap` redirect ke dashboard utama (bookmark lama).
- **Panduan:** `docs/PANDUAN_PENGGUNAAN_SIMS_APP.md` §15 + `docs/PANDUAN_VISUAL_PIKET_GURU_GRUP_CHAT.md` disinkronkan dengan struktur menu/sidebar saat ini (2026-08-08).

**Verifikasi sebelumnya:** 2026-08-01 — **Fase 1-5 selesai dan diverifikasi end-to-end**. Ditambahkan penanda `is_ketua` pada `jadwal_piket`: admin memilih tepat satu ketua untuk setiap Senin-Jumat, dan hanya ketua yang dapat mengatur penugasan pengganti/titip tugas. Integrasi penugasan memakai struktur jadwal sekolah (`jadwals.hari` + `id_jam`, fallback `jam_ke`), mengecualikan guru yang mengajar/tidak hadir/sudah mengisi slot lain, dan validasi server-side mengunci guru kandidat untuk mencegah double-assignment race. Full suite terakhir sebelum perubahan ketua: **881 passed, 22 skipped, 4086 assertions**. **Belum diverifikasi visual di browser sungguhan.**

---

## Fase 1: Jadwal Piket Guru — SELESAI (task 1-11)

Ref: `features/01-jadwal-piket-guru.md`

- [x] 1–5 UI kalender rotasi (`piket.kalender`) + kelola rotasi (`piket.rotasi`) — `app/Http/Controllers/PiketController.php`, `resources/views/piket/*.blade.php`, menu di `layouts/app.blade.php`, modul `piket` di `ModulAktif`
- [x] 6 Migration `2026_07_31_090000_create_jadwal_piket_table.php` + model `JadwalPiket` — dijalankan & diverifikasi round-trip
- [x] 7 `PiketController` real (kalender + rotasi query Eloquent asli, endpoint CRUD via AJAX)
- [x] 8 Endpoint tukar jadwal (`rotasiTukar`, `DB::transaction()` + `lockForUpdate()`) — diverifikasi end-to-end
- [x] 9 `JadwalPiketPolicy` (`manage` = admin-only, auto-discovered) — controller diubah ke `$this->authorize()`
- [x] 10 `piket:h1-reminder` command + `PiketH1Notification` (channel database), dijadwalkan harian 15:00 — diverifikasi kirim 1x & idempoten
- [x] 11 `JadwalPiketFactory` + `PiketSeeder` (idempoten) — diverifikasi jalan (50 slot) & idempoten, lalu data contoh dibersihkan dari database aktif atas keputusan FL (seeder tetap tersedia untuk dijalankan manual kapan saja)

**Belum diverifikasi visual di browser** — semua verifikasi di atas lewat `php artisan tinker` (render Blade + panggil controller/command langsung sebagai user admin sungguhan). FL disarankan buka `/piket` dan `/piket/rotasi` langsung untuk cek tampilan.

## Fase 2: Pencatatan Guru Tidak Hadir — SELESAI (task 1-11)

Ref: `features/02-pencatatan-guru-tidak-hadir.md`

- [x] 1–5 UI daftar tidak hadir + form manual + panel jam kosong (data tiruan)
- [x] 6 Migration & model `GuruTidakHadir` (FK ke `presensi_gurus`/`jadwals` hard-constraint)
- [x] 7 Controller + service sync `presensi_gurus`
- [x] 8 Endpoint input manual + query jam kosong dari `jadwals` (by `hari`)
- [x] 9 `GuruTidakHadirPolicy` (+ akses baca role `kurikulum`)
- [x] 10 Activity log input manual
- [x] 11 Seeder & factory

## Fase 3: Penugasan Guru Pengganti — SELESAI (task 1-11)

Ref: `features/03-penugasan-guru-pengganti.md`

- [x] 1–5 UI daftar jam kosong + assign pengganti (data tiruan)
- [x] 6 Migration & model `PenugasanPengganti`
- [x] 7 Controller + query guru tersedia terintegrasi jadwal sekolah + validasi server-side assignment
- [x] 8 Endpoint ambil alih + update status
- [x] 9 `PenugasanPenggantiPolicy` (wewenang ketua dicek dinamis dari `jadwal_piket.is_ketua`, bukan role statis; + akses baca role `kurikulum`)
- [x] 10 Activity log assign/ambil alih
- [x] 11 Seeder & factory
- [x] Penanda ketua guru piket: migration `is_ketua`, pilihan satu ketua per Senin-Jumat di UI, policy assignment/titip hanya untuk ketua

**Polish UI lanjutan:** `resources/views/piket/penugasan.blade.php` kini menampilkan setiap
slot penugasan dalam kartu vertikal full-width; informasi slot, status, dropdown, dan tombol
aksi tersusun ke bawah agar lebih mudah dipakai dari layar HP. Form lapor ketidakhadiran di
`tugas-saya.blade.php` memakai layout landscape dua kolom di desktop dan portrait satu kolom
di mobile. Menu dan route Dashboard Piket/Rekap Bulanan dihapus sesuai keputusan FL;
dashboard utama guru piket tetap menampilkan ketidakhadiran dan tugas berdasarkan jadwal
piket aktif pada hari tersebut.

## Fase 4: Distribusi Tugas ke Kelas — SELESAI (task 1-12, termasuk perluasan Ruang Kelas)

Ref: `features/04-distribusi-tugas-kelas.md`

- [x] 1–5 UI upload guru asli + titip manual + konfirmasi (data asli)
- [x] 6 Migration & model `TugasKelas` (FK polos, konsisten konvensi codebase)
- [x] 7 Controller upload + titip manual
- [x] 8 Endpoint konfirmasi → auto-create `agendas`
- [x] 9 `TugasKelasPolicy`
- [x] 10 Activity log titip tugas & konfirmasi
- [x] 11 `TugasKelasFactory` + `TugasKelasSeeder` (idempoten) — diverifikasi jalan, idempoten, data contoh dibersihkan (scoped delete untuk baris `agendas` terkait, bukan truncate)
- [x] 12 **(baru)** Terbit ke Ruang Kelas siswa — migration `2026_07_31_150000_add_classroom_assignment_to_tugas_kelas.php`, `ClassroomService::subjectRoom()`/`linkToKelas()` dipakai (bukan sistem paralel), `ClassroomAssignment` published + `ClassroomAssignmentFile` (file disalin ke disk `public`). Diverifikasi: siswa sungguhan lolos `ClassroomPolicy::view()` asli. Lihat PRD §10.

## Fase 5: Dashboard & Rekap Kepala Sekolah — DIHAPUS DARI NAVIGASI (keputusan FL)

Ref: `features/05-dashboard-rekap-kepala-sekolah.md`

- [x] 1–5 UI dashboard real-time (`piket.dashboard`) + rekap bulanan (`piket.rekap`) + export PDF (data asli, bukan tiruan — langsung dibangun di atas skema Fase 1-4 yang sudah ada)
- [x] 6 `DashboardPiketService` (`summaryRealTime`, `rekapBulanan`)
- [x] 7 `DashboardPiketController` — akses `kepala`/`kurikulum`/`admin`/`superadmin`
- [x] 8 `RekapPiketController` + export PDF (`barryvdh/laravel-dompdf`, terpasang & diverifikasi — PDF asli, bukan placeholder)
- [x] 9 Otorisasi inline `abort_unless` (pola sama seperti `KalenderController::guard()`) — akses `kepala`/`admin`/`superadmin` untuk rekap+export; `kurikulum` cuma dashboard (read-only), **tidak** dapat rekap bulanan/export, sesuai PRD §9
- [x] 11 `RekapPiketHistorisSeeder` (data 2 bulan ke belakang, idempoten)

**QA pass (2026-07-31) — 3 bug ditemukan & diperbaiki sebelum verifikasi akhir:**
1. `DashboardPiketController`/`RekapPiketController` pakai `$request->user()` (null di luar siklus HTTP penuh, juga tidak konsisten dengan `auth()->user()` yang dipakai di semua controller piket lain) → diganti `auth()->user()`.
2. `DashboardPiketService::summaryRealTime()` — hitungan "tercover" hanya mengecek `id_guru_pengganti`/`tugasKelas`, **melewatkan kasus piket ambil alih** (`id_guru_piket` terisi tapi belum ada `tugas_kelas`) → diganti jadi `status != 'menunggu'` (satu query, mencakup kedua kasus). Diverifikasi: slot ambil-alih sekarang benar terhitung tercover.
3. `DashboardPiketService::rekapBulanan()` — "total_mengganti" cuma hitung `id_guru_pengganti`, **tidak menghitung piket yang ambil alih sendiri** (`id_guru_piket`) — padahal PRD eksplisit minta rekap ini untuk mendeteksi beban kerja tidak merata, dan piket yang sering ambil alih adalah sinyal itu sendiri → digabung hitung keduanya. Diverifikasi: guru piket yang ambil alih sekarang muncul di rekap dengan `total_mengganti` benar.

Juga dibersihkan: komentar TODO/hedging ("asumsi... kita asumsikan bisa dipanggil") di `RekapPiketController` soal DomPDF — sudah dikonfirmasi terpasang (`barryvdh/laravel-dompdf: ^3.1` di `composer.json`), komentar dihapus karena sudah tidak relevan.

Semua diverifikasi end-to-end via `php artisan tinker` (render sebagai admin & sebagai guru biasa untuk cek penolakan akses, skenario piket-ambil-alih nyata, PDF export menghasilkan file `%PDF` asli).

---

## Keputusan terbuka (lihat PRD §9)

Item 1, 2, 3, 5 (skema tabel, role `guru_piket`, role Waka Kurikulum) **sudah terverifikasi dari codebase 2026-07-31** — lihat PRD §6/§7. Tidak lagi memblokir.

1. 1 atau lebih guru piket per hari? — PRD **jalan dengan asumsi 1/hari**; tidak memblokir mulai Fase 1, tinggal dikoreksi (kolom slot tambahan) kalau FL bilang beda.
