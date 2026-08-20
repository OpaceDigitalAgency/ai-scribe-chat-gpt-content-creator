#!/usr/bin/env bash
#
# AI-Scribe v3 → wordpress.org SVN deploy preparation.
#
# Copies the packaged release into the local SVN checkout (snailsvn/):
#   trunk/       ← replaced with the 3.0.0 payload
#   tags/3.0.0/  ← fresh copy of the same payload
#
# DRY-RUN BY DEFAULT: prints every action without touching the checkout.
# Run with --apply to perform the copy. This script NEVER runs `svn commit`
# — after --apply, review the working copy and commit manually (see
# docs/RELEASE_RUNBOOK.md). Rotate the SVN password BEFORE any commit.
#
# Usage:
#   scripts/deploy-svn.sh            # dry run (default)
#   scripts/deploy-svn.sh --apply    # copy files into the SVN checkout

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SVN_ROOT="$(cd "$REPO_ROOT/../snailsvn" && pwd)"
SLUG="ai-scribe-the-chatgpt-powered-seo-content-creation-wizard"

VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$REPO_ROOT/article_builder.php" | head -1 | tr -d '[:space:]')"
ZIP="$REPO_ROOT/dist/ai-scribe-$VERSION.zip"

MODE="dry-run"
if [[ "${1:-}" == "--apply" ]]; then
	MODE="apply"
fi

echo "== AI-Scribe SVN deploy prep ($MODE) =="
echo "   version : $VERSION"
echo "   package : $ZIP"
echo "   checkout: $SVN_ROOT"
echo

if [[ ! -f "$ZIP" ]]; then
	echo "ERROR: package not found — run scripts/build-release.sh first." >&2
	exit 1
fi
if [[ ! -d "$SVN_ROOT/trunk" ]]; then
	echo "ERROR: SVN checkout not found at $SVN_ROOT" >&2
	exit 1
fi
if [[ -d "$SVN_ROOT/tags/$VERSION" ]]; then
	echo "ERROR: tags/$VERSION already exists — refusing to overwrite a released tag." >&2
	exit 1
fi

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
unzip -q "$ZIP" -d "$STAGE"
PAYLOAD="$STAGE/$SLUG"
if [[ ! -f "$PAYLOAD/article_builder.php" ]]; then
	echo "ERROR: unexpected zip layout (no $SLUG/article_builder.php)." >&2
	exit 1
fi

run() {
	if [[ "$MODE" == "apply" ]]; then
		echo "  + $*"
		"$@"
	else
		echo "  (dry-run) $*"
	fi
}

echo "-- 1. Replace trunk/ payload (preserving .svn metadata)"
# Delete tracked files, keep .svn.
while IFS= read -r entry; do
	run rm -rf "$SVN_ROOT/trunk/$entry"
done < <(ls "$SVN_ROOT/trunk")
run cp -R "$PAYLOAD/." "$SVN_ROOT/trunk/"

echo "-- 2. Create tags/$VERSION"
run mkdir -p "$SVN_ROOT/tags/$VERSION"
run cp -R "$PAYLOAD/." "$SVN_ROOT/tags/$VERSION/"

echo
echo "-- 3. NOT DONE BY THIS SCRIPT (manual, see docs/RELEASE_RUNBOOK.md):"
cat <<'EOT'
   * svn add/rm the changed paths (svn status shows ? and ! entries)
   * Update assets/ (wp.org banner + screenshots) in the SVN /assets dir
   * svn commit -m "AI-Scribe 3.0.0" (AFTER rotating the SVN password)
EOT

if [[ "$MODE" == "dry-run" ]]; then
	echo
	echo "Dry run complete — nothing was changed. Re-run with --apply to copy."
fi
