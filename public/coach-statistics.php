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
| Helper functions
|--------------------------------------------------------------------------
*/

function coachStatsEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function coachStatsFormatValue(mixed $value): string
{
    if ($value === null || $value === '') {
        return '0';
    }

    return rtrim(
        rtrim(
            number_format((float) $value, 2, '.', ''),
            '0'
        ),
        '.'
    );
}

function coachStatsFormatMetric(string $metricCode): string
{
    return ucwords(
        strtolower(
            str_replace(['_', '-'], ' ', trim($metricCode))
        )
    );
}

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
| Calculate summary values
|--------------------------------------------------------------------------
*/

$playersWithStats = 0;
$totalMetricEntries = 0;

foreach ($teamStatistics as $team) {
    $playersWithStats += count($team['players']);

    foreach ($team['players'] as $player) {
        $totalMetricEntries += count($player['metrics']);
    }
}

$totalTeams = count($teamStatistics);

require_once __DIR__ . '/../includes/header.php';

?>

<style>
    /*
    |--------------------------------------------------------------------------
    | Coach Team Statistics
    |--------------------------------------------------------------------------
    | Styles are scoped to .coach-statistics-page.
    |--------------------------------------------------------------------------
    */

    .coach-statistics-page {
        --cs-primary: var(--primary-color, #3157d5);
        --cs-text: var(--text-color, #172033);
        --cs-muted: var(--muted-color, #667085);
        --cs-border: var(--border-color, #e5e7eb);
        --cs-surface: var(--card-bg, #ffffff);
        --cs-soft: #f6f8fc;

        color: var(--cs-text);
    }

    .coach-statistics-page * {
        box-sizing: border-box;
    }

    .coach-statistics-page .cs-page-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 26px;
    }

    .coach-statistics-page .cs-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 8px;
        color: var(--cs-primary);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 1.2px;
        text-transform: uppercase;
    }

    .coach-statistics-page .cs-page-heading h1 {
        margin: 0;
        font-size: clamp(25px, 3vw, 34px);
        line-height: 1.2;
        font-weight: 800;
    }

    .coach-statistics-page .cs-page-heading p {
        max-width: 650px;
        margin: 10px 0 0;
        color: var(--cs-muted);
        font-size: 14px;
        line-height: 1.7;
    }

    .coach-statistics-page .cs-heading-icon {
        display: grid;
        place-items: center;
        flex: 0 0 56px;
        width: 56px;
        height: 56px;
        border: 1px solid #dce5ff;
        border-radius: 17px;
        background: #eef2ff;
        color: #3157d5;
        font-size: 26px;
    }

    .coach-statistics-page .cs-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 30px;
    }

    .coach-statistics-page .cs-summary-card {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
        padding: 20px;
        border: 1px solid var(--cs-border);
        border-radius: 17px;
        background: var(--cs-surface);
        box-shadow: 0 5px 18px rgba(15, 23, 42, 0.035);
        transition: transform 180ms ease, box-shadow 180ms ease;
    }

    .coach-statistics-page .cs-summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07);
    }

    .coach-statistics-page .cs-summary-icon {
        display: grid;
        place-items: center;
        flex: 0 0 46px;
        width: 46px;
        height: 46px;
        border-radius: 14px;
        background: #eef2ff;
        color: #3157d5;
        font-size: 21px;
    }

    .coach-statistics-page .cs-summary-card:nth-child(2) .cs-summary-icon {
        background: #ecfdf3;
        color: #16834a;
    }

    .coach-statistics-page .cs-summary-card:nth-child(3) .cs-summary-icon {
        background: #fff7e6;
        color: #b7791f;
    }

    .coach-statistics-page .cs-summary-label {
        display: block;
        margin-bottom: 5px;
        color: var(--cs-muted);
        font-size: 12px;
        font-weight: 600;
    }

    .coach-statistics-page .cs-summary-value {
        display: block;
        font-size: 27px;
        line-height: 1.1;
        font-weight: 800;
    }

    .coach-statistics-page .cs-section-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin: 0 0 18px;
    }

    .coach-statistics-page .cs-section-heading h2 {
        margin: 0;
        font-size: 21px;
        font-weight: 800;
    }

    .coach-statistics-page .cs-section-heading p {
        margin: 5px 0 0;
        color: var(--cs-muted);
        font-size: 13px;
    }

    .coach-statistics-page .cs-team-list {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 22px;
    }

    .coach-statistics-page .cs-team-card {
        overflow: hidden;
        border: 1px solid var(--cs-border);
        border-radius: 18px;
        background: var(--cs-surface);
        box-shadow: 0 5px 18px rgba(15, 23, 42, 0.035);
    }

    .coach-statistics-page .cs-team-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 22px 24px;
        border-bottom: 1px solid var(--cs-border);
        background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
    }

    .coach-statistics-page .cs-team-identity {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }

    .coach-statistics-page .cs-team-icon {
        display: grid;
        place-items: center;
        flex: 0 0 48px;
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: #eaf0ff;
        color: #3157d5;
        font-size: 23px;
    }

    .coach-statistics-page .cs-team-title {
        min-width: 0;
    }

    .coach-statistics-page .cs-team-title h3 {
        margin: 0;
        overflow-wrap: anywhere;
        font-size: 17px;
        font-weight: 800;
    }

    .coach-statistics-page .cs-team-sport {
        margin-top: 6px;
        color: var(--cs-muted);
        font-size: 13px;
    }

    .coach-statistics-page .cs-team-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        padding: 8px 12px;
        border: 1px solid #d8e2ff;
        border-radius: 999px;
        background: #eef2ff;
        color: #3157d5;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .coach-statistics-page .cs-player-list {
        display: grid;
        gap: 0;
    }

    .coach-statistics-page .cs-player-row {
        display: grid;
        grid-template-columns: minmax(180px, 0.8fr) minmax(0, 1.7fr);
        gap: 22px;
        padding: 20px 24px;
        border-bottom: 1px solid var(--cs-border);
    }

    .coach-statistics-page .cs-player-row:last-child {
        border-bottom: 0;
    }

    .coach-statistics-page .cs-player-identity {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .coach-statistics-page .cs-player-avatar {
        display: grid;
        place-items: center;
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #edf2ff;
        color: #3157d5;
        font-size: 15px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .coach-statistics-page .cs-player-info {
        min-width: 0;
    }

    .coach-statistics-page .cs-player-info strong {
        display: block;
        overflow-wrap: anywhere;
        font-size: 14px;
        font-weight: 800;
        line-height: 1.5;
    }

    .coach-statistics-page .cs-player-id {
        display: block;
        margin-top: 4px;
        color: var(--cs-muted);
        font-size: 12px;
        overflow-wrap: anywhere;
    }

    .coach-statistics-page .cs-metrics {
        display: flex;
        flex-wrap: wrap;
        align-content: center;
        gap: 9px;
        min-width: 0;
    }

    .coach-statistics-page .cs-metric {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        max-width: 100%;
        padding: 9px 12px;
        border: 1px solid #e1e8ff;
        border-radius: 11px;
        background: #f5f7ff;
    }

    .coach-statistics-page .cs-metric-name {
        color: #52607a;
        font-size: 12px;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .coach-statistics-page .cs-metric-value {
        color: #2449bd;
        font-size: 14px;
        font-weight: 900;
        white-space: nowrap;
    }

    .coach-statistics-page .cs-empty-state {
        padding: 48px 24px;
        border: 1px dashed var(--cs-border);
        border-radius: 18px;
        background: var(--cs-surface);
        text-align: center;
    }

    .coach-statistics-page .cs-empty-icon {
        display: grid;
        place-items: center;
        width: 64px;
        height: 64px;
        margin: 0 auto 16px;
        border-radius: 20px;
        background: #eef2ff;
        color: #3157d5;
        font-size: 30px;
    }

    .coach-statistics-page .cs-empty-state h3 {
        margin: 0 0 8px;
        font-size: 19px;
        font-weight: 800;
    }

    .coach-statistics-page .cs-empty-state p {
        max-width: 480px;
        margin: 0 auto;
        color: var(--cs-muted);
        font-size: 14px;
        line-height: 1.7;
    }

    .coach-statistics-page .cs-info-card {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        margin-top: 24px;
        padding: 20px;
        border: 1px solid #dce5ff;
        border-radius: 16px;
        background: #f7f9ff;
    }

    .coach-statistics-page .cs-info-icon {
        display: grid;
        place-items: center;
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        border-radius: 13px;
        background: #e8eeff;
        color: #3157d5;
        font-size: 20px;
    }

    .coach-statistics-page .cs-info-card h3 {
        margin: 0 0 5px;
        font-size: 14px;
        font-weight: 800;
    }

    .coach-statistics-page .cs-info-card p {
        margin: 0;
        color: var(--cs-muted);
        font-size: 13px;
        line-height: 1.7;
    }

    @media (max-width: 900px) {
        .coach-statistics-page .cs-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .coach-statistics-page .cs-player-row {
            grid-template-columns: minmax(0, 1fr);
            gap: 15px;
        }
    }

    @media (max-width: 600px) {
        .coach-statistics-page .cs-page-heading {
            gap: 12px;
        }

        .coach-statistics-page .cs-heading-icon {
            flex-basis: 46px;
            width: 46px;
            height: 46px;
            border-radius: 14px;
            font-size: 22px;
        }

        .coach-statistics-page .cs-summary-grid {
            gap: 12px;
        }

        .coach-statistics-page .cs-summary-card {
            gap: 10px;
            padding: 14px;
        }

        .coach-statistics-page .cs-summary-icon {
            flex-basis: 38px;
            width: 38px;
            height: 38px;
            border-radius: 11px;
            font-size: 18px;
        }

        .coach-statistics-page .cs-summary-value {
            font-size: 23px;
        }

        .coach-statistics-page .cs-team-header {
            align-items: flex-start;
            padding: 18px;
        }

        .coach-statistics-page .cs-player-row {
            padding: 18px;
        }

        .coach-statistics-page .cs-metric {
            padding: 8px 10px;
        }
    }

    @media (max-width: 420px) {
        .coach-statistics-page .cs-summary-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .coach-statistics-page .cs-page-heading h1 {
            font-size: 25px;
        }

        .coach-statistics-page .cs-team-header {
            flex-direction: column;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .coach-statistics-page .cs-summary-card {
            transition: none;
        }
    }
</style>

<div class="coach-statistics-page">

    <!-- Page heading -->
    <div class="cs-page-heading">
        <div>
            <span class="cs-eyebrow">Coach Portal</span>

            <h1>Team Statistics</h1>

            <p>
                Monitor player performance and recorded statistics
                across your assigned teams.
            </p>
        </div>

        <div class="cs-heading-icon" aria-hidden="true">
            📊
        </div>
    </div>

    <!-- Summary cards -->
    <div class="cs-summary-grid">

        <div class="cs-summary-card">
            <div class="cs-summary-icon" aria-hidden="true">
                🏆
            </div>

            <div>
                <span class="cs-summary-label">Teams With Statistics</span>

                <strong class="cs-summary-value">
                    <?= $totalTeams ?>
                </strong>
            </div>
        </div>

        <div class="cs-summary-card">
            <div class="cs-summary-icon" aria-hidden="true">
                👥
            </div>

            <div>
                <span class="cs-summary-label">Players With Stats</span>

                <strong class="cs-summary-value">
                    <?= $playersWithStats ?>
                </strong>
            </div>
        </div>

        <div class="cs-summary-card">
            <div class="cs-summary-icon" aria-hidden="true">
                📈
            </div>

            <div>
                <span class="cs-summary-label">Recorded Metrics</span>

                <strong class="cs-summary-value">
                    <?= $totalMetricEntries ?>
                </strong>
            </div>
        </div>

    </div>

    <!-- Team statistics -->
    <section>

        <div class="cs-section-heading">
            <div>
                <h2>Performance by Team</h2>

                <p>
                    Player statistics are grouped under each team.
                </p>
            </div>
        </div>

        <?php if (empty($teamStatistics)): ?>

            <div class="cs-empty-state">
                <div class="cs-empty-icon" aria-hidden="true">
                    📊
                </div>

                <h3>No Statistics Available</h3>

                <p>
                    Player match statistics have not been recorded
                    for your teams yet. Recorded statistics will
                    appear here once they are available.
                </p>
            </div>

        <?php else: ?>

            <div class="cs-team-list">

                <?php foreach ($teamStatistics as $team): ?>

                    <section class="cs-team-card">

                        <!-- Team heading -->
                        <div class="cs-team-header">

                            <div class="cs-team-identity">

                                <div class="cs-team-icon" aria-hidden="true">
                                    🏅
                                </div>

                                <div class="cs-team-title">

                                    <h3>
                                        <?= coachStatsEscape($team['team_name']) ?>
                                    </h3>

                                    <div class="cs-team-sport">
                                        <?= coachStatsEscape($team['sport_name']) ?>
                                    </div>

                                </div>

                            </div>

                            <span class="cs-team-count">
                                <?= count($team['players']) ?>
                                <?= count($team['players']) === 1 ? 'Player' : 'Players' ?>
                            </span>

                        </div>

                        <!-- Players -->
                        <div class="cs-player-list">

                            <?php foreach ($team['players'] as $player): ?>

                                <?php
                                $playerName = trim(
                                    (string) ($player['player_name'] ?? '')
                                );

                                $playerInitial = $playerName !== ''
                                    ? substr($playerName, 0, 1)
                                    : '?';

                                $studentId = trim(
                                    (string) ($player['student_id'] ?? '')
                                );
                                ?>

                                <article class="cs-player-row">

                                    <!-- Player identity -->
                                    <div class="cs-player-identity">

                                        <div class="cs-player-avatar" aria-hidden="true">
                                            <?= coachStatsEscape($playerInitial) ?>
                                        </div>

                                        <div class="cs-player-info">

                                            <strong>
                                                <?= coachStatsEscape(
                                                    $playerName !== ''
                                                        ? $playerName
                                                        : 'Unnamed Player'
                                                ) ?>
                                            </strong>

                                            <span class="cs-player-id">
                                                Student ID:
                                                <?= coachStatsEscape(
                                                    $studentId !== ''
                                                        ? $studentId
                                                        : 'Not available'
                                                ) ?>
                                            </span>

                                        </div>

                                    </div>

                                    <!-- Player metrics -->
                                    <div class="cs-metrics">

                                        <?php foreach ($player['metrics'] as $metric): ?>

                                            <?php
                                            $metricCode = trim(
                                                (string) ($metric['metric_code'] ?? '')
                                            );

                                            $metricLabel = $metricCode !== ''
                                                ? coachStatsFormatMetric($metricCode)
                                                : 'Statistic';
                                            ?>

                                            <div class="cs-metric">

                                                <span class="cs-metric-name">
                                                    <?= coachStatsEscape($metricLabel) ?>
                                                </span>

                                                <strong class="cs-metric-value">
                                                    <?= coachStatsEscape(
                                                        coachStatsFormatValue(
                                                            $metric['total_value']
                                                        )
                                                    ) ?>
                                                </strong>

                                            </div>

                                        <?php endforeach; ?>

                                    </div>

                                </article>

                            <?php endforeach; ?>

                        </div>

                    </section>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <!-- Information card -->
    <section class="cs-info-card">

        <div class="cs-info-icon" aria-hidden="true">
            ℹ️
        </div>

        <div>
            <h3>How Statistics Work</h3>

            <p>
                Statistics shown here are totals calculated from the
                player match statistics recorded for your teams.
                The metric names and values depend on the statistics
                entered for each player.
            </p>
        </div>

    </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>