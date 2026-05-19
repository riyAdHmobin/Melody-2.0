<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $db = melody_db();

    $playlists = $db->query("SELECT * FROM melody_playlists ORDER BY id DESC")->fetchAll();

    $response = [];
    $stmt = $db->prepare("SELECT * FROM melody_videos WHERE playlist_id = ? ORDER BY position ASC, id ASC");

    foreach ($playlists as $pl) {
        $stmt->execute([$pl->id]);
        $videos = $stmt->fetchAll();

        $demo = array_map(fn($v) => [
            'id'        => $v->youtube_id,
            'title'     => $v->title,
            'url'       => $v->youtube_url,
            'localPath' => $v->local_audio_path ?: null,
        ], $videos);

        $response[] = [
            'name' => $pl->name,
            'slug' => $pl->slug,
            'api'  => '/api/playlist.php?slug=' . urlencode($pl->slug),
            'demo' => $demo,
        ];
    }

    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
