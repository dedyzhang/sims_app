# Dashboard & Rekap Kepala Sekolah

Kepala sekolah bisa memantau kondisi piket & substitusi secara real-time dan bulanan.

## Spesifikasi

### Tujuan
Beri kepala sekolah visibilitas tanpa perlu tanya langsung ke guru piket — kondisi hari ini secara real-time untuk keputusan cepat, dan rekap bulanan untuk bahan evaluasi kinerja guru (frekuensi tidak hadir, frekuensi jadi pengganti).

### Selesai bila
- Dashboard real-time menampilkan siapa piket hari ini, jumlah guru tidak hadir, dan status cover jam kosong (sudah/belum).
- Rekap bulanan per guru bisa diakses, menampilkan frekuensi tidak hadir dan frekuensi jadi pengganti.
- Rekap bisa diunduh dalam format sederhana (tabel cetak/PDF).

### Catatan
Fase ini murni membaca data dari Fase 1-4 (tidak ada tabel baru, tidak ada write mutatif) — pola task di bawah disesuaikan (skip migration tabel baru, ganti dengan service agregasi).

## Sub-fitur: Monitor Real-time

Siapa piket hari ini, berapa guru tidak hadir, berapa jam yang sudah/belum ter-cover.

### Tujuan
Kepala sekolah bisa cek kondisi hari ini kapan saja tanpa menunggu laporan manual dari guru piket.

### Selesai bila
- Widget/summary card: nama guru piket hari ini, jumlah guru tidak hadir, jumlah jam kosong ter-cover vs belum (dari status `penugasan_pengganti`).
- Data ter-refresh saat halaman dibuka (polling ringan opsional, bukan wajib real-time via websocket — konsisten dengan requirement async-first Arena Belajar).

## Sub-fitur: Rekap Bulanan per Guru

Frekuensi tidak hadir per guru, frekuensi jadi pengganti, untuk bahan evaluasi kinerja.

### Tujuan
Beri data kuantitatif untuk evaluasi kinerja guru per bulan/semester — siapa yang paling sering tidak hadir, siapa yang paling sering jadi pengganti (beban kerja tidak merata bisa terlihat).

### Selesai bila
- Filter periode (bulan/semester), tabel agregat per guru: jumlah hari tidak hadir (per alasan), jumlah kali jadi pengganti.
- Data ditarik dari `guru_tidak_hadir` dan `penugasan_pengganti` (tidak ada scope tenant — satu instalasi per sekolah).

## Sub-fitur: Export Rekap

Unduh rekap piket & substitusi per bulan/semester dalam format sederhana (tabel cetak).

### Tujuan
Kepala sekolah butuh salinan fisik/PDF untuk rapat evaluasi atau arsip, bukan hanya lihat di layar.

### Selesai bila
- Tombol export menghasilkan PDF/tabel cetak dari rekap bulanan yang sedang difilter.
- Format sederhana (nama guru, jumlah tidak hadir, jumlah jadi pengganti) — tidak perlu desain kompleks.

## Task

### 1-5. Halaman dashboard real-time + rekap bulanan + export `[DONE]`
`resources/views/piket/dashboard.blade.php` (summary card: piket hari ini, jumlah tidak hadir, jam kosong, status cover), `resources/views/piket/rekap.blade.php` (tabel agregat per guru + filter bulan/tahun), `resources/views/piket/rekap-pdf.blade.php` (layout cetak terpisah untuk DomPDF). Dibangun langsung dengan data asli (skema Fase 1-4 sudah ada saat fase ini dikerjakan, jadi tidak lewat tahap data tiruan terpisah). Navigasi dashboard ↔ rekap ↔ export saling terhubung.

### 6. Buat service agregasi query `[DONE]`
`app/Services/Piket/DashboardPiketService.php` — `summaryRealTime($tanggal)` (piket aktif, jumlah tidak hadir, jam kosong, tercover/belum) dan `rekapBulanan($tahun, $bulan)` (agregat per guru: tidak hadir per alasan, jadi pengganti). **Diperbaiki saat QA (lihat PROGRESS.md):** "tercover" awalnya melewatkan kasus piket ambil alih; "total_mengganti" awalnya tidak menghitung piket ambil alih. Keduanya sudah dibetulkan dan diverifikasi.

### 7. Buat `DashboardPiketController` + route `[DONE]`
Akses `kepala`/`kurikulum`/`admin`/`superadmin`. Otorisasi via `auth()->user()` (diperbaiki dari `$request->user()` saat QA — lihat PROGRESS.md).

### 8. Buat `RekapPiketController` + endpoint export PDF `[DONE]`
Export pakai `barryvdh/laravel-dompdf` (terpasang, diverifikasi hasil `%PDF` asli). Akses `kepala`/`admin`/`superadmin` saja (tanpa `kurikulum`, sesuai PRD §9).

### 9. Tambahkan policy akses dashboard & rekap `[DONE]`
Diimplementasikan sebagai `abort_unless` inline di controller (pola sama seperti `KalenderController::guard()`, bukan Policy class terpisah — cukup untuk kebutuhan saat ini karena aturannya statis per-role, tidak dinamis per-data seperti fase lain). Role `kepala`: full read + export. Role `kurikulum`: dashboard saja (read-only), **tidak** dapat rekap bulanan/export.

### 10. Tidak berlaku
Read-only, tidak ada aksi mutatif yang perlu activity log di fase ini.

### 11. Buat seeder data historis 2-3 bulan `[DONE]`
`database/seeders/RekapPiketHistorisSeeder.php` (idempoten via `firstOrCreate`) — data 2 bulan ke belakang, campuran alasan tidak hadir dan skenario assign/ambil-alih, untuk rekap bulanan punya angka yang masuk akal saat didemokan.
