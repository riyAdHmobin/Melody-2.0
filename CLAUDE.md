# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Melody is a self-contained YouTube playlist manager and music player.  
It runs as a **standalone PHP + MySQL application** — no WordPress, no external backend.

## Running the project

### Docker (recommended)

```bash
cp .env.example .env          # fill in passwords if needed
docker compose up --build     # builds the PHP/Apache image and starts MySQL
```

Player → http://localhost:8080  
Admin  → http://localhost:8080/admin.php  (default: admin / melody)

Tables are created automatically on first DB connection — no setup step needed.

### Electron desktop app (Ubuntu/Debian)

```bash
bash install.sh   # first time: installs deps, copies to /opt/melody, prompts for DB + admin creds
melody            # launch after install
bash update.sh    # pull latest and rsync to /opt/melody
bash uninstall.sh # remove
```

Dev mode (no install): `electron electron/` from the project root (requires `~/.config/melody/.env` with DB creds).

The installer writes DB credentials and the admin password hash to `~/.config/melody/.env` (mode 600). Electron reads this file at startup and injects the vars into the PHP process environment. If `DB_HOST` is missing from that file, Electron shows an error and quits.

### Local PHP + existing MySQL

```bash
# Edit config.php (or set env vars) with your DB credentials
php -S localhost:8080
```

### No tests or linting

There is no test suite and no linter configured. Validate changes manually via Docker or the local PHP server.

## Architecture

```
index.php          — Player UI (HTML + inline SVG, loads assets/js/*)
admin.php          — Password-protected playlist/track CRUD (self-contained PHP + inline CSS)
config.php         — DB credentials + admin hash (reads env vars, falls back to hardcoded defaults)
.env               — Local environment overrides (gitignored)
.htaccess          — Blocks direct HTTP access to config.php; sets DirectoryIndex

includes/
  db.php           — PDO singleton; runs CREATE TABLE IF NOT EXISTS on every first connection
  auth.php         — Session-based login (melody_session_start, melody_login, melody_logout)
  youtube.php      — melody_extract_youtube_id(), melody_get_youtube_title() via oEmbed,
                     melody_slugify(), melody_is_youtube_short() (HEAD request to /shorts/{id},
                     200 = Short); melody_fetch_latest_channel_video() (RSS feed, skips Shorts);
                     melody_sync_playlist_channel() (insert new video, update last_seen_video_id,
                     silent error logging)
  .htaccess        — Blocks direct HTTP access to PHP include files

api/
  playlists.php    — GET → all playlists, each with full track list embedded in "demo" field
  playlist.php     — GET ?slug=xxx → track array for one playlist
  favorites.php    — GET → [ytId, ...]; POST {id} → toggle favorite (add/remove), returns {action, id}
  order.php        — POST {playlist_slug, order:[ytId,...]} → reorders tracks by updating position column
  sync.php         — GET ?id=N → sync one playlist channel; no param → sync all; returns JSON status per playlist

assets/
  css/style.css    — Dark glassmorphism theme
  js/sources.js    — getPlaylistSources(): fetches /api/playlists.php; DEFAULT_PLAYLIST_SOURCES is an
                     empty array (all playlists come from the DB)
  js/script.js     — All player logic (~1230 lines)
  icons/logo.svg   — Inlined in index.php (not loaded as <img>)

electron/
  main.js          — Electron entry: spawns `php -S` on a free port (PHP_CLI_SERVER_WORKERS=4),
                     reads config from ~/.config/melody/.env; frameless window (frame:false) with
                     ipcMain handlers for win-minimize/maximize/close
  preload.js       — contextBridge script; exposes window.electronAPI.{minimize,maximize,close}
  package.json     — Electron app manifest (no npm deps; uses globally installed electron)

cron/
  sync.php         — PHP CLI script for background sync (system cron or Docker bash loop, 43200 s = 12 h)

Dockerfile         — php:8.2-apache + pdo_mysql + simplexml
docker-compose.yml — app (port 8080) + db (MySQL 8, healthcheck-gated) + cron (bash loop)

install.sh         — Ubuntu/Debian installer: installs php-cli php-mysql php-mbstring php-curl php-xml,
                     Node 20, electron globally; copies to /opt/melody; prompts DB + admin creds;
                     adds crontab entry (0 */12 * * *) writing to ~/.config/melody/sync.log
update.sh          — Pulls latest changes (git pull or clone) then rsyncs to /opt/melody/
uninstall.sh       — Removes /opt/melody, /usr/local/bin/melody, .desktop entry, optionally ~/.config/melody/
```

### Admin panel (admin.php) mutations

All mutations are POST-only, guarded by `melody_is_logged_in()`:

- `melody_create_playlist` — create playlist with optional `channel_id`
- `melody_update_channel` — set or clear `channel_id` on a playlist
- `melody_sync_now` — trigger channel sync for one playlist
- `melody_delete_playlist` — delete playlist and cascade-delete its videos
- `melody_add_track` — add a track by YouTube URL (title fetched via oEmbed)
- `melody_delete_track` — delete a single track

### API response format

`GET /api/playlists.php`:
```json
[{ "name": "Playlist Name", "slug": "playlist-name", "api": "/api/playlist.php?slug=playlist-name", "demo": [{ "id": "ytId", "title": "...", "url": "..." }] }]
```

`GET /api/playlist.php?slug=playlist-name`:
```json
[{ "id": "ytId", "title": "...", "url": "..." }]
```

`POST /api/order.php`:
```json
{ "playlist_slug": "my-playlist", "order": ["ytId1", "ytId2", "ytId3"] }
```

`demo` in the playlists response contains **all** tracks (not a sample). `playlist.php` returns the same data — it exists as a per-playlist endpoint for potential custom sources.

### Track data shape
```js
{ id: "YouTubeVideoId", title: "Video Title", url: "https://www.youtube.com/watch?v=..." }
```

### Database schema
```sql
melody_playlists  (id, name, slug UNIQUE, channel_id, last_seen_video_id, created_at)
melody_videos     (id, playlist_id FK, youtube_id, title, youtube_url, position, created_at)
melody_favorites  (id, youtube_id UNIQUE, created_at)
```

`position`, `channel_id`, and `last_seen_video_id` are all added via in-place ALTER TABLE migrations in `db.php` (checks `information_schema.COLUMNS` on first connect). **New columns must always be added this way — never in `CREATE TABLE`.**

### Channel auto-import

Playlists with a `channel_id` set are synced automatically (twice daily) and manually via the Sync button in the sidebar or `GET /api/sync.php?id={playlist_id}`.

Sync logic (`melody_sync_playlist_channel` in `includes/youtube.php`):
1. Fetch `https://www.youtube.com/feeds/videos.xml?channel_id={channel_id}` (requires `php-xml`)
2. Iterate entries; for each, HEAD-request `youtube.com/shorts/{id}` — HTTP 200 = Short (skip), redirect = regular video
3. Compare video ID against `last_seen_video_id`; if different, insert into `melody_videos` and update `last_seen_video_id`
4. All errors are logged via `error_log()` and return `['status' => 'error']` — nothing crashes

Sync status values: `added` | `up_to_date` | `skipped` (no channel_id) | `error`.

### Player (script.js)

Boot sequence: `boot()` → `initPlaylists()` (API fetch) → `loadStorage()` → `applySavedState()` → `renderPlaylistNav()` → `bindEvents()` → `initParticles()` / `initVisualizer()` → `loadPlaylist()`.

- Central `state` object — playlists, tracks, player instance, playback settings, favorites
- `dom` object — all ~50 DOM element references, looked up once at parse time via `const $ = id => document.getElementById(id)`
- YouTube playback via IFrame API; `onYouTubeIframeAPIReady` is a required global callback
- `saveStorage()` / `loadStorage()` persist to `localStorage` under key `melody_v2`
- Two canvas animations run always: 60-bar simulated visualizer and ambient particles
- Custom (user-added) playlists are flagged `{ custom: true }` and round-trip through localStorage only
- Favorites are stored in both localStorage and `melody_favorites` DB table; on boot, `/api/favorites.php` is fetched and overwrites the localStorage set (localStorage is the fallback when DB is unreachable)
- **Pseudo-playlists**: "All Songs" (`state.allActive`, `loadAllView()`) aggregates unique tracks across all playlists; "Favorites" (`state.favoritesActive`, `loadFavoritesView()`) does the same for favorited tracks. Both are rendered in `renderPlaylistNav()` above the regular playlist list and use `pl.tracks` (if loaded) or `pl.demo` (always present from API) as their source.
- Track counts shown far-right on each sidebar entry via `<span class="pl-count">` with `margin-left: auto`. Active count color uses `.playlist-nav li.active .pl-count` — **not** `.pl-count.active` (that selector is wrong; active class is on the parent `li`).
- Mini player mode: toggled via `dom.miniToggle`; adds `mini-mode` class to `#app`
- Right-click on a track shows a context menu (`#ctx-menu`) with "Play Next" option
- `playbackMode` (`'audio'` | `'video'`) controls whether the YouTube iframe or the album art container is visible
- **Electron frameless window**: `bindEvents()` checks `window.electronAPI`; if present it adds `body.electron` class (activates custom title bar CSS) and wires `#win-minimize/maximize/close` buttons via IPC. The `#win-controls` bar spans full width with `-webkit-app-region: drag`; buttons override with `no-drag`. The app grid shifts down 32 px via `body.electron #app { margin-top: 32px }`. In browser/Docker mode the buttons are hidden and `window.electronAPI` is undefined.
- **Sidebar Sync button** (`#btn-sync-channels`): calls `GET /api/sync.php`, shows toast ("N new videos added" / "Already up to date" / "No channels configured"), then re-runs `initPlaylists()` + `renderPlaylistNav()` + `loadPlaylist()` if any videos were added. Disabled (faded) while in-flight.
- **Sidebar playlist scroll**: `.playlist-nav` is `display:flex; flex-direction:column; overflow:hidden`. Only the `ul` has `overflow-y:auto` so the "Playlists" nav label stays pinned. `ul` has `padding-right:3px` for scrollbar breathing room.

**localStorage persists**: volume, loop mode, shuffle, autoplay, speed, playback mode, favorites, custom playlists, active playlist index.

**Keyboard shortcuts** (blocked when focus is on an input/select/textarea):

| Key | Action |
|-----|--------|
| `Space` | Play / Pause |
| `Shift+→` / `Shift+←` | Next / Previous track |
| `→` / `←` | Seek ±5 s |
| `↑` / `↓` | Volume ±5 |
| `M` | Toggle mute |
| `L` | Cycle loop mode |
| `S` | Toggle shuffle |
| `Ctrl+F` | Focus search |

## Environment variables

| Variable | Purpose |
|----------|---------|
| `DB_HOST` | MySQL host (required by Electron) |
| `DB_PORT` | MySQL port (default 3306) |
| `DB_NAME` | Database name |
| `DB_USER` | Database user |
| `DB_PASS` | Database password |
| `MYSQL_ROOT_PASSWORD` | MySQL root password (Docker only) |
| `MELODY_ADMIN_USER` | Admin username (default: `admin`) |
| `MELODY_ADMIN_PASS_HASH` | bcrypt hash of admin password |

Generate a new hash: `php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"`

## Key Constraints

- **No build tooling** — do not introduce bundlers, TypeScript, or npm without discussing first
- **No framework** — the frontend is intentionally vanilla JS
- **`php-xml` is required** for channel sync (`simplexml_load_string`); install with `sudo apt-get install php-xml`. Already included in `install.sh` but may be missing on older installs.
- **`.env.example` contains a real bcrypt hash** — replace it before shipping; it is a leftover placeholder
