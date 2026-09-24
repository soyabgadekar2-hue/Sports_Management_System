<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit.php';

requireRole('PLAYER');

/*
|--------------------------------------------------------------------------
| Only POST requests are allowed
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/*
|--------------------------------------------------------------------------
| CSRF protection
|--------------------------------------------------------------------------
*/

requireValidCsrfToken($_POST['csrf_token'] ?? null);

$user = currentUser();

$pdo = db();

/*
|--------------------------------------------------------------------------
| Validate team ID
|--------------------------------------------------------------------------
*/

$teamId = filter_var(
    $_POST['team_id'] ?? null,
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

/*
|--------------------------------------------------------------------------
| Get player profile
|--------------------------------------------------------------------------
*/

$playerStatement = $pdo->prepare(
    'SELECT
        player_id,
        player_status
     FROM player_profiles
     WHERE user_id = :user_id
     LIMIT 1'
);

$playerStatement->execute([
    ':user_id' => (int) $user['id']
]);

$player = $playerStatement->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    http_response_code(404);
    exit('Player profile not found.');
}

/*
|--------------------------------------------------------------------------
| Player must be active
|--------------------------------------------------------------------------
*/

if ($player['player_status'] !== 'ACTIVE') {
    http_response_code(403);
    exit('Your player account is not active.');
}

$playerId = (int) $player['player_id'];

/*
|--------------------------------------------------------------------------
| Get team
|--------------------------------------------------------------------------
*/

$teamStatement = $pdo->prepare(
    'SELECT
        t.team_id,
        t.sport_id,
        t.team_name,
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

/*
|--------------------------------------------------------------------------
| Team must be active
|--------------------------------------------------------------------------
*/

if ($team['team_status'] !== 'ACTIVE') {
    http_response_code(400);
    exit('This team is not active.');
}

$sportId = (int) $team['sport_id'];

/*
|--------------------------------------------------------------------------
| Player must be enrolled in the team's sport
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
    ':sport_id' => $sportId,
    ':status' => 'ACTIVE'
]);

$sportEnrollment = $sportEnrollmentStatement->fetch(
    PDO::FETCH_ASSOC
);

if (!$sportEnrollment) {

    header(
        'Location: player-teams.php?error=not_enrolled'
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Check whether player is already in this exact team
|--------------------------------------------------------------------------
*/

$existingMembershipStatement = $pdo->prepare(
    'SELECT
        team_player_id
     FROM team_players
     WHERE player_id = :player_id
       AND team_id = :team_id
       AND membership_status = :status
     LIMIT 1'
);

$existingMembershipStatement->execute([
    ':player_id' => $playerId,
    ':team_id' => $teamId,
    ':status' => 'ACTIVE'
]);

$existingMembership =
    $existingMembershipStatement->fetch(PDO::FETCH_ASSOC);

if ($existingMembership) {

    header(
        'Location: player-teams.php?message=already_member'
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Check whether player already has another active team
| in the same sport
|--------------------------------------------------------------------------
*/

$otherTeamStatement = $pdo->prepare(
    'SELECT
        tp.team_player_id,
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
    ':sport_id' => $sportId,
    ':status' => 'ACTIVE'
]);

$otherTeam =
    $otherTeamStatement->fetch(PDO::FETCH_ASSOC);

if ($otherTeam) {

    http_response_code(409);

    exit(
        'You are already a member of the team: '
        . $otherTeam['team_name']
    );
}

/*
|--------------------------------------------------------------------------
| Check roster capacity
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

$currentPlayers =
    (int) $countStatement->fetchColumn();

$rosterLimit =
    $team['roster_limit'] !== null
        ? (int) $team['roster_limit']
        : null;

if (
    $rosterLimit !== null
    && $currentPlayers >= $rosterLimit
) {
    http_response_code(409);
    exit('This team is already full.');
}

/*
|--------------------------------------------------------------------------
| Add player to team
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
        ':player_id' =>
            $playerId,

        ':team_id' =>
            $teamId,

        ':membership_status' =>
            'ACTIVE',

        ':assigned_by_user_id' =>
            (int) $user['id'],

        ':notes' =>
            'Player joined team.'
    ]);

    $pdo->commit();

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Player team join failed: '
        . $exception->getMessage()
    );

    http_response_code(500);

    exit('Unable to join team.');
}

/*
|--------------------------------------------------------------------------
| Audit log
|--------------------------------------------------------------------------
*/

auditLog(
    'PLAYER_JOINED_TEAM',
    'TEAM',
    $teamId,
    'Player joined team: ' . $team['team_name']
);

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header(
    'Location: player-teams.php?message=joined'
);

exit;