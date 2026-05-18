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
# First run only: create DB tables
curl http://localhost:8080/setup.php
# Then delete setup.php (or just leave it — it's safe to re-run)
```

Player → http://localhost:8080  
Admin  → http://localhost:8080/admin.php  (default: admin / melody)

### Local PHP + existing MySQL

```bash
# Edit config.php (or set env vars) with your DB credentials
# Create the melody database, then:
php -S localhost:8080
# Visit http://localhost:8080/setup.php to create tables
```

## Architecture

```
index.php          — Player UI (vanilla JS, served by PHP)
admin.php          — Password-protected playlist/track CRUD
setup.php          — One-time table creation (delete after first run)
config.php         — DB credentials + admin hash (reads from env vars)
.env               — Local environment overrides (gitignored)
.env.example       — Template to copy

includes/
  db.php           — PDO MySQL connection + melody_create_tables()
  auth.php         — Session-based login (melody_login / melody_logout)
  youtube.php      — melody_extract_youtube_id(), melody_get_youtube_title() via oEmbed, melody_slugify()

api/
  playlists.php    — GET → JSON list of all playlists with demo tracks
  playlist.php     — GET ?slug=xxx → JSON array of tracks for one playlist

assets/
  css/style.css    — Dark glassmorphism theme
  js/sources.js    — Fetches /api/playlists.php; falls back to empty list
  js/script.js     — All player logic (~955 lines)
  icons/logo.svg

Dockerfile         — php:8.2-apache + pdo_mysql
docker-compose.yml — app (port 8080) + db (MySQL 8)
wp-plugins/        — Legacy WordPress plugin (archived, not used)
```

### API response format

`GET /api/playlists.php`:
```json
[
  {
    "name": "Playlist Name",
    "api":  "/api/playlist.php?slug=playlist-name",
    "demo": [{ "id": "ytId", "title": "...", "url": "..." }]
  }
]
```

`GET /api/playlist.php?slug=playlist-name`:
```json
[{ "id": "ytId", "title": "...", "url": "..." }]
```

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
- Central `state` object holds everything (playlists, tracks, player instance, playback settings, favorites)
- `dom` object holds all ~50 DOM references
- `bindEvents()` wires all event listeners at startup
- Persistence: `saveStorage()` / `loadStorage()` sync to `localStorage` under the key `melody_v2`
- YouTube playback via the IFrame API (`onYouTubeIframeAPIReady` global callback)
- Two Canvas animations: visualizer (60 bars) and background particles

**LocalStorage persists**: volume, loop mode, shuffle, autoplay, speed, favorites, custom playlists, active playlist index.

## Key Constraints

- **No build tooling** — do not introduce bundlers, TypeScript, or npm without discussing first
- **No framework** — the frontend is intentionally vanilla JS
- Admin password hash is set via `MELODY_ADMIN_PASS_HASH` env var (or `config.php` fallback)
  - Generate a new hash: `php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"`
- `wp-plugins/` is archived — do not modify it
