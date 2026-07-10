# GameHub

A reusable, white-label WordPress games-portal suite: one theme + two plugins that share one brand and update independently.

| Package | Slug | Role |
|---|---|---|
| **GameHub** (theme) | `gamehub` | Presentation: homepage grid, category pages, single-game player, HTML sitemap. |
| **GameHub Engine** (plugin) | `gamehub-engine` | Data layer: `game` post type + `game_category` taxonomy, remote-JSON import, metrics tables, REST API, settings. |
| **GameHub Analytics** (plugin) | `gamehub-analytics` | Dashboards: date ranges, charts, ranked lists, CSV export. |

The **Engine** owns all data and survives theme switches. The theme and analytics plugin depend on it and declare a minimum version, so you can update any one package without the others.

## Repo layout

This is a **monorepo** — all three packages live here and are published as separate releases distinguished by tag prefix (`engine-*`, `theme-*`, `analytics-*`).

```
wp-content/themes/gamehub/            # theme
wp-content/plugins/gamehub-engine/    # engine plugin
wp-content/plugins/gamehub-analytics/ # analytics plugin
deploy/nginx/img-proxy.conf           # icon reverse-proxy snippet
.github/workflows/release.yml         # tag -> build zip -> publish release
build.sh  deploy.sh  release.sh
```

## Install order

1. Install & activate **GameHub Engine**.
2. Install & activate **GameHub Analytics** (optional but recommended).
3. Install & activate the **GameHub** theme. If the engine is missing, the theme shows a one-click activation prompt.
4. Go to **Games → Settings**, set the **JSON URL**, and click **Fetch & sync games now**.
5. Visit **Settings → Permalinks** once (or it auto-flushes) so game/category URLs resolve.
6. (Optional) Create a Page with slug `sitemap` using the **GameHub Sitemap** template.

## Data model

- Each game is a `game` post — title becomes the slug/permalink `/g/{name}/` automatically (base configurable).
- Categories are `game_category` terms → `/c/{name}/` pages are created and populated automatically (base configurable).
- Game fields live in post meta: `_ghub_iframe_url`, `_ghub_icon_url`, `_ghub_source_id`.
- Metrics live in custom tables keyed by post ID, so imports never disturb them:
  - `{prefix}gh_stats` — lifetime plays, visits, likes, dislikes, rating, session time.
  - `{prefix}gh_daily` — per-game-per-day rollups (powers analytics calendars/ranges).
  - `{prefix}gh_votes` — one row per (game, visitor) for like/dislike/rating dedupe.

## JSON feed format

An array of games, or an object with a `games` array. Fields are matched flexibly:

```json
[
  {
    "game_id": "subway-surfers",
    "gamename": "Subway Surfers",
    "iframeurl": "https://example.com/play/subway-surfers",
    "icon": "https://img.poki-cdn.com/subway.png",
    "category": "Action"
  }
]
```

- Name aliases: `gamename` / `name` / `title`
- URL aliases: `iframeurl` / `gameurl` / `game_url` / `url` / `embed`
- Icon aliases: `icon` / `iconurl` / `image` / `thumbnail`
- Category aliases: `category` / `categories` / `genre` / `type` (string, comma/pipe separated, or array)
- `game_id` (optional) is the stable key used to match on re-sync; without it, matching falls back to iframe URL then slug.

Re-syncs update existing games in place. Enable **"Draft games missing from the feed"** to auto-hide removed games.

## REST API (`gamehub/v1`)

`GET /games`, `GET /games/{id}`, and `POST /games/{id}/{play|visit|like|dislike|rate|session}`. The theme's front-end JS calls these for plays, page visits, like/dislike, star ratings, and play-session durations (via `navigator.sendBeacon`).

## Icon image proxy (optional)

Serve game icons from your own domain instead of hotlinking the CDN:

1. **Server**: deploy [`deploy/nginx/img-proxy.conf`](deploy/nginx/img-proxy.conf) to `/var/www/<domain>/img-nginx.conf` (Webinoly auto-includes it), then `nginx -t && systemctl reload nginx`. `./deploy.sh --nginx` does this for you.
2. **WordPress**: **Games → Settings → Icon image proxy** → enable, set CDN host (`img.poki-cdn.com`) and local path (`img`).

Then `https://img.poki-cdn.com/a/b.png` renders as `https://<your-domain>/img/a/b.png`. The original CDN URL stays in the DB, so toggling the proxy needs no re-import. If you change the host/path in settings, update the nginx file to match.

## Updates (GitHub releases, automated)

Each package self-updates from this repo's releases, matched by tag prefix, so they version independently. The repo paths are pre-configured (`naresh1290/Gamehub`) and editable under **Games → Settings → Updates**.

**Cut a release** — one command bumps the version, commits, tags, and pushes; GitHub Actions then builds the zip and publishes the release:

```bash
./release.sh engine 1.0.1      # -> tag engine-v1.0.1  -> gamehub-engine.zip
./release.sh theme 1.2.0       # -> tag theme-v1.2.0   -> gamehub.zip
./release.sh analytics 1.0.1   # -> tag analytics-v1.0.1 -> gamehub-analytics.zip
```

WordPress then shows the update on its normal Updates screen; the "Check for updates" link force-refreshes. Public repo → sites need no token.

## Build & deploy

```bash
# Build release zips locally into dist/ (CI does this automatically on tag)
./build.sh

# Deploy working copies to a server (activate + install nginx proxy)
GHUB_SSH="root@1.2.3.4" GHUB_WPROOT="/var/www/poki.com.im/htdocs" ./deploy.sh --activate --nginx
```

## Versioning

Bump only the package you changed. Keep the Engine's public functions/hooks backward-compatible; raise a dependent's minimum-engine requirement only when it truly needs a newer Engine.
