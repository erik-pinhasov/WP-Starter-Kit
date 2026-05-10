#!/bin/bash
# Build script for WP Starter Kit
# Usage: ./build.sh [all|suite|<module-name>]
set -e

VERSION="1.1.0"
ROOT="$(cd "$(dirname "$0")" && pwd)"
DIST="$ROOT/dist"

MODULES=(
	turnstile
	login-url
	media-organizer
	media-replace
	brevo-mailer
	accessibility
	security-headers
	performance
)

# ── Compile translations (.po → .mo) ────────────────────────

compile_translations() {
	echo "Compiling translations..."
	find "$ROOT" -name '*.po' | while read po; do
		mo="${po%.po}.mo"
		if [ ! -f "$mo" ] || [ "$po" -nt "$mo" ]; then
			msgfmt "$po" -o "$mo" 2>/dev/null && echo "  ✓ $(basename "$mo")" || echo "  ✗ $(basename "$po") (msgfmt not available)"
		fi
	done
}

# ── Build: Suite ─────────────────────────────────────────────

build_suite() {
	echo "→ Building suite: wp-starter-kit-${VERSION}.zip"
	local tmp="$DIST/_tmp/wp-starter-kit"
	mkdir -p "$tmp"

	cp "$ROOT/wp-starter-kit.php" "$tmp/"
	cp -r "$ROOT/core" "$tmp/"
	cp -r "$ROOT/modules" "$tmp/"
	[ -d "$ROOT/languages" ] && cp -r "$ROOT/languages" "$tmp/"
	[ -f "$ROOT/readme.txt" ] && cp "$ROOT/readme.txt" "$tmp/"
	[ -f "$ROOT/README.md" ] && cp "$ROOT/README.md" "$tmp/"

	cd "$DIST/_tmp"
	zip -rq "$DIST/wp-starter-kit-${VERSION}.zip" wp-starter-kit/
	cd "$ROOT"

	echo "  ✓ dist/wp-starter-kit-${VERSION}.zip"
}

# ── Build: Standalone module ─────────────────────────────────

build_standalone() {
	local module="$1"
	local slug="wpsk-${module}"
	local module_dir="$ROOT/modules/${module}"
	local main_file="$ROOT/standalone/${slug}/${slug}.php"

	if [ ! -f "$main_file" ]; then
		echo "  ✗ ${slug}: standalone wrapper not found, skipping."
		return
	fi
	if [ ! -d "$module_dir" ]; then
		echo "  ✗ ${slug}: module directory not found, skipping."
		return
	fi

	echo "→ Building standalone: ${slug}-${VERSION}.zip"
	local tmp="$DIST/_tmp/${slug}"
	mkdir -p "$tmp"

	cp "$main_file" "$tmp/"
	cp -r "$ROOT/core" "$tmp/"
	mkdir -p "$tmp/modules"
	cp -r "$module_dir" "$tmp/modules/"

	cd "$DIST/_tmp"
	zip -rq "$DIST/${slug}-${VERSION}.zip" "${slug}/"
	cd "$ROOT"

	echo "  ✓ dist/${slug}-${VERSION}.zip"
}

# ── Main ─────────────────────────────────────────────────────

compile_translations

target="${1:-all}"

rm -rf "$DIST"
mkdir -p "$DIST"

if [ "$target" = "all" ] || [ "$target" = "suite" ]; then
	build_suite
fi

if [ "$target" = "all" ]; then
	for module in "${MODULES[@]}"; do
		build_standalone "$module"
	done
elif [ "$target" != "suite" ] && [ "$target" != "all" ]; then
	build_standalone "$target"
fi

rm -rf "$DIST/_tmp"

echo ""
echo "Done. Output in dist/:"
ls -lh "$DIST"/*.zip 2>/dev/null || echo "  (no zip files produced)"
