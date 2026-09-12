#!/usr/bin/env bash
# Build GitHub Release zip for Compath Choir Rehearsal (Lite) with updater.
# Root folder / asset name: compath-choir-rehearsal
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/choir-rehearsal"
SLUG="compath-choir-rehearsal"
OUT_DIR="${1:-$ROOT/dist}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$OUT_DIR"
mkdir -p "$STAGE/$SLUG"
cp -a "$SRC/." "$STAGE/$SLUG/"

# Keep updater + update.json for GitHub self-updates; omit tests and internal docs.
rm -rf \
  "$STAGE/$SLUG/tests" \
  "$STAGE/$SLUG/docs/deploy"
rm -f "$STAGE/$SLUG/includes/distribution-wporg.php"

VERSION="$(grep -E "^\s*\* Version:" "$STAGE/$SLUG/choir-rehearsal.php" | head -1 | sed -E 's/.*Version:[[:space:]]*//')"
ZIP="$OUT_DIR/${SLUG}.zip"

rm -f "$ZIP"
( cd "$STAGE" && zip -rq "$ZIP" "$SLUG" -x '*.DS_Store' )

echo "Built $ZIP (v${VERSION})"
unzip -l "$ZIP" | head -15
