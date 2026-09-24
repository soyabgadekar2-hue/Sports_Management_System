<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../config/database.php';

requireAnyRole([
    'ADMIN',
    'SPORTS_COORDINATOR'
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

requireValidCsrfToken($_POST['csrf_token'] ?? null);

$userId = filter_var(
    $_POST['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($userId === false || $userId <= 0) {
    http_response_code(422);
    exit('Invalid user ID.');
}

try {

    $pdo = db();

    $statement = $pdo->prepare(
        'SELECT
            user_id,
            full_name,
            email,
            account_status
         FROM users
         WHERE user_id = :user_id
         LIMIT 1'
    );

    $statement->execute([
        ':user_id' => $userId
    ]);

    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        exit('Student account not found.');
    }

    if ($user['account_status'] !== 'PENDING') {
        exit('This student account has already been processed.');
    }

    $update = $pdo->prepare(
        'UPDATE users
         SET
            account_status = :account_status,
            approved_by_user_id = :approved_by_user_id,
            approved_at = NOW()
         WHERE user_id = :user_id
           AND account_status = :old_status'
    );

    $currentUserId = (int) currentUser()['id'];

    $update->execute([
        ':account_status' => 'REJECTED',
        ':approved_by_user_id' => $currentUserId,
        ':user_id' => $userId,
        ':old_status' => 'PENDING'
    ]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException(
            'Student rejection could not be completed.'
        );
    }

    auditLog(
        'STUDENT_REJECTED',
        'users',
        $userId,
        'Student registration rejected.',
        json_encode([
            'account_status' => 'PENDING'
        ]),
        json_encode([
            'account_status' => 'REJECTED',
            'rejected_by_user_id' => $currentUserId
        ])
    );

    header(
        'Location: admin-pending-students.php'
    );

    exit;

} catch (Throwable $exception) {

    error_log(
        'Student rejection failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    echo '<h2>Rejection Failed</h2>';

    echo '<p>';
    echo 'Something went wrong while rejecting the student.';
    echo '</p>';

    echo '<p>';
    echo '<a href="admin-pending-students.php">';
    echo 'Back to Pending Students';
    echo '</a>';
    echo '</p>';
}