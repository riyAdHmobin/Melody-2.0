<?php
// ─── Database ───────────────────────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'melody');
define('DB_USER', getenv('DB_USER') ?: 'melody');
define('DB_PASS', getenv('DB_PASS') ?: 'melody');

// ─── Admin credentials ──────────────────────────────────
// Default: admin / melody  — change MELODY_ADMIN_PASS_HASH for production.
// Generate a new hash:  php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
define('MELODY_ADMIN_USER', getenv('MELODY_ADMIN_USER') ?: 'admin');
define('MELODY_ADMIN_PASS_HASH',
    getenv('MELODY_ADMIN_PASS_HASH') ?:
    '$2y$10$euyhpUvnalTxU6JfKkybFOHSsviY97SRLkivkENlmRdVuAloTBaky'  // melody
);
