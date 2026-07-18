# SIMS — Sistem Informasi Manajemen Sekolah

Aplikasi Laravel untuk operasional sekolah: absensi, akademik, AI guru, Arena Belajar, Sarpras, SPP, forum, dan modul pendukung lainnya. Produksi tunggal-sekolah (single-tenant) dengan saklar modul on/off dan kunci langganan.

## Modul utama (`ModulAktif`)

| Kode | Label |
|------|--------|
| `absensi` | Absensi & Presensi |
| `akademik` | Akademik (Ruang Kelas, jadwal, nilai, rapor, …) |
| `asisten_guru` | Asisten Guru (+ Studio Presentasi / Canva) |
| `analisis_ai` | Narasi Data AI + Dokumen AI (RAG) |
| `arena_belajar` | Arena Belajar (kuis + misi) |
| `agenda` | Agenda Guru & Rapat |
| `disiplin` | Kedisiplinan |
| `sarpras` | Sarana & Prasarana |
| `keuangan` | Keuangan / SPP |
| `forum` | Forum Diskusi |
| `pengumuman` | Pengumuman |
| `chatbot` | Chat / Asisten Sekolah |
| `cetak` | Cetak Data |
| `kartu_pelajar` | Kartu Pelajar |
| `alumni` | Data Alumni |

Saklar: **Pengaturan Sistem → Fitur**.

## Alur mengajar (disarankan)

1. **Asisten Guru** — buat soal / outline (API key Gemini pribadi guru).
2. **Arena Belajar** — Ruang Kelas → tab Arena (kuis / live / misi).
3. **Studio Presentasi** / **Canva** — desain visual (Canva butuh kredensial server; lihat [`docs/canva-connect-setup.md`](docs/canva-connect-setup.md)).

## Menjalankan lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Tes inti:

```bash
php artisan test --filter="AiRagTest|AiAnalyzeTest|CanvaConnectTest|SarprasRoleAccessTest|ModulAktifTest|Langganan"
```

## Dokumentasi

- Panduan pengguna: [`docs/PANDUAN_PENGGUNAAN_SIMS_APP.md`](docs/PANDUAN_PENGGUNAAN_SIMS_APP.md)
- Canva ops: [`docs/canva-connect-setup.md`](docs/canva-connect-setup.md)
- Demo / jual: [`docs/demo-school-checklist.md`](docs/demo-school-checklist.md)
- Arena PRD: [`PRD.md`](PRD.md) + [`features/`](features/)
- Situs penjualan + langganan: [`docs/prd-situs-penjualan-langganan.md`](docs/prd-situs-penjualan-langganan.md)

## Catatan AI

| Fitur | Kunci |
|-------|--------|
| Asisten Guru | API key Gemini **pribadi** guru (tersimpan terenkripsi di user) |
| Narasi Data / Dokumen AI (RAG) | **Kunci sekolah** di `.env` (`GEMINI_API_KEY` / OpenRouter) |
| Canva Connect | `CANVA_CLIENT_ID` + `CANVA_CLIENT_SECRET` di `.env` |
