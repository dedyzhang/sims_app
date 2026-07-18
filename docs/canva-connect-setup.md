# Checklist ops — Canva Connect (belajar.id)

Integrasi Canva di Asisten Guru / Studio Presentasi memakai **Canva Connect API** (bukan Apps SDK “Unggah kode”).

## 1. Buat integrasi di Canva Developers

1. Buka [Canva Developers → Your integrations](https://www.canva.com/developers/integrations).
2. Create integration (Public / Connect API).
3. **Configuration → Credentials**:
   - Tab **Values**: salin **Client ID** dan generate/salin **Client secret**.
4. **Authentication → Authorized redirects** — tambahkan URI yang **persis** sama dengan SIMS:

```text
{APP_URL}/ai/teacher/canva/callback
```

Contoh lokal:

```text
http://127.0.0.1:8000/ai/teacher/canva/callback
```

Jangan campur `localhost` dan `127.0.0.1`.

5. **Scopes** (minimal):
   - `design:meta:read`
   - `design:content:read`
   - `design:content:write`
   - `profile:read`

## 2. Isi `.env` server SIMS

```env
CANVA_CLIENT_ID=OC-...
CANVA_CLIENT_SECRET=...
# Opsional jika beda dari default:
# CANVA_REDIRECT_URI=http://127.0.0.1:8000/ai/teacher/canva/callback
# CANVA_ALLOWED_EMAIL_SUFFIX=.belajar.id
```

Lalu:

```bash
php artisan migrate
php artisan config:clear
```

## 3. Pengaturan di SIMS

- **Pengaturan Sistem → Integrasi**: aktifkan Canva Connect; suffix wajib berakhiran `.belajar.id`.
- Guru di **Asisten Guru**: isi email `*@*.belajar.id` → **Simpan email** → **Hubungkan Canva**.
- Dari **Studio Presentasi**: buat desain → edit di Canva → export PDF (disimpan disk privat, unduh lewat route auth).

## 4. Verifikasi cepat

| Cek | Hasil diharapkan |
|-----|------------------|
| Asisten Guru tanpa Client ID | Peringatan kuning “Admin belum mengisi CANVA_CLIENT_…” |
| Setelah `.env` + config:clear | Peringatan hilang; tombol Hubungkan aktif |
| Email gmail | Ditolak |
| Redirect URI salah | OAuth gagal / error Canva |

## Catatan keamanan produk

- Email belajar.id di SIMS adalah **attestation di sisi sekolah** (API Canva Connect tidak mengembalikan email profil).
- Export PDF tidak disajikan publik (`/storage/...`); hanya lewat unduhan terautentikasi pemilik presentasi.
