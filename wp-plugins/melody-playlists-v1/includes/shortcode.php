<?php

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode(
    'melody_playlist_manager',
    'melody_playlist_manager_shortcode'
);

function melody_playlist_manager_shortcode() {

    if (!is_user_logged_in()) {
        return '<p>You must login first.</p>';
    }

    global $wpdb;

    $playlists_table = $wpdb->prefix . 'melody_playlists';
    $videos_table = $wpdb->prefix . 'melody_videos';

    ob_start();

    /*
    |--------------------------------------------------------------------------
    | CREATE PLAYLIST
    |--------------------------------------------------------------------------
    */

    if (isset($_POST['melody_create_playlist'])) {

        $name = sanitize_text_field($_POST['playlist_name']);

        if (!empty($name)) {

            $slug = sanitize_title($name);

            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $playlists_table WHERE slug = %s",
                    $slug
                )
            );

            if (!$exists) {

                $wpdb->insert(
                    $playlists_table,
                    [
                        'name' => $name,
                        'slug' => $slug
                    ]
                );

                echo '<div class="melody-alert success">
                    Playlist created successfully.
                </div>';

            } else {

                echo '<div class="melody-alert error">
                    Playlist already exists.
                </div>';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ADD VIDEO
    |--------------------------------------------------------------------------
    */

    if (isset($_POST['melody_add_video'])) {

        $playlist_id = intval($_POST['playlist_id']);

        $youtube_url = esc_url_raw($_POST['youtube_url']);

        $youtube_id = melody_extract_youtube_id($youtube_url);

        if ($youtube_id) {

            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $videos_table 
                     WHERE youtube_id = %s 
                     AND playlist_id = %d",
                    $youtube_id,
                    $playlist_id
                )
            );

            if (!$exists) {

                $title = melody_get_youtube_title(
                    $youtube_url
                );

                $wpdb->insert(
                    $videos_table,
                    [
                        'playlist_id' => $playlist_id,
                        'youtube_id' => $youtube_id,
                        'title' => $title,
                        'youtube_url' => $youtube_url
                    ]
                );

                echo '<div class="melody-alert success">
                    Video added successfully.
                </div>';

            } else {

                echo '<div class="melody-alert error">
                    Video already exists in playlist.
                </div>';
            }

        } else {

            echo '<div class="melody-alert error">
                Invalid YouTube URL.
            </div>';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE VIDEO
    |--------------------------------------------------------------------------
    */

    if (isset($_GET['melody_delete_video'])) {

        $video_id = intval($_GET['melody_delete_video']);

        $wpdb->delete(
            $videos_table,
            ['id' => $video_id]
        );

        echo '<div class="melody-alert success">
            Video deleted.
        </div>';
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE PLAYLIST
    |--------------------------------------------------------------------------
    */

    if (isset($_GET['melody_delete_playlist'])) {

        $playlist_id = intval($_GET['melody_delete_playlist']);

        $wpdb->delete(
            $playlists_table,
            ['id' => $playlist_id]
        );

        $wpdb->delete(
            $videos_table,
            ['playlist_id' => $playlist_id]
        );

        echo '<div class="melody-alert success">
            Playlist deleted.
        </div>';
    }

    $playlists = $wpdb->get_results("
        SELECT * FROM $playlists_table
        ORDER BY id DESC
    ");

    ?>

    <div class="melody-container">

        <h1>Melody Playlist Manager</h1>

        <div class="melody-box">

            <h2>Create Playlist</h2>

            <form method="POST">

                <input
                    type="text"
                    name="playlist_name"
                    placeholder="Playlist Name"
                    required
                >

                <button
                    type="submit"
                    name="melody_create_playlist"
                >
                    Create Playlist
                </button>

            </form>

        </div>

        <div class="melody-box">

            <h2>Add YouTube Video</h2>

            <form method="POST">

                <select
                    name="playlist_id"
                    required
                >

                    <?php foreach ($playlists as $playlist): ?>

                        <option value="<?= $playlist->id ?>">
                            <?= esc_html($playlist->name) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <input
                    type="text"
                    name="youtube_url"
                    placeholder="YouTube URL"
                    required
                >

                <button
                    type="submit"
                    name="melody_add_video"
                >
                    Add Video
                </button>

            </form>

        </div>

        <div class="melody-box">

            <h2>Existing Playlists</h2>

            <?php if (!$playlists): ?>

                <p>No playlists found.</p>

            <?php endif; ?>

            <?php foreach ($playlists as $playlist): ?>

                <div class="melody-playlist">

                    <div class="melody-playlist-header">

                        <h3>
                            <?= esc_html($playlist->name) ?>
                        </h3>

                        <div>

                            <a
                                class="melody-delete"
                                href="?melody_delete_playlist=<?= $playlist->id ?>"
                                onclick="return confirm('Delete playlist?')"
                            >
                                Delete Playlist
                            </a>

                        </div>

                    </div>

                    <p>
                        API:
                        <code>
                            <?= esc_html(
                                rest_url(
                                    'melody/v1/playlist/' .
                                    $playlist->slug
                                )
                            ) ?>
                        </code>
                    </p>

                    <?php

                    $videos = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM $videos_table 
                             WHERE playlist_id = %d
                             ORDER BY id DESC",
                            $playlist->id
                        )
                    );

                    ?>

                    <?php if (!$videos): ?>

                        <p>No videos yet.</p>

                    <?php else: ?>

                        <ul class="melody-video-list">

                            <?php foreach ($videos as $video): ?>

                                <li>

                                    <img
                                        src="https://img.youtube.com/vi/<?= esc_attr($video->youtube_id) ?>/mqdefault.jpg"
                                        alt=""
                                    >

                                    <div class="melody-video-info">

                                        <strong>
                                            <?= esc_html($video->title) ?>
                                        </strong>

                                        <small>
                                            <?= esc_html($video->youtube_id) ?>
                                        </small>

                                        <div class="melody-actions">

                                            <a
                                                href="<?= esc_url($video->youtube_url) ?>"
                                                target="_blank"
                                            >
                                                Watch
                                            </a>

                                            <a
                                                class="melody-delete"
                                                href="?melody_delete_video=<?= $video->id ?>"
                                                onclick="return confirm('Delete video?')"
                                            >
                                                Delete
                                            </a>

                                        </div>

                                    </div>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

    <?php

    return ob_get_clean();
}