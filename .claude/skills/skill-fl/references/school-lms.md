# LMS / SIMS Sekolah (B'tive)

Konteks: tim FL bikin **B'tive** (LMS/SIMS) dan menjualnya ke sekolah lain (klien inti: Sekolah Maitreyawira Tanjungpinang). Jenjang SD/SMP/SMA/SMK. **Produk dijual ke banyak sekolah → multi-tenant wajib.**

> Aturan teknis (UUID `HasUuids`, `school_id` scope + `BelongsToSchool` global scope, uang BIGINT+BCMath, `spatie/*`, `DB::transaction()`) mengikuti Konvensi kanonik di `SKILL.md`. File ini hanya pengetahuan domain.

## Multi-tenancy (kritikal sejak awal)

- Setiap tabel utama punya `school_id`. Jangan campur data antar sekolah — query SELALU ter-scope global scope, `school_id` diambil dari konteks auth, bukan request body.
- Pola: shared DB + kolom `school_id` (cukup untuk skala awal FL), bukan DB terpisah per sekolah.
- Role: superadmin (FL/tim), admin sekolah, guru, siswa, orang tua, bendahara — via `spatie/laravel-permission`.

## Modul inti

- **Absensi** — per kelas/mapel, harian, rekap kehadiran.
- **Ujian online / CBT** — bank soal, jadwal ujian, auto-grading pilihan ganda, manual untuk esai.
- **Penilaian** — komponen nilai (tugas/UH/UTS/UAS), bobot, rapor. Selaras kurikulum berjalan (mis. Pembelajaran Mendalam / 8-Dimensi Profil Lulusan).
- **Materi ajar** — upload per mapel/kelas, link/file.
- **Administrasi** — siswa, guru, kelas, mapel, tahun ajaran, semester.
- **Keuangan sekolah** (modul bendahara) — SPP, BOS, RKAS. Pemisahan dana BOS/SPP, budget tracking, integrasi pembayaran (Midtrans). Uang WAJIB integer rupiah BIGINT+BCMath, audit trail via activitylog, approval gate untuk aksi sensitif.
- **Sarpras** (fasilitas/aset), **pelanggaran/SP siswa**, **PBKS** (profil belajar & karakter) — modul yang pernah dibangun; PBKS punya human-review gate wajib.

## Skema dasar

- `schools`, `users` (+role), `academic_years`, `semesters`, `classes`, `subjects`, `class_subjects` (guru pengampu).
- `students` (link ke class), `enrollments`.
- `attendances` (student_id, date, session, status).
- `assignments`/`exams`, `questions`, `submissions`, `grades` (komponen + bobot).
- `materials` (subject, class, file/url).

## Catatan jenjang

- SD/SMP/SMA/SMK beda struktur nilai & mapel → konfigurasi per jenjang, jangan hardcode.
- SMK ada jurusan/kompetensi keahlian → siapkan tabel jurusan.

## Penjualan / onboarding sekolah baru

- Produk B2B → butuh flow provisioning tenant: buat school + admin awal + seed tahun ajaran.
- Materi promosi B'tive: skema warna **biru tua, putih, aksen kuning**. Pakai ini kalau bikin materi visual B'tive.
