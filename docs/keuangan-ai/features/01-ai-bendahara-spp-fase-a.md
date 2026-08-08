# Fase A — AI Bendahara SPP Operasional

Asisten harian bendahara: antrian prioritas, OCR saran bukti, dashboard SPP bulanan, parser rekening koran diperluas, dan audit transisi — tanpa AI menghitung rupiah dan tanpa menyalin Narasi Data pimpinan.

## User stories

### SB-1 — Antrian prioritas verifikasi (A1)

**Sebagai** bendahara, **saya ingin** melihat daftar pembayaran/bukti yang menunggu verifikasi diurutkan prioritas, **agar** saya menangani kasus paling mendesak dulu.

**Acceptance criteria:**
- Daftar hanya menampilkan data `school_id` aktif (global scope).
- Skor prioritas dihitung server-side dengan aturan tetap (bukan LLM); urutan dapat dijelaskan (tooltip/kolom alasan).
- Bendahara dapat membuka detail dari satu baris antrian menuju alur verifikasi existing.
- Role selain bendahara (dan permission yang ditetapkan) tidak melihat antrian ini.

### SB-2 — OCR asisten bukti (A2)

**Sebagai** bendahara, **saya ingin** mengunggah foto/PDF bukti transfer dan mendapat saran isian (nama pengirim, tanggal, referensi), **agar** saya tidak mengetik ulang, tetap memverifikasi sendiri.

**Acceptance criteria:**
- Hasil OCR ditampilkan sebagai **saran**; tombol simpan transaksi tidak aktif sampai bendahara mengonfirmasi field kritis.
- Nominal dari OCR (jika ada) tidak langsung menulis ke kolom nominal resmi tanpa validasi manual dan tidak menggantikan perhitungan tagihan SIMS.
- File bukti disimpan dengan policy storage sekolah; akses unduh tercatat jika modul file sensitif.
- Kegagalan OCR menampilkan pesan jelas; bendahara dapat isi manual.

### SB-3 — Dashboard pendapatan SPP bulanan (A3)

**Sebagai** bendahara, **saya ingin** grafik dan tabel ringkasan penerimaan SPP per bulan, **agar** saya memantau tren tanpa spreadsheet terpisah.

**Acceptance criteria:**
- Agregat dihitung dari transaksi/tagihan SPP yang sudah terverifikasi (definisi status mengikuti modul keuangan).
- Semua angka format rupiah dari BIGINT; tidak ada float di API response.
- Filter bulan/tahun; default bulan berjalan.
- Tidak memaparkan data siswa ke role non-bendahara.

### SB-4 — Impor mutasi & audit transisi (A4 + A5)

**Sebagai** bendahara, **saya ingin** mengimpor rekening koran dari bank selain BCA dan melihat jejak setiap perubahan status verifikasi, **agar** rekonsiliasi dan akuntabilitas internal terjaga.

**Acceptance criteria:**
- Parser mendukung minimal satu format bank tambahan selain BCA (konfigurasi kolom).
- Baris gagal parse dilaporkan per baris tanpa menghentikan seluruh batch (partial success dengan ringkasan).
- Setiap transisi status verifikasi SPP yang relevan menulis activity log (actor, `school_id`, before/after status, referensi transaksi).
- Tidak ada endpoint yang menduplikasi narasi `AiAnalyzeController` untuk pimpinan.

## Aturan bisnis

1. **Sumber kebenaran nominal:** Tagihan dan pembayaran tercatat di modul keuangan SIMS; AI/OCR tidak menjadi sumber kebenaran nominal.
2. **Prioritas antrian:** Formula v1 deterministik (contoh bobot: hari terlambat > nominal tertunggak > usia unggahan bukti); formula versi disimpan di config untuk audit perubahan aturan.
3. **Status verifikasi:** Hanya bendahara (permission `keuangan.spp.verifikasi` atau setara) yang boleh menyetujui/menolak; siswa/orang tua hanya submit bukti.
4. **Parser rekening koran:** Mapping bank disimpan per `school_id` jika diperlukan; impor tidak membuat pembayaran otomatis tanpa langkah matching Fase B (Fase A: impor + preview saja jika belum ada matching).
5. **Retensi saran OCR:** Draft saran dapat dihapus otomatis setelah verifikasi selesai atau setelah TTL (mis. 30 hari) — kebijakan final saat implementasi.

## Keamanan

* **Policy & permission:** Gate bendahara terpisah dari route `ai.analyze` / narasi pimpinan; uji IDOR antar sekolah wajib di feature test.
* **Activity log:** Event transisi keuangan sensitif wajib log; tidak log isi penuh gambar bukti (hanya referensi file/id).
* **Upload:** Validasi MIME/ukuran; scan path traversal; file private disk.
* **AI/OCR:** Prompt dan response tidak meminta model menghitung total atau mengubah database; panggilan provider memakai key sekolah atau env terpusat sesuai kebijakan `config/ai.php` (bukan key pribadi guru).
* **Rate limit:** Endpoint OCR dan impor batch di-throttle per user.

## Acceptance criteria (fase A — selesai bila)

- [ ] SB-1 s.d. SB-4 terpenuhi di staging dengan data uji multi-tenant.
- [ ] Test feature: antrian scope, OCR tidak auto-post, dashboard agregat BIGINT, parser bank tambahan, activity log pada verifikasi.
- [ ] Audit `laravel-security-audit` untuk route bendahara baru tanpa temuan P0/P1.
- [ ] Dokumentasi singkat di README modul keuangan: non-tujuan ARKAS dan human-in-the-loop.

## Sub-fitur → mapping task (implementasi)

| Kode | Sub-fitur | Catatan |
|------|-----------|---------|
| A1 | Antrian prioritas | Service `SppVerificationQueue` rule-based |
| A2 | OCR asisten | Job async + form saran |
| A3 | Dashboard SPP | Query agregat + chart Blade/Alpine |
| A4 | Parser koran | Strategy per bank |
| A5 | Activity log | Spatie log pada model observer / service |

Urutan coding mengikuti `prd-generator`: UI tiruan → integrasi → migration (gate FL) → policy → test.
