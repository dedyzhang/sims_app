# Fase B — Matching & Rekonsiliasi Cerdas

Lapisan saran pencocokan mutasi rekening ↔ tagihan SPP berbasis skor aturan, deteksi duplikat/anomali (flag saja), dan notifikasi ringkas antrian menumpuk — tetap human-in-the-loop.

## User stories

### SB-5 — Saran pencocokan mutasi ↔ tagihan (B1)

**Sebagai** bendahara, **saya ingin** melihat skor kecocokan antara baris mutasi rekening koran dan tagihan SPP, **agar** saya cepat memilih pasangan yang benar sebelum menandai lunas.

**Acceptance criteria:**
- Skor dihitung server-side (VA, nominal persis, kedekatan tanggal, kemiripan teks nama) — bukan LLM menghitung rupiah.
- Pratinjau impor rekening koran menampilkan skor dan alasan per baris.
- Bendahara wajib konfirmasi centang manual; tidak ada auto-post.
- Halaman rekonsiliasi di Asisten Bendahara merangkum tagihan `terverifikasi` yang menunggu validasi bank.

### SB-6 — Deteksi duplikat & anomali nominal (B2)

**Sebagai** bendahara, **saya ingin** sistem menandai bukti ganda atau nominal mencurigakan, **agar** saya meninjau sebelum verifikasi — tanpa blok otomatis.

**Acceptance criteria:**
- Flag: bukti path sama dipakai >1 pembayaran, nominal tagihan ≠ tarif siswa, pengajuan ganda (siswa+bulan+nominal sama).
- Flag hanya peringatan visual; alur verifikasi tetap bisa dilanjutkan bendahara.
- Daftar anomali terpusat di `/keuangan/bendahara-ai/anomali`.

### SB-7 — Notifikasi antrian menumpuk (B3)

**Sebagai** bendahara, **saya ingin** notifikasi in-app ringkas saat antrian verifikasi menumpuk, **agar** saya tidak lupa mengecek.

**Acceptance criteria:**
- Ambang batas konfigurasi (`menunggu_min`, `usia_hari_min`).
- Command terjadwal `bendahara:antrian-digest` kirim satu notifikasi database per bendahara per siklus.
- Banner ringkas di hub Asisten Bendahara menampilkan ringkasan tanpa menunggu notifikasi.

## Aturan bisnis

1. **Skor matching v1:** Bobot VA (40) + nominal persis (35) + tanggal ±7 hari (15) + teks nama (10). Maks 100.
2. **Anomali:** Dihitung on-the-fly saat scan; tidak memblokir aksi bendahara.
3. **Digest:** Hanya ke user dengan `manage_keuangan`; tidak duplikasi dalam 6 jam (cek notifikasi terakhir).

## Keamanan

* Semua query scoped `school_id`; uji IDOR di feature test.
* Notifikasi tidak memuat nominal sensitif per siswa — hanya jumlah antrian.

## Acceptance criteria (fase B — selesai bila)

- [ ] SB-5 s.d. SB-7 terpenuhi di staging.
- [ ] Test feature: skor matching, flag anomali, digest notifikasi, akses bendahara.
- [ ] `PROGRESS.md` dan `docs/keuangan-ai/PROGRESS.md` diperbarui.

## Sub-fitur → mapping task

| Kode | Sub-fitur | Service / route |
|------|-----------|-----------------|
| B1 | Skor matching mutasi | `SppMutasiMatchingService` |
| B2 | Flag anomali | `SppAnomalyDetector` |
| B3 | Digest antrian | `BendaharaAntrianDigest` + command |
