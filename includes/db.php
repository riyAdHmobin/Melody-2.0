<?php
require_once __DIR__ . '/../config.php';

function melody_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    ]);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS melody_playlists (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(255) NOT NULL,
            slug       VARCHAR(255) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS melody_videos (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            playlist_id INT UNSIGNED NOT NULL,
            youtube_id  VARCHAR(100) NOT NULL,
            title       TEXT NOT NULL,
            youtube_url TEXT NOT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (playlist_id) REFERENCES melody_playlists(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS melody_favorites (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            youtube_id VARCHAR(100) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Add position column to melody_videos if not yet present
    $has_position = $pdo->query("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'melody_videos'
          AND COLUMN_NAME  = 'position'
    ")->fetchColumn();
    if (!$has_position) {
        $pdo->exec("ALTER TABLE melody_videos ADD COLUMN position INT UNSIGNED NOT NULL DEFAULT 0");
    }

    return $pdo;
}
