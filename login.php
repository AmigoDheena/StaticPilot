<?php
require __DIR__ . '/bootstrap.php';
if ($loggedIn) { header('Location: ./'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($csrf, $_POST['csrf'])) {
            throw new RuntimeException('Your session expired. Refresh the page and try again.');
        }
            // A shared file makes the limit persist across sessions and browsers.
            $lock = fopen(CONTENT_STORAGE . '/login.lock', 'c+');
            if (!$lock || !flock($lock, LOCK_EX)) {
                throw new RuntimeException('Login is temporarily unavailable.');
            }
            try {
                $attempts = json_decode(stream_get_contents($lock), true) ?: [];
                $key = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
                $attempts = array_filter($attempts, function ($entry) { return $entry['until'] > time(); });
                $entry = $attempts[$key] ?? ['count' => 0, 'until' => time() + 900];
                if ($entry['count'] >= 5) {
                    throw new RuntimeException('Too many attempts. Try again in 15 minutes.');
                }
                if (!is_string($_POST['username'] ?? null) || !is_string($_POST['password'] ?? null) || !hash_equals($account['username'], $_POST['username']) || !hash_equals($account['password'], $_POST['password'])) {
                    $entry['count']++;
                    $attempts[$key] = $entry;
                    rewind($lock);
                    ftruncate($lock, 0);
                    fwrite($lock, json_encode($attempts));
                    throw new RuntimeException('Incorrect username or password.');
                }
                unset($attempts[$key]);
                rewind($lock);
                ftruncate($lock, 0);
                fwrite($lock, json_encode($attempts));
                session_regenerate_id(true);
                $_SESSION['version'] = $account['version'];
                $_SESSION['last_seen'] = time();
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                header('Location: ./');
                exit;
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }

    } catch (Throwable $exception) {
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Login is temporarily unavailable. Please try again.';
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Sign in | Admin</title><meta name="color-scheme" content="light dark"><script src="theme.js"></script><link rel="stylesheet" href="admin.css"></head>
<body class="signin-page"><header class="signin-header"><a href="./" class="brand">StaticPilot<span class="brand-tag">CMS</span></a><div class="theme-switch" role="group" aria-label="Appearance"><button type="button" data-theme-choice="light" aria-pressed="false" title="Light appearance"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2"/></svg><span>Light</span></button><button type="button" data-theme-choice="dark" aria-pressed="false" title="Dark appearance"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 15A9 9 0 0 1 9 4a9 9 0 1 0 11 11Z"/></svg><span>Dark</span></button><button type="button" data-theme-choice="system" aria-pressed="false" title="Use device appearance"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8m-4-4v4"/></svg><span>Auto</span></button></div></header><main><div class="signin-layout"><aside class="signin-story"><span class="story-symbol" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="m5 25 10-19h12L17 25H5Z"/><path d="M11 16h10"/></svg></span><h2>Your website.<br>Your workspace.</h2><p>A clear space to manage the details, stories, and pages that make your website yours.</p><ul><li>Company details &amp; social links</li><li>Services, products &amp; testimonials</li><li>Pages, navigation &amp; custom fields</li></ul><span class="story-footer">Built for the way you manage content.</span></aside><div class="signin-form">
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif ?>
<section class="login"><h1>Welcome back.</h1><p>Sign in to manage your website content.</p><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="login"><label for="username">Username</label><input id="username" name="username" required autocomplete="username" maxlength="80" autofocus><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"><button class="primary login-button">Sign in</button></form><p class="login-note">StaticPilot · Website administration</p></section>

</div></div></main></body></html>

