# Progress — AI Keuangan / Asisten Bendahara

Ref: `PRD.md` · Branch: `cursor/ai-fase-1-4`

| Fase | Ringkasan | Status |
|------|-----------|--------|
| **A** | Antrian prioritas, OCR HITL, dashboard SPP, parser koran, activity log | [x] Selesai |
| **B** | Skor matching mutasi↔tagihan, flag anomali, digest antrian | [x] Selesai |
| **C** | Wawasan naratif, ekspor paket verifikasi, gateway opsional | [ ] Rencana |

## Fase B — checklist

- [x] `SppMutasiMatchingService` — skor VA/nominal/tanggal/nama
- [x] `SppAnomalyDetector` — duplikat bukti, nominal janggal, pengajuan ganda
- [x] `BendaharaAntrianDigest` + `bendahara:antrian-digest` (2x/hari)
- [x] Halaman `/keuangan/bendahara-ai/rekonsiliasi` & `/anomali`
- [x] Kolom skor di pratinjau impor rekening koran
- [x] `KeuanganAiFaseBTest`

**Verifikasi:** `php artisan test --filter="KeuanganAi|KeuanganSpp|RekeningKoran"`
