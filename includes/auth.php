<?php
require_once __DIR__ . '/../config.php';

function melody_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function melody_is_logged_in(): bool {
    melody_session_start();
    return !empty($_SESSION['melody_logged_in']);
}

function melody_login(string $username, string $password): bool {
    melody_session_start();
    if ($username !== MELODY_ADMIN_USER) return false;
    if (!password_verify($password, MELODY_ADMIN_PASS_HASH)) return false;
    $_SESSION['melody_logged_in'] = true;
    return true;
}

function melody_logout(): void {
    melody_session_start();
    unset($_SESSION['melody_logged_in']);
    session_destroy();
}

function melody_require_login(): void {
    if (!melody_is_logged_in()) {
        header('Location: /admin.php');
        exit;
    }
}
