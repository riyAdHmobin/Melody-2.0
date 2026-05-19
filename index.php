<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Melody — Something I Hold Dear</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/png" href="assets/icons/logo.svg">
</head>
<body>

<!-- ─── Ambient Background Particles ─── -->
<canvas id="particles-canvas"></canvas>

<!-- ─── App Shell ─── -->
<div id="app">

    <!-- Custom window controls (Electron frameless mode only) -->
    <div id="win-controls" aria-label="Window controls">
        <button id="win-minimize" title="Minimize">
            <svg viewBox="0 0 12 12" fill="none"><line x1="2" y1="6" x2="10" y2="6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </button>
        <button id="win-maximize" title="Maximize / Restore">
            <svg viewBox="0 0 12 12" fill="none"><rect x="2" y="2" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
        </button>
        <button id="win-close" title="Close">
            <svg viewBox="0 0 12 12" fill="none"><line x1="2" y1="2" x2="10" y2="10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="10" y1="2" x2="2" y2="10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </button>
    </div>

    <!-- ═══════════════════════════════════════
         LEFT SIDEBAR
    ════════════════════════════════════════ -->
    <aside id="sidebar">
        <div class="sidebar-inner">

            <!-- Logo -->
            <div class="logo">
                <span class="logo-name">Melody</span>
                <span class="logo-tagline">Something I Hold Dear</span>
            </div>

            <!-- Search -->
            <div class="search-wrap">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="search-input" placeholder="Search songs…" autocomplete="off" />
                <button id="search-clear" class="search-clear" aria-label="Clear search">✕</button>
            </div>

            <!-- Playlist Nav -->
            <nav class="playlist-nav">
                <p class="nav-label">Playlists</p>
                <ul id="playlist-list"></ul>
            </nav>

            <!-- Mode Toggle -->
            <div class="mode-toggle-wrap">
                <p class="nav-label">Playlist Settings</p>
                <div class="mode-toggle">
                    <button onclick="window.location.href='/admin.php'" class="mode-btn as-r-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
                        </svg>
                        Add Media
                    </button>
                </div>
                <div class="mode-toggle sync-btn">
                    <button id="btn-sync-channels" class="mode-btn as-r-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                        </svg>
                        Sync
                    </button>
                </div>
            </div>

            <!-- Mode Toggle -->
            <div class="mode-toggle-wrap">
                <p class="nav-label">Playback Mode</p>
                <div class="mode-toggle">
                    <button id="btn-audio-mode" class="mode-btn active" data-mode="audio">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
                        </svg>
                        Audio
                    </button>
                    <button id="btn-video-mode" class="mode-btn" data-mode="video">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                        </svg>
                        Video
                    </button>
                </div>
            </div>

            <!-- Extra Controls -->
            <div class="sidebar-extras">
                <p class="nav-label">Options</p>
                <label class="toggle-row">
                    <span>Autoplay</span>
                    <div class="switch"><input type="checkbox" id="toggle-autoplay" checked /><span class="slider"></span></div>
                </label>
                <label class="toggle-row">
                    <span>Shuffle</span>
                    <div class="switch"><input type="checkbox" id="toggle-shuffle" /><span class="slider"></span></div>
                </label>
                <div class="speed-row">
                    <span>Speed</span>
                    <select id="speed-select">
                        <option value="0.5">0.5×</option>
                        <option value="0.75">0.75×</option>
                        <option value="1" selected>1×</option>
                        <option value="1.25">1.25×</option>
                        <option value="1.5">1.5×</option>
                        <option value="2">2×</option>
                    </select>
                </div>
            </div>

        </div>
    </aside>

    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay"></div>

    <!-- ═══════════════════════════════════════
         MIDDLE — TRACK LIST
    ════════════════════════════════════════ -->
    <section id="tracklist-panel">
        <div class="tracklist-header">
            <h2 id="tracklist-title">Tracks</h2>
            <span id="tracklist-count"></span>
            <button id="btn-add-playlist" title="Add custom playlist source">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
            </button>
        </div>
        <div id="tracklist-loading" class="loading-state">
            <div class="spinner"></div>
            <span>Loading tracks…</span>
        </div>
        <ul id="track-list"></ul>
    </section>

    <!-- ═══════════════════════════════════════
         MAIN CONTENT
    ════════════════════════════════════════ -->
    <main id="main-content">

        <!-- Top Bar (mobile) -->
        <header id="topbar">
            <button id="menu-toggle" aria-label="Open menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <span class="topbar-logo">Melody</span>
            <button id="mini-player-toggle" aria-label="Mini player">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/>
                </svg>
            </button>
        </header>

        <!-- Now Playing Stage -->
        <section id="now-playing-stage">

            <!-- Video Container -->
            <div id="video-container" class="hidden">
                <div id="youtube-player-wrap">
                    <div id="youtube-player"></div>
                    <div id="yt-overlay"></div>
                </div>
            </div>

            <!-- Audio Art Container -->
            <div id="audio-art-container">
                <div id="art-glow"></div>
                <div id="album-art-wrap">
                    <img id="album-art" src="assets/icons/logo.svg" alt="Album art" loading="lazy" />
                    <div id="art-spinner"></div>
                </div>
                <!-- Equalizer bars -->
                <div id="equalizer" class="hidden">
                    <span></span><span></span><span></span><span></span><span></span>
                    <span></span><span></span><span></span><span></span><span></span>
                </div>
            </div>

            <!-- Song Info -->
            <div id="song-info">
                <div id="song-title-wrap">
                    <h1 id="song-title">Melody - Something I Hold Dear</h1>
                    <button id="btn-favorite" aria-label="Add to favorites" title="Favorite">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                    </button>
                </div>
                <p id="song-sub">Choose a playlist to start</p>
                <div id="song-tags"></div>
            </div>

            <!-- Canvas Visualizer -->
            <canvas id="visualizer-canvas"></canvas>
            <div class="player-timeline-vol">
                <div class="timeline-wrap">
                    <span id="time-current">0:00</span>
                    <div id="progress-bar-wrap">
                        <div id="progress-bar">
                            <div id="progress-fill"></div>
                            <div id="progress-thumb"></div>
                        </div>
                    </div>
                    <span id="time-total">0:00</span>
                </div>
            </div>


        </section>

    </main>

    <!-- ═══════════════════════════════════════
         BOTTOM PLAYER BAR
    ════════════════════════════════════════ -->
    <footer id="player-bar">

        <!-- Track mini info -->
        <div class="player-track-info">
            <img id="player-thumb" src="assets/icons/logo.svg" alt="" />
            <div class="player-meta">
                <span id="player-title">Melody</span>
                <span id="player-playlist">Something I Hold Dear</span>
            </div>
        </div>

        <!-- Controls -->
        <div class="player-controls">
            <button id="btn-loop" class="ctrl-btn" title="Loop (off)" data-loop="none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                </svg>
            </button>
            <button id="btn-prev" class="ctrl-btn" title="Previous (←)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/>
                </svg>
            </button>
            <button id="btn-play" class="ctrl-btn play-btn" title="Play/Pause (Space)">
                <svg class="icon-play" viewBox="0 0 24 24" fill="currentColor">
                    <polygon points="5 3 19 12 5 21 5 3"/>
                </svg>
                <svg class="icon-pause hidden" viewBox="0 0 24 24" fill="currentColor">
                    <rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>
                </svg>
            </button>
            <button id="btn-next" class="ctrl-btn" title="Next (→)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/>
                </svg>
            </button>
            <button id="btn-shuffle" class="ctrl-btn" title="Shuffle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/>
                    <polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/>
                    <line x1="4" y1="4" x2="9" y2="9"/>
                </svg>
            </button>
        </div>

        <!-- Timeline + Volume -->
        <div class="player-timeline-vol">
<div class="volume-wrap">
                <button id="btn-mute" class="ctrl-btn small" title="Mute (M)">
                    <svg id="vol-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                        <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>
                    </svg>
                </button>
                <input type="range" id="volume-slider" min="0" max="100" value="80" />
            </div>
        </div>

        <!-- Mini-mode progress strip (hidden in normal mode) -->
        <div id="mini-progress"><div id="mini-progress-fill"></div></div>

    </footer>

</div><!-- /#app -->

<!-- ─── Add Playlist Modal ─── -->
<div id="modal-overlay" class="hidden">
    <div id="modal">
        <h3>Add Playlist Source</h3>
        <label>Name<input type="text" id="modal-name" placeholder="e.g. My Playlist" /></label>
        <label>API URL<input type="text" id="modal-api" placeholder="https://…/api.json" /></label>
        <div class="modal-actions">
            <button id="modal-cancel" class="btn-secondary">Cancel</button>
            <button id="modal-save" class="btn-primary">Add Playlist</button>
        </div>
    </div>
</div>

<!-- ─── Track Context Menu ─── -->
<div id="ctx-menu" class="hidden" role="menu" aria-label="Track options">
    <button id="ctx-play-next" role="menuitem">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/>
        </svg>
        Play Next
    </button>
</div>

<!-- ─── Toast ─── -->
<div id="toast" aria-live="polite"></div>

<!-- ─── Local Audio (for downloaded tracks) ─── -->
<audio id="local-audio" preload="none"></audio>

<!-- ─── YouTube IFrame API ─── -->
<script src="https://www.youtube.com/iframe_api"></script>
<script src="assets/js/sources.js"></script>
<script src="assets/js/script.js"></script>
</body>
</html>