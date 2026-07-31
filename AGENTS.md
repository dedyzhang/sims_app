# Agent Instructions — SIMS (B'tive)

## Standing instructions

Baca `PRD.md` dan seluruh isi folder `features/` sebelum mengerjakan task apa pun di project ini. Urutan pengerjaan ikuti nomor fase di `PRD.md` dan task di `features/01-*.md` → `02` → `03`. **Cek `PROGRESS.md` untuk status terakhir** — sinkronkan checklist setelah menyelesaikan task.

## Stack & konvensi wajib

- **Laravel 12** + Blade/Livewire/Alpine (sesuai modul existing).
- Primary key **UUID** (`HasUuids`); multi-tenant **`school_id`** + global scope — jangan filter manual per query.
- Uang/nominal: **integer rupiah (BIGINT)** + **BCMath** — tidak ada float.
- Auth/role: **Spatie permission** + **Policy**; data sensitif (nilai, absensi, transaksi): **activity log**.
- Write multi-tabel: **`DB::transaction()`**.
- UI default **Bahasa Indonesia**.

## Modul aktif: Arena Belajar

Task terkait kuis interaktif / Arena Belajar: prompt implementasi Fase 1 ada di `PROMPT-ARENA-BELAJAR.md`. Jangan lompat fase tanpa approval FL untuk task yang menyentuh migration, auth/policy, atau alur nilai.

## Modul lain: Piket Guru & Substitusi Kelas

PRD + task breakdown terpisah di `docs/piket-guru/PRD.md` + `docs/piket-guru/features/01-*.md` s.d. `05-*.md`, status di `docs/piket-guru/PROGRESS.md`. Belum ada implementasi — beberapa fase **blocked** menunggu verifikasi skema `absensi_ptk`/`jadwal_pelajaran`/`buku_agenda` dan keputusan role `guru_piket`/`waka_kurikulum` (lihat PRD §9). Jangan mulai task migration/auth di modul ini tanpa cek blocker tersebut.

## Review sebelum rilis

Audit keamanan Laravel (IDOR multi-tenant, policy, uang, data siswa): pakai skill **`laravel-security-audit`**. Jangan commit kecuali FL minta eksplisit.

## Skills FL (v2)

Skill kanonik ada di `.claude/skills/` (disamakan dengan `~/.cursor/skills` dan `~/.codex/skills`). Baca `SKILL.md` terkait sebelum task yang cocok. Kalau ada konflik antar skill, **`skill-fl` menang**.

| Skill | Pakai saat |
| --- | --- |
| `skill-fl` | Setiap build software FL — identitas + konvensi teknis kanonik (UUID, `school_id`, rupiah BIGINT+BCMath, Spatie, `DB::transaction()`) |
| `prd-generator` | Buat PRD / dokumen kebutuhan / breakdown fitur (format Struktur-PRD-Task) |
| `senior-prompt-engineering` | Susun **satu** blok prompt siap-tempel (bukan dokumen PRD) |
| `laravel-security-audit` | Security patch / harden / review sebelum rilis ke sekolah/klien |
| `android-native-offline-first` | App Android native (Kotlin/Compose/Room) offline-first sync ke Laravel |
| `android-webview-app-builder` | App Android WebView wrapper / PWA-to-APK |

Sumber sync: folder `skills-fl-v2`. Backup versi lama disimpan di luar tree aktif (`~/.cursor/skills-backup`, `~/.codex/skills-backup`) supaya tidak ikut ter-load.