# SIMS Kios untuk macOS

Aplikasi AppKit dan WKWebView untuk survei SIMS. Apple Automatic Assessment Configuration mengunci perangkat. Aplikasi hanya menandatangani permintaan buka soal dan kirim jawaban setelah macOS mengonfirmasi sesi penguncian aktif. Aplikasi mengakhiri sesi setelah server mengonfirmasi bahwa respons siswa tersimpan.

## Build lokal

```sh
bash apps/sims-kios-macos/build.sh
```

Build lokal berjalan pada Mac Intel dan Apple Silicon dengan macOS 12 ke atas. Secara default aplikasi membuka survei demo di localhost. Set `SIMS_SURVEY_URL` saat build untuk memakai URL masuk periode lain. Build lokal ditandatangani ad hoc dan dapat digunakan untuk memeriksa login dan tampilan. Apple mewajibkan entitlement khusus agar Assessment Mode aktif. [Dokumentasi entitlement Apple](https://developer.apple.com/documentation/bundleresources/entitlements/com.apple.developer.automatic-assessment-configuration)

## Aktifkan untuk perangkat sekolah

1. Siapkan pengesahan Apple untuk bundle ID `id.edutive.sims.kios`. Isi `SIMS_KIOS_SIGN_IDENTITY` dan `SIMS_KIOS_PROFILE` saat membangun aplikasi. Untuk distribusi di luar App Store, selesaikan penandatanganan dan notarization yang sesuai.
2. Terapkan migrasi SIMS. Pakai URL HTTPS untuk perangkat sekolah. Jalankan registrasi dari akun macOS yang akan menjalankan aplikasi pada setiap perangkat terkelola.

   ```sh
   "/path/SIMS Kios.app/Contents/MacOS/SIMS-Kios" --register-device
   ```

3. Masukkan kunci publik hasil registrasi ke `SURVEI_KIOS_PUBLIC_KEYS` di server SIMS. Pisahkan beberapa kunci dengan koma. Segarkan cache konfigurasi Laravel jika dipakai. Simpan kunci privat di Keychain perangkat dan batasi akses administrasi Mac kepada petugas.
4. Siapkan kata sandi pemulihan yang unik dan kuat. Atur hash SHA-256 heksadesimalnya pada preferensi terkelola `RecoveryPasswordSHA256` untuk bundle ID aplikasi. Gunakan akun siswa standar dan konfigurasi terkelola sekolah agar siswa tidak bisa mengganti pengaturan pemulihan. Pemandu dapat memakai **Bantuan pemandu** untuk memasukkan kata sandi jika pengiriman terhenti.
5. Di **Detail Survei**, pilih **Wajibkan SIMS Kios**, lalu mulai sesi dan bagikan token. Jalankan uji perangkat dengan akun demo. Periksa perpindahan aplikasi saat mengisi, penolakan keluar sebelum mengirim, dan pembukaan perangkat setelah respons tersimpan.

Registrasi kunci mengizinkan perangkat yang dikelola sekolah. Untuk perangkat pribadi, kunci terdaftar tidak membuktikan integritas macOS atau bahwa akun perangkat tidak dapat dimodifikasi.
