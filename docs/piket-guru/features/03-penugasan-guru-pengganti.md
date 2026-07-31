# Penugasan Guru Pengganti

Guru piket menugaskan siapa yang mengisi jam kosong — guru lain, atau guru piket sendiri.

## Spesifikasi

### Tujuan
Memastikan setiap jam kosong akibat guru tidak hadir punya penanggung jawab yang jelas dan tercatat — bukan sekadar "dititip lisan" ke guru lain — supaya kepala sekolah bisa memantau mana yang sudah ter-cover dan mana yang belum.

### Selesai bila
- Guru piket bisa assign guru pengganti per jam kosong dari daftar guru yang jamnya kosong di jam yang sama.
- Guru piket bisa menandai dirinya sendiri sebagai pengganti kalau tidak ada guru lain tersedia.
- Status penugasan (`Menunggu` → `Ditugaskan` → `Selesai`) terlihat jelas per jam kosong.
- Role `kurikulum` (Waka Kurikulum) bisa lihat status ini read-only.

## Sub-fitur: Assign Guru Pengganti per Jam Kosong

Pilih dari daftar guru yang jamnya kosong di jam yang sama.

### Tujuan
Menghindari bentrok jadwal — guru pengganti yang dipilih harus benar-benar tidak sedang mengajar di jam yang sama.

### Selesai bila
- Sistem tampilkan daftar guru yang tidak punya jadwal mengajar di jam pelajaran tersebut (query silang `jadwals` pada `hari` yang sama).
- Assign membuat/update baris `penugasan_pengganti` dengan `id_guru_pengganti` terisi dan `status = 'ditugaskan'`.

## Sub-fitur: Piket Ambil Alih

Kalau tidak ada guru pengganti tersedia, guru piket bisa tandai dirinya sendiri yang masuk kelas.

### Tujuan
Menjamin tidak ada jam kosong yang benar-benar tanpa pengawas, bahkan saat tidak ada guru pengganti yang available.

### Selesai bila
- Tombol "Saya yang masuk" mengisi `penugasan_pengganti` dengan `id_guru_pengganti = null` dan `id_guru_piket` terisi guru piket yang login, `status = 'ditugaskan'`.

## Sub-fitur: Status Penugasan

Menunggu → Ditugaskan → Selesai, supaya kepala sekolah bisa lihat mana yang belum ter-cover.

### Tujuan
Beri visibilitas real-time ke kepala sekolah/Role `kurikulum` (Waka Kurikulum) tanpa harus tanya langsung ke guru piket.

### Selesai bila
- Status default `Menunggu` saat jam kosong terdeteksi (dari Fase 2), berubah `Ditugaskan` saat di-assign, `Selesai` setelah jam pelajaran itu berlalu (manual mark atau otomatis berdasarkan waktu).
- Dashboard piket menampilkan ringkasan jumlah per status.

## Task

### 1-5. Halaman daftar jam kosong + status penugasan + assign `[DONE]`
`resources/views/piket/penugasan.blade.php` + `app/Http/Controllers/PenugasanPenggantiController.php`. Dropdown guru tersedia (real, dari `JamKosongService::guruTersediaUntuk()`), tombol "Saya yang masuk", badge status Menunggu/Ditugaskan/Selesai + ringkasan jumlah per status, link dari/ke Fase 2 & Fase 4.

### 6. Buat migration & model `PenugasanPengganti` `[DONE]`
Migration `2026_07_31_110000_create_penugasan_pengganti_table.php` — FK polos konsisten konvensi codebase. Dijalankan & diverifikasi round-trip.

### 7. Buat `PenugasanPenggantiController` + query guru tersedia `[DONE]`
Guru tersedia = tidak sedang mengajar di `hari`+`jam_ke` yang sama DAN tidak sedang tidak-hadir hari itu (`JamKosongService::guruTersediaUntuk()`). Sinkronisasi baris `penugasan_pengganti` dari `guru_tidak_hadir` dibungkus `DB::transaction()`.

### 8. Buat endpoint "piket ambil alih" dan endpoint update status manual (mark Selesai) `[DONE]`
`assign()`, `ambilAlih()`, `selesai()` — semua diverifikasi end-to-end (nama guru pengisi benar, status berpindah sesuai alur).

### 9. Tambahkan policy `PenugasanPenggantiPolicy` `[DONE]`
Auto-discovered. `manage($user, $tanggal)` dicek dinamis via `JadwalPiket::isPiketAktif($idGuru, $tanggal)` — pakai tanggal slot terkait, bukan asumsi "hari ini", supaya piket tetap bisa beres-beres slot hari piketnya sendiri walau dibuka besoknya. Diverifikasi: guru non-piket ditolak.

### 10. Log activity untuk aksi assign & ambil alih `[DONE]`
Dicatat di `assign()` dan `ambilAlih()`. Diverifikasi 3 entri activity log tercatat benar dengan causer yang tepat.

### 11. Buat seeder & factory `PenugasanPengganti` `[DONE]`
`database/factories/PenugasanPenggantiFactory.php` + `database/seeders/PenugasanPenggantiSeeder.php` (bergantung `GuruTidakHadirSeeder`, idempoten, variasi status untuk demo).
