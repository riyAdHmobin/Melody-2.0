<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/youtube.php';

melody_session_start();

// ── Logout ──────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    melody_logout();
    header('Location: /admin.php');
    exit;
}

// ── Login handler ────────────────────────────────────────────────────────
$login_error = '';
if (!melody_is_logged_in() && isset($_POST['melody_login_submit'])) {
    $ok = melody_login(
        trim($_POST['username'] ?? ''),
        $_POST['password'] ?? ''
    );
    if ($ok) {
        header('Location: /admin.php');
        exit;
    }
    $login_error = 'Invalid username or password.';
}

// ── Show login form if not authenticated ─────────────────────────────────
if (!melody_is_logged_in()):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Melody — Login</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0a0a0f;color:#f0f0f5;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#111118;border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:40px;width:340px}
h2{font-size:1.4rem;margin-bottom:24px;color:#1DB954}
input{width:100%;background:#18181f;border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:10px 14px;color:#f0f0f5;font-size:.95rem;margin-bottom:14px}
input:focus{outline:none;border-color:#1DB954}
button{width:100%;background:#1DB954;color:#000;border:none;border-radius:8px;padding:11px;font-size:1rem;font-weight:600;cursor:pointer}
.error{background:rgba(220,50,50,.15);border:1px solid rgba(220,50,50,.3);border-radius:8px;padding:10px 14px;margin-bottom:16px;color:#f88;font-size:.9rem}
</style>
</head>
<body>
<div class="card">
    <h2>Melody</h2>
    <?php if ($login_error): ?>
        <div class="error"><?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required autofocus/>
        <input type="password" name="password" placeholder="Password" required/>
        <button type="submit" name="melody_login_submit">Sign in</button>
    </form>
</div>
</body>
</html>
<?php
    exit;
endif;

// ── Authenticated: handle mutations ──────────────────────────────────────
$db  = melody_db();
$msg = '';
$err = '';

if (isset($_POST['melody_create_playlist'])) {
    $name = trim($_POST['playlist_name'] ?? '');
    if ($name !== '') {
        $slug = melody_slugify($name);
        $exists = $db->prepare("SELECT id FROM melody_playlists WHERE slug = ?");
        $exists->execute([$slug]);
        if ($exists->fetch()) {
            $err = 'A playlist with that name already exists.';
        } else {
            $db->prepare("INSERT INTO melody_playlists (name,slug) VALUES (?,?)")
               ->execute([$name, $slug]);
            $msg = "Playlist &ldquo;{$name}&rdquo; created.";
        }
    }
}

if (isset($_POST['melody_add_video'])) {
    $playlist_id = (int)($_POST['playlist_id'] ?? 0);
    $url         = trim($_POST['youtube_url'] ?? '');
    $yt_id       = melody_extract_youtube_id($url);
    if (!$yt_id) {
        $err = 'Invalid YouTube URL.';
    } else {
        $dup = $db->prepare("SELECT id FROM melody_videos WHERE youtube_id=? AND playlist_id=?");
        $dup->execute([$yt_id, $playlist_id]);
        if ($dup->fetch()) {
            $err = 'This video is already in that playlist.';
        } else {
            $title = melody_get_youtube_title($url);
            $db->prepare("INSERT INTO melody_videos (playlist_id,youtube_id,title,youtube_url) VALUES (?,?,?,?)")
               ->execute([$playlist_id, $yt_id, $title, $url]);
            $msg = "Video &ldquo;{$title}&rdquo; added.";
        }
    }
}

if (isset($_GET['del_video'])) {
    $db->prepare("DELETE FROM melody_videos WHERE id=?")->execute([(int)$_GET['del_video']]);
    $msg = 'Video deleted.';
}

if (isset($_GET['del_playlist'])) {
    $db->prepare("DELETE FROM melody_playlists WHERE id=?")->execute([(int)$_GET['del_playlist']]);
    $msg = 'Playlist deleted.';
}

$playlists = $db->query("SELECT * FROM melody_playlists ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Melody</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0a0a0f;color:#f0f0f5;padding:24px}
a{color:#1DB954;text-decoration:none}
a:hover{text-decoration:underline}
h1{font-size:1.6rem;color:#1DB954;margin-bottom:4px}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px}
.topbar-right{display:flex;gap:16px;align-items:center}
.back-link{font-size:.9rem;opacity:.7}

.card{background:#111118;border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:28px;margin-bottom:24px}
h2{font-size:1.1rem;font-weight:600;margin-bottom:18px;color:#ccc}
h3{font-size:1rem;font-weight:600;margin-bottom:10px}

.row{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
input[type=text]{background:#18181f;border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:9px 13px;color:#f0f0f5;font-size:.9rem;min-width:220px;flex:1}
input:focus{outline:none;border-color:#1DB954}
select{background:#18181f;border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:9px 13px;color:#f0f0f5;font-size:.9rem;min-width:180px}
select:focus{outline:none;border-color:#1DB954}

.btn{padding:9px 18px;border:none;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer}
.btn-primary{background:#1DB954;color:#000}
.btn-danger{background:rgba(220,50,50,.15);border:1px solid rgba(220,50,50,.25);color:#f88}
.btn-danger:hover{background:rgba(220,50,50,.3)}

.alert{border-radius:8px;padding:10px 16px;margin-bottom:20px;font-size:.9rem}
.alert-ok{background:rgba(29,185,84,.12);border:1px solid rgba(29,185,84,.25);color:#1DB954}
.alert-err{background:rgba(220,50,50,.12);border:1px solid rgba(220,50,50,.25);color:#f88}

.playlist-block{border:1px solid rgba(255,255,255,.07);border-radius:10px;margin-bottom:20px;overflow:hidden}
.pl-head{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:rgba(255,255,255,.03)}
.pl-head h3{margin:0}
.pl-slug{font-size:.8rem;color:#808090;margin-left:6px}
code{background:#18181f;padding:2px 7px;border-radius:4px;font-size:.8rem;color:#aaa}

.video-list{list-style:none;padding:0}
.video-item{display:flex;align-items:center;gap:14px;padding:10px 18px;border-top:1px solid rgba(255,255,255,.04)}
.video-item:hover{background:rgba(255,255,255,.03)}
.thumb{width:64px;height:48px;object-fit:cover;border-radius:6px;flex-shrink:0}
.video-meta{flex:1;min-width:0}
.video-title{font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.video-id{font-size:.78rem;color:#808090;margin-top:2px}
.video-actions{display:flex;gap:10px;align-items:center;flex-shrink:0}

.empty{padding:14px 18px;color:#808090;font-size:.9rem}
@media(max-width:600px){.row{flex-direction:column}.btn{width:100%}}
</style>
</head>
<body>

<div class="topbar">
    <div>
        <h1>Melody</h1>
        <a href="/" class="back-link">← Back to player</a>
    </div>
    <div class="topbar-right">
        <a href="?logout=1">Logout</a>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-ok"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card">
    <h2>Create Playlist</h2>
    <form method="POST">
        <div class="row">
            <input type="text" name="playlist_name" placeholder="Playlist name" required/>
            <button class="btn btn-primary" type="submit" name="melody_create_playlist">Create</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Add YouTube Video</h2>
    <?php if (!$playlists): ?>
        <p style="color:#808090;font-size:.9rem">Create a playlist first.</p>
    <?php else: ?>
    <form method="POST">
        <div class="row">
            <select name="playlist_id" required>
                <?php foreach ($playlists as $pl): ?>
                    <option value="<?= $pl->id ?>"><?= htmlspecialchars($pl->name) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="youtube_url" placeholder="YouTube URL" required/>
            <button class="btn btn-primary" type="submit" name="melody_add_video">Add Video</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Playlists</h2>

    <?php if (!$playlists): ?>
        <p class="empty">No playlists yet.</p>
    <?php endif; ?>

    <?php foreach ($playlists as $pl): ?>
        <div class="playlist-block">
            <div class="pl-head">
                <h3>
                    <?= htmlspecialchars($pl->name) ?>
                    <span class="pl-slug"><?= htmlspecialchars($pl->slug) ?></span>
                </h3>
                <div style="display:flex;gap:10px;align-items:center">
                    <code>/api/playlist.php?slug=<?= htmlspecialchars($pl->slug) ?></code>
                    <a href="?del_playlist=<?= $pl->id ?>"
                       class="btn btn-danger"
                       onclick="return confirm('Delete playlist and all its videos?')">Delete</a>
                </div>
            </div>
            <?php
            $vstmt = $db->prepare("SELECT * FROM melody_videos WHERE playlist_id=? ORDER BY id ASC");
            $vstmt->execute([$pl->id]);
            $videos = $vstmt->fetchAll();
            ?>
            <?php if (!$videos): ?>
                <p class="empty">No videos yet.</p>
            <?php else: ?>
            <ul class="video-list">
                <?php foreach ($videos as $v): ?>
                <li class="video-item">
                    <img class="thumb"
                         src="https://img.youtube.com/vi/<?= htmlspecialchars($v->youtube_id) ?>/mqdefault.jpg"
                         alt="" loading="lazy"/>
                    <div class="video-meta">
                        <div class="video-title"><?= htmlspecialchars($v->title) ?></div>
                        <div class="video-id"><?= htmlspecialchars($v->youtube_id) ?></div>
                    </div>
                    <div class="video-actions">
                        <a href="<?= htmlspecialchars($v->youtube_url) ?>" target="_blank" rel="noopener">Watch</a>
                        <a href="?del_video=<?= $v->id ?>"
                           class="btn btn-danger"
                           onclick="return confirm('Remove this video?')">Delete</a>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>
