"""
Render video storyboard panduan: Arena Belajar + Asisten Guru.
Output: public/videos/panduan/*.mp4 dan public/images/panduan/*-sN.png

Jalankan:
  python docs/panduan-render/render_arena_ai_videos.py
"""

from __future__ import annotations

import math
import subprocess
import tempfile
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[2]
OUT_VID = ROOT / "public" / "videos" / "panduan"
OUT_IMG = ROOT / "public" / "images" / "panduan"
W, H = 1280, 720
FPS = 30


def font(size: int, bold: bool = False):
    candidates = [
        "C:/Windows/Fonts/segoeuib.ttf" if bold else "C:/Windows/Fonts/segoeui.ttf",
        "C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf",
        "C:/Windows/Fonts/calibrib.ttf" if bold else "C:/Windows/Fonts/calibri.ttf",
    ]
    for p in candidates:
        if Path(p).exists():
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()


def wrap(draw: ImageDraw.ImageDraw, text: str, fnt, max_w: int) -> list[str]:
    words = text.split()
    lines, cur = [], ""
    for w in words:
        trial = (cur + " " + w).strip()
        if draw.textlength(trial, font=fnt) <= max_w:
            cur = trial
        else:
            if cur:
                lines.append(cur)
            cur = w
    if cur:
        lines.append(cur)
    return lines or [""]


def gradient_bg(c1=(12, 74, 110), c2=(15, 118, 110)) -> Image.Image:
    img = Image.new("RGB", (W, H))
    px = img.load()
    for y in range(H):
        t = y / (H - 1)
        r = int(c1[0] * (1 - t) + c2[0] * t)
        g = int(c1[1] * (1 - t) + c2[1] * t)
        b = int(c1[2] * (1 - t) + c2[2] * t)
        for x in range(W):
            # soft radial vignette
            dx = (x - W / 2) / (W / 2)
            dy = (y - H / 2) / (H / 2)
            v = 1 - 0.12 * (dx * dx + dy * dy)
            px[x, y] = (
                max(0, min(255, int(r * v))),
                max(0, min(255, int(g * v))),
                max(0, min(255, int(b * v))),
            )
    return img


def draw_card(draw: ImageDraw.ImageDraw, xy, wh, radius=28, fill=(255, 255, 255, 235)):
    x, y = xy
    w, h = wh
    # pillow rounded_rectangle supports RGBA overlay via separate layer
    overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    od = ImageDraw.Draw(overlay)
    od.rounded_rectangle([x, y, x + w, y + h], radius=radius, fill=fill)
    return overlay


def make_slide(
    brand: str,
    title: str,
    subtitle: str,
    bullets: list[str],
    shot_no: int,
    total: int,
    accent=(20, 184, 166),
    tip: str | None = None,
) -> Image.Image:
    base = gradient_bg()
    card = draw_card(ImageDraw.Draw(base), (72, 88), (W - 144, H - 176), radius=32, fill=(255, 255, 255, 242))
    img = Image.alpha_composite(base.convert("RGBA"), card).convert("RGB")
    d = ImageDraw.Draw(img)

    f_brand = font(22, True)
    f_title = font(42, True)
    f_sub = font(22, False)
    f_bullet = font(26, False)
    f_meta = font(18, True)
    f_tip = font(20, False)

    # brand chip
    d.rounded_rectangle([96, 112, 96 + 260, 112 + 40], radius=20, fill=accent)
    d.text((116, 120), brand, font=f_brand, fill=(255, 255, 255))

    # progress
    meta = f"Shot {shot_no}/{total}"
    tw = d.textlength(meta, font=f_meta)
    d.text((W - 96 - tw, 120), meta, font=f_meta, fill=(71, 85, 105))

    d.text((96, 180), title, font=f_title, fill=(15, 23, 42))
    for i, line in enumerate(wrap(d, subtitle, f_sub, W - 240)):
        d.text((96, 240 + i * 30), line, font=f_sub, fill=(71, 85, 105))

    y0 = 320
    for b in bullets:
        d.ellipse([104, y0 + 8, 120, y0 + 24], fill=accent)
        for j, line in enumerate(wrap(d, b, f_bullet, W - 280)):
            d.text((140, y0 + j * 34), line, font=f_bullet, fill=(30, 41, 59))
        y0 += 34 * max(1, len(wrap(d, b, f_bullet, W - 280))) + 14

    if tip:
        d.rounded_rectangle([96, H - 150, W - 96, H - 110], radius=14, fill=(241, 245, 249))
        d.text((116, H - 142), tip, font=f_tip, fill=(51, 65, 85))

    # footer
    d.text((96, H - 56), "SIMS · Panduan Visual", font=f_meta, fill=(148, 163, 184))
    return img


VIDEOS = {
    "arenabelajar": {
        "brand": "Arena Belajar",
        "accent": (14, 165, 233),
        "seconds_per_shot": 4.5,
        "shots": [
            {
                "title": "Masuk Arena dari Ruang Kelas",
                "subtitle": "Satu hub untuk kuis interaktif dan misi edukatif per mapel.",
                "bullets": [
                    "Akademik → Ruang Kelas → pilih ruang mapel",
                    "Buka tab Arena Belajar",
                    "Pilih mode Kuis atau Misi",
                ],
                "tip": "Modul diaktifkan di Pengaturan Sistem → Fitur → Arena Belajar",
            },
            {
                "title": "Buat & Terbitkan Kuis",
                "subtitle": "Siswa baru bisa main setelah experience diterbitkan.",
                "bullets": [
                    "Buat Kuis → isi judul & soal (PG, B/S, isian, pasangkan)",
                    "Simpan draf, lalu klik Terbitkan (ada petunjuk jari jika masih draf)",
                    "Panel aksi cepat: hasil, live, template, tim, PDF",
                ],
                "tip": "Jangan lupa Terbitkan — draf tidak terlihat siswa",
            },
            {
                "title": "Template & Main Solo",
                "subtitle": "Skin permainan + urutan soal acak per percobaan.",
                "bullets": [
                    "Pilih skin: Quiz, Pasangkan, Flashcard, Teka-teki, Susun kata, Ular tangga",
                    "Mode solo: chip “Solo · soal acak” — soal & opsi diacak per attempt",
                    "Live tetap urutan tetap (host yang memandu)",
                ],
                "tip": "Ular tangga: sampai ubin finish = menang",
            },
            {
                "title": "Salin, Live, Transfer Nilai",
                "subtitle": "Satu set soal untuk banyak kelas, atau main bareng di kelas.",
                "bullets": [
                    "Salin soal ke kelas lain (mapel sama)",
                    "Mulai Live → siswa jawab real-time → podium",
                    "Hasil → Transfer Nilai ke formatif/sumatif",
                ],
                "tip": "Salin hanya ke ruang dengan mapel yang sama",
            },
        ],
    },
    "arenakuis": {
        "brand": "Arena · Kuis",
        "accent": (59, 130, 246),
        "seconds_per_shot": 4.0,
        "shots": [
            {
                "title": "Alur guru: buat sampai main",
                "subtitle": "Dari draf hingga siswa mengumpulkan.",
                "bullets": [
                    "Buat / sunting soal di form experience",
                    "Terbitkan agar muncul di daftar siswa",
                    "Siswa: Mulai → jawab → Kumpulkan → lihat hasil",
                ],
            },
            {
                "title": "Panel kelola (update terbaru)",
                "subtitle": "Tombol aksi dalam ubin berwarna agar mudah dilacak.",
                "bullets": [
                    "Aksi cepat: sunting, hasil, live, tim",
                    "Mode & ekspor: template play, PDF, sync offline",
                    "Skin template termasuk Ular tangga",
                ],
            },
            {
                "title": "Salin soal ke kelas lain",
                "subtitle": "Hemat waktu untuk guru yang mengajar banyak rombel.",
                "bullets": [
                    "Di halaman Experience, buka Salin soal ke kelas lain",
                    "Centang kelas tujuan (mapel sama)",
                    "Konfirmasi — soal tersalin sebagai experience baru",
                ],
            },
            {
                "title": "Siswa main solo (acak)",
                "subtitle": "Setiap attempt punya urutan soal & opsi berbeda.",
                "bullets": [
                    "Mulai percobaan baru",
                    "Perhatikan chip Solo · soal acak",
                    "Kumpulkan untuk menyimpan skor",
                ],
            },
        ],
    },
    "arenamisi": {
        "brand": "Arena · Misi",
        "accent": (168, 85, 247),
        "seconds_per_shot": 4.0,
        "shots": [
            {
                "title": "Tab Misi di hub Arena",
                "subtitle": "Narasi, keputusan, puzzle, recall, menjodohkan.",
                "bullets": [
                    "Buka Arena → mode Misi",
                    "Panel rekomendasi per jenjang SD / SMP / SMA-SMK",
                    "Filter chip Tren 25–26 bila ingin misi tren",
                ],
            },
            {
                "title": "Guru menugaskan misi",
                "subtitle": "Dari katalog ke ruang mapel.",
                "bullets": [
                    "Pilih misi → set jadwal opsional → Tugaskan",
                    "Pantau di Monitor hasil",
                    "Transfer nilai ke buku nilai bila siap",
                ],
            },
            {
                "title": "Siswa menyelesaikan misi",
                "subtitle": "Skor tersimpan di kelas.",
                "bullets": [
                    "Buka kartu misi (badge jenjang / Tren)",
                    "Mulai → selesaikan alur → kumpulkan",
                    "Lihat skor di hasil misi",
                ],
            },
            {
                "title": "Debrief singkat di kelas",
                "subtitle": "Main dulu, lalu diskusi makna.",
                "bullets": [
                    "Tanya: apa yang dipelajari?",
                    "Contoh tren: cek fakta, etika AI, hemat energi lab",
                    "Kaitkan ke tujuan mapel hari itu",
                ],
            },
        ],
    },
    "ai": {
        "brand": "Asisten Guru",
        "accent": (16, 185, 129),
        "seconds_per_shot": 4.2,
        "shots": [
            {
                "title": "Hubungkan API key Gemini",
                "subtitle": "Generate memakai key akun Google Anda sendiri.",
                "bullets": [
                    "Akademik → Asisten Guru",
                    "Buka Google AI Studio → Create API key",
                    "Tempel di SIMS → Simpan API key",
                ],
                "tip": "Jangan bagikan API key ke orang lain",
            },
            {
                "title": "Folder Nalar Guru & Kuota",
                "subtitle": "Update: Nalar + kuota digabung dalam satu folder collapsible.",
                "bullets": [
                    "Buka folder Nalar Guru & Kuota",
                    "Chat Nalar / saran prompt",
                    "Pantau sisa Generate Kuota LIVE",
                ],
            },
            {
                "title": "Generator & tab lain",
                "subtitle": "Soal, RPM, ringkasan, draft feedback.",
                "bullets": [
                    "Generator Soal: topik atau unggah materi",
                    "RPM Learning, Perangkum Materi, Draft Feedback",
                    "Pratinjau → unduh Word/PDF → History Generate",
                ],
                "tip": "Hasil AI wajib diperiksa guru sebelum dibagikan",
            },
            {
                "title": "Alur mengajar → Arena",
                "subtitle": "Soal dari AI langsung ke form kuis Arena.",
                "bullets": [
                    "Buat soal di Nalar / Generator",
                    "Kartu Alur mengajar → Kirim ke Arena",
                    "Pilih ruang kelas → buka form kuis Arena",
                ],
                "tip": "Opsional: lanjut ke Studio Presentasi / Canva Pendidikan",
            },
            {
                "title": "Siap dipakai di kelas",
                "subtitle": "Dari ide AI sampai siswa bermain.",
                "bullets": [
                    "Periksa kunci jawaban & kesesuaian kurikulum",
                    "Terbitkan experience di Arena",
                    "Main solo / live / template sesuai kebutuhan",
                ],
            },
        ],
    },
}


def save_gallery(vid_id: str, slides: list[Image.Image]):
    OUT_IMG.mkdir(parents=True, exist_ok=True)
    paths = []
    for i, slide in enumerate(slides, start=1):
        p = OUT_IMG / f"{vid_id}-s{i}.png"
        slide.save(p, "PNG", optimize=True)
        paths.append(p)
    # hero cover = first slide
    hero = OUT_IMG / f"{vid_id}.png"
    if vid_id == "ai":
        hero = OUT_IMG / "asisten-ai.png"
    elif vid_id == "arenabelajar":
        hero = OUT_IMG / "arena-belajar.png"
    elif vid_id == "arenakuis":
        hero = OUT_IMG / "arena-kuis.png"
    elif vid_id == "arenamisi":
        hero = OUT_IMG / "arena-misi.png"
    slides[0].save(hero, "PNG", optimize=True)
    return paths, hero


def render_mp4(vid_id: str, slides: list[Image.Image], seconds_per_shot: float):
    OUT_VID.mkdir(parents=True, exist_ok=True)
    out = OUT_VID / f"{vid_id}.mp4"
    with tempfile.TemporaryDirectory(prefix=f"panduan_{vid_id}_") as td:
        tdir = Path(td)
        frames_needed = max(1, int(seconds_per_shot * FPS))
        idx = 0
        for slide in slides:
            for _ in range(frames_needed):
                # subtle fade pulse via slight brightness on first/last 8 frames of each shot
                slide.save(tdir / f"f_{idx:05d}.png")
                idx += 1
        # fade transition: duplicate last frame of each shot already handled by hold
        cmd = [
            "ffmpeg",
            "-y",
            "-framerate",
            str(FPS),
            "-i",
            str(tdir / "f_%05d.png"),
            "-c:v",
            "libx264",
            "-pix_fmt",
            "yuv420p",
            "-movflags",
            "+faststart",
            str(out),
        ]
        subprocess.run(cmd, check=True, capture_output=True)
    return out


def main():
    print("Rendering Arena Belajar + Asisten Guru panduan videos…")
    for vid_id, cfg in VIDEOS.items():
        shots = cfg["shots"]
        slides = []
        for i, s in enumerate(shots, start=1):
            slides.append(
                make_slide(
                    brand=cfg["brand"],
                    title=s["title"],
                    subtitle=s["subtitle"],
                    bullets=s["bullets"],
                    shot_no=i,
                    total=len(shots),
                    accent=cfg["accent"],
                    tip=s.get("tip"),
                )
            )
        gal, hero = save_gallery(vid_id, slides)
        mp4 = render_mp4(vid_id, slides, cfg["seconds_per_shot"])
        print(f"  OK {vid_id}: {len(slides)} shots -> {mp4.name} + {hero.name} + {len(gal)} gallery")
    # also alias asisten-ai gallery names expected by visual.html
    for i in range(1, 6):
        src = OUT_IMG / f"ai-s{i}.png"
        dst = OUT_IMG / f"asisten-ai-s{i}.png"
        if src.exists():
            Image.open(src).save(dst, "PNG", optimize=True)
    print("Done.")


if __name__ == "__main__":
    main()
