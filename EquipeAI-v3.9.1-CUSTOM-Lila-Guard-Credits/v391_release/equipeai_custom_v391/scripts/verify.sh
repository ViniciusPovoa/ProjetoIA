#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
echo "[1/3] PHP lint"
find "$ROOT/backend" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
echo "OK"
echo "[2/3] Frontend dependencies"
cd "$ROOT/frontend"
if [ ! -d node_modules ]; then echo "node_modules ausente. Rode: npm install"; exit 2; fi
echo "[3/3] Frontend production build"
npm run build
echo "Projeto validado."
