# Laundry Management

Konteks: aplikasi manajemen laundry. Mirip pola POS (transaksi + status order) tapi siklus lebih panjang (order → proses → selesai → ambil).

> Aturan teknis (uang integer rupiah BIGINT+BCMath, UUID `HasUuids`, `DB::transaction()`, snapshot harga, multi-outlet = pola `school_id`) mengikuti Konvensi kanonik di `SKILL.md`. File ini hanya pengetahuan domain.

## Skema inti

- `customers` — nama, HP, alamat (opsional), member/poin (opsional).
- `services` — jenis (cuci kering, cuci setrika, setrika saja, dry clean, satuan), tarif per **kg** atau per **pcs/satuan** (BIGINT rupiah).
- `orders` — nomor nota, customer, tanggal masuk, estimasi selesai, status, total, status bayar.
- `order_items` — service, berat/qty, **harga snapshot saat transaksi**, subtotal.
- `payments` — DP/lunas, metode (cash/QRIS/transfer).

## Status & alur (penting)

Status khas: `diterima → diproses → selesai → diambil` (+ `dibatalkan` bila perlu).
- Timestamp tiap perubahan status untuk tracking & SLA.
- Notifikasi pelanggan saat "selesai" (WhatsApp link/manual) — sangat dihargai user laundry.

## Prinsip

- **Offline-tolerant** seperti POS — outlet sering koneksi tidak stabil.
- Cetak nota (struk thermal 58/80mm) — layout print-friendly. Kalau app native → ESC/POS via skill `android-native-offline-first`.
- Hitung campuran: satu order bisa berisi item per-kg + per-pcs sekaligus — jaga akurasi integer.

## Fitur lanjutan (setelah MVP)

- Laporan omzet harian, layanan terlaris.
- Membership/poin, langganan.
- Multi-outlet (kalau berkembang → tambah `outlet_id`, pola mirip multi-tenant `school_id`).
