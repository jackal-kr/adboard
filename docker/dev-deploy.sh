#!/usr/bin/env bash
#
# dev-deploy.sh — push the current source tree into a running Joomla dev
# container, laid out exactly the way the Joomla installer would place it.
#
# WHY a deploy script instead of bind mounts?
# A package installs three component targets (administrator/, site/, media/)
# plus a plugin target — and the manifest (adboard.xml) + script.php live at
# the component ROOT in source but inside administrator/components/com_adboard
# after install. A single bind mount can't reproduce that split cleanly, and a
# mount that omits the manifest breaks Joomla's update/uninstall. Staging the
# real layout and copying it in is exact, fast (<1s), and works with ANY
# existing docker-compose.
#
# IMPORTANT — run the FIRST install from the package zip, not this script:
#   1. ./build/build.sh
#   2. Joomla admin → System → Install → Upload dist/pkg_adboard_v*.zip
# That first install runs script.php (creates #__adboard, seeds categories &
# expiry terms, sets Manager ACL, registers action-log config). This script
# only syncs FILES for fast iteration afterwards. Re-run the zip install
# whenever you change SQL, the manifest, config.xml, or script.php.
#
# Config (override via env or a .env next to your compose):
#   JOOMLA_CONTAINER  name of the running Joomla container (default: joomla)
#   JOOMLA_ROOT       web root inside the container (default: /var/www/html)
#   WEB_USER          owner Joomla runs as        (default: www-data)
#
set -euo pipefail

JOOMLA_CONTAINER="${JOOMLA_CONTAINER:-joomla}"
JOOMLA_ROOT="${JOOMLA_ROOT:-/var/www/html}"
WEB_USER="${WEB_USER:-www-data}"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$REPO_ROOT/src/com_adboard"
PLG="$REPO_ROOT/src/plg_finder_adboard"

if ! docker ps --format '{{.Names}}' | grep -qx "$JOOMLA_CONTAINER"; then
  echo "✗ Container '$JOOMLA_CONTAINER' is not running."
  echo "  Set JOOMLA_CONTAINER=<name> (see: docker ps)."
  exit 1
fi

# Stage the exact install layout in a temp dir.
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

ADMIN_DST="$STAGE/administrator/components/com_adboard"
SITE_DST="$STAGE/components/com_adboard"
MEDIA_DST="$STAGE/media/com_adboard"
PLG_DST="$STAGE/plugins/finder/adboard"
mkdir -p "$ADMIN_DST" "$SITE_DST" "$MEDIA_DST" "$PLG_DST"

# admin/* + the component-root manifest & install script go to the admin target
cp -r "$SRC/admin/." "$ADMIN_DST/"
cp "$SRC/adboard.xml" "$ADMIN_DST/"
cp "$SRC/script.php"  "$ADMIN_DST/"
# site/* → site component target
cp -r "$SRC/site/." "$SITE_DST/"
# media/adboard/* → media/com_adboard  (manifest destination is com_adboard)
cp -r "$SRC/media/adboard/." "$MEDIA_DST/"
# finder plugin
cp -r "$PLG/." "$PLG_DST/"

echo "▶ Deploying to $JOOMLA_CONTAINER:$JOOMLA_ROOT"
# Copy each tree into the container root. `docker cp` merges into existing dirs.
docker cp "$STAGE/administrator" "$JOOMLA_CONTAINER:$JOOMLA_ROOT/"
docker cp "$STAGE/components"    "$JOOMLA_CONTAINER:$JOOMLA_ROOT/"
docker cp "$STAGE/media"         "$JOOMLA_CONTAINER:$JOOMLA_ROOT/"
docker cp "$STAGE/plugins"       "$JOOMLA_CONTAINER:$JOOMLA_ROOT/"

# Restore ownership so Joomla can read/write (esp. the media/com_adboard/ads dir).
docker exec "$JOOMLA_CONTAINER" chown -R "$WEB_USER":"$WEB_USER" \
  "$JOOMLA_ROOT/administrator/components/com_adboard" \
  "$JOOMLA_ROOT/components/com_adboard" \
  "$JOOMLA_ROOT/media/com_adboard" \
  "$JOOMLA_ROOT/plugins/finder/adboard" 2>/dev/null || true

echo "✓ Synced. Reload the page — PHP/tmpl/CSS/JS changes are live."
echo "  (SQL / manifest / config.xml / script.php changes still need a zip reinstall.)"
