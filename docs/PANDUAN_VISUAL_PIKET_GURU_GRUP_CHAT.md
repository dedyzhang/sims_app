# Panduan Visual — Piket Guru dan Grup Chat

Dokumen ini menjadi acuan visual untuk review desktop dan mobile pada perubahan
Piket Guru, Grup Chat Kelas, Pengumuman, dan Private Chat yang terhubung dari
daftar anggota grup.

## 1. Arah Visual

### Prinsip utama

- Utamakan tindakan pagi hari: lihat masalah, pilih tindakan, dapatkan konfirmasi.
- Satu layar memiliki satu tindakan utama yang jelas.
- Bahasa antarmuka tetap Bahasa Indonesia.
- Status memakai warna dan teks, bukan warna saja.
- Tombol interaktif memiliki target sentuh minimum sekitar 44px.
- Teks panjang harus wrap atau truncate, tidak memaksa horizontal scroll.

### Bahasa visual

| Elemen | Gaya |
|---|---|
| Aksi utama | Indigo/primary, teks putih, radius besar |
| Menunggu | Slate netral |
| Ditugaskan | Amber |
| Selesai/terbit | Emerald |
| Peringatan penting | Rose atau amber dengan label teks |
| Grup Kelas | Indigo-purple |
| Siswa | Emerald-teal |
| Orang tua | Amber-orange |

## 2. Breakpoint Review

| Lebar | Target | Aturan layout |
|---|---|---|
| 320–375px | Android kecil | Satu kolom, tombol full-width, metadata boleh wrap |
| 376–767px | Mobile umum | Satu kolom, kartu vertikal, modal hampir full-width |
| 768–1023px | Tablet | Kartu tetap lega, grid tindakan dapat dua kolom |
| 1024px ke atas | Desktop | Header horizontal, tabel boleh scroll di dalam panel |

### Kriteria lulus

- Tidak ada scroll horizontal pada body halaman.
- Scroll horizontal hanya boleh berada di tabel jadwal yang memang lebih lebar.
- Composer chat tetap terlihat saat keyboard mobile dibuka sejauh yang didukung browser.
- Modal anggota dapat discroll sendiri tanpa menutup tombol header.
- Nama guru, kelas, dan nama file tidak merusak lebar kartu.
- Tombol aksi tidak berdempetan dan dapat ditekan tanpa zoom.

## 3. Piket Guru

### 3.1 Pengaturan Jadwal Piket

**Aktor:** Admin

- Desktop menampilkan tabel guru dengan kolom Senin–Jumat.
- Mobile mempertahankan tabel di dalam `overflow-x-auto`; halaman utama tidak ikut melebar.
- Setiap sel memiliki dua kontrol: `Piket` dan `Ketua`.
- Tombol `Simpan Jadwal` tetap terlihat di header dan memiliki state `Menyimpan...`.
- Sistem menolak penyimpanan jika salah satu hari belum memiliki ketua.

**Review visual:**

1. Pastikan nama guru tetap terbaca saat tabel digeser horizontal.
2. Pastikan radio `Ketua` terlihat disabled ketika guru tidak dipilih sebagai piket.
3. Pastikan pesan validasi tidak hanya mengandalkan warna toast.

### 3.2 Guru Tidak Hadir

- Header dan filter tanggal tersusun satu kolom di mobile.
- Kartu guru menampilkan sumber, alasan, dan jam kosong.
- Link menuju penugasan pengganti memakai target sentuh besar.
- Panel jam kosong menggunakan teks pendek: kelas, mata pelajaran, dan jam.

### 3.3 Penugasan Guru Pengganti

- Setiap slot ditampilkan sebagai kartu vertikal full-width.
- Urutan informasi: mata pelajaran, guru absen, kelas, jam, status, lalu tindakan.
- Dropdown kandidat memakai lebar penuh.
- Di mobile tombol `Tugaskan` dan `Saya yang masuk` tersusun vertikal.
- Di tablet/desktop tombol dapat berada dalam dua kolom.
- Setelah status berubah, kartu langsung memperbarui label tanpa reload penuh.

### 3.4 Tugas Saya

- Form laporan ketidakhadiran satu kolom di mobile dan dua kolom di desktop.
- Metadata kelas dan jam memakai flex-wrap agar nama panjang tidak meluber.
- Judul, instruksi, dan file tersusun vertikal di setiap kartu.
- Tombol kirim full-width.
- Status dibedakan dengan label: belum dikirim, sudah dikonfirmasi, dan sudah diterbitkan ke siswa.

## 4. Grup Chat Kelas

### 4.1 Header dan Riwayat

- Header grup menggunakan nama yang truncate, jumlah anggota, dan status grup.
- Area pesan mengisi tinggi layar yang tersedia dan hanya area ini yang scroll.
- Riwayat lama dimuat lewat tombol `Muat pesan sebelumnya`, bukan memuat seluruh percakapan sekaligus.
- Polling memakai cursor batch agar backlog besar tidak melewati pesan.

### 4.2 Bubble Pesan

- Bubble milik user berada di kanan, pesan anggota lain di kiri.
- Lebar bubble maksimal 85% mobile dan 75% desktop.
- Teks panjang memakai `break-words` dan tidak membuat body melebar.
- Aksi balas/hapus selalu dapat disentuh di mobile; di desktop tampil saat hover.
- Lampiran file memiliki nama yang truncate dan target unduh yang jelas.

### 4.3 Mode Pengumuman

- Wali kelas dapat mengirim pesan baru.
- Siswa/orang tua melihat instruksi bahwa mereka hanya dapat membalas pesan wali kelas.
- Pesan penting memiliki label dan warna peringatan yang konsisten.
- Composer terkunci sampai user memilih pesan yang boleh dibalas.

### 4.4 Modal Anggota

- Modal memakai `max-w-sm`, `max-h-[80vh]`, dan area daftar yang scroll sendiri.
- Daftar anggota diurutkan alfabetis.
- Presence ditampilkan sebagai `Online`, `Baru saja aktif`, atau `Tidak aktif`; timestamp mentah tidak ditampilkan.
- Wali kelas mendapat tautan Private Chat hanya untuk siswa di Grup Kelas atau orang tua di Grup Paguyuban yang sama.

## 5. Private Chat

- Layout chat memiliki header, area pesan, dan composer yang selalu tersusun vertikal.
- Bubble pesan dibatasi maksimal 85% lebar layar.
- Tombol emoji dan kirim berukuran tetap 44px.
- Panel emoji tidak boleh keluar dari viewport mobile.
- Pesan kosong tidak dapat dikirim.
- Akses percakapan ditentukan oleh relasi wali kelas dengan anggota grup, bukan hanya UUID di URL.

## 6. Checklist UAT Visual

- [ ] `/piket` pada 360px: tabel dapat digeser, body tidak melebar.
- [ ] `/piket/penugasan` pada 360px: dropdown dan dua tombol tersusun vertikal.
- [ ] `/piket/tugas/saya` pada 360px: metadata kelas/jam wrap dengan benar.
- [ ] `/grup/{grup}` pada 360px: bubble panjang tidak membuat scroll horizontal.
- [ ] Modal anggota pada 360px: daftar panjang dapat discroll.
- [ ] Private Chat pada 360px: composer dan panel emoji tidak terpotong.
- [ ] Semua halaman pada 1280px: konten tidak terlalu melebar dan tetap memiliki whitespace.
- [ ] State loading, error, empty, dan success dapat dibedakan tanpa melihat warna saja.
- [ ] Navigasi keyboard desktop tetap mencapai tombol utama dan tombol modal.

## 7. Batasan Verifikasi

Review ini mencakup inspeksi class Tailwind/Blade, test feature, dan build asset.
