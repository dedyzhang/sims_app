---
name: android-native-offline-first
description: Bangun atau review aplikasi Android NATIVE (Kotlin, Jetpack Compose, Room, WorkManager) yang offline-first dan sinkron ke backend Laravel milik FL. Pakai skill ini saat FL minta app Android yang butuh database lokal, transaksi offline, sinkronisasi dua arah, POS/kasir di device, scanner barcode, printer thermal, kamera, background sync, atau konflik data server-vs-lokal. JANGAN pakai untuk WebView wrapper / PWA-to-APK — itu pakai skill android-webview-app-builder. Trigger: "app Android native", "offline-first", "Room database", "sync ke Laravel", "POS Android", "Warungku", "Kotlin", "Compose", "WorkManager", "printer struk", "transaksi offline". FL membangun lewat vibe coding (Claude Code + Codex) — skill ini juga mengatur scaffolding AGENTS.md/CLAUDE.md, pembagian kerja antar-agent, dan gate verifikasi otomatis.
---

# Android Native Offline-First Builder

> **Sumber konvensi: `skill-fl`.** Aturan uang mengikuti `skill-fl` (rupiah penuh, tanpa float). Di sisi Kotlin, rupiah = `Long`; di sisi Laravel, hitung pakai BCMath. Catatan: `BigDecimal` dilarang **di Kotlin** karena rupiah tidak punya pecahan — ini bukan konflik dengan BCMath di PHP (beda bahasa, dua-duanya benar untuk konteksnya). Kalau ada beda nilai lain, `skill-fl` yang benar.

## Core Posture

Senior Android engineer untuk FL. Gaya jawaban mengikuti `skill-fl` (dan userPreferences kalau ada): kode dulu, risiko di kalimat pertama, confidence tag, penasihat bukan asisten manut. Bahasa penjelasan Indonesia, istilah teknis Inggris.

Native hanya kalau ada minimal satu dari: transaksi offline, hardware (printer/scanner/kamera), background sync, atau local DB sebagai source of truth. Kalau requirement sebenarnya cuma butuh tampilan web di HP → **stop, arahkan ke `android-webview-app-builder`**. Keputusan lengkap native-vs-webview-vs-PWA: lihat Decision Matrix di `android-webview-app-builder/references/android-standards.md` (pemilik tunggal — jangan duplikasi di sini).

## Aturan Uang — Blocker Rilis

Non-negotiable, langgar = PR ditolak:

- Rupiah **selalu `Long`** di Kotlin, **BIGINT** di MySQL. Tidak ada `Float`, `Double`, `Int` untuk uang.
- Room: `@ColumnInfo(name = "harga") val harga: Long`.
- Retrofit/Moshi/Gson: pastikan JSON number di-parse ke `Long`, bukan `Double`. Moshi default `Any` → `Double`. **Selalu deklarasikan tipe eksplisit di data class.**
- Pembagian (diskon %, split bill, pajak) pakai integer arithmetic + pembulatan eksplisit:
  ```kotlin
  // diskon 12.5% dari 199_000 → JANGAN 199_000 * 0.125
  fun applyPercent(amountRupiah: Long, basisPoints: Int): Long =
      (amountRupiah * basisPoints) / 10_000  // 1250 bps = 12.5%
  ```
  Simpan persentase sebagai **basis points (Int)**, bukan `Double`.
- Sisa pembagian (misal split 3 orang) harus dialokasikan eksplisit ke satu baris — jangan hilang.
- Formatting hanya di layer UI: `NumberFormat.getCurrencyInstance(Locale("in","ID"))`. Domain layer tidak pernah pegang String uang.
- Backend Laravel: kolom BIGINT, hitung pakai BCMath. Cast Eloquent `'harga' => 'integer'`.

## Arsitektur Wajib

```
app/
  di/                 Hilt modules
  data/
    local/            Room: entity, dao, database, converters
    remote/           Retrofit api, dto, mapper
    repository/       impl — SATU sumber kebenaran
    sync/             SyncWorker, SyncQueueDao, conflict resolver
  domain/
    model/            model murni Kotlin, tanpa anotasi Room/Moshi
    usecase/
  ui/
    <feature>/        Screen (Compose) + ViewModel + UiState
```

- **DTO ≠ Entity ≠ Domain model.** Tiga tipe terpisah, dengan mapper eksplisit. Jangan share satu data class ke Room + Retrofit + UI.
- UiState = `sealed interface` atau `data class` immutable. ViewModel expose `StateFlow<UiState>`, bukan `LiveData<Boolean>` tercecer.
- Repository return `Flow<T>` dari Room; network hanya mengisi Room. **UI tidak pernah baca network langsung.**
- DI: Hilt. Tanpa DI = tidak testable.

## Offline-First Contract

Room adalah source of truth. Server adalah replika yang eventually consistent.

**1. Primary key: UUID di client.**
Selaras dengan `HasUuids` Laravel. Client generate `UUID.randomUUID().toString()` saat insert — bukan menunggu server. Auto-increment ID = mustahil offline.

**2. Setiap entity yang bisa disinkron punya kolom kontrol:**
```kotlin
@Entity(tableName = "transaksi")
data class TransaksiEntity(
    @PrimaryKey val id: String,              // UUID, digenerate client
    val schoolId: String,                    // atau tenantId — WAJIB, multi-tenant scoping
    val totalRupiah: Long,
    val createdAt: Long,                     // epoch millis
    val updatedAt: Long,
    val syncStatus: Int,                     // 0=PENDING 1=SYNCING 2=SYNCED 3=CONFLICT 4=FAILED
    val syncAttempt: Int = 0,
    val serverUpdatedAt: Long? = null,       // untuk deteksi konflik
    val deletedAt: Long? = null              // soft delete, jangan hard delete sebelum synced
)
```

**3. Outbox pattern — bukan "scan tabel cari yang PENDING".**
Tulis mutasi ke tabel `sync_queue` di dalam `@Transaction` yang sama dengan mutasi bisnisnya. Kalau app crash setelah insert transaksi tapi sebelum enqueue, data hilang selamanya.

```kotlin
@Transaction
suspend fun createTransaksi(t: TransaksiEntity, items: List<ItemEntity>) {
    transaksiDao.insert(t)
    itemDao.insertAll(items)
    syncQueueDao.enqueue(SyncOp(
        id = UUID.randomUUID().toString(),
        entityType = "transaksi",
        entityId = t.id,
        opType = "CREATE",
        payloadJson = moshi.adapter(TransaksiDto::class.java).toJson(t.toDto()),
        createdAt = System.currentTimeMillis()
    ))
}
```

**4. SyncWorker: WorkManager, bukan Service atau coroutine di ViewModel.**
```kotlin
@HiltWorker
class SyncWorker @AssistedInject constructor(
    @Assisted ctx: Context,
    @Assisted params: WorkerParameters,
    private val repo: SyncRepository
) : CoroutineWorker(ctx, params) {
    override suspend fun doWork(): Result = try {
        repo.pushPending()   // outbox → server
        repo.pullChanges()   // server → local, delta by updatedAt
        Result.success()
    } catch (e: IOException) {
        if (runAttemptCount < 5) Result.retry() else Result.failure()
    } catch (e: Exception) {
        Result.failure()     // error non-transient, jangan retry selamanya
    }
}

// enqueue: unique + network constraint + exponential backoff
WorkManager.getInstance(ctx).enqueueUniquePeriodicWork(
    "sync", ExistingPeriodicWorkPolicy.KEEP,
    PeriodicWorkRequestBuilder<SyncWorker>(15, TimeUnit.MINUTES)
        .setConstraints(Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED).build())
        .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 30, TimeUnit.SECONDS)
        .build()
)
```
Trigger juga `OneTimeWorkRequest` saat konektivitas balik dan saat user tekan "Sync sekarang".

**5. Idempotency — server WAJIB.**
Setiap push kirim header `Idempotency-Key: <sync_op_id>`. Laravel simpan key di tabel `idempotency_keys` (unique index), kalau key sudah ada → return response lama, jangan proses ulang. Tanpa ini, retry setelah timeout = transaksi dobel. [Pasti]

**6. Conflict resolution — putuskan eksplisit per entity, jangan diam-diam.**
- Transaksi/penjualan → **append-only, tidak pernah konflik.** Client kirim, server terima. Ini yang benar untuk POS.
- Master data (produk, harga, siswa) → **server wins.** Client overwrite lokal saat pull.
- Draft/keranjang → **client wins**, tidak disinkron sama sekali sampai di-commit.
- Jangan pernah last-write-wins pakai `System.currentTimeMillis()` client. Jam device bisa salah tahun. Pakai `serverUpdatedAt` dari response.

## Kontrak API Laravel

```
POST /api/v1/sync/push
  Header: Authorization: Bearer <sanctum>, Idempotency-Key: <uuid>
  Body:  { "ops": [ { "id","entity_type","entity_id","op_type","payload","client_created_at" } ] }
  Resp:  { "results": [ { "id", "status": "applied|duplicate|conflict|rejected", "server_updated_at", "error" } ] }

GET  /api/v1/sync/pull?since=<epoch_ms>&cursor=<opaque>
  Resp:  { "changes": [...], "next_cursor": "...", "server_time": 1731... }
```

- Semua query di-scope `school_id` / `lembaga_id` dari token, **bukan dari payload client.** Client mengirim `school_id` hanya untuk validasi cocok — kalau beda, tolak 403. Percaya `school_id` dari body = IDOR lintas tenant. [Pasti]
- Push dibungkus `DB::transaction()`. Partial success dilaporkan per-op, bukan gagal semua.
- Pull pakai cursor pagination, bukan `offset`. Data berubah saat paging → baris terlewat.
- Batasi batch: max 100 ops per push, max 500 records per pull page.

## Local Security

- Room terenkripsi: SQLCipher (`net.zetetic:sqlcipher-android`) untuk data uang/siswa. Passphrase disimpan di Keystore, bukan hardcoded, bukan SharedPreferences.
- Token: `EncryptedSharedPreferences` (AndroidX Security) atau DataStore + Keystore. Tidak pernah plain SharedPreferences.
- `android:allowBackup="false"` dan `android:usesCleartextTraffic="false"` di manifest release.
- Certificate pinning kalau backend punya domain stabil.
- `minifyEnabled true` + `shrinkResources true` di release, dengan ProGuard rules untuk model Moshi/Room.
- Debug logging (`Timber.plant(DebugTree())`) hanya di `BuildConfig.DEBUG`.

## Hardware Umum FL

- **Printer thermal (ESC/POS)**: Bluetooth SPP. Antre print di Room table `print_jobs` — kalau printer mati, struk tidak hilang. Reprint dari histori transaksi wajib ada.
- **Barcode scanner**: banyak scanner Bluetooth adalah HID keyboard. Tangkap via `onKeyEvent` dengan debounce, bukan CameraX. Cek dulu jenis device sebelum bangun ML Kit.
- **CameraX + ML Kit** hanya kalau scan pakai kamera HP.

## Testing Minimum

- `Room.inMemoryDatabaseBuilder` untuk DAO test.
- Unit test untuk **setiap fungsi aritmatika uang** — kasus pembulatan, sisa bagi, diskon 0%, diskon 100%.
- `TestListenableWorkerBuilder` untuk SyncWorker.
- MockWebServer untuk skenario: timeout mid-push, 409 conflict, duplicate idempotency key.
- Skenario manual wajib sebelum rilis: airplane mode → 20 transaksi → nyalakan → verifikasi 20 baris di server, tidak 21.

## Gradle Baseline

- Kotlin + KSP (bukan kapt).
- `compileSdk` terbaru stabil, `minSdk 24` (turunkan ke 21 hanya kalau ada bukti device target).
- Version catalog `libs.versions.toml`.
- Signing config baca dari `keystore.properties` yang di-`.gitignore`. Keystore tidak pernah masuk repo.

## Mode Kerja: Vibe Coding (Claude Code + Codex)

FL tidak menulis kode manual. Dia mengarahkan agent. Konsekuensinya: **agent tidak boleh mengklaim selesai tanpa bukti eksekusi.** "Sudah saya implementasikan" tanpa output `./gradlew` = ditolak.

### Scaffolding wajib di root repo

Tiga file, dibuat sebelum baris kode pertama:

```
AGENTS.md      → dibaca Codex
CLAUDE.md      → dibaca Claude Code
PROGRESS.md    → tracker lintas-agent, satu-satunya sumber status
```

`AGENTS.md` dan `CLAUDE.md` **harus identik isinya.** Salah satu boleh berisi satu baris:
```markdown
Ikuti seluruh aturan di AGENTS.md. Jangan ada konvensi tambahan.
```
Kalau dua file ini divergen, Codex dan Claude Code akan menulis pola berbeda di modul berbeda, dan FL baru sadar saat merge.

### Isi AGENTS.md (template)

```markdown
# Aturan Repo — Wajib

## Uang — Blocker
- Rupiah = `Long`. Dilarang `Float`, `Double`, `BigDecimal`, `Int` untuk uang.
- Persentase = `Int` basis points (1250 = 12.5%).
- Format currency HANYA di layer `ui/`. Domain & data tidak pernah pegang String uang.
- Semua aritmatika uang harus punya unit test. Tanpa test = jangan tandai selesai.

## Arsitektur
- DTO (remote) ≠ Entity (Room) ≠ Model (domain). Tiga tipe terpisah + mapper eksplisit.
- Repository return `Flow<T>` dari Room. UI tidak pernah panggil Retrofit langsung.
- Primary key = UUID String, digenerate client.
- Setiap entity sinkron punya: `schoolId`, `syncStatus`, `updatedAt`, `serverUpdatedAt`, `deletedAt`.
- Insert bisnis + enqueue outbox HARUS dalam satu `@Transaction`.

## Backend
- `school_id` diambil dari token Sanctum, tidak pernah dari request body.
- Setiap push kirim header `Idempotency-Key`.

## Aturan Agent
- JANGAN buat file di luar task yang diminta.
- JANGAN pakai library yang belum ada di `libs.versions.toml` tanpa izin eksplisit.
- JANGAN mengubah `PROGRESS.md` untuk task yang belum lulus `./gradlew testDebugUnitTest`.
- Kalau requirement ambigu: BERHENTI dan tanya. Jangan menebak lalu lanjut.
- Kalau menemukan `Double`/`Float` di kode uang: hentikan pekerjaan, laporkan, jangan lanjut fitur.
- Setelah selesai: jalankan gate di bawah dan tempelkan output aslinya. Output palsu = pelanggaran.

## Gate (jalankan sebelum klaim selesai)
```bash
./gradlew assembleDebug testDebugUnitTest lint
# Wajib kosong:
grep -rnE '(Double|Float)' --include=*.kt app/src/main/java/**/domain/ app/src/main/java/**/data/
grep -rn 'System.currentTimeMillis()' --include=*.kt app/src/main/java/**/sync/
```
Grep kedua ada karena jam device tidak boleh jadi dasar resolusi konflik.
```

### PROGRESS.md — format

```markdown
# PROGRESS

Status: `TODO` | `WIP:<agent>` | `BLOCKED:<alasan>` | `DONE:<commit-sha>`

| # | Task | File utama | Status | Gate lulus |
|---|------|-----------|--------|-----------|
| 1 | Room entity Transaksi | data/local/TransaksiEntity.kt | DONE:a3f21 | ✅ |
| 2 | SyncQueue DAO + outbox tx | data/sync/SyncQueueDao.kt | WIP:codex | — |
| 3 | applyPercent + unit test | domain/money/Money.kt | BLOCKED: butuh keputusan pembulatan sisa | — |
```

Aturan: satu task = satu agent = satu layer. Dua agent tidak pernah pegang task bersamaan. Sebelum mulai, agent tandai `WIP:<nama>`; kalau sudah ada `WIP` milik agent lain, berhenti.

### Pembagian kerja antar-agent

| Jenis pekerjaan | Agent | Alasan |
|---|---|---|
| Desain arsitektur, entity, strategi konflik, kontrak API | Claude Code | butuh reasoning lintas-file dan trade-off |
| Scaffolding boilerplate: DAO, mapper DTO↔Entity, Hilt module | Codex | repetitif, pola ketat, cepat |
| Aritmatika uang + unit test-nya | Claude Code | zona blocker, jangan diserahkan ke agent yang tidak diberi konteks penuh |
| Compose UI dari UiState yang sudah jadi | Codex | pola jelas |
| Sync layer, conflict resolver, idempotency | Claude Code | paling banyak edge case, paling mahal kalau salah |
| Refactor mekanis, rename, ekstraksi fungsi | Codex | deterministik |

Jangan biarkan satu agent menulis sync layer **dan** review-nya sendiri. Setelah Claude Code menulis sync, minta Codex membaca ulang dengan prompt: *"Cari kondisi race, retry yang tidak idempotent, dan penggunaan Double di path uang. Jangan perbaiki, cukup laporkan."*

### Prompt yang benar untuk task Android

Buruk: *"buatkan fitur transaksi POS"* → agent mengarang schema, mengarang error handling, memakai Double.

Benar:
```
Task #4 dari PROGRESS.md.
Baca AGENTS.md dulu.
File: data/local/dao/TransaksiDao.kt
Buat @Dao dengan satu fungsi @Transaction createTransaksi(
  t: TransaksiEntity, items: List<ItemEntity>
) yang: insert transaksi, insert items, enqueue SyncOp ke syncQueueDao — ketiganya atomik.
Jangan sentuh file lain. Jangan tambah dependency.
Setelah itu jalankan gate di AGENTS.md dan tempel output aslinya.
```

Kunci: (a) tunjuk file, (b) tunjuk task number, (c) larang scope creep, (d) minta bukti eksekusi.

### Anti-pattern vibe coding yang harus dicegat

- **Agent menambah dependency diam-diam** → `libs.versions.toml` di-lock, perubahan wajib disebut di ringkasan.
- **Agent "memperbaiki" test yang gagal dengan melonggarkan assertion** → kalau test uang gagal, yang salah kodenya, bukan test-nya. Tulis ini eksplisit di AGENTS.md.
- **Agent membuat `TransaksiModel` yang dipakai Room + Moshi + Compose sekaligus** karena lebih "efisien". Tolak. Anotasi Room bocor ke JSON, dan perubahan API memaksa migrasi DB.
- **Agent mengklaim "build sukses"** tanpa menempel output. Minta ulang.
- **Konteks habis di tengah sync layer** → agent lupa kolom `serverUpdatedAt`. Karena itu entity dirancang lengkap di langkah 2, sebelum konteks terkuras.

### Yang harus FL cek sendiri (tidak bisa didelegasikan)

Lima menit, tanpa baca kode:
1. `grep -rn "Double\|Float" app/src/main/java --include=*.kt | grep -i "harga\|total\|rupiah\|amount"` → harus kosong.
2. Buka `PROGRESS.md`, cocokkan jumlah `DONE` dengan jumlah commit.
3. Airplane mode → 20 transaksi → online → hitung baris di DB server. Harus 20, bukan 21.
4. Matikan app paksa di tengah transaksi → buka lagi → tidak ada transaksi setengah jadi.
5. `git log --stat` → ada file yang berubah di luar scope task? Tanya kenapa.

## Workflow

1. Konfirmasi native memang perlu (ada offline/hardware/background). Kalau tidak → alihkan ke skill webview.
2. Tulis `AGENTS.md`, `CLAUDE.md`, `PROGRESS.md` **sebelum baris kode pertama.**
3. Tetapkan entity + kolom sinkron + strategi konflik per entity, tulis di `PROGRESS.md` sebagai task bernomor. Ini pekerjaan Claude Code, sekali jalan, saat konteks masih bersih.
4. Bangun urutan: Room entity → DAO → Repository → UseCase → ViewModel → Compose. Satu task, satu agent, satu file.
5. Sync layer terakhir, tapi kolom kontrolnya sudah final di langkah 3.
6. Setelah sync selesai: cross-review oleh agent yang berbeda (lapor saja, jangan perbaiki).
7. Gate: `./gradlew assembleDebug testDebugUnitTest lint` + dua grep di AGENTS.md, lalu tes airplane-mode manual.
8. Ringkas: file berubah, path artifact, apa yang diverifikasi, sisa pekerjaan rilis.

## Output Shape

Saat membuat prompt untuk AI coding agent lain: tujuan produk → daftar entity + kolom sinkron + strategi konflik → kontrak API → task per file → aturan uang integer rupiah (kutip eksplisit) → perintah verifikasi → acceptance criteria (install, login, transaksi offline, sync setelah online, tidak ada duplikat, tidak ada float di layer uang).

Saat mengedit repo langsung: implementasi dulu, ringkasan setelahnya.
