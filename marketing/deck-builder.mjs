import fs from 'node:fs/promises';
import { Presentation, PresentationFile } from '@oai/artifact-tool';

const OUT_DIR = 'D:/SIMS_MW_APP/marketing/deck-output';
const IMG_DIR = 'D:/SIMS_MW_APP/marketing/assets/app-original';
const W = 1280;
const H = 720;
const C = { ink: '#0B1739', muted: '#5B6780', violet: '#164EB3', deep: '#070765', mint: '#FFF4B3', yellow: '#FFD928', coral: '#F5C400', paper: '#F5F8FF', white: '#FFFFFF', line: '#DCE5F2' };

async function writeBlob(path, blob) {
  await fs.writeFile(path, new Uint8Array(await blob.arrayBuffer()));
}

async function imageBytes(name) {
  const bytes = await fs.readFile(`${IMG_DIR}/${name}`);
  return bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength);
}

function box(slide, left, top, width, height, fill, radius = 'rounded-xl', line = C.line) {
  const config = { geometry: radius === 'none' ? 'rect' : 'roundRect', position: { left, top, width, height }, fill, line: { style: 'solid', fill: line, width: line === 'none' ? 0 : 1 } };
  if (radius !== 'none') config.borderRadius = radius;
  return slide.shapes.add(config);
}

function text(slide, value, left, top, width, height, style = {}) {
  const shape = slide.shapes.add({ geometry: 'textbox', position: { left, top, width, height }, fill: 'none', line: { style: 'solid', fill: 'none', width: 0 } });
  shape.text = value;
  shape.text.style = { fontFamily: style.fontFamily ?? 'Plus Jakarta Sans', fontSize: style.fontSize ?? 20, color: style.color ?? C.ink, bold: style.bold ?? false, alignment: style.alignment ?? 'left' };
  return shape;
}

function footer(slide, number, section = 'SIMS · Sistem Informasi Manajemen Sekolah', color = C.muted) {
  text(slide, section, 72, 674, 600, 18, { fontSize: 12, color });
  text(slide, String(number).padStart(2, '0'), 1168, 674, 40, 18, { fontSize: 12, color, alignment: 'right', bold: true });
}

function title(slide, eyebrow, heading, sub = '', options = {}) {
  text(slide, eyebrow.toUpperCase(), 72, 54, 520, 20, { fontSize: 12, color: options.eyebrowColor ?? C.violet, bold: true });
  text(slide, heading, 72, 84, 880, 104, { fontSize: 42, color: options.headingColor ?? C.ink, bold: true, fontFamily: 'Plus Jakarta Sans' });
  if (sub) text(slide, sub, 74, 198, 820, 42, { fontSize: 18, color: options.subColor ?? C.muted });
}

async function addImage(slide, name, left, top, width, height) {
  slide.images.add({ blob: await imageBytes(name), contentType: 'image/png', alt: name, fit: 'contain', position: { left, top, width, height }, geometry: 'roundRect', borderRadius: 'rounded-xl' });
}

function notes(slide, body) {
  slide.speakerNotes.textFrame.setText(`Sumber konten internal: D:/SIMS_MW_APP/README.md, PRD.md, PROGRESS.md, docs/PANDUAN_PENGGUNAAN_SIMS_APP.md.\n\n${body}`);
  slide.speakerNotes.setVisible(true);
}

async function build() {
  await fs.mkdir(OUT_DIR, { recursive: true });
  const deck = Presentation.create({ slideSize: { width: W, height: H } });

  {
    const s = deck.slides.add(); s.background.fill = C.deep;
    box(s, 790, 0, 490, H, C.violet, 'none', 'none');
    text(s, 'EDUTIVE', 72, 74, 220, 42, { fontSize: 24, color: C.white, bold: true, fontFamily: 'Plus Jakarta Sans' });
    text(s, 'Sistem Informasi\nManajemen Sekolah', 72, 180, 650, 150, { fontSize: 54, color: C.white, bold: true, fontFamily: 'Plus Jakarta Sans' });
    text(s, 'Satu alur kerja untuk operasional, pembelajaran, komunikasi, dan keputusan sekolah.', 76, 364, 530, 70, { fontSize: 22, color: '#DBE7FF' });
    box(s, 76, 500, 260, 54, C.white, 'rounded-xl', 'none');
    text(s, 'MATERI PRESENTASI KLIEN', 96, 517, 220, 20, { fontSize: 13, color: C.deep, bold: true });
    await addImage(s, 'dashboard.png', 824, 140, 365, 430);
    text(s, 'Promosi · Demo · Konsultasi', 824, 600, 360, 20, { fontSize: 14, color: '#DBE7FF' });
    notes(s, 'Slide pembuka. Gunakan untuk membuka percakapan tentang kebutuhan sekolah, bukan untuk membaca seluruh daftar fitur.');
  }

  {
    const s = deck.slides.add(); s.background.fill = C.paper; title(s, 'Tantangan sekolah', 'Informasi sekolah tidak boleh berhenti di satu bagian.', 'Ketika data, nilai, komunikasi, dan operasional tersebar, waktu habis untuk mencari dan merekap.');
    const items = [['Admin', 'Menjaga data, akses, dan konfigurasi tetap tertib.'], ['Guru', 'Menyiapkan pembelajaran, tugas, dan penilaian.'], ['Orang tua', 'Membutuhkan informasi perkembangan yang jelas.'], ['Kepala sekolah', 'Membutuhkan ringkasan untuk mengambil keputusan.']];
    items.forEach(([head, body], i) => { const x = 72 + (i % 2) * 558; const y = 260 + Math.floor(i / 2) * 150; box(s, x, y, 510, 112, C.white); text(s, head, x + 24, y + 22, 180, 28, { fontSize: 22, bold: true, fontFamily: 'Plus Jakarta Sans' }); text(s, body, x + 24, y + 57, 440, 34, { fontSize: 16, color: C.muted }); });
    box(s, 72, 584, 1080, 44, C.mint, 'rounded-lg', 'none'); text(s, 'SIMS menyatukan konteksnya — dengan pengalaman sesuai peran.', 94, 596, 1030, 20, { fontSize: 18, color: C.deep, bold: true }); footer(s, 2); notes(s, 'Jangan menyebut angka dampak yang belum diukur. Sampaikan ini sebagai masalah operasional yang ingin diselesaikan.');
  }

  {
    const s = deck.slides.add(); s.background.fill = C.white; title(s, 'Solusi', 'SIMS menghubungkan satu siklus sekolah.', 'Setiap peran melihat pekerjaan yang relevan, sementara data bergerak ke proses berikutnya.');
    const roles = [['01', 'Admin', 'Menyiapkan data & akses'], ['02', 'Guru', 'Mengajar & menilai'], ['03', 'Siswa', 'Belajar & berlatih'], ['04', 'Orang tua', 'Memantau perkembangan'], ['05', 'Kepala', 'Melihat ringkasan']];
    roles.forEach(([n, head, body], i) => { const x = 72 + i * 228; box(s, x, 260, 190, 190, i === 2 ? C.violet : C.paper); text(s, n, x + 20, 282, 80, 28, { fontSize: 22, color: i === 2 ? C.yellow : C.coral, bold: true, fontFamily: 'Plus Jakarta Sans' }); text(s, head, x + 20, 342, 150, 30, { fontSize: 23, color: i === 2 ? C.white : C.ink, bold: true, fontFamily: 'Plus Jakarta Sans' }); text(s, body, x + 20, 382, 145, 45, { fontSize: 15, color: i === 2 ? '#DBE7FF' : C.muted }); if (i < roles.length - 1) text(s, '→', x + 196, 340, 32, 30, { fontSize: 26, color: C.coral, bold: true, alignment: 'center' }); });
    footer(s, 3); notes(s, 'Tekankan bahwa SIMS adalah platform operasional sekolah, bukan hanya LMS.');
  }

  {
    const s = deck.slides.add(); s.background.fill = C.paper; title(s, 'CBT, akademik & kehadiran', 'Ujian web selesai. Nilai langsung bergerak ke buku nilai digital.', 'CBT formal, penilaian akademik, dan kehadiran berada di dalam alur SIMS yang dapat ditelusuri.');
    box(s, 72, 250, 510, 320, C.white); await addImage(s, 'cbt-ujian.png', 92, 270, 470, 230); text(s, 'CBT & buku nilai digital', 96, 520, 420, 26, { fontSize: 22, bold: true, fontFamily: 'Plus Jakarta Sans' }); text(s, 'Bank soal, token, auto-grading, nilai esai, pemantauan, lalu transfer otomatis ke Sumatif, PTS, atau PAS.', 96, 555, 440, 45, { fontSize: 15, color: C.muted });
    box(s, 618, 250, 510, 320, C.white); await addImage(s, 'absensi.png', 638, 270, 470, 230); text(s, 'Absensi & presensi', 642, 520, 420, 26, { fontSize: 22, bold: true, fontFamily: 'Plus Jakarta Sans' }); text(s, 'Manual, QR, wajah, GPS, kiosk, presensi guru, kalender, dan rekap.', 642, 555, 420, 45, { fontSize: 15, color: C.muted }); footer(s, 4); notes(s, 'CBT telah diverifikasi mendukung transfer otomatis ke Sumatif, PTS, dan PAS setelah attempt selesai dinilai. Soal objektif auto-grade; esai tetap melalui penilaian guru.');
  }

  {
    const s = deck.slides.add(); s.background.fill = C.deep; title(s, 'AI & pembelajaran', 'Guru menyiapkan pembelajaran. Siswa belajar lebih aktif.', 'AI membantu persiapan; Arena Belajar mengubah materi menjadi pengalaman yang dapat dipantau.', { eyebrowColor: C.yellow, headingColor: C.white, subColor: '#DBE7FF' });
    text(s, 'ASISTEN GURU', 72, 252, 250, 20, { fontSize: 12, color: C.yellow, bold: true }); await addImage(s, 'ai-guru.png', 72, 280, 430, 250); text(s, 'Generator soal · RPM · rangkuman · feedback · catatan siswa · ekspor', 72, 548, 450, 36, { fontSize: 16, color: '#DBE7FF' });
    text(s, 'ARENA BELAJAR', 650, 252, 250, 20, { fontSize: 12, color: C.yellow, bold: true }); await addImage(s, 'arena-belajar.png', 650, 280, 430, 250); text(s, 'Kuis · live session · leaderboard · misi · mode tim · analitik', 650, 548, 450, 36, { fontSize: 16, color: '#DBE7FF' }); footer(s, 5, 'Edutive · Asisten Guru + Arena Belajar', '#C7D8FF'); notes(s, 'Asisten Guru memerlukan API key guru/sekolah. Arena Belajar memiliki kuis, live session, template, mode tim, dan misi sesuai status dokumen progres.');
  }

  {
    const s = deck.slides.add(); s.background.fill = C.white; title(s, 'Komunikasi & pengelolaan', 'Sekolah tidak hanya mengajar — sekolah juga berkomunikasi dan mengelola.', 'Modul pendukung membantu aktivitas sekolah tetap berada dalam satu ekosistem.');
    const cards = [['Komunikasi', 'komunikasi.png', 'Forum, pengumuman, Grup Kelas, Paguyuban, private chat, dan notifikasi.'], ['Keuangan', 'keuangan.png', 'Tagihan, bukti bayar, verifikasi, rekonsiliasi, dan antrian prioritas.'], ['Sarpras', 'sarpras.png', 'Aset, denah, peminjaman, kerusakan, perbaikan, pengadaan, dan laporan.']];
    cards.forEach(([head, img, body], i) => { const x = 72 + i * 370; box(s, x, 250, 330, 330, C.paper); addImage(s, img, x + 18, 268, 294, 145); text(s, head, x + 20, 438, 280, 28, { fontSize: 23, bold: true, fontFamily: 'Plus Jakarta Sans' }); text(s, body, x + 20, 482, 282, 60, { fontSize: 15, color: C.muted }); }); footer(s, 6); notes(s, 'Tiga kelompok ini membantu memperluas pembicaraan dari LMS ke sistem manajemen sekolah.');
  }

  {
    const s = deck.slides.add(); s.background.fill = C.paper; title(s, 'Nilai bagi sekolah', 'Yang dibeli sekolah bukan jumlah menu — melainkan ketertiban kerja.', 'Gunakan empat manfaat ini sebagai bahasa utama saat menjelaskan SIMS.');
    const benefits = [['01', 'Lebih tertata', 'Data, akses, tugas, nilai, dan laporan mengikuti alur yang jelas.'], ['02', 'Lebih produktif', 'Guru mengurangi pekerjaan berulang saat menyiapkan materi dan menilai.'], ['03', 'Lebih terhubung', 'Siswa dan orang tua mendapat informasi dari jalur yang sama.'], ['04', 'Lebih siap mengambil keputusan', 'Kepala sekolah melihat ringkasan operasional dan akademik.']];
    benefits.forEach(([n, h, b], i) => { const x = 72 + (i % 2) * 558; const y = 250 + Math.floor(i / 2) * 155; text(s, n, x, y, 50, 32, { fontSize: 24, color: C.coral, bold: true, fontFamily: 'Plus Jakarta Sans' }); text(s, h, x + 72, y, 400, 30, { fontSize: 25, bold: true, fontFamily: 'Plus Jakarta Sans' }); text(s, b, x + 72, y + 42, 420, 46, { fontSize: 16, color: C.muted }); }); footer(s, 7); notes(s, 'Manfaat ini adalah positioning, bukan angka hasil yang sudah diukur.');
  }

  {
    const s = deck.slides.add(); s.background.fill = C.white; title(s, 'Implementasi', 'Mulai dari alur yang paling penting bagi sekolah.', 'Implementasi dapat dipresentasikan sebagai langkah bertahap, bukan perubahan besar sekaligus.');
    const steps = [['1', 'Pemetaan', 'Kenali peran, data, dan proses prioritas.'], ['2', 'Konfigurasi', 'Atur modul, hak akses, semester, dan kebutuhan integrasi.'], ['3', 'Demo peran', 'Uji alur admin, guru, siswa, orang tua, dan kepala.'], ['4', 'Go-live', 'Mulai dari modul utama lalu perluas sesuai kesiapan.']];
    steps.forEach(([n, h, b], i) => { const x = 72 + i * 270; text(s, n, x, 270, 54, 54, { fontSize: 36, color: C.violet, bold: true, fontFamily: 'Plus Jakarta Sans' }); text(s, h, x, 350, 230, 28, { fontSize: 24, bold: true, fontFamily: 'Plus Jakarta Sans' }); text(s, b, x, 395, 220, 60, { fontSize: 16, color: C.muted }); if (i < 3) text(s, '→', x + 232, 276, 28, 30, { fontSize: 24, color: C.coral, bold: true, alignment: 'center' }); });
    box(s, 72, 548, 1050, 55, C.mint, 'rounded-lg', 'none'); text(s, 'Hak akses, activity log, CSRF, rate limit, dan file privat menjadi bagian dari fondasi aplikasi.', 94, 566, 1000, 20, { fontSize: 16, color: C.deep, bold: true }); footer(s, 8); notes(s, 'Jangan menjanjikan implementasi dengan durasi tetap sebelum kapasitas sekolah dan data awal diketahui.');
  }

  {
    const s = deck.slides.add(); s.background.fill = C.deep; title(s, 'Struktur layanan', 'Paket dapat disesuaikan dengan tahap transformasi sekolah.', 'Rancangan tier awal untuk memulai percakapan; harga dan detail final perlu dikonfirmasi sebelum dipublikasikan.', { eyebrowColor: C.yellow, headingColor: C.white, subColor: '#DBE7FF' });
    const packages = [['DASAR', 'Fondasi operasional', 'Data master\nAbsensi & presensi\nAkademik & rapor\nPengumuman & agenda'], ['PRO', 'Kolaborasi sekolah', 'Semua Dasar\nRuang Kelas & Forum\nKeuangan/SPP\nChat & notifikasi'], ['ENTERPRISE', 'Transformasi terpadu', 'Semua Pro\nSarpras lengkap\nAsisten Guru & Analisis Data\nKustomisasi & dukungan']];
    packages.forEach(([h, sub, body], i) => { const x = 72 + i * 370; box(s, x, 260, 330, 300, i === 1 ? C.white : '#0E3A91'); text(s, h, x + 22, 286, 260, 22, { fontSize: 13, color: i === 1 ? C.violet : C.yellow, bold: true }); text(s, sub, x + 22, 330, 275, 32, { fontSize: 24, color: i === 1 ? C.ink : C.white, bold: true, fontFamily: 'Plus Jakarta Sans' }); text(s, body, x + 22, 390, 270, 110, { fontSize: 16, color: i === 1 ? C.muted : '#DBE7FF' }); }); footer(s, 9, 'SIMS · Struktur paket awal', '#C7D8FF'); notes(s, 'Harga final, PPN, dan detail paket belum tersedia di repo. Slide ini hanya struktur proposal untuk diskusi.');
  }

  {
    const s = deck.slides.add(); s.background.fill = C.violet; text(s, 'LANGKAH BERIKUTNYA', 72, 72, 360, 20, { fontSize: 12, color: C.yellow, bold: true }); text(s, 'Mari lihat alur SIMS\ndi sekolah Anda.', 72, 142, 650, 130, { fontSize: 56, color: C.white, bold: true, fontFamily: 'Plus Jakarta Sans' }); text(s, 'Demo terbaik dimulai dari kebutuhan nyata: peran siapa yang paling sibuk, data apa yang paling sering dicari, dan proses mana yang ingin ditertibkan terlebih dahulu.', 76, 330, 620, 78, { fontSize: 21, color: '#DBE7FF' }); box(s, 76, 500, 270, 56, C.white, 'rounded-xl', 'none'); text(s, 'JADWALKAN DEMO →', 98, 519, 230, 20, { fontSize: 14, color: C.deep, bold: true }); box(s, 820, 150, 280, 280, C.mint, 'rounded-2xl', 'none'); text(s, 'Guru\n→ Siswa\n→ Kepala\n→ Orang tua', 870, 205, 190, 170, { fontSize: 30, color: C.deep, bold: true, fontFamily: 'Plus Jakarta Sans', alignment: 'center' }); footer(s, 10, 'SIMS · Demo dan konsultasi', '#C7D8FF'); notes(s, 'Tutup dengan ajakan menjadwalkan demo. Jangan tutup dengan daftar fitur tambahan.');
  }

  for (const [index, slide] of deck.slides.items.entries()) {
    const stem = `slide-${String(index + 1).padStart(2, '0')}`;
    await writeBlob(`${OUT_DIR}/${stem}.png`, await deck.export({ slide, format: 'png', scale: 1 }));
    await fs.writeFile(`${OUT_DIR}/${stem}.layout.json`, await (await slide.export({ format: 'layout' })).text());
  }
  await writeBlob(`${OUT_DIR}/deck-montage.webp`, await deck.export({ format: 'webp', montage: true, scale: 1 }));
  const pptx = await PresentationFile.exportPptx(deck);
  await pptx.save(`${OUT_DIR}/SIMS-Promosi-Klien.pptx`);
  console.log(`created ${OUT_DIR}/SIMS-Promosi-Klien.pptx`);
}

build().catch((error) => { console.error(error); process.exitCode = 1; });
