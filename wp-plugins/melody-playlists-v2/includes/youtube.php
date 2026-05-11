<?php

if (!defined('ABSPATH')) {
    exit;
}

function melody_extract_youtube_id($url) {

    preg_match(
        '/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/',
        $url,
        $matches
    );

    return $matches[1] ?? false;
}

function melody_get_youtube_title($url) {

    $endpoint = 'https://www.youtube.com/oembed?url=' .
        urlencode($url) .
        '&format=json';

    $response = wp_remote_get($endpoint);

    if (is_wp_error($response)) {
        return 'Unknown Title';
    }

    $body = json_decode(
        wp_remote_retrieve_body($response),
        true
    );

    return $body['title'] ?? 'Unknown Title';
}