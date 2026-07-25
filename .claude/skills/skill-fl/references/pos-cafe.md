# POS & Finansial Cafe / F&B

Konteks: FL owner restoran (Jaya Rasa), fokus profitabilitas. Prioritas: **HPP/COGS dulu**, lalu POS.

> Aturan teknis (uang integer rupiah BIGINT + BCMath, UUID `HasUuids`, `DB::transaction()`, snapshot harga, offline-tolerant) mengikuti Konvensi kanonik di `SKILL.md`. File ini hanya pengetahuan domain.

## HPP / COGS (prioritas)

HPP = Harga Pokok Penjualan. Tujuan: tahu biaya bahan per menu → tahu margin asli.

Struktur minimal:
- `ingredients` — id, nama, satuan beli (kg/liter/pcs), harga beli per satuan (BIGINT rupiah), satuan pakai.
- `recipes` (resep per menu) — menu_id, ingredient_id, qty_pakai per porsi.
- HPP per menu = Σ (qty_pakai × harga per satuan pakai). Margin = harga jual − HPP.

Catatan praktis:
- **Konversi satuan** (beli per kg, pakai per gram) sering jadi sumber error — buat kolom konversi eksplisit, jangan hardcode di kalkulasi.
- Yield/waste factor opsional (mis. sayur susut 10%).
- HPP menyentuh uang → hitung dengan integer arithmetic, hati-hati pembulatan konversi satuan (flag kalau ada risiko salah bulat).
- Output yang FL hargai: template spreadsheet HPP siap-pakai ATAU skema tabel + prompt untuk app.

## POS / kasir

Tabel inti:
- `categories`, `menu_items` (harga BIGINT, kategori, aktif/tidak, link ke recipe), `modifiers` (less sugar, extra shot) + `modifier_groups`.
- `orders` (nomor, waktu, kasir, status, metode bayar, total), `order_items` (menu, qty, **harga snapshot saat transaksi**, modifier terpilih, subtotal).
- `payments` (cash/QRIS/kartu, jumlah, kembalian).

Prinsip:
- **Offline-first** — koneksi outlet sering jelek. Antrian transaksi lokal, sync saat online. Kalau butuh app native offline-first → skill `android-native-offline-first`.
- Snapshot harga di `order_items` (jangan join live ke menu — harga bisa berubah).
- Nomor order unik & berurutan per hari untuk rekonsiliasi.
- Metode bayar QRIS umum (Midtrans/QRIS statis, tergantung proyek).

## Laporan profit

- Penjualan harian/mingguan, item terlaris, jam ramai.
- **Gross profit = revenue − total HPP terjual** — inilah kenapa HPP harus benar duluan.
- Laporan waste/stok kalau inventori diaktifkan.
- Strategi yang pernah FL pakai di Jaya Rasa: beverage attach-rate, promo "Paket Tutup Dapur", koreksi kuantitas beli sayur.

## Inventori (opsional, tahap lanjut)

- `stock` per ingredient, kurangi otomatis dari recipe saat order selesai (bungkus `DB::transaction()`, pertimbangkan `lockForUpdate` untuk stok concurrent).
- Alert stok menipis. Inventori real-time menambah kompleksitas — aktifkan setelah POS+HPP stabil.
