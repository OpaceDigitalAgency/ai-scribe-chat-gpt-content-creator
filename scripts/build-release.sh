#!/usr/bin/env bash
#
# AI-Scribe v3 release packager.
#
# Builds dist/ai-scribe-<version>.zip whose top-level directory is the
# wordpress.org slug (ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/),
# containing ONLY runtime files — no tests, tooling, or dev configuration.
#
# Usage:  scripts/build-release.sh
# Output: dist/ai-scribe-<version>.zip  (version read from article_builder.php)

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="ai-scribe-the-chatgpt-powered-seo-content-creation-wizard"

VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$REPO_ROOT/article_builder.php" | head -1 | tr -d '[:space:]')"
if [[ -z "$VERSION" ]]; then
	echo "ERROR: could not read Version from article_builder.php" >&2
	exit 1
fi

STABLE_TAG="$(sed -n 's/^Stable tag:[[:space:]]*//p' "$REPO_ROOT/readme.txt" | head -1 | tr -d '[:space:]')"
if [[ "$STABLE_TAG" != "$VERSION" ]]; then
	echo "ERROR: readme.txt stable tag ($STABLE_TAG) != plugin version ($VERSION)" >&2
	exit 1
fi

DIST="$REPO_ROOT/dist"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
PKG="$STAGE/$SLUG"
mkdir -p "$PKG" "$DIST"

# Runtime payload only. Anything not listed here does not ship.
RUNTIME_PATHS=(
	article_builder.php
	uninstall.php
	readme.txt
	includes
	templates
	assets
)

for path in "${RUNTIME_PATHS[@]}"; do
	if [[ ! -e "$REPO_ROOT/$path" ]]; then
		echo "ERROR: expected runtime path missing: $path" >&2
		exit 1
	fi
	cp -R "$REPO_ROOT/$path" "$PKG/"
done

# Strip dev artefacts that live inside runtime directories.
rm -rf "$PKG/assets/ENQUEUE_MANIFEST.md"
find "$PKG" -name '.DS_Store' -delete
find "$PKG" -name '*.map' -delete

# Normalise archive metadata so the same source produces the same package on
# every build. ZIP stores timestamps and Unix permissions; leaving either to
# the local checkout made a rebuild of unchanged source produce a new hash.
find "$PKG" -type d -exec chmod 0755 {} +
find "$PKG" -type f -exec chmod 0644 {} +
find "$PKG" -exec touch -t 198001010000 {} +

# Belt-and-braces: fail the build if any excluded artefact slipped in.
for banned in tests node_modules playwright.config.ts scripts design-reference docs mu-plugins .wp-env.json .git package.json package-lock.json; do
	if [[ -e "$PKG/$banned" ]]; then
		echo "ERROR: dev artefact leaked into package: $banned" >&2
		exit 1
	fi
done

ZIP="$DIST/ai-scribe-$VERSION.zip"
rm -f "$ZIP"
(
	cd "$STAGE"
	find "$SLUG" -print | LC_ALL=C sort | zip -X -q "$ZIP" -@
)

FILE_COUNT="$(unzip -l "$ZIP" | tail -1 | awk '{print $2}')"
SIZE="$(du -h "$ZIP" | awk '{print $1}')"
echo "Built $ZIP ($SIZE, $FILE_COUNT entries, top dir $SLUG/)"
