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
NAVY = (16, 25, 47)
BLUE = (31, 79, 216)
WHITE = (255, 255, 255)
MIST = (214, 226, 255)

TAGLINES = {
    "en": "IT support and software\nin Tallinn",
    "et": "IT tugi ja tarkvara\nTallinnas",
    "ru": "IT-поддержка и разработка\nв Таллине",
}


def raster_mark():
    svg = ASSETS / "mark.svg"
    BRAND.mkdir(parents=True, exist_ok=True)
    sizes = {
        "favicon-32.png": 32,
        "favicon-192.png": 192,
        "favicon-512.png": 512,
        "apple-touch-icon.png": 180,
    }
    for name, size in sizes.items():
        subprocess.check_call(
            ["rsvg-convert", "-w", str(size), "-h", str(size), str(svg), "-o", str(BRAND / name)]
        )
    icon = Image.open(BRAND / "favicon-32.png").convert("RGBA")
    icon16 = icon.resize((16, 16), Image.Resampling.LANCZOS)
    icon48 = Image.open(BRAND / "favicon-192.png").resize((48, 48), Image.Resampling.LANCZOS)
    icon16.save(BRAND / "favicon.ico", sizes=[(16, 16), (32, 32), (48, 48)], append_images=[icon, icon48])


def og_image(code, headline):
    image = Image.new("RGB", (1200, 630), NAVY)
    draw = ImageDraw.Draw(image)
    draw.rectangle((0, 0, 18, 630), fill=BLUE)
    mark = Image.open(BRAND / "favicon-192.png").convert("RGBA").resize((84, 84), Image.Resampling.LANCZOS)
    image.paste(mark, (72, 64), mark)
    small = ImageFont.truetype(str(FONT_BOLD), 28)
    large = ImageFont.truetype(str(FONT_BOLD), 68)
    body = ImageFont.truetype(str(FONT), 32)
    draw.text((176, 78), "COMPATH OÜ", font=small, fill=MIST)
    draw.multiline_text((72, 200), headline, font=large, fill=WHITE, spacing=12)
    draw.text((72, 540), "compath.ee  ·  Ahtri 12, Tallinn", font=body, fill=MIST)
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
        if path.suffix.lower() not in {".png", ".webp", ".svg", ".ico"}:
            continue
        if path.suffix.lower() == ".ico":
            info[path.name] = {"bytes": path.stat().st_size}
            continue
        with Image.open(path) as image:
            info[path.name] = {"width": image.size[0], "height": image.size[1], "bytes": path.stat().st_size}
    (BRAND / "images.json").write_text(json.dumps(info, indent=2) + "\n", encoding="utf-8")


def main():
    raster_mark()
    for code, line in TAGLINES.items():
        og_image(code, line)
    product_images()
    manifest()
    print("wrote", BRAND)


if __name__ == "__main__":
    main()
