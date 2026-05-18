@# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Melody is a YouTube playlist manager and music player with two independent components:
- **Frontend**: Vanilla JS/HTML/CSS web app — no framework, no build step
- **Backend**: WordPress plugin (`wp-plugins/melody-playlists-v2/`) that exposes a REST API

## Development Workflow

No build process exists. To develop the frontend, open `index.html` directly in a browser or serve it with any static file server:

```bash
python3 -m http.server 8080
# or
npx serve .
```

For WordPress plugin development, copy `wp-plugins/melody-playlists-v2/` into a WordPress installation's `wp-content/plugins/` directory and activate it.

The production WordPress backend is at: `https://postonce.dev-polygontech.xyz`

## Architecture

### Frontend

**Entry point**: `index.html` → loads `assets/js/sources.js` then `assets/js/script.js`.

**`assets/js/sources.js`** — fetches the playlist list from the WordPress REST API:
```
GET /wp-json/melody/v1/playlists
```
Falls back to demo data if the API is unreachable.

**`assets/js/script.js`** (~955 lines) — all player logic:
- Central `state` object holds everything (playlists, tracks, player instance, playback settings, favorites)
- `dom` object holds all ~50 DOM references
- `bindEvents()` wires all event listeners at startup
- Persistence: `saveState()` / `loadState()` sync to `localStorage` under the key `melody_v2`
- YouTube playback via the IFrame API (`onYouTubeIframeAPIReady` global callback)
- Two Canvas animations running on `requestAnimationFrame`: visualizer (60 bars) and background particles

**LocalStorage persists**: volume, loop mode, shuffle, autoplay, speed, favorites, custom playlists, active playlist index.

**Custom playlists** are frontend-only (localStorage) — they are not sent to WordPress.

**Track data shape**:
```js
{ id: "YouTubeVideoId", title: "Video Title", url: "https://www.youtube.com/watch?v=..." }
```

### WordPress Plugin (`wp-plugins/melody-playlists-v2/`)

| File | Purpose |
|------|---------|
| `melody-playlists.php` | Plugin bootstrap, requires all includes |
| `includes/api.php` | REST endpoints (`/wp-json/melody/v1/playlists`, `/wp-json/melody/v1/playlist/{slug}`) |
| `includes/db.php` | DB schema creation on activation (`wp_melody_playlists`, `wp_melody_videos`) |
| `includes/youtube.php` | Extracts YouTube video ID from URL; fetches title via oEmbed |
| `includes/auth.php` | Session-based admin login (bcrypt password stored as PHP constant) |
| `includes/shortcode.php` | `[melody_playlist_manager]` shortcode — admin CRUD UI |

**API response format** (from `GET /playlists`):
```json
[
  {
    "name": "Playlist Name",
    "api": "https://.../wp-json/melody/v1/playlist/slug",
    "demo": [{ "id": "ytId", "title": "...", "url": "..." }]
  }
]
```

The plugin uses `$wpdb` directly (no ORM), procedural PHP (no classes), and WordPress sessions for auth — no JWT or nonces.

## Key Constraints

- **No build tooling** — do not introduce bundlers, TypeScript, or npm without discussing first
- **No framework** — the frontend is intentionally vanilla JS
- The API base URL is hardcoded in `sources.js`; changing it requires editing that file
- The admin password hash is hardcoded in `includes/auth.php`
- `wp-plugins/melody-playlists-v1/` is the legacy version — prefer `v2` for all changes
