Video storyboard panduan visual SIMS (1920×1080).
URL publik: /videos/panduan/{id}.mp4

Satu video per fitur, misalnya:
- login.mp4, dashboard.mp4, absensi.mp4, arenabelajar.mp4, ai.mp4, settings.mp4
- Fitur baru (2026-07): ai-foto-soal.mp4, ai-catatan-siswa.mp4, ai-export-mobile.mp4,
  absensi-guru-self.mp4, kartu-id-guru.mp4

Render ulang seluruh fitur:
  python docs/panduan-render/render_sims_panduan_all.py

Hanya fitur yang belum punya video:
  python docs/panduan-render/render_sims_panduan_all.py --missing-video

Satu atau beberapa id:
  python docs/panduan-render/render_sims_panduan_all.py --only ai-foto-soal,ai-catatan-siswa
