# Live Session & Leaderboard

Mode live-lite untuk review kelas: guru buka sesi, siswa ikut dari Ruang Kelas, leaderboard diperbarui lewat polling, plus tipe soal Match Up dan isian singkat.

## Spesifikasi

### Tujuan
Membawa energi review ala Kahoot tanpa memaksa WebSocket di hari pertama: guru mengontrol tempo soal, siswa menjawab di perangkat sendiri, podium kelas muncul hampir real-time via polling. Menambah jenis soal Match Up dan short answer agar konten lebih kaya sebelum template Fase 3.

### Selesai bila
- Guru bisa start/pause/advance/end sesi live dari kuis yang sudah published.
- Siswa melihat soal aktif yang sama (host-paced) dan bisa submit jawaban untuk soal itu.
- Leaderboard kelas refresh otomatis (polling 2–5 detik) tanpa reload penuh halaman.
- Tipe soal `match` dan `short_answer` bisa dibuat, dimainkan, dan di-grade (fuzzy match di server untuk short answer).
- Fase 2 tidak wajib Laravel Reverb; polling AJAX cukup. Reverb boleh dicatat sebagai opsi upgrade.
- Mode Kompetitif boleh dipakai di live review; mode Akurasi tetap default untuk kuis graded async.

## Sub-fitur: Live Lobby

Guru start/stop sesi; siswa melihat status & soal yang sedang aktif.

### Tujuan
Sinkronisasi kelas tanpa PIN anonim — semua pemain dari roster Ruang Kelas.

### Selesai bila
- Status sesi: `idle` | `lobby` | `question` | `reveal` | `ended` tersimpan di quiz/session state.
- Guru advance ke soal berikutnya; siswa yang terlambat join masuk di soal aktif (aturan terdokumentasi).
- Hanya anggota classroom assignment yang bisa join.

## Sub-fitur: Leaderboard

Podium real-time (polling) dengan mode Akurasi vs Kompetitif.

### Tujuan
Memberi feedback kompetitif yang adil untuk review, tanpa menggantikan penilaian rapor yang berbasis akurasi.

### Selesai bila
- Endpoint JSON leaderboard (top N + skor siswa sendiri).
- Competitive: poin dasar + bonus kecepatan; Accuracy: poin penuh jika benar tanpa bonus speed.
- UI podium Alpine (top 3 + daftar) update dari polling.

## Sub-fitur: Match Up & Short Answer

Pasangkan istilah–definisi; isian singkat dengan fuzzy match di server.

### Tujuan
Memperluas bank soal di luar MCQ/TF tanpa menunggu template switcher penuh.

### Selesai bila
- Builder mendukung input pasangan match dan kunci short answer (+ sinonim opsional di `meta`).
- Grading match: semua pasangan benar = full points (atau proporsional — pilih satu, dokumentasikan).
- Short answer: normalisasi lowercase/trim; fuzzy optional (Levenshtein threshold) di server.

## Sub-fitur: Notifikasi Live

Opsional FCM “kuis live dimulai” ke anggota kelas.

### Tujuan
Siswa di HP mendapat dorongan masuk sesi tanpa guru harus broadcast manual di grup chat.

### Selesai bila
- Saat guru start lobby/live, sistem kirim notifikasi via channel FCM existing (best-effort, gagal notifikasi tidak gagalkan sesi).
- Teks notifikasi Bahasa Indonesia.

## Task

### 1. Buat halaman/view live lobby & podium dengan data tiruan [DONE]
### 2. Tambah UI builder untuk Match Up & Short Answer (data tiruan) [DONE]
### 3. Halaman state live multi-step (lobby → question → reveal → ended) masih dummy [DONE]
### 4. Integrasikan navigasi dari detail kuis → “Mulai Live” / “Gabung Live” [DONE]
### 5. Poles tampilan live (fullscreen-friendly, kontras tinggi di proyektor) [DONE]
### 6. Buat migration & model untuk state sesi live [DONE]
### 7. Buat controller + route live + leaderboard JSON [DONE]
### 8. Grading Match Up & Short Answer + simpan jawaban [DONE]
### 9. Tambahkan policy untuk aksi live [DONE]
### 10. Notifikasi FCM + activity log start/end sesi [DONE]
### 11. Seeder/factory sesi live + feature test polling/leaderboard [DONE]
