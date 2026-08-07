# Fase C — Wawasan & Efisiensi Lanjutan

Ringkasan naratif **non-nominal** untuk internal bendahara, ekspor paket kerja verifikasi (PDF/Excel), dan placeholder integrasi gateway pembayaran (opsional/deferred).

## User stories

### SB-8 — Wawasan operasional non-nominal (C1)

**Sebagai** bendahara, **saya ingin** melihat pola antrian, keterlambatan waktu bayar, dan hari bayar terpopuler, **agar** saya mengatur prioritas harian tanpa spreadsheet terpisah — terpisah dari Narasi Data pimpinan.

**Acceptance criteria:**
- Metrik dihitung server-side (jumlah antrian, % terlambat vs jatuh tempo, distribusi hari bayar).
- Poin narasi aturan tampil tanpa AI; narasi AI opsional hanya dari metrik non-nominal (tanpa rupiah).
- Halaman `/keuangan/bendahara-ai/wawasan`; role `manage_keuangan` wajib.

### SB-9 — Ekspor paket verifikasi (C2)

**Sebagai** bendahara, **saya ingin** mengunduh daftar verifikasi SPP ke Excel/PDF, **agar** arsip sekolah dan audit internal terdokumentasi.

**Acceptance criteria:**
- Route `GET /keuangan/bendahara-ai/export-paket?format=excel|pdf&status=...`
- Nominal BIGINT dari DB (bukan AI); filter status opsional.
- Hanya bendahara; scoped `school_id`.

### SB-10 — Gateway pembayaran (C3, deferred)

**Sebagai** admin sekolah, **saya ingin** integrasi gateway opsional di masa depan — **tidak** diimplementasi Fase C awal (gate FL + kebijakan sekolah).

## Aturan bisnis

1. **Narasi bendahara ≠ Narasi Data pimpinan** — controller & prompt terpisah dari `AiAnalyzeController`.
2. **AI wawasan:** Prompt melarang nominal rupiah; hanya pola waktu & antrian.
3. **Ekspor:** Bukan laporan ARKAS resmi; disclaimer di PDF.

## Keamanan

* Export & wawasan di middleware `manage_keuangan` + `modul:keuangan`.
* Ekspor tidak memuat path bukti privat (hanya metadata transaksi).
* Rate limit endpoint narasi AI.

## Acceptance criteria (fase C — selesai bila)

- [x] SB-8 & SB-9 terpenuhi.
- [x] `KeuanganAiFaseCTest` lulus.
- [x] `PROGRESS.md` diperbarui.
- [ ] SB-10 (gateway) — deferred.

## Sub-fitur → mapping task

| Kode | Sub-fitur | Service / route |
|------|-----------|-----------------|
| C1 | Wawasan non-nominal | `BendaharaWawasanService` + `wawasan` / `wawasanNarasi` |
| C2 | Ekspor paket | `SppVerifikasiPaketService` + `BendaharaVerifikasiPaketExport` + `exportPaket` |
| C3 | Gateway opsional | Deferred — dokumentasi saja |
