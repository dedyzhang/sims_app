# Fase 1 — Refactor Penyederhanaan Sarpras

## Selesai

- [x] Gabung booking → peminjaman (`RuanganJadwalService`, migrasi data legacy)
- [x] Hapus route editor kanvas/hotspot; pertahankan import gambar
- [x] Pengadaan → Usulan Kebutuhan (estimasi opsional, BA serah terima PDF)
- [x] Menu sidebar 6 item; hapus tab internal modul
- [x] Export KIB/KIR, stok opname ATK, activity log ringan
- [x] Field `nama_teknisi` inline di perbaikan

## Acceptance

- Tidak ada route aktif `booking.store` — redirect ke peminjaman
- Bentrok jadwal ruangan satu sumber (`Peminjaman::bentrok`)
- Tes `php artisan test --filter=Sarpras` hijau
