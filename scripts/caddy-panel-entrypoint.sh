#!/bin/sh
# FrankenPHP / Docker entrypoint example — verify the configuration before start
# and automatically roll back to the last known good config when broken.
#
# Usage (Docker):
#   ENTRYPOINT ["/srv/caddy-panel/scripts/caddy-panel-entrypoint.sh"]
#   CMD ["frankenphp", "run", "--config", "/srv/caddy-panel/etc/Caddyfile", "--adapter", "caddyfile"]
set -e

: "${CADDY_PANEL_PHP:=php}"
: "${CADDY_PANEL_APP_ROOT:=$(pwd)}"

if ! "$CADDY_PANEL_PHP" "$CADDY_PANEL_APP_ROOT/bin/panel" doctor --fix >/dev/null 2>&1; then
  echo "[caddy-panel] doctor failed after automatic restore; refusing to start." >&2
  "$CADDY_PANEL_PHP" "$CADDY_PANEL_APP_ROOT/bin/panel" restore --list >&2 || true
  exit 1
fi

exec "$@"
