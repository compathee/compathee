#!/usr/bin/env python3
"""Create favicons, Open Graph images, and optimized Choir Rehearsal visuals.

The website itself uses system fonts. DejaVu is rasterized only into the
share images. Re-run this if the mark or the taglines change.
"""

import json
import subprocess
import urllib.request
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parent
ASSETS = ROOT / "assets"
BRAND = ASSETS / "brand"
FONT = Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf")
FONT_BOLD = Path("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf")
BANNER_URL = "https://demo.rehearsal.compath.ee/wp-content/uploads/2026/09/choir-rehearsal-shop-ui-banner.png"
INK = (28, 28, 28)
ORANGE = (226, 61, 0)
AMBER = (245, 176, 0)
WHITE = (255, 255, 255)
PAPER = (247, 245, 242)

TAGLINES = {
    "en": "IT support and software\nin Tallinn",
    "et": "IT tugi ja tarkvara\nTallinnas",
    "ru": "IT-поддержка и разработка\nв Таллине",
}


def unmatte_white(image):
    """Drop a plain white background and keep anti-aliased edges."""
    image = image.convert("RGBA")
    out = Image.new("RGBA", image.size)
    src = image.load()
    dst = out.load()
    for y in range(image.size[1]):
        for x in range(image.size[0]):
            r, g, b, a = src[x, y]
            alpha = 255 - min(r, g, b)
            if a < 255:
                alpha = min(alpha, a)
            if alpha <= 6:
                dst[x, y] = (0, 0, 0, 0)
                continue
            def channel(value):
                return max(0, min(255, round((value - (255 - alpha)) * 255 / alpha)))
            dst[x, y] = (channel(r), channel(g), channel(b), alpha)
    return out


def trim(image, pad=0):
    bbox = image.getbbox()
    if not bbox:
        raise SystemExit("logo has no visible pixels")
    cropped = image.crop(bbox)
    if pad:
        canvas = Image.new("RGBA", (cropped.size[0] + pad * 2, cropped.size[1] + pad * 2), (0, 0, 0, 0))
        canvas.paste(cropped, (pad, pad), cropped)
        return canvas
    return cropped


def even_size(image):
    width, height = image.size
    if width % 2 == 0 and height % 2 == 0:
        return image
    canvas = Image.new("RGBA", (width + (width % 2), height + (height % 2)), (0, 0, 0, 0))
    canvas.paste(image, (0, 0), image)
    return canvas


def prepare_logo():
    source = BRAND / "compath-logo-source.png"
    logo = even_size(trim(unmatte_white(Image.open(source))))
    logo.save(BRAND / "logo.png", optimize=True)
    width, height = logo.size
    meta = {
        "srcWidth": width,
        "srcHeight": height,
        "width": width // 2,
        "height": height // 2,
    }
    (BRAND / "logo-meta.json").write_text(json.dumps(meta, indent=2) + "\n", encoding="utf-8")
    return logo


def dots_from(logo):
    """Keep the orange and yellow dots and drop the black wordmark."""
    src = logo.load()
    out = Image.new("RGBA", logo.size, (0, 0, 0, 0))
    dst = out.load()
    for y in range(logo.size[1]):
        for x in range(logo.size[0]):
            r, g, b, a = src[x, y]
            if a < 20:
                continue
            if max(r, g, b) - min(r, g, b) < 28 and r < 80:
                continue
            if r < 140:
                continue
            dst[x, y] = (r, g, b, a)
    return trim(out, pad=8)


def square_icon(dots, size, background=None):
    canvas = Image.new("RGBA", (size, size), background or (0, 0, 0, 0))
    margin = max(2, round(size * 0.08))
    fitted = dots.copy()
    fitted.thumbnail((size - margin * 2, size - margin * 2), Image.Resampling.LANCZOS)
    x = (size - fitted.size[0]) // 2
    y = (size - fitted.size[1]) // 2
    canvas.paste(fitted, (x, y), fitted)
    if background:
        flat = Image.new("RGB", (size, size), background[:3])
        flat.paste(canvas, mask=canvas.getchannel("A"))
        return flat
    return canvas


def raster_mark(logo):
    dots = dots_from(logo)
    dots.save(BRAND / "logo-dots.png", optimize=True)
    sizes = {
        "favicon-32.png": 32,
        "favicon-192.png": 192,
        "favicon-512.png": 512,
    }
    for name, size in sizes.items():
        square_icon(dots, size).save(BRAND / name, optimize=True)
    square_icon(dots, 180, background=(255, 255, 255, 255)).save(BRAND / "apple-touch-icon.png", optimize=True)
    icon32 = Image.open(BRAND / "favicon-32.png").convert("RGBA")
    icon16 = square_icon(dots, 16)
    icon48 = square_icon(dots, 48)
    icon16.save(BRAND / "favicon.ico", sizes=[(16, 16), (32, 32), (48, 48)], append_images=[icon32, icon48])
    # SVG favicon uses the real dots, scaled. A downscale stays sharper than redrawing them.
    import base64
    payload = base64.b64encode((BRAND / "logo-dots.png").read_bytes()).decode("ascii")
    dw, dh = dots.size
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {dw} {dh}" role="img">
<title>Compath</title>
<image width="{dw}" height="{dh}" href="data:image/png;base64,{payload}"/>
</svg>
'''
    (BRAND / "favicon.svg").write_text(svg, encoding="utf-8")


def og_image(code, headline, logo):
    image = Image.new("RGB", (1200, 630), WHITE)
    draw = ImageDraw.Draw(image)
    for y in range(630):
        t = y / 629
        color = tuple(round(AMBER[i] * (1 - t) + ORANGE[i] * t) for i in range(3))
        draw.line([(0, y), (16, y)], fill=color)
    mark = logo.copy()
    mark.thumbnail((560, 160), Image.Resampling.LANCZOS)
    image.paste(mark, (72, 56), mark)
    large = ImageFont.truetype(str(FONT_BOLD), 64)
    body = ImageFont.truetype(str(FONT), 32)
    draw.multiline_text((72, 250), headline, font=large, fill=INK, spacing=12)
    draw.text((72, 540), "compath.ee  ·  Ahtri 12, Tallinn", font=body, fill=(74, 69, 63))
    png = BRAND / f"og-{code}.png"
    webp = BRAND / f"og-{code}.webp"
    image.save(png, optimize=True)
    image.save(webp, quality=82, method=6)


def product_images():
    cache = Path("/tmp/cr-visuals")
    cache.mkdir(parents=True, exist_ok=True)
    banner = cache / "banner.png"
    if not banner.exists():
        urllib.request.urlretrieve(BANNER_URL, banner)
    shot = Image.open(banner).convert("RGB")
    shot.thumbnail((1200, 1200), Image.Resampling.LANCZOS)
    shot.save(BRAND / "choir-rehearsal.webp", quality=76, method=6)
    # Public demo library. Cropped above the sign-in form so demo passwords
    # are not copied onto the company site.
    demo_src = cache / "demo-desktop.png"
    if demo_src.exists():
        demo = Image.open(demo_src).convert("RGB").crop((60, 108, 1220, 392))
        demo.save(BRAND / "choir-demo.webp", quality=78, method=6)


def manifest():
    info = {}
    for path in sorted(BRAND.glob("*")):
        if path.suffix.lower() not in {".png", ".webp", ".ico"}:
            continue
        if path.suffix.lower() == ".ico":
            info[path.name] = {"bytes": path.stat().st_size}
            continue
        with Image.open(path) as image:
            info[path.name] = {"width": image.size[0], "height": image.size[1], "bytes": path.stat().st_size}
    (BRAND / "images.json").write_text(json.dumps(info, indent=2) + "\n", encoding="utf-8")


def main():
    BRAND.mkdir(parents=True, exist_ok=True)
    logo = prepare_logo()
    raster_mark(logo)
    for code, line in TAGLINES.items():
        og_image(code, line, logo)
    product_images()
    manifest()
    print("wrote", BRAND)


if __name__ == "__main__":
    main()
