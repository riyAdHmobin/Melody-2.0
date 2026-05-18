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

api/
  playlists.php    — GET → all playlists, each with full track list embedded in "demo" field
  playlist.php     — GET ?slug=xxx → track array for one playlist

assets/
  css/style.css    — Dark glassmorphism theme
  js/sources.js    — getPlaylistSources(): fetches /api/playlists.php, merges with DEFAULT_PLAYLIST_SOURCES
  js/script.js     — All player logic (~926 lines)
  icons/logo.svg   — Inlined in index.php (not loaded as <img>)

Dockerfile         — php:8.2-apache + pdo_mysql
docker-compose.yml — app (port 8080) + db (MySQL 8, healthcheck-gated)
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

`demo` in the playlists response contains **all** tracks (not a sample). `playlist.php` returns the same data — it exists as a per-playlist endpoint for potential custom sources.

### Track data shape
```js
{ id: "YouTubeVideoId", title: "Video Title", url: "https://www.youtube.com/watch?v=..." }
```

### Database schema
```sql
melody_playlists (id, name, slug UNIQUE, created_at)
melody_videos    (id, playlist_id FK, youtube_id, title, youtube_url, created_at)
```

### Player (script.js)

Boot sequence: `boot()` → `initPlaylists()` (API fetch) → `loadStorage()` → `applySavedState()` → `renderPlaylistNav()` → `bindEvents()` → `initParticles()` / `initVisualizer()` → `loadPlaylist()`.

- Central `state` object — playlists, tracks, player instance, playback settings, favorites
- `dom` object — all ~50 DOM element references, looked up once at parse time
- YouTube playback via IFrame API; `onYouTubeIframeAPIReady` is a required global callback
- `saveStorage()` / `loadStorage()` persist to `localStorage` under key `melody_v2`
- Two canvas animations run always: 60-bar simulated visualizer and ambient particles
- Custom (user-added) playlists are flagged `{ custom: true }` and round-trip through localStorage only

**localStorage persists**: volume, loop mode, shuffle, autoplay, speed, favorites, custom playlists, active playlist index.

## Key Constraints

- **No build tooling** — do not introduce bundlers, TypeScript, or npm without discussing first
- **No framework** — the frontend is intentionally vanilla JS
- Admin password hash is set via `MELODY_ADMIN_PASS_HASH` env var (or `config.php` fallback)
  - Generate: `php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"`
