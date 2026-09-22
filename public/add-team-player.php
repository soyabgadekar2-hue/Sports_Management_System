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

$teamId = filter_var(
    $_POST['team_id'] ?? null,
    FILTER_VALIDATE_INT
);

$playerId = filter_var(
    $_POST['player_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $teamId === false
    || $teamId === null
    || $teamId <= 0
) {
    http_response_code(400);
    exit('Invalid team.');
}

if (
    $playerId === false
    || $playerId === null
    || $playerId <= 0
) {
    http_response_code(400);
    exit('Invalid player.');
}

/*
|--------------------------------------------------------------------------
| 1. Get team
|--------------------------------------------------------------------------
*/

$teamStatement = $pdo->prepare(
    'SELECT
        t.team_id,
        t.team_name,
        t.sport_id,
        t.roster_limit,
        t.team_status,
        s.sport_name
     FROM teams t
     INNER JOIN sports s
        ON s.sport_id = t.sport_id
     WHERE t.team_id = :team_id
     LIMIT 1'
);

$teamStatement->execute([
    ':team_id' => $teamId
]);

$team = $teamStatement->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    http_response_code(404);
    exit('Team not found.');
}

if ($team['team_status'] !== 'ACTIVE') {
    http_response_code(400);
    exit('This team is not active.');
}

$teamSportId = (int) $team['sport_id'];

/*
|--------------------------------------------------------------------------
| 2. Get player
|--------------------------------------------------------------------------
*/

$playerStatement = $pdo->prepare(
    'SELECT
        p.player_id,
        p.user_id,
        p.student_id,
        p.player_status,
        u.full_name,
        u.email
     FROM player_profiles p
     INNER JOIN users u
        ON u.user_id = p.user_id
     WHERE p.player_id = :player_id
     LIMIT 1'
);

$playerStatement->execute([
    ':player_id' => $playerId
]);

$player = $playerStatement->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    http_response_code(404);
    exit('Player not found.');
}

if ($player['player_status'] !== 'ACTIVE') {
    http_response_code(400);
    exit('This player is not active.');
}

if ($player['user_id'] === null) {
    http_response_code(400);
    exit('Player account is invalid.');
}

/*
|--------------------------------------------------------------------------
| 3. Check player is enrolled in team's sport
|--------------------------------------------------------------------------
*/

$sportEnrollmentStatement = $pdo->prepare(
    'SELECT
        player_sport_id
     FROM player_sports
     WHERE player_id = :player_id
       AND sport_id = :sport_id
       AND participation_status = :status
     LIMIT 1'
);

$sportEnrollmentStatement->execute([
    ':player_id' => $playerId,
    ':sport_id' => $teamSportId,
    ':status' => 'ACTIVE'
]);

$sportEnrollment = $sportEnrollmentStatement->fetch(
    PDO::FETCH_ASSOC
);

if (!$sportEnrollment) {
    http_response_code(409);

    exit(
        'This player is not enrolled in '
        . $team['sport_name']
        . '.'
    );
}

/*
|--------------------------------------------------------------------------
| 4. Check existing membership in this team
|--------------------------------------------------------------------------
*/

$existingMembershipStatement = $pdo->prepare(
    'SELECT
        team_player_id
     FROM team_players
     WHERE team_id = :team_id
       AND player_id = :player_id
       AND membership_status = :status
     LIMIT 1'
);

$existingMembershipStatement->execute([
    ':team_id' => $teamId,
    ':player_id' => $playerId,
    ':status' => 'ACTIVE'
]);

$existingMembership = $existingMembershipStatement->fetch(
    PDO::FETCH_ASSOC
);

if ($existingMembership) {
    http_response_code(409);

    exit(
        'This player is already a member of this team.'
    );
}

/*
|--------------------------------------------------------------------------
| 5. Check player is not already in another team
|    for the same sport
|--------------------------------------------------------------------------
*/

$otherTeamStatement = $pdo->prepare(
    'SELECT
        tp.team_player_id,
        t.team_id,
        t.team_name
     FROM team_players tp
     INNER JOIN teams t
        ON t.team_id = tp.team_id
     WHERE tp.player_id = :player_id
       AND t.sport_id = :sport_id
       AND tp.membership_status = :status
     LIMIT 1'
);

$otherTeamStatement->execute([
    ':player_id' => $playerId,
    ':sport_id' => $teamSportId,
    ':status' => 'ACTIVE'
]);

$otherTeam = $otherTeamStatement->fetch(PDO::FETCH_ASSOC);

if ($otherTeam) {
    http_response_code(409);

    exit(
        'This player is already a member of another team: '
        . $otherTeam['team_name']
    );
}

/*
|--------------------------------------------------------------------------
| 6. Check roster limit
|--------------------------------------------------------------------------
*/

$countStatement = $pdo->prepare(
    'SELECT COUNT(*)
     FROM team_players
     WHERE team_id = :team_id
       AND membership_status = :status'
);

$countStatement->execute([
    ':team_id' => $teamId,
    ':status' => 'ACTIVE'
]);

$currentPlayers = (int) $countStatement->fetchColumn();

$rosterLimit = $team['roster_limit'] !== null
    ? (int) $team['roster_limit']
    : null;

if (
    $rosterLimit !== null
    && $currentPlayers >= $rosterLimit
) {
    http_response_code(409);

    exit(
        'This team is already full.'
    );
}

/*
|--------------------------------------------------------------------------
| 7. Add player to team
|--------------------------------------------------------------------------
*/

$pdo->beginTransaction();

try {

    $insertStatement = $pdo->prepare(
        'INSERT INTO team_players (
            player_id,
            team_id,
            membership_status,
            assigned_by_user_id,
            notes
         )
         VALUES (
            :player_id,
            :team_id,
            :membership_status,
            :assigned_by_user_id,
            :notes
         )'
    );

    $insertStatement->execute([
        ':player_id' => $playerId,
        ':team_id' => $teamId,
        ':membership_status' => 'ACTIVE',
        ':assigned_by_user_id' => (int) $user['id'],
        ':notes' => 'Player assigned by administrator.'
    ]);

    $pdo->commit();

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Adding player to team failed: '
        . $exception->getMessage()
    );

    http_response_code(500);
    exit('Unable to add player to team.');
}

/*
|--------------------------------------------------------------------------
| 8. Audit log
|--------------------------------------------------------------------------
*/

auditLog(
    'PLAYER_ADDED_TO_TEAM',
    'TEAM',
    $teamId,
    'Player '
    . $player['full_name']
    . ' added to team '
    . $team['team_name']
);

/*
|--------------------------------------------------------------------------
| 9. Redirect
|--------------------------------------------------------------------------
*/

header(
    'Location: manage-team.php?team_id='
    . $teamId
    . '&message=player_added'
);

exit;