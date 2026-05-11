<?php

if (!defined('ABSPATH')) exit;

/*
|--------------------------------------------------------------------------
| HARDCODED LOGIN (SECURE HASHED PASSWORD)
|--------------------------------------------------------------------------
*/

define('MELODY_ADMIN_USER', 'riyadhmobin');

define('MELODY_ADMIN_PASS_HASH', '$2a$12$FuoiReyW3dWO5hErkjPMBO9wQy3oWjQ9oruY5o5KS9EkbldtaUNv6');

/*
|--------------------------------------------------------------------------
| SESSION INIT
|--------------------------------------------------------------------------
*/

add_action('init', function () {
    if (!session_id()) {
        session_start();
    }
});

/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

function melody_is_logged_in() {
    return !empty($_SESSION['melody_logged_in']);
}

/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

function melody_login($username, $password) {

    if ($username !== MELODY_ADMIN_USER) return false;

    if (!password_verify($password, MELODY_ADMIN_PASS_HASH)) return false;

    $_SESSION['melody_logged_in'] = true;

    return true;
}

/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

function melody_logout() {
    unset($_SESSION['melody_logged_in']);
}