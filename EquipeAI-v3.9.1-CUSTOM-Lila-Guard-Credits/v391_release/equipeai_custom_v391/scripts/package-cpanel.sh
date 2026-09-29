#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/frontend"
npm run build
OUT="$ROOT/.release"
rm -rf "$OUT" && mkdir -p "$OUT/public_html/api" "$OUT/equipeai-backend"
cp -R dist/. "$OUT/public_html/"
cp "$ROOT/deploy/cpanel/public_html/.htaccess" "$OUT/public_html/.htaccess"
cp "$ROOT/deploy/cpanel/public_html/api/index.php" "$OUT/public_html/api/index.php"
cp -R "$ROOT/backend/." "$OUT/equipeai-backend/"
cp -R "$ROOT/database" "$OUT/database"
cd "$OUT"
zip -qr "$ROOT/equipeai-cpanel-release.zip" .
echo "$ROOT/equipeai-cpanel-release.zip"
