# Jadwal Piket Guru

Rotasi jadwal piket harian/mingguan yang diatur admin, jadi acuan siapa piket hari itu.

## Spesifikasi

### Tujuan
Menggantikan jadwal piket yang selama ini dicetak/ditempel manual dengan kalender rotasi digital, supaya semua guru (dan sistem) tahu siapa piket hari ini tanpa perlu cek papan pengumuman, dan supaya fase-fase selanjutnya (pencatatan tidak hadir, penugasan pengganti) punya acuan "siapa yang berwenang bertindak hari ini".

### Selesai bila
- Admin bisa lihat kalender bulanan berisi guru piket per hari, scoped ke `school_id` dan `tahun_ajaran_id` aktif.
- Admin bisa assign/ubah guru piket untuk tanggal tertentu, termasuk tukar jadwal antar guru (status `Ditukar`).
- Guru piket dapat notifikasi in-app H-1 sebelum gilirannya.
- Guru piket hanya bisa mengelola hari piketnya sendiri (kecuali admin menugaskan hari lain).

## Sub-fitur: Kalender Rotasi

Tampilan kalender bulanan menampilkan siapa piket per hari.

### Tujuan
Beri admin dan semua guru gambaran cepat rotasi piket sebulan ke depan/belakang, termasuk status tiap slot (aktif/ditukar/dibatalkan).

### Selesai bila
- Kalender bulanan render dari tabel `jadwal_piket`, filter `school_id` + `tahun_ajaran_id`.
- Klik tanggal menampilkan detail (nama guru piket, status).
- Navigasi bulan sebelumnya/berikutnya tanpa reload penuh (Livewire).

## Sub-fitur: CRUD Rotasi Manual

Admin assign guru ke slot piket tertentu, termasuk tukar jadwal antar guru.

### Tujuan
Beri admin kontrol penuh menyusun rotasi di awal semester dan menyesuaikan saat ada guru yang perlu tukar jadwal, tanpa perlu edit manual di luar sistem.

### Selesai bila
- Admin bisa create/update/delete slot `jadwal_piket` per tanggal.
- Tukar jadwal antar dua guru tercatat dengan status `Ditukar` (bukan hapus-buat-ulang), supaya riwayat tetap terlihat.
- Validasi: tidak boleh dua guru piket aktif di tanggal & sekolah yang sama (kecuali asumsi multi-piket per hari dikonfirmasi berbeda — lihat PRD §9).

## Sub-fitur: Notifikasi H-1

Pengingat sederhana (in-app) ke guru piket sehari sebelum gilirannya.

### Tujuan
Pastikan guru piket tidak lupa gilirannya, karena keterlambatan piket berarti guru tidak hadir hari itu tidak tercatat tepat waktu.

### Selesai bila
- Notifikasi in-app muncul otomatis H-1 untuk guru yang dijadwalkan piket besok.
- Notifikasi hilang/ditandai terbaca setelah guru buka dashboard piket hari-H.

## Task

### 1. Buat halaman kalender piket dengan data tiruan `[DONE]`
`app/Http/Controllers/PiketController@kalender` + `resources/views/piket/kalender.blade.php`. Grid bulanan (pola sama seperti `KalenderController`/`resources/views/kalender/index.blade.php`), array guru piket hardcode, belum query database. Route `GET /piket` (`piket.kalender`), dibungkus middleware `modul:piket` (kode `piket` didaftarkan di `App\Support\ModulAktif::semua()`), menu sidebar ditambahkan di `layouts/app.blade.php`.

### 2. Tambah interaksi filter bulan & klik-tanggal-untuk-detail `[DONE]`
Navigasi bulan sebelumnya/berikutnya (server-side, query `?bulan=`) + modal detail slot (Alpine `x-data`, bukan Livewire — cukup untuk state client-only, konsisten dengan pola modal lain di `layouts/app.blade.php`), masih pakai data tiruan.

### 3. Buat form CRUD rotasi (assign/tukar jadwal) dengan data tiruan `[DONE]`
`resources/views/piket/rotasi.blade.php` (route `GET /piket/rotasi`, admin-only via `isAdmin()`). Tambah/ubah/hapus/tukar slot rotasi diproses di Alpine client state (`rotasiKelola()`), belum submit ke server — sesuai instruksi "array in-memory", disederhanakan jadi in-browser karena task 6+ akan mengganti seluruhnya dengan Eloquent asli.

### 4. Integrasikan navigasi antara kalender dan form CRUD `[DONE]`
Tombol "Kelola Rotasi" di kalender (admin only) → halaman rotasi; tombol "Lihat Kalender" di halaman rotasi → kalender; modal detail slot di kalender juga link ke halaman rotasi.

### 5. Poles tampilan dan responsivitas `[DONE]`
Kalender: grid 7 kolom tetap ringkas di layar kecil. Rotasi: tabel di desktop (`hidden md:block`), kartu bertumpuk di mobile (`md:hidden`) — pola `overflow-x-auto` + kartu konsisten dengan `settings/roles.blade.php`/`agenda/index.blade.php`. Diverifikasi render end-to-end (admin user, via `php artisan tinker`) tanpa error.

### 6. Buat migration & model `JadwalPiket` `[DONE]`
Migration `2026_07_31_090000_create_jadwal_piket_table.php` + migration penanda ketua `is_ketua`. Model `app/Models/JadwalPiket.php` (`HasUuids` + `primaryKey='uuid'`, relasi `guru()`, helper `isPiketAktif()`/`isKetuaAktif()`). Satu ketua divalidasi untuk setiap hari Senin-Jumat melalui UI dan controller.

### 7. Buat `PiketController` real (ganti data tiruan) `[DONE]`
`index()` query jadwal mingguan real + kirim daftar ketua per hari. Endpoint simpan menerima jadwal guru dan satu ketua untuk setiap hari kerja, divalidasi agar ketua benar-benar terdaftar sebagai piket pada hari tersebut.

### 8. Tambahkan endpoint tukar jadwal `[DONE]`
Penanda ketua disimpan atomik bersama jadwal piket; hanya ketua hari terkait yang dapat mengatur penugasan pengganti/titip tugas.

### 9. Tambahkan policy `JadwalPiketPolicy` `[DONE]`
`app/Policies/JadwalPiketPolicy.php` — auto-discovered oleh Laravel (pola sama seperti `GrupChatPolicy`/`GameQuizPolicy`, tidak didaftarkan manual). `viewAny`/`view`: semua user login. `manage` (create/update/delete/tukar): `$user->isAdmin()`. `PiketController` diubah dari `guardAdmin()` inline ke `$this->authorize('manage', ...)`.

### 10. Buat job/scheduler notifikasi H-1 `[DONE]`
`app/Notifications/PiketH1Notification.php` (channel `database`, pola sama seperti `GuruTerlambatNotification`) + `app/Console/Commands/PiketH1Reminder.php` (`piket:h1-reminder`, idempoten via cek notifikasi yang sudah ada) + terdaftar di `routes/console.php` (`dailyAt('15:00')`). Diverifikasi end-to-end: kirim 1x, run kedua 0 terkirim (tidak duplikat).

### 11. Buat seeder & factory `JadwalPiket` `[DONE]`
`database/factories/JadwalPiketFactory.php` + `database/seeders/PiketSeeder.php` (idempoten, satu ketua untuk setiap hari kerja, tidak didaftarkan di `DatabaseSeeder` — jalankan manual `php artisan db:seed --class=Database\Seeders\PiketSeeder`).
