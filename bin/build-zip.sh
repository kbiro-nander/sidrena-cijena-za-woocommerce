#!/usr/bin/env bash
# Builds dist/sidrena-cijena-za-woocommerce.zip with production vendor/ (no dev deps).
set -euo pipefail
cd "$(dirname "$0")/.."
SLUG=sidrena-cijena-za-woocommerce
VERSION=$(grep -m1 'Version:' "$SLUG.php" | sed 's/.*Version: *//')
BUILD=build/$SLUG
rm -rf build dist && mkdir -p "$BUILD" dist
rsync -a --exclude-from=- ./ "$BUILD/" <<'EXCLUDE'
.git
.gitignore
.idea
.DS_Store
.phpunit.result.cache
build
dist
docs
tests
bin
node_modules
vendor
composer.lock
phpunit.xml.dist
phpstan.neon.dist
phpstan-bootstrap.php
phpcs.xml.dist
EXCLUDE
( cd "$BUILD" && composer install --no-dev --no-interaction --classmap-authoritative --quiet && rm -f composer.json composer.lock )
if command -v msgfmt >/dev/null; then
  for po in "$BUILD"/languages/*.po; do [ -f "$po" ] && msgfmt -o "${po%.po}.mo" "$po"; done
fi
# Owner's manual (PDF) ships inside the plugin folder.
for pdf in docs/manual/*.pdf; do [ -f "$pdf" ] && cp "$pdf" "$BUILD/prirucnik.pdf"; done
( cd build && zip -qr "../dist/$SLUG-$VERSION.zip" "$SLUG" )
echo "Built dist/$SLUG-$VERSION.zip"
