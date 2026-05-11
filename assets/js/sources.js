// Disabled: Using API playlists only
/*
const DEFAULT_PLAYLIST_SOURCES = [
    {
        name: 'Rock',
        api: '',
        demo: [
            { id: 'oQDm5DtIUQw', title: 'Keh de banjaare', url: 'https://www.youtube.com/watch?v=oQDm5DtIUQw&list=RD_ofs9Kfh4AQ&index=7' },
            { id: 'p4pHCMOJbXo', title: 'Epic Guitar Solo – Classic Rock Vibes', url: 'https://www.youtube.com/watch?v=p4pHCMOJbXo' },
            { id: 'dQw4w9WgXcQ', title: 'Never Gonna Give You Up – Rick Astley', url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' },
            { id: 'hTWKbfoikeg', title: 'Smells Like Teen Spirit – Nirvana', url: 'https://www.youtube.com/watch?v=hTWKbfoikeg' },
        ]
    }
];
*/
const DEFAULT_PLAYLIST_SOURCES = [];

/**
 * Fetch playlists from the API endpoint and merge with default sources
 * @returns {Promise<Array>} Combined array of playlists (defaults + API data)
 */
async function getPlaylistSources() {
    const sources = [...DEFAULT_PLAYLIST_SOURCES];

    try {
        const res = await fetch('https://postonce.dev-polygontech.xyz/wp-json/melody/v1/playlists');
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const apiPlaylists = await res.json();

        // Merge API playlists with defaults
        if (Array.isArray(apiPlaylists)) {
            apiPlaylists.forEach(playlist => {
                sources.push({
                    name: playlist.name || 'Untitled',
                    api: playlist.api || '',
                    demo: playlist.demo || [],
                });
            });
        }
    } catch (err) {
        console.warn('Failed to fetch playlists from API, using defaults only:', err);
    }

    return sources;
}

