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

$matchId = filter_input(
    INPUT_POST,
    'match_id',
    FILTER_VALIDATE_INT
);

$teamAScore = trim((string) ($_POST['team_a_score'] ?? ''));
$teamBScore = trim((string) ($_POST['team_b_score'] ?? ''));
$resultNotes = trim((string) ($_POST['result_notes'] ?? ''));

function resultError(int $matchId, string $message): never
{
    header(
        'Location: enter-match-result.php?match_id=' .
        $matchId .
        '&error=' .
        urlencode($message)
    );

    exit;
}

if (!$matchId) {
    exit('Invalid match ID.');
}

/*
|--------------------------------------------------------------------------
| Validate Scores
|--------------------------------------------------------------------------
*/

if ($teamAScore === '' || $teamBScore === '') {
    resultError(
        $matchId,
        'Both team scores are required.'
    );
}

if (!is_numeric($teamAScore) || !is_numeric($teamBScore)) {
    resultError(
        $matchId,
        'Scores must be valid numbers.'
    );
}

$teamAScoreValue = (float) $teamAScore;
$teamBScoreValue = (float) $teamBScore;

if ($teamAScoreValue < 0 || $teamBScoreValue < 0) {
    resultError(
        $matchId,
        'Scores cannot be negative.'
    );
}

if (strlen($resultNotes) > 1000) {
    resultError(
        $matchId,
        'Result notes cannot exceed 1000 characters.'
    );
}

/*
|--------------------------------------------------------------------------
| Get Match
|--------------------------------------------------------------------------
*/

$matchStmt = $pdo->prepare("
    SELECT
        m.match_id,
        m.tournament_id,
        m.team_a_id,
        m.team_b_id,
        m.match_status,

        t.tournament_name,
        t.tournament_status,

        team_a.team_name AS team_a_name,
        team_b.team_name AS team_b_name

    FROM matches m

    INNER JOIN tournaments t
        ON t.tournament_id = m.tournament_id

    INNER JOIN teams team_a
        ON team_a.team_id = m.team_a_id

    INNER JOIN teams team_b
        ON team_b.team_id = m.team_b_id

    WHERE m.match_id = :match_id

    LIMIT 1
");

$matchStmt->execute([
    ':match_id' => $matchId
]);

$match = $matchStmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    exit('Match not found.');
}

if ($match['match_status'] !== 'SCHEDULED') {
    resultError(
        $matchId,
        'This match does not have a SCHEDULED status.'
    );
}

/*
|--------------------------------------------------------------------------
| Determine Winner
|--------------------------------------------------------------------------
*/

$winnerTeamId = null;

if ($teamAScoreValue > $teamBScoreValue) {

    $winnerTeamId = (int) $match['team_a_id'];

} elseif ($teamBScoreValue > $teamAScoreValue) {

    $winnerTeamId = (int) $match['team_b_id'];

}

/*
|--------------------------------------------------------------------------
| Get Current User
|--------------------------------------------------------------------------
*/

$user = currentUser();

if ($user === null) {
    http_response_code(401);
    exit('Unauthorized.');
}

$updatedByUserId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Save Result
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |----------------------------------------------------------------------
    | Insert Match Result
    |----------------------------------------------------------------------
    */

    $insertResultStmt = $pdo->prepare("
        INSERT INTO match_results (
            match_id,
            team_a_score,
            team_b_score,
            winner_team_id,
            result_notes,
            entered_by_user_id
        )
        VALUES (
            :match_id,
            :team_a_score,
            :team_b_score,
            :winner_team_id,
            :result_notes,
            :entered_by_user_id
        )
    ");

    $insertResultStmt->execute([
        ':match_id' => $matchId,
        ':team_a_score' => $teamAScoreValue,
        ':team_b_score' => $teamBScoreValue,
        ':winner_team_id' => $winnerTeamId,
        ':result_notes' => $resultNotes !== ''
            ? $resultNotes
            : null,
        ':entered_by_user_id' => $updatedByUserId
    ]);

    /*
    |----------------------------------------------------------------------
    | Mark Match Completed
    |----------------------------------------------------------------------
    */

    $updateMatchStmt = $pdo->prepare("
        UPDATE matches
        SET
            match_status = 'COMPLETED'
        WHERE match_id = :match_id
    ");

    $updateMatchStmt->execute([
        ':match_id' => $matchId
    ]);

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */

    $winnerName = 'DRAW';

    if ($winnerTeamId === (int) $match['team_a_id']) {
        $winnerName = $match['team_a_name'];
    } elseif ($winnerTeamId === (int) $match['team_b_id']) {
        $winnerName = $match['team_b_name'];
    }

    auditLog(
        'MATCH_RESULT_ENTERED',
        'MATCH',
        $matchId,
        'Result entered for match #' .
        $matchId .
        '. ' .
        $match['team_a_name'] .
        ' ' .
        $teamAScoreValue .
        ' - ' .
        $teamBScoreValue .
        ' ' .
        $match['team_b_name'] .
        '. Winner: ' .
        $winnerName
    );

    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        'Location: match-results.php?tournament_id=' .
        $match['tournament_id'] .
        '&message=result_saved'
    );

    exit;

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Match result save failed: ' .
        $exception->getMessage()
    );

    resultError(
        $matchId,
        'Unable to save the match result. Please try again.'
    );
}