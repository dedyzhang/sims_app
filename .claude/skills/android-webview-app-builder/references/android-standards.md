# Android Standards

Use this reference for concrete engineering decisions while building or reviewing Android WebView, PWA wrapper, TWA, hybrid, or native Android apps.

## Decision Matrix

Choose WebView wrapper when:

- The main product already exists as a mobile-responsive Laravel/web app.
- The app mostly needs login, dashboard, forms, reports, and content pages.
- Native features are light: file upload, camera picker, download, push notification, deep link.
- Fast APK/AAB delivery matters more than native UI polish.

Choose TWA/PWA when:

- The website has HTTPS, service worker, manifest, icons, and installable behavior.
- Browser parity is acceptable.
- The goal is cheap distribution with minimal native shell code.

Choose native Android when:

- Offline-first behavior is central, not just a fallback error page.
- The app needs reliable background work, GPS/camera/bluetooth, biometric login, local database, or complex notifications.
- The web UI feels slow or awkward on mobile even after responsive fixes.

## WebView Baseline

Preferred project shape:

- `MainActivity.kt` owns the WebView, back navigation, loading/error states, and lifecycle glue.
- `WebViewClient` handles host allowlist, SSL/mixed-content policy, navigation, and error pages.
- `WebChromeClient` handles file chooser, permissions, console diagnostics in debug, and progress.
- `DownloadListener` delegates downloads through `DownloadManager` or an external browser.
- `AndroidManifest.xml` declares only required permissions.

Secure defaults:

```kotlin
webView.settings.javaScriptEnabled = true
webView.settings.domStorageEnabled = true
webView.settings.allowFileAccess = false
webView.settings.allowContentAccess = false
webView.settings.mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
webView.settings.setSupportZoom(false)
CookieManager.getInstance().setAcceptCookie(true)
CookieManager.getInstance().setAcceptThirdPartyCookies(webView, false)
```

Only relax defaults for a named feature and explain the reason in code or summary.

Navigation rules:

- Allow only production/staging hosts owned by the project.
- Open WhatsApp, mailto, tel, maps, payment apps, and unrelated external links with Android intents.
- Keep deep link routing explicit in the manifest and server routes.
- Handle `onReceivedError` and `onReceivedHttpError` with a branded local error view.

Auth rules:

- Prefer normal web session cookies for WebView wrappers.
- Use HTTPS and Secure/SameSite cookie settings on the Laravel side.
- Do not pass long-lived API tokens through query strings.
- Do not expose native bridges with `addJavascriptInterface` unless absolutely required; if used, limit methods and validate origin.

## Native Android Baseline

Use this structure unless the existing app has a clear convention:

- `ui/`: screens, composables/fragments/activities, navigation.
- `presentation/`: ViewModels and UI state.
- `domain/`: use cases and domain models for non-trivial logic.
- `data/remote/`: API client, DTOs, auth interceptors.
- `data/local/`: Room/DataStore/cache.
- `data/repository/`: data orchestration.
- `core/`: app-wide utilities, network state, result wrappers.

Preferred libraries when already compatible with the repo:

- Kotlin coroutines and Flow for async work.
- Retrofit/OkHttp or Ktor client for APIs.
- DataStore for small local preferences/token metadata.
- Room for relational offline cache.
- WorkManager for reliable deferred sync.
- Firebase Cloud Messaging for push notifications when push is required.

## Laravel Backend Expectations

When Android depends on a Laravel backend:

- Use Laravel API routes/resources for mobile endpoints.
- Use Sanctum or Passport only after matching the auth model to the app need.
- Keep tenant scoping server-side with `school_id`, `tenant_id`, or `outlet_id`; never trust the app to enforce tenant boundaries.
- Return mobile-friendly JSON with stable error shapes.
- Rate-limit auth and public endpoints.
- Money fields returned to the app (balances, prices, payment amounts) must stay integer rupiah (BIGINT) end-to-end — never serialize as float/decimal. Compute with BCMath on the Laravel side; the app only displays and never recalculates money.
- For money, payments, school/student data, and attendance/grades, log sensitive writes with audit trail.

## Build And Release Checklist

Before claiming production readiness, verify as much as the environment allows:

- `./gradlew assembleDebug`
- `./gradlew lint` when configured.
- `./gradlew test` when tests exist.
- `./gradlew assembleRelease` or `./gradlew bundleRelease` for release artifacts.
- Signing config exists outside committed secrets.
- App ID, app label, versionCode, and versionName are correct.
- Icons and splash screen render correctly.
- Cleartext traffic is disabled for production.
- WebView target host works on device/emulator.
- Login, logout, back navigation, file upload/download, offline/error state, and external intents are smoke-tested.

## Common Senior Review Findings

Call these out directly when found:

- WebView allows every URL without host filtering.
- Mixed content or cleartext HTTP is enabled in production.
- APK signing keys or passwords are committed.
- `addJavascriptInterface` exposes broad native methods.
- Back button exits the app instead of navigating WebView history.
- File upload permissions are requested too broadly.
- Laravel app is not mobile-responsive, making a WebView shell feel broken.
- Auth token is stored in SharedPreferences without a strong reason or expiry.
- No offline/error screen, so network failure creates a blank app.
- No release build verification before sharing APK/AAB.
