<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/audit.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

$tournamentId = filter_input(
    INPUT_GET,
    'tournament_id',
    FILTER_VALIDATE_INT
);

if (!$tournamentId) {
    exit('Invalid tournament ID.');
}

/*
|--------------------------------------------------------------------------
| Check Tournament
|--------------------------------------------------------------------------
*/

$tournamentStmt = $pdo->prepare("
    SELECT
        tournament_id,
        tournament_name,
        sport_id,
        points_win,
        points_draw,
        points_loss,
        tournament_status
    FROM tournaments
    WHERE tournament_id = :tournament_id
    LIMIT 1
");

$tournamentStmt->execute([
    ':tournament_id' => $tournamentId
]);

$tournament = $tournamentStmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    exit('Tournament not found.');
}

/*
|--------------------------------------------------------------------------
| Get Registered Teams
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

if (!$teams) {
    exit('No active teams are registered in this tournament.');
}

/*
|--------------------------------------------------------------------------
| Calculate Standings
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |----------------------------------------------------------------------
    | Remove Existing Standings
    |----------------------------------------------------------------------
    */

    $deleteStmt = $pdo->prepare("
        DELETE FROM tournament_standings
        WHERE tournament_id = :tournament_id
    ");

    $deleteStmt->execute([
        ':tournament_id' => $tournamentId
    ]);

    /*
    |----------------------------------------------------------------------
    | Prepare Statistics
    |----------------------------------------------------------------------
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
    |----------------------------------------------------------------------
    | Get Completed Match Results
    |----------------------------------------------------------------------
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
    |----------------------------------------------------------------------
    | Calculate Each Match
    |----------------------------------------------------------------------
    */

    foreach ($results as $result) {

        $teamAId = (int) $result['team_a_id'];
        $teamBId = (int) $result['team_b_id'];

        $teamAScore = (float) $result['team_a_score'];
        $teamBScore = (float) $result['team_b_score'];

        /*
        | Team A
        */

        if (isset($standings[$teamAId])) {

            $standings[$teamAId]['matches_played']++;

            $standings[$teamAId]['score_for'] += $teamAScore;

            $standings[$teamAId]['score_against'] += $teamBScore;

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
        | Team B
        */

        if (isset($standings[$teamBId])) {

            $standings[$teamBId]['matches_played']++;

            $standings[$teamBId]['score_for'] += $teamBScore;

            $standings[$teamBId]['score_against'] += $teamAScore;

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
    | 1. Points
    | 2. Score Difference
    | 3. Score For
    |
    */

    usort(
        $standings,
        function (array $a, array $b): int {

            if ($a['points'] != $b['points']) {
                return $b['points'] <=> $a['points'];
            }

            if ($a['score_difference'] != $b['score_difference']) {
                return $b['score_difference']
                    <=> $a['score_difference'];
            }

            if ($a['score_for'] != $b['score_for']) {
                return $b['score_for']
                    <=> $a['score_for'];
            }

            return strcmp(
                $a['team_name'],
                $b['team_name']
            );
        }
    );

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
            ':tournament_id' => $tournamentId,
            ':team_id' => $standing['team_id'],
            ':matches_played' => $standing['matches_played'],
            ':wins' => $standing['wins'],
            ':draws' => $standing['draws'],
            ':losses' => $standing['losses'],
            ':score_for' => $standing['score_for'],
            ':score_against' => $standing['score_against'],
            ':score_difference' => $standing['score_difference'],
            ':points' => $standing['points'],
            ':standing_rank' => $rank
        ]);

        $rank++;
    }

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */

    auditLog(
        'STANDINGS_CALCULATED',
        'TOURNAMENT',
        $tournamentId,
        'Tournament standings recalculated for tournament ID ' .
        $tournamentId
    );

    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        'Location: tournament-standings.php?tournament_id=' .
        $tournamentId .
        '&message=calculated'
    );

    exit;

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Standings calculation failed: ' .
        $exception->getMessage()
    );

    exit(
        'Unable to calculate tournament standings.'
    );
}