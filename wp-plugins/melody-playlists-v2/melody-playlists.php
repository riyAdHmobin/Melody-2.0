<?php
/*
Plugin Name: Melody Playlists
Plugin URI: https://example.com
Description: Frontend YouTube Playlist Manager + REST API
Version: 1.0.0
Author: You
*/

if (!defined('ABSPATH')) {
    exit;
}

define('MELODY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MELODY_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once MELODY_PLUGIN_PATH . 'includes/db.php';
require_once MELODY_PLUGIN_PATH . 'includes/youtube.php';
require_once MELODY_PLUGIN_PATH . 'includes/api.php';
require_once MELODY_PLUGIN_PATH . 'includes/auth.php';
require_once MELODY_PLUGIN_PATH . 'includes/shortcode.php';

register_activation_hook(__FILE__, 'melody_create_tables');

add_action('wp_enqueue_scripts', function () {

    wp_enqueue_style(
        'melody-style',
        MELODY_PLUGIN_URL . 'assets/style.css',
        [],
        '1.0.0'
    );
});