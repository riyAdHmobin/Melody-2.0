<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/youtube.php';

header('Content-Type: application/json');

try {
    $db = melody_db();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id) {
        $stmt = $db->prepare("SELECT * FROM melody_playlists WHERE id = ?");
        $stmt->execute([$id]);
        $playlist = $stmt->fetch();
        if (!$playlist) {
            http_response_code(404);
            echo json_encode(['error' => 'Playlist not found']);
            exit;
        }
        echo json_encode(melody_sync_playlist_channel($db, $playlist));
    } else {
        $playlists = $db->query(
            "SELECT * FROM melody_playlists WHERE channel_id IS NOT NULL AND channel_id != ''"
        )->fetchAll();
        $results = [];
        foreach ($playlists as $pl) {
            $results[$pl->id] = melody_sync_playlist_channel($db, $pl);
        }
        echo json_encode($results);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
