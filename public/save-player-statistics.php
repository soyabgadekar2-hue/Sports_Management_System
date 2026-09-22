<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

requireValidCsrfToken($_POST['csrf_token'] ?? '');

$matchId = (int) ($_POST['match_id'] ?? 0);
$stats = $_POST['stats'] ?? [];

if ($matchId <= 0) {
    exit('Invalid match.');
}

if (!is_array($stats)) {
    exit('Invalid statistics data.');
}

try {
    $pdo = db();

    // Check that the match exists
    $matchStatement = $pdo->prepare(
        'SELECT match_id
         FROM matches
         WHERE match_id = :match_id
         LIMIT 1'
    );

    $matchStatement->execute([
        ':match_id' => $matchId
    ]);

    if (!$matchStatement->fetch()) {
        http_response_code(404);
        exit('Match not found.');
    }

    $pdo->beginTransaction();

    /*
     * Delete existing statistics for this match.
     * This allows the page to update statistics instead of
     * creating duplicate records.
     */
    $deleteStatement = $pdo->prepare(
        'DELETE pms
         FROM player_match_statistics pms
         INNER JOIN player_match_participation pmp
             ON pmp.match_participation_id = pms.match_participation_id
         WHERE pmp.match_id = :match_id'
    );

    $deleteStatement->execute([
        ':match_id' => $matchId
    ]);

    /*
     * Insert new statistics.
     */
    $insertStatement = $pdo->prepare(
        'INSERT INTO player_match_statistics
        (
            match_participation_id,
            metric_code,
            metric_value
        )
        SELECT
            pmp.match_participation_id,
            :metric_code,
            :metric_value
        FROM player_match_participation pmp
        WHERE pmp.match_participation_id = :match_participation_id
          AND pmp.match_id = :match_id
        LIMIT 1'
    );

    $savedCount = 0;

    foreach ($stats as $participationId => $playerStats) {

        $participationId = (int) $participationId;

        if ($participationId <= 0 || !is_array($playerStats)) {
            continue;
        }

        $metricCodes = $playerStats['metric_code'] ?? [];
        $metricValues = $playerStats['metric_value'] ?? [];

        if (!is_array($metricCodes) || !is_array($metricValues)) {
            continue;
        }

        foreach ($metricCodes as $index => $metricCode) {

            $metricCode = trim((string) $metricCode);

            if ($metricCode === '') {
                continue;
            }

            $metricValue = $metricValues[$index] ?? '';

            if ($metricValue === '' || !is_numeric($metricValue)) {
                continue;
            }

            $metricValue = (float) $metricValue;

            if ($metricValue < 0) {
                continue;
            }

            $insertStatement->execute([
                ':match_participation_id' => $participationId,
                ':match_id' => $matchId,
                ':metric_code' => $metricCode,
                ':metric_value' => $metricValue
            ]);

            if ($insertStatement->rowCount() > 0) {
                $savedCount++;
            }
        }
    }

    $pdo->commit();

    auditLog(
        'UPDATE',
        'matches',
        $matchId,
        'Player match statistics updated. Statistics records saved: ' . $savedCount
    );

    header(
        'Location: match-player-statistics.php?match_id=' .
        $matchId .
        '&success=1'
    );

    exit;

} catch (Throwable $exception) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Saving player statistics failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    echo '<h2>Unable to Save Statistics</h2>';
    echo '<p>Something went wrong while saving player statistics.</p>';

    echo '<p>
        <a href="match-player-statistics.php?match_id=' .
        $matchId .
        '">
        Back to Player Statistics
        </a>
    </p>';

    echo '<p>
        <strong>Development error:</strong> ' .
        htmlspecialchars(
            $exception->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        ) .
        '</p>';
}