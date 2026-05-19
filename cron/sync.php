<?php
// System cron (Electron install):
//   0 */12 * * * php /opt/melody/cron/sync.php >> ~/.config/melody/sync.log 2>&1
// Docker: a dedicated service in docker-compose.yml runs this in a bash loop (every 12 h).

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/youtube.php';

try {
    $db        = melody_db();
    $playlists = $db->query(
        "SELECT * FROM melody_playlists WHERE channel_id IS NOT NULL AND channel_id != ''"
    )->fetchAll();

    if (!$playlists) {
        exit(0);
    }

    foreach ($playlists as $pl) {
        $result = melody_sync_playlist_channel($db, $pl);
        $ts     = date('Y-m-d H:i:s');
        $extra  = match ($result['status']) {
            'added'      => " title=\"{$result['title']}\"",
            'error'      => " reason={$result['reason']}",
            default      => '',
        };
        echo "{$ts} playlist_id={$pl->id} status={$result['status']}{$extra}\n";
    }
} catch (Throwable $e) {
    echo date('Y-m-d H:i:s') . " ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
