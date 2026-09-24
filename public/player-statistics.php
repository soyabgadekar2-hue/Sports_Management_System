<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';

requireRole('PLAYER');

$user = currentUser();
$db = db();

$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Get Player
|--------------------------------------------------------------------------
*/
$playerStmt = $db->prepare("
    SELECT
        p.player_id,
        p.student_id,
        p.department,
        p.course,
        u.full_name
    FROM player_profiles p
    INNER JOIN users u
        ON u.user_id = p.user_id
    WHERE p.user_id = :user_id
    LIMIT 1
");

$playerStmt->execute([
    ':user_id' => $userId
]);

$player = $playerStmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    exit('Player profile not found.');
}

$playerId = (int) $player['player_id'];

/*
|--------------------------------------------------------------------------
| Get Overall Player Statistics
|--------------------------------------------------------------------------
|
| Statistics are stored against match participation.
|
| Example:
| goals   = 2
| assists = 1
|
| The same metric can exist across multiple matches.
| Therefore we SUM the values for the player's overall statistics.
|
|--------------------------------------------------------------------------
*/
$statsStmt = $db->prepare("
    SELECT
        pms.metric_code,
        SUM(pms.metric_value) AS total_value,
        COUNT(*) AS entries_count
    FROM player_match_statistics pms

    INNER JOIN player_match_participation pmp
        ON pmp.match_participation_id = pms.match_participation_id

    WHERE pmp.player_id = :player_id

    GROUP BY pms.metric_code

    ORDER BY pms.metric_code ASC
");

$statsStmt->execute([
    ':player_id' => $playerId
]);

$statistics = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Get Match Statistics History
|--------------------------------------------------------------------------
|
| Shows each recorded statistic match-by-match.
|
|--------------------------------------------------------------------------
*/
$historyStmt = $db->prepare("
    SELECT
        pms.player_match_statistic_id,
        pms.metric_code,
        pms.metric_value,

        m.match_id,
        m.match_number,
        m.scheduled_start,

        t.tournament_name,

        team.team_name

    FROM player_match_statistics pms

    INNER JOIN player_match_participation pmp
        ON pmp.match_participation_id = pms.match_participation_id

    INNER JOIN matches m
        ON m.match_id = pmp.match_id

    INNER JOIN tournaments t
        ON t.tournament_id = m.tournament_id

    INNER JOIN teams team
        ON team.team_id = pmp.team_id

    WHERE pmp.player_id = :player_id

    ORDER BY
        m.scheduled_start DESC,
        pms.metric_code ASC
");

$historyStmt->execute([
    ':player_id' => $playerId
]);

$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/
$totalStatisticEntries = count($history);
$totalMatchesWithStats = count(
    array_unique(
        array_column($history, 'match_id')
    )
);

/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">

    <!-- =========================================================
         PAGE HEADER
         ========================================================= -->

    <section class="welcome-card">

        <div class="welcome-content">

            <div class="welcome-label">
                MY STATISTICS
            </div>

            <h1>
                My Statistics
            </h1>

            <p>
                View your overall performance and match statistics.
            </p>

        </div>

        <div class="welcome-icon">
            📊
        </div>

    </section>


    <!-- =========================================================
         PLAYER SUMMARY
         ========================================================= -->

    <section class="dashboard-stats">

        <div class="stat-card">

            <div class="stat-icon">
                👤
            </div>

            <div class="stat-content">

                <span>
                    Player
                </span>

                <strong>
                    <?= htmlspecialchars(
                        (string) $player['full_name']
                    ) ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                🪪
            </div>

            <div class="stat-content">

                <span>
                    Student ID
                </span>

                <strong>
                    <?= htmlspecialchars(
                        (string) $player['student_id']
                    ) ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                ⚽
            </div>

            <div class="stat-content">

                <span>
                    Matches With Stats
                </span>

                <strong>
                    <?= $totalMatchesWithStats ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                📈
            </div>

            <div class="stat-content">

                <span>
                    Statistic Entries
                </span>

                <strong>
                    <?= $totalStatisticEntries ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- =========================================================
         OVERALL STATISTICS
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Overall Statistics
                </h2>

                <p>
                    Your accumulated statistics across recorded matches.
                </p>

            </div>

            <div class="card-header-icon">
                📊
            </div>

        </div>


        <?php if (!empty($statistics)): ?>

            <div class="dashboard-stats">

                <?php foreach ($statistics as $stat): ?>

                    <?php
                    $metricCode = (string) $stat['metric_code'];

                    $metricName = ucwords(
                        str_replace(
                            ['_', '-'],
                            ' ',
                            $metricCode
                        )
                    );

                    $totalValue = (float) $stat['total_value'];

                    if (floor($totalValue) === $totalValue) {
                        $displayValue = (string) (int) $totalValue;
                    } else {
                        $displayValue = number_format(
                            $totalValue,
                            2
                        );
                    }
                    ?>

                    <div class="stat-card">

                        <div class="stat-icon">
                            🏅
                        </div>

                        <div class="stat-content">

                            <span>
                                <?= htmlspecialchars(
                                    $metricName
                                ) ?>
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $displayValue
                                ) ?>
                            </strong>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-dashboard">

                <div style="font-size: 42px;">
                    📊
                </div>

                <strong>
                    No statistics available
                </strong>

                <p>
                    Statistics will appear here after your match
                    performance has been recorded.
                </p>

            </div>

        <?php endif; ?>

    </section>


    <!-- =========================================================
         MATCH STATISTICS HISTORY
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Match Statistics History
                </h2>

                <p>
                    Statistics recorded for each match.
                </p>

            </div>

            <div class="card-header-icon">
                📋
            </div>

        </div>


        <?php if (!empty($history)): ?>

            <div class="table-responsive">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>
                                Match
                            </th>

                            <th>
                                Tournament
                            </th>

                            <th>
                                Team
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Statistic
                            </th>

                            <th>
                                Value
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($history as $item): ?>

                            <?php
                            $metricCode =
                                (string) $item['metric_code'];

                            $metricName = ucwords(
                                str_replace(
                                    ['_', '-'],
                                    ' ',
                                    $metricCode
                                )
                            );

                            $metricValue =
                                (float) $item['metric_value'];

                            if (floor($metricValue) === $metricValue) {
                                $displayValue =
                                    (string) (int) $metricValue;
                            } else {
                                $displayValue =
                                    number_format(
                                        $metricValue,
                                        2
                                    );
                            }
                            ?>

                            <tr>

                                <td>

                                    <strong>
                                        Match #<?= htmlspecialchars(
                                            (string) $item['match_number']
                                        ) ?>
                                    </strong>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        (string) $item['tournament_name']
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        (string) $item['team_name']
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                (string) $item['scheduled_start']
                                            )
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $metricName
                                    ) ?>

                                </td>


                                <td>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $displayValue
                                        ) ?>
                                    </strong>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-dashboard">

                <div style="font-size: 42px;">
                    📋
                </div>

                <strong>
                    No match statistics recorded
                </strong>

                <p>
                    Your match statistics history will appear here
                    once statistics are entered by the authorized
                    tournament staff.
                </p>

            </div>

        <?php endif; ?>

    </section>


    <!-- =========================================================
         INFORMATION
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    About Your Statistics
                </h2>

                <p>
                    How statistics are displayed.
                </p>

            </div>

            <div class="card-header-icon">
                ℹ️
            </div>

        </div>


        <div class="feature-placeholder">

            <p>
                Your statistics are recorded separately for each match.
                The Overall Statistics section combines the recorded
                values for each statistic.
            </p>

            <p>
                For example, if you score 2 goals in one match and
                1 goal in another match, your overall goals will be
                displayed as 3.
            </p>

        </div>

    </section>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>