<?php

function melody_extract_youtube_id(string $url): string|false {
    preg_match(
        '/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/',
        $url,
        $m
    );
    return $m[1] ?? false;
}

function melody_get_youtube_title(string $url): string {
    $endpoint = 'https://www.youtube.com/oembed?url=' . urlencode($url) . '&format=json';

    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $body = @file_get_contents($endpoint, false, $ctx);
    if ($body === false) return 'Unknown Title';

    $data = json_decode($body, true);
    return $data['title'] ?? 'Unknown Title';
}

function melody_is_youtube_short(string $video_id): bool {
    // Shorts return HTTP 200 on their /shorts/ URL; regular videos get a redirect.
    // On any network failure we return false so the video is not skipped.
    $ctx = stream_context_create(['http' => [
        'method'          => 'HEAD',
        'timeout'         => 5,
        'follow_location' => 0,
    ]]);
    @file_get_contents('https://www.youtube.com/shorts/' . $video_id, false, $ctx);
    $status = $http_response_header[0] ?? '';
    return (bool)preg_match('/^HTTP\/\S+\s+200\b/', $status);
}

function melody_fetch_latest_channel_video(string $channel_id): array|false {
    $feed_url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . urlencode($channel_id);
    $ctx  = stream_context_create(['http' => ['timeout' => 10]]);
    $body = @file_get_contents($feed_url, false, $ctx);
    if ($body === false) return false;

    $feed = @simplexml_load_string($body);
    if (!$feed || !isset($feed->entry[0])) return false;

    // Iterate entries to find the most recent non-Short video
    foreach ($feed->entry as $entry) {
        // entry id is "yt:video:VIDEOID" — grab everything after the last colon
        $entry_id = (string)$entry->id;
        $video_id = substr($entry_id, strrpos($entry_id, ':') + 1);
        if (!$video_id) continue;
        if (melody_is_youtube_short($video_id)) continue;
        return [
            'id'    => $video_id,
            'title' => (string)$entry->title,
            'url'   => 'https://www.youtube.com/watch?v=' . $video_id,
        ];
    }
    return false;
}

function melody_sync_playlist_channel(PDO $db, object $playlist): array {
    if (empty($playlist->channel_id)) {
        return ['status' => 'skipped'];
    }

    $video = melody_fetch_latest_channel_video($playlist->channel_id);
    if (!$video) {
        error_log("Melody sync: failed to fetch feed for playlist {$playlist->id} (channel {$playlist->channel_id})");
        return ['status' => 'error', 'reason' => 'feed fetch failed'];
    }

    if ($video['id'] === $playlist->last_seen_video_id) {
        return ['status' => 'up_to_date'];
    }

    // Insert only if not already in this playlist (manual add may have beaten us)
    $dup = $db->prepare("SELECT id FROM melody_videos WHERE youtube_id = ? AND playlist_id = ?");
    $dup->execute([$video['id'], $playlist->id]);
    if (!$dup->fetch()) {
        $db->prepare("INSERT INTO melody_videos (playlist_id, youtube_id, title, youtube_url) VALUES (?,?,?,?)")
           ->execute([$playlist->id, $video['id'], $video['title'], $video['url']]);
    }

    $db->prepare("UPDATE melody_playlists SET last_seen_video_id = ? WHERE id = ?")
       ->execute([$video['id'], $playlist->id]);

    return ['status' => 'added', 'id' => $video['id'], 'title' => $video['title']];
}

function melody_slugify(string $name): string {
    $slug = mb_strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}
