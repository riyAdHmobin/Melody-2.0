'use strict';

/* ─────────────────────────────────────────────────────
    2.  STATE
──────────────────────────────────────────────────── */
const state = {
    playlists:        [],        // [{name, api, demo, tracks:[]}]
    activePl:         0,         // index of active playlist
    tracks:           [],        // current playlist tracks
    currentIdx:       -1,        // currently playing track index
    isPlaying:        false,
    isShuffle:        false,
    loopMode:         'none',    // 'none' | 'one' | 'playlist'
    playbackMode:     'audio',   // 'audio' | 'video'
    volume:           80,
    autoplay:         true,
    speed:            1,
    favorites:        new Set(),
    favoritesActive:  false,
    searchQuery:      '',
    ytPlayer:         null,
    ytReady:          false,
    seekDragging:     false,
    progressInterval: null,
    ctxTrackIdx:      -1,
};

let dragSrcIdx = -1;

/* ─────────────────────────────────────────────────────
   3.  DOM REFS
───────────────────────────────────────────────────── */
const $ = id => document.getElementById(id);
const dom = {
    app:             $('app'),
    sidebar:         $('sidebar'),
    sidebarOverlay:  $('sidebar-overlay'),
    menuToggle:      $('menu-toggle'),
    miniToggle:      $('mini-player-toggle'),
    playlistList:    $('playlist-list'),
    searchInput:     $('search-input'),
    searchClear:     $('search-clear'),
    trackList:       $('track-list'),
    tracklistTitle:  $('tracklist-title'),
    tracklistCount:  $('tracklist-count'),
    tracklistLoading: $('tracklist-loading'),
    albumArt:        $('album-art'),
    albumArtWrap:    $('album-art-wrap'),
    artSpinner:      $('art-spinner'),
    artGlow:         $('art-glow'),
    equalizer:       $('equalizer'),
    songTitle:       $('song-title'),
    songSub:         $('song-sub'),
    songTags:        $('song-tags'),
    videoContainer:  $('video-container'),
    audioArtCont:    $('audio-art-container'),
    playerTitle:     $('player-title'),
    playerPlaylist:  $('player-playlist'),
    playerThumb:     $('player-thumb'),
    btnPlay:         $('btn-play'),
    iconPlay:        document.querySelector('.icon-play'),
    iconPause:       document.querySelector('.icon-pause'),
    btnPrev:         $('btn-prev'),
    btnNext:         $('btn-next'),
    btnLoop:         $('btn-loop'),
    btnShuffle:      $('btn-shuffle'),
    btnFavorite:     $('btn-favorite'),
    progressWrap:    $('progress-bar-wrap'),
    progressFill:    $('progress-fill'),
    progressBar:     $('progress-bar'),
    timeCurrent:     $('time-current'),
    timeTotal:       $('time-total'),
    volumeSlider:    $('volume-slider'),
    btnMute:         $('btn-mute'),
    volIcon:         $('vol-icon'),
    btnAudioMode:    $('btn-audio-mode'),
    btnVideoMode:    $('btn-video-mode'),
    toggleAutoplay:  $('toggle-autoplay'),
    toggleShuffle:   $('toggle-shuffle'),
    speedSelect:     $('speed-select'),
    btnAddPlaylist:  $('btn-add-playlist'),
    modalOverlay:    $('modal-overlay'),
    modalName:       $('modal-name'),
    modalApi:        $('modal-api'),
    modalCancel:     $('modal-cancel'),
    modalSave:       $('modal-save'),
    visualizerCanvas:    $('visualizer-canvas'),
    particlesCanvas:     $('particles-canvas'),
    miniProgressFill:    $('mini-progress-fill'),
    ctxMenu:             $('ctx-menu'),
    ctxPlayNext:         $('ctx-play-next'),
    toast:               $('toast'),
};

/* ─────────────────────────────────────────────────────
   4.  LOCAL STORAGE HELPERS
───────────────────────────────────────────────────── */
const LS_KEY = 'melody_v2';

function loadStorage() {
    try {
        const saved = JSON.parse(localStorage.getItem(LS_KEY) || '{}');
        if (saved.volume    !== undefined) state.volume    = saved.volume;
        if (saved.loopMode  !== undefined) state.loopMode  = saved.loopMode;
        if (saved.isShuffle !== undefined) state.isShuffle = saved.isShuffle;
        if (saved.autoplay  !== undefined) state.autoplay  = saved.autoplay;
        if (saved.speed     !== undefined) state.speed     = saved.speed;
        if (saved.playbackMode)            state.playbackMode = saved.playbackMode;
        if (saved.favorites)               state.favorites = new Set(saved.favorites);
        if (saved.playlists && Array.isArray(saved.playlists)) {
            // merge custom playlists only
            saved.playlists
                .filter(p => p.custom)
                .forEach(p => state.playlists.push(p));
        }
        if (saved.activePl  !== undefined) state.activePl  = Math.min(saved.activePl, state.playlists.length - 1);
    } catch(e) { /* ignore */ }
}

function saveStorage() {
    const customPl = state.playlists.filter(p => p.custom);
    localStorage.setItem(LS_KEY, JSON.stringify({
        volume:       state.volume,
        loopMode:     state.loopMode,
        isShuffle:    state.isShuffle,
        autoplay:     state.autoplay,
        speed:        state.speed,
        playbackMode: state.playbackMode,
        favorites:    [...state.favorites],
        activePl:     state.activePl,
        playlists:    customPl,
    }));
}

/* ─────────────────────────────────────────────────────
   5.  YOUTUBE IFRAME API
───────────────────────────────────────────────────── */

// Called by YouTube IFrame API when ready
window.onYouTubeIframeAPIReady = function () {
    state.ytPlayer = new YT.Player('youtube-player', {
        height: '100%',
        width: '100%',
playerVars: {
            autoplay:       0,
            controls:       0,
            disablekb:      1,
            rel:            0,
            modestbranding: 1,
            iv_load_policy: 3,
            fs:             0,
        },
        events: {
            onReady:       onPlayerReady,
            onStateChange: onPlayerStateChange,
            onError:       onPlayerError,
        }
    });
};

function onPlayerReady(e) {
    state.ytReady = true;
    e.target.setVolume(state.volume);
    e.target.setPlaybackRate(state.speed);
}

function onPlayerStateChange(e) {
    const YT_PLAYING  = 1;
    const YT_PAUSED   = 2;
    const YT_ENDED    = 0;
    const YT_BUFFERING= 3;

    if (e.data === YT_PLAYING) {
        state.isPlaying = true;
        updatePlayPauseUI();
        dom.artSpinner.classList.remove('loading');
        dom.albumArtWrap.classList.add('playing');
        dom.equalizer.classList.remove('hidden');
        startProgressTracking();
        const duration = state.ytPlayer.getDuration();
        dom.timeTotal.textContent = formatTime(duration);
    } else if (e.data === YT_PAUSED) {
        state.isPlaying = false;
        updatePlayPauseUI();
        dom.albumArtWrap.classList.remove('playing');
        dom.equalizer.classList.add('hidden');
        stopProgressTracking();
    } else if (e.data === YT_ENDED) {
        handleTrackEnd();
    } else if (e.data === YT_BUFFERING) {
        dom.artSpinner.classList.add('loading');
    }
}

function onPlayerError(e) {
    console.warn('YouTube player error:', e.data);
    dom.artSpinner.classList.remove('loading');
    // Try next track on error
    setTimeout(() => playNext(), 1500);
}

/* ─────────────────────────────────────────────────────
   6.  PLAYBACK CONTROL
───────────────────────────────────────────────────── */
function loadTrack(idx, autoplay = false) {
    if (!state.tracks.length) return;
    idx = clampIndex(idx);
    state.currentIdx = idx;

    const track = state.tracks[idx];
    if (!track) return;

    const thumb = `https://img.youtube.com/vi/${track.id}/hqdefault.jpg`;
    dom.albumArt.src = thumb;
    dom.songTitle.textContent = track.title;
    dom.songSub.textContent   = state.playlists[state.activePl]?.name || '';
    dom.artSpinner.classList.add('loading');

    dom.songTags.innerHTML = '';
    const pl = state.playlists[state.activePl];
    if (pl) {
        const tag = document.createElement('span');
        tag.className = 'tag';
        tag.textContent = pl.name;
        dom.songTags.appendChild(tag);
    }

    dom.playerTitle.textContent   = track.title;
    dom.playerPlaylist.textContent = state.playlists[state.activePl]?.name || '';
    dom.playerThumb.src = thumb;

    dom.btnFavorite.classList.toggle('favorited', state.favorites.has(track.id));

    renderTrackListActive();

    if (state.ytReady && state.ytPlayer) {
        if (autoplay) {
            state.ytPlayer.loadVideoById(track.id);
        } else {
            state.ytPlayer.cueVideoById(track.id);
            dom.artSpinner.classList.remove('loading');
        }
        state.ytPlayer.setPlaybackRate(state.speed);
    }

    dom.progressFill.style.width     = '0%';
    dom.miniProgressFill.style.width = '0%';
    dom.timeCurrent.textContent      = '0:00';

    scrollTrackIntoView(idx);

    saveStorage();
}

function togglePlay() {
    if (!state.tracks.length) return;
    if (state.currentIdx < 0) { loadTrack(0, true); return; }

    if (!state.ytReady || !state.ytPlayer) return;

    if (state.isPlaying) {
        state.ytPlayer.pauseVideo();
    } else {
        state.ytPlayer.playVideo();
        state.isPlaying = true;
        updatePlayPauseUI();
    }
}

function playTrack(idx) {
    loadTrack(idx, true);
}

function playNext() {
    if (!state.tracks.length) return;
    let next;
    if (state.loopMode === 'one') {
        next = state.currentIdx;
    } else if (state.isShuffle) {
        next = randomIndex();
    } else {
        next = state.currentIdx + 1;
        if (next >= state.tracks.length) {
            if (state.loopMode === 'playlist') next = 0;
            else { handlePlaylistEnd(); return; }
        }
    }
    playTrack(next);
}

function playPrev() {
    if (!state.tracks.length) return;
    // If > 3 seconds in, restart track
    const currentTime = state.ytReady && state.ytPlayer ? state.ytPlayer.getCurrentTime() : 0;
    if (currentTime > 3) {
        state.ytPlayer.seekTo(0);
        return;
    }
    let prev = state.isShuffle ? randomIndex() : state.currentIdx - 1;
    if (prev < 0) prev = state.tracks.length - 1;
    playTrack(prev);
}

function handleTrackEnd() {
    dom.albumArtWrap.classList.remove('playing');
    dom.equalizer.classList.add('hidden');
    stopProgressTracking();
    state.isPlaying = false;
    updatePlayPauseUI();

    if (state.loopMode === 'one') {
        playTrack(state.currentIdx);
    } else if (state.autoplay) {
        playNext();
    }
}

function handlePlaylistEnd() {
    state.isPlaying = false;
    updatePlayPauseUI();
    dom.albumArtWrap.classList.remove('playing');
    dom.equalizer.classList.add('hidden');
}

function randomIndex() {
    const arr = [...Array(state.tracks.length).keys()].filter(i => i !== state.currentIdx);
    return arr[Math.floor(Math.random() * arr.length)] ?? 0;
}

function clampIndex(i) {
    return Math.max(0, Math.min(i, state.tracks.length - 1));
}

/* ─────────────────────────────────────────────────────
   7.  LOOP / SHUFFLE MODES
───────────────────────────────────────────────────── */
function cycleLoop() {
    const modes = ['none', 'one', 'playlist'];
    const next = modes[(modes.indexOf(state.loopMode) + 1) % modes.length];
    state.loopMode = next;
    dom.btnLoop.dataset.loop = next;
    const labels = { none: 'Loop (off)', one: 'Loop one track', playlist: 'Loop playlist' };
    dom.btnLoop.title = labels[next];
    dom.btnLoop.classList.toggle('active', next !== 'none');
    saveStorage();
}

function setShuffle(on) {
    state.isShuffle = on;
    dom.btnShuffle.classList.toggle('active', on);
    dom.toggleShuffle.checked = on;
    saveStorage();
}

/* ─────────────────────────────────────────────────────
   8.  VOLUME
───────────────────────────────────────────────────── */
let lastVolume = 80;

function setVolume(v) {
    v = Math.max(0, Math.min(100, v));
    state.volume = v;
    dom.volumeSlider.value = v;
    if (state.ytReady && state.ytPlayer) state.ytPlayer.setVolume(v);
    updateVolIcon(v);
    saveStorage();
}

function updateVolIcon(v) {
    const paths = {
        mute:   '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/>',
        low:    '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>',
        high:   '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>',
    };
    dom.volIcon.innerHTML = v === 0 ? paths.mute : (v < 50 ? paths.low : paths.high);
}

function toggleMute() {
    if (state.volume > 0) {
        lastVolume = state.volume;
        setVolume(0);
    } else {
        setVolume(lastVolume || 80);
    }
}

/* ─────────────────────────────────────────────────────
   9.  PROGRESS BAR
───────────────────────────────────────────────────── */
function startProgressTracking() {
    stopProgressTracking();
    state.progressInterval = setInterval(updateProgress, 500);
}

function stopProgressTracking() {
    if (state.progressInterval) {
        clearInterval(state.progressInterval);
        state.progressInterval = null;
    }
}

function updateProgress() {
    if (!state.ytReady || !state.ytPlayer || state.seekDragging) return;
    try {
        const current  = state.ytPlayer.getCurrentTime() || 0;
        const duration = state.ytPlayer.getDuration()    || 0;
        if (duration > 0) {
            const pct = (current / duration) * 100;
            dom.progressFill.style.width = pct + '%';
            dom.progressBar.querySelector('#progress-thumb').style.left = pct + '%';
            dom.miniProgressFill.style.width = pct + '%';
        }
        dom.timeCurrent.textContent = formatTime(current);
        dom.timeTotal.textContent   = formatTime(duration);
    } catch(e) { /* player not ready */ }
}

function seekTo(e) {
    if (!state.ytReady || !state.ytPlayer) return;
    const rect = dom.progressWrap.getBoundingClientRect();
    const pct  = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    const dur  = state.ytPlayer.getDuration() || 0;
    state.ytPlayer.seekTo(pct * dur, true);
    dom.progressFill.style.width = (pct * 100) + '%';
}

/* ─────────────────────────────────────────────────────
    10.  PLAYLIST LOADING
───────────────────────────────────────────────── */
async function initPlaylists() {
    const sources = await getPlaylistSources();
    state.playlists = sources.map(p => ({ ...p, tracks: [] }));
    // Custom ones are already pushed by loadStorage before initPlaylists
}

async function loadPlaylist(idx) {
    const pl = state.playlists[idx];
    if (!pl) return;

    state.favoritesActive = false;
    state.activePl  = idx;
    state.currentIdx = -1;

    dom.tracklistTitle.textContent = pl.name;
    dom.tracklistLoading.classList.remove('hidden');
    dom.trackList.innerHTML = '';

    let tracks = [];

    if (pl.tracks && pl.tracks.length > 0) {
        tracks = pl.tracks;
    } else if (pl.api) {
        try {
            const res  = await fetch(pl.api);
            const data = await res.json();
            if (Array.isArray(data)) {
                tracks = data.map(item => ({
                    id:    item.id    || extractYTId(item.url),
                    title: item.title || item.name || 'Unknown',
                    url:   item.url  || `https://www.youtube.com/watch?v=${item.id}`,
                }));
            }
        } catch (err) {
            console.warn(`Playlist API failed for "${pl.name}", using demo data.`, err);
            tracks = pl.demo || [];
        }
    } else {
        tracks = pl.demo || [];
    }

    pl.tracks    = tracks;
    state.tracks = tracks;

    dom.tracklistLoading.classList.add('hidden');
    dom.tracklistCount.textContent = `${tracks.length} track${tracks.length !== 1 ? 's' : ''}`;

    renderTrackList();
    renderPlaylistNav();
    saveStorage();
}

function extractYTId(url) {
    if (!url) return '';
    const m = url.match(/[?&]v=([^&]+)/) || url.match(/youtu\.be\/([^?]+)/);
    return m ? m[1] : '';
}

/* ─────────────────────────────────────────────────────
   11.  RENDER TRACK LIST
───────────────────────────────────────────────────── */
function renderTrackList() {
    const query  = state.searchQuery.toLowerCase();
    const tracks = query
        ? state.tracks.filter(t => t.title.toLowerCase().includes(query))
        : state.tracks;

    dom.trackList.innerHTML = '';

    if (!tracks.length) {
        dom.trackList.innerHTML = `<li style="padding:20px;color:var(--txt-muted);font-size:.85rem;text-align:center;">No tracks found.</li>`;
        return;
    }

    tracks.forEach((track, visIdx) => {
        const realIdx = state.tracks.indexOf(track);
        const isFav   = state.favorites.has(track.id);
        const isActive = realIdx === state.currentIdx;

        const li = document.createElement('li');
        li.dataset.idx = realIdx;
        li.className = isActive ? 'active' : '';
        li.style.animationDelay = `${visIdx * 30}ms`;

        const canDrag = !state.searchQuery;

        li.innerHTML = `
      ${canDrag ? `<span class="track-drag-handle" draggable="true" aria-hidden="true">
        <svg viewBox="0 0 8 14" fill="currentColor">
          <circle cx="2" cy="2"  r="1.1"/><circle cx="6" cy="2"  r="1.1"/>
          <circle cx="2" cy="7"  r="1.1"/><circle cx="6" cy="7"  r="1.1"/>
          <circle cx="2" cy="12" r="1.1"/><circle cx="6" cy="12" r="1.1"/>
        </svg>
      </span>` : ''}
      <span class="track-num">${visIdx + 1}</span>
      <div class="track-eq" aria-hidden="true">
        <span></span><span></span><span></span>
      </div>
      <img class="track-thumb" src="https://img.youtube.com/vi/${track.id}/default.jpg" alt="" loading="lazy" />
      <div class="track-info">
        <span class="track-name" title="${escHtml(track.title)}">${highlightMatch(escHtml(track.title), query)}</span>
      </div>
      <button class="track-fav-btn ${isFav ? 'faved' : ''}" data-id="${track.id}" title="${isFav ? 'Unfavorite' : 'Favorite'}" aria-label="Favorite">
        <svg viewBox="0 0 24 24" fill="${isFav ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
      </button>
    `;

        li.addEventListener('click', e => {
            if (e.target.closest('.track-fav-btn')) return;
            if (e.target.closest('.track-drag-handle')) return;
            playTrack(realIdx);
        });

        if (canDrag) {
            const handle = li.querySelector('.track-drag-handle');

            handle.addEventListener('dragstart', e => {
                dragSrcIdx = realIdx;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', realIdx);
                e.dataTransfer.setDragImage(li, e.offsetX + handle.offsetLeft, e.offsetY + 8);
                setTimeout(() => li.classList.add('dragging'), 0);
            });

            handle.addEventListener('dragend', () => {
                li.classList.remove('dragging');
                dom.trackList.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
                dragSrcIdx = -1;
            });

            li.addEventListener('dragover', e => {
                if (dragSrcIdx === -1) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                dom.trackList.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
                li.classList.add('drag-over');
            });

            li.addEventListener('dragleave', e => {
                if (!li.contains(e.relatedTarget)) li.classList.remove('drag-over');
            });

            li.addEventListener('drop', e => {
                e.preventDefault();
                const targetIdx = parseInt(li.dataset.idx);
                li.classList.remove('drag-over');
                reorderTrack(dragSrcIdx, targetIdx);
            });
        }

        li.querySelector('.track-fav-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            toggleFavorite(track.id, li.querySelector('.track-fav-btn'));
        });

        dom.trackList.appendChild(li);
    });
}

function renderTrackListActive() {
    dom.trackList.querySelectorAll('li').forEach(li => {
        const idx = parseInt(li.dataset.idx);
        li.classList.toggle('active', idx === state.currentIdx);
    });
}

function scrollTrackIntoView(idx) {
    const li = dom.trackList.querySelector(`li[data-idx="${idx}"]`);
    if (li) li.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

function highlightMatch(str, q) {
    if (!q) return str;
    const re = new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return str.replace(re, '<mark style="background:var(--accent-dim);color:var(--accent);border-radius:2px;">$1</mark>');
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function truncTitle(s, n = 16) {
    return s.length > n ? s.slice(0, n) + '…' : s;
}

/* ─────────────────────────────────────────────────────
   12.  RENDER PLAYLIST NAV
───────────────────────────────────────────────────── */
function renderPlaylistNav() {
    dom.playlistList.innerHTML = '';

    // Favorites pseudo-playlist
    const favLi = document.createElement('li');
    favLi.className = state.favoritesActive ? 'active' : '';
    const favCount = state.favorites.size;
    favLi.innerHTML = `
      <button>
        <span class="pl-dot fav-pl-dot"></span>
        Favorites${favCount ? ` (${favCount})` : ''}
      </button>`;
    favLi.querySelector('button').addEventListener('click', loadFavoritesView);
    dom.playlistList.appendChild(favLi);

    state.playlists.forEach((pl, idx) => {
        const li  = document.createElement('li');
        li.className = (!state.favoritesActive && idx === state.activePl) ? 'active' : '';
        li.innerHTML = `
      <button>
        <span class="pl-dot"></span>
        ${escHtml(pl.name)}
      </button>`;
        li.querySelector('button').addEventListener('click', () => {
            loadPlaylist(idx);
        });
        dom.playlistList.appendChild(li);
    });
}

/* ─────────────────────────────────────────────────────
   13.  PLAYBACK MODE (Audio / Video)
───────────────────────────────────────────────────── */
function setPlaybackMode(mode) {
    state.playbackMode = mode;
    dom.btnAudioMode.classList.toggle('active', mode === 'audio');
    dom.btnVideoMode.classList.toggle('active', mode === 'video');

    if (mode === 'video') {
        dom.videoContainer.classList.remove('hidden');
        dom.audioArtCont.classList.add('hidden');
    } else {
        dom.videoContainer.classList.add('hidden');
        dom.audioArtCont.classList.remove('hidden');
    }
    saveStorage();
}

/* ─────────────────────────────────────────────────────
   14.  FAVORITES
───────────────────────────────────────────────────── */
function toggleFavorite(id, btn) {
    if (state.favorites.has(id)) {
        state.favorites.delete(id);
        btn?.classList.remove('faved');
        if (btn) btn.querySelector('svg').setAttribute('fill','none');
        if (state.tracks[state.currentIdx]?.id === id) dom.btnFavorite.classList.remove('favorited');
    } else {
        state.favorites.add(id);
        btn?.classList.add('faved');
        if (btn) btn.querySelector('svg').setAttribute('fill','currentColor');
        if (state.tracks[state.currentIdx]?.id === id) dom.btnFavorite.classList.add('favorited');
    }
    saveStorage();
    syncFavoriteToDB(id);
    renderPlaylistNav();
    if (state.favoritesActive) loadFavoritesView();
}

async function syncFavoriteToDB(id) {
    try {
        await fetch('/api/favorites.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
    } catch(e) { /* offline — localStorage already saved it */ }
}

function loadFavoritesView() {
    state.favoritesActive = true;
    state.currentIdx = -1;

    const seen = new Set();
    const tracks = [];
    for (const pl of state.playlists) {
        const src = pl.tracks?.length ? pl.tracks : (pl.demo || []);
        for (const t of src) {
            if (state.favorites.has(t.id) && !seen.has(t.id)) {
                seen.add(t.id);
                tracks.push(t);
            }
        }
    }
    state.tracks = tracks;

    dom.tracklistTitle.textContent = 'Favorites';
    dom.tracklistLoading.classList.add('hidden');
    dom.tracklistCount.textContent = `${tracks.length} track${tracks.length !== 1 ? 's' : ''}`;
    renderTrackList();
    renderPlaylistNav();
}

/* ─────────────────────────────────────────────────────
   15.  UI HELPERS
───────────────────────────────────────────────────── */
function updatePlayPauseUI() {
    dom.iconPlay.classList.toggle('hidden', state.isPlaying);
    dom.iconPause.classList.toggle('hidden', !state.isPlaying);
}

function formatTime(secs) {
    if (!secs || isNaN(secs)) return '0:00';
    const m = Math.floor(secs / 60);
    const s = Math.floor(secs % 60).toString().padStart(2,'0');
    return `${m}:${s}`;
}

/* ─────────────────────────────────────────────────────
   16.  AMBIENT PARTICLES (canvas)
───────────────────────────────────────────────────── */
function initParticles() {
    const canvas = dom.particlesCanvas;
    const ctx    = canvas.getContext('2d');
    let particles = [];
    const COUNT  = 60;

    function resize() {
        canvas.width  = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    function createParticle() {
        return {
            x:   Math.random() * canvas.width,
            y:   Math.random() * canvas.height,
            r:   Math.random() * 2 + .5,
            vx:  (Math.random() - .5) * .3,
            vy:  -(Math.random() * .6 + .1),
            alpha: Math.random() * .5 + .1,
        };
    }

    for (let i = 0; i < COUNT; i++) particles.push(createParticle());

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(29,185,84,${p.alpha * (state.isPlaying ? 1 : .4)})`;
            ctx.fill();
            p.x += p.vx;
            p.y += p.vy;
            if (p.y < -5 || p.x < -5 || p.x > canvas.width + 5) {
                Object.assign(p, createParticle(), { y: canvas.height + 5 });
            }
        });
        requestAnimationFrame(draw);
    }
    draw();
}

/* ─────────────────────────────────────────────────────
   17.  VISUALIZER BARS (canvas, simulated)
───────────────────────────────────────────────────── */
function initVisualizer() {
    const canvas = dom.visualizerCanvas;
    const ctx    = canvas.getContext('2d');
    const BARS   = 60;
    let heights  = new Array(BARS).fill(0);
    let targets  = new Array(BARS).fill(0);

    function resize() {
        canvas.width  = canvas.offsetWidth  * devicePixelRatio;
        canvas.height = canvas.offsetHeight * devicePixelRatio;
        ctx.scale(devicePixelRatio, devicePixelRatio);
    }

    const ro = new ResizeObserver(resize);
    ro.observe(canvas);
    resize();

    function tick() {
        const w = canvas.offsetWidth;
        const h = canvas.offsetHeight;
        ctx.clearRect(0, 0, w, h);

        if (state.isPlaying) {
            targets = targets.map(() => Math.random() * h * .8 + 5);
        } else {
            targets = targets.map(t => t * .9);
        }

        heights = heights.map((cur, i) => cur + (targets[i] - cur) * .1);

        const barW = (w / BARS) - 1;
        heights.forEach((bh, i) => {
            const x = i * (barW + 1);
            const alpha = .15 + (bh / (h || 1)) * .35;
            ctx.fillStyle = `rgba(29,185,84,${alpha})`;
            ctx.beginPath();
            ctx.roundRect
                ? ctx.roundRect(x, h - bh, barW, bh, [2, 2, 0, 0])
                : ctx.rect(x, h - bh, barW, bh);
            ctx.fill();
        });

        requestAnimationFrame(tick);
    }
    tick();
}

/* ─────────────────────────────────────────────────────
   18.  EVENT LISTENERS
───────────────────────────────────────────────────── */
function bindEvents() {

    /* Play / Pause */
    dom.btnPlay.addEventListener('click', togglePlay);

    /* Prev / Next */
    dom.btnPrev.addEventListener('click', playPrev);
    dom.btnNext.addEventListener('click', playNext);

    /* Loop */
    dom.btnLoop.addEventListener('click', cycleLoop);

    /* Shuffle (button) */
    dom.btnShuffle.addEventListener('click', () => setShuffle(!state.isShuffle));

    /* Volume */
    dom.volumeSlider.addEventListener('input', e => setVolume(+e.target.value));
    dom.btnMute.addEventListener('click', toggleMute);

    /* Progress bar – click seek */
    dom.progressWrap.addEventListener('click', seekTo);
    /* Drag seek */
    dom.progressWrap.addEventListener('mousedown', () => { state.seekDragging = true; });
    document.addEventListener('mousemove', e => {
        if (state.seekDragging) seekTo(e);
    });
    document.addEventListener('mouseup', () => { state.seekDragging = false; });

    /* Touch seek */
    dom.progressWrap.addEventListener('touchstart', e => {
        state.seekDragging = true;
        seekTo(e.touches[0]);
    }, { passive: true });
    document.addEventListener('touchmove', e => {
        if (state.seekDragging) seekTo(e.touches[0]);
    }, { passive: true });
    document.addEventListener('touchend', () => { state.seekDragging = false; });

    /* Search */
    dom.searchInput.addEventListener('input', e => {
        state.searchQuery = e.target.value;
        dom.searchClear.classList.toggle('visible', !!e.target.value);
        renderTrackList();
    });
    dom.searchClear.addEventListener('click', () => {
        dom.searchInput.value = '';
        state.searchQuery = '';
        dom.searchClear.classList.remove('visible');
        renderTrackList();
        dom.searchInput.focus();
    });

    /* Playback Mode */
    dom.btnAudioMode.addEventListener('click', () => setPlaybackMode('audio'));
    dom.btnVideoMode.addEventListener('click', () => setPlaybackMode('video'));

    /* Autoplay toggle */
    dom.toggleAutoplay.addEventListener('change', e => {
        state.autoplay = e.target.checked;
        saveStorage();
    });

    /* Shuffle toggle (sidebar) */
    dom.toggleShuffle.addEventListener('change', e => setShuffle(e.target.checked));

    /* Speed */
    dom.speedSelect.addEventListener('change', e => {
        state.speed = parseFloat(e.target.value);
        if (state.ytReady && state.ytPlayer) state.ytPlayer.setPlaybackRate(state.speed);
        saveStorage();
    });

    /* Favorite (main) */
    dom.btnFavorite.addEventListener('click', () => {
        const track = state.tracks[state.currentIdx];
        if (track) toggleFavorite(track.id, null);
    });

    /* Mobile menu */
    dom.menuToggle.addEventListener('click', () => {
        dom.sidebar.classList.add('open');
        dom.sidebarOverlay.classList.add('visible');
    });
    dom.sidebarOverlay.addEventListener('click', () => {
        dom.sidebar.classList.remove('open');
        dom.sidebarOverlay.classList.remove('visible');
    });

    /* Mini player toggle */
    dom.miniToggle.addEventListener('click', () => {
        dom.app.classList.toggle('mini-mode');
    });

    /* Add Playlist modal */
    dom.btnAddPlaylist.addEventListener('click', () => {
        dom.modalOverlay.classList.remove('hidden');
        dom.modalName.focus();
    });
    dom.modalCancel.addEventListener('click', () => {
        dom.modalOverlay.classList.add('hidden');
    });
    dom.modalSave.addEventListener('click', addCustomPlaylist);
    dom.modalOverlay.addEventListener('click', e => {
        if (e.target === dom.modalOverlay) dom.modalOverlay.classList.add('hidden');
    });

    /* Keyboard shortcuts */
    document.addEventListener('keydown', handleKeyboard);

    /* Context menu — right-click on track list items */
    dom.trackList.addEventListener('contextmenu', e => {
        const li = e.target.closest('li[data-idx]');
        if (!li) return;
        showCtxMenu(e, parseInt(li.dataset.idx));
    });

    dom.ctxPlayNext.addEventListener('click', () => {
        queuePlayNext(state.ctxTrackIdx);
        hideCtxMenu();
    });

    /* Close context menu on any click outside, scroll, or Escape */
    document.addEventListener('click', e => {
        if (!dom.ctxMenu.contains(e.target)) hideCtxMenu();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') hideCtxMenu();
    }, true);
    document.addEventListener('scroll', hideCtxMenu, true);

}

function handleKeyboard(e) {
    if (['INPUT','SELECT','TEXTAREA'].includes(e.target.tagName)) return;

    switch (e.code) {
        case 'Space':     e.preventDefault(); togglePlay();                 break;
        case 'ArrowRight':
            if (e.shiftKey) playNext();
            else if (state.ytReady && state.ytPlayer) state.ytPlayer.seekTo((state.ytPlayer.getCurrentTime()||0)+5, true);
            break;
        case 'ArrowLeft':
            if (e.shiftKey) playPrev();
            else if (state.ytReady && state.ytPlayer) state.ytPlayer.seekTo(Math.max(0,(state.ytPlayer.getCurrentTime()||0)-5), true);
            break;
        case 'ArrowUp':   e.preventDefault(); setVolume(state.volume + 5);  break;
        case 'ArrowDown': e.preventDefault(); setVolume(state.volume - 5);  break;
        case 'KeyM':      toggleMute();                                      break;
        case 'KeyL':      cycleLoop();                                       break;
        case 'KeyS':      setShuffle(!state.isShuffle);                     break;
        case 'KeyF':
            if (e.ctrlKey || e.metaKey) { e.preventDefault(); dom.searchInput.focus(); }
            break;
    }
}

/* ─────────────────────────────────────────────────────
   19.  ADD CUSTOM PLAYLIST
───────────────────────────────────────────────────── */
function addCustomPlaylist() {
    const name = dom.modalName.value.trim();
    const api  = dom.modalApi.value.trim();
    if (!name) { dom.modalName.focus(); return; }
    state.playlists.push({ name, api, demo: [], tracks: [], custom: true });
    dom.modalOverlay.classList.add('hidden');
    dom.modalName.value = '';
    dom.modalApi.value  = '';
    renderPlaylistNav();
    loadPlaylist(state.playlists.length - 1);
}

/* ─────────────────────────────────────────────────────
   20.  APPLY SAVED STATE TO UI
───────────────────────────────────────────────────── */
function applySavedState() {
    setVolume(state.volume);
    dom.btnLoop.dataset.loop = state.loopMode;
    dom.btnLoop.classList.toggle('active', state.loopMode !== 'none');
    setShuffle(state.isShuffle);
    dom.toggleAutoplay.checked = state.autoplay;
    if (dom.speedSelect) dom.speedSelect.value = state.speed;
    setPlaybackMode(state.playbackMode);
}


/* ─────────────────────────────────────────────────────
   22.  CONTEXT MENU (Play Next)
───────────────────────────────────────────────────── */
let _toastTimer = null;

function showToast(msg) {
    dom.toast.textContent = msg;
    dom.toast.classList.add('show');
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => dom.toast.classList.remove('show'), 2400);
}

function showCtxMenu(e, realIdx) {
    e.preventDefault();
    state.ctxTrackIdx = realIdx;

    const menu = dom.ctxMenu;
    menu.classList.remove('hidden');

    // Position near cursor, clamped to viewport
    const mw = 180, mh = 48;
    const x = Math.min(e.clientX, window.innerWidth  - mw - 8);
    const y = Math.min(e.clientY, window.innerHeight - mh - 8);
    menu.style.left = x + 'px';
    menu.style.top  = y + 'px';
}

function hideCtxMenu() {
    dom.ctxMenu.classList.add('hidden');
    state.ctxTrackIdx = -1;
}

function reorderTrack(srcIdx, targetIdx) {
    if (srcIdx === targetIdx || srcIdx === -1) return;

    const currentTrack = state.tracks[state.currentIdx]; // keep ref; find it again after splice
    const dragged = state.tracks.splice(srcIdx, 1)[0];

    // After removal, find where the drop target now sits
    const newTarget = srcIdx < targetIdx ? targetIdx - 1 : targetIdx;
    state.tracks.splice(newTarget, 0, dragged);

    // Restore currentIdx by reference — no index math needed
    state.currentIdx = state.tracks.indexOf(currentTrack);

    renderTrackList();
    persistTrackOrder();
}

function persistTrackOrder() {
    const pl = state.playlists[state.activePl];
    if (!pl || pl.custom || !pl.slug) return; // custom playlists live in localStorage only

    fetch('/api/order.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            playlist_slug: pl.slug,
            order: state.tracks.map(t => t.id),
        }),
    }).catch(() => {}); // fire-and-forget; in-memory order is already correct
}

function queuePlayNext(realIdx) {
    const track = state.tracks[realIdx];
    if (!track || realIdx === state.currentIdx) return;

    // Remove from current position
    state.tracks.splice(realIdx, 1);

    // If the removed track was before the current, shift currentIdx down
    if (realIdx < state.currentIdx) state.currentIdx--;

    // Insert immediately after current
    const insertAt = state.currentIdx + 1;
    state.tracks.splice(insertAt, 0, track);

    renderTrackList();
    showToast(`"${truncTitle(track.title, 28)}" plays next`);
}

/* ─────────────────────────────────────────────────────
    23.  BOOT
───────────────────────────────────────────────── */
async function boot() {
    await initPlaylists();
    loadStorage();       // may push custom playlists
    await loadFavoritesFromDB();
    applySavedState();
    renderPlaylistNav();
    bindEvents();
    initParticles();
    initVisualizer();

    const startPl = Math.min(state.activePl, state.playlists.length - 1);
    loadPlaylist(startPl);
}

async function loadFavoritesFromDB() {
    try {
        const res = await fetch('/api/favorites.php');
        if (!res.ok) return;
        const ids = await res.json();
        if (Array.isArray(ids) && ids.length) {
            state.favorites = new Set(ids);
            saveStorage();
        }
    } catch(e) { /* keep localStorage favorites if DB unreachable */ }
}

function resetPlayer() {
    localStorage.removeItem(LS_KEY);
    state.volume = 80;
    state.loopMode = 'none';
    state.isShuffle = false;
    state.autoplay = true;
    state.speed = 1;
    state.favorites.clear();
    state.activePl = 0;
    state.currentIdx = -1;
    state.isPlaying = false;
    boot();
}

document.addEventListener('DOMContentLoaded', boot);