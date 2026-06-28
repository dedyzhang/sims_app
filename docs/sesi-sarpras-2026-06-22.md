# Catatan Sesi — Integrasi & Pengembangan Modul Sarpras

**Tanggal:** 22 Juni 2026
**Branch:** `punya-pak-felli`
**Aplikasi:** SIMS MW (Laravel 12, SQLite, Tailwind CDN + Alpine)

Dokumen ini merekam seluruh pekerjaan pada sesi ini agar bisa dibuka & dilanjutkan kapan saja.

---

## Ringkasan permintaan (kronologis)

1. **Pull GitHub / update terbaru** — fast-forward `16f4428 → ff82f02` (fitur Classroom, Agenda, Forum, Nilai/Rapor, Ekskul). DB lokal lama di-backup ke `database/database.backup-20260622.sqlite`.
2. **`composer install` + `php artisan migrate`** — 2 paket baru (`intervention/image`, `intervention/gif`); migrasi "Nothing to migrate".
3. **`php artisan serve`** — server dev di http://127.0.0.1:8000.
4. **Gabungkan aplikasi Sarpras MW (`D:\SARPRAS MW`) ke SIMS** + samakan tema + migrate tanpa error.
5. **Fix:** `pelapor_id` null saat simpan kerusakan (User PK = uuid).
6. **Fix:** foto kerusakan tidak tampil (perlu `storage:link`).
7. **Tambah** kategori/ruang booking + item sidebar **Booking Ruangan** + 6 ruang fasilitas.
8. **Gabung Peminjaman Aset + Booking Ruangan jadi 1 alur terintegrasi.**
9. **Denah multi-lantai** (tambah lantai per gedung + pemilih lantai).
10. **Menggambar denah di aplikasi** (sketsa kanvas + blok ruangan).
11. **Tombol Import Denah** dari file gambar (jpg/png/webp/gif/bmp).
12. **Resize blok ruangan 4 sisi + 4 sudut** (bukan hanya 1 pojok).
13. **Toggle Grid + snap-to-grid** pada editor blok ruangan.

---

## Keputusan arsitektur (adaptasi Sarpras → SIMS)

Sarpras dibangun standalone (multi-tenant + spatie). SIMS single-sekolah & tanpa spatie. Adaptasi:

| Aspek | Standalone | Jadi di SIMS |
|---|---|---|
| Otorisasi | spatie/permission (`permission:`) | **Gate native** dipetakan ke `users.access`; route `can:`; `@can` tetap jalan (lihat `App\Sarpras\SarprasServiceProvider`) |
| Multi-tenant | `school_id` FK→`schools` | single tenant: `school_id` uuid nullable, diisi konstan oleh `SarprasModel` |
| FK user | `foreignId` (bigint) | `foreignUuid` → `users.uuid` |
| Audit | spatie/activitylog | dibuang (RoleController & Laporan Aktivitas dinonaktifkan) |
| Layout | Tailwind CDN standalone | `@extends('layouts.app')` SIMS (sidebar/topbar/tema) |
| Nama user | kolom `name` | accessor `User::getNameAttribute()` → guru.nama / siswa.nama / username |

Pemetaan role Gate: `MANAGE = [superadmin, admin, sapras]`; `STAFF = MANAGE + kepala, kurikulum, kesiswaan, sekretaris, walikelas, guru`.

---

## Lokasi kode penting

- Modul: `app/Sarpras/` (Models, Http/Controllers, Http/Requests, Services, Notifications, Console, Concerns, Support, `SarprasServiceProvider.php`)
- Rute: `routes/sarpras.php` (prefix `sarpras`, name `sarpras.`)
- View: `resources/views/sarpras/`
- Migration: `database/migrations/2026_06_22_*` (18 tabel `sarpras_*` + `..._add_ruangan_to_sarpras_peminjaman` + `..._add_ukuran_to_sarpras_denah_ruangan`)
- Seeder: `database/seeders/SarprasSeeder.php` (idempotent: kategori, denah, ruang fasilitas, aset, teknisi)
- Provider terdaftar di `bootstrap/providers.php`; scheduler di `routes/console.php`
- Menu sidebar: grup **"Sarana & Prasarana"** di `resources/views/layouts/app.blade.php`
- Accessor nama user: `app/Models/User.php` (`getNameAttribute`)

## Fitur Denah (gambar di aplikasi)
- Editor sketsa kanvas: `resources/views/sarpras/denah/gambar.blade.php` → `DenahController@editorGambar` / `@simpanGambar` (POST `denah/{denah}/gambar`)
- Import gambar: `denah/partials/import-button.blade.php` → `DenahController@imporGambar` (POST `denah/{denah}/import`); format jpg/jpeg/png/webp/gif/bmp
- Editor blok ruangan: `denah/hotspot.blade.php` — blok berlabel, resize 4 sisi + 4 sudut (anchor sisi seberang), **toggle Grid + snap** (2.5/5/10%, disimpan di localStorage)
- Ukuran ruangan: kolom `lebar`,`tinggi` (persen) di `sarpras_denah_ruangan`
- `FotoCompressor::compressString()` untuk simpan gambar dari kanvas (base64)

## Peminjaman terintegrasi
- Satu form (`peminjaman/create`): keperluan + periode (mulai/selesai) + ruangan opsional + aset opsional (min salah satu)
- Cek bentrok ruangan via `Peminjaman::scopeBentrok` (parse Carbon dulu — hindari bug banding string `T` vs spasi di SQLite)
- Booking lama dihapus (controller/request/view/route); model+tabel `BookingRuangan` dibiarkan dorman

---

## Status & catatan operasional
- ✅ Semua migrasi jalan tanpa error.
- ✅ `php artisan storage:link` sudah dibuat (junction `public/storage`). **Wajib diulang saat deploy ke server lain.**
- ✅ GD extension aktif (kompresi foto jalan).
- Server dev: `php artisan serve` → http://127.0.0.1:8000
- Akses modul: login user `access` superadmin/admin/sapras → menu **Sarana & Prasarana**.
- Belum ada commit git untuk pekerjaan ini (perubahan masih di working tree).

## Data demo yang diseed (opsional dibersihkan)
- Denah "Gedung A - Lantai 1" (7A/7B/8A/LAB-IPA/PERPUS), aset contoh, teknisi "Pak Budi" — duplikat nama dgn ruang fasilitas; bisa dihapus bila ingin daftar bersih.

## Cara membuka kembali percakapan sesi ini
- `claude --continue` → lanjutkan sesi terakhir, atau
- `claude --resume` → pilih sesi dari daftar.
- Dokumen ini + memori project menjadi ringkasan konteks bila sesi sudah ter-rotasi.
