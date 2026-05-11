<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$wpdb->query(
    "DROP TABLE IF EXISTS {$wpdb->prefix}melody_playlists"
);

$wpdb->query(
    "DROP TABLE IF EXISTS {$wpdb->prefix}melody_videos"
);