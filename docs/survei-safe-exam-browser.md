# Mode Safe Exam Browser untuk survei SIMS

Mode ini memakai penguncian perangkat dari aplikasi resmi [Safe Exam Browser](https://safeexambrowser.org/download_en.html). SIMS memeriksa Browser Exam Key (BEK) dari konfigurasi yang disetujui, mengizinkan pengisian sesuai token dan kelas, lalu memberi perintah keluar setelah jawaban tersimpan.

## Siapkan server dan profil SEB

1. Terapkan migrasi SIMS. Gunakan alamat HTTPS SIMS yang dapat dijangkau oleh perangkat siswa.
2. Buat UUID acak khusus untuk keluar, misalnya dengan `php -r 'require "vendor/autoload.php"; echo Illuminate\Support\Str::uuid(), PHP_EOL;'`. Simpan nilai ini di `SURVEI_SEB_QUIT_ID` pada konfigurasi server.
3. Di SEB Config Tool atau pengaturan SEB, set **Start URL** ke tautan masuk periode yang ada di Detail Survei. Pilih profil **Starting an exam**.
4. Di pengaturan SEB, aktifkan mode kios atau Assessment Mode sesuai perangkat, nonaktifkan perpindahan aplikasi dan navigasi keluar, serta kosongkan daftar aplikasi tambahan yang diizinkan. Lindungi pengaturan dengan kata sandi administrator. Atur **Allow user to quit SEB** dengan **Quit/unlock password** yang hanya dipegang pemandu untuk pemulihan. Gunakan profil yang dilindungi dan simpan kata sandi di luar SIMS.
5. Isi **Link to quit SEB after exam** dengan URL lengkap `https://alamat-sims/survei-pembelajaran/selesai/<UUID>`. Gunakan UUID dari langkah 2. Nonaktifkan konfirmasi dan restart pada Quit Link agar perangkat terbuka setelah pengiriman. Simpan URL ini di profil SEB dan konfigurasi server. SEB sendiri menangani Quit Link sehingga kerahasiaan URL ini penting.
6. Aktifkan pengiriman **Browser & Config Keys** pada SEB Windows. Gunakan **Prefer Modern** untuk macOS/iOS agar SEB mengirim bukti lewat JavaScript API. Jika memakai URL filter, izinkan navigasi yang diperlukan untuk login dan survei di SIMS serta Quit Link.
7. Setelah profil tersimpan, buka profil itu pada setiap versi/platform SEB yang dipakai dan salin **Browser Exam Key** ke `SURVEI_SEB_BROWSER_EXAM_KEYS`. Pisahkan beberapa BEK dengan koma atau spasi. Perbarui cache konfigurasi Laravel jika dipakai. Ulangi pengambilan BEK jika profil atau versi aplikasi berubah.

## Jalankan sesi

Survei baru memakai sesi token kelas. **Wajib Safe Exam Browser** opsional: centang saat membuat periode, atau pilih **Wajibkan Safe Exam Browser** di panel sesi sebelum memulai sesi. Saat sesi berjalan, siswa membuka profil SEB, login dengan akun SIMS dan memasukkan token kelas.

Rute survei tetap memerlukan login SIMS dan melewati pemeriksaan wajib ganti password. Rute ini dikecualikan hanya dari gate pendaftaran wajah agar siswa dan petugas dapat mengikuti survei tanpa harus mendaftarkan biometrik; pembatasan per kelas, peran, periode, token, dan pengiriman tetap diperiksa oleh middleware/controller survei.

Sebelum dipakai di kelas, lakukan pengujian pada perangkat yang akan dipakai. Coba pindah aplikasi, pastikan jawaban gagal kirim tidak menutup SEB, lalu kirim respons uji dan pastikan perangkat terbuka. Pastikan pemandu dapat memakai kata sandi keluar jika perangkat bermasalah atau sesi berakhir sebelum siswa selesai.

[Panduan integrasi resmi SEB](https://safeexambrowser.org/developer/seb-integration.html) · [Dukungan klien SEB](https://safeexambrowser.org/download_en.html)
