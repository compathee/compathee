#!/usr/bin/env bash
# Build Demo sandbox zip for demo.rehearsal.compath.ee.
# Keeps includes/distribution-demo.php (sets CHOIR_REHEARSAL_DISTRIBUTION=demo).
# Asset: compath-choir-rehearsal-demo.zip — root folder: choir-rehearsal/
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/choir-rehearsal"
FOLDER="choir-rehearsal"
ASSET="compath-choir-rehearsal-demo.zip"
OUT_DIR="${1:-$ROOT/dist}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

if [[ ! -f "$SRC/includes/distribution-demo.php" ]]; then
  echo "ERROR: includes/distribution-demo.php missing — checkout the Demo branch." >&2
  exit 1
fi

mkdir -p "$OUT_DIR"
mkdir -p "$STAGE/$FOLDER"
cp -a "$SRC/." "$STAGE/$FOLDER/"

rm -rf \
  "$STAGE/$FOLDER/tests" \
  "$STAGE/$FOLDER/docs/deploy"
rm -f "$STAGE/$FOLDER/includes/distribution-wporg.php"
# Keep distribution-demo.php — that is what makes this a Demo build.

VERSION="$(grep -E "^\s*\* Version:" "$STAGE/$FOLDER/choir-rehearsal.php" | head -1 | sed -E 's/.*Version:[[:space:]]*//')"
ZIP="$OUT_DIR/$ASSET"

rm -f "$ZIP"
( cd "$STAGE" && zip -rq "$ZIP" "$FOLDER" -x '*.DS_Store' )

echo "Built $ZIP (Demo v${VERSION}, folder ${FOLDER}/)"
unzip -l "$ZIP" | head -20
if ! unzip -l "$ZIP" | grep -q " ${FOLDER}/includes/distribution-demo.php$"; then
  echo "ERROR: Demo zip must contain distribution-demo.php" >&2
  exit 1
fi
if unzip -l "$ZIP" | grep -q 'distribution-wporg.php'; then
  echo "ERROR: Demo zip must not contain distribution-wporg.php" >&2
  exit 1
fi
