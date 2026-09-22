#!/usr/bin/env bash
# Build GitHub Release zip for Compath Choir Rehearsal (Lite) with updater.
# Asset name: compath-choir-rehearsal.zip — root folder inside: choir-rehearsal/
# (Existing sites like veneta.ee use wp-content/plugins/choir-rehearsal/.)
# WordPress.org package uses build-wporg-zip.sh → compath-choir-rehearsal/ folder instead.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/choir-rehearsal"
FOLDER="choir-rehearsal"
ASSET="compath-choir-rehearsal.zip"
OUT_DIR="${1:-$ROOT/dist}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$OUT_DIR"
mkdir -p "$STAGE/$FOLDER"
cp -a "$SRC/." "$STAGE/$FOLDER/"

# Keep updater + update.json for GitHub self-updates; omit tests and internal docs.
rm -rf \
  "$STAGE/$FOLDER/tests" \
  "$STAGE/$FOLDER/docs/deploy"
rm -f "$STAGE/$FOLDER/includes/distribution-wporg.php"
rm -f "$STAGE/$FOLDER/includes/distribution-demo.php"

VERSION="$(grep -E "^\s*\* Version:" "$STAGE/$FOLDER/choir-rehearsal.php" | head -1 | sed -E 's/.*Version:[[:space:]]*//')"
ZIP="$OUT_DIR/$ASSET"

rm -f "$ZIP"
( cd "$STAGE" && zip -rq "$ZIP" "$FOLDER" -x '*.DS_Store' )

echo "Built $ZIP (v${VERSION}, folder ${FOLDER}/)"
unzip -l "$ZIP" | head -15
if ! unzip -l "$ZIP" | grep -q " ${FOLDER}/choir-rehearsal.php$"; then
  echo "ERROR: GitHub zip must contain ${FOLDER}/ at root" >&2
  exit 1
fi
if unzip -l "$ZIP" | grep -q ' compath-choir-rehearsal/'; then
  echo "ERROR: GitHub zip must not use compath-choir-rehearsal/ folder" >&2
  exit 1
fi
