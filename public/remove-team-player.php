<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

requireValidCsrfToken($_POST['csrf_token'] ?? null);

$user = currentUser();
$pdo = db();

$teamPlayerId = filter_var(
    $_POST['team_player_id'] ?? null,
    FILTER_VALIDATE_INT
);

$teamId = filter_var(
    $_POST['team_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $teamPlayerId === false
    || $teamPlayerId === null
    || $teamPlayerId <= 0
) {
    http_response_code(400);
    exit('Invalid team player.');
}

if (
    $teamId === false
    || $teamId === null
    || $teamId <= 0
) {
    http_response_code(400);
    exit('Invalid team.');
}

/*
|--------------------------------------------------------------------------
| 1. Find the active team membership
|--------------------------------------------------------------------------
*/

$membershipStatement = $pdo->prepare(
    'SELECT
        tp.team_player_id,
        tp.team_id,
        tp.player_id,
        tp.membership_status,
        t.team_name,
        u.full_name AS player_name
     FROM team_players tp
     INNER JOIN teams t
        ON t.team_id = tp.team_id
     INNER JOIN player_profiles p
        ON p.player_id = tp.player_id
     INNER JOIN users u
        ON u.user_id = p.user_id
     WHERE tp.team_player_id = :team_player_id
       AND tp.team_id = :team_id
     LIMIT 1'
);

$membershipStatement->execute([
    ':team_player_id' => $teamPlayerId,
    ':team_id' => $teamId
]);

$membership = $membershipStatement->fetch(PDO::FETCH_ASSOC);

if (!$membership) {
    http_response_code(404);
    exit('Team membership not found.');
}

if ($membership['membership_status'] !== 'ACTIVE') {
    http_response_code(400);
    exit('This player is not an active member of the team.');
}

/*
|--------------------------------------------------------------------------
| 2. Mark membership as REMOVED
|--------------------------------------------------------------------------
|
| We do NOT delete the database record.
| This preserves team history.
|--------------------------------------------------------------------------
*/

$pdo->beginTransaction();

try {

    $updateStatement = $pdo->prepare(
        'UPDATE team_players
         SET
            membership_status = :membership_status,
            left_at = NOW(),
            notes = :notes
         WHERE team_player_id = :team_player_id
           AND team_id = :team_id
           AND membership_status = :current_status'
    );

    $updateStatement->execute([
        ':membership_status' => 'REMOVED',
        ':notes' => 'Player removed from team by administrator.',
        ':team_player_id' => $teamPlayerId,
        ':team_id' => $teamId,
        ':current_status' => 'ACTIVE'
    ]);

    if ($updateStatement->rowCount() !== 1) {
        throw new RuntimeException(
            'Team membership could not be updated.'
        );
    }

    $pdo->commit();

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Removing player from team failed: '
        . $exception->getMessage()
    );

    http_response_code(500);
    exit('Unable to remove player from team.');
}

/*
|--------------------------------------------------------------------------
| 3. Audit log
|--------------------------------------------------------------------------
*/

auditLog(
    'PLAYER_REMOVED_FROM_TEAM',
    'TEAM',
    $teamId,
    'Player '
    . $membership['player_name']
    . ' removed from team '
    . $membership['team_name']
);

/*
|--------------------------------------------------------------------------
| 4. Redirect
|--------------------------------------------------------------------------
*/

header(
    'Location: manage-team.php?team_id='
    . $teamId
    . '&message=player_removed'
);

exit;