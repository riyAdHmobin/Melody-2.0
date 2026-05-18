<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$slug  = trim($body['playlist_slug'] ?? '');
$order = $body['order'] ?? [];

if ($slug === '' || !is_array($order) || count($order) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'playlist_slug and order required']);
    exit;
}

try {
    $db = melody_db();

    $stmt = $db->prepare("SELECT id FROM melody_playlists WHERE slug = ?");
    $stmt->execute([$slug]);
    $pl = $stmt->fetch();

    if (!$pl) {
        http_response_code(404);
        echo json_encode(['error' => 'Playlist not found']);
        exit;
    }

    $upd = $db->prepare(
        "UPDATE melody_videos SET position = ? WHERE playlist_id = ? AND youtube_id = ?"
    );

    $db->beginTransaction();
    foreach ($order as $pos => $ytId) {
        $upd->execute([(int)$pos, $pl->id, (string)$ytId]);
    }
    $db->commit();

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
