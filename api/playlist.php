<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(400);
    echo json_encode(['error' => 'slug required']);
    exit;
}

try {
    $db = melody_db();

    $stmt = $db->prepare("SELECT * FROM melody_playlists WHERE slug = ?");
    $stmt->execute([$slug]);
    $pl = $stmt->fetch();

    if (!$pl) {
        http_response_code(404);
        echo json_encode(['error' => 'Playlist not found']);
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM melody_videos WHERE playlist_id = ? ORDER BY position ASC, id ASC");
    $stmt->execute([$pl->id]);
    $videos = $stmt->fetchAll();

    $response = array_map(fn($v) => [
        'id'        => $v->youtube_id,
        'title'     => $v->title,
        'url'       => $v->youtube_url,
        'localPath' => $v->local_audio_path ?: null,
    ], $videos);

    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
