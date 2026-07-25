---
name: laravel-security-audit
description: "Checklist audit keamanan untuk aplikasi Laravel multi-tenant milik FL (sims_app/B'tive, BimbelPro, EduNest, laundry app) — semua pakai konvensi school_id/tenant scoping, UUID, integer rupiah. WAJIB pakai skill ini setiap kali FL minta 'security patch', 'audit keamanan', 'harden', 'cek vulnerability', 'security review', atau minta Claude 'perkuat' aplikasi/modul yang sudah ada. Juga trigger kalau FL paste kode (controller/model/route/migration) dan minta dicek sebelum deploy atau sebelum submit ke sekolah/klien. Jangan tunggu FL bilang kata 'skill' — begitu konteksnya security review pada codebase Laravel miliknya, pakai skill ini."
---

# Laravel Security Audit — FL's Multi-Tenant Apps

> **Sumber konvensi: `skill-fl`.** Kriteria cek uang/tenant/UUID di area 1 & 4 di-restate di sini supaya audit bisa jalan mandiri. Definisi kanonik ada di `skill-fl`; kalau ragu nilai yang benar (mis. rupiah penuh vs sen), rujuk `skill-fl`.
>
> **Pembagian peran:** `skill-fl` = konvensi PREVENTIF (aturan yang ditulis ke kode sejak awal saat membangun). Skill INI = pemeriksaan DETEKTIF (cari pelanggaran konvensi itu di kode yang sudah jadi, sebelum deploy). Skill ini tidak mengajarkan ulang konvensi — fokusnya mendeteksi di mana konvensi bocor.
>
> **BATAS vs plugin generic engineering:code-review:** trigger dua-duanya mirip ("review kode ini", "cek sebelum merge"). Skill INI WAJIB dipakai kalau kode yang direview menyentuh app Laravel milik FL (ada `school_id`/`lembaga_id`, uang rupiah, atau multi-tenant) — plugin generic cuma cek OWASP top-10 umum dan TIDAK tahu cek tenant-scoping IDOR, BCMath rounding, atau idempotency webhook yang jadi risiko spesifik FL. Plugin generic hanya cukup untuk kode non-Laravel/non-FL-stack.

Skill ini dipakai untuk audit keamanan kode Laravel milik FL. Semua project FL share konvensi yang sama: multi-tenant (`school_id`/`tenant_id` scoping), UUID primary key, uang integer rupiah (BCMath), Spatie permission, Blade views.

## Cara kerja

1. **Kalau FL belum kasih kode** — jangan menebak, minta yang spesifik: file Controller + Model + Route + Migration terkait (bukan cuma satu file, karena kerentanan tenant-isolation/IDOR cuma kelihatan dari relasi antar-file). Prioritaskan modul yang menyentuh uang (bendahara/SPP/pembayaran) kalau FL nggak spesifik mau modul mana.

2. **Audit pakai 7 area di bawah**, urutkan temuan dari CRITICAL. Setiap temuan format:
   `[SEVERITY] file:line → Risiko konkret → Patch (kode/diff siap-paste)`
   Area yang sudah aman: tulis "OK" satu baris, jangan padding penjelasan panjang.

3. **Jangan ubah business logic.** Patch hanya untuk keamanan, tetap fungsional.

4. Tutup dengan: ringkasan jumlah temuan per severity, urutan eksekusi patch yang disarankan, dan tandai patch mana yang butuh migration/perubahan DB.

## 7 Area Wajib Cek

### 1. Multi-tenant isolation (paling kritis — cek ini duluan)
- Setiap query Eloquent ter-scope `school_id`/`tenant_id`? Cari query tanpa global scope.
- Mass assignment: bisa user kirim `school_id` lewat request lalu nimpa punya tenant lain? Cek `$fillable`/`$guarded`.
- Route model binding: object tenant lain bisa diakses lewat ID di URL (IDOR)?
- Webhook & scheduled job: tenant scope ke-bypass di context non-HTTP?

### 2. Authorization
- Setiap action sensitif punya Policy/Gate? Cari controller method tanpa `authorize()`.
- Role check (Spatie) konsisten di route/middleware?
- Bulk operations cek ownership per-item, bukan cuma role global.

### 3. Input & Injection
- Raw query/`DB::raw`/`whereRaw` dengan input user → parameterized?
- Blade `{!! !!}` render input user tanpa sanitasi (XSS)?
- File upload: validasi MIME real (bukan ekstensi), size limit, rename file, path di luar public bila perlu.

### 4. Uang & integritas data (spesifik ke stack FL)
- Operasi uang pakai integer rupiah/BCMath? Tandai float/`round()` yang berisiko salah bulat.
- Transaksi saldo pakai DB transaction + `lockForUpdate` (race condition di concurrent request)?
- Endpoint pembayaran/webhook idempotent (nggak double-process kalau di-retry)?

### 5. Auth & session
- Rate limiting login (`throttle`), CSRF aktif di form non-API.
- Token/Sanctum scope benar, ada expiry.
- Route sensitif tanpa `auth` middleware?

### 6. Data exposure
- API Resource/response bocorin field sensitif (password hash, token, data tenant lain)?
- `APP_DEBUG=false` di prod — stack trace nggak bocor ke user.
- Log nyimpen data sensitif (password, token, NIK, data siswa)?

### 7. Dependency & config
- `.env` ter-commit ke repo? Secret hardcoded di kode?
- Versi paket dengan CVE diketahui (sebutkan kalau tahu, tandai [Menebak] kalau nggak yakin versi persis).

## Gaya jawaban

Ikuti gaya default FL: langsung ke temuan/patch, tandai confidence ([Pasti]/[Kemungkinan Besar]/[Menebak]) untuk klaim CVE atau severity yang nggak 100% pasti, jangan ada pujian pembuka.
