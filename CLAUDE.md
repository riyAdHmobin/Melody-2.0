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

The installer writes DB credentials and the admin password hash to `~/.config/melody/.env` (mode 600). Electron reads this file at startup and injects the vars into the PHP process environment. If `DB_HOST` is missing from that file, Electron shows an error and quits.

### Local PHP + existing MySQL

```bash
# Edit config.php (or set env vars) with your DB credentials
php -S localhost:8080
```

## Architecture

```
index.php          — Player UI (HTML + inline SVG, loads assets/js/*)
admin.php          — Password-protected playlist/track CRUD (self-contained PHP + inline CSS)
config.php         — DB credentials + admin hash (reads env vars, falls back to hardcoded defaults)
.env               — Local environment overrides (gitignored)

includes/
  db.php           — PDO singleton; runs CREATE TABLE IF NOT EXISTS on every first connection
  auth.php         — Session-based login (melody_session_start, melody_login, melody_logout)
  youtube.php      — melody_extract_youtube_id(), melody_get_youtube_title() via oEmbed, melody_slugify()
  .htaccess        — Blocks direct HTTP access to PHP include files

api/
  playlists.php    — GET → all playlists, each with full track list embedded in "demo" field
  playlist.php     — GET ?slug=xxx → track array for one playlist
  favorites.php    — GET → [ytId, ...]; POST {id} → toggle favorite (add/remove), returns {action, id}
  order.php        — POST {playlist_slug, order:[ytId,...]} → reorders tracks by updating position column

assets/
  css/style.css    — Dark glassmorphism theme
  js/sources.js    — getPlaylistSources(): fetches /api/playlists.php, merges with DEFAULT_PLAYLIST_SOURCES
  js/script.js     — All player logic (~1000 lines)
  icons/logo.svg   — Inlined in index.php (not loaded as <img>)

electron/
  main.js          — Electron entry: spawns `php -S` on a free port, reads config from ~/.config/melody/.env
  package.json     — Electron app manifest (no npm deps; uses globally installed electron)

Dockerfile         — php:8.2-apache + pdo_mysql
docker-compose.yml — app (port 8080) + db (MySQL 8, healthcheck-gated)

install.sh         — Ubuntu/Debian installer: installs php-cli, Node 20, electron globally, copies app to /opt/melody, creates launcher + .desktop entry, prompts for DB creds + admin password
update.sh          — Pulls latest changes (git pull or clone) then rsyncs to /opt/melody/
uninstall.sh       — Removes /opt/melody, /usr/local/bin/melody, .desktop entry, optionally ~/.config/melody/
```

### API response format

`GET /api/playlists.php`:
```json
[{ "name": "Playlist Name", "api": "/api/playlist.php?slug=playlist-name", "demo": [{ "id": "ytId", "title": "...", "url": "..." }] }]
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
melody_playlists  (id, name, slug UNIQUE, created_at)
melody_videos     (id, playlist_id FK, youtube_id, title, youtube_url, position, created_at)
melody_favorites  (id, youtube_id UNIQUE, created_at)
```

`position` is added via an in-place migration in `db.php` (checks `information_schema.COLUMNS` on first connect and runs `ALTER TABLE` if missing).

### Player (script.js)

Boot sequence: `boot()` → `initPlaylists()` (API fetch) → `loadStorage()` → `applySavedState()` → `renderPlaylistNav()` → `bindEvents()` → `initParticles()` / `initVisualizer()` → `loadPlaylist()`.

- Central `state` object — playlists, tracks, player instance, playback settings, favorites
- `dom` object — all ~50 DOM element references, looked up once at parse time
- YouTube playback via IFrame API; `onYouTubeIframeAPIReady` is a required global callback
- `saveStorage()` / `loadStorage()` persist to `localStorage` under key `melody_v2`
- Two canvas animations run always: 60-bar simulated visualizer and ambient particles
- Custom (user-added) playlists are flagged `{ custom: true }` and round-trip through localStorage only
- Favorites are stored in both localStorage and `melody_favorites` DB table; on boot, `/api/favorites.php` is fetched and overwrites the localStorage set (localStorage is the fallback when DB is unreachable)
- Mini player mode: toggled via `dom.miniToggle`; adds `mini-mode` class to `#app`
- Right-click on a track shows a context menu (`#ctx-menu`) with "Play Next" option
- `playbackMode` (`'audio'` | `'video'`) controls whether the YouTube iframe or the album art container is visible

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

### sources.js — custom playlist sources

`DEFAULT_PLAYLIST_SOURCES` in `sources.js` is a hardcoded fallback array of playlist descriptors (same shape as the API response). `getPlaylistSources()` fetches `/api/playlists.php` and merges DB playlists before the defaults, deduplicating by name.

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
- **`.env.example` contains a real bcrypt hash** — replace it before shipping; it is a leftover placeholder
