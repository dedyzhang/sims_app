"""Shared slide renderer for SIMS panduan visual (1920×1080)."""

from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

W, H = 1920, 1080
FPS = 30
S = W / 1280  # scale factor from legacy 1280×720 layout


def _scale(v: float) -> int:
    return int(v * S)


def font(size: int, bold: bool = False):
    scaled = max(12, _scale(size))
    candidates = [
        "C:/Windows/Fonts/segoeuib.ttf" if bold else "C:/Windows/Fonts/segoeui.ttf",
        "C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf",
        "C:/Windows/Fonts/calibrib.ttf" if bold else "C:/Windows/Fonts/calibri.ttf",
    ]
    for p in candidates:
        if Path(p).exists():
            return ImageFont.truetype(p, scaled)
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
            dx = (x - W / 2) / (W / 2)
            dy = (y - H / 2) / (H / 2)
            v = 1 - 0.12 * (dx * dx + dy * dy)
            px[x, y] = (
                max(0, min(255, int(r * v))),
                max(0, min(255, int(g * v))),
                max(0, min(255, int(b * v))),
            )
    return img


def draw_card(xy, wh, radius=28, fill=(255, 255, 255, 235)):
    x, y = xy
    w, h = wh
    overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    od = ImageDraw.Draw(overlay)
    od.rounded_rectangle([x, y, x + w, y + h], radius=_scale(radius), fill=fill)
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
    pad_x = _scale(72)
    pad_top = _scale(88)
    card_w = W - pad_x * 2
    card_h = H - _scale(176)
    inner_x = _scale(96)

    base = gradient_bg()
    card = draw_card((pad_x, pad_top), (card_w, card_h), radius=32, fill=(255, 255, 255, 242))
    img = Image.alpha_composite(base.convert("RGBA"), card).convert("RGB")
    d = ImageDraw.Draw(img)

    f_brand = font(22, True)
    f_title = font(42, True)
    f_sub = font(22, False)
    f_bullet = font(26, False)
    f_meta = font(18, True)
    f_tip = font(20, False)

    chip_w = _scale(260)
    chip_h = _scale(40)
    chip_y = _scale(112)
    d.rounded_rectangle([inner_x, chip_y, inner_x + chip_w, chip_y + chip_h], radius=_scale(20), fill=accent)
    d.text((inner_x + _scale(20), chip_y + _scale(8)), brand, font=f_brand, fill=(255, 255, 255))

    meta = f"Shot {shot_no}/{total}"
    tw = d.textlength(meta, font=f_meta)
    d.text((W - inner_x - tw, chip_y + _scale(8)), meta, font=f_meta, fill=(71, 85, 105))

    d.text((inner_x, _scale(180)), title, font=f_title, fill=(15, 23, 42))
    text_w = W - inner_x * 2 - _scale(48)
    for i, line in enumerate(wrap(d, subtitle, f_sub, text_w)):
        d.text((inner_x, _scale(240) + i * _scale(30)), line, font=f_sub, fill=(71, 85, 105))

    y0 = _scale(320)
    bullet_x = inner_x + _scale(44)
    for b in bullets:
        d.ellipse(
            [inner_x + _scale(8), y0 + _scale(8), inner_x + _scale(24), y0 + _scale(24)],
            fill=accent,
        )
        wrapped = wrap(d, b, f_bullet, W - bullet_x - inner_x)
        for j, line in enumerate(wrapped):
            d.text((bullet_x, y0 + j * _scale(34)), line, font=f_bullet, fill=(30, 41, 59))
        y0 += _scale(34) * max(1, len(wrapped)) + _scale(14)

    if tip:
        tip_y1 = H - _scale(150)
        tip_y2 = H - _scale(110)
        d.rounded_rectangle([inner_x, tip_y1, W - inner_x, tip_y2], radius=_scale(14), fill=(241, 245, 249))
        d.text((inner_x + _scale(20), tip_y1 + _scale(8)), tip, font=f_tip, fill=(51, 65, 85))

    d.text((inner_x, H - _scale(56)), "SIMS · Panduan Visual · 1920×1080", font=f_meta, fill=(148, 163, 184))
    return img
