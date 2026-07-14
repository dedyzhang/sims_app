# Prompt — Arena Belajar Fase 1 (Laravel 12)

Prompt siap-tempel untuk AI coding agent (Cursor / Claude Code / OpenCode).  
**Pakai untuk implementasi Fase 1 saja.** Fase 2–3: baca `features/02-*.md` / `features/03-*.md` lalu minta prompt terpisah.

Salin blok di bawah ini utuh ke agent:

---

```
<role>
Kamu senior Laravel 12 engineer di proyek SIMS (School Information Management System) multi-tenant / multi-sekolah. Utamakan keamanan, konvensi eksisting, dan vertical-slice yang bisa direview UI dulu — bukan over-engineering.
</role>

<context>
Proyek: SIMS / B'tive di root workspace (Laravel 12 + Blade + Alpine.js 3 + Tailwind CSS 4 + Vite 7).
Modul baru: **Arena Belajar** — kuis interaktif in-app (bukan embed Kahoot/Wordwall/Poki).

Baca dulu sebelum coding:
- PRD.md
- features/01-bank-soal-kuis-async.md
- app/Http/Controllers/ClassroomAssignmentController.php (pola transferGrades, CRUD assignment)
- app/Policies/ClassroomPolicy.php
- app/Http/Controllers/Concerns/HandlesContentLock.php
- app/Http/Controllers/AiTeacherController.php (sumber impor soal AI)
- routes/web.php (group ruang-kelas)

Konvensi WAJIB (jangan langgar):
- Primary key UUID via HasUuids; PK kolom biasanya `uuid` seperti model Classroom* existing.
- Laravel 12: method casts() (bukan property $casts); scheduler di routes/console.php jika perlu.
- Semua write multi-tabel dalam DB::transaction().
- UI default Bahasa Indonesia (label, tombol, validasi, flash).
- Authorization via Policy (bukan if ($user->access === 'guru') tersebar).
- Audit::log() / activitylog untuk create kuis, submit attempt, transfer nilai.
- Jangan kirim is_correct opsi ke client sebelum feedback diizinkan / setelah submit sesuai aturan.

File/hal yang TIDAK BOLEH diubah:
- Skema tabel classroom_assignments / alur submit file tugas existing.
- Auth login, middleware face registration, users schema.
- Jangan install Inertia/React untuk Fase 1.
- Jangan pakai WebSocket / Laravel Reverb di Fase 1 (async HTTP saja).
</context>

<task>
Implementasikan modul Arena Belajar **Fase 1** sesuai features/01-bank-soal-kuis-async.md:

1. Quiz Builder (MCQ 4 opsi + Benar/Salah), draft/publish.
2. Assign ke Ruang Kelas (game_quiz_assignments), jadwal opens_at/due_at, scoring_mode accuracy|competitive (default accuracy), hide_scores, instant_feedback, show_leaderboard.
3. Attempt engine siswa: start → jawab → submit → skor server-side.
4. Monitor hasil guru (completion, skor per siswa, akurasi per soal).
5. Transfer nilai ke NilaiFormatif / NilaiSumatif (mirror transferGrades), batalkan jika rapor terkunci.
6. Opsional: reuse HandlesContentLock jika is_locked.
7. Impor soal dari output Asisten AI (best-effort parser) — boleh stub service jika format AI belum stabil, tapi hook UI-nya ada.

Urutan kerja WAJIB: UI dummy → poles → migration/model → controller/route Eloquent → policy → transfer+audit → seeder/factory → feature test.
</task>

<constraints>
- Tabel: game_quizzes, game_questions, game_question_options, game_quiz_assignments, game_attempts, game_answers — sesuai PRD.md section 6.
- Controllers usulan: GameQuizController, GameAttemptController (boleh pecah jika perlu).
- Views: resources/views/arena-belajar/
- Routes: di dalam group auth + ruang-kelas existing di routes/web.php; prefix jelas (arena-belajar).
- Satu attempt aktif/submitted per siswa per assignment (default Fase 1).
- Scoring accuracy: poin penuh jika benar, 0 jika salah; competitive boleh tambah bonus kecepatan tapi dokumentasikan rumus singkat di komentar service.
- Jangan buat file di luar kebutuhan Fase 1.
- Kalau keputusan ambigu (mis. relasi student_id ke users vs siswa.uuid), cek pola ClassroomSubmission existing lalu ikuti; jika masih ragu, TANYA dulu.
- Stop dan minta approval sebelum migration jika ada konflik skema dengan branch lokal.
</constraints>

<plan_first>
Sebelum menulis kode: paparkan rencana (daftar file yang akan dibuat/diubah + alasan singkat, mapping ke task 1–11 di features/01). Tunggu konfirmasi "lanjut" dariku sebelum eksekusi. Setelah "lanjut", kerjakan task UI tiruan dulu sampai poles; stop lagi sebelum migration.
</plan_first>

<output_format>
- Tampilkan per file: path lengkap lalu isi lengkap (ready-to-paste), tanpa placeholder "// ... dst" untuk file baru.
- Untuk edit file existing: tampilkan diff/hunk yang jelas atau instruksi StrReplace yang unik.
- Setelah tiap batch task: ringkas 3–5 baris apa yang selesai + cara cek manual singkat.
- Bahasa komunikasi ke user: Indonesia santai + istilah teknis Inggris.
</output_format>
```

---

## Cara pakai

1. Pastikan `PRD.md` dan `features/01-bank-soal-kuis-async.md` ada di root project.
2. Tempel prompt di atas ke agent baru / Composer.
3. Review rencana agent → ketik **lanjut** untuk UI dummy.
4. Review UI → ketik **lanjut** untuk migration ke bawah.
5. Setelah Fase 1 selesai: `lanjut fase 2` dengan merujuk `features/02-live-session-leaderboard.md` (buat prompt baru, jangan campur di sesi yang sudah panjang).

## Catatan desain prompt

- XML tags memaksa konteks Laravel 12 + larangan WebSocket/Inertia agar agent tidak “upgrade stack” sendiri.
- `plan_first` + stop sebelum migration mengurangi risiko skema salah di codebase besar.
- Mapping eksplisit ke `transferGrades` dan `ClassroomPolicy` supaya integrasi nilai tidak diulang dari nol.
