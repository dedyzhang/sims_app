# Distribusi Tugas ke Kelas

Tugas untuk kelas yang gurunya tidak hadir didistribusikan dan otomatis tercatat di Buku Agenda.

## Spesifikasi

### Tujuan
Memastikan kelas yang gurunya tidak hadir tetap dapat tugas/materi yang tercatat rapi — bukan dititip lisan tanpa jejak — dan otomatis masuk ke Buku Agenda kelas supaya orang tua/wali kelas bisa lihat riwayatnya seperti hari belajar normal.

### Selesai bila
- Guru yang berhalangan bisa upload materi/tugas dari HP sebelum jamnya (opsional).
- Guru piket bisa titip tugas manual generik kalau guru asli tidak sempat kirim apa-apa.
- Setiap tugas yang didistribusikan otomatis membuat entri baru di `agendas` (Buku Agenda) kelas terkait, dengan penanda sumbernya (guru piket vs guru asli).

## Sub-fitur: Upload Tugas dari Guru Asli (Opsional/Remote)

Guru yang berhalangan bisa upload materi/tugas dari HP sebelum jamnya, kalau masih sempat.

### Tujuan
Memberi kesempatan guru asli tetap mengarahkan pembelajaran walau tidak hadir fisik, tanpa harus login penuh atau datang ke sekolah.

### Selesai bila
- Guru bisa akses form upload dari HP (mobile-friendly), pilih kelas & jam kosong miliknya (dari `guru_tidak_hadir`/`penugasan_pengganti` terkait dirinya), isi judul/deskripsi + file opsional.
- Tersimpan sebagai `tugas_kelas` dengan `jenis = 'Upload Guru Asli'`, status menunggu konfirmasi guru piket.

## Sub-fitur: Titip Tugas Manual oleh Guru Piket

Kalau guru asli tidak sempat kirim apa-apa, guru piket bisa isi tugas generik.

### Tujuan
Menjamin tidak ada kelas kosong tanpa tugas sama sekali, bahkan saat guru asli tidak sempat kirim apa pun.

### Selesai bila
- Form isi cepat (judul, deskripsi/instruksi seperti "baca halaman X, kerjakan latihan Y").
- Tersimpan sebagai `tugas_kelas` dengan `jenis = 'titip_manual_piket'`, `dibuat_oleh` = guru piket login.

## Sub-fitur: Auto-catat ke Buku Agenda (`agendas`)

Setiap tugas yang didistribusikan otomatis membuat entri baru di `agendas` kelas terkait.

### Tujuan
Menghindari sistem pencatatan ganda — `agendas` tetap satu-satunya riwayat resmi kegiatan kelas, termasuk saat gurunya tidak hadir.

### Selesai bila
- Setelah `tugas_kelas` disimpan/dikonfirmasi, entri `agendas` baru otomatis dibuat (`tanggal`, `id_kelas`, `id_guru` = guru asli yang tidak hadir, `id_pelajaran`, `kegiatan`/`pembahasan` diisi dari `tugas_kelas.deskripsi`, `proses = 'selesai'`), dengan penanda "diisi oleh guru piket" atau "dari guru asli" (mis. di `kendala` atau kolom penanda baru — cek dulu apakah `agendas` butuh kolom tambahan atau cukup disisipkan di teks `kegiatan`).
- `tugas_kelas.id_agenda` terisi setelah entri agenda dibuat, menghubungkan kedua record.

## Sub-fitur: Terbit ke Ruang Kelas Siswa (ditambahkan 2026-07-31)

Tugas yang dikonfirmasi juga langsung diterbitkan sebagai `ClassroomAssignment` published ke Ruang Kelas siswa — bukan cuma tercatat administratif di Agenda. Lihat PRD §10 untuk rantai data lengkap.

### Tujuan
"Siswa dapat menerima penugasan jika guru tidak hadir" (permintaan FL) — memakai jadwal ajar guru (`Jadwal.id_kelas`+`id_pelajaran`) untuk menemukan/membuat Ruang Kelas yang tepat lewat `ClassroomService::subjectRoom()` yang sudah ada (sistem yang sama dipakai Arena Belajar/Ngajar), bukan bikin portal siswa terpisah.

### Selesai bila
- `ClassroomAssignment` dibuat dengan `status='published'` (syarat wajib supaya siswa langsung bisa lihat, bukan `draft`).
- Ditautkan ke kelas yang benar via `ClassroomService::linkToKelas()`.
- File (kalau ada) disalin ke disk `public` sebagai `ClassroomAssignmentFile`.
- `tugas_kelas.id_classroom_assignment` terisi.
- Slot tanpa `id_kelas`/`id_pelajaran` (jam Istirahat/Upacara dsb) melewati bagian ini tanpa error — Agenda tetap tercatat seperti biasa.

## Task

### 1-5. Halaman upload guru asli (mobile) + titip manual + konfirmasi `[DONE]`
`resources/views/piket/tugas-saya.blade.php` (mobile-first, guru upload untuk slotnya sendiri, dengan/tanpa file) + `resources/views/piket/tugas.blade.php` (guru piket: lihat status per slot — belum ada/ada upload menunggu konfirmasi/sudah tercatat di agenda — titip manual atau konfirmasi).

### 6. Buat migration & model `TugasKelas` `[DONE]`
Migration `2026_07_31_120000_create_tugas_kelas_table.php` — FK polos konsisten konvensi codebase, kolom tambahan `file_nama_asli` (untuk nama unduhan). Upload file pakai `App\Support\Uploads::safeExtension()` (pola sama seperti `ChatAttachments`) + `Storage::disk('local')` (bukan `public` — file tidak diakses via URL langsung, harus lewat route `piket.tugas.unduh` yang diotorisasi).

### 7. Buat `TugasKelasController` (upload guru asli + titip manual) `[DONE]`
`upload()` (guru asli, hanya untuk slotnya sendiri — dicek `TugasKelasPolicy::upload()`), `titip()` (guru piket, manual + langsung konfirmasi dalam satu transaksi).

### 8. Buat endpoint konfirmasi distribusi → auto-create `agendas` `[DONE]`
`konfirmasi()` + helper privat `distribusikan()` (sebelumnya `konfirmasiKeAgenda()`, diperluas — lihat task 12) dalam `DB::transaction()`: insert `agendas` (`tanggal`, `id_guru` = guru asli, `id_kelas`/`id_pelajaran` dari `jadwal` slot, `pembahasan`/`kegiatan` dari tugas, `proses = 'selesai'`, penanda sumber di `kendala`) + update `tugas_kelas.id_agenda`. Diverifikasi: 2 entri `agendas` dibuat (upload guru asli & titip manual), masing-masing dengan kendala/pembahasan berbeda dan benar; re-konfirmasi tidak membuat duplikat (idempoten via cek `id_agenda`).

### 12. Terbitkan ke Ruang Kelas siswa `[DONE]` (ditambahkan 2026-07-31, di luar 11 task asli)
Migration `2026_07_31_150000_add_classroom_assignment_to_tugas_kelas.php` (kolom `id_classroom_assignment`). `distribusikan()` sekarang juga memanggil `buatClassroomAssignment()`: resolve/create `Classroom` via `ClassroomService::subjectRoom($slot->kelas, $slot->pelajaran, auth()->user())`, buat `ClassroomAssignment` (`status='published'`, `created_by` = akun guru asli kalau ada), `linkToKelas()`, salin file (kalau ada) ke disk `public`. Lihat PRD §10 untuk detail keputusan desain. Diverifikasi end-to-end dua kali (titip manual tanpa file; upload guru asli dengan file PDF) — termasuk verifikasi bahwa siswa sungguhan yang ter-enroll di kelas terkait lolos `ClassroomPolicy::view()` yang asli (bukan stub/simulasi).

### 9. Tambahkan policy `TugasKelasPolicy` `[DONE]`
Auto-discovered. `upload()`: hanya guru pemilik slot (`guruTidakHadir.id_guru` = guru login). `manage()` (titip/konfirmasi): guru piket dinamis via `JadwalPiket::isPiketAktif()`. `view()` (unduh file): admin/kepala/kurikulum, guru piket hari itu, atau guru pemilik slot. Diverifikasi: guru lain ditolak upload ke slot bukan miliknya.

### 10. Log activity untuk titip tugas & konfirmasi distribusi `[DONE]`
Dicatat di `upload()`, `titip()`, `konfirmasi()`. Diverifikasi causer benar untuk tiap aksi.

### 11. Buat seeder & factory `TugasKelas` `[DONE]`
`database/factories/TugasKelasFactory.php` + `database/seeders/TugasKelasSeeder.php` (idempoten, bergantung pada `PenugasanPenggantiSeeder`/`RekapPiketHistorisSeeder` — ambil slot berstatus `ditugaskan`/`selesai` yang belum punya `tugas_kelas`, buat contoh titip manual + upload guru asli, sekaligus entri `agendas` terkait). Diverifikasi jalan (2 tugas dibuat), idempoten (re-run 0 baru), data `agendas` terkait benar, lalu data contoh dibersihkan dari database aktif (scoped delete, bukan truncate, karena `agendas` tabel bersama).
