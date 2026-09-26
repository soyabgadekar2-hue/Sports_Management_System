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
| Get approved active players from the coach's teams
|--------------------------------------------------------------------------
*/

$playersStmt = $db->prepare("
    SELECT
        pp.player_id,
        pp.student_id,
        pp.department,
        pp.course,
        pp.academic_year,
        pp.semester,
        pp.gender,
        pp.player_status,

        u.full_name,
        u.email,
        u.phone,
        u.account_status,

        t.team_id,
        t.team_name,
        t.team_category,

        s.sport_name

    FROM team_players tp

    INNER JOIN teams t
        ON t.team_id = tp.team_id

    INNER JOIN sports s
        ON s.sport_id = t.sport_id

    INNER JOIN player_profiles pp
        ON pp.player_id = tp.player_id

    INNER JOIN users u
        ON u.user_id = pp.user_id

    WHERE t.coach_id = :coach_id
      AND tp.membership_status = 'ACTIVE'
      AND tp.left_at IS NULL
      AND pp.player_status = 'ACTIVE'
      AND u.account_status = 'APPROVED'

    ORDER BY
        t.team_name ASC,
        u.full_name ASC
");

$playersStmt->execute([
    ':coach_id' => $coachId
]);

$players = $playersStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Group players by team
|--------------------------------------------------------------------------
*/

$playersByTeam = [];

foreach ($players as $player) {
    $teamId = (int) $player['team_id'];

    if (!isset($playersByTeam[$teamId])) {
        $playersByTeam[$teamId] = [
            'team_id' => $teamId,
            'team_name' => $player['team_name'],
            'team_category' => $player['team_category'],
            'sport_name' => $player['sport_name'],
            'players' => []
        ];
    }

    $playersByTeam[$teamId]['players'][] = $player;
}

$totalPlayers = count($players);
$totalTeams = count($playersByTeam);

require_once __DIR__ . '/../includes/header.php';

?>

<style>
    /*
    |--------------------------------------------------------------------------
    | Coach Players Page
    |--------------------------------------------------------------------------
    | All styles are scoped to .coach-players-page to avoid affecting
    | other pages in the application.
    |--------------------------------------------------------------------------
    */

    .coach-players-page {
        --cp-primary: var(--primary-color, #3157d5);
        --cp-text: var(--text-color, #172033);
        --cp-muted: var(--muted-color, #667085);
        --cp-border: var(--border-color, #e5e7eb);
        --cp-surface: var(--card-bg, #ffffff);
        --cp-soft: #f6f8fc;
        color: var(--cp-text);
    }

    .coach-players-page * {
        box-sizing: border-box;
    }

    .coach-players-page .cp-page-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 26px;
    }

    .coach-players-page .cp-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 8px;
        color: var(--cp-primary);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 1.2px;
        text-transform: uppercase;
    }

    .coach-players-page .cp-page-heading h1 {
        margin: 0;
        font-size: clamp(25px, 3vw, 34px);
        line-height: 1.2;
        font-weight: 800;
    }

    .coach-players-page .cp-page-heading p {
        max-width: 650px;
        margin: 10px 0 0;
        color: var(--cp-muted);
        font-size: 14px;
        line-height: 1.7;
    }

    .coach-players-page .cp-heading-icon {
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

    .coach-players-page .cp-summary-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        margin-bottom: 28px;
    }

    .coach-players-page .cp-summary-card {
        display: flex;
        align-items: center;
        gap: 16px;
        min-width: 0;
        padding: 22px;
        border: 1px solid var(--cp-border);
        border-radius: 18px;
        background: var(--cp-surface);
        box-shadow: 0 5px 18px rgba(15, 23, 42, 0.035);
        transition: transform 180ms ease, box-shadow 180ms ease;
    }

    .coach-players-page .cp-summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07);
    }

    .coach-players-page .cp-summary-icon {
        display: grid;
        place-items: center;
        flex: 0 0 52px;
        width: 52px;
        height: 52px;
        border-radius: 15px;
        background: #eef2ff;
        color: #3157d5;
        font-size: 24px;
    }

    .coach-players-page .cp-summary-card:nth-child(2) .cp-summary-icon {
        background: #ecfdf3;
        color: #16834a;
    }

    .coach-players-page .cp-summary-label {
        display: block;
        margin-bottom: 5px;
        color: var(--cp-muted);
        font-size: 13px;
        font-weight: 600;
    }

    .coach-players-page .cp-summary-value {
        display: block;
        font-size: 28px;
        line-height: 1.1;
        font-weight: 800;
    }

    .coach-players-page .cp-section-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin: 0 0 16px;
    }

    .coach-players-page .cp-section-heading h2 {
        margin: 0;
        font-size: 20px;
        font-weight: 800;
    }

    .coach-players-page .cp-section-heading p {
        margin: 5px 0 0;
        color: var(--cp-muted);
        font-size: 13px;
    }

    .coach-players-page .cp-team-list {
        display: grid;
        gap: 22px;
    }

    .coach-players-page .cp-team-card {
        overflow: hidden;
        border: 1px solid var(--cp-border);
        border-radius: 18px;
        background: var(--cp-surface);
        box-shadow: 0 5px 18px rgba(15, 23, 42, 0.035);
    }

    .coach-players-page .cp-team-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 22px 24px;
        border-bottom: 1px solid var(--cp-border);
        background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
    }

    .coach-players-page .cp-team-identity {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }

    .coach-players-page .cp-team-icon {
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

    .coach-players-page .cp-team-title {
        min-width: 0;
    }

    .coach-players-page .cp-team-title h3 {
        margin: 0;
        overflow-wrap: anywhere;
        font-size: 17px;
        font-weight: 800;
    }

    .coach-players-page .cp-team-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 7px;
        margin-top: 7px;
        color: var(--cp-muted);
        font-size: 13px;
    }

    .coach-players-page .cp-meta-dot {
        color: #a0a7b5;
    }

    .coach-players-page .cp-player-count {
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

    .coach-players-page .cp-table-wrapper {
        width: 100%;
        overflow-x: auto;
    }

    .coach-players-page .cp-player-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .coach-players-page .cp-player-table thead {
        background: var(--cp-soft);
    }

    .coach-players-page .cp-player-table th {
        padding: 14px 18px;
        color: #596579;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .coach-players-page .cp-player-table td {
        padding: 16px 18px;
        border-top: 1px solid var(--cp-border);
        color: var(--cp-text);
        font-size: 13px;
        vertical-align: middle;
    }

    .coach-players-page .cp-player-table tbody tr {
        transition: background 150ms ease;
    }

    .coach-players-page .cp-player-table tbody tr:hover {
        background: #fafbff;
    }

    .coach-players-page .cp-player-name {
        display: flex;
        align-items: center;
        gap: 11px;
        min-width: 170px;
    }

    .coach-players-page .cp-player-avatar {
        display: grid;
        place-items: center;
        flex: 0 0 36px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #edf2ff;
        color: #3157d5;
        font-size: 14px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .coach-players-page .cp-player-name strong {
        display: block;
        overflow-wrap: anywhere;
        font-size: 13px;
        font-weight: 700;
    }

    .coach-players-page .cp-player-id {
        color: var(--cp-muted);
        font-size: 12px;
    }

    .coach-players-page .cp-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        background: #ecfdf3;
        color: #167647;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .coach-players-page .cp-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .coach-players-page .cp-empty-state {
        padding: 48px 24px;
        border: 1px dashed var(--cp-border);
        border-radius: 18px;
        background: var(--cp-surface);
        text-align: center;
    }

    .coach-players-page .cp-empty-icon {
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

    .coach-players-page .cp-empty-state h3 {
        margin: 0 0 8px;
        font-size: 19px;
        font-weight: 800;
    }

    .coach-players-page .cp-empty-state p {
        max-width: 460px;
        margin: 0 auto;
        color: var(--cp-muted);
        font-size: 14px;
        line-height: 1.7;
    }

    @media (max-width: 768px) {
        .coach-players-page .cp-page-heading {
            gap: 12px;
        }

        .coach-players-page .cp-heading-icon {
            flex-basis: 46px;
            width: 46px;
            height: 46px;
            border-radius: 14px;
            font-size: 22px;
        }

        .coach-players-page .cp-summary-grid {
            gap: 12px;
        }

        .coach-players-page .cp-summary-card {
            gap: 12px;
            padding: 16px;
        }

        .coach-players-page .cp-summary-icon {
            flex-basis: 42px;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            font-size: 20px;
        }

        .coach-players-page .cp-summary-value {
            font-size: 24px;
        }

        .coach-players-page .cp-team-header {
            align-items: flex-start;
            padding: 18px;
        }

        .coach-players-page .cp-player-table {
            min-width: 850px;
        }
    }

    @media (max-width: 480px) {
        .coach-players-page .cp-summary-grid {
            grid-template-columns: 1fr;
        }

        .coach-players-page .cp-page-heading h1 {
            font-size: 25px;
        }

        .coach-players-page .cp-team-header {
            flex-direction: column;
        }

        .coach-players-page .cp-player-count {
            margin-left: 62px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .coach-players-page .cp-summary-card,
        .coach-players-page .cp-player-table tbody tr {
            transition: none;
        }
    }
</style>

<div class="coach-players-page">

    <!-- Page heading -->
    <div class="cp-page-heading">
        <div>
            <span class="cp-eyebrow">Coach Portal</span>

            <h1>Team Players</h1>

            <p>
                View the approved and active players currently assigned
                to your teams.
            </p>
        </div>

        <div class="cp-heading-icon" aria-hidden="true">
            👥
        </div>
    </div>

    <!-- Summary cards -->
    <div class="cp-summary-grid">

        <div class="cp-summary-card">
            <div class="cp-summary-icon" aria-hidden="true">
                👥
            </div>

            <div>
                <span class="cp-summary-label">Total Players</span>
                <strong class="cp-summary-value">
                    <?= $totalPlayers ?>
                </strong>
            </div>
        </div>

        <div class="cp-summary-card">
            <div class="cp-summary-icon" aria-hidden="true">
                🏆
            </div>

            <div>
                <span class="cp-summary-label">My Teams</span>
                <strong class="cp-summary-value">
                    <?= $totalTeams ?>
                </strong>
            </div>
        </div>

    </div>

    <!-- Team list heading -->
    <div class="cp-section-heading">
        <div>
            <h2>Players by Team</h2>
            <p>Players are grouped under their assigned teams.</p>
        </div>
    </div>

    <?php if (empty($playersByTeam)): ?>

        <!-- Empty state -->
        <section class="cp-empty-state">
            <div class="cp-empty-icon" aria-hidden="true">
                👥
            </div>

            <h3>No Players Found</h3>

            <p>
                There are currently no approved active players assigned
                to your teams. Players will appear here when they are
                active team members and their accounts are approved.
            </p>
        </section>

    <?php else: ?>

        <!-- Team cards -->
        <div class="cp-team-list">

            <?php foreach ($playersByTeam as $team): ?>

                <section class="cp-team-card">

                    <!-- Team heading -->
                    <div class="cp-team-header">

                        <div class="cp-team-identity">

                            <div class="cp-team-icon" aria-hidden="true">
                                🏅
                            </div>

                            <div class="cp-team-title">

                                <h3>
                                    <?= htmlspecialchars(
                                        (string) $team['team_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </h3>

                                <div class="cp-team-meta">

                                    <span>
                                        <?= htmlspecialchars(
                                            (string) $team['sport_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                    <?php
                                    $teamCategory = trim(
                                        (string) ($team['team_category'] ?? '')
                                    );
                                    ?>

                                    <?php if ($teamCategory !== ''): ?>
                                        <span class="cp-meta-dot" aria-hidden="true">
                                            •
                                        </span>

                                        <span>
                                            <?= htmlspecialchars(
                                                $teamCategory,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                        <span class="cp-player-count">
                            <?= count($team['players']) ?>
                            <?= count($team['players']) === 1 ? 'Player' : 'Players' ?>
                        </span>

                    </div>

                    <!-- Player table -->
                    <div class="cp-table-wrapper">

                        <table class="cp-player-table">

                            <thead>
                                <tr>
                                    <th scope="col">Player</th>
                                    <th scope="col">Student ID</th>
                                    <th scope="col">Department</th>
                                    <th scope="col">Course</th>
                                    <th scope="col">Year</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($team['players'] as $player): ?>

                                    <?php
                                    $playerName = trim(
                                        (string) ($player['full_name'] ?? '')
                                    );

                                    $initial = $playerName !== ''
                                        ? mb_substr($playerName, 0, 1, 'UTF-8')
                                        : '?';

                                    $playerStatus = strtoupper(
                                        (string) ($player['player_status'] ?? 'UNKNOWN')
                                    );
                                    ?>

                                    <tr>

                                        <td>
                                            <div class="cp-player-name">

                                                <span class="cp-player-avatar" aria-hidden="true">
                                                    <?= htmlspecialchars(
                                                        $initial,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </span>

                                                <div>
                                                    <strong>
                                                        <?= htmlspecialchars(
                                                            $playerName !== '' ? $playerName : 'Unnamed Player',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>
                                                    </strong>
                                                </div>

                                            </div>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                (string) ($player['student_id'] ?? '-'),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                (string) ($player['department'] ?? '-'),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                (string) ($player['course'] ?? '-'),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                (string) ($player['academic_year'] ?? '-'),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                (string) ($player['email'] ?? '-'),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>
                                            <span class="cp-status-badge">
                                                <span class="cp-status-dot" aria-hidden="true"></span>

                                                <?= htmlspecialchars(
                                                    $playerStatus,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </span>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </section>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>