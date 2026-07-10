#!/usr/bin/env bash
# Build distributable zips for the GameHub suite.
#
# Each zip's top-level folder matches the plugin/theme slug, which is what
# WordPress expects on install and what the GitHub-release self-updater
# downloads (attach these zips as release assets).
#
#   dist/gamehub.zip            -> themes/gamehub/
#   dist/gamehub-engine.zip     -> plugins/gamehub-engine/
#   dist/gamehub-analytics.zip  -> plugins/gamehub-analytics/
#
# Usage: ./build.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WPC="$ROOT/wp-content"
DIST="$ROOT/dist"
rm -rf "$DIST"
mkdir -p "$DIST"

ZIP_EXCLUDES=( -x '*.DS_Store' -x '*/.git/*' -x '*.map' )

echo "==> Building theme: gamehub"
( cd "$WPC/themes" && zip -rq "$DIST/gamehub.zip" gamehub "${ZIP_EXCLUDES[@]}" )

echo "==> Building plugin: gamehub-engine"
( cd "$WPC/plugins" && zip -rq "$DIST/gamehub-engine.zip" gamehub-engine "${ZIP_EXCLUDES[@]}" )

echo "==> Building plugin: gamehub-analytics"
( cd "$WPC/plugins" && zip -rq "$DIST/gamehub-analytics.zip" gamehub-analytics "${ZIP_EXCLUDES[@]}" )

echo "==> Done. Artifacts:"
ls -lh "$DIST"
