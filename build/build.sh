#!/usr/bin/env bash
#
# build.sh — assemble the source tree into an installable Joomla package.
#
# Output: dist/pkg_adboard_v<version>.zip
#         (plus the intermediate com_adboard.zip and plg_finder_adboard.zip
#          that Joomla's package installer expands internally)
#
# The source tree under src/ is the single source of truth. The zips are
# build artifacts and are git-ignored — never edit a zip, edit the source.
#
# Usage:
#   ./build/build.sh            # build using version from pkg_adboard.xml
#   ./build/build.sh 1.5.27     # override version on the output package name
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$REPO_ROOT/src"
DIST="$REPO_ROOT/dist"

# Version: first CLI arg, else parsed from the package manifest.
if [[ "${1:-}" != "" ]]; then
  VERSION="$1"
else
  VERSION="$(grep -oPm1 '(?<=<version>)[^<]+' "$SRC/pkg_adboard.xml")"
fi

echo "▶ Building Ad Board package v${VERSION}"
rm -rf "$DIST"
mkdir -p "$DIST"

# 1) Component zip — contents of src/com_adboard/ at the archive root.
( cd "$SRC/com_adboard" && zip -rq "$DIST/com_adboard.zip" . \
    -x '*.DS_Store' -x '__MACOSX*' )
echo "  ✓ com_adboard.zip"

# 2) Finder plugin zip.
( cd "$SRC/plg_finder_adboard" && zip -rq "$DIST/plg_finder_adboard.zip" . \
    -x '*.DS_Store' -x '__MACOSX*' )
echo "  ✓ plg_finder_adboard.zip"

# 3) Outer package zip: the two extension zips + the package manifest.
cp "$SRC/pkg_adboard.xml" "$DIST/pkg_adboard.xml"
( cd "$DIST" && zip -q "pkg_adboard_v${VERSION}.zip" \
    com_adboard.zip plg_finder_adboard.zip pkg_adboard.xml )

# Tidy the loose manifest copy (kept inside the package already).
rm -f "$DIST/pkg_adboard.xml"

echo "✓ dist/pkg_adboard_v${VERSION}.zip"
echo "  Install via  System → Install → Extensions → Upload Package File"
