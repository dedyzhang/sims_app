# Panduan Penggunaan SIMS App

## 1. Alur Awal Penggunaan

1. Buka aplikasi, halaman `/` otomatis diarahkan ke `/login`.
2. Masuk memakai nama pengguna dan kata sandi.
3. Jika akun diwajibkan mengganti kata sandi, ikuti halaman `Keamanan Akun`.
4. Jika tersedia, pengguna dapat masuk memakai PIN 6 digit atau pemindai wajah pada perangkat Anda.
5. Untuk siswa/guru/staf yang terkena persyaratan wajib pemindaian wajah, buka `Wajah Saya`, ambil beberapa sampel wajah, lalu simpan.
6. Masuk ke `Dashboard`.
7. Atur profil dan tampilan lewat menu tarik-turun profil kanan atas.

## 2. Masuk, Keamanan Akun, dan Profil

### Masuk

![Tampilan Halaman Masuk (\/Login)](/images/panduan/login.png)

Digunakan oleh semua pengguna.
Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `/login`.
2. Masukkan nama pengguna (username) dan kata sandi (password) Anda dengan benar.
3. Klik tombol "Masuk" untuk melanjutkan ke dalam aplikasi.
4. Jika memakai PIN, pilih masuk PIN dan masukkan PIN 6 digit.
5. Jika lupa kata sandi, gunakan formulir permintaan reset kata sandi.

Catatan:

- Masuk kata sandi dan PIN memakai sistem pembatasan waktu tunggu (jeda) otomatis jika salah memasukkan kata sandi berkali-kali untuk mencegah percobaan pembobolan paksa.
- Reset kata sandi diminta dari halaman masuk, lalu diproses admin/wali kelas sesuai fitur reset akun.

### Keluar

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Klik avatar/profil di kanan atas.
2. Pilih `Keluar`.

### Ganti Nama pengguna dan Kata sandi

Untuk mengakses fitur ini, silakan buka menu **menu tarik-turun profil > `Profil` atau halaman `Keamanan Akun`.** pada bilah navigasi (sidebar).

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Ganti Password`.
2. Ubah nama pengguna jika diminta.
3. Masukkan kata sandi lama dan kata sandi baru.
4. Klik `Simpan Password`.

### Set PIN Masuk

Untuk mengakses fitur ini, silakan buka menu **halaman `Ganti PIN`.** pada bilah navigasi (sidebar).

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Set PIN Login`.
2. Isi PIN 6 digit.
3. Simpan.
4. Setelah aktif, pengguna bisa masuk cepat dengan PIN.

### Pemindai wajah pada perangkat Anda

Fitur: masuk/register WebAuthn.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Masuk dulu dengan akun normal.
2. Daftarkan perangkat biometrik/passkey jika antarmuka aplikasi tersedia.
3. Saat masuk berikutnya, gunakan autentikasi perangkat.

### Profil

Untuk mengakses fitur ini, silakan buka menu **menu tarik-turun profil > `Profil`.** pada bilah navigasi (sidebar).

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Profil`.
2. Klik sunting profil.
3. Sesuaikan data yang tersedia.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

### Preferensi Tampilan

Untuk mengakses fitur ini, silakan buka menu **menu tarik-turun profil > `Tampilan`.** pada bilah navigasi (sidebar).

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Pilih warna utama, warna sekunder, aksen, sidebar, ukuran font, motif, dan mode antarmuka aplikasi.
2. Simpan preferensi.
3. Gunakan reset jika ingin kembali ke bawaan.
4. Tombol gaya cepat dapat mengganti gaya `Soft` dan `Analyzer`.

## 3. Dasbor dan Notifikasi

### Dasbor

![Tampilan Dasbor Utama](/images/panduan/dashboard.png)

Untuk mengakses fitur ini, silakan buka menu **`Dashboard`.** pada bilah navigasi (sidebar).
Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Menampilkan ringkasan sesuai peran.
- Menampilkan gawit siswa/guru/kesiswaan/kurikulum/sarpras/keuangan sesuai akses.
- Menampilkan teks berjalan statistik waktu nyata.
- Mendukung tata letak gawit yang bisa disimpan.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Dashboard`.
2. Pantau kartu statistik dan daftar aktivitas.
3. Jika gawit bisa diatur, seret/lepas tata letak.
4. Simpan tata letak dasbor.

### Notifikasi

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Menampilkan notifikasi pengumuman, forum, ruang kelas, dan sarpras.
- Mendukung bunyi notifikasi.
- Mendukung notifikasi langsung pada perangkat telepon pintar (Android) Anda.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Klik ikon notifikasi di header.
2. Klik notifikasi untuk membuka target.
3. Gunakan `Tandai semua dibaca` jika ingin membersihkan lencana.
4. Aktif/nonaktifkan suara notifikasi bila tersedia.

## 4. Pengumuman

![Halaman Pengumuman](/images/panduan/pengumuman.png)

Untuk mengakses fitur ini, silakan buka menu **`Pengumuman`.** pada bilah navigasi (sidebar).

- Semua pengguna dapat melihat pengumuman sesuai target peran.
- Pengguna dengan permission `manage_pengumuman` dapat membuat, sunting, dan hapus.

Langkah-langkah untuk melihat daftar pengumuman:

1. Silakan navigasikan ke menu `Pengumuman`.
2. Pilih pengumuman dari daftar.
3. Baca rincian pengumuman.

Langkah-langkah untuk membuat pengumuman baru:

1. Silakan navigasikan ke menu `Pengumuman`.
2. Klik `Buat`.
3. Isi judul, isi pengumuman, dan target peran.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.
5. Sistem mengirim notifikasi ke pengguna sasaran.

Langkah-langkah untuk mengubah atau menghapus data:

1. Silakan buka halaman atau menu rincian atau daftar pengumuman.
2. Klik sunting untuk mengubah.
3. Klik hapus jika pengumuman tidak dipakai lagi.

## 5. Asisten Sekolah dan Obrolan

### Bot obrolan Asisten Sekolah untuk Pengguna

- Semua peran non-admin dapat memakai gawit penanya.
- Admin/superadmin memakai Inbox Admin.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Klik floating obrolan atau buka `/chatbot`.
2. Ketik pertanyaan.
3. Kirim gambar atau berkas jika diperlukan.
4. Klik `Hubungkan ke Admin` jika butuh bantuan manusia.
5. Klik `Kembali ke Bot` jika ingin percakapan kembali dijawab bot.

Fitur:

- TANYA JAWAB cepat.
- Alih tugas ke admin.
- Unggah gambar dan berkas.
- pemeriksaan pesan masuk secara otomatis.
- Lencana unread.

### Inbox Admin Bot obrolan

Untuk mengakses fitur ini, silakan buka menu **`Chat / Inbox`.** pada bilah navigasi (sidebar).

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Chat / Inbox`.
2. Lihat percakapan masuk dan antrean.
3. Pilih percakapan.
4. Klik tugaskan jika ingin mengambil percakapan.
5. Balas dengan teks, gambar, atau berkas.
6. Gunakan `Kembalikan ke Bot` jika pengguna tidak perlu admin lagi.
7. Klik `Tutup` untuk menyelesaikan percakapan.
8. Atur avatar, pesan notifikasi, dan pertanyaan cepat lewat pengaturan inbox.

### Asisten Guru Global

Fitur:

- Obrolan AI masuk pengguna.
- Riwayat percakapan per pengguna.
- Hapus percakapan.
- Bisa memakai grounding Google Search sesuai niat.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan buka halaman atau menu gawit/fitur Asisten Guru global jika tampil.
2. Ketik pertanyaan.
3. Buka riwayat jika ingin melanjutkan percakapan lama.
4. Anda juga dapat menghapus percakapan yang tidak diperlukan. jika data tersebut tidak lagi diperlukan.

### Fitur Asisten Guru

Untuk mengakses fitur ini, silakan buka menu **`Akademik > Asisten Guru`.** pada bilah navigasi (sidebar). Menu ini tampil untuk guru dan wali kelas.

Fitur ini membantu guru menyusun dokumen pembelajaran lebih cepat, tetapi hasilnya tetap perlu ditinjau sebelum dibagikan ke siswa.

Fitur utama:

- Generator Soal untuk membuat soal dari topik atau file materi.
- RPM Learning untuk menyusun rancangan pembelajaran dari topik atau file materi.
- Rangkuman Materi untuk merangkum bahan ajar menjadi poin penting.
- Draft Feedback untuk membuat komentar/umpan balik siswa.
- Pratinjau hasil sebelum diunduh.
- Ekspor hasil soal atau RPM ke Word/PDF jika tersedia.
- Riwayat generate agar guru bisa membuka kembali hasil sebelumnya.

Cara membuat soal:

1. Silakan navigasikan ke menu `Akademik > Asisten Guru`.
2. Pilih tab `Generator Soal`.
3. Pilih sumber: generate dari topik atau unggah file materi.
4. Isi topik, tingkat kesulitan, jumlah soal, dan jenis soal yang dibutuhkan.
5. Klik `Buat Soal`.
6. Tinjau hasil soal dan kunci jawaban.
7. Jika sudah sesuai, gunakan tombol pratinjau atau ekspor Word/PDF.

Cara membuat RPM Learning:

1. Pilih tab `RPM Learning`.
2. Isi topik/judul RPM atau unggah file materi.
3. Lengkapi jenjang, kelas, mata pelajaran, dan fokus pembelajaran jika tersedia.
4. Klik `Buat RPM Learning`.
5. Tinjau struktur dokumen: identitas, DPL, tujuan, kegiatan, asesmen, refleksi, lampiran, dan sumber belajar.
6. Ekspor ke Word/PDF jika dokumen sudah siap digunakan.

Cara merangkum materi:

1. Pilih tab `Rangkuman Materi`.
2. Masukkan teks materi atau unggah bahan yang didukung.
3. Klik buat rangkuman.
4. Periksa hasil ringkasan sebelum dipakai sebagai bahan ajar.

Cara membuat draft feedback siswa:

1. Pilih tab `Draft Feedback`.
2. Isi nama siswa jika diperlukan.
3. Isi konteks nilai, perilaku, tugas, atau catatan guru.
4. Klik buat feedback.
5. Sunting kalimat agar sesuai dengan kondisi siswa sebenarnya.

Cara memakai riwayat hasil:

1. Lihat panel `History Generate` di halaman Asisten Guru.
2. Pilih hasil lama untuk dibaca ulang.
3. Hapus riwayat yang tidak diperlukan.

Catatan penggunaan:

- Jangan memasukkan data pribadi sensitif yang tidak diperlukan.
- Hasil AI bisa keliru, jadi guru wajib meninjau ulang isi, angka, kunci jawaban, dan kesesuaian kurikulum.
- Untuk soal atau RPM resmi, lakukan koreksi manual sebelum dicetak atau dibagikan.

### Analisis AI

Untuk mengakses fitur ini, silakan buka menu **`Analisis AI > Narasi Data AI`.** pada bilah navigasi (sidebar).

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Narasi Data AI`.
2. Pilih jenis analisis: nilai, absensi, atau keuangan.
3. Isi penyaring/periode jika tersedia.
4. Jalankan analisis.
5. Gunakan narasi sebagai bahan evaluasi, bukan pengganti keputusan final.

### Dokumen AI (Pencarian Referensi)

Untuk mengakses fitur ini, silakan buka menu **`Analisis AI > Dokumen AI`.** pada bilah navigasi (sidebar).

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Dokumen AI`.
2. Unggah dokumen.
3. Tunggu dokumen diproses.
4. Ajukan pertanyaan berdasarkan isi dokumen.
5. Baca jawaban dan sitasi.
6. Hapus dokumen yang tidak diperlukan.

## 6. Data Master

Untuk mengakses fitur ini, silakan buka menu **`Data Master`.** pada bilah navigasi (sidebar).

### Data Guru

![Halaman Data Guru](/images/panduan/data_guru.png)


Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Tambah, sunting, lihat, hapus guru.
- Reset akun guru.
- Impor guru dari Excel.
- Unduh templat impor.
- Unduh kredensial hasil impor.
- Atur penugasan mengajar guru per mapel/kelas.
- Simpan dan hapus data wajah guru.

Langkah-langkah untuk menambahkan data guru baru:

1. Silakan navigasikan ke menu `Data Master > Data Guru`.
2. Klik tambah guru.
3. Isi identitas guru.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

Langkah-langkah untuk mengimpor data guru secara massal:

1. Silakan navigasikan ke menu `Data Guru > Import`.
2. Unduh templat.
3. Isi berkas sesuai format.
4. Unggah berkas.
5. Setelah impor, unduh kredensial guru baru.

Langkah-langkah untuk mengatur jadwal mengajar:

1. Silakan buka halaman atau menu rincian atau aksi `Pelajaran` pada guru.
2. Pilih kelas dan mata pelajaran.
3. Simpan penugasan.
4. Anda juga dapat menghapus penugasan jika tidak berlaku. jika data tersebut tidak lagi diperlukan.

Langkah-langkah untuk melakukan reset akun guru:

1. Silakan buka halaman atau menu daftar/rincian guru.
2. Klik reset.
3. Salin kredensial baru yang muncul.

### Data Siswa

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Tambah, sunting, lihat, hapus siswa.
- Membuat akun siswa dan orang tua.
- Reset akun siswa dan orang tua.
- Impor siswa dari Excel.
- Unduh templat impor.
- Unduh kredensial hasil impor.
- Simpan dan hapus data wajah siswa.

Langkah-langkah untuk menambahkan data siswa baru:

1. Silakan navigasikan ke menu `Data Master > Data Siswa`.
2. Klik tambah siswa.
3. Isi identitas, kelas, dan data orang tua.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

Langkah-langkah untuk mengimpor data siswa secara massal:

1. Silakan navigasikan ke menu `Data Siswa > Import`.
2. Unduh templat.
3. Isi berkas Excel sesuai panduan.
4. Unggah dan impor.
5. Unduh kredensial hasil impor.

Langkah-langkah untuk melakukan reset akun:

1. Silakan buka halaman atau menu data siswa.
2. Klik reset akun siswa atau reset akun orang tua.
3. Salin kredensial baru.

### Data Kelas

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Tambah, sunting, hapus kelas.
- Atur wali kelas.
- Set rombel siswa.
- Melihat histori rombel.
- Hapus histori rombel.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Data Master > Data Kelas`.
2. Tambahkan kelas jika belum ada.
3. Pilih aksi wali kelas untuk menetapkan guru wali.
4. Gunakan `Set Kelas` untuk memasukkan siswa ke rombel.
5. Cek `Histori Rombel` untuk riwayat perpindahan kelas.

### Mata Pelajaran

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Tambah mata pelajaran.
- Sunting mata pelajaran.
- Hapus mata pelajaran.
- Pengurutan urutan mapel.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Data Master > Mata Pelajaran`.
2. Tambahkan mapel dari formulir/modal.
3. Sunting nama/atribut mapel bila perlu.
4. Drag/urutkan jika antarmuka aplikasi pengurutan tersedia.
5. Simpan urutan.

### Kartu Pelajar Admin

Untuk mengakses fitur ini, silakan buka menu **`Data Master > Kartu Pelajar`.** pada bilah navigasi (sidebar).

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Kelola kartu pelajar digital per siswa.
- Unggah berkas kartu kustom.
- Lihat kartu siswa.
- Hapus kartu.
- Cetak massal kartu per tingkat.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Kartu Pelajar`.
2. Penyaring siswa/tingkat jika tersedia.
3. Unggah kartu untuk siswa tertentu atau gunakan buat bawaan.
4. Klik lihat untuk preview.
5. Klik cetak untuk cetak massal per tingkat.

## 7. Absensi dan Presensi

Untuk mengakses fitur ini, silakan buka menu **`Absensi & Presensi`.** pada bilah navigasi (sidebar).

### Kalender Absensi

![Tampilan Kalender Absensi](/images/panduan/kalender_absensi.png)


Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Menentukan hari efektif/non-efektif.
- Alih tanggal satu per satu.
- Massal set banyak tanggal.
- Atur mode kalender.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Kalender Absensi`.
2. Pilih tanggal.
3. Alih status hari.
4. Gunakan massal untuk rentang tanggal.
5. Simpan mode kalender jika tersedia.

### Absensi Siswa Manual

- Admin/pengelola absensi.
- Wali kelas untuk kelasnya.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Absensi Siswa`.
2. Pilih kelas dan tanggal.
3. Tandai status tiap siswa.
4. Klik `Simpan Absensi`.
5. Buka `Rekap Absensi` untuk rekap per rentang tanggal.

### Rekap Absensi Siswa

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Rekap Absensi`.
2. Pilih kelas dan rentang tanggal.
3. Lihat rekap kehadiran.
4. Gunakan hasilnya untuk evaluasi wali kelas/kesiswaan.

### Registrasi dan Validasi Wajah

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Registrasi wajah siswa/guru.
- Galeri wajah.
- Deteksi wajah ganda.

Berikut adalah langkah-langkah penggunaan khusus untuk Admin:

1. Silakan navigasikan ke menu `Validasi Wajah`.
2. Pilih siswa/guru yang belum punya wajah.
3. Ambil beberapa sampel wajah dari sudut berbeda.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.
5. Buka `Wajah Galeri` untuk tinjauan data wajah.
6. Buka `Wajah Ganda` untuk memeriksa potensi wajah duplikat.

Berikut adalah langkah-langkah penggunaan untuk pengguna umum:

1. Silakan navigasikan ke menu `Wajah Saya`.
2. Izinkan kamera.
3. Ambil sampel wajah.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

### Absensi Wajah Kiosk

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Pindai wajah lewat komputer/piket.
- Tautan kiosk publik memakai token rahasia.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Admin buka `Pengaturan > Absensi`.
2. Buat token kiosk.
3. Pasang tautan `/kiosk-absensi/{token}` di komputer piket.
4. Guru/siswa melakukan pindai wajah di perangkat tersebut.

### QR Absensi

Menu admin: `QR Absensi`.

Menu pengguna: `Absen QR`.

Berikut adalah langkah-langkah penggunaan khusus untuk Admin:

1. Silakan navigasikan ke menu `QR Absensi`.
2. Tampilkan QR harian di layar/cetak.
3. Pastikan lokasi QR sudah diatur di pengaturan.

Langkah-langkah untuk Siswa dan Guru:

1. Silakan navigasikan ke menu `Absen QR`.
2. Pindai QR harian.
3. Izinkan lokasi.
4. Kirim absen.

### Presensi Guru

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Presensi guru manual.
- Pindai wajah guru.
- Rekap presensi guru.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Presensi Guru`.
2. Input presensi manual jika diperlukan.
3. Gunakan `Scan` untuk presensi wajah.
4. Buka `Rekap` untuk laporan rentang tanggal.

## 8. Akademik

### Ruang Kelas

![Tampilan Ruang Kelas Akademik](/images/panduan/ruang_kelas.png)


Untuk mengakses fitur ini, silakan buka menu **`Akademik > Ruang Kelas`.** pada bilah navigasi (sidebar).

- Admin/kepala/kurikulum/guru/siswa sesuai policy.
- Orang tua tidak masuk menu Ruang Kelas.

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Navigasi kelas dan mapel.
- Penyediaan otomatis ruang kelas per kelas/mapel.
- Materi.
- Tugas.
- Komentar.
- Pengumpulan tugas.
- Penilaian pengumpulan.
- Transfer nilai tugas ke modul nilai.
- Lock materi/tugas dengan token.
- Pemantauan lock peristiwa.
- Tautan meet dan tutup meet.

Langkah-langkah bagi Guru untuk membuat materi pembelajaran:

1. Silakan navigasikan ke menu `Ruang Kelas`.
2. Pilih kelas.
3. Pilih mata pelajaran.
4. Klik buat materi.
5. Isi judul, konten, berkas/tautan, dan opsi lock jika diperlukan.
6. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

Langkah-langkah bagi Guru untuk membuat tugas:

1. Silakan buka halaman atau menu ruang mapel.
2. Klik buat tugas.
3. Isi instruksi, tenggat waktu, berkas pendukung, dan opsi lock.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

Langkah-langkah bagi Siswa untuk membaca materi:

1. Silakan navigasikan ke menu `Ruang Kelas`.
2. Pilih kelas/mapel.
3. Klik materi.
4. Jika terkunci, masukkan token dari guru.
5. Unduh/preview berkas bila tersedia.
6. Tulis komentar jika dibuka.

Langkah-langkah bagi Siswa untuk mengumpulkan tugas:

1. Silakan buka halaman atau menu tugas.
2. Baca instruksi.
3. Unggah berkas jawaban atau isi jawaban sesuai formulir.
4. Klik kumpulkan.

Langkah-langkah bagi Guru untuk menilai tugas yang dikumpulkan:

1. Silakan buka halaman atau menu rincian tugas.
2. Pilih menu penilaian/pengumpulan.
3. Beri nilai dan umpan balik.
4. Kembalikan pengumpulan jika perlu revisi.
5. Gunakan transfer nilai untuk memasukkan nilai ke buku nilai.

### Jadwal Pelajaran

Menu admin/kurikulum: `Akademik > Jadwal Pelajaran`.

Menu guru/staf: `Jadwal Mengajar`.

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Editor kisi jadwal per hari.
- Jadwal kelas.
- Jadwal guru.
- Master jam pelajaran.
- Buat jadwal.
- Salin jam.
- Hapus jam.

Langkah-langkah untuk Admin atau Kurikulum:

1. Silakan navigasikan ke menu `Jadwal Pelajaran`.
2. Atur master jam di menu JP/jam.
3. Pilih kelas/hari.
4. Isi sel jadwal dengan mapel dan guru.
5. Simpan sel.
6. Gunakan buat jika ingin jadwal otomatis.
7. Cek tampilan jadwal per kelas.

Langkah-langkah khusus untuk Guru:

1. Silakan navigasikan ke menu `Jadwal Mengajar`.
2. Lihat jadwal mengajar berdasarkan penugasan.

### Penilaian / Buku Guru

![Tampilan Buku Nilai Guru](/images/panduan/penilaian.png)


Untuk mengakses fitur ini, silakan buka menu **`Akademik > Penilaian` atau `Buku Guru`.** pada bilah navigasi (sidebar).

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Daftar penugasan mengajar.
- KKTP per penugasan.
- Materi dan tujuan pembelajaran.
- Nilai formatif.
- Nilai sumatif.
- Nilai penjabaran.
- Nilai PTS.
- Nilai PAS.
- Nilai rapor.
- Deskripsi rapor.
- Konfirmasi/batal konfirmasi rapor.

Langkah-langkah bagi Guru untuk mengisi nilai akhir:

1. Silakan navigasikan ke menu `Buku Guru` atau `Penilaian`.
2. Pilih kelas/mapel yang diajar.
3. Atur KKTP jika belum.
4. Buat materi dan tujuan pembelajaran.
5. Masuk ke halaman bagian formatif/sumatif/penjabaran/PTS/PAS.
6. Isi nilai pada sel siswa.
7. Cek nilai rapor.
8. Isi deskripsi rapor.
9. Konfirmasi rapor jika final.

Langkah-langkah untuk Admin atau Kurikulum:

1. Silakan navigasikan ke menu `Penilaian`.
2. Pilih guru/kelas/mapel.
3. Pantau progres nilai.
4. Buka rekap untuk melihat lintas kelas/mapel.

### Nilai Saya

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Akademik > Nilai Saya`.
2. Pilih semester jika tersedia.
3. Lihat nilai per mapel.
4. Orang tua melihat nilai anak sesuai relasi akun.

### Rekap Nilai

Untuk mengakses fitur ini, silakan buka menu **`Akademik > Rekap Nilai`.** pada bilah navigasi (sidebar).

- Admin/peran dengan `manage_rapor`.
- Wali kelas untuk kelasnya.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Rekap Nilai`.
2. Pilih kelas/semester/penyaring.
3. Lihat rekap nilai siswa.
4. Gunakan sebagai dasar cetak rapor.

### Cetak Rapor

Untuk mengakses fitur ini, silakan buka menu **`Akademik > Cetak Rapor`.** pada bilah navigasi (sidebar).

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Cetak Rapor`.
2. Pilih kelas dan siswa atau parameter cetak.
3. Pastikan nilai sudah dikonfirmasi.
4. Klik cetak.
5. Periksa tampilan PDF/cetak.

### Ekstrakurikuler

Untuk mengakses fitur ini, silakan buka menu **`Akademik > Ekstrakurikuler`.** pada bilah navigasi (sidebar).

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Kelola ekskul.
- Nilai/deskripsi ekskul.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Ekstrakurikuler`.
2. Admin menambah/mengubah/menghapus ekskul.
3. Guru/pembina membuka nilai ekskul.
4. Isi nilai/deskripsi per siswa.
5. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

### Perangkat Ajar

Menu admin: `Akademik > Perangkat Ajar`.

Menu guru: `Akademik > Perangkat Ajar`.

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Master jenis perangkat ajar.
- Guru unggah berkas perangkat ajar.
- Preview/unduh berkas.
- Hapus berkas.
- Unduh berkas terkompresi per guru.

Cara admin:

1. Silakan navigasikan ke menu `Perangkat Ajar`.
2. Tambah jenis dokumen, misalnya Modul Ajar, Prota, Prosem, RPP.
3. Pilih guru.
4. Pantau berkas yang sudah/belum diupload.
5. Unduh berkas terkompresi jika butuh arsip.

Langkah-langkah khusus untuk Guru:

1. Silakan navigasikan ke menu `Perangkat Ajar`.
2. Pilih jenis dokumen.
3. Unggah berkas.
4. Preview atau unduh untuk cek.
5. Hapus dan unggah ulang jika salah.

## 9. Agenda Guru

Untuk mengakses fitur ini, silakan buka menu **`Agenda`.** pada bilah navigasi (sidebar).

### Agenda Guru

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Mengisi agenda mengajar.
- Memilih slot jadwal berdasarkan tanggal.
- Memuat daftar siswa per jadwal.
- Mencatat ketidakhadiran siswa.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Agenda > Agenda Guru`.
2. Klik `Tambah Agenda`.
3. Pilih tanggal.
4. Pilih slot/jadwal mengajar.
5. Isi materi, kegiatan, catatan, dan absensi jika ada.
6. Simpan agenda.
7. Sunting atau hapus agenda jika belum final.

### Rekap Agenda

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Agenda > Rekap Agenda`.
2. Penyaring guru/tanggal/kelas jika tersedia.
3. Tinjauan agenda.
4. Beri validasi dan catatan.
5. Simpan validasi.

### Buku Batas

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Rekap batas pelajaran per kelas/rentang tanggal.
- Ekspor Excel.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Agenda > Buku Batas`.
2. Pilih kelas dan rentang tanggal.
3. Klik tampilkan.
4. Klik `Unduh Excel` jika perlu arsip.

## 10. Kedisiplinan: Poin atau P3

Aplikasi memiliki dua sistem disiplin, dipilih dari `Pengaturan > Sistem > Jenis Aturan`.

### Sistem Poin

Untuk mengakses fitur ini, silakan buka menu **`Poin & Aturan`.** pada bilah navigasi (sidebar).

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Master aturan poin.
- Impor/ekspor aturan.
- Ledger poin siswa.
- Pengajuan poin oleh guru/wali kelas/sekretaris.
- Approval pengajuan.
- Dasbor kedisiplinan.
- Poin Saya untuk siswa/orang tua.

Cara admin/kesiswaan mengelola aturan:

1. Silakan navigasikan ke menu `Master Aturan`.
2. Tambah aturan poin.
3. Isi jenis, kategori, nilai poin, dan keterangan.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.
5. Gunakan impor/ekspor jika banyak aturan.

Cara input poin langsung:

1. Silakan navigasikan ke menu `Poin Siswa`.
2. Pilih siswa.
3. Klik tambah entri.
4. Pilih aturan.
5. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

Cara guru/sekretaris mengajukan poin:

1. Silakan navigasikan ke menu `Ajukan Poin`.
2. Pilih siswa.
3. Pilih aturan dan isi catatan.
4. Kirim pengajuan.

Cara admin memproses pengajuan:

1. Silakan navigasikan ke menu `Pengajuan Poin`.
2. Pilih pengajuan.
3. Setujui, tolak, atau proses massal.
4. Cek riwayat pengajuan.

Cara siswa/orang tua:

1. Silakan navigasikan ke menu `Poin Saya`.
2. Lihat saldo/riwayat poin.

### Sistem P3

Untuk mengakses fitur ini, silakan buka menu **`P3 Kedisiplinan`.** pada bilah navigasi (sidebar).

P3 mencakup Pelanggaran, Prestasi, dan Partisipasi.

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Master kategori P3.
- Data P3 siswa.
- Pengajuan P3.
- Approval/disapproval pengajuan.
- Cetak laporan P3 per siswa.
- P3 Saya untuk siswa/orang tua.

Cara admin/kesiswaan:

1. Silakan navigasikan ke menu `Master Kategori`.
2. Tambah kategori P3.
3. Buka `P3 Siswa`.
4. Pilih siswa.
5. Tambah entri P3 atau sunting entri.
6. Cetak laporan jika diperlukan.

Cara guru/sekretaris:

1. Silakan navigasikan ke menu `Ajukan P3`.
2. Pilih siswa.
3. Pilih kategori.
4. Isi catatan/bukti.
5. Kirim pengajuan.

Cara approval:

1. Silakan navigasikan ke menu `Pengajuan P3`.
2. Tinjauan rincian.
3. Klik approve atau disapprove.
4. Cek riwayat.

Cara siswa/orang tua:

1. Silakan navigasikan ke menu `P3 Saya`.
2. Lihat riwayat prestasi, partisipasi, dan pelanggaran.

## 11. Wali Kelas

Untuk mengakses fitur ini, silakan buka menu **`Wali Kelas`.** pada bilah navigasi (sidebar).

### Data Siswa Kelas

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Wali Kelas > Data Siswa Kelas`.
2. Lihat daftar siswa di kelas.
3. Klik rincian siswa untuk melihat profil.
4. Reset akun siswa/orang tua jika dibutuhkan.

### Set Sekretaris

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Menentukan siswa yang boleh mengajukan Poin/P3 sebagai sekretaris kelas.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Set Sekretaris`.
2. Pilih siswa yang menjadi sekretaris.
3. Simpan.

### Absensi Kelas

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Absensi Kelas`.
2. Pilih tanggal.
3. Isi status kehadiran siswa.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

### Rekap Absensi Kelas

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Rekap Absensi Kelas`.
2. Pilih rentang tanggal.
3. Tinjauan rekap.

### Poin/P3 Siswa Kelas

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan buka halaman atau menu menu Poin/P3 siswa kelas.
2. Pilih siswa.
3. Lihat riwayat kedisiplinan.

### Nilai Kelas Saya

Catatan: menu ini tampil jika pengaturan `walikelas_lihat_nilai` aktif.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Nilai Kelas Saya`.
2. Pilih siswa/mapel jika tersedia.
3. Tinjauan nilai kelas.

## 12. Forum Diskusi

Untuk mengakses fitur ini, silakan buka menu **`Forum Diskusi`.** pada bilah navigasi (sidebar).

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Topik forum.
- Komentar.
- Reaksi.
- Pin topik.
- Lock topik.
- Komentar terbaik.
- Presence peserta.
- Pengaturan akses forum.

Cara membaca forum:

1. Silakan navigasikan ke menu `Forum Diskusi`.
2. Pilih kategori/topik.
3. Baca diskusi.

Cara membuat topik:

1. Klik `Buat`.
2. Pilih kategori, audience, kelas jika relevan.
3. Isi judul dan konten.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

Cara berkomentar:

1. Silakan buka halaman atau menu topik.
2. Isi komentar.
3. Kirim.
4. Sunting/hapus komentar jika memiliki izin.

Cara moderator/admin:

1. Pin/unpin topik penting.
2. Lock/unlock topik.
3. Tandai komentar terbaik.
4. Anda juga dapat menghapus topik/komentar yang tidak sesuai. jika data tersebut tidak lagi diperlukan.
5. Atur matriks akses melalui `Forum > Akses`.

## 13. Sarana dan Prasarana (Sarpras)

Untuk mengakses fitur ini, silakan buka menu **`Sarana & Prasarana`.** pada bilah navigasi (sidebar).

- Pengelola: superadmin, admin, sapras.
- Staff/guru/siswa/peran tertentu: bisa melihat denah, lapor kerusakan, dan peminjaman sesuai Gate.

### Dasbor Sarpras

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Dashboard Sarpras`.
2. Pantau jumlah aset, kondisi, laporan kerusakan, peminjaman, pengadaan, dan aktivitas.

### Denah Interaktif

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Kelola denah gedung/lantai.
- Unggah/impor gambar denah.
- Gambar denah manual di kanvas.
- Atur hotspot ruangan.
- Tambah/impor ruangan.
- Sunting posisi ruangan.
- Ekspor denah JPEG/PDF dari antarmuka aplikasi.

Cara membuat denah:

1. Silakan navigasikan ke menu `Denah Interaktif`.
2. Klik tambah lantai/denah.
3. Isi nama gedung/lantai.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.
5. Impor gambar denah atau gambar manual.
6. Buka editor hotspot.
7. Tambahkan ruangan dan posisikan di denah.

Cara impor ruangan:

1. Silakan buka halaman atau menu rincian denah.
2. Klik impor ruangan.
3. Unduh templat.
4. Isi Excel/CSV.
5. Unggah dan proses impor.

Cara melihat ruangan:

1. Silakan buka halaman atau menu denah.
2. Klik hotspot ruangan.
3. Lihat rincian ruangan dan aset di ruangan.

### Maintenance Lapor

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Lapor kerusakan aset/ruangan.
- Unggah foto.
- Pengelola menerima atau menolak laporan.

Cara pengguna melapor:

1. Silakan navigasikan ke menu `Maintenance Lapor`.
2. Klik lapor kerusakan.
3. Pilih aset atau ruangan.
4. Isi deskripsi.
5. Unggah foto jika ada.
6. Kirim ke Waka Sarpras.

Cara pengelola:

1. Silakan buka halaman atau menu daftar laporan.
2. Klik rincian laporan.
3. Terima jika valid atau tolak jika tidak valid.
4. Jika diterima, lanjutkan ke order perbaikan bila perlu.

### Inventaris Barang

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Data aset.
- Tambah/sunting/hapus aset.
- Impor aset.
- Ekspor Excel.
- QR aset.
- Cetak label aset PDF.
- Kategori aset.

Cara tambah aset:

1. Silakan navigasikan ke menu `Inventaris Barang`.
2. Klik tambah aset.
3. Isi kode, nama, kategori, ruangan, kondisi, status, nilai perolehan, masa manfaat, dan foto jika ada.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

Cara impor aset:

1. Silakan navigasikan ke menu `Inventaris Barang`.
2. Klik impor.
3. Unduh templat.
4. Isi berkas.
5. Unggah dan proses impor.

Cara cetak label:

1. Silakan buka halaman atau menu rincian aset.
2. Klik `Cetak Label`.
3. Cetak PDF berisi QR, kode, dan nama aset.

### Kategori Aset

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Tambah/sunting/hapus kategori.
- Impor kategori.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan buka halaman atau menu kategori dari area inventaris/pengaturan sarpras.
2. Tambahkan kategori aset.
3. Gunakan impor jika kategori banyak.

### Pengadaan Aset

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Ajukan pengadaan.
- Item pengadaan.
- Approval setujui/tolak.
- Penerimaan barang.
- Unggah dokumen/nota.

Cara ajukan:

1. Silakan navigasikan ke menu `Pengadaan Aset`.
2. Klik pengadaan baru.
3. Isi judul dan item.
4. Tambah item jika lebih dari satu.
5. Kirim pengajuan.

Cara approval:

1. Silakan buka halaman atau menu rincian pengadaan.
2. Klik setujui atau tolak.
3. Jika disetujui dan barang datang, isi penerimaan.
4. Unggah nota/dokumen pendukung.

### Peminjaman Aset dan Booking Ruangan

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Ajukan peminjaman aset.
- Ajukan booking ruangan.
- Approval setujui/tolak.
- Tandai pengembalian.

Cara ajukan peminjaman aset:

1. Silakan navigasikan ke menu `Peminjaman Aset`.
2. Klik ajukan peminjaman.
3. Pilih aset/ruangan dan tanggal.
4. Isi keperluan.
5. Ajukan.

Cara booking ruangan:

1. Silakan buka halaman atau menu halaman booking jika tersedia dari modul peminjaman.
2. Pilih ruangan, tanggal, jam mulai, jam selesai, dan keperluan.
3. Kirim pengajuan.

Cara pengelola:

1. Silakan buka halaman atau menu rincian peminjaman/booking.
2. Setujui atau tolak.
3. Setelah barang kembali, klik kembalikan.

### Perbaikan dan Teknisi

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Order perbaikan.
- Sunting order perbaikan.
- Tandai selesai.
- Master teknisi.
- Jadwal pemeliharaan.

Cara buat order perbaikan:

1. Silakan navigasikan ke menu `Perbaikan & Teknisi`.
2. Klik order perbaikan baru.
3. Pilih aset/laporan kerusakan/teknisi.
4. Isi keluhan, tindakan, biaya, dan tanggal jika ada.
5. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

Cara selesai:

1. Silakan buka halaman atau menu rincian perbaikan.
2. Perbarui data perbaikan.
3. Klik selesai.

Cara kelola teknisi:

1. Silakan buka halaman atau menu sub-menu teknisi.
2. Tambah/sunting/hapus teknisi internal atau eksternal.

Cara jadwal pemeliharaan:

1. Silakan buka halaman atau menu jadwal pemeliharaan.
2. Tambah jadwal untuk aset/ruangan.
3. Isi tanggal, frekuensi, dan catatan.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

### Mutasi dan Penghapusan Aset

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Mutasi aset antar ruangan.
- Berita acara mutasi.
- Pengajuan penghapusan aset.
- Approval penghapusan.
- Berita acara penghapusan.

Cara mutasi aset:

1. Silakan navigasikan ke menu `Mutasi & Hapus > Mutasi`.
2. Klik mutasi baru.
3. Pilih aset, ruangan asal, dan ruangan tujuan.
4. Isi alasan.
5. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.
6. Cetak berita acara jika diperlukan.

Cara penghapusan aset:

1. Silakan navigasikan ke menu `Penghapusan Aset`.
2. Klik ajukan penghapusan.
3. Pilih aset dan alasan.
4. Ajukan.
5. Pengelola menyetujui atau menolak.
6. Cetak berita acara jika disetujui.

### Supplier

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Data supplier untuk pengadaan.
- Tambah/sunting/hapus supplier.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Supplier`.
2. Klik tambah supplier.
3. Isi nama, kontak, alamat, dan catatan.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

### Laporan Sarpras

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Laporan aset.
- Laporan aktivitas.
- Ekspor aset Excel/PDF.
- Ekspor mutasi Excel.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Laporan`.
2. Pilih jenis laporan.
3. Gunakan penyaring jika tersedia.
4. Ekspor Excel/PDF sesuai kebutuhan.

## 14. Keuangan SPP

Menu admin/bendahara: `Keuangan`.

Menu siswa/orang tua: `Tagihan SPP`.

### Pembayaran SPP Admin/Bendahara

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Dasbor pembayaran SPP.
- Daftar kelas.
- Rincian pembayaran per kelas.
- Sunting nominal/VA/status pembayaran per siswa/bulan.
- Pengaturan kelas.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Keuangan > Pembayaran SPP`.
2. Pilih kelas.
3. Cek tabel pembayaran per siswa dan bulan.
4. Klik sel pembayaran untuk perbarui status/nominal/catatan.
5. Buka pengaturan kelas untuk menetapkan nominal, jatuh tempo, VA, atau aturan kelas.
6. Simpan pengaturan.

### Verifikasi Pembayaran

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Verifikasi bukti bayar dari siswa/orang tua.
- Validasi batch.
- Revisi batch.
- Tolak batch.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Keuangan > Verifikasi`.
2. Tinjauan bukti bayar yang masuk.
3. Pilih satu atau beberapa pembayaran.
4. Klik verifikasi jika bukti benar.
5. Klik validasi jika sudah cocok dengan rekening koran.
6. Klik revisi jika bukti perlu diperbaiki.
7. Klik tolak jika pembayaran tidak valid.

### Bank dan Metode

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Mengatur rekening/bank/metode pembayaran sekolah.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Keuangan > Bank & Metode`.
2. Isi nama bank, nomor rekening, atas nama, dan instruksi pembayaran.
3. Simpan.

### Tagihan SPP Siswa/Orang Tua

Cara melihat tagihan:

1. Silakan navigasikan ke menu `Tagihan SPP`.
2. Pilih bulan/tagihan.
3. Lihat nominal, status, jatuh tempo, dan instruksi bayar.

Cara unggah bukti:

1. Silakan buka halaman atau menu rincian tagihan.
2. Unggah bukti transfer.
3. Kirim.
4. Tunggu status berubah setelah diverifikasi bendahara.
5. Jika diminta revisi, unggah ulang bukti.

## 15. Cetak Data

Untuk mengakses fitur ini, silakan buka menu **`Cetak Data`.** pada bilah navigasi (sidebar).

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Ekspor Excel Data Siswa.
- Ekspor Excel Data Guru.
- Ekspor Excel Data Kelas.
- Ekspor Excel Absensi Guru.
- Ekspor Excel Data Agenda.
- Ekspor Excel Buku Batas.
- Ekspor Excel Nilai Formatif.
- Ekspor Excel Nilai Sumatif.
- Ekspor Excel Nilai Rapor.
- Ekspor Excel Nilai PAS.
- Ekspor Excel Nilai Penjabaran.

Cara umum:

1. Silakan buka halaman atau menu menu `Cetak Data`.
2. Pilih jenis data.
3. Isi penyaring seperti kelas, guru, atau rentang tanggal jika tersedia.
4. Klik unduh/ekspor.
5. Simpan berkas Excel.

Catatan per jenis:

- Data Siswa biasanya memakai penyaring kelas/tingkat.
- Absensi Guru memakai rentang tanggal.
- Agenda dan Buku Batas memakai rentang tanggal dan/atau guru/kelas.
- Nilai memakai kelas atau parameter mapel/kelas sesuai halaman.

## 16. Sistem dan Pengaturan

Untuk mengakses fitur ini, silakan buka menu **`Sistem`.** pada bilah navigasi (sidebar).

### Pengaturan Umum

Fitur ini menyediakan beberapa fungsi utama, di antaranya:

- Identitas sekolah.
- Logo/media sosial.
- Semester aktif.
- Sistem absensi.
- Kiosk token.
- Lokasi QR.
- Waktu terlambat.
- Poin terlambat.
- Jenis aturan: Poin atau P3.
- Poin terlambat aturan.
- Agenda wajib pulang.
- Mapel rapor.
- Tanggal rapor.
- Rumus rapor.
- Wali kelas boleh lihat nilai.
- TP range.
- Unggah aplikasi APK/Windows.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Sistem > Pengaturan`.
2. Isi identitas sekolah.
3. Atur semester aktif.
4. Atur metode absensi dan lokasi QR.
5. Pilih sistem disiplin: Poin atau P3.
6. Atur rumus rapor dan tanggal rapor.
7. Aktifkan/nonaktifkan wali kelas lihat nilai.
8. Unggah APK/installer Windows jika fitur unduh aplikasi ingin ditampilkan.
9. Simpan tiap bagian pengaturan.

### Nilai Penjabaran

Untuk mengakses fitur ini, silakan buka menu **`Pengaturan > Penjabaran`.** pada bilah navigasi (sidebar).

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan buka halaman atau menu pengaturan penjabaran.
2. Tambah komponen nilai.
3. Atur label dan konfigurasi.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

### Kop Rapor

Untuk mengakses fitur ini, silakan buka menu **`Pengaturan > Kop Rapor`.** pada bilah navigasi (sidebar).

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan buka halaman atau menu kop rapor.
2. Unggah logo/kop/backdrop jika tersedia.
3. Isi teks kop.
4. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.
5. Cek hasil di cetak rapor.

### Hak Akses (Hak Akses Sistem)

Untuk mengakses fitur ini, silakan buka menu **`Sistem > Hak Akses (Hak Akses Sistem)`.** pada bilah navigasi (sidebar).

Peran yang bisa dikonfigurasi:

- kepala
- kurikulum
- kesiswaan
- sarpras
- bendahara
- guru
- orangtua
- siswa

Permission yang tersedia:

- `manage_users`: Data Master.
- `manage_absensi`: Absensi dan presensi.
- `manage_jadwal`: Jadwal pelajaran.
- `view_all_nilai`: Melihat semua nilai.
- `manage_agenda`: Mengelola/validasi agenda.
- `manage_rapor`: Rekap nilai dan cetak rapor.
- `manage_disiplin`: Modul kedisiplinan.
- `manage_sarpras`: Sarpras.
- `manage_keuangan`: Keuangan.
- `manage_pengumuman`: Pengumuman.
- `manage_settings`: Pengaturan sistem.
- `manage_perangkat`: Monitoring perangkat ajar guru.

Berikut adalah panduan langkah demi langkah untuk menggunakannya:

1. Silakan navigasikan ke menu `Hak Akses (Hak Akses Sistem)`.
2. Centang permission untuk peran.
3. Simpan.
4. Keluar/masuk ulang pengguna terkait jika menu belum berubah.

## 17. Unduh Aplikasi

Untuk mengakses fitur ini, silakan buka menu **`Unduh Aplikasi`.** pada bilah navigasi (sidebar).

Cara admin mengaktifkan:

1. Silakan navigasikan ke menu `Sistem > Pengaturan`.
2. Cari bagian `Unduh Aplikasi`.
3. Aktifkan fitur.
4. Unggah APK Android dan/atau installer Windows.
5. Terakhir, klik tombol "Simpan" untuk menerapkan perubahan.

Cara pengguna mengunduh:

1. Silakan navigasikan ke menu `Unduh Aplikasi`.
2. Pilih platform Android atau Windows.
3. Klik `Unduh`.

Catatan:

- Berkas disimpan privat dan hanya bisa diunduh melalui jalur halaman (rute) masuk.

## 18. Kartu Pelajar Digital untuk Siswa

Menu siswa: `Kartu Pelajar`.

Cara pakai siswa:

1. Silakan navigasikan ke menu `Kartu Pelajar`.
2. Klik lihat untuk preview kartu.
3. Klik unduh untuk mengunduh kartu.

Catatan:

- Jika admin mengupload kartu kustom, berkas kustom dipakai.
- Jika tidak ada kartu kustom, sistem buat kartu bawaan.

## 19. Alur Operasional Harian yang Disarankan

### Admin/Operator

1. Pastikan semester aktif benar.
2. Perbarui data guru, siswa, kelas, mapel.
3. Atur wali kelas dan penugasan guru mengajar.
4. Cek absensi dan presensi harian.
5. Pantau pengumuman, forum, bot obrolan, dan notifikasi.
6. Ekspor data/cetak sesuai kebutuhan.

### Guru

1. Masuk dan pastikan wajah sudah terdaftar.
2. Cek jadwal mengajar.
3. Isi agenda mengajar setiap selesai kelas.
4. Unggah materi/tugas di Ruang Kelas.
5. Isi nilai di Buku Guru.
6. Unggah perangkat ajar.
7. Ajukan Poin/P3 jika ada kejadian siswa.

### Wali Kelas

1. Cek data siswa kelas.
2. Set sekretaris kelas.
3. Isi/monitor absensi kelas.
4. Pantau Poin/P3 siswa.
5. Tinjauan nilai kelas jika fitur aktif.
6. Cetak rapor saat nilai final.

### Siswa

1. Masuk.
2. Daftarkan wajah jika diminta.
3. Absen QR.
4. Buka Ruang Kelas untuk materi/tugas.
5. Kumpulkan tugas.
6. Cek nilai.
7. Cek tagihan SPP.
8. Unduh kartu pelajar jika perlu.

### Orang Tua

1. Masuk akun orang tua.
2. Cek pengumuman.
3. Cek nilai anak.
4. Cek tagihan SPP.
5. Unggah bukti pembayaran.
6. Gunakan bot obrolan untuk menghubungi admin jika ada kendala.

### Bendahara

1. Silakan buka halaman atau menu Keuangan.
2. Atur bank/metode pembayaran.
3. Atur tagihan per kelas.
4. Tinjauan bukti pembayaran masuk.
5. Verifikasi, validasi, revisi, atau tolak bukti.
6. Pantau rekap pembayaran.

### Waka Sarpras

1. Cek dasbor Sarpras.
2. Tinjauan laporan kerusakan.
3. Kelola aset dan ruangan.
4. Proses pengadaan/peminjaman/perbaikan.
5. Catat mutasi dan penghapusan aset.
6. Ekspor laporan aset atau mutasi.

## 20. Checklist Agar Tidak Terlewat Saat Pelatihan Pengguna

Gunakan checklist ini saat demo ke sekolah:

- Masuk, keluar, ganti kata sandi, PIN, dan registrasi wajah.
- Dasbor dan notifikasi.
- Pengumuman.
- Bot obrolan pengguna dan Inbox Admin.
- Data Guru, impor guru, reset guru, penugasan mengajar.
- Data Siswa, impor siswa, reset siswa/orang tua.
- Data Kelas, wali kelas, rombel, histori rombel.
- Mata Pelajaran.
- Kartu Pelajar admin dan siswa.
- Kalender Absensi.
- Absensi siswa manual dan rekap.
- QR Absensi dan Absen QR pengguna.
- Presensi guru dan rekap.
- Validasi wajah, galeri, dan wajah ganda.
- Ruang Kelas: materi, tugas, komentar, pengumpulan, penilaian, transfer nilai.
- Jadwal Pelajaran dan Jadwal Mengajar.
- Penilaian: KKTP, materi, TP, formatif, sumatif, penjabaran, PTS, PAS, rapor.
- Nilai Saya.
- Rekap Nilai dan Cetak Rapor.
- Ekstrakurikuler.
- Perangkat Ajar.
- Agenda Guru, Rekap Agenda, Buku Batas.
- Sistem Poin atau P3 sesuai pengaturan aktif.
- Wali Kelas: siswa kelas, sekretaris, absensi kelas, disiplin kelas, nilai kelas.
- Forum Diskusi dan pengaturan akses forum.
- Asisten Guru: Generator Soal, RPM Learning, Rangkuman Materi, Draft Feedback, ekspor, dan riwayat generate.
- Analisis AI dan Dokumen AI.
- Sarpras lengkap: dasbor, denah, ruangan, kerusakan, aset, kategori, pengadaan, supplier, peminjaman, booking, perbaikan, teknisi, jadwal, mutasi, penghapusan, laporan.
- Keuangan: pembayaran SPP, verifikasi, bank/metode, tagihan siswa/orang tua.
- Cetak Data semua ekspor.
- Sistem: pengaturan umum, kop rapor, penjabaran, Hak Akses Sistem, unggah aplikasi.
- Unduh Aplikasi.

## 21. Catatan Tinjauan Teknis Singkat

- [Pasti] Aplikasi ini adalah SIMS Laravel dengan banyak modul sekolah, bukan hanya LMS.
- [Pasti] Modul besar yang ada: master data, absensi/presensi, akademik, agenda, disiplin Poin/P3, wali kelas, forum, sarpras, keuangan SPP, AI, bot obrolan, pengumuman, cetak data, pengaturan.
- [Pasti] Sarpras punya jalur halaman (rute) terpisah di `routes/sarpras.php` dan perlu dimasukkan dalam pelatihan pengguna.
- [Pasti] Hak akses tidak satu mekanisme saja: Hak Akses Sistem umum, matriks forum, Gate Sarpras, dan policy classroom.
- [Pasti] Beberapa menu hanya muncul berdasarkan peran, permission, atau pengaturan aktif. Saat pelatihan, masuk dengan beberapa peran agar semua fitur terlihat.
