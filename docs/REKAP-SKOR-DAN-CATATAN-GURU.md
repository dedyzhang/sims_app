# Rekap skor dan catatan survei per guru

Buka **Survei Pembelajaran → periode yang sudah berakhir → Analisis Survei per Guru → Unduh PDF rekap guru**. Laporan memuat ringkasan seluruh guru, lalu rincian masing-masing guru. Tidak ada pengiriman otomatis ke pihak lain.

## Isi dan perhitungan

- Skor 1–5 mengikuti instrumen yang tersimpan pada periode, termasuk arah butir negatif. “Tidak tahu” tidak dinilai.
- Setiap butir per sasaran (kelas/guru/mapel atau wali kelas) memerlukan minimal lima jawaban valid sebelum digabungkan. Beberapa kelompok kecil tidak digabung untuk meloloskan ambang ini.
- Skor guru = jumlah skor jawaban yang memenuhi syarat / jumlah jawaban butir yang dinilai. Ini pembobotan berdasarkan jawaban, bukan rata-rata sederhana atas skor kelas.
- Nilai skala 100 = skor × 20. Contoh: skor 4 menjadi 80. Rentang efektif 20–100. Pembulatan dua desimal hanya untuk tampilan.
- Guru mata pelajaran dan wali kelas dipisahkan. Data belum cukup ditandai demikian, tidak diberi nilai nol.
- Rincian indikator, hingga tiga kekuatan (skor ≥ 4) dan tiga area perbaikan (skor < 3,5) menjadi bahan tindak lanjut. Tidak ada peringkat atau predikat kinerja otomatis.
- Isian survei dan jawaban butir bukan jumlah siswa unik; siswa yang sama dapat mengisi beberapa sasaran.

## Rekap catatan

Catatan dikaitkan ke guru berdasarkan sasaran formulir yang dijawab siswa, lalu dipisahkan menurut peran dan pertanyaan terbuka pada periode. Kendala lainnya disertakan untuk guru mata pelajaran. Catatan dari sasaran dengan kurang dari lima respons disembunyikan.

Tulisan sama digabung dengan normalisasi huruf besar/kecil dan spasi; satu teks asli tetap ditampilkan bersama jumlah catatannya. Tidak ada penafsiran sentimen, rangkuman buatan AI, atau pengaruh komentar terhadap skor. Jumlah kemunculan adalah jumlah catatan, bukan siswa unik.

## Akses dan kerahasiaan

- Tetap gunakan izin existing `canViewSurveiResults()` untuk rekap global/PDF. Guru dengan akses hasil sendiri tetap melihat agregat guru tersebut saja dan tidak mendapat PDF global.
- Komentar asli tetap khusus admin pembuat periode melalui `bolehMelihatIdentitas()`. Pembuat memperoleh PDF lengkap untuk ditinjau sebelum disampaikan kepada kepala sekolah/waka. Petugas lain yang berizin memperoleh versi skor tanpa komentar asli.
- Tidak ada nama akun atau ID siswa pada laporan. Teks bebas siswa bisa menyebut data pribadi; pembuat perlu meninjaunya sebelum berbagi.
- Semua teks komentar di-escape. Respons memakai `Cache-Control: private, no-store`; unduhan dicatat dalam audit tanpa isi komentar.
- Tidak mengubah matriks izin, migration, jawaban siswa, atau nilai akademik/rapor.

## Beban dan verifikasi

Pengambilan rekap memakai eager loading: satu SELECT sasaran dan satu SELECT respons, tanpa query per guru. Kolom komentar/kendala hanya dimuat untuk pembuat ketika diperlukan. Penghitungan dilakukan saat halaman/laporan diminta, tanpa polling, layanan AI, atau dependency baru. PDF panjang tetap membutuhkan CPU/memori sesuai banyaknya jawaban/catatan; belum ada uji beban produksi.

Verifikasi lokal: 22 tes survei (493 assertion), termasuk pembobotan, pembalikan butir negatif, jawaban tidak tahu, ambang kelompok kecil, pemisahan guru/periode, pengelompokan catatan, akses pembuat/petugas/guru/siswa, escaping, audit, jumlah query, dan unduhan PDF. Contoh PDF dengan data uji dirender dan diperiksa secara visual.


## Format laporan evaluasi (revisi keterbacaan)

Susunan mengikuti prinsip bukti, diskusi, tindak lanjut, dan refleksi pada [panduan Kemendikdasmen](https://pusatinformasi.ult.kemendikdasmen.go.id/hc/id/articles/40451104394521-Tentang-Alur-Penilaian-Praktik-Kinerja-Oleh-Kepala-Sekolah), diperiksa 14 November 2026. [Panduan pengisian penilaian](https://pusatinformasi.ult.kemendikdasmen.go.id/hc/id/articles/40450964239001-Cara-Mengisi-Penilaian-Praktik-Kinerja-Guru-Oleh-Kepala-Sekolah) menjelaskan peran kepala sekolah dalam menilai bukti observasi dan mendiskusikan tindak lanjut. Susunan SIMS merupakan adaptasi laporan pendukung dari survei siswa; bukan formulir resmi atau predikat PKG.

- Satu guru/peran memiliki lembar hasil: kop sekolah, identitas, cakupan, nilai utama, tabel indikator, arah butir negatif, dan ringkasan fokus pembinaan.
- Lembar berikutnya memuat catatan siswa dalam tabel frekuensi, rencana tindakan, penanggung jawab/target waktu, refleksi, dan ruang paraf guru/waka/kepala sekolah.
- Kolom tindakan, refleksi, tanggal, dan paraf disediakan kosong untuk diisi setelah diskusi; tidak dibuatkan persetujuan atau keputusan otomatis.
- Banyak guru mendapat tambahan halaman ringkasan. Daftar catatan panjang dapat berlanjut; kepala tabel diulang. Contoh satu guru dengan 10 indikator dan empat kelompok tulisan muat dua halaman A4.
- Nomor halaman tersedia pada PDF aplikasi. Rumus, ambang privasi, dan akses komentar tetap sama.
- Revisi format diuji melalui tes laporan yang lulus dengan 42 assertion; kedua halaman contoh diperiksa secara visual.
