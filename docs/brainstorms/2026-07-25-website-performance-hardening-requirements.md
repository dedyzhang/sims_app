# Brainstorm: Percepatan & Hardening Website SIMS

**Date:** 2026-07-25  
**Status:** Requirements ready for planning  
**User choice:** Approach **A** — baseline global dulu (layout + polling), lalu surface berat  
**Scope:** Deep — feature (bukan product rewrite)

---

## Summary

Percepat SIMS dan perbaiki permukaan lambat/rusak lewat **audit terarah**, bukan rewrite 345 blade. Prioritas: (1) beban layout CDN + polling global, (2) pagination/query hot path, (3) surface berat (scan, AI, forum) + bug P0/P1 yang memblokir alur inti.

---

## Evidence (repo)

- `resources/views/layouts/app.blade.php`: Tailwind CDN runtime, Alpine/unpkg, lucide@latest, Tom Select, Sortable, DataTables; poll notif 10s, chat/feedback 20s, ticker 20s.
- Poll fitur: forum/classroom comments ~5s, AI quota ~10s, scan absensi rapat, live arena.
- Banyak controller `->get()` tanpa pagination; `teacher.blade.php` monolit besar.
- PROGRESS: Arena/Misi done; Ludensa/OCR WIP — jangan digabung ke rilis perf tanpa gate.

---

## Requirements (stable IDs)

### R1 Layout cost
- R1.1 Conditional load library hanya di halaman yang butuh (DataTables/Tom Select/Sortable).
- R1.2 Pin versi CDN; larang lucide@latest di production.
- R1.3 Kurangi Tailwind runtime CDN di production bila build Vite memungkinkan.
- R1.4 Navigasi tetap responsif; tidak tambah blocking script.

### R2 Polling
- R2.1 Pause poll layout saat `document.hidden`.
- R2.2 Target idle background ≤ ~6 req/menit/user (kecuali live/kiosk).
- R2.3 Poll fitur (forum, komentar, AI quota) visibility-aware.
- R2.4 Live/scan poll rapat hanya di halaman tersebut.

### R3 Query/list
- R3.1 Pagination/limit di index yang bisa unbounded.
- R3.2 Eager-load relasi view (anti N+1).
- R3.3 Export tidak silent hang (timeout + feedback).

### R4 Surface berat (paralel ringan)
- R4.1 Scan absensi: jangan muat bundle AI/OCR.
- R4.2 Asisten Guru: lazy-init tab non-aktif; camera stream off saat hidden.
- R4.3 Forum/classroom/arena: interval “cukup” + visibility.
- R4.4 Dashboard: kurangi ticker 1s non-esensial.

### R5 Fungsional
- R5.1 Bug blokir alur inti = P0/P1 terpisah.
- R5.2 Fix + reproduksi + test bila suite ada.
- R5.3 Tidak merge Ludensa/OCR WIP ke PR perf.

### R6 Measurement
- R6.1 Baseline: Dashboard, form guru, Scan absensi (HP mid / throttle).
- R6.2 Success: load interactive Dashboard/nav ≥20% lebih baik **atau** background idle −≥40%.
- R6.3 Suite filter Arena/Absensi/AI smoke tetap hijau.

---

## Approaches

| | Description | Verdict |
|---|-------------|---------|
| **A** | Baseline global (R1–R3) dulu | **CHOSEN** |
| B | Surface terburuk dulu (scan/AI) | Secondary parallel only |
| C | Big rewrite SPA | Rejected |

---

## Non-goals
- SPA rewrite, sharding DB, rewrite full AI/Arena, “fix semua bug”, migration/auth tanpa approval FL.

---

## Success criteria
- S1: Dashboard/nav +20% interactive **atau** idle requests −40%.
- S2: No regression relevant tests.
- S3: Layout poll pauses when hidden.
- S4: ≥3 unbounded lists limited/paginated.
- S5: Scan vs AI assets isolated.

---

## Outstanding (non-blocking)
1. Baseline di staging vs lokal+throttle?
2. Keluhan guru spesifik (detik/screenshot)?
3. Pin CDN cepat vs partial Vite (planning decides)?

---

## Risks
- Conditional scripts break pages that need DataTables → inventory first.
- Slower notif badge → refresh on focus.
- Scope creep “fix all” → only P0/P1 + easy.

---

## Hot files (for ce-plan)
- `resources/views/layouts/app.blade.php`
- `resources/views/ai/teacher.blade.php`
- `resources/views/forum/show.blade.php`
- `resources/views/classroom/partials/comments.blade.php`
- Controllers with unbounded `->get()`
- `PROGRESS.md`

---

## Next step
**ce-plan** for “Perf Baseline R1–R3 + R2 + S1–S4”, then ce-work in small PRs.
