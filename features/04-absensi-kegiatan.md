# Absensi Kegiatan

Fitur manajemen acara di mana peserta (internal/eksternal) bisa mendaftar mandiri via link publik, lalu pada hari-H peserta melakukan konfirmasi kehadiran secara mandiri melalui pemindaian QR Code milik masing-masing, ditutup dengan pencetakan rekap daftar hadir format resmi.

## Spesifikasi

### Tujuan
Mengotomatisasi proses registrasi dan absensi kegiatan/acara sekolah. Menghilangkan proses absensi manual dan antrean panjang dengan memanfaatkan QR Code yang bisa discan oleh peserta secara mandiri, sekaligus memudahkan panitia (Admin) dalam menghasilkan laporan daftar hadir berformat PDF resmi (dengan kop surat dan format tabel standar tanda tangan zig-zag) yang siap ditandatangani Kepala Sekolah.

### Selesai bila
- Admin dapat membuat Kegiatan Acara baru dan mendefinisikan field tambahan untuk pendaftaran (walaupun Nama dan Instansi wajib ada).
- Tersedia link publik untuk pendaftaran mandiri oleh peserta (baik dari dalam maupun luar aplikasi).
- Admin dapat mencetak kartu QR Code untuk seluruh peserta (mirip format Pemilihan OSIS).
- Peserta dapat melakukan scan QR Code mereka sendiri dan menekan tombol "Hadir" pada hari-H acara.
- Sistem mencatat timestamp waktu kehadiran peserta.
- Admin dapat mencetak Daftar Hadir berformat PDF persis seperti template referensi (kop surat sistem, kolom No, Nama, Instansi, Tanda Tangan zig-zag, dan penutup TTD Kepala Sekolah di bawah).

## Sub-fitur: Manajemen Kegiatan & Pendaftaran

Admin membuat kegiatan, mengatur form, dan memantau pendaftar.

### Tujuan
Memberikan kebebasan bagi Admin untuk mengatur acara dan mengakomodasi kebutuhan data peserta yang berbeda-beda tiap acara (melalui form dinamis).

### Selesai bila
- Ada halaman tabel daftar kegiatan (CRUD).
- Form pembuatan kegiatan mencakup: Nama, Tema, Tanggal, Waktu, Tempat, dan seting JSON form pendaftaran.
- Ada halaman pendaftaran publik tanpa perlu login (bisa dibagikan link-nya) tempat peserta mengisi data.
- Data yang disubmit otomatis masuk sebagai peserta dengan status kehadiran 'belum'.

## Sub-fitur: Cetak QR & Konfirmasi Kehadiran

Proses hari-H absensi menggunakan QR Code per peserta.

### Tujuan
Mempercepat proses check-in peserta tanpa harus mengabsen satu per satu secara manual oleh panitia.

### Selesai bila
- Ada tombol cetak QR Code massal (grid/layout) untuk seluruh peserta terdaftar.
- Tiap QR Code menyimpan token unik peserta.
- Saat di-scan, QR mengarahkan ke halaman konfirmasi yang menampilkan nama acara dan tombol "Hadir".
- Setelah ditekan, status berubah menjadi 'hadir' dan mencatat `waktu_hadir`.

## Sub-fitur: Cetak Laporan Daftar Hadir (PDF)

Cetak rekap akhir untuk dokumentasi resmi.

### Tujuan
Memenuhi kebutuhan administrasi dokumentasi kehadiran dengan format baku, siap potong/cetak dan disahkan.

### Selesai bila
- PDF digenerate dengan menggunakan DOMPDF/library sejenis.
- Terdapat kop surat instansi yang terintegrasi dengan pengaturan aplikasi.
- Kolom Tanda Tangan digenerate urut nomor secara zig-zag (kiri-kanan) seperti form fisik.
- Di bagian bawah kanan laporan, terdapat blok pengesahan "Mengetahui, Kepala Sekolah ..." sesuai data sistem.

## Task

### 1. Buat halaman/view Manajemen Kegiatan dengan data tiruan
Bangun Blade view untuk list kegiatan dan detail pendaftar dengan data hardcode/dummy array dulu, tanpa query database.

### 2. Buat halaman pendaftaran publik & halaman cetak dengan data tiruan
Buat form registrasi publik, view cetak QR Code (CSS Grid/layout OSIS), dan view PDF Laporan (tabel zig-zag + kop) pakai data tiruan.

### 3. Buat halaman konfirmasi scan QR dengan data tiruan
Buat halaman konfirmasi setelah QR di-scan yang menampilkan detail nama peserta dan tombol "Hadir".

### 4. Integrasikan navigasi antar halaman/state
Pastikan tombol "Tambah Kegiatan", "Lihat Pendaftar", "Cetak QR", "Cetak PDF", dll terhubung dengan baik meski masih data dummy.

### 5. Poles tampilan dan responsivitas
Rapikan UI/UX agar responsif, pastikan preview PDF (layout cetak) tidak pecah halamannya, pakai font standard.

### 6. Buat migration & model Eloquent untuk tabel `event_kegiatans` dan `event_pesertas`
Sertakan `HasUuids`, `school_id` scope karena ini aplikasi multi-tenant, dan siapkan field JSON untuk custom form/biodata.

### 7. Buat controller + route untuk CRUD Kegiatan & Form Settings
Ganti data tiruan di view dengan query Eloquent asli.

### 8. Buat controller + route untuk Submit Pendaftaran (Publik)
Fungsikan form pendaftaran agar menyimpan data peserta dan di-generate `qr_token` uniknya.

### 9. Buat controller + route untuk Proses Konfirmasi Hadir (Scan QR)
Fungsikan halaman scan QR agar tombol "Hadir" meng-update status `status_kehadiran` dan `waktu_hadir` di DB.

### 10. Buat controller + route untuk Export PDF (Daftar Hadir)
Generate PDF beneran pakai DomPDF/snappy berdasarkan query daftar peserta yang sudah di-sort (contoh: berdasarkan status/waktu), pastikan kop surat dan ttd kepsek dinamis.

### 11. Tambahkan policy/authorization
Pastikan role Admin yang bisa kelola CRUD kegiatan dan cetak-mencetak laporan.

### 12. Buat seeder/factory
Isi data contoh (Kegiatan dan beberapa Peserta simulasi) untuk memudahkan testing fitur absensi hari-H.
