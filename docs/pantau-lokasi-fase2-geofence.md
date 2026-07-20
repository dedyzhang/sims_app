# Pantau Lokasi — Fase 2 (Geofence jam sekolah)

> Dokumentasi lanjutan. **Belum diimplementasi.** Fase 1 (riwayat titik absen QR di peta web) sudah ada di SIMS.

## Tujuan

Memberi alert **masuk / keluar area sekolah** hanya pada **jam sekolah** (mis. 06:00–16:00), bukan pelacakan GPS 24 jam.

## Prasyarat

1. Fase 1 aktif (`pantau_lokasi_aktif`) dan pin sekolah + radius sudah diatur.
2. Aplikasi Android native (lihat `docs/prompt-android-webview-fcm.md`) sudah rilis dengan WebView + FCM.
3. Opt-in orang tua eksplisit (setting per anak atau persetujuan di akun ortu).

## Desain teknis (usulan)

| Komponen | Perilaku |
|----------|----------|
| Android | Foreground service + geofencing API; window jam dari setting sekolah |
| Event | `masuk_area` / `keluar_area` → POST ke Laravel (session/CSRF atau token device terikat user) |
| Penyimpanan | Tabel event terpisah (bukan overwrite `absensis.geo_*`) |
| Notifikasi | FCM ke ortu + opsional admin/kesiswaan; **jangan** sertakan lat/lng di payload publik |
| Peta web | Marker event digabung di halaman Pantau Lokasi |

## Play policy & privasi

- Deklarasikan penggunaan lokasi sebagai *student safety / school attendance*, **school hours only**.
- Tampilkan notifikasi foreground yang jelas saat layanan aktif.
- Jangan minta *background location* “selalu” tanpa justifikasi; batasi ke geofence + jam operasional.
- Simpan consent ortu + audit siapa yang melihat data.

## Yang tidak boleh

- Live map “ikuti HP anak” sepanjang hari.
- Mengandalkan WebView `watchPosition` saat app di background (tidak andal).

## Urutan kerja disarankan

1. Setting jam operasional + opt-in ortu di Laravel.
2. Endpoint ingest event geofence + tes otorisasi.
3. Native Android geofence + FCM.
4. UI Pantau Lokasi menampilkan event masuk/keluar.
