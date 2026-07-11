#!/usr/bin/env bash
# One-shot provisioning for a fresh GameHub site on a Webinoly server that sits
# behind Cloudflare (edge SSL). Run once, as root, after `site <domain> -wp`
# and after the WordPress install wizard:
#
#   ./provision-site.sh poki.uk.com
#
# It fixes the two things a plugin/theme upload can NOT do by itself, which is
# what breaks a fresh site until they are done:
#
#   1. HTTPS-behind-Cloudflare  — WordPress ships as http:// but the site is
#      served over https://, so every REST/admin-ajax/beacon call is blocked as
#      mixed content (likes, analytics, AI generator all silently fail). We add
#      the Cloudflare HTTPS-detection snippet to wp-config.php and switch
#      siteurl/home to https.
#
#   2. Icon reverse proxy — installs the nginx /img/ location and reloads nginx.
#
# The alternative permanent fix for #1 is a real origin certificate:
#   sudo site <domain> -ssl=on         (Let's Encrypt; set Cloudflare SSL=Full)
# With a real cert you can skip the wp-config hack entirely.
set -euo pipefail

DOMAIN="${1:-}"
if [ -z "$DOMAIN" ]; then
	echo "Usage: $0 <domain>   (e.g. $0 poki.uk.com)" >&2
	exit 1
fi

WEBROOT="/var/www/$DOMAIN"
HTDOCS="$WEBROOT/htdocs"
CFG="$WEBROOT/wp-config.php"
[ -d "$HTDOCS" ] || { echo "[ERROR] Site not found: $HTDOCS" >&2; exit 1; }
[ -f "$CFG" ]   || CFG="$HTDOCS/wp-config.php"
[ -f "$CFG" ]   || { echo "[ERROR] wp-config.php not found for $DOMAIN" >&2; exit 1; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# --- 1a. Cloudflare / reverse-proxy HTTPS detection in wp-config -------------
if grep -q "HTTP_X_FORWARDED_PROTO" "$CFG"; then
	echo "==> HTTPS detection already in wp-config, skipping"
else
	cp "$CFG" "$CFG.bak.$(date +%s 2>/dev/null || echo bak)" 2>/dev/null || cp "$CFG" "$CFG.bak"
	python3 - "$CFG" <<'PY'
import sys
f = sys.argv[1]
s = open(f).read()
snip = ('/* Cloudflare / reverse-proxy HTTPS detection */\n'
        'if ( ( isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) && $_SERVER["HTTP_X_FORWARDED_PROTO"] === "https" )\n'
        '  || ( isset($_SERVER["HTTP_CF_VISITOR"]) && strpos($_SERVER["HTTP_CF_VISITOR"], "https") !== false ) ) {\n'
        '    $_SERVER["HTTPS"] = "on";\n'
        '}\n\n')
anchor = "/* That's all, stop editing"
i = s.find(anchor)
if i == -1:
    i = s.find("require_once ABSPATH")
if i == -1:
    sys.exit("could not find an insertion point in wp-config.php")
open(f, "w").write(s[:i] + snip + s[i:])
PY
	php -l "$CFG" >/dev/null && echo "==> Added HTTPS detection to wp-config"
fi

# --- 1b. Force siteurl/home to https ----------------------------------------
cd "$HTDOCS"
CUR="$(sudo -u www-data wp option get siteurl --allow-root 2>/dev/null || echo '')"
sudo -u www-data wp option update siteurl "https://$DOMAIN" --allow-root >/dev/null
sudo -u www-data wp option update home    "https://$DOMAIN" --allow-root >/dev/null
echo "==> siteurl/home set to https://$DOMAIN (was: ${CUR:-unknown})"

# --- 2. Icon reverse proxy ---------------------------------------------------
install -o www-data -g www-data -m 0644 "$SCRIPT_DIR/nginx/img-proxy.conf" "$WEBROOT/img-nginx.conf"
echo "==> Installed $WEBROOT/img-nginx.conf"

if nginx -t; then
	systemctl reload nginx
	echo "==> nginx reloaded"
else
	echo "[ERROR] nginx config test failed — fix and rerun." >&2
	exit 1
fi

# --- Flush caches ------------------------------------------------------------
webinoly -clear-cache >/dev/null 2>&1 || true
echo "==> Cache cleared"
echo "==> Done. $DOMAIN is provisioned (HTTPS + icon proxy). Verify likes/analytics/AI."
