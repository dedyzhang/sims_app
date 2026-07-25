---
name: senior-prompt-engineering
description: >
  Panduan wajib FL untuk MENYUSUN PROMPT tingkat senior — dipakai SETIAP KALI FL
  akan mem-prompt AI apa pun (AI coding agent: Claude Code/Cursor/Antigravity/OpenCode/GLM,
  maupun prompt umum: konten, analisis, riset, copy). Trigger begitu FL bilang
  "buatkan prompt", "tolong prompt-kan", "susun prompt untuk...", "perbaiki prompt ini",
  atau saat FL akan mendelegasikan tugas ke agent lain. Jangan tunggu kata "skill" —
  begitu output-nya adalah sebuah PROMPT (bukan jawaban langsung), pakai skill ini.
  Skill ini TIDAK dipakai kalau FL cuma tanya jawab biasa; hanya saat produk akhir = prompt.
  BATAS vs prd-generator: skill INI menang kalau output = satu blok prompt siap-tempel
  (tanpa struktur PRD/features). Kalau FL minta dokumen perencanaan / breakdown app /
  spec multi-file (walau di dalamnya ada prompt eksekusi), itu ranah prd-generator.
---

# Senior Prompt Engineering — FL

> **Sumber konvensi: `skill-fl`.** Blok `<context>` di Mode B sengaja mengutip konvensi teknis FL — itu memang isi prompt yang akan ditempel ke agent lain, jadi harus mandiri. Saat mengutip, ambil nilai dari `skill-fl` (uang integer rupiah BIGINT+BCMath, UUID, `school_id` scope, dst). Kalau ada beda, `skill-fl` yang benar.

Tujuan: setiap prompt yang FL kirim ke AI harus deterministik, minim ambiguitas,
dan langsung menghasilkan output ready-to-use. Prinsip inti: **AI hanya sebaik
konteks + constraint yang diberi.** Prompt bagus = spesifikasi, bukan harapan.

## Aturan output skill ini
- Keluarkan prompt dalam **blok kode** (```) supaya FL bisa copy utuh.
- Kalau prompt kompleks (coding agent), bungkus pakai tag XML (`<context>`, `<task>`, `<constraints>`, `<output_format>`) — model lebih patuh pada struktur bertag.
- Setelah blok prompt, kasih **1-3 baris** catatan: kenapa disusun begini + risiko jika bagian tertentu dihapus. Jangan teori panjang.
- Kalau brief FL ambigu di titik yang mengubah hasil, tanya SEBELUM menulis prompt. Kalau ambiguitasnya kecil, tulis asumsi eksplisit di dalam prompt.

## 7 komponen prompt senior (checklist wajib)
Setiap prompt final minimal cek 7 ini. Yang tak relevan boleh dilewati, tapi harus sadar dilewati.

1. **Role/persona** — siapa yang menjawab. ("Kamu senior Laravel engineer, 10th di multi-tenant SaaS.")
2. **Context** — data, kondisi, stack, file terkait, batasan lingkungan. Ini bagian paling sering kurang.
3. **Task** — 1 tujuan utama, kata kerja jelas. Hindari menumpuk banyak tujuan dalam 1 prompt.
4. **Constraints** — larangan & keharusan konkret (versi, format, hal yang TIDAK boleh diubah).
5. **Reasoning cue** — untuk tugas non-trivial: "pikirkan langkah demi langkah dulu sebelum menulis kode" / "rencanakan lalu eksekusi".
6. **Output format** — bentuk pasti: file apa, bahasa, panjang, dengan/tanpa penjelasan.
7. **Examples (few-shot)** — kalau ada pola gaya/format yang harus ditiru, kasih 1 contoh input→output. Ini pengungkit akurasi terbesar.

## Prinsip inti
- **Positif > negatif.** "Tulis dalam Bahasa Indonesia" lebih patuh daripada "jangan pakai bahasa Inggris".
- **Spesifik > umum.** "maksimal 3 fungsi, tiap fungsi <30 baris" > "kode yang rapi".
- **1 prompt = 1 tujuan.** Tugas besar dipecah bertahap (lihat pola coding di bawah), bukan satu prompt raksasa.
- **Minta rencana dulu untuk tugas berisiko.** Suruh agent paparkan rencana → FL setujui → baru eksekusi. Mencegah agent ngasal di codebase besar.
- **Sebut yang TIDAK boleh disentuh.** Di codebase eksisting, cantumkan file/konvensi yang haram diubah, kalau tidak agent suka "merapikan" hal yang tak diminta.
- **Escape hatch.** Beri izin agent bilang "tidak yakin / butuh info X" daripada mengarang. Mengurangi halusinasi.

---

## MODE A — Prompt umum (konten / analisis / riset)

Template:
```
Peran: [siapa AI-nya + level keahlian]
Konteks: [audiens, tujuan, data/sumber yang boleh dipakai, batasan]
Tugas: [1 kalimat kata kerja]
Batasan: [panjang, gaya, hal yang harus/tidak boleh, bahasa]
Format output: [bentuk pasti — daftar? tabel? draft? berapa kata?]
[opsional] Contoh: [1 contoh gaya/format target]
```

Contoh terisi (konten TikTok FL):
```
Peran: Kamu content strategist untuk founder teknis yang membangun software bisnis pakai AI.
Konteks: Audiens = pemilik UMKM & aspiring builder Indonesia. Tujuan = hook 3 detik pertama kuat, gaya "build in public", bukan tutorial menggurui.
Tugas: Tulis 5 hook + skrip 30 detik untuk video "bikin modul absensi sekolah pakai AI dalam 1 sore".
Batasan: Bahasa Indonesia campur istilah teknis seperlunya. Tiap skrip ≤ 90 kata. Tanpa emoji berlebihan.
Format output: Nomori 1-5. Tiap item: [HOOK] baris pertama, lalu [SKRIP] paragraf pendek.
```

---

## MODE B — Prompt untuk AI Coding Agent

Bungkus dengan tag XML. Kerangka wajib:
```
<role>
Kamu senior [Laravel 12] engineer di proyek multi-tenant. Utamakan keamanan & konvensi eksisting di atas kepintaran.
</role>

<context>
Proyek: [mis. B'tive v6 SIMS]. Stack: Laravel 12 + Blade/Livewire.
Konvensi WAJIB (jangan langgar):
- Primary key UUID via HasUuids.
- Multi-tenant: scoping school_id (BelongsToSchool + SchoolScope global scope).
- Uang: integer BIGINT rupiah, BCMath untuk aritmetika. TIDAK BOLEH float.
- Semua write multi-tabel dalam DB::transaction().
- Laravel 12: pakai method casts() (bukan $casts); scheduler di routes/console.php.
- UI default Bahasa Indonesia.
- spatie/laravel-permission + spatie/laravel-activitylog.
File/hal yang TIDAK BOLEH diubah: [sebutkan, mis. skema tabel X, service Y].
</context>

<task>
[1 tujuan konkret. Contoh: Buat modul [nama] dengan fitur A, B, C.]
</task>

<constraints>
- Ikuti urutan kerja: UI dummy → migration/model → controller/route (Eloquent nyata) → policy → seeder.
- Jangan buat file di luar yang diperlukan tugas ini.
- Kalau butuh keputusan yang belum jelas, TANYA dulu, jangan berasumsi diam-diam.
</constraints>

<plan_first>
Sebelum menulis kode: paparkan rencana (daftar file yang akan dibuat/diubah + alasan singkat). Tunggu konfirmasi "lanjut" dariku sebelum eksekusi.
</plan_first>

<output_format>
Tampilkan per file: path lengkap lalu isi lengkap file dalam blok kode. Ready-to-paste, tanpa placeholder "// ... dst".
</output_format>
```

Catatan pemakaian Mode B:
- Untuk perbaikan bug: ganti `<task>` jadi gejala + reproduksi + perilaku yang diharapkan, dan minta agent **menemukan akar masalah dulu**, jangan tebak-tempel patch.
- Untuk review/security: pasangkan dengan skill `laravel-security-audit`.
- Blok `<plan_first>` boleh dihapus untuk tugas kecil (1 file), wajib untuk tugas menyentuh banyak file.

---

## MODE C — Perbaiki prompt yang sudah ada
Kalau FL nempel prompt lama, jangan langsung tulis ulang total. Kembalikan:
1. **Diagnosa** (2-4 baris): komponen mana dari 7-checklist yang hilang/lemah → dampaknya ke output.
2. **Versi revisi** dalam blok kode.
Fokus ke penyebab output jelek, bukan kosmetik.

## Anti-pattern (tolak/koreksi kalau FL memintanya)
- Prompt tanpa format output → hasil bentuknya tak terprediksi.
- Menumpuk 5+ tujuan berbeda dalam 1 prompt → agent kehilangan fokus, kualitas turun.
- Constraint hanya negatif ("jangan begini, jangan begitu") tanpa arahan positif.
- "Buatkan yang bagus" tanpa definisi "bagus" yang terukur.
