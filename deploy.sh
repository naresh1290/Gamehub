#!/usr/bin/env bash
# Deploy the GameHub suite to a WordPress server over SSH via rsync.
#
# Usage:
#   GHUB_SSH="root@139.59.76.255" \
#   GHUB_WPROOT="/var/www/poki.com.im/htdocs" \
#   ./deploy.sh [--activate] [--nginx]
#
# --activate  activate plugins+theme and flush rewrites via WP-CLI
# --nginx     install the icon-proxy nginx snippet and reload nginx
#
# Requires SSH access (key or agent). Set GHUB_SSHPASS to use a password via
# sshpass (less secure; prefer keys). Add --activate to activate via WP-CLI.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WPC="$ROOT/wp-content"

SSH_TARGET="${GHUB_SSH:?Set GHUB_SSH, e.g. root@1.2.3.4}"
WPROOT="${GHUB_WPROOT:?Set GHUB_WPROOT, e.g. /var/www/poki.com.im/htdocs}"

SSH_CMD=(ssh -o StrictHostKeyChecking=accept-new)
RSYNC_RSH="ssh -o StrictHostKeyChecking=accept-new"
if [[ -n "${GHUB_SSHPASS:-}" ]]; then
	SSH_CMD=(sshpass -p "$GHUB_SSHPASS" ssh -o StrictHostKeyChecking=accept-new)
	RSYNC_RSH="sshpass -p $GHUB_SSHPASS ssh -o StrictHostKeyChecking=accept-new"
fi

echo "==> Syncing theme + plugins to $SSH_TARGET:$WPROOT"
rsync -az --delete -e "$RSYNC_RSH" \
	--exclude '.DS_Store' \
	"$WPC/themes/gamehub" \
	"$SSH_TARGET:$WPROOT/wp-content/themes/"

for plugin in gamehub-engine gamehub-analytics; do
	rsync -az --delete -e "$RSYNC_RSH" \
		--exclude '.DS_Store' \
		"$WPC/plugins/$plugin" \
		"$SSH_TARGET:$WPROOT/wp-content/plugins/"
done

echo "==> Fixing ownership (www-data)"
"${SSH_CMD[@]}" "$SSH_TARGET" "chown -R www-data:www-data $WPROOT/wp-content/themes/gamehub $WPROOT/wp-content/plugins/gamehub-engine $WPROOT/wp-content/plugins/gamehub-analytics"

if [[ "${*:-}" == *"--nginx"* ]]; then
	SITE_DIR="$(dirname "$WPROOT")"
	echo "==> Installing icon-proxy nginx snippet to $SITE_DIR/img-nginx.conf"
	rsync -az -e "$RSYNC_RSH" "$ROOT/deploy/nginx/img-proxy.conf" "$SSH_TARGET:$SITE_DIR/img-nginx.conf"
	"${SSH_CMD[@]}" "$SSH_TARGET" "nginx -t && systemctl reload nginx && echo 'nginx reloaded'"
fi

if [[ "${*:-}" == *"--activate"* ]]; then
	echo "==> Activating via WP-CLI"
	"${SSH_CMD[@]}" "$SSH_TARGET" "cd $WPROOT && sudo -u www-data wp plugin activate gamehub-engine gamehub-analytics && sudo -u www-data wp theme activate gamehub && sudo -u www-data wp rewrite flush"
fi

echo "==> Deploy complete."
