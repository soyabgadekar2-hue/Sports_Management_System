<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin-tournaments.php');
    exit;
}

requireValidCsrfToken($_POST['csrf_token'] ?? null);

$pdo = db();

$tournamentId = filter_input(
    INPUT_POST,
    'tournament_id',
    FILTER_VALIDATE_INT
);

$teamId = filter_input(
    INPUT_POST,
    'team_id',
    FILTER_VALIDATE_INT
);

$remarks = trim(
    (string) ($_POST['remarks'] ?? '')
);

if (!$tournamentId || !$teamId) {

    header(
        'Location: admin-tournaments.php?error=' .
        urlencode('Invalid tournament or team.')
    );

    exit;
}

if (strlen($remarks) > 500) {

    header(
        'Location: manage-tournament.php?tournament_id=' .
        $tournamentId .
        '&error=' .
        urlencode('Remarks cannot exceed 500 characters.')
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Get tournament
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        tournament_id,
        sport_id,
        tournament_status
    FROM tournaments
    WHERE tournament_id = :tournament_id
    LIMIT 1
");

$stmt->execute([
    ':tournament_id' => $tournamentId
]);

$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {

    header(
        'Location: admin-tournaments.php?error=' .
        urlencode('Tournament not found.')
    );

    exit;
}

if ($tournament['tournament_status'] !== 'REGISTRATION_OPEN') {

    header(
        'Location: manage-tournament.php?tournament_id=' .
        $tournamentId .
        '&error=' .
        urlencode('Team registration is not open.')
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Get team
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        team_id,
        sport_id,
        team_name,
        team_status
    FROM teams
    WHERE team_id = :team_id
    LIMIT 1
");

$stmt->execute([
    ':team_id' => $teamId
]);

$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {

    header(
        'Location: manage-tournament.php?tournament_id=' .
        $tournamentId .
        '&error=' .
        urlencode('Team not found.')
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Team must be active
|--------------------------------------------------------------------------
*/

if ($team['team_status'] !== 'ACTIVE') {

    header(
        'Location: manage-tournament.php?tournament_id=' .
        $tournamentId .
        '&error=' .
        urlencode('Only active teams can be registered.')
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Team sport must match tournament sport
|--------------------------------------------------------------------------
*/

if ((int) $team['sport_id'] !== (int) $tournament['sport_id']) {

    header(
        'Location: manage-tournament.php?tournament_id=' .
        $tournamentId .
        '&error=' .
        urlencode('Team sport does not match tournament sport.')
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Check duplicate registration
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        tournament_team_id,
        participation_status
    FROM tournament_teams
    WHERE tournament_id = :tournament_id
      AND team_id = :team_id
    LIMIT 1
");

$stmt->execute([
    ':tournament_id' => $tournamentId,
    ':team_id' => $teamId
]);

$existingRegistration = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingRegistration) {

    if (
        $existingRegistration['participation_status'] === 'ACTIVE'
    ) {

        header(
            'Location: manage-tournament.php?tournament_id=' .
            $tournamentId .
            '&error=' .
            urlencode('This team is already registered.')
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Current user
|--------------------------------------------------------------------------
*/

$user = currentUser();

if ($user === null) {
    http_response_code(401);
    exit('Unauthorized');
}

$userId = (int) $user['id'];

try {

    $pdo->beginTransaction();

    if ($existingRegistration) {

        /*
        |--------------------------------------------------------------
        | Re-activate withdrawn/disqualified registration
        |--------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE tournament_teams
            SET
                participation_status = 'ACTIVE',
                registered_at = NOW(),
                registered_by_user_id = :user_id,
                remarks = :remarks
            WHERE tournament_team_id = :tournament_team_id
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':remarks' => $remarks !== '' ? $remarks : null,
            ':tournament_team_id' =>
                (int) $existingRegistration['tournament_team_id']
        ]);

    } else {

        /*
        |--------------------------------------------------------------
        | New registration
        |--------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO tournament_teams (
                tournament_id,
                team_id,
                participation_status,
                registered_by_user_id,
                remarks
            )
            VALUES (
                :tournament_id,
                :team_id,
                'ACTIVE',
                :registered_by_user_id,
                :remarks
            )
        ");

        $stmt->execute([
            ':tournament_id' => $tournamentId,
            ':team_id' => $teamId,
            ':registered_by_user_id' => $userId,
            ':remarks' => $remarks !== '' ? $remarks : null
        ]);
    }

    $pdo->commit();

    auditLog(
        'TEAM_REGISTERED_TOURNAMENT',
        'TOURNAMENT',
        $tournamentId,
        'Registered team ' . $team['team_name'] .
        ' in tournament.'
    );

    header(
        'Location: manage-tournament.php?tournament_id=' .
        $tournamentId .
        '&message=team_registered'
    );

    exit;

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Tournament team registration failed: ' .
        $exception->getMessage()
    );

    header(
        'Location: manage-tournament.php?tournament_id=' .
        $tournamentId .
        '&error=' .
        urlencode('Unable to register team. Please try again.')
    );

    exit;
}