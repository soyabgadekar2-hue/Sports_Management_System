<?php
declare(strict_types=1);

const SESSION_IDLE_TIMEOUT_SECONDS = 1800;

/**
 * Starts the project's session once, with secure cookie settings.
 */
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_name('college_sports_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        throw new RuntimeException('Unable to start the session.');
    }

    $now = time();
    $lastActivity = $_SESSION['last_activity_at'] ?? null;

    if (is_int($lastActivity) && ($now - $lastActivity) > SESSION_IDLE_TIMEOUT_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    $_SESSION['last_activity_at'] = $now;
}

/**
 * Replaces the current session identifier after login or an authentication change.
 */
function regenerateSessionId(): void
{
    startSecureSession();

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to regenerate the session identifier.');
    }
}

startSecureSession();
