# Pencatatan Guru Tidak Hadir

Guru piket mencatat siapa saja guru yang tidak hadir hari itu, terhubung ke data presensi staf.

## Spesifikasi

### Tujuan
Menyatukan sumber kebenaran "guru mana yang tidak hadir hari ini" ke satu tempat — ditarik otomatis dari `Absensi PTK` yang sudah ada, dilengkapi input manual untuk kasus mendadak — supaya guru piket tidak perlu bertanya manual ke tiap ruang guru, dan langsung tahu jam pelajaran mana yang butuh pengganti.

### Selesai bila
- Guru tidak hadir yang sudah tercatat di `presensi_gurus` (status izin/sakit/alpa) otomatis muncul di daftar piket hari itu tanpa input ulang.
- Guru piket bisa tambah entri manual (alasan Sakit/Izin/Dinas Luar/Alpa) untuk guru yang belum tercatat di `presensi_gurus`.
- Setiap guru tidak hadir menampilkan daftar jam pelajaran & kelas yang kosong akibat ketidakhadirannya, otomatis dari `jadwals` (rekuren per hari-dalam-minggu).

## Sub-fitur: Tarik Data dari Absensi PTK (`presensi_gurus`)

Kalau guru sudah tercatat tidak hadir di `presensi_gurus`, otomatis muncul di daftar guru piket hari itu.

### Tujuan
Menghindari duplikasi data presensi guru — `presensi_gurus` tetap jadi satu-satunya sumber kebenaran kehadiran staf.

### Selesai bila
- Query harian menarik guru dengan `presensi_gurus.status` ∈ {izin, sakit, alpa} untuk tanggal berjalan.
- Entri `guru_tidak_hadir` dengan `sumber = 'otomatis_presensi'` dibuat/disinkron otomatis (bukan disalin manual oleh guru piket), idempoten via `id_presensi_guru`.

## Sub-fitur: Input Manual Guru Piket

Kalau ada guru mendadak izin dan belum tercatat di `presensi_gurus`, guru piket bisa input manual.

### Tujuan
Menutup celah waktu antara kejadian riil (guru izin mendadak pagi hari) dan pencatatan resmi di `presensi_gurus` yang mungkin belum sempat diinput staf TU.

### Selesai bila
- Form cepat: pilih guru, alasan (Sakit/Izin/Dinas Luar/Alpa), keterangan opsional.
- Entri tersimpan dengan `sumber = 'manual_piket'` dan `dicatat_oleh` terisi otomatis dari user login.

## Sub-fitur: Daftar Jam Pelajaran Kosong

Sistem otomatis tampilkan jam pelajaran & kelas mana saja yang kosong akibat guru tersebut tidak hadir.

### Tujuan
Guru piket langsung tahu cakupan dampak (berapa jam, kelas apa saja) tanpa harus buka jadwal pelajaran terpisah dan mencocokkan manual.

### Selesai bila
- Untuk tiap guru di `guru_tidak_hadir`, sistem query `jadwals` (filter `hari` = hari-dalam-minggu dari `tanggal`) berdasarkan `id_guru` dan tampilkan daftar jam+kelas (join `jam_pelajaran` untuk jam mulai/selesai).
- Daftar ini jadi input langsung ke Fase 3 (assign pengganti per jam kosong).

## Task

### 1-5. Halaman daftar guru tidak hadir + form manual + panel jam kosong `[DONE]`
`resources/views/piket/tidak-hadir.blade.php` + `app/Http/Controllers/GuruTidakHadirController.php`. Dibangun langsung dengan data asli (bukan tahap tiruan terpisah). Badge sumber & alasan, form manual, panel jam kosong per guru (expand/collapse), link ke Fase 3 (`piket.penugasan`).

### 6. Buat migration & model `GuruTidakHadir` `[DONE]`
Migration `2026_07_31_100000_create_guru_tidak_hadir_table.php` — kolom sesuai PRD §6, FK polos (`string('id_x',36)->index()`, tanpa `foreignUuid()->constrained()`, konsisten konvensi codebase). Dijalankan & diverifikasi round-trip.

### 7. Buat `GuruTidakHadirController` + service sinkronisasi `presensi_gurus` `[DONE]`
Sinkronisasi idempoten via `App\Services\Piket\JamKosongService` (dipakai bersama Fase 3) + cek `id_presensi_guru`. Diverifikasi: sync dari `presensi_gurus` status izin/sakit/alpa membuat baris `guru_tidak_hadir` otomatis, tidak duplikat.

### 8. Buat endpoint input manual + query jam kosong `[DONE]`
`store()` simpan manual dengan `dicatat_oleh` = user login. Jam kosong dihitung dari `jadwals` via `hari = Carbon::parse($tanggal)->dayOfWeekIso` (bukan filter tanggal langsung). Diverifikasi end-to-end.

### 9. Tambahkan policy `GuruTidakHadirPolicy` `[DONE]`
Auto-discovered (`app/Policies/GuruTidakHadirPolicy.php`). Guru piket dicek dinamis via `JadwalPiket::isPiketAktif()`. Role `kurikulum`: read-only. Diverifikasi: guru non-piket ditolak `create`.

### 10. Log activity untuk input manual `[DONE]`
`activity('piket')->causedBy(...)->performedOn($entri)->log(...)` di `store()`. Diverifikasi causer tercatat benar.

### 11. Buat seeder & factory `GuruTidakHadir` `[DONE]`
`database/factories/GuruTidakHadirFactory.php` + `database/seeders/GuruTidakHadirSeeder.php` (idempoten).
