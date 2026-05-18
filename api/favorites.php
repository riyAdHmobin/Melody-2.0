<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $db = melody_db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $rows = $db->query("SELECT youtube_id FROM melody_favorites ORDER BY created_at ASC")->fetchAll();
        echo json_encode(array_column($rows, 'youtube_id'));

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = trim($body['id'] ?? '');

        if (!$id || !preg_match('/^[A-Za-z0-9_-]{6,16}$/', $id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid YouTube ID']);
            exit;
        }

        $exists = $db->prepare("SELECT 1 FROM melody_favorites WHERE youtube_id = ?");
        $exists->execute([$id]);

        if ($exists->fetch()) {
            $db->prepare("DELETE FROM melody_favorites WHERE youtube_id = ?")->execute([$id]);
            echo json_encode(['action' => 'removed', 'id' => $id]);
        } else {
            $db->prepare("INSERT INTO melody_favorites (youtube_id) VALUES (?)")->execute([$id]);
            echo json_encode(['action' => 'added', 'id' => $id]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
