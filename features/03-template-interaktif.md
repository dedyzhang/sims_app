# Template Interaktif & Mode Tim

Variasi template ala Wordwall dari bank soal yang sama, mode tim, cetak PDF, dan antrean offline untuk wilayah 3T.

## Spesifikasi

### Tujuan
Satu bank soal Arena Belajar bisa dimainkan dalam beberapa bentuk interaktif (quiz, match, flashcard, crossword, susun kata), mendukung kerja kelompok, mencetak worksheet, dan menoleransi offline singkat — tanpa embed game Poki/CrazyGames eksternal.

### Selesai bila
- Guru bisa ganti template tampilan dari satu `game_quiz` tanpa menduplikasi soal.
- Minimal 4 template playable: Quiz, Match, Flashcard, Crossword atau Susun Kata (pilih yang feasible di Blade/Alpine).
- Mode tim: siswa digroup, skor agregat tampil di live/async.
- Export PDF worksheet dari bank soal yang sama (DomPDF).
- Offline queue best-effort: jawaban tersimpan di localStorage lalu sync saat online (dokumentasikan batasan).
- Tidak ada iframe game eksternal / iklan pihak ketiga.

## Sub-fitur: Template Switcher

Satu set konten → Quiz / Match / Flashcard / Crossword / Susun Kata.

### Tujuan
Mengurangi kerja guru: tulis sekali, mainkan banyak cara (prinsip Wordwall).

### Selesai bila
- Field `template` pada quiz/assignment: `quiz` | `match` | `flashcard` | `crossword` | `unjumble`.
- Renderer Blade/Alpine terpisah per template, data dari `game_questions` + `meta`.
- Template yang butuh struktur khusus (crossword) punya generator server-side atau fallback ke quiz bila konten tidak cukup.

## Sub-fitur: Mode Tim

Skor agregat per kelompok dalam sesi live atau assignment.

### Tujuan
Mendukung pembelajaran kolaboratif di kelas besar tanpa akun tambahan.

### Selesai bila
- Guru bisa buat tim (nama + anggota siswa) per assignment/sesi.
- Leaderboard bisa mode individu atau tim.
- Anggota tim melihat skor tim (opsional skor individu).

## Sub-fitur: Printable PDF

Worksheet dari bank soal yang sama.

### Tujuan
Guru punya versi kertas untuk kelas tanpa perangkat / PR cetak.

### Selesai bila
- Tombol “Cetak PDF” menghasilkan soal + lembar jawab (kunci terpisah untuk guru).
- Pakai DomPDF existing; Bahasa Indonesia.

## Sub-fitur: Offline Queue

Attempt tersimpan lokal lalu sync saat online.

### Tujuan
Mengurangi gagal submit di jaringan putus-putus (daerah 3T).

### Selesai bila
- Client menyimpan draft jawaban di localStorage saat offline terdeteksi.
- Saat online, sync ke endpoint submit/answer; konflik (sudah submitted server) ditangani jelas.
- Batasan terdokumentasi: bukan offline-first penuh; kuis locked/sumatif bisa menonaktifkan offline queue.

## Task

### 1. Buat halaman/view template switcher & preview dengan data tiruan [DONE]
### 2. Buat UI mode tim (buat tim, assign anggota) data tiruan [DONE]
### 3. Preview worksheet PDF (HTML print-friendly) data tiruan [DONE]
### 4. Integrasikan navigasi template dari detail kuis & live [DONE]
### 5. Poles tampilan template (animasi ringan, aksesibilitas sentuh) [DONE]
### 6. Buat migration & model untuk teams + template fields [DONE]
### 7. Buat controller + route template play + team scoring [DONE]
### 8. Export DomPDF worksheet + kunci guru [DONE]
### 9. Policy untuk tim & export [DONE]
### 10. Offline queue client + endpoint sync + activity log [DONE]
### 11. Seeder/factory template + feature test [DONE]
