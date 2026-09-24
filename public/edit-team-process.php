<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

requireValidCsrfToken($_POST['csrf_token'] ?? null);

$pdo = db();

/*
|--------------------------------------------------------------------------
| Get and validate input
|--------------------------------------------------------------------------
*/

$teamId = filter_var(
    $_POST['team_id'] ?? null,
    FILTER_VALIDATE_INT
);

$teamName = trim((string) ($_POST['team_name'] ?? ''));

$teamCategory = strtoupper(
    trim((string) ($_POST['team_category'] ?? ''))
);

$coachIdInput = trim(
    (string) ($_POST['coach_id'] ?? '')
);

$rosterLimit = filter_var(
    $_POST['roster_limit'] ?? null,
    FILTER_VALIDATE_INT
);

$teamStatus = strtoupper(
    trim((string) ($_POST['team_status'] ?? ''))
);

if (
    $teamId === false
    || $teamId === null
    || $teamId <= 0
) {
    http_response_code(400);
    exit('Invalid team ID.');
}

if ($teamName === '') {
    http_response_code(400);
    exit('Team name is required.');
}

if (mb_strlen($teamName) > 120) {
    http_response_code(400);
    exit('Team name cannot exceed 120 characters.');
}

$allowedCategories = [
    'OPEN',
    'MEN',
    'WOMEN',
    'MIXED'
];

if (!in_array($teamCategory, $allowedCategories, true)) {
    http_response_code(400);
    exit('Invalid team category.');
}

if (
    $rosterLimit === false
    || $rosterLimit === null
    || $rosterLimit < 1
    || $rosterLimit > 500
) {
    http_response_code(400);
    exit('Roster limit must be between 1 and 500.');
}

$allowedStatuses = [
    'ACTIVE',
    'INACTIVE'
];

if (!in_array($teamStatus, $allowedStatuses, true)) {
    http_response_code(400);
    exit('Invalid team status.');
}

/*
|--------------------------------------------------------------------------
| Coach
|--------------------------------------------------------------------------
*/

$coachId = null;

if ($coachIdInput !== '') {

    $coachId = filter_var(
        $coachIdInput,
        FILTER_VALIDATE_INT
    );

    if (
        $coachId === false
        || $coachId === null
        || $coachId <= 0
    ) {
        http_response_code(400);
        exit('Invalid coach.');
    }

    $coachId = (int) $coachId;
}

/*
|--------------------------------------------------------------------------
| Get current team
|--------------------------------------------------------------------------
*/

$teamStatement = $pdo->prepare(
    'SELECT
        team_id,
        sport_id,
        team_name,
        team_category,
        roster_limit,
        team_status,
        coach_id
     FROM teams
     WHERE team_id = :team_id
     LIMIT 1'
);

$teamStatement->execute([
    ':team_id' => $teamId
]);

$currentTeam = $teamStatement->fetch(PDO::FETCH_ASSOC);

if (!$currentTeam) {
    http_response_code(404);
    exit('Team not found.');
}

/*
|--------------------------------------------------------------------------
| Check duplicate team name within same sport
|--------------------------------------------------------------------------
*/

$duplicateStatement = $pdo->prepare(
    'SELECT team_id
     FROM teams
     WHERE sport_id = :sport_id
       AND team_name = :team_name
       AND team_id <> :team_id
     LIMIT 1'
);

$duplicateStatement->execute([
    ':sport_id' => $currentTeam['sport_id'],
    ':team_name' => $teamName,
    ':team_id' => $teamId
]);

if ($duplicateStatement->fetch()) {
    http_response_code(409);
    exit('Another team with this name already exists in this sport.');
}

/*
|--------------------------------------------------------------------------
| Validate coach
|--------------------------------------------------------------------------
*/

if ($coachId !== null) {

    $coachStatement = $pdo->prepare(
        'SELECT
            cp.coach_id
         FROM coach_profiles cp
         INNER JOIN users u
            ON u.user_id = cp.user_id
         WHERE cp.coach_id = :coach_id
           AND cp.coach_status = :coach_status
           AND u.account_status = :account_status
         LIMIT 1'
    );

    $coachStatement->execute([
        ':coach_id' => $coachId,
        ':coach_status' => 'ACTIVE',
        ':account_status' => 'APPROVED'
    ]);

    if (!$coachStatement->fetch()) {
        http_response_code(400);
        exit('Selected coach is not active or approved.');
    }
}

/*
|--------------------------------------------------------------------------
| Check current active player count
|--------------------------------------------------------------------------
*/

$countStatement = $pdo->prepare(
    'SELECT COUNT(*)
     FROM team_players
     WHERE team_id = :team_id
       AND membership_status = :membership_status'
);

$countStatement->execute([
    ':team_id' => $teamId,
    ':membership_status' => 'ACTIVE'
]);

$activePlayerCount = (int) $countStatement->fetchColumn();

if ($rosterLimit < $activePlayerCount) {
    http_response_code(409);

    exit(
        'Roster limit cannot be lower than the current number of active players. ' .
        'Current active players: ' .
        $activePlayerCount .
        '.'
    );
}

/*
|--------------------------------------------------------------------------
| Update team
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    $updateStatement = $pdo->prepare(
        'UPDATE teams
         SET
            team_name = :team_name,
            team_category = :team_category,
            coach_id = :coach_id,
            roster_limit = :roster_limit,
            team_status = :team_status
         WHERE team_id = :team_id'
    );

    $updateStatement->execute([
        ':team_name' => $teamName,
        ':team_category' => $teamCategory,
        ':coach_id' => $coachId,
        ':roster_limit' => $rosterLimit,
        ':team_status' => $teamStatus,
        ':team_id' => $teamId
    ]);

    $pdo->commit();

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Team update failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit('Unable to update team.');
}

/*
|--------------------------------------------------------------------------
| Audit log
|--------------------------------------------------------------------------
*/

$previousData = json_encode([
    'team_name' => $currentTeam['team_name'],
    'team_category' => $currentTeam['team_category'],
    'coach_id' => $currentTeam['coach_id'],
    'roster_limit' => $currentTeam['roster_limit'],
    'team_status' => $currentTeam['team_status']
], JSON_UNESCAPED_UNICODE);

$newData = json_encode([
    'team_name' => $teamName,
    'team_category' => $teamCategory,
    'coach_id' => $coachId,
    'roster_limit' => $rosterLimit,
    'team_status' => $teamStatus
], JSON_UNESCAPED_UNICODE);

auditLog(
    'TEAM_UPDATED',
    'TEAM',
    (int) $teamId,
    'Team details were updated.',
    $previousData,
    $newData
);

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header(
    'Location: manage-team.php?team_id=' .
    (int) $teamId .
    '&message=updated'
);

exit;