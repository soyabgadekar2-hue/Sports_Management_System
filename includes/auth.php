<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'role' => $_SESSION['user_role'],
    ];
}

function loginUser(int $userId, string $role): void
{
    require_once __DIR__ . '/../config/session.php';

    regenerateSessionId();

    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $role;
}

function logoutUser(): void
{
    require_once __DIR__ . '/../config/session.php';

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'] ?? '',
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        http_response_code(401);
        exit('Unauthorized');
    }
}