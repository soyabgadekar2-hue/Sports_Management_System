<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit.php';

startSecureSession();

requireLogin();
requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/*
|--------------------------------------------------------------------------
| CSRF protection
|--------------------------------------------------------------------------
*/
requireValidCsrfToken($_POST['csrf_token'] ?? '');

/*
|--------------------------------------------------------------------------
| Validate match ID
|--------------------------------------------------------------------------
*/
$matchId = filter_input(
    INPUT_POST,
    'match_id',
    FILTER_VALIDATE_INT
);

if (!$matchId || $matchId <= 0) {
    http_response_code(400);
    exit('Invalid match ID.');
}

/*
|--------------------------------------------------------------------------
| Get logged-in user
|--------------------------------------------------------------------------
*/
$recordedByUserId = $_SESSION['user_id'] ?? null;

if (!$recordedByUserId || !is_numeric($recordedByUserId)) {
    http_response_code(401);
    exit('Unable to identify the logged-in user.');
}

$recordedByUserId = (int) $recordedByUserId;

if ($recordedByUserId <= 0) {
    http_response_code(401);
    exit('Invalid logged-in user.');
}

/*
|--------------------------------------------------------------------------
| Get selected player IDs
|--------------------------------------------------------------------------
*/
$playerIds = $_POST['player_ids'] ?? [];

if (!is_array($playerIds)) {
    $playerIds = [];
}

/*
|--------------------------------------------------------------------------
| Normalize player IDs
|--------------------------------------------------------------------------
*/
$playerIds = array_map(
    static function ($id): int {
        return (int) $id;
    },
    $playerIds
);

$playerIds = array_values(
    array_unique(
        array_filter(
            $playerIds,
            static function (int $id): bool {
                return $id > 0;
            }
        )
    )
);

$pdo = db();

try {

    /*
    |--------------------------------------------------------------------------
    | Verify logged-in user exists
    |--------------------------------------------------------------------------
    */
    $userStmt = $pdo->prepare(
        "
        SELECT
            user_id,
            full_name,
            role_id,
            account_status
        FROM users
        WHERE user_id = :user_id
        LIMIT 1
        "
    );

    $userStmt->execute([
        ':user_id' => $recordedByUserId
    ]);

    $recordingUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$recordingUser) {
        throw new RuntimeException(
            'The logged-in user does not exist in the users table.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get match information
    |--------------------------------------------------------------------------
    */
    $matchStmt = $pdo->prepare(
        "
        SELECT
            m.match_id,
            m.tournament_id,
            m.team_a_id,
            m.team_b_id,
            m.match_status,
            ta.team_name AS team_a_name,
            tb.team_name AS team_b_name
        FROM matches m

        INNER JOIN teams ta
            ON ta.team_id = m.team_a_id

        INNER JOIN teams tb
            ON tb.team_id = m.team_b_id

        WHERE m.match_id = :match_id

        LIMIT 1
        "
    );

    $matchStmt->execute([
        ':match_id' => $matchId
    ]);

    $match = $matchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$match) {
        throw new RuntimeException(
            'Match not found.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Participation should only be recorded for completed matches
    |--------------------------------------------------------------------------
    */
    if ($match['match_status'] !== 'COMPLETED') {
        throw new RuntimeException(
            'Player participation can only be recorded for a completed match.'
        );
    }

    $teamAId = (int) $match['team_a_id'];
    $teamBId = (int) $match['team_b_id'];

    /*
    |--------------------------------------------------------------------------
    | Find valid players
    |--------------------------------------------------------------------------
    */
    $validPlayers = [];

    $playersStmt = $pdo->prepare(
        "
        SELECT
            pp.player_id,
            pp.user_id,
            u.full_name,
            tp.team_id

        FROM player_profiles pp

        INNER JOIN users u
            ON u.user_id = pp.user_id

        INNER JOIN team_players tp
            ON tp.player_id = pp.player_id

        WHERE
            u.account_status = 'APPROVED'

            AND tp.membership_status = 'ACTIVE'

            AND tp.left_at IS NULL

            AND tp.team_id IN (:team_a_id, :team_b_id)
        "
    );

    $playersStmt->execute([
        ':team_a_id' => $teamAId,
        ':team_b_id' => $teamBId
    ]);

    while ($row = $playersStmt->fetch(PDO::FETCH_ASSOC)) {

        $playerId = (int) $row['player_id'];
        $teamId = (int) $row['team_id'];

        $validPlayers[$playerId] = [
            'player_id' => $playerId,
            'user_id' => (int) $row['user_id'],
            'full_name' => $row['full_name'],
            'team_id' => $teamId
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Validate every selected player
    |--------------------------------------------------------------------------
    */
    foreach ($playerIds as $playerId) {

        if (!isset($validPlayers[$playerId])) {
            throw new RuntimeException(
                "Player ID {$playerId} is not a valid active player of this match."
            );
        }

        $playerTeamId = $validPlayers[$playerId]['team_id'];

        if (
            $playerTeamId !== $teamAId &&
            $playerTeamId !== $teamBId
        ) {
            throw new RuntimeException(
                "Player ID {$playerId} does not belong to either team in this match."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save participation
    |--------------------------------------------------------------------------
    */
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Remove previous participation records
    |--------------------------------------------------------------------------
    */
    $deleteStmt = $pdo->prepare(
        "
        DELETE FROM player_match_participation
        WHERE match_id = :match_id
        "
    );

    $deleteStmt->execute([
        ':match_id' => $matchId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Insert new participation records
    |--------------------------------------------------------------------------
    */
    $insertStmt = $pdo->prepare(
        "
        INSERT INTO player_match_participation
        (
            match_id,
            player_id,
            team_id,
            recorded_by_user_id
        )
        VALUES
        (
            :match_id,
            :player_id,
            :team_id,
            :recorded_by_user_id
        )
        "
    );

    foreach ($playerIds as $playerId) {

        $teamId = $validPlayers[$playerId]['team_id'];

        $insertStmt->execute([
            ':match_id' => $matchId,
            ':player_id' => $playerId,
            ':team_id' => $teamId,
            ':recorded_by_user_id' => $recordedByUserId
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Commit transaction
    |--------------------------------------------------------------------------
    */
    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    */
    $selectedPlayerDetails = [];

    foreach ($playerIds as $playerId) {

        $selectedPlayerDetails[] = [
            'player_id' => $playerId,
            'player_name' => $validPlayers[$playerId]['full_name'],
            'team_id' => $validPlayers[$playerId]['team_id']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Convert audit data array to JSON string
    |--------------------------------------------------------------------------
    */
    $auditNewData = json_encode(
        [
            'match_id' => $matchId,
            'recorded_by_user_id' => $recordedByUserId,
            'players' => $selectedPlayerDetails
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($auditNewData === false) {
        $auditNewData = '{}';
    }

    auditLog(
        'MATCH_PLAYER_PARTICIPATION_SAVED',
        'MATCH',
        $matchId,
        'Saved player participation for match #' . $matchId,
        null,
        $auditNewData
    );

    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */
    header(
        'Location: match-players.php?match_id=' .
        urlencode((string) $matchId) .
        '&message=participation_saved'
    );

    exit;

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback transaction if necessary
    |--------------------------------------------------------------------------
    */
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
    |--------------------------------------------------------------------------
    | Development error output
    |--------------------------------------------------------------------------
    */
    http_response_code(500);

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Unable to save player participation</title>';
    echo '</head>';
    echo '<body>';

    echo '<h2>Unable to save player participation</h2>';

    echo '<p><strong>Error:</strong></p>';

    echo '<pre>';
    echo htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    );
    echo '</pre>';

    echo '<p>';
    echo '<strong>File:</strong> ';
    echo htmlspecialchars(
        $e->getFile(),
        ENT_QUOTES,
        'UTF-8'
    );
    echo '</p>';

    echo '<p>';
    echo '<strong>Line:</strong> ';
    echo (int) $e->getLine();
    echo '</p>';

    echo '</body>';
    echo '</html>';

    exit;
}