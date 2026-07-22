{{-- Gaya kartu ID guru — dipakai pdf.blade.php (tunggal), cetak-massal.blade.php, & self.blade.php
     (halaman "Kartu ID Saya", di-scale via transform di sana). Semua rule di-scope ke .kg-card
     (bukan `*` global) karena self.blade.php merender ini di TENGAH halaman app biasa — `*` tak
     berbatas akan membocorkan font DejaVu Sans (dompdf) ke seluruh layout Tailwind di sekitarnya. --}}
<style>
    .kg-card, .kg-card * { font-family: "DejaVu Sans", sans-serif; }
    /* Tailwind preflight (aktif di halaman self.blade.php, TIDAK ada di dokumen PDF) memaksa
       semua <img> jadi display:block secara global — itu mematahkan trik "text-align:center pada
       parent" yang dipakai di bawah utk menengahkan logo & foto (text-align cuma berlaku utk
       elemen inline/inline-block). Kembalikan ke inline-block khusus di dalam kartu supaya
       kartu tetap tampil sama persis baik di PDF (tanpa Tailwind) maupun di web (dgn Tailwind). */
    .kg-card img { display: inline-block; }

    .kg-card {
        width: 54mm; height: 85.6mm; position: relative; overflow: hidden;
        background: #ffffff; border: 0.5pt solid #cbd5e1; border-radius: 3mm;
    }

    /* Aksen kuning: lingkaran sebagian keluar kartu */
    .kg-c1 { position: absolute; top: -7mm; right: -7mm; width: 16mm; height: 16mm; background: #fbbf24; border-radius: 8mm; }
    .kg-c2 { position: absolute; bottom: -9mm; left: -9mm; width: 20mm; height: 20mm; background: #f59e0b; border-radius: 10mm; }

    /* Tumpukan teks jabatan berulang (background di belakang foto) */
    .kg-rows {
        position: absolute; top: 14mm; left: -3mm; width: 60mm; height: 53mm;
        overflow: hidden; text-align: center; font-weight: bold;
        letter-spacing: 1.5pt; text-transform: uppercase; white-space: nowrap;
    }
    .kg-row-solid { color: #2d5fa8; }
    .kg-row-ghost { color: #dce7f6; }

    /* Foto cutout besar — tanpa bingkai, berdiri "di atas" banner nama */
    .kg-foto { position: absolute; top: 14mm; left: 0; width: 54mm; height: 52.5mm; text-align: center; overflow: hidden; }
    .kg-foto img { height: 52.5mm; }
    .kg-noface {
        width: 30mm; height: 30mm; margin: 11mm auto 0; background: #eef2f7;
        border-radius: 15mm; text-align: center; font-size: 26pt; font-weight: bold;
        color: #94a3b8; padding-top: 6.5mm;
    }

    /* Pojok logo kiri-atas (bentuk gelap membulat ke kanan-bawah, ala contoh) */
    .kg-corner {
        position: absolute; top: 0; left: 0; width: 15.5mm; height: 13mm;
        background: #1e3f7a; border-radius: 0 0 4mm 0;
    }
    .kg-logo { width: 8.5mm; height: 8.5mm; background: #ffffff; border-radius: 4.5mm; margin: 2mm 0 0 3mm; text-align: center; }
    .kg-logo img { width: 6.6mm; height: 6.6mm; margin-top: 0.9mm; }

    .kg-sch {
        position: absolute; top: 3mm; left: 17mm; right: 3mm; text-align: right;
        font-weight: bold; font-size: 6.2pt; color: #1e3f7a; line-height: 1.15;
    }
    .kg-cap { color: #d97706; font-size: 3.8pt; font-weight: bold; letter-spacing: 1.3pt; margin-top: 0.7mm; }

    /* Banner nama gelap */
    .kg-banner {
        position: absolute; top: 66.5mm; left: 3mm; right: 3mm; height: 5.4mm;
        background: #1e3f7a; border-radius: 1.8mm; text-align: center;
        color: #ffffff; font-weight: bold; padding-top: 1.6mm; line-height: 1.05;
    }
    .kg-underline { position: absolute; top: 74.4mm; left: 20mm; width: 14mm; height: 0.9mm; background: #f59e0b; border-radius: 0.45mm; }

    /* Baris bawah sisi depan: jabatan + nomor, dipusatkan (QR sekarang di sisi belakang) */
    .kg-meta { position: absolute; top: 76.2mm; left: 3mm; right: 3mm; text-align: center; }
    .kg-jb { color: #2d5fa8; font-size: 4.9pt; font-weight: bold; letter-spacing: 0.4pt; text-transform: uppercase; line-height: 1.25; }
    .kg-no { color: #64748b; font-size: 4.5pt; margin-top: 0.8mm; }

    /* ===== Sisi BELAKANG kartu (_card-back.blade.php) — header sederhana + QR besar ===== */
    .kg-back-header { position: absolute; top: 6mm; left: 3mm; right: 3mm; text-align: center; }
    .kg-back-logo {
        width: 11mm; height: 11mm; margin: 0 auto; background: #ffffff;
        border: 0.4pt solid #cbd5e1; border-radius: 5.5mm; text-align: center;
    }
    .kg-back-logo img { width: 8.6mm; height: 8.6mm; margin-top: 1.15mm; }
    .kg-back-sch { margin-top: 1.8mm; font-weight: bold; font-size: 5.6pt; color: #1e3f7a; line-height: 1.2; }

    .kg-back-qr-wrap { position: absolute; top: 26mm; left: 0; right: 0; text-align: center; }
    .kg-back-qr {
        width: 30mm; height: 30mm; margin: 0 auto; background: #ffffff;
        border: 0.6pt solid #cbd5e1; border-radius: 2mm; text-align: center;
    }
    .kg-back-qr img { width: 27mm; height: 27mm; margin-top: 1.5mm; }

    .kg-back-name { position: absolute; top: 59.5mm; left: 3mm; right: 3mm; text-align: center; font-weight: bold; font-size: 7.2pt; color: #1e3f7a; }
    .kg-back-jb { position: absolute; top: 64mm; left: 3mm; right: 3mm; text-align: center; font-size: 4.9pt; font-weight: bold; letter-spacing: 0.4pt; text-transform: uppercase; color: #2d5fa8; }
    .kg-back-no { position: absolute; top: 67.6mm; left: 3mm; right: 3mm; text-align: center; font-size: 4.5pt; color: #64748b; }
    .kg-back-note { position: absolute; top: 76mm; left: 4mm; right: 4mm; text-align: center; font-size: 4pt; color: #94a3b8; line-height: 1.4; }
</style>
