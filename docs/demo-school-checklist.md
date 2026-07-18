# Checklist demo sekolah (siap jual / demo)

Gunakan sebelum demo ke calon sekolah atau seed instalasi baru.

## 1. Infrastruktur

- [ ] `php artisan migrate --force`
- [ ] `php artisan config:clear` (setelah ubah `.env`)
- [ ] `APP_URL` cocok dengan URL yang dibuka di browser
- [ ] Storage link: `php artisan storage:link` (jika perlu file publik)

## 2. Kunci & integrasi

| Item | `.env` / setting | Wajib untuk demo |
|------|------------------|------------------|
| AI sekolah (Narasi / RAG) | `GEMINI_API_KEY` atau OpenRouter | Ya, jika demo Analisis AI |
| Asisten Guru | Guru isi API key pribadi di UI | Ya |
| Canva | `CANVA_CLIENT_ID` + `CANVA_CLIENT_SECRET` + redirect | Opsional; lihat `docs/canva-connect-setup.md` |
| FCM / unduh app | sesuai ops | Opsional |

## 3. Modul (Pengaturan → Fitur)

Default: semua **aktif**. Untuk demo ringkas:

- [ ] `akademik`, `asisten_guru`, `arena_belajar`, `absensi` **ON**
- [ ] `analisis_ai` ON hanya jika key sekolah siap
- [ ] `keuangan` ON jika demo SPP
- [ ] Matikan modul yang tidak ingin ditonjolkan

## 4. Data minimal

- [ ] Semester aktif + tahun ajaran
- [ ] 1 kelas, 1 mapel, 1 guru, beberapa siswa
- [ ] 1 Ruang Kelas dengan anggota
- [ ] Seed / buat 1 kuis Arena + (opsional) 1 misi
- [ ] Superadmin: tetapkan **Langganan** 3/6/12 bulan di menu Sistem → Langganan

## 5. Alur demo 10 menit

1. Login guru → Asisten Guru → generate soal singkat.
2. Ruang Kelas → tab **Arena Belajar** (kuis / misi).
3. (Opsional) Studio Presentasi / Canva.
4. Login kepala → Narasi Data / Dokumen AI (jika key sekolah ada).
5. Superadmin → Langganan: tunjukkan sisa hari & kunci saat kadaluarsa (opsional sandbox).

## 6. Tes otomatis sebelum demo

```bash
php artisan test --filter="LanggananTest|AiRagTest|AiAnalyzeTest|CanvaConnectTest|SarprasRoleAccessTest|ModulAktifTest"
```

## 7. Setelah demo

- [ ] Cabut key Canva/Gemini uji dari mesin demo jika perlu
- [ ] Catat lead di proses penjualan (situs `www.` — proyek terpisah, lihat PRD langganan)
