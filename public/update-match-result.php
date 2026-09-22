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

$teamAScore = trim(
    (string) ($_POST['team_a_score'] ?? '')
);

$teamBScore = trim(
    (string) ($_POST['team_b_score'] ?? '')
);

$resultNotes = trim(
    (string) ($_POST['result_notes'] ?? '')
);

function updateResultError(
    int $matchId,
    string $message
): never {
    header(
        'Location: edit-match-result.php?match_id=' .
        $matchId .
        '&error=' .
        urlencode($message)
    );

    exit;
}

if (!$matchId) {
    exit('Invalid match ID.');
}

if ($teamAScore === '' || $teamBScore === '') {
    updateResultError(
        $matchId,
        'Both team scores are required.'
    );
}

if (
    !is_numeric($teamAScore) ||
    !is_numeric($teamBScore)
) {
    updateResultError(
        $matchId,
        'Scores must be valid numbers.'
    );
}

$teamAScoreValue = (float) $teamAScore;
$teamBScoreValue = (float) $teamBScore;

if (
    $teamAScoreValue < 0 ||
    $teamBScoreValue < 0
) {
    updateResultError(
        $matchId,
        'Scores cannot be negative.'
    );
}

if (strlen($resultNotes) > 1000) {
    updateResultError(
        $matchId,
        'Result notes cannot exceed 1000 characters.'
    );
}

/*
|--------------------------------------------------------------------------
| Get Current Match
|--------------------------------------------------------------------------
*/

$matchStmt = $pdo->prepare("
    SELECT
        m.match_id,
        m.match_number,
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

if ($match['match_status'] !== 'COMPLETED') {
    updateResultError(
        $matchId,
        'Only completed matches can be corrected.'
    );
}

/*
|--------------------------------------------------------------------------
| Get Existing Result
|--------------------------------------------------------------------------
*/

$resultStmt = $pdo->prepare("
    SELECT
        match_id,
        team_a_score,
        team_b_score,
        winner_team_id,
        result_notes,
        entered_by_user_id,
        updated_by_user_id,
        recorded_at,
        updated_at

    FROM match_results

    WHERE match_id = :match_id

    LIMIT 1
");

$resultStmt->execute([
    ':match_id' => $matchId
]);

$existingResult = $resultStmt->fetch(PDO::FETCH_ASSOC);

if (!$existingResult) {
    updateResultError(
        $matchId,
        'Existing match result was not found.'
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
| Current User
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
| Save Correction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Lock Match Row
    |--------------------------------------------------------------------------
    */

    $lockStmt = $pdo->prepare("
        SELECT
            match_id,
            match_status

        FROM matches

        WHERE match_id = :match_id

        FOR UPDATE
    ");

    $lockStmt->execute([
        ':match_id' => $matchId
    ]);

    $lockedMatch = $lockStmt->fetch(PDO::FETCH_ASSOC);

    if (!$lockedMatch) {
        throw new RuntimeException(
            'Match not found.'
        );
    }

    if ($lockedMatch['match_status'] !== 'COMPLETED') {
        throw new RuntimeException(
            'Match is no longer available for correction.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Existing Result
    |--------------------------------------------------------------------------
    */

    $updateStmt = $pdo->prepare("
        UPDATE match_results

        SET
            team_a_score = :team_a_score,
            team_b_score = :team_b_score,
            winner_team_id = :winner_team_id,
            result_notes = :result_notes,
            updated_by_user_id = :updated_by_user_id

        WHERE match_id = :match_id
    ");

    $updateStmt->execute([
        ':team_a_score' => $teamAScoreValue,

        ':team_b_score' => $teamBScoreValue,

        ':winner_team_id' => $winnerTeamId,

        ':result_notes' =>
            $resultNotes !== ''
                ? $resultNotes
                : null,

        ':updated_by_user_id' =>
            $updatedByUserId,

        ':match_id' => $matchId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Recalculate Tournament Standings
    |--------------------------------------------------------------------------
    |
    | For now we update the standings directly here.
    | This guarantees that correcting the result immediately
    | changes the league table.
    |
    */

    $tournamentId = (int) $match['tournament_id'];

    /*
    |--------------------------------------------------------------------------
    | Get Tournament Points Rules
    |--------------------------------------------------------------------------
    */

    $tournamentStmt = $pdo->prepare("
        SELECT
            points_win,
            points_draw,
            points_loss

        FROM tournaments

        WHERE tournament_id = :tournament_id

        LIMIT 1
    ");

    $tournamentStmt->execute([
        ':tournament_id' => $tournamentId
    ]);

    $tournament = $tournamentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$tournament) {
        throw new RuntimeException(
            'Tournament not found.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get Active Tournament Teams
    |--------------------------------------------------------------------------
    */

    $teamsStmt = $pdo->prepare("
        SELECT
            tt.team_id,
            tm.team_name

        FROM tournament_teams tt

        INNER JOIN teams tm
            ON tm.team_id = tt.team_id

        WHERE tt.tournament_id = :tournament_id
          AND tt.participation_status = 'ACTIVE'
          AND tm.team_status = 'ACTIVE'

        ORDER BY tm.team_name
    ");

    $teamsStmt->execute([
        ':tournament_id' => $tournamentId
    ]);

    $teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Initialize Standings
    |--------------------------------------------------------------------------
    */

    $standings = [];

    foreach ($teams as $team) {

        $teamId = (int) $team['team_id'];

        $standings[$teamId] = [
            'team_id' => $teamId,
            'team_name' => $team['team_name'],

            'matches_played' => 0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,

            'score_for' => 0.00,
            'score_against' => 0.00,
            'score_difference' => 0.00,

            'points' => 0.00
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Get Completed Match Results
    |--------------------------------------------------------------------------
    */

    $resultsStmt = $pdo->prepare("
        SELECT
            m.team_a_id,
            m.team_b_id,
            mr.team_a_score,
            mr.team_b_score

        FROM matches m

        INNER JOIN match_results mr
            ON mr.match_id = m.match_id

        WHERE m.tournament_id = :tournament_id
          AND m.match_status = 'COMPLETED'

        ORDER BY m.match_number ASC
    ");

    $resultsStmt->execute([
        ':tournament_id' => $tournamentId
    ]);

    $results = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Calculate Statistics
    |--------------------------------------------------------------------------
    */

    foreach ($results as $result) {

        $teamAId = (int) $result['team_a_id'];
        $teamBId = (int) $result['team_b_id'];

        $teamAScore = (float) $result['team_a_score'];
        $teamBScore = (float) $result['team_b_score'];

        /*
        |--------------------------------------------------------------------------
        | Team A
        |--------------------------------------------------------------------------
        */

        if (isset($standings[$teamAId])) {

            $standings[$teamAId]['matches_played']++;

            $standings[$teamAId]['score_for'] +=
                $teamAScore;

            $standings[$teamAId]['score_against'] +=
                $teamBScore;

            if ($teamAScore > $teamBScore) {

                $standings[$teamAId]['wins']++;

                $standings[$teamAId]['points'] +=
                    (float) $tournament['points_win'];

            } elseif ($teamAScore < $teamBScore) {

                $standings[$teamAId]['losses']++;

                $standings[$teamAId]['points'] +=
                    (float) $tournament['points_loss'];

            } else {

                $standings[$teamAId]['draws']++;

                $standings[$teamAId]['points'] +=
                    (float) $tournament['points_draw'];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Team B
        |--------------------------------------------------------------------------
        */

        if (isset($standings[$teamBId])) {

            $standings[$teamBId]['matches_played']++;

            $standings[$teamBId]['score_for'] +=
                $teamBScore;

            $standings[$teamBId]['score_against'] +=
                $teamAScore;

            if ($teamBScore > $teamAScore) {

                $standings[$teamBId]['wins']++;

                $standings[$teamBId]['points'] +=
                    (float) $tournament['points_win'];

            } elseif ($teamBScore < $teamAScore) {

                $standings[$teamBId]['losses']++;

                $standings[$teamBId]['points'] +=
                    (float) $tournament['points_loss'];

            } else {

                $standings[$teamBId]['draws']++;

                $standings[$teamBId]['points'] +=
                    (float) $tournament['points_draw'];
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate Score Difference
    |--------------------------------------------------------------------------
    */

    foreach ($standings as &$standing) {

        $standing['score_difference'] =
            $standing['score_for'] -
            $standing['score_against'];
    }

    unset($standing);

    /*
    |--------------------------------------------------------------------------
    | Sort Standings
    |--------------------------------------------------------------------------
    |
    | Current order:
    |
    | 1. Points
    | 2. Score Difference
    | 3. Score For
    | 4. Team Name
    |
    */

    usort(
        $standings,
        function (
            array $a,
            array $b
        ): int {

            if ($a['points'] != $b['points']) {

                return $b['points'] <=>
                    $a['points'];
            }

            if (
                $a['score_difference'] !=
                $b['score_difference']
            ) {

                return $b['score_difference'] <=>
                    $a['score_difference'];
            }

            if (
                $a['score_for'] !=
                $b['score_for']
            ) {

                return $b['score_for'] <=>
                    $a['score_for'];
            }

            return strcmp(
                $a['team_name'],
                $b['team_name']
            );
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Delete Old Standings
    |--------------------------------------------------------------------------
    */

    $deleteStmt = $pdo->prepare("
        DELETE FROM tournament_standings

        WHERE tournament_id = :tournament_id
    ");

    $deleteStmt->execute([
        ':tournament_id' => $tournamentId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Insert New Standings
    |--------------------------------------------------------------------------
    */

    $insertStmt = $pdo->prepare("
        INSERT INTO tournament_standings (
            tournament_id,
            team_id,
            matches_played,
            wins,
            draws,
            losses,
            score_for,
            score_against,
            score_difference,
            points,
            standing_rank,
            last_calculated_at
        )

        VALUES (
            :tournament_id,
            :team_id,
            :matches_played,
            :wins,
            :draws,
            :losses,
            :score_for,
            :score_against,
            :score_difference,
            :points,
            :standing_rank,
            NOW()
        )
    ");

    $rank = 1;

    foreach ($standings as $standing) {

        $insertStmt->execute([
            ':tournament_id' =>
                $tournamentId,

            ':team_id' =>
                $standing['team_id'],

            ':matches_played' =>
                $standing['matches_played'],

            ':wins' =>
                $standing['wins'],

            ':draws' =>
                $standing['draws'],

            ':losses' =>
                $standing['losses'],

            ':score_for' =>
                $standing['score_for'],

            ':score_against' =>
                $standing['score_against'],

            ':score_difference' =>
                $standing['score_difference'],

            ':points' =>
                $standing['points'],

            ':standing_rank' =>
                $rank
        ]);

        $rank++;
    }

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Audit Correction
    |--------------------------------------------------------------------------
    */

    auditLog(
        'MATCH_RESULT_CORRECTED',
        'MATCH',
        $matchId,
        'Corrected result for match #' .
        $match['match_number'] .
        '. ' .
        $match['team_a_name'] .
        ' ' .
        $teamAScoreValue .
        ' - ' .
        $teamBScoreValue .
        ' ' .
        $match['team_b_name'] .
        '. Tournament standings recalculated.',
        json_encode(
            [
                'team_a_score' =>
                    $existingResult['team_a_score'],

                'team_b_score' =>
                    $existingResult['team_b_score'],

                'winner_team_id' =>
                    $existingResult['winner_team_id'],

                'result_notes' =>
                    $existingResult['result_notes']
            ],
            JSON_UNESCAPED_UNICODE
        ),
        json_encode(
            [
                'team_a_score' =>
                    $teamAScoreValue,

                'team_b_score' =>
                    $teamBScoreValue,

                'winner_team_id' =>
                    $winnerTeamId,

                'result_notes' =>
                    $resultNotes !== ''
                        ? $resultNotes
                        : null
            ],
            JSON_UNESCAPED_UNICODE
        )
    );

    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        'Location: match-results.php?tournament_id=' .
        $tournamentId .
        '&message=result_updated'
    );

    exit;

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Match result correction failed: ' .
        $exception->getMessage()
    );

    updateResultError(
        $matchId,
        'Unable to update the match result. Please try again.'
    );
}