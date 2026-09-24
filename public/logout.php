<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';

if (isLoggedIn()) {
    auditLog(
        'LOGOUT',
        'users',
        (int) currentUser()['id'],
        'User logged out.'
    );
}

logoutUser();

header('Location: login.php');
exit;