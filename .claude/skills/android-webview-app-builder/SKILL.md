---
name: android-webview-app-builder
description: Build or review Android applications as WebView wrappers, PWA-to-APK/AAB packages, or native Android apps with senior engineering standards. Use when the user asks to make an Android app, convert a Laravel/web/PWA site into an Android WebView app, create APK/AAB, configure Android Studio/Gradle/Kotlin/Java WebView, implement mobile app architecture, add push notifications, downloads/uploads, deep links, offline behavior, Play Store readiness, signing, or review an Android/WebView project for production quality.
---

# Android WebView App Builder

> **Sumber konvensi: `skill-fl`.** Money/tenant rules mengikuti `skill-fl` (integer rupiah BIGINT+BCMath, `school_id`/`outlet_id` scope server-side). Skill ini adalah **pemilik tunggal Decision Matrix native-vs-webview-vs-PWA** (`references/android-standards.md`) — skill `android-native-offline-first` merujuk ke sini, jangan duplikasi matrix di sana.

## Core Posture

Act as a senior Android engineer for FL: execute quickly, but stop bad mobile decisions before they become expensive. Default to Indonesian explanations, Android/Kotlin/Gradle terms in English, and concrete files/commands when working in a repo.

Prefer the simplest production-ready path:

- Use a WebView wrapper when the product is already a responsive Laravel/web app, needs fast release, and does not require heavy native device features.
- Use PWA first when installability, offline cache, and browser parity solve the problem without Play Store pressure.
- Use native Android when the app needs reliable offline-first sync, background services, camera/location/bluetooth, complex push actions, local database, or Play Store-grade UX beyond a website shell.
- Keep Laravel as the default backend/API when the app needs server data, auth, roles, payments, reports, or admin panels.

## Workflow

1. Inspect the target: existing repo, Android Gradle project, web URL, Laravel app, PWA manifest, auth flow, and mobile breakpoints.
2. Choose app mode explicitly: WebView wrapper, PWA/TWA, hybrid shell, or native Android. State the trade-off briefly before editing.
3. Build with production defaults: Kotlin preferred for new Android code, AndroidX, minSdk that matches device reality, versioned Gradle config, secure WebView settings, and clean app structure.
4. Integrate app capabilities only when needed: file upload/download, camera/gallery picker, push notification, deep link, back navigation, pull-to-refresh, offline/error screen, network status, and external link handling.
5. Verify with real commands when possible: Gradle build, lint/tests if present, APK/AAB signing checks, emulator/device smoke test, and HTTP/WebView behavior checks.
6. Deliver a concise summary: changed files, build artifact path, what was verified, and any remaining Play Store/release work.

## Required Standards

For WebView apps:

- Enable JavaScript only when required by the site.
- Keep `setAllowFileAccess(false)` and avoid broad file/content access unless a specific feature requires it.
- Block mixed content by default and require HTTPS in production.
- Use a strict host allowlist; open unknown external hosts in the browser.
- Do not inject credentials, tokens, or raw cookies into JavaScript.
- Support Android back button inside WebView history before exiting the Activity.
- Provide custom offline/loading/error UI instead of leaving a blank WebView.
- Handle file chooser, downloads, permissions, and external intents deliberately.

For native apps:

- Separate UI, domain/use case, data repository, and network/local storage layers.
- Use ViewModel and lifecycle-aware state. Prefer Kotlin coroutines/Flow for async work.
- Validate auth/session handling, token refresh, tenant scoping, and error states.
- For money, payments, school data, student data, or transactions, treat security and auditability as release blockers.

## References

Read `references/android-standards.md` before designing or editing a non-trivial Android/WebView project, especially when the task touches security, release signing, push notifications, offline sync, file upload/download, deep links, or Play Store submission.

## Output Shape

When creating a prompt/spec for another AI coding tool, include:

- Product goal and chosen app mode.
- Tech stack: Android Kotlin/Gradle plus Laravel API/backend when server features are needed.
- File-by-file tasks.
- Security rules and WebView/native constraints.
- Verification commands.
- Acceptance criteria for install, login, navigation, offline/error state, build artifact, and release readiness.

When editing a repo directly, implement first, then summarize only what changed and what was verified.
