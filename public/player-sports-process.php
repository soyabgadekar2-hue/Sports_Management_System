<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole('PLAYER');

$user = currentUser();
$db = db();

$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Validate CSRF Token
|--------------------------------------------------------------------------
*/
requireValidCsrfToken($_POST['csrf_token'] ?? '');

/*
|--------------------------------------------------------------------------
| Get Selected Sports
|--------------------------------------------------------------------------
*/
$selectedSports = $_POST['sport_ids'] ?? [];

if (!is_array($selectedSports)) {
    $selectedSports = [];
}

/*
|--------------------------------------------------------------------------
| Clean Sport IDs
|--------------------------------------------------------------------------
*/
$selectedSports = array_map(
    static function ($sportId): int {
        return (int) $sportId;
    },
    $selectedSports
);

$selectedSports = array_filter(
    $selectedSports,
    static function (int $sportId): bool {
        return $sportId > 0;
    }
);

$selectedSports = array_values(
    array_unique($selectedSports)
);

/*
|--------------------------------------------------------------------------
| Get Player
|--------------------------------------------------------------------------
*/
$playerStmt = $db->prepare("
    SELECT
        player_id
    FROM player_profiles
    WHERE user_id = :user_id
    LIMIT 1
");

$playerStmt->execute([
    ':user_id' => $userId
]);

$player = $playerStmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    http_response_code(404);
    exit('Player profile not found.');
}

$playerId = (int) $player['player_id'];

/*
|--------------------------------------------------------------------------
| Validate Selected Sports
|--------------------------------------------------------------------------
|
| Only sports that actually exist in the sports table can be selected.
|
*/
$validSportIds = [];

if (!empty($selectedSports)) {

    $placeholders = implode(
        ',',
        array_fill(
            0,
            count($selectedSports),
            '?'
        )
    );

    $sportsStmt = $db->prepare("
        SELECT
            sport_id
        FROM sports
        WHERE sport_id IN ($placeholders)
    ");

    $sportsStmt->execute($selectedSports);

    $validSportIds = $sportsStmt->fetchAll(PDO::FETCH_COLUMN);

    $validSportIds = array_map(
        'intval',
        $validSportIds
    );
}

/*
|--------------------------------------------------------------------------
| Save Sports
|--------------------------------------------------------------------------
*/
try {

    $db->beginTransaction();

    /*
    |----------------------------------------------------------------------
    | Remove Existing Sports
    |----------------------------------------------------------------------
    */
    $deleteStmt = $db->prepare("
        DELETE FROM player_sports
        WHERE player_id = :player_id
    ");

    $deleteStmt->execute([
        ':player_id' => $playerId
    ]);

    /*
    |----------------------------------------------------------------------
    | Insert New Sports
    |----------------------------------------------------------------------
    */
    if (!empty($validSportIds)) {

        $insertStmt = $db->prepare("
            INSERT INTO player_sports (
                player_id,
                sport_id
            )
            VALUES (
                :player_id,
                :sport_id
            )
        ");

        foreach ($validSportIds as $sportId) {

            $insertStmt->execute([
                ':player_id' => $playerId,
                ':sport_id' => $sportId
            ]);
        }
    }

    $db->commit();

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);

    exit(
        'Unable to save sports. Please try again.'
    );
}

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/
header(
    'Location: player-sports.php?saved=1'
);

exit;