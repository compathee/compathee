#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
DEPLOY="$ROOT/deploy"

echo "Syncing src/ and config.example.php into deploy/..."
rm -rf "$DEPLOY/src"
cp -R "$ROOT/src" "$DEPLOY/src"
cp "$ROOT/config/config.example.php" "$DEPLOY/config/config.example.php"

mkdir -p "$DEPLOY/data"
touch "$DEPLOY/data/.gitkeep"

ZIP="$ROOT/api-compath-ee-deploy.zip"
rm -f "$ZIP"
(cd "$DEPLOY" && zip -qr "$ZIP" .)

echo "Done."
echo "  FTP folder: $DEPLOY"
echo "  Zip archive: $ZIP"
echo "  Files: $(find "$DEPLOY" -type f | wc -l)"
