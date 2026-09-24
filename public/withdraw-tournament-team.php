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

if (!$tournamentId || !$teamId) {
    header('Location: admin-tournaments.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Tournament must be open for registration
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT tournament_status
    FROM tournaments
    WHERE tournament_id = :tournament_id
    LIMIT 1
");

$stmt->execute([
    ':tournament_id' => $tournamentId
]);

$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    exit('Tournament not found.');
}

if ($tournament['tournament_status'] !== 'REGISTRATION_OPEN') {

    header(
        'Location: manage-tournament.php?tournament_id=' .
        $tournamentId .
        '&error=' .
        urlencode('Teams cannot be withdrawn after registration closes.')
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Find registration
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        tt.tournament_team_id,
        tm.team_name
    FROM tournament_teams tt
    INNER JOIN teams tm
        ON tm.team_id = tt.team_id
    WHERE tt.tournament_id = :tournament_id
      AND tt.team_id = :team_id
      AND tt.participation_status = 'ACTIVE'
    LIMIT 1
");

$stmt->execute([
    ':tournament_id' => $tournamentId,
    ':team_id' => $teamId
]);

$registration = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registration) {

    header(
        'Location: manage-tournament.php?tournament_id=' .
        $tournamentId .
        '&error=' .
        urlencode('Active team registration not found.')
    );

    exit;
}

try {

    $stmt = $pdo->prepare("
        UPDATE tournament_teams
        SET participation_status = 'WITHDRAWN'
        WHERE tournament_team_id = :tournament_team_id
    ");

    $stmt->execute([
        ':tournament_team_id' =>
            (int) $registration['tournament_team_id']
    ]);

    auditLog(
        'TEAM_WITHDRAWN_TOURNAMENT',
        'TOURNAMENT',
        $tournamentId,
        'Withdrawn team ' .
        $registration['team_name'] .
        ' from tournament.'
    );

    header(
        'Location: manage-tournament.php?tournament_id=' .
        $tournamentId .
        '&message=team_withdrawn'
    );

    exit;

} catch (Throwable $exception) {

    error_log(
        'Tournament team withdrawal failed: ' .
        $exception->getMessage()
    );

    header(
        'Location: manage-tournament.php?tournament_id=' .
        $tournamentId .
        '&error=' .
        urlencode('Unable to withdraw team.')
    );

    exit;
}