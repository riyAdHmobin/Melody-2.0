<?php
header('Content-Type: application/json');
set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$input      = json_decode(file_get_contents('php://input'), true);
$youtube_id = trim($input['youtube_id'] ?? '');

if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $youtube_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid YouTube ID']);
    exit;
}

$downloads_dir = __DIR__ . '/../downloads';
if (!is_dir($downloads_dir)) {
    mkdir($downloads_dir, 0755, true);
}

$filename    = $youtube_id . '.mp3';
$output_path = $downloads_dir . '/' . $filename;

// Already on disk — just ensure DB is synced and return
if (file_exists($output_path)) {
    melody_db()->prepare("UPDATE melody_videos SET local_audio_path = ? WHERE youtube_id = ?")
               ->execute([$filename, $youtube_id]);
    echo json_encode(['status' => 'exists', 'path' => '/downloads/' . $filename]);
    exit;
}

// Check yt-dlp is available
exec('which yt-dlp 2>/dev/null', $which, $which_code);
if ($which_code !== 0) {
    echo json_encode(['status' => 'error', 'message' => 'yt-dlp not found — install it with: pip3 install yt-dlp']);
    exit;
}

$url = 'https://www.youtube.com/watch?v=' . $youtube_id;
$cmd = sprintf(
    'yt-dlp -x --audio-format mp3 --audio-quality 0 --no-playlist -o %s %s 2>&1',
    escapeshellarg($output_path),
    escapeshellarg($url)
);

exec($cmd, $out_lines, $exit_code);

if ($exit_code !== 0 || !file_exists($output_path)) {
    $detail = implode("\n", array_slice($out_lines, -5));
    echo json_encode(['status' => 'error', 'message' => 'Download failed', 'detail' => $detail]);
    exit;
}

melody_db()->prepare("UPDATE melody_videos SET local_audio_path = ? WHERE youtube_id = ?")
           ->execute([$filename, $youtube_id]);

echo json_encode(['status' => 'success', 'path' => '/downloads/' . $filename]);
