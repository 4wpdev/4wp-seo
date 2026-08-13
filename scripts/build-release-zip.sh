#!/usr/bin/env bash
# Build WordPress.org deploy ZIP (respects .distignore patterns).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(grep -m1 '^ \* Version:' "$ROOT/4wp-seo-helper.php" | sed 's/.*Version:[[:space:]]*//')"
SLUG="4wp-seo-helper"
BUILD="/tmp/${SLUG}-${VERSION}-build"
OUT_DIR="$ROOT/.releases"
ZIP="$OUT_DIR/${SLUG}-${VERSION}.zip"

rm -rf "$BUILD"
mkdir -p "$BUILD/$SLUG" "$OUT_DIR"

rsync -a \
	--exclude='.git/' \
	--exclude='.gitignore' \
	--exclude='.gitattributes' \
	--exclude='README.md' \
	--exclude='docs/' \
	--exclude='scripts/' \
	--exclude='.editorconfig' \
	--exclude='.phpcs.xml' \
	--exclude='.phpcs.xml.dist' \
	--exclude='phpcs.xml' \
	--exclude='.phpcs.cache' \
	--exclude='.distignore' \
	--exclude='.releases/' \
	"$ROOT/" "$BUILD/$SLUG/"

(cd "$BUILD" && rm -f "$ZIP" && zip -rq "$ZIP" "$SLUG")

echo "Built: $ZIP ($(du -h "$ZIP" | awk '{print $1}'))"
