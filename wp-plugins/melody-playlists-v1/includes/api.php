<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {

    register_rest_route('melody/v1', '/playlists', [
        'methods' => 'GET',
        'callback' => 'melody_get_playlists',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('melody/v1', '/playlist/(?P<slug>[a-zA-Z0-9-_]+)', [
        'methods' => 'GET',
        'callback' => 'melody_get_playlist',
        'permission_callback' => '__return_true'
    ]);
});

function melody_get_playlists() {

    global $wpdb;

    $playlists_table = $wpdb->prefix . 'melody_playlists';
    $videos_table = $wpdb->prefix . 'melody_videos';

    $playlists = $wpdb->get_results("
        SELECT * FROM $playlists_table
        ORDER BY id DESC
    ");

    $response = [];

    foreach ($playlists as $playlist) {

        $videos = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $videos_table WHERE playlist_id = %d",
                $playlist->id
            )
        );

        $demo = [];

        foreach ($videos as $video) {

            $demo[] = [
                'id' => $video->youtube_id,
                'title' => $video->title,
                'url' => $video->youtube_url
            ];
        }

        $response[] = [
            'name' => $playlist->name,
            'api' => rest_url(
                'melody/v1/playlist/' . $playlist->slug
            ),
            'demo' => $demo
        ];
    }

    return rest_ensure_response($response);
}

function melody_get_playlist($request) {

    global $wpdb;

    $slug = sanitize_text_field($request['slug']);

    $playlists_table = $wpdb->prefix . 'melody_playlists';
    $videos_table = $wpdb->prefix . 'melody_videos';

    $playlist = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $playlists_table WHERE slug = %s",
            $slug
        )
    );

    if (!$playlist) {

        return new WP_Error(
            'playlist_not_found',
            'Playlist not found',
            ['status' => 404]
        );
    }

    $videos = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $videos_table WHERE playlist_id = %d",
            $playlist->id
        )
    );

    $response = [];

    foreach ($videos as $video) {

        $response[] = [
            'id' => $video->youtube_id,
            'title' => $video->title,
            'url' => $video->youtube_url
        ];
    }

    return rest_ensure_response($response);
}