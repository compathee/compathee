#!/usr/bin/env bash
# Build a WordPress.org–compliant zip of Choir Rehearsal (Lite).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/choir-rehearsal"
OUT_DIR="${1:-$ROOT/dist}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$OUT_DIR"
mkdir -p "$STAGE/choir-rehearsal"

# Copy plugin tree, then remove paths that must not ship to wordpress.org.
cp -a "$SRC/." "$STAGE/choir-rehearsal/"
rm -rf \
  "$STAGE/choir-rehearsal/tests" \
  "$STAGE/choir-rehearsal/updates" \
  "$STAGE/choir-rehearsal/docs/deploy" \
  "$STAGE/choir-rehearsal/docs/shop-setup.md" \
  "$STAGE/choir-rehearsal/docs/product-page.html" \
  "$STAGE/choir-rehearsal/docs/product-data.json"
rm -f "$STAGE/choir-rehearsal/update.json"
# Plugin Check fails if custom updater code is present, even when disabled at runtime.
rm -f "$STAGE/choir-rehearsal/includes/class-updater.php"

# Mark package as WordPress.org distribution (disables GitHub self-updater).
cat > "$STAGE/choir-rehearsal/includes/distribution-wporg.php" <<'PHP'
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

VERSION="$(grep -E "^\s*\* Version:" "$STAGE/choir-rehearsal/choir-rehearsal.php" | head -1 | sed -E 's/.*Version:[[:space:]]*//')"
ZIP="$OUT_DIR/choir-rehearsal-wporg-${VERSION}.zip"

rm -f "$ZIP"
( cd "$STAGE" && zip -rq "$ZIP" choir-rehearsal -x '*.DS_Store' )

echo "Built $ZIP"
unzip -l "$ZIP" | grep -E 'distribution-wporg|pdf\.min\.js' | head -5

unzip -l "$ZIP" | grep -q 'distribution-wporg.php'
unzip -l "$ZIP" | grep -q 'assets/vendor/pdfjs/pdf.min.js'
if unzip -l "$ZIP" | grep -qE '(^|/)update\.json$|tests/|shop-setup\.md|class-updater\.php'; then
  echo "ERROR: forbidden paths found in wporg zip" >&2
  unzip -l "$ZIP" | grep -E 'update\.json|tests/|shop-setup|class-updater' || true
  exit 1
fi
if unzip -p "$ZIP" choir-rehearsal/includes/class-frontend.php | grep -q 'cdnjs'; then
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
if ! unzip -p "$ZIP" choir-rehearsal/readme.txt | grep -Eq '^Tested up to:[[:space:]]*7\.1[[:space:]]*$'; then
  echo "ERROR: readme.txt Tested up to must be 7.1 for Plugin Check" >&2
  unzip -p "$ZIP" choir-rehearsal/readme.txt | grep -E '^Tested up to:' || true
  exit 1
fi
echo "OK wporg package checks passed"
