#!/usr/bin/env bash
# Build a WordPress.org–compliant zip of Compath Choir Rehearsal (Lite).
# Package slug/folder: compath-choir-rehearsal
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/choir-rehearsal"
SLUG="compath-choir-rehearsal"
OUT_DIR="${1:-$ROOT/dist}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$OUT_DIR"
OUT_DIR="$(cd "$OUT_DIR" && pwd)"
mkdir -p "$STAGE/$SLUG"

# Copy plugin tree, then remove paths that must not ship to wordpress.org.
cp -a "$SRC/." "$STAGE/$SLUG/"
rm -rf \
  "$STAGE/$SLUG/tests" \
  "$STAGE/$SLUG/updates" \
  "$STAGE/$SLUG/docs/deploy" \
  "$STAGE/$SLUG/docs/shop-setup.md" \
  "$STAGE/$SLUG/docs/product-page.html" \
  "$STAGE/$SLUG/docs/product-data.json"
rm -f "$STAGE/$SLUG/update.json"
# Internal docs name the commercial add-on. The catalog zip does not ship them.
rm -rf "$STAGE/$SLUG/docs"
# Demo sandbox drop-in must never ship in the Plugin Directory zip.
rm -f "$STAGE/$SLUG/includes/distribution-demo.php"
# Song notes are not part of this package. Omit the implementation entirely.
rm -f \
  "$STAGE/$SLUG/includes/class-song-notes.php" \
  "$STAGE/$SLUG/includes/class-song-notes-format.php" \
  "$STAGE/$SLUG/public/js/song-notes.js"
php "$ROOT/scripts/wporg-readme.php" "$STAGE/$SLUG/readme.txt"
# Plugin Check fails if custom updater code is present, even when disabled at runtime.
# That file also holds the SureCart update-check control, so the .org package has neither.
rm -f "$STAGE/$SLUG/includes/class-updater.php"
python3 "$ROOT/scripts/prepare-wporg-tree.py" "$STAGE/$SLUG"
# WordPress.org package: full feature set, no Lite/Pro commercial gates in shipped code.
# The mixer is Pro/Demo only. Do not ship its PHP or scripts (no gated mixer code).
cp "$STAGE/$SLUG/includes/edition-wporg.php" "$STAGE/$SLUG/includes/class-edition.php"
rm -f "$STAGE/$SLUG/includes/edition-wporg.php"
rm -f \
  "$STAGE/$SLUG/includes/class-mixer.php" \
  "$STAGE/$SLUG/public/js/mixer.js" \
  "$STAGE/$SLUG/public/js/mixer-logic.js" \
  "$STAGE/$SLUG/admin/js/mixer-launch.js"
python3 - "$STAGE/$SLUG/readme.txt" <<'PY'
import re, sys
path = sys.argv[1]
text = open(path, encoding="utf-8").read()
kept = []
for line in text.splitlines():
    if re.match(r"^=+", line.strip()):
        kept.append(line)
        continue
    parts = re.split(r"(?<=[.!?])\s+", line)
    parts = [part for part in parts if not re.search(r"\bPro\b", part) and "shop.compath.ee" not in part]
    cleaned = " ".join(part for part in parts if part.strip())
    if cleaned.strip():
        kept.append(cleaned)
    elif line.strip() == "":
        kept.append("")
open(path, "w", encoding="utf-8").write("\n".join(kept).rstrip() + "\n")
PY

# Mark package as WordPress.org distribution (disables GitHub self-updater).
cat > "$STAGE/$SLUG/includes/distribution-wporg.php" <<'PHP'
<?php
/**
 * Present only in WordPress.org builds.
 * Disables the GitHub self-updater (guideline: updates only via WordPress.org).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CHOIR_REHEARSAL_DISTRIBUTION' ) ) {
	define( 'CHOIR_REHEARSAL_DISTRIBUTION', 'wporg' );
}
PHP

VERSION="$(grep -E "^\s*\* Version:" "$STAGE/$SLUG/choir-rehearsal.php" | head -1 | sed -E 's/.*Version:[[:space:]]*//')"
ZIP="$OUT_DIR/${SLUG}-wporg-${VERSION}.zip"

rm -f "$ZIP"
( cd "$STAGE" && zip -rq "$ZIP" "$SLUG" -x '*.DS_Store' )

echo "Built $ZIP"
# grep -q closes unzip early; with pipefail that is SIGPIPE (141) even when the match exists.
set +o pipefail
unzip -l "$ZIP" | grep -E 'distribution-wporg|pdf\.min\.js' | head -5 || true

unzip -l "$ZIP" | grep -q 'distribution-wporg.php'
unzip -l "$ZIP" | grep -q 'assets/vendor/pdfjs/pdf.min.js'
if unzip -l "$ZIP" | grep -qE '(^|/)update\.json$|tests/|shop-setup\.md|class-updater\.php|distribution-demo\.php|class-song-notes\.php|class-song-notes-format\.php|song-notes\.js'; then
  echo "ERROR: forbidden paths found in wporg zip" >&2
  unzip -l "$ZIP" | grep -E 'update\.json|tests/|shop-setup|class-updater|distribution-demo|song-notes' || true
  exit 1
fi
if unzip -p "$ZIP" "$SLUG/includes/class-frontend.php" | grep -q 'cdnjs'; then
  echo "ERROR: CDN reference still present" >&2
  exit 1
fi
# Static Plugin Check looks for these identifiers in any PHP file.
if unzip -l "$ZIP" | awk '/\.php$/ {print $NF}' | while read -r php_path; do
  unzip -p "$ZIP" "$php_path"
done | grep -Eiq 'pre_set_site_transient_update_plugins|site_transient_update_plugins|_site_transient_update_plugins'; then
  echo "ERROR: plugin updater identifiers still present in wporg zip PHP" >&2
  exit 1
fi
if ! unzip -p "$ZIP" "$SLUG/readme.txt" | grep -Eq '^Tested up to:[[:space:]]*7\.1[[:space:]]*$'; then
  echo "ERROR: readme.txt Tested up to must be 7.1 for Plugin Check" >&2
  unzip -p "$ZIP" "$SLUG/readme.txt" | grep -E '^Tested up to:' || true
  exit 1
fi
if ! unzip -p "$ZIP" "$SLUG/choir-rehearsal.php" | grep -q 'Plugin Name:       Compath Choir Rehearsal'; then
  echo "ERROR: Plugin Name must be Compath Choir Rehearsal" >&2
  exit 1
fi
if ! unzip -l "$ZIP" | grep -q "^.* ${SLUG}/choir-rehearsal.php$"; then
  echo "ERROR: zip root folder must be ${SLUG}/" >&2
  unzip -l "$ZIP" | head -20
  exit 1
fi
if unzip -l "$ZIP" | grep -q 'edition-wporg.php'; then
  echo "ERROR: edition-wporg.php must not ship in wporg zip" >&2
  exit 1
fi
if unzip -l "$ZIP" | grep -q 'distribution-demo.php'; then
  echo "ERROR: distribution-demo.php must not ship in wporg zip" >&2
  exit 1
fi
if unzip -p "$ZIP" "$SLUG/includes/class-edition.php" | grep -qE 'LITE_MAX_TRACKS|choir_rehearsal_is_pro|is_full_edition'; then
  echo "ERROR: wporg class-edition.php must not contain Lite/Pro gate identifiers" >&2
  exit 1
fi
if unzip -p "$ZIP" "$SLUG/includes/class-slugs.php" | grep -q "add_filter( 'sanitize_title'"; then
  echo "ERROR: wporg build must not register global sanitize_title filter" >&2
  exit 1
fi
if unzip -p "$ZIP" "$SLUG/includes/class-edition.php" | grep -q 'can_record(): bool' \
  && unzip -p "$ZIP" "$SLUG/includes/class-edition.php" | grep -q 'can_use_mixer(): bool'; then
  :
else
  echo "ERROR: wporg class-edition.php missing capability helpers" >&2
  exit 1
fi
if unzip -p "$ZIP" "$SLUG/includes/class-edition.php" | grep -A2 'function can_use_mixer' | grep -q 'return true'; then
  echo "ERROR: wporg package must not unlock the mixer" >&2
  exit 1
fi
if unzip -l "$ZIP" | grep -qE 'class-mixer\.php|public/js/mixer\.js|public/js/mixer-logic\.js|admin/js/mixer-launch\.js'; then
  echo "ERROR: mixer implementation must not ship in the wporg zip" >&2
  exit 1
fi
if unzip -p "$ZIP" "$SLUG/readme.txt" | grep -qiE '\bPro\b|shop\.compath\.ee|song notes|\bmixer\b|surecart'; then
  echo "ERROR: wporg readme.txt must not mention Pro, the shop, song notes, the mixer, or SureCart" >&2
  exit 1
fi
if unzip -l "$ZIP" | awk '/\.php$/ {print $NF}' | while read -r php_path; do
  unzip -p "$ZIP" "$php_path"
done | grep -q 'choir-mixer__fader'; then
  echo "ERROR: mixer panel markup must not ship in the wporg zip" >&2
  exit 1
fi
python3 - "$ZIP" "$SLUG" <<'PY'
import sys, zipfile, re
zip_path, slug = sys.argv[1], sys.argv[2]
banned = re.compile(r"surecart", re.I)
pro_readme = re.compile(r"\bPro\b|song notes|\bmixer\b", re.I)
with zipfile.ZipFile(zip_path) as zf:
    names = zf.namelist()
    if any(n.endswith("class-updater.php") for n in names):
        sys.exit("ERROR: class-updater.php shipped in wporg zip")
    if any(n.endswith("class-song-notes.php") or n.endswith("class-song-notes-format.php") or n.endswith("song-notes.js") for n in names):
        sys.exit("ERROR: song notes code shipped in wporg zip")
    if any("/docs/" in n for n in names):
        sys.exit("ERROR: docs shipped in wporg zip")
    readme = zf.read(f"{slug}/readme.txt").decode("utf-8")
    if pro_readme.search(readme) or banned.search(readme) or "shop.compath.ee" in readme:
        sys.exit("ERROR: wporg readme mentions Pro, SureCart, song notes, the mixer, or the shop")
    headings = set(re.findall(r"(?m)^= (\d+\.\d+\.\d+) =$", readme))
    stable = re.search(r"(?m)^Stable tag:\s*(\d+\.\d+\.\d+)\s*$", readme)
    if not stable or stable.group(1) not in headings:
        sys.exit("ERROR: stable tag missing from wporg changelog")
    for name in names:
        if name.endswith((".php", ".txt", ".po", ".json", ".md", ".js", ".css")) or name.endswith(".l10n.php"):
            text = zf.read(name).decode("utf-8", "replace")
            if banned.search(text):
                sys.exit(f"ERROR: {name} mentions SureCart")
print("OK wporg package has no SureCart, song notes, or mixer code")
PY
echo "OK wporg package checks passed"
