<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') .
        '">';
}

function verifyCsrfToken(?string $token): bool
{
    if ($token === null || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireValidCsrfToken(?string $token): void
{
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}