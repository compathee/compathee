#!/usr/bin/env bash
# Build choir-rehearsal-pro.zip for SureCart Secure Storage / Current Release.
# Root folder inside zip: choir-rehearsal-pro/ (must match release.json slug).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/choir-rehearsal-pro"
FOLDER="choir-rehearsal-pro"
ASSET="choir-rehearsal-pro.zip"
OUT_DIR="${1:-$ROOT/dist}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$OUT_DIR"
mkdir -p "$STAGE/$FOLDER"
cp -a "$SRC/." "$STAGE/$FOLDER/"

# Omit tests from the customer package.
rm -rf "$STAGE/$FOLDER/tests"

VERSION="$(grep -E "^\s*\* Version:" "$STAGE/$FOLDER/choir-rehearsal-pro.php" | head -1 | sed -E 's/.*Version:[[:space:]]*//')"
ZIP="$OUT_DIR/$ASSET"

rm -f "$ZIP"
( cd "$STAGE" && zip -rq "$ZIP" "$FOLDER" -x '*.DS_Store' )

echo "Built $ZIP (v${VERSION}, folder ${FOLDER}/)"

# Package checks
unzip -l "$ZIP" | grep -q " ${FOLDER}/choir-rehearsal-pro.php$"
unzip -l "$ZIP" | grep -q " ${FOLDER}/release.json$"
unzip -l "$ZIP" | grep -q " ${FOLDER}/licensing/src/Client.php$"
unzip -l "$ZIP" | grep -q " ${FOLDER}/includes/class-licensing.php$"

# release.json slug must match folder
slug="$(python3 -c "import json; print(json.load(open('$STAGE/$FOLDER/release.json'))['slug'])")"
if [[ "$slug" != "$FOLDER" ]]; then
  echo "ERROR: release.json slug '$slug' != folder '$FOLDER'" >&2
  exit 1
fi

ver_json="$(python3 -c "import json; print(json.load(open('$STAGE/$FOLDER/release.json'))['version'])")"
if [[ "$ver_json" != "$VERSION" ]]; then
  echo "ERROR: release.json version '$ver_json' != plugin header '$VERSION'" >&2
  exit 1
fi

token="$(php -r "\$c=include '$STAGE/$FOLDER/includes/surecart-config.php'; echo \$c['public_token'] ?? '';")"
if [[ -z "$token" ]]; then
  echo "WARNING: public_token is empty in surecart-config.php — paste pt_… before uploading to SureCart."
else
  echo "OK: public_token is set (${#token} chars)"
fi

unzip -l "$ZIP" | head -25
echo "OK Pro package checks passed"
