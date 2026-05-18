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

function melody_slugify(string $name): string {
    $slug = mb_strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}
