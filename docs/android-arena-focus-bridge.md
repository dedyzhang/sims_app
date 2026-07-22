# Android WebView — Bridge Mode Fokus Arena

> **STATUS: APK DITAHAN** — jangan kerjakan FASE 12 / update APK dulu.
> Mode fokus **website** sudah aktif (`$holdArenaFocusLock = false`).
> Bridge native di bawah ini hanya untuk rilis APK berikutnya.

---

Sumber web sudah siap di `resources/views/components/arena-focus-lock.blade.php`.
APK WebView harus menambah method di **JavascriptInterface yang sama** dengan FCM (`AndroidFcm`), lalu handle tombol Back.

## Kontrak JS ↔ Native

Web memanggil (bila ada):

| Method | Kapan | Efek native yang diharapkan |
|--------|--------|------------------------------|
| `AndroidFcm.enterArenaFocus()` | Siswa ketuk “Mulai mode fokus” | Immersive sticky: sembunyikan status/nav bar; set flag fokus aktif |
| `AndroidFcm.exitArenaFocus()` | Navigasi sah (kumpul / kembali) | Kembalikan system bars; clear flag |
| `AndroidFcm.setArenaFocusActive(boolean)` | Opsional sync flag | Mirror `window.__arenaFocusActive` |

Native memanggil ke WebView:

| JS | Kapan | Return |
|----|--------|--------|
| `window.arenaFocusAndroidBack()` | `onBackPressed` saat fokus aktif | `true` = jangan `goBack()`; web sudah log + tampil overlay |
| Baca `window.__arenaFocusActive` | Sebelum memutuskan back | `true` bila sesi fokus berjalan |

Web juga mendeteksi WebView lewat keberadaan `window.AndroidFcm` / `AndroidArena`, lalu:
- **skip Fullscreen API** → pakai CSS `.arena-is-fullscreen`
- reason log: `pindah aplikasi` / `tombol back Android`

## Cuplikan Kotlin (tempel ke interface FCM existing)

```kotlin
// Di class @JavascriptInterface (mis. AndroidFcmBridge)
@JavascriptInterface
fun enterArenaFocus() {
    activity.runOnUiThread {
        activity.window.insetsController?.let { c ->
            c.hide(WindowInsets.Type.statusBars() or WindowInsets.Type.navigationBars())
            c.systemBarsBehavior =
                WindowInsetsController.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
        }
        arenaFocusActive = true
    }
}

@JavascriptInterface
fun exitArenaFocus() {
    activity.runOnUiThread {
        activity.window.insetsController?.show(
            WindowInsets.Type.statusBars() or WindowInsets.Type.navigationBars()
        )
        arenaFocusActive = false
    }
}

@JavascriptInterface
fun setArenaFocusActive(active: Boolean) {
    arenaFocusActive = active
}

@JavascriptInterface
fun isArenaFocusActive(): Boolean = arenaFocusActive
```

## Tombol Back di MainActivity

```kotlin
override fun onBackPressed() {
    if (arenaFocusActive || /* atau evaluate JS __arenaFocusActive */) {
        webView.evaluateJavascript(
            "(function(){return window.arenaFocusAndroidBack&&window.arenaFocusAndroidBack()?1:0;})()",
            { result ->
                if (result == "1" || result == "true") {
                    // tetap di halaman; overlay “kembali ke mode fokus” dari web
                } else if (webView.canGoBack()) {
                    webView.goBack()
                } else {
                    super.onBackPressed()
                }
            }
        )
        return
    }
    if (webView.canGoBack()) webView.goBack() else super.onBackPressed()
}
```

**ProGuard:** keep semua method `@JavascriptInterface` di bridge (sudah disyaratkan Fase 10 FCM).

## Uji manual di APK

1. Login siswa → buka Arena solo (token) / live / template match.
2. Muncul gate **Mulai mode fokus** (bukan stuck menunggu Fullscreen API).
3. Pindah ke WhatsApp / Home → kembali ke app → overlay “meninggalkan sesi” + kolom **Keluar fokus** di hasil guru bertambah.
4. Tekan Back Android saat fokus → overlay muncul, **bukan** navigasi mundur WebView.
5. Kumpul jawaban / tombol Experience → bars kembali normal, tidak terhitung pelanggaran ekstra.

## Catatan

- Tidak perlu endpoint API baru — log tetap `POST …/arena-belajar/{quiz}/fokus-keluar`.
- Bila APK lama belum punya method bridge: web tetap jalan (CSS immersive + `visibilitychange`); Back Android mungkin masih `goBack` sampai APK di-update.
