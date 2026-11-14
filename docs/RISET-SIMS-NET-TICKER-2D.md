# Riset SIMS-NET: ticker bursa 2D ringan

14 November 2026. Rekomendasi dan implementasi: satu track dengan CSS `transform: translateX()`, gerakan linear sekitar 32 piksel/detik. Durasi dihitung dari lebar isi ketika setup, resize, font selesai dimuat, atau ukuran statistik berubah. Tidak ada loop JavaScript per-frame.

## Perbandingan

| Pilihan | Pertimbangan | Keputusan |
| --- | --- | --- |
| CSS transform 2D | Dapat dioptimalkan compositor browser; tidak mengubah layout tiap frame | Dipakai |
| Animasi left/margin atau scrollLeft via timer | Lebih banyak pekerjaan layout atau JavaScript berulang | Tidak dipakai |
| Canvas/WebGL/3D | Menambah kode rendering, pengelolaan teks, dan aksesibilitas untuk informasi sederhana | Tidak diperlukan |

Panduan utama: [web.dev — High-performance CSS animations](https://web.dev/articles/animations-guide), [MDN — animation-play-state](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Properties/animation-play-state). Optimasi compositor bergantung pada browser/perangkat; bukan jaminan FPS tertentu.

## Perilaku

- Server merender satu rangkaian statistik sesuai peran. Browser membuat satu salinan visual untuk sambungan loop; salinan memakai `aria-hidden` dan tidak mengakses database.
- Satu animasi pada track, tanpa blur, ping, shadow besar, library animasi, `setInterval` animasi, atau `requestAnimationFrame` rekursif.
- Jeda melalui tombol Jeda/Lanjut serta tab tersembunyi. Fokus pada area statistik menyediakan pembacaan diam; fokus/hover pada tombol Lanjut tidak menahan animasi. Jeda manual juga melewati fetch ticker terjadwal.
- Pengaturan perangkat `prefers-reduced-motion` menghasilkan bar diam dengan tombol Gerakkan sebagai pilihan eksplisit. Pilihan tersebut diingat per akun/tab dan tidak mengubah pengaturan OS.
- Polling data tetap satu timer 60 detik dengan guard request berjalan dan cache/memo existing. Pergantian frame tidak mengirim request.
- Tanpa JavaScript: statistik asli tetap dapat digeser; tombol jeda disembunyikan.

Kontrol gerakan mengikuti [W3C — Pause, Stop, Hide](https://www.w3.org/WAI/WCAG20/Understanding/pause-stop-hide.html) dan preferensi gerakan mengikuti [MDN — prefers-reduced-motion](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@media/prefers-reduced-motion).

## Perbaikan setelah pemeriksaan Safari

Mode reduced motion terdeteksi aktif di Safari dan merupakan penyebab ticker tidak otomatis bergerak. Tombol Gerakkan kini tetap terlihat. Status Jeda (gerak aktif) berhasil dipertahankan setelah refresh di tab yang sama. Skrip ticker dimuat setelah markup agar tidak menunggu skrip CDN. CSS menyesuaikan palet kelima tema, tanpa fetch preferensi atau timer tambahan.

## Validasi dan batas

Tes frontend memastikan salinan hanya satu meskipun setup dipanggil ulang; tidak ada timer/network dari kode animasi; jeda manual, hidden tab, dan preferensi reduced motion bekerja sebagai state terpisah. Tes fitur memastikan statistik server tetap satu salinan dan cache/memo bekerja. Build dan kompilasi Blade diperiksa. Benchmark FPS/GPU dan HP fisik belum dilakukan.
