<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';

requireRole('COACH');

$user = currentUser();
$userId = (int) $user['id'];

$db = db();

/*
|--------------------------------------------------------------------------
| Get coach profile
|--------------------------------------------------------------------------
*/
$coachStmt = $db->prepare("
    SELECT
        cp.coach_id,
        u.full_name
    FROM coach_profiles cp
    INNER JOIN users u
        ON u.user_id = cp.user_id
    WHERE cp.user_id = :user_id
    LIMIT 1
");

$coachStmt->execute([
    ':user_id' => $userId
]);

$coach = $coachStmt->fetch(PDO::FETCH_ASSOC);

if (!$coach) {
    http_response_code(404);
    exit('Coach profile not found.');
}

$coachId = (int) $coach['coach_id'];

/*
|--------------------------------------------------------------------------
| Get player statistics for coach's teams
|--------------------------------------------------------------------------
*/
$statsStmt = $db->prepare("
    SELECT
        pp.player_id,
        pp.student_id,
        u.full_name,

        t.team_id,
        t.team_name,

        s.sport_name,

        pms.metric_code,
        SUM(pms.metric_value) AS total_value

    FROM teams t

    INNER JOIN sports s
        ON s.sport_id = t.sport_id

    INNER JOIN team_players tp
        ON tp.team_id = t.team_id

    INNER JOIN player_profiles pp
        ON pp.player_id = tp.player_id

    INNER JOIN users u
        ON u.user_id = pp.user_id

    INNER JOIN player_match_participation pmp
        ON pmp.player_id = pp.player_id
        AND pmp.team_id = t.team_id

    INNER JOIN player_match_statistics pms
        ON pms.match_participation_id = pmp.match_participation_id

    WHERE t.coach_id = :coach_id
      AND tp.membership_status = 'ACTIVE'
      AND tp.left_at IS NULL
      AND pp.player_status = 'ACTIVE'
      AND u.account_status = 'APPROVED'

    GROUP BY
        pp.player_id,
        pp.student_id,
        u.full_name,
        t.team_id,
        t.team_name,
        s.sport_name,
        pms.metric_code

    ORDER BY
        t.team_name ASC,
        u.full_name ASC,
        pms.metric_code ASC
");

$statsStmt->execute([
    ':coach_id' => $coachId
]);

$statistics = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Group statistics by team and player
|--------------------------------------------------------------------------
*/
$teamStatistics = [];

foreach ($statistics as $stat) {

    $teamId = (int) $stat['team_id'];
    $playerId = (int) $stat['player_id'];

    if (!isset($teamStatistics[$teamId])) {

        $teamStatistics[$teamId] = [
            'team_name' => $stat['team_name'],
            'sport_name' => $stat['sport_name'],
            'players' => []
        ];
    }

    if (!isset($teamStatistics[$teamId]['players'][$playerId])) {

        $teamStatistics[$teamId]['players'][$playerId] = [
            'player_name' => $stat['full_name'],
            'student_id' => $stat['student_id'],
            'metrics' => []
        ];
    }

    $teamStatistics[$teamId]['players'][$playerId]['metrics'][] = [
        'metric_code' => $stat['metric_code'],
        'total_value' => $stat['total_value']
    ];
}

/*
|--------------------------------------------------------------------------
| Total players with statistics
|--------------------------------------------------------------------------
*/
$playersWithStats = 0;

foreach ($teamStatistics as $team) {
    $playersWithStats += count($team['players']);
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="page-header">

    <div>

        <span class="eyebrow">Coach Portal</span>

        <h1>Team Statistics 📊</h1>

        <p>
            Monitor player performance across your teams.
        </p>

    </div>

</div>

<!-- Summary -->
<div class="dashboard-grid">

    <div class="stat-card">

        <div class="stat-icon">🏆</div>

        <div>

            <span class="stat-label">Teams</span>

            <strong>
                <?= count($teamStatistics) ?>
            </strong>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon">👥</div>

        <div>

            <span class="stat-label">Players With Stats</span>

            <strong>
                <?= $playersWithStats ?>
            </strong>

        </div>

    </div>

</div>

<?php if (empty($teamStatistics)): ?>

    <section class="dashboard-section">

        <div class="empty-state">

            <div class="empty-icon">📊</div>

            <h3>No Statistics Available</h3>

            <p>
                Player match statistics have not been recorded
                for your teams yet.
            </p>

        </div>

    </section>

<?php else: ?>

    <?php foreach ($teamStatistics as $team): ?>

        <section class="dashboard-section">

            <div class="section-heading">

                <div>

                    <h2>
                        <?= htmlspecialchars($team['team_name']) ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars($team['sport_name']) ?>
                    </p>

                </div>

                <span class="badge badge-approved">
                    <?= count($team['players']) ?> Players
                </span>

            </div>

            <div class="table-wrapper">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>Player</th>

                            <th>Student ID</th>

                            <th>Statistics</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($team['players'] as $player): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $player['player_name']
                                    ) ?>
                                </strong>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $player['student_id']
                                ) ?>

                            </td>

                            <td>

                                <?php foreach (
                                    $player['metrics']
                                    as $metric
                                ): ?>

                                    <span
                                        class="badge badge-approved"
                                        style="margin-right: 6px; margin-bottom: 4px;"
                                    >

                                        <?= htmlspecialchars(
                                            $metric['metric_code']
                                        ) ?>

                                        :

                                        <?= rtrim(
                                            rtrim(
                                                number_format(
                                                    (float) $metric['total_value'],
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                                '0'
                                            ),
                                            '.'
                                        ) ?>

                                    </span>

                                <?php endforeach; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endforeach; ?>

<?php endif; ?>

<section class="dashboard-section">

    <div class="simple-info-card">

        <div class="simple-info-icon">
            📊
        </div>

        <div>

            <h3>How Statistics Work</h3>

            <p>
                Statistics shown here are calculated from the
                player match statistics recorded for completed
                or recorded matches.
            </p>

        </div>

    </div>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>