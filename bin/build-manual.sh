#!/usr/bin/env bash
# Builds docs/manual/prirucnik.html into a PDF (WeasyPrint, fallback: headless Google Chrome).
set -euo pipefail

cd "$(dirname "$0")/.."

HTML="docs/manual/prirucnik.html"
OUT="docs/manual/Sidrena-cijena-za-WooCommerce-prirucnik.pdf"
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"

if [ ! -f "$HTML" ]; then
  echo "Missing $HTML" >&2
  exit 1
fi

if command -v weasyprint >/dev/null 2>&1; then
  echo "Building with WeasyPrint..."
  weasyprint "$HTML" "$OUT"
elif [ -x "$CHROME" ]; then
  echo "WeasyPrint not found; building with headless Google Chrome..."
  "$CHROME" --headless --disable-gpu --no-pdf-header-footer \
    --print-to-pdf="$(pwd)/$OUT" "file://$(pwd)/$HTML" 2>/dev/null
else
  echo "Neither weasyprint nor Google Chrome found. Install WeasyPrint: pip install weasyprint" >&2
  exit 1
fi

echo "PDF: $(pwd)/$OUT"
if command -v pdfinfo >/dev/null 2>&1; then
  pdfinfo "$OUT" 2>/dev/null | grep -E '^Pages:' || true
fi
