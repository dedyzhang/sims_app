# Bank Soal & Kuis Async

Guru membuat kuis interaktif (MCQ + Benar/Salah), menugaskan ke Ruang Kelas, siswa mengerjakan async, sistem auto-grade, hasil bisa ditransfer ke buku nilai.

## Spesifikasi

### Tujuan
Mengganti alur “kuis = unggah file” di Ruang Kelas dengan kuis playable in-app: soal terstruktur di database, attempt siswa, skor otomatis, dan sinkron ke Nilai Formatif/Sumatif. Ini fondasi Arena Belajar sebelum live session atau template Wordwall.

### Selesai bila
- Guru bisa buat, edit, draft, dan publish kuis dengan soal MCQ (4 opsi) dan Benar/Salah.
- Siswa anggota Ruang Kelas bisa mulai attempt, submit, dan melihat skor (kecuali hide_scores).
- Skor dihitung di server; kunci jawaban tidak bocor ke payload play sebelum feedback diizinkan.
- Guru melihat monitor completion % + skor per siswa + akurasi per soal.
- Guru bisa transfer nilai graded attempts ke Formatif/Sumatif dengan `DB::transaction()` dan audit log.
- Semua UI label/pesan Bahasa Indonesia; primary key UUID; Laravel 12 `casts()`.

## Sub-fitur: Quiz Builder

Form buat/edit kuis beserta daftar soal dan opsi, plus impor dari Asisten AI.

### Tujuan
Guru punya satu tempat untuk menyusun konten kuis tanpa keluar ke Word/PDF.

### Selesai bila
- CRUD kuis + soal + opsi dalam satu alur UI.
- Validasi: minimal 1 soal, MCQ wajib punya tepat 1 opsi benar, TF punya 2 opsi.
- Import teks/struktur dari Asisten AI Guru (parser best-effort) mengisi draft soal.

## Sub-fitur: Assign & Jadwal

Menugaskan kuis ke classroom, mengatur jadwal, mode skor, hide_scores, dan content lock.

### Tujuan
Kuis muncul di Ruang Kelas siswa hanya saat window waktu terbuka dan status published.

### Selesai bila
- Assignment bisa ke satu atau lebih classroom (pola mirip `classroom_assignment_links`).
- `opens_at` / `due_at` ditegakkan di server saat start/submit attempt.
- Opsi scoring_mode `accuracy` (default) dan `competitive` (bonus kecepatan untuk review).
- Content lock reuse `HandlesContentLock` bila `is_locked`.

## Sub-fitur: Attempt Engine

Siswa menjawab soal, sistem menyimpan jawaban dan menghitung skor.

### Tujuan
Pengalaman main yang sederhana, fair, dan mobile-friendly tanpa WebSocket.

### Selesai bila
- Satu attempt aktif per siswa per assignment (aturan default Fase 1).
- Submit membungkus create/update answers + hitung skor dalam `DB::transaction()`.
- Instant feedback hanya jika `instant_feedback` true; leaderboard hanya jika `show_leaderboard`.
- Setelah submitted, siswa tidak bisa mengubah jawaban.

## Sub-fitur: Monitor & Transfer Nilai

Dashboard hasil untuk guru dan pemindahan skor ke buku nilai akademik.

### Tujuan
Menutup loop pembelajaran: dari main → skor → rapor, tanpa input manual satu per satu.

### Selesai bila
- Halaman hasil menampilkan daftar siswa, skor, status attempt, dan breakdown per soal.
- Transfer nilai mirror pola `ClassroomAssignmentController::transferGrades()` (formatif butuh TP, sumatif butuh materi).
- Dibatalkan bila rapor semester sudah terkunci/konfirmasi.
- `Audit::log` untuk create kuis, submit, dan transfer.

## Task

### 1. Buat halaman/view daftar & detail Arena Belajar dengan data tiruan [DONE]
Bangun Blade di `resources/views/arena-belajar/` (index, show) dengan data hardcode/dummy array, tanpa query database, supaya UI bisa direview sebelum backend jadi.

### 2. Tambah form Quiz Builder (masih data tiruan) [DONE]
Form judul, instruksi, mode skor, daftar soal MCQ/TF dengan opsi, tombol tambah/hapus soal — Alpine.js untuk interaksi UI, data masih dummy.

### 3. Buat halaman play attempt + hasil siswa (data tiruan) [DONE]
Alur multi-step: intro → soal per soal → submit → skor. Masih dummy JSON di Blade/Alpine.

### 4. Integrasikan navigasi antar halaman/state [DONE]
Link dari Ruang Kelas → Arena Belajar index → builder/show/play/hasil/monitor; aktifkan menu di layout/partial Ruang Kelas yang relevan.

### 5. Poles tampilan dan responsivitas [DONE]
Pastikan touch target besar, layout HP portrait, konsisten dengan Tailwind/tema SIMS existing; semua copy Bahasa Indonesia.

### 6. Buat migration & model Eloquent untuk tabel game_* [DONE]
Migration untuk `game_quizzes`, `game_questions`, `game_question_options`, `game_quiz_assignments`, `game_attempts`, `game_answers`. Model dengan `HasUuids`, SoftDeletes pada quiz, method `casts()` Laravel 12. Relasi Eloquent lengkap.

### 7. Buat controller + route untuk CRUD kuis & assign [DONE]
`GameQuizController` (index/create/store/edit/update/destroy/publish) + routes di group Ruang Kelas. Ganti data tiruan builder/index dengan query Eloquent. Write multi-tabel (quiz+questions+options) bungkus `DB::transaction()`.

### 8. Endpoint attempt + auto-grading + monitor [DONE]
`GameAttemptController` (start, answer/save, submit, show result) + endpoint monitor/results untuk guru. Scoring server-side; jangan kirim `is_correct` opsi ke client sebelum diizinkan. Ganti play/hasil tiruan dengan data nyata.

### 9. Tambahkan policy/authorization [DONE]
`GameQuizPolicy` (atau extend `ClassroomPolicy`): manage hanya guru classroom; play hanya siswa anggota; monitor sesuai manage/view. Daftarkan di `AuthServiceProvider` / `AppServiceProvider` sesuai konvensi project.

### 10. Transfer nilai + activity log [DONE]
Aksi transfer ke `NilaiFormatif`/`NilaiSumatif` mirror `transferGrades()`, cek kunci rapor, `Audit::log` untuk aksi sensitif (create, submit, transfer).

### 11. Buat seeder/factory + feature test [DONE]
Factory/seeder contoh 1 kuis + 5 soal + assignment. `tests/Feature/GameQuizTest.php`: guru buat kuis, siswa attempt & submit, skor benar, siswa lain tidak bisa akses.
