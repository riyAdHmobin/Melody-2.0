<?php

if (!defined('ABSPATH')) {
    exit;
}

function melody_create_tables() {

    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $playlists_table = $wpdb->prefix . 'melody_playlists';
    $videos_table = $wpdb->prefix . 'melody_videos';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql1 = "CREATE TABLE $playlists_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $sql2 = "CREATE TABLE $videos_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        playlist_id BIGINT UNSIGNED NOT NULL,
        youtube_id VARCHAR(100) NOT NULL,
        title TEXT NOT NULL,
        youtube_url TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    dbDelta($sql1);
    dbDelta($sql2);
}