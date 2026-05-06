#!/usr/bin/env bash
#
# build.sh — Package WP Starter Kit into distributable zip files.
#
# Usage:
#   ./build.sh              Build everything (suite + all standalone plugins)
#   ./build.sh suite        Build the suite only
#   ./build.sh turnstile    Build a specific standalone plugin
#
# Output goes to ./dist/
#

set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
DIST="$ROOT/dist"
VERSION="1.0.0"

# All known modules (add new ones here)
MODULES=(
	turnstile
	brevo-mailer
	security-headers
	performance
	login-url
	media-organizer
	media-replace
	accessibility
)

rm -rf "$DIST"
mkdir -p "$DIST"

# ── Compile .po → .mo ───────────────────────────────────────

compile_translations() {
	echo "→ Compiling translations..."
	find "$ROOT" -name '*.po' | while read -r po; do
		mo="${po%.po}.mo"
		if [ ! -f "$mo" ] || [ "$po" -nt "$mo" ]; then
			msgfmt "$po" -o "$mo" 2>/dev/null && echo "  ✓ $(basename "$mo")" || echo "  ✗ $(basename "$po") (msgfmt failed or not installed)"
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
	cp -r "$ROOT/languages" "$tmp/"
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
		echo "  ✗ ${slug}: standalone wrapper not found at ${main_file}, skipping."
		return
	fi

	if [ ! -d "$module_dir" ]; then
		echo "  ✗ ${slug}: module directory not found at ${module_dir}, skipping."
		return
	fi

	echo "→ Building standalone: ${slug}-${VERSION}.zip"
	local tmp="$DIST/_tmp/${slug}"
	mkdir -p "$tmp"

	# Copy the standalone main file.
	cp "$main_file" "$tmp/"

	# Copy the shared core.
	cp -r "$ROOT/core" "$tmp/"

	# Copy only this module.
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

if [ "$target" = "all" ] || [ "$target" = "suite" ]; then
	build_suite
fi

if [ "$target" = "all" ]; then
	for module in "${MODULES[@]}"; do
		[[ "$module" =~ ^# ]] && continue  # skip commented-out modules
		build_standalone "$module"
	done
elif [ "$target" != "suite" ]; then
	build_standalone "$target"
fi

# Cleanup temp directory
rm -rf "$DIST/_tmp"

echo ""
echo "Done. Output in dist/:"
ls -lh "$DIST"/*.zip 2>/dev/null || echo "  (no zip files produced)"
