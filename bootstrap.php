<?php
require_once __DIR__ . '/content-store.php';
ini_set('session.use_strict_mode', '1');
session_name('admin_' . substr(hash('sha256', __DIR__), 0, 12));
if (!is_dir(CONTENT_STORAGE . '/sessions')) {
    mkdir(CONTENT_STORAGE . '/sessions', 0700, true);
}
session_save_path(CONTENT_STORAGE . '/sessions');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Strict', 'path' => '/']);
if (!session_start()) {
    http_response_code(503);
    exit('Admin sessions are unavailable. Check storage permissions.');
}
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('X-Content-Type-Options: nosniff');
$account = require __DIR__ . '/setup.php';
$account['version'] = hash('sha256', $account['username'] . "\0" . $account['password']);
$error = '';
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);
$csrf = $_SESSION['csrf'] ?? ($_SESSION['csrf'] = bin2hex(random_bytes(32)));
$loggedIn = $account && isset($_SESSION['version'], $_SESSION['last_seen']) && hash_equals($account['version'], $_SESSION['version']) && time() - $_SESSION['last_seen'] < 1800;
if ($loggedIn) {
    $_SESSION['last_seen'] = time();
}
function e($text) { return content_escape((string) $text); }
