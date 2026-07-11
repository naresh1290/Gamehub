#!/usr/bin/env bash
# Install the GameHub icon reverse-proxy for a Webinoly WordPress site.
#
# A WordPress plugin cannot write nginx config, so this one server-side step
# drops the /img/ proxy snippet into the site directory (which Webinoly already
# auto-includes via `include /var/www/<domain>/*-nginx.conf;`) and reloads nginx.
#
# Run once per site, as root, on the server:
#   ./install-img-proxy.sh poki.uk.com
#
# Pair it with the engine's "Icon image proxy" settings (on by default):
#   CDN host = img.poki-cdn.com   Local path = img
set -euo pipefail

DOMAIN="${1:-}"
if [ -z "$DOMAIN" ]; then
	echo "Usage: $0 <domain>   (e.g. $0 poki.uk.com)" >&2
	exit 1
fi

WEBROOT="/var/www/$DOMAIN"
if [ ! -d "$WEBROOT" ]; then
	echo "[ERROR] Site not found: $WEBROOT" >&2
	exit 1
fi

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/nginx/img-proxy.conf"
DEST="$WEBROOT/img-nginx.conf"

install -o www-data -g www-data -m 0644 "$SRC" "$DEST"
echo "==> Installed $DEST"

if nginx -t; then
	systemctl reload nginx
	echo "==> nginx reloaded"
else
	echo "[ERROR] nginx config test failed — not reloading. Fix and rerun." >&2
	exit 1
fi

echo "==> Done. Icons on https://$DOMAIN/img/... now proxy to img.poki-cdn.com"
