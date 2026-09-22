<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function hasRole(string $role): bool
{
    $user = currentUser();

    if ($user === null) {
        return false;
    }

    return $user['role'] === $role;
}

function requireRole(string $role): void
{
    requireLogin();

    if (!hasRole($role)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function requireAnyRole(array $roles): void
{
    requireLogin();

    $user = currentUser();

    if ($user === null || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
}