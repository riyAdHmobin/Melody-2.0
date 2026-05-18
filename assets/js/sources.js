const DEFAULT_PLAYLIST_SOURCES = [];

async function getPlaylistSources() {
    const sources = [...DEFAULT_PLAYLIST_SOURCES];

    try {
        const res = await fetch('/api/playlists.php');
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const apiPlaylists = await res.json();

        if (Array.isArray(apiPlaylists)) {
            apiPlaylists.forEach(playlist => {
                sources.push({
                    name: playlist.name || 'Untitled',
                    api:  playlist.api  || '',
                    demo: playlist.demo || [],
                });
            });
        }
    } catch (err) {
        console.warn('Failed to fetch playlists from API, using defaults only:', err);
    }

    return sources;
}
