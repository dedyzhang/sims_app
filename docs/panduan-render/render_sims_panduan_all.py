"""
Render seluruh video & screenshot panduan SIMS (1920×1080).

Membaca data storyboard dari resources/panduan/visual.html,
menghasilkan:
  - public/videos/panduan/{id}.mp4
  - public/images/panduan/{img}  (hero)
  - public/images/panduan/{id}-sN.png  (gallery, bila ada)

Jalankan dari root repo:
  python docs/panduan-render/render_sims_panduan_all.py
"""

from __future__ import annotations

import re
import subprocess
import tempfile
from pathlib import Path

from PIL import Image

from panduan_slide import FPS, H, W, make_slide

ROOT = Path(__file__).resolve().parents[2]
VISUAL_HTML = ROOT / "resources" / "panduan" / "visual.html"
OUT_VID = ROOT / "public" / "videos" / "panduan"
OUT_IMG = ROOT / "public" / "images" / "panduan"

CAT_ACCENT = {
    "Akses & Akun": (47, 95, 239),
    "Beranda": (139, 92, 246),
    "Data Master": (20, 184, 166),
    "Absensi & Presensi": (14, 165, 233),
    "Akademik": (59, 130, 246),
    "Agenda": (168, 85, 247),
    "Poin & Aturan (Kedisiplinan)": (239, 68, 68),
    "Wali Kelas": (245, 158, 11),
    "Sarana & Prasarana": (16, 185, 129),
    "Keuangan": (34, 197, 94),
    "Sistem & Pengaturan": (99, 102, 241),
}

# Override shots untuk fitur yang punya konten khusus / update terbaru
CUSTOM = {
    "arenabelajar": {
        "brand": "Arena Belajar",
        "accent": (14, 165, 233),
        "shots": [
            {
                "title": "Masuk Arena dari Ruang Kelas",
                "subtitle": "Hub kuis interaktif & misi edukatif per mapel.",
                "bullets": [
                    "Akademik → Ruang Kelas → pilih ruang mapel",
                    "Buka tab Arena Belajar — mode Kuis atau Misi",
                    "Aktifkan modul di Pengaturan → Fitur → Arena Belajar",
                ],
            },
            {
                "title": "Kode Masuk & QR Gabung",
                "subtitle": "Experience terkunci membutuhkan token arena.",
                "bullets": [
                    "Panel Kode masuk arena tampil saat Solo atau Live",
                    "Token otomatis saat kuis diterbitkan (bisa disalin guru)",
                    "QR & barcode SIMS-ARENA untuk siswa gabung cepat",
                ],
                "tip": "Bagikan token/QR hanya ke peserta yang berhak",
            },
            {
                "title": "Buat, Terbitkan & Template",
                "subtitle": "Siswa baru bisa main setelah experience diterbitkan.",
                "bullets": [
                    "Buat Kuis → isi soal → Terbitkan (ikuti petunjuk jari bila draf)",
                    "Skin: Quiz, Pasangkan, Flashcard, Teka-teki, Susun kata, Ular tangga",
                    "Solo: chip Solo · soal acak — urutan & opsi diacak per attempt",
                ],
            },
            {
                "title": "Live, Salin & Transfer Nilai",
                "subtitle": "Main bareng di kelas lalu arsipkan skor.",
                "bullets": [
                    "Mulai Live → siswa masuk pakai token/QR → jawab real-time",
                    "Podium & leaderboard setelah sesi berakhir",
                    "Salin soal ke kelas lain (mapel sama) · Transfer Nilai ke rapor",
                ],
            },
            {
                "title": "Mode Misi & jenjang",
                "subtitle": "Tugaskan misi edukatif per mapel.",
                "bullets": [
                    "Tab Misi → filter SD / SMP / SMA-SMK atau Tren 25–26",
                    "Guru tugaskan → siswa main → monitor hasil",
                    "Transfer nilai ke buku nilai bila siap",
                ],
            },
        ],
    },
    "arenakuis": {
        "brand": "Arena · Kuis",
        "accent": (59, 130, 246),
        "shots": [
            {
                "title": "Alur guru: buat sampai main",
                "subtitle": "Draf wajib diterbitkan agar siswa melihat.",
                "bullets": [
                    "Buat / sunting experience di form kuis",
                    "Klik Terbitkan — token arena terisi otomatis",
                    "Siswa: Mulai → jawab → Kumpulkan → lihat hasil",
                ],
            },
            {
                "title": "Token Live & QR Gabung",
                "subtitle": "Siswa masuk live hanya dengan kode yang benar.",
                "bullets": [
                    "Panel Kode masuk arena di halaman experience guru",
                    "Tampilkan QR/barcode untuk scan siswa di perangkat",
                    "Format barcode: SIMS-ARENA:LIVE:TOKEN",
                ],
            },
            {
                "title": "Panel kelola & salin soal",
                "subtitle": "Ubin aksi berwarna: hasil, live, template, tim, PDF.",
                "bullets": [
                    "Skin template termasuk Ular tangga",
                    "Salin soal ke kelas lain dengan mapel yang sama",
                    "Mode solo mengacak soal & opsi per percobaan",
                ],
            },
            {
                "title": "Siswa main solo atau live",
                "subtitle": "Live urutan tetap; solo acak per attempt.",
                "bullets": [
                    "Solo: chip Solo · soal acak di layar permainan",
                    "Live: host maju soal → Akhiri → podium",
                    "Hasil bisa ditransfer ke nilai formatif/sumatif",
                ],
            },
            {
                "title": "Ular tangga & skin lain",
                "subtitle": "Template permainan variatif.",
                "bullets": [
                    "Pilih skin Quiz, Pasangkan, Flashcard, Teka-teki, Susun kata",
                    "Ular tangga: sampai ubin finish = menang",
                    "Preview template sebelum siswa main",
                ],
            },
        ],
    },
    "arenamisi": {
        "brand": "Arena · Misi",
        "accent": (168, 85, 247),
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
    "arenatren": {
        "brand": "Arena · Tren 25–26",
        "accent": (236, 72, 153),
        "shots": [
            {
                "title": "Chip Tren 2025–2026",
                "subtitle": "Literasi AI, media kritis, iklim, green computing.",
                "bullets": [
                    "Buka Arena → mode Misi → chip Tren 25–26",
                    "Saring jenjang SD / SMP / SMA-SMK",
                    "Kartu misi bertanda badge Tren",
                ],
            },
            {
                "title": "Contoh misi per jenjang",
                "subtitle": "SD: Jeda Layar Sehat · SMP: Cek Fakta · SMA: Prompt Cerdas.",
                "bullets": [
                    "Deepfake di Dunia Kerja (SMA/SMK)",
                    "Green Computing di Lab",
                    "Fakta vs Dongeng Online (SD)",
                ],
            },
            {
                "title": "Guru tugaskan & pantau",
                "subtitle": "Jadwal opsional + monitor hasil.",
                "bullets": [
                    "Tugaskan misi tren ke kelas",
                    "Siswa main → skor tersimpan",
                    "Transfer nilai bila siap",
                ],
            },
            {
                "title": "Debrief kelas",
                "subtitle": "Diskusi singkat pasca-main.",
                "bullets": [
                    "Apa yang dipelajari dari misi?",
                    "Kaitkan ke PPKn, IPA, Informatika, PJOK",
                    "Tanpa platform eksternal",
                ],
            },
        ],
    },
    "ai": {
        "brand": "Asisten Guru",
        "accent": (16, 185, 129),
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
                "subtitle": "Chat Nalar + sisa kuota generate dalam satu panel.",
                "bullets": [
                    "Buka folder collapsible Nalar Guru & Kuota",
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
            },
            {
                "title": "Siap dipakai di kelas",
                "subtitle": "Dari ide AI sampai siswa bermain.",
                "bullets": [
                    "Periksa kunci jawaban & kurikulum",
                    "Terbitkan experience di Arena",
                    "Main solo / live dengan token & QR",
                ],
            },
        ],
    },
}

GALLERY_IDS = {"arenabelajar", "arenakuis", "arenamisi", "ai"}
AI_GALLERY_ALIASES = True


def parse_features(html: str) -> list[dict]:
    chunk = html.split("const F = [", 1)[1].split("];", 1)[0]
    blocks = re.split(r"\n(?=\{cat:)", chunk)
    features = []
    for block in blocks:
        block = block.strip().rstrip(",")
        if not block.startswith("{cat:"):
            continue
        fid = re.search(r'id:"([^"]+)"', block)
        if not fid:
            continue
        cat = re.search(r'cat:"([^"]+)"', block).group(1)
        title = re.search(r'title:"([^"]+)"', block).group(1)
        img = re.search(r'img:"([^"]+)"', block).group(1)
        narr_m = re.search(r'narr:"((?:[^"\\]|\\.)*)"', block)
        narr = narr_m.group(1) if narr_m else ""
        dur_m = re.search(r'dur:"([^"]+)"', block)
        dur = dur_m.group(1) if dur_m else "~40 detik"
        shots_m = re.search(r"shots:\[(.*?)\]\s*,\s*narr:", block, re.DOTALL)
        shots_raw = shots_m.group(1) if shots_m else ""
        sb_shots = re.findall(r'\["(\d+)","([^"]+)","([^"]+)"\]', shots_raw)
        gallery_m = re.search(r"gallery:\[(.*?)\]", block, re.DOTALL)
        gallery = []
        if gallery_m:
            gallery = re.findall(r'"([^"]+)"', gallery_m.group(1))
        features.append(
            {
                "id": fid.group(1),
                "cat": cat,
                "title": title,
                "img": img,
                "narr": narr,
                "dur": dur,
                "sb_shots": sb_shots,
                "gallery": gallery,
            }
        )
    return features


def seconds_per_shot(dur: str, n: int) -> float:
    m = re.search(r"(\d+)", dur)
    total = int(m.group(1)) if m else 40
    return max(3.5, total / max(1, n))


def default_shots(feature: dict) -> list[dict]:
    title = feature["title"]
    narr = feature["narr"]
    out = []
    for i, (_num, shot_title, action) in enumerate(feature["sb_shots"]):
        bullets = [action]
        if i == 0 and narr:
            snippet = narr.replace("'", "")
            if len(snippet) > 110:
                snippet = snippet[:107] + "..."
            bullets.append(snippet)
        out.append(
            {
                "title": shot_title,
                "subtitle": title,
                "bullets": bullets[:3],
            }
        )
    if not out:
        out.append(
            {
                "title": title,
                "subtitle": "Panduan SIMS",
                "bullets": ["Ikuti langkah di panduan visual", narr or "SIMS SMP Maitreyawira"],
            }
        )
    return out


def build_config(feature: dict) -> dict:
    fid = feature["id"]
    if fid in CUSTOM:
        cfg = CUSTOM[fid].copy()
        cfg.setdefault("seconds_per_shot", seconds_per_shot(feature["dur"], len(cfg["shots"])))
        return cfg
    shots = default_shots(feature)
    return {
        "brand": feature["cat"].split("(")[0].strip()[:28],
        "accent": CAT_ACCENT.get(feature["cat"], (59, 130, 246)),
        "shots": shots,
        "seconds_per_shot": seconds_per_shot(feature["dur"], len(shots)),
    }


def save_hero(img_filename: str, slide: Image.Image):
    OUT_IMG.mkdir(parents=True, exist_ok=True)
    path = OUT_IMG / img_filename
    slide.save(path, "PNG", optimize=True)
    return path


def save_gallery(vid_id: str, slides: list[Image.Image], gallery_names: list[str] | None):
    OUT_IMG.mkdir(parents=True, exist_ok=True)
    paths = []
    for i, slide in enumerate(slides, start=1):
        name = gallery_names[i - 1] if gallery_names and i - 1 < len(gallery_names) else f"{vid_id}-s{i}.png"
        p = OUT_IMG / name
        slide.save(p, "PNG", optimize=True)
        paths.append(p)
    return paths


def render_mp4(vid_id: str, slides: list[Image.Image], seconds_per_shot: float):
    OUT_VID.mkdir(parents=True, exist_ok=True)
    out = OUT_VID / f"{vid_id}.mp4"
    with tempfile.TemporaryDirectory(prefix=f"panduan_{vid_id}_") as td:
        tdir = Path(td)
        frames_needed = max(1, int(seconds_per_shot * FPS))
        idx = 0
        for slide in slides:
            for _ in range(frames_needed):
                slide.save(tdir / f"f_{idx:05d}.png")
                idx += 1
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
    import argparse

    parser = argparse.ArgumentParser(description="Render panduan SIMS 1920x1080")
    parser.add_argument("--only", help="Render satu fitur saja (id, mis. arenatren)")
    args = parser.parse_args()

    html = VISUAL_HTML.read_text(encoding="utf-8")
    features = parse_features(html)
    if args.only:
        features = [f for f in features if f["id"] == args.only]
        if not features:
            raise SystemExit(f"Fitur tidak ditemukan: {args.only}")
    print(f"Rendering {len(features)} fitur panduan @ {W}x{H}…")

    for feature in features:
        fid = feature["id"]
        cfg = build_config(feature)
        shots_cfg = cfg["shots"]
        slides = []
        for i, s in enumerate(shots_cfg, start=1):
            slides.append(
                make_slide(
                    brand=cfg.get("brand", feature["cat"]),
                    title=s["title"],
                    subtitle=s.get("subtitle", feature["title"]),
                    bullets=s["bullets"],
                    shot_no=i,
                    total=len(shots_cfg),
                    accent=cfg.get("accent", (59, 130, 246)),
                    tip=s.get("tip"),
                )
            )

        hero_path = save_hero(feature["img"], slides[0])
        mp4_path = render_mp4(fid, slides, cfg["seconds_per_shot"])

        gal_msg = ""
        if fid in GALLERY_IDS or feature["gallery"]:
            gal_paths = save_gallery(fid, slides, feature["gallery"] or None)
            gal_msg = f" + {len(gal_paths)} gallery"
            if fid == "ai" and AI_GALLERY_ALIASES:
                for i, slide in enumerate(slides, start=1):
                    alias = OUT_IMG / f"asisten-ai-s{i}.png"
                    slide.save(alias, "PNG", optimize=True)

        print(f"  OK {fid}: {len(slides)} shots -> {mp4_path.name}, {hero_path.name}{gal_msg}")

    print("Selesai — semua asset panduan 1920×1080 diperbarui.")


if __name__ == "__main__":
    main()
