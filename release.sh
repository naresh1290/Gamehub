#!/usr/bin/env bash
# Bump a package's version, commit, tag, and push — which triggers the GitHub
# Actions workflow to build the zip and publish the release.
#
# Usage:
#   ./release.sh engine 1.0.1
#   ./release.sh theme 1.2.0
#   ./release.sh analytics 1.0.1
set -euo pipefail

PKG="${1:?package: engine|theme|analytics}"
VER="${2:?version: e.g. 1.0.1}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

case "$PKG" in
  engine)
    MAIN="$ROOT/wp-content/plugins/gamehub-engine/gamehub-engine.php"
    sed -i.bak -E "s/(\* Version:[[:space:]]*)[0-9.]+/\1$VER/" "$MAIN"
    sed -i.bak -E "s/(GHUB_ENGINE_VERSION', ')[0-9.]+/\1$VER/" "$MAIN"
    PREFIX="engine-"
    ;;
  analytics)
    MAIN="$ROOT/wp-content/plugins/gamehub-analytics/gamehub-analytics.php"
    sed -i.bak -E "s/(\* Version:[[:space:]]*)[0-9.]+/\1$VER/" "$MAIN"
    sed -i.bak -E "s/(GHUB_ANALYTICS_VERSION', ')[0-9.]+/\1$VER/" "$MAIN"
    PREFIX="analytics-"
    ;;
  theme)
    CSS="$ROOT/wp-content/themes/gamehub/style.css"
    FUNC="$ROOT/wp-content/themes/gamehub/functions.php"
    sed -i.bak -E "s/(Version:[[:space:]]*)[0-9.]+/\1$VER/" "$CSS"
    sed -i.bak -E "s/(GAMEHUB_THEME_VERSION', ')[0-9.]+/\1$VER/" "$FUNC"
    PREFIX="theme-"
    ;;
  *) echo "Unknown package: $PKG (use engine|theme|analytics)"; exit 1 ;;
esac

find "$ROOT" -name '*.bak' -delete

TAG="${PREFIX}v${VER}"
git -C "$ROOT" add -A
git -C "$ROOT" commit -m "$PKG $VER"
git -C "$ROOT" tag "$TAG"
git -C "$ROOT" push origin HEAD
git -C "$ROOT" push origin "$TAG"

echo "==> Pushed $TAG. GitHub Actions will build and publish the release."
