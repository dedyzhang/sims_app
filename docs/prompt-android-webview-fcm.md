# Prompt: SIMS → Aplikasi Android (WebView) + Firebase FCM + Rilis APK/AAB

> Disusun bertahap (10 fase). Tempel **PROMPT 0** dulu ke AI coding (Claude Code/Android Studio AI/dll), tunggu konfirmasi paham, baru tempel fase 1, 2, 3, ... satu per satu. Jangan lompat fase — tiap fase punya kriteria uji yang harus lolos sebelum lanjut.

## Yang perlu disiapkan sebelum mulai
1. **URL produksi (HTTPS):** `https://smp.maitreyawira-tpi.sch.id` — sudah HTTPS, siap dipakai (kamera & geolokasi WAJIB HTTPS di HP fisik, sudah terpenuhi).
2. **Project Firebase** (console.firebase.google.com) → download:
   - `google-services.json` (untuk app Android)
   - Service Account JSON (Project Settings → Service Accounts → Generate new private key, untuk backend Laravel)
3. **Nama package/applicationId** final (mis. `id.sch.maitreyawiratpi.sims`) — didaftarkan **sama persis** di Firebase Console Android app (sumber error #1 kalau beda: *"Default FirebaseApp is not initialized"*).

---

## PROMPT 0 — Konteks & Spesifikasi (tempel sekali)

```
Kamu adalah senior Android engineer + Laravel engineer. Buatkan aplikasi Android NATIVE (Kotlin, Android Studio, Gradle KTS) yang membungkus web app existing memakai WebView, DITAMBAH integrasi Firebase Cloud Messaging (FCM) untuk push notification. JANGAN pakai Cordova/Capacitor/Flutter.

TENTANG WEB APP:
- Laravel 12 "SIMS" (sistem informasi sekolah SMP Maitreyawira Tanjungpinang).
- BASE_URL PRODUKSI (HTTPS, sudah aktif): https://smp.maitreyawira-tpi.sch.id
- Dev emulator: http://10.0.2.2:8000
- Dev HP fisik (LAN): http://{IP_LAPTOP}:8000
- Auth: session + cookie Laravel standar (BUKAN Sanctum, tidak ada routes/api.php). Semua request AJAX di web pakai CSRF token dari meta tag <meta name="csrf-token">. Ikuti pola ini untuk request baru dari WebView, JANGAN buat REST API/token auth terpisah.
- QUEUE_CONNECTION=database sudah aktif — job FCM boleh di-queue.
- Notifikasi sudah ada (Laravel database notifications): app/Notifications/ForumReplyNotification.php, ClassroomCommentNotification.php, app/Sarpras/Notifications/KerusakanDilaporkan.php, PemeliharaanJatuhTempo.php — semua saat ini via()=>['database'] saja, dipoll browser tiap 10 detik lewat route notifications-json. Endpoint terkait: NotificationController@getNotifications/markAsRead/markAllAsRead.

FITUR WEB YANG WAJIB TETAP JALAN DI WEBVIEW:
1) KAMERA (getUserMedia): daftar wajah, absensi wajah, scan QR, foto aset/siswa.
2) GEOLOKASI: absen QR validasi GPS.
3) UPLOAD FILE (18 titik <input type=file>): bukti pembayaran SPP (gambar, dikompres di browser), foto, import Excel/CSV. Sebagian butuh pilih dari KAMERA + galeri + dokumen.
4) UNDUH FILE (14 endpoint Excel/PDF): rapor, berita acara, label QR, laporan — respons Content-Disposition attachment, perlu cookie sesi ikut terkirim.
5) localStorage persist (dark mode 'theme_mode', tata letak dashboard) & cookie sesi persist antar buka-tutup app.
6) Link eksternal (wa.me, mailto:, tel:, domain sosmed lain) buka di app luar, BUKAN di WebView.
7) WebAuthn (Laragear, login sidik jari/Face ID) — WebView Android tidak mendukung penuh; WAJIB fallback ke password/PIN tanpa crash.

FITUR BARU — FIREBASE CLOUD MESSAGING:
- Trigger push: balasan forum, komentar tugas ruang kelas, lapor kerusakan sarpras (ke Waka Sarpras), jatuh tempo pemeliharaan, pesan chatbot baru dari admin.
- Setiap notifikasi FCM harus tetap MASUK ke database notifications yang sudah ada (jangan duplikasi logic, jangan pecah struktur data 'type'/'message' yang sudah dipakai bell icon).
- Token FCM disimpan per user (satu user bisa multi-device) di tabel BARU, dikirim dari Android via route web (session+CSRF), BUKAN endpoint API terpisah.
- Kegagalan kirim FCM (token invalid/expired) TIDAK BOLEH melempar exception yang mengganggu notifikasi database — auto-hapus token yang gagal, log, lanjut.

TARGET RILIS: minSdk 24, targetSdk 34, Kotlin, ViewBinding, output akhir: APK (testing) + AAB (Play Store), dengan Play App Signing.

CARA KERJA: kerjakan PER FASE sesuai prompt berikutnya (ada 10 fase total: 7 fase WebView dasar, 2 fase Firebase, 1 fase build rilis). Tiap fase: tampilkan file yang diubah lengkap + AndroidManifest terkait + langkah uji manual yang harus lolos. Pastikan build/compile sukses tiap fase sebelum lanjut. Jangan lanjut ke fase berikutnya sebelum aku bilang "lanjut".
Konfirmasi paham, lalu tunggu PROMPT FASE 1.
```

---

## FASE 1 — Skeleton WebView (login, cookie, back, dark mode)

```
FASE 1 — Skeleton WebView.
MainActivity dengan WebView fullscreen memuat BASE_URL (https://smp.maitreyawira-tpi.sch.id). Wajib:
- WebSettings: javaScriptEnabled, domStorageEnabled, databaseEnabled, loadWithOverviewMode, useWideViewPort, mediaPlaybackRequiresUserGesture=false.
- CookieManager: setAcceptCookie + setAcceptThirdPartyCookies + flush di onPause — sesi login harus bertahan setelah app ditutup.
- Tombol BACK Android: webView.canGoBack() → goBack(), else keluar app.
- SwipeRefreshLayout (pull-to-refresh).
- network_security_config.xml: cleartext HANYA untuk 10.0.2.2 & IP LAN dev; domain produksi (smp.maitreyawira-tpi.sch.id) WAJIB HTTPS saja, jangan whitelist cleartext untuknya.
- windowSoftInputMode=adjustResize (form tak ketutup keyboard).
- WebViewClient.onReceivedError → halaman "Tidak ada koneksi" + tombol Coba Lagi.

UJI (harus lolos sebelum lanjut):
[ ] Login berhasil di https://smp.maitreyawira-tpi.sch.id/login & tetap login setelah app ditutup-buka.
[ ] Dark mode tetap tersimpan setelah reload.
[ ] Tombol back menavigasi di dalam web, bukan langsung keluar app.
```

## FASE 2 — Kamera

```
FASE 2 — Kamera.
- WebChromeClient.onPermissionRequest → grant RESOURCE_VIDEO_CAPTURE setelah runtime permission CAMERA diberikan.
- AndroidManifest: <uses-permission CAMERA>, <uses-feature camera required="false">.

UJI:
[ ] Daftar wajah & absensi scan wajah: kamera muncul, tidak hitam.
[ ] Scan QR absensi: kamera membaca QR.
[ ] Tolak izin sekali → tidak crash, muncul prompt ulang.
```

## FASE 3 — Upload file

```
FASE 3 — Upload (<input type=file>).
- WebChromeClient.onShowFileChooser: hormati atribut accept & multiple.
- Untuk image/*: chooser gabungan Kamera (FileProvider) + Galeri + Dokumen.
- FileProvider (authorities ${applicationId}.fileprovider + file_paths.xml).
- ActivityResultLauncher → kembalikan Uri[] ke filePathCallback; batal → callback(null) (jangan biarkan null pointer/freeze).
- targetSdk 34: scoped storage, tanpa READ_EXTERNAL_STORAGE bila tak perlu.

UJI:
[ ] Upload bukti SPP dari galeri & dari kamera langsung → terkirim.
[ ] Import .xlsx aset/siswa → terproses.
[ ] Batal pilih file → form tidak freeze.
```

## FASE 4 — Unduh file

```
FASE 4 — Download.
- webView.setDownloadListener → DownloadManager, teruskan Cookie (CookieManager.getCookie) & User-Agent (WAJIB, karena endpoint download butuh sesi login — tanpa cookie akan dapat halaman login, bukan file).
- Scoped storage API 29+; parse Content-Disposition untuk nama file benar.
- Notifikasi selesai + buka file via ACTION_VIEW + FileProvider.

UJI:
[ ] Export Excel & PDF (rapor/berita acara/label QR) → file benar terbuka (BUKAN halaman HTML login).
```

## FASE 5 — Geolokasi

```
FASE 5 — Geolokasi.
- WebChromeClient.onGeolocationPermissionsShowPrompt → callback.invoke(origin,true,false) setelah ACCESS_FINE_LOCATION diberikan.
- GPS mati → web dapat error wajar, app tidak crash.

UJI:
[ ] Absen QR mendapat koordinat & validasi lokasi jalan.
[ ] Tolak izin lokasi → tidak crash.
```

## FASE 6 — Navigasi eksternal, offline, polish

```
FASE 6 — Finishing dasar.
- shouldOverrideUrlLoading: domain smp.maitreyawira-tpi.sch.id tetap di WebView; mailto:/tel:/wa.me/domain lain → Intent.ACTION_VIEW ke app luar.
- Halaman offline + tombol reload; splash, ikon adaptif, status bar sesuai tema.

UJI:
[ ] Klik link WhatsApp/sosmed → buka app luar, bukan di WebView.
[ ] Mode pesawat → halaman offline muncul, Coba Lagi berfungsi.
```

## FASE 7 — WebAuthn fallback

```
FASE 7 — WebAuthn (Laragear) fallback aman.
- Tombol "Login biometrik" TIDAK BOLEH crash bila navigator.credentials tidak tersedia — app tetap bisa login password & PIN.
- (Opsional) androidx.webkit WebViewFeature.WEB_AUTHENTICATION bila device mendukung.

UJI:
[ ] Tombol biometrik tidak crash; password & PIN tetap normal.
```

## FASE 8 — Firebase FCM (sisi Android)

```
FASE 8 — Firebase Cloud Messaging (Android).
- Tambah Firebase SDK (BoM terbaru) + google-services.json (placeholder, aku isi manual dari Firebase Console — applicationId project HARUS SAMA PERSIS dengan package_name di google-services.json, ini penyebab #1 error "Default FirebaseApp is not initialized").
- Buat class FirebaseMessagingService:
  - onNewToken(token): simpan token, lalu evaluateJavascript ke WebView untuk kirim token ke server (lihat Fase 9) — HANYA jika WebView sedang di halaman ter-autentikasi (bukan halaman /login).
  - onMessageReceived: payload FCM WAJIB "data-only" (bukan "notification" block) agar konsisten dibangun manual baik saat app foreground maupun background — hindari notifikasi dobel/hilang.
  - Bangun NotificationCompat manual: channel ID, judul dari data['title'], body dari data['message'], tap → buka MainActivity dengan extra "open_url" = data['url'] (path relatif, mis. /forum/abc-slug) → MainActivity load BASE_URL+open_url ke WebView.
- Notification Channel (Android 8+) dibuat sekali di Application class.
- Runtime permission POST_NOTIFICATIONS (Android 13/API 33+) — WAJIB diminta, tanpa ini FCM terkirim tapi TIDAK TAMPIL (silent, tanpa error) — jangan sampai lolos tanpa uji ini.
- Jembatan WebView→native: setelah login terdeteksi sukses (URL berubah dari /login), panggil JS `window.__fcmToken` (native provide via addJavascriptInterface `AndroidBridge.getFcmToken()`) ATAU sebaliknya native evaluateJavascript memanggil fungsi JS `window.registerFcmToken(token)` yang sudah didefinisikan di web (Fase 9 sisi Laravel).

UJI:
[ ] Emulator/HP terima push test dari Firebase Console Cloud Messaging (kirim ke token perangkat) — notifikasi tampil di app foreground DAN background.
[ ] Tap notifikasi → app terbuka ke halaman yang benar (deep link), bukan hanya halaman utama.
[ ] Tolak izin notifikasi → app tidak crash; notifikasi memang tidak tampil (perilaku wajar, bukan bug).
[ ] Token refresh (uninstall-reinstall) → token baru terkirim ulang ke server.
```

## FASE 9 — Firebase FCM (sisi Laravel backend)

```
FASE 9 — Firebase Cloud Messaging (Laravel).
- composer require kreait/firebase-php.
- Service account JSON Firebase disimpan di storage/app/firebase/service-account.json (TIDAK di-commit — tambahkan ke .gitignore), path direferensikan via .env FIREBASE_CREDENTIALS.
- Migration baru: tabel user_fcm_tokens (uuid PK, user_uuid FK ke users.uuid cascadeOnDelete, token string unique, device_type nullable, created_at). SATU user boleh punya banyak token (multi-device).
- Route registrasi token — pakai grup auth existing (session+CSRF, SATU baris dekat route notifications-json di routes/web.php):
  POST /notifications/fcm-token  → NotificationController@storeFcmToken (validasi token required|string, upsert by user_uuid+token)
  DELETE /notifications/fcm-token → NotificationController@destroyFcmToken (dipanggil saat logout, best-effort)
- Di web (resources/views/layouts/app.blade.php, dekat kode bell notifikasi existing): definisikan window.registerFcmToken(token) yang POST ke route di atas pakai CSRF meta tag (pola fetch() SAMA seperti kode simpan tata letak dashboard yang sudah ada — JANGAN bikin mekanisme baru).
- Buat App\Services\FcmService: kirim push via Firebase Admin SDK (Messaging::send), terima payload data-only (title, message, url, type) — SESUAI struktur toArray() yang sudah ada di tiap Notification class, JANGAN ubah field yang sudah dipakai bell icon.
- Buat App\Notifications\Channels\FcmChannel (custom channel): loop semua token milik $notifiable, kirim satu-satu; tangkap exception token invalid/expired (NotFound/InvalidArgument dari Firebase SDK) → hapus baris token itu, JANGAN lempar exception ke pemanggil (agar Notification::send ke banyak user & channel database tetap jalan meski satu token gagal).
- Update via() di 4 Notification class existing (ForumReplyNotification, ClassroomCommentNotification, KerusakanDilaporkan, PemeliharaanJatuhTempo) jadi ['database', FcmChannel::class]. Tambahkan method toFcm($notifiable) yang reuse data dari toArray() (title generik + message + url tujuan halaman + type) — jangan duplikasi logic pembuatan pesan.
- Dispatch pengiriman FCM lewat queue (QUEUE_CONNECTION=database sudah aktif) — implement ShouldQueue di FcmChannel/job pengirim agar request user tidak menunggu HTTP call ke Firebase.

UJI:
[ ] php artisan migrate sukses, tabel user_fcm_tokens terbentuk.
[ ] Login dari Android → token tersimpan (cek tabel).
[ ] Balas forum/komentar tugas/lapor kerusakan → notifikasi database TETAP terbentuk seperti biasa DAN push FCM terkirim ke device Android.
[ ] Hapus token manual di DB lalu trigger notifikasi lagi → tidak error 500, token invalid otomatis terhapus dari tabel, log tercatat.
[ ] php artisan test tetap 100% lulus (tidak ada test lama yang rusak oleh perubahan via()).
```

## FASE 10 — Build APK & AAB (rilis)

```
FASE 10 — Build & rilis.
- Generate keystore rilis (keytool) ATAU pakai Play App Signing (upload key) — jelaskan dua opsi, rekomendasikan Play App Signing.
- build.gradle.kts: signingConfigs release, versionCode/versionName strategi bump, applicationId final (HARUS SAMA dengan google-services.json).
- ProGuard/R8 keep rules WAJIB (sumber error rilis paling umum):
  - Keep class JavaScript interface (method @JavascriptInterface) — kalau di-obfuscate, WebView JS bridge gagal total ("has no method" saat production build padahal debug build normal).
  - Pastikan consumer-proguard-rules bawaan Firebase tidak di-override manual.
- Build APK debug/release untuk uji manual dulu, baru bundleRelease (AAB) untuk upload Play Console.
- Checklist Play Console Data Safety: deklarasikan penggunaan Camera, Location (approximate/precise), Notifications; target API level 34; upload deobfuscation/mapping file (mapping.txt) untuk crash report yang berarti.

UJI (WAJIB sebelum submit):
[ ] Install APK release (BUKAN debug) di device fisik → ulang semua uji Fase 1–9 di build release (obfuscation kadang memunculkan bug yang tak muncul di debug).
[ ] AAB lolos "bundletool build-apks" simulasi lokal tanpa error sebelum upload ke Play Console.
```

## FASE 11 — Ringtone kustom notifikasi (Pengumuman)

Notifikasi Pengumuman mengirim payload data-only dengan field tambahan `sound: "notif_sims"`.
Sisi web sudah memutar `public/sounds/notif-sims.wav` saat badge bertambah. Agar Android
memakai ringtone yang SAMA:

```
- Salin file public/sounds/notif-sims.wav ke android res/raw/notif_sims.wav
  (nama resource HARUS lowercase tanpa ekstensi saat dirujuk: R.raw.notif_sims).
- Saat membangun NotificationChannel (Android 8+), set suara channel:
    val uri = Uri.parse("android.resource://${packageName}/${R.raw.notif_sims}")
    channel.setSound(uri, AudioAttributes.Builder()
        .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
        .setUsage(AudioAttributes.USAGE_NOTIFICATION).build())
  CATATAN: suara channel dikunci saat channel pertama dibuat — bila ganti suara,
  ubah juga channelId (mis. "sims_pengumuman_v2") atau uninstall/clear data.
- Payload.data["sound"] bisa dipakai untuk memilih channel/suara per-tipe notifikasi
  (mis. hanya "pengumuman" pakai nada ini, tipe lain pakai default).

UJI:
[ ] Terbitkan Pengumuman dari web → device Android berbunyi nada notif_sims (bukan default).
[ ] Web: badge bertambah → nada terdengar; toggle 🔇 di dropdown notifikasi mematikannya.
```

## FASE 12 — Mode fokus Arena Belajar (anti keluar sesi)

> **APK DITAHAN** — jangan implement native dulu. Spec disimpan untuk nanti.
>
> Spec lengkap + cuplikan Kotlin: `docs/android-arena-focus-bridge.md`.  
> Sisi **website** sudah aktif (`$holdArenaFocusLock = false`).

```
FASE 12 — Mode fokus Arena (WebView Android).
[APK DITAHAN] Web sudah punya gate fokus + log keluar (visibilitychange / CSS immersive).
Tambah di JavascriptInterface yang SAMA dengan FCM (AndroidFcm):

1) @JavascriptInterface fun enterArenaFocus()
   - Immersive sticky: hide status + navigation bars
   - Set flag arenaFocusActive = true

2) @JavascriptInterface fun exitArenaFocus()
   - Show system bars lagi
   - arenaFocusActive = false

3) (Opsional) setArenaFocusActive(Boolean) / isArenaFocusActive(): Boolean

4) MainActivity onBackPressed / OnBackPressedCallback:
   - Jika arenaFocusActive (atau evaluateJavascript window.__arenaFocusActive):
     panggil window.arenaFocusAndroidBack()
     bila return true → JANGAN webView.goBack()
   - Else: perilaku back biasa (canGoBack / keluar app)

5) ProGuard: keep method interface di atas (sama seperti getToken FCM).

UJI di APK release:
[ ] Solo / live siswa: gate "Mulai mode fokus" muncul & bisa mulai tanpa Fullscreen API browser.
[ ] Home / pindah app → kembali: overlay meninggalkan sesi; guru lihat kolom Keluar fokus.
[ ] Tombol Back saat fokus: overlay, BUKAN navigasi mundur.
[ ] Kumpul / keluar Experience: system bars normal; tidak spam log pelanggaran.
[ ] APK tanpa method baru tetap tidak crash (web fallback CSS saja).
```
