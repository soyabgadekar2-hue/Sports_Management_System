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

$teamAId = filter_input(
    INPUT_POST,
    'team_a_id',
    FILTER_VALIDATE_INT
);

$teamBId = filter_input(
    INPUT_POST,
    'team_b_id',
    FILTER_VALIDATE_INT
);

$venueId = filter_input(
    INPUT_POST,
    'venue_id',
    FILTER_VALIDATE_INT
);

$scheduledStart = trim(
    (string) ($_POST['scheduled_start'] ?? '')
);

$scheduledEnd = trim(
    (string) ($_POST['scheduled_end'] ?? '')
);

$notes = trim(
    (string) ($_POST['notes'] ?? '')
);

function scheduleError(
    int $tournamentId,
    string $message
): never {
    header(
        'Location: schedule-match.php?tournament_id=' .
        $tournamentId .
        '&error=' .
        urlencode($message)
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Basic validation
|--------------------------------------------------------------------------
*/

if (!$tournamentId) {
    exit('Invalid tournament ID.');
}

if (!$teamAId || !$teamBId) {
    scheduleError(
        $tournamentId,
        'Please select both teams.'
    );
}

if ($teamAId === $teamBId) {
    scheduleError(
        $tournamentId,
        'A team cannot play against itself.'
    );
}

if (!$venueId) {
    scheduleError(
        $tournamentId,
        'Please select a venue.'
    );
}

if ($scheduledStart === '' || $scheduledEnd === '') {
    scheduleError(
        $tournamentId,
        'Start and end date/time are required.'
    );
}

if ($scheduledEnd <= $scheduledStart) {
    scheduleError(
        $tournamentId,
        'Match end time must be after start time.'
    );
}

if (strlen($notes) > 1000) {
    scheduleError(
        $tournamentId,
        'Notes cannot exceed 1000 characters.'
    );
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
        start_date,
        end_date,
        tournament_format,
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
    scheduleError(
        $tournamentId,
        'Tournament not found.'
    );
}

if ($tournament['tournament_format'] !== 'LEAGUE') {
    scheduleError(
        $tournamentId,
        'Only league tournaments are supported.'
    );
}

if (!in_array(
    $tournament['tournament_status'],
    ['REGISTRATION_OPEN', 'ACTIVE'],
    true
)) {
    scheduleError(
        $tournamentId,
        'Matches can only be scheduled for an open or active tournament.'
    );
}

/*
|--------------------------------------------------------------------------
| Validate date and time
|--------------------------------------------------------------------------
*/

try {

    $startDateTime = new DateTime($scheduledStart);
    $endDateTime = new DateTime($scheduledEnd);

    $tournamentStart = new DateTime(
        $tournament['start_date'] . ' 00:00:00'
    );

    $tournamentEnd = new DateTime(
        $tournament['end_date'] . ' 23:59:59'
    );

} catch (Exception $exception) {

    scheduleError(
        $tournamentId,
        'Invalid date or time.'
    );
}

if (
    $startDateTime < $tournamentStart ||
    $startDateTime > $tournamentEnd
) {
    scheduleError(
        $tournamentId,
        'Match start must be within the tournament dates.'
    );
}

if (
    $endDateTime < $tournamentStart ||
    $endDateTime > $tournamentEnd
) {
    scheduleError(
        $tournamentId,
        'Match end must be within the tournament dates.'
    );
}

/*
|--------------------------------------------------------------------------
| Validate registered teams
|--------------------------------------------------------------------------
*/

$teamStmt = $pdo->prepare("
    SELECT
        t.team_id,
        t.team_name,
        t.sport_id
    FROM teams t
    INNER JOIN tournament_teams tt
        ON tt.team_id = t.team_id
    WHERE tt.tournament_id = :tournament_id
      AND tt.participation_status = 'ACTIVE'
      AND t.team_status = 'ACTIVE'
      AND t.sport_id = :sport_id
      AND t.team_id IN (:team_a_id, :team_b_id)
");

$teamStmt->execute([
    ':tournament_id' => $tournamentId,
    ':sport_id' => $tournament['sport_id'],
    ':team_a_id' => $teamAId,
    ':team_b_id' => $teamBId
]);

$validTeams = $teamStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($validTeams) !== 2) {
    scheduleError(
        $tournamentId,
        'Both selected teams must be active registered teams in this tournament.'
    );
}

/*
|--------------------------------------------------------------------------
| Validate venue
|--------------------------------------------------------------------------
*/

$venueStmt = $pdo->prepare("
    SELECT venue_id
    FROM venues
    WHERE venue_id = :venue_id
      AND availability_status = 'AVAILABLE'
    LIMIT 1
");

$venueStmt->execute([
    ':venue_id' => $venueId
]);

if (!$venueStmt->fetch()) {
    scheduleError(
        $tournamentId,
        'Selected venue is not available.'
    );
}

/*
|--------------------------------------------------------------------------
| Check duplicate fixture
|--------------------------------------------------------------------------
*/

$duplicateStmt = $pdo->prepare("
    SELECT match_id
    FROM matches
    WHERE tournament_id = :tournament_id
      AND (
            (
                team_a_id = :team_a_id
                AND team_b_id = :team_b_id
            )
            OR
            (
                team_a_id = :team_b_id_reverse
                AND team_b_id = :team_a_id_reverse
            )
      )
      AND match_status <> 'CANCELLED'
    LIMIT 1
");

$duplicateStmt->execute([
    ':tournament_id' => $tournamentId,
    ':team_a_id' => $teamAId,
    ':team_b_id' => $teamBId,
    ':team_b_id_reverse' => $teamBId,
    ':team_a_id_reverse' => $teamAId
]);

if ($duplicateStmt->fetch()) {
    scheduleError(
        $tournamentId,
        'A match between these two teams already exists in this tournament.'
    );
}

/*
|--------------------------------------------------------------------------
| Check team schedule conflict
|--------------------------------------------------------------------------
*/

$teamConflictStmt = $pdo->prepare("
    SELECT match_id
    FROM matches
    WHERE tournament_id = :tournament_id
      AND match_status <> 'CANCELLED'
      AND (
            team_a_id IN (:team_a_id, :team_b_id)
            OR
            team_b_id IN (:team_a_id_2, :team_b_id_2)
      )
      AND scheduled_start < :scheduled_end
      AND scheduled_end > :scheduled_start
    LIMIT 1
");

$teamConflictStmt->execute([
    ':tournament_id' => $tournamentId,
    ':team_a_id' => $teamAId,
    ':team_b_id' => $teamBId,
    ':team_a_id_2' => $teamAId,
    ':team_b_id_2' => $teamBId,
    ':scheduled_end' => $scheduledEnd,
    ':scheduled_start' => $scheduledStart
]);

if ($teamConflictStmt->fetch()) {
    scheduleError(
        $tournamentId,
        'One of the selected teams already has a match during this time.'
    );
}

/*
|--------------------------------------------------------------------------
| Check venue schedule conflict
|--------------------------------------------------------------------------
*/

$venueConflictStmt = $pdo->prepare("
    SELECT match_id
    FROM matches
    WHERE venue_id = :venue_id
      AND match_status <> 'CANCELLED'
      AND scheduled_start < :scheduled_end
      AND scheduled_end > :scheduled_start
    LIMIT 1
");

$venueConflictStmt->execute([
    ':venue_id' => $venueId,
    ':scheduled_end' => $scheduledEnd,
    ':scheduled_start' => $scheduledStart
]);

if ($venueConflictStmt->fetch()) {
    scheduleError(
        $tournamentId,
        'The selected venue is already booked during this time.'
    );
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

$createdByUserId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Generate match number
|--------------------------------------------------------------------------
*/

$matchNumberStmt = $pdo->prepare("
    SELECT COALESCE(MAX(match_number), 0) + 1
    FROM matches
    WHERE tournament_id = :tournament_id
");

$matchNumberStmt->execute([
    ':tournament_id' => $tournamentId
]);

$matchNumber = (int) $matchNumberStmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Insert match
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    $insertStmt = $pdo->prepare("
        INSERT INTO matches (
            tournament_id,
            team_a_id,
            team_b_id,
            venue_id,
            match_number,
            scheduled_start,
            scheduled_end,
            match_status,
            notes,
            created_by_user_id
        )
        VALUES (
            :tournament_id,
            :team_a_id,
            :team_b_id,
            :venue_id,
            :match_number,
            :scheduled_start,
            :scheduled_end,
            'SCHEDULED',
            :notes,
            :created_by_user_id
        )
    ");

    $insertStmt->execute([
        ':tournament_id' => $tournamentId,
        ':team_a_id' => $teamAId,
        ':team_b_id' => $teamBId,
        ':venue_id' => $venueId,
        ':match_number' => $matchNumber,
        ':scheduled_start' => $startDateTime->format('Y-m-d H:i:s'),
        ':scheduled_end' => $endDateTime->format('Y-m-d H:i:s'),
        ':notes' => $notes !== '' ? $notes : null,
        ':created_by_user_id' => $createdByUserId
    ]);

    $matchId = (int) $pdo->lastInsertId();

    $pdo->commit();

    auditLog(
        'MATCH_SCHEDULED',
        'MATCH',
        $matchId,
        'Scheduled league match #' .
        $matchNumber .
        ' for tournament ID ' .
        $tournamentId
    );

    header(
        'Location: manage-tournament.php?tournament_id=' .
        $tournamentId .
        '&message=match_scheduled'
    );

    exit;

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Match scheduling failed: ' .
        $exception->getMessage()
    );

    scheduleError(
        $tournamentId,
        'Unable to schedule the match. Please try again.'
    );
}