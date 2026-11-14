# Review performa tema, wallpaper, dan SIMS-NET

Tanggal: 14 November 2026. Lingkup: kode personalisasi, layout bersama, statistik footer, dan polling terkait. Semua perubahan lokal, tanpa commit/push. Review sumber dan tes terarah; bukan hasil benchmark CPU/GPU atau uji beban produksi.

## Revisi setelah review

Atas permintaan berikutnya, bar statis diganti ticker bursa CSS transform 2D. Animasi kini satu track dengan satu salinan visual yang dibuat browser, kontrol jeda, dan dukungan reduced motion. Polling tetap 60 detik. Detail keputusan dan sumber: [Riset ticker 2D](RISET-SIMS-NET-TICKER-2D.md). Temuan di bawah mendokumentasikan tahap review sebelum revisi tersebut.

## Temuan dan perbaikan

1. **[P2, diperbaiki] Animasi footer terus berjalan dan markup statistik digandakan.** Layout lama memakai `ticker-scroll` 35 detik berulang, salinan statistik kedua, serta indikator ping. Beban utamanya rendering browser, bukan query database tambahan. Diganti `resources/views/partials/sims-net.blade.php`: satu bar statis, satu salinan tiap angka, dapat digeser dengan sentuhan/trackpad/keyboard. Tidak memakai blur, marquee, atau shadow besar. CSS di `public/css/sims-net.css`.

2. **[P2, diperbaiki] Slider wallpaper membuat ulang URL foto.** `public/js/sims-appearance.js`, `applyWallpaper()`, sebelumnya mencabut dan membuat object URL setiap perubahan redup/buram. Hal ini berpotensi memicu pemuatan/decode gambar berulang pada browser. Sekarang URL dipakai ulang selama Blob fotonya sama; diganti/dicabut ketika foto atau jenis wallpaper berubah. Properti background hanya ditulis saat berubah. Tes regresi memeriksa penggunaan ulang dan penggantian URL.

3. **[P2, perlu pengukuran skala produksi] Request polling tetap bertambah sesuai jumlah tab aktif.** Ticker berjalan 60 detik, sama dengan TTL cache statistik. Satu tab sekitar satu request/menit, sehingga 1.000 tab aktif sekitar 1.000 request/menit (rata-rata 16,7 request/detik, belum termasuk fitur lain). Ini perhitungan interval, bukan kapasitas server terukur. Cache menghemat agregat database, tetapi sesi, middleware, serta respons HTTP tetap punya biaya. `Cache::remember` juga tidak menjamin satu proses agregasi saat banyak cache miss bersamaan. Sebelum skala besar, ukur request rate, latency p95, dan query ketika cache habis; pertimbangkan refresh statistik terjadwal bila hasil pengukuran membutuhkannya.

4. **[P2, batas perangkat] Blur wallpaper penuh layar dan foto resolusi tinggi dapat menambah beban GPU/memori.** Default blur tetap 0. Foto dibatasi format JPG/PNG/WebP, 10 MB, dan 32 MP; disimpan sebagai JPEG dengan sisi terpanjang maksimal 2.560 piksel di browser. Batas resolusi diperiksa setelah decode, sehingga proses unggah masih dapat membutuhkan memori sementara cukup besar. Untuk HP lama, gunakan wallpaper bawaan atau foto yang lebih kecil dengan blur 0. Belum diukur di HP fisik.

## Yang sudah efisien

- Lima SVG bawaan berukuran 871–902 byte per gambar (total 4.397 byte, sebelum kompresi HTTP). Hanya tema aktif menjadi background; miniatur pada halaman Personalisasi dapat memuat tema lain sebagai aset statis.
- Pergantian tema tidak memakai fetch, query SQL, ataupun timer baru. Foto keluarga tidak dikirim ke server, melainkan IndexedDB per akun/browser.
- Dock memakai registry menu yang sudah dihitung layout, tanpa query atau endpoint tambahan.
- Statistik dipakai ulang melalui memo per-request dan cache TTL 60 detik. Tes memastikan pembacaan berulang hanya mengakses cache sekali, serta invalidasi membersihkan keduanya.
- Timer ticker berkunci mencegah registrasi ganda dalam halaman; menunggu request selesai; tidak mengambil data saat tab tersembunyi, kiosk, atau mode ujian. Notifikasi existing memakai response gabungan dan guard request berjalan, bukan polling baru dari tema.
- Pembaruan footer memakai referensi DOM yang sudah dikumpulkan dan `textContent`, hanya bila nilainya berubah. Data tetap difilter sesuai peran melalui `TickerStats::forRole()`.

## Validasi

- Tes fitur: ProfilePreferenceTest, SettingTampilanTest, RoleTendikTest, TickerStatsMemoTest.
- Tes frontend: isolasi wallpaper, penyimpanan/cancel, format foto, perpindahan tema, penggunaan ulang URL gambar, timer tunggal, cadence, hidden tab, dan request berjalan.
- Build Vite, kompilasi Blade, pemeriksaan sintaks JS, dan diff whitespace.

Tidak ada migration, perubahan hak akses, atau ketergantungan baru. Tidak dapat menyimpulkan seluruh aplikasi bebas query duplikat dari review modul ini.
