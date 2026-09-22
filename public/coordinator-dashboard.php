<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$user = currentUser();

if (($user['role'] ?? '') !== 'SPORTS_COORDINATOR') {
    http_response_code(403);
    exit('Access denied.');
}

$pdo = db();

$totalPlayers = 0;
$totalTeams = 0;
$totalTournaments = 0;
$totalMatches = 0;
$completedMatches = 0;
$upcomingMatches = 0;

try {
    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM users
         WHERE role_id = 4
         AND account_status = 'APPROVED'"
    );
    $totalPlayers = (int) $stmt->fetchColumn();

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM teams"
    );
    $totalTeams = (int) $stmt->fetchColumn();

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM tournaments"
    );
    $totalTournaments = (int) $stmt->fetchColumn();

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM matches"
    );
    $totalMatches = (int) $stmt->fetchColumn();

    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM matches
         WHERE match_status = 'COMPLETED'"
    );
    $completedMatches = (int) $stmt->fetchColumn();

    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM matches
         WHERE match_status IN ('SCHEDULED', 'UPCOMING')"
    );
    $upcomingMatches = (int) $stmt->fetchColumn();

} catch (Throwable $e) {
    error_log(
        'Coordinator dashboard error: ' .
        $e->getMessage()
    );
}

$recentTournaments = [];

try {
    $stmt = $pdo->query(
        "SELECT
            t.tournament_id,
            t.tournament_name,
            t.start_date,
            t.end_date,
            t.tournament_status,
            s.sport_name
         FROM tournaments t
         LEFT JOIN sports s
            ON s.sport_id = t.sport_id
         ORDER BY t.created_at DESC
         LIMIT 5"
    );

    $recentTournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log(
        'Coordinator tournament query error: ' .
        $e->getMessage()
    );
}

$recentMatches = [];

try {
    $stmt = $pdo->query(
        "SELECT
            m.match_id,
            m.match_number,
            m.match_date,
            m.match_status,
            t.tournament_name,
            tm1.team_name AS home_team,
            tm2.team_name AS away_team
         FROM matches m
         LEFT JOIN tournaments t
            ON t.tournament_id = m.tournament_id
         LEFT JOIN teams tm1
            ON tm1.team_id = m.home_team_id
         LEFT JOIN teams tm2
            ON tm2.team_id = m.away_team_id
         ORDER BY m.match_date DESC
         LIMIT 5"
    );

    $recentMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log(
        'Coordinator matches query error: ' .
        $e->getMessage()
    );
}

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

function coordinatorStatusClass(?string $status): string
{
    $status = strtoupper($status ?? '');

    return match ($status) {
        'ACTIVE',
        'APPROVED',
        'ONGOING',
        'COMPLETED' => 'status-success',

        'PENDING',
        'REGISTRATION_OPEN',
        'SCHEDULED',
        'UPCOMING' => 'status-warning',

        'CANCELLED',
        'REJECTED',
        'SUSPENDED' => 'status-danger',

        default => 'status-neutral'
    };
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Coordinator Dashboard | SportSync</title>

    <meta
        name="description"
        content="SportSync Sports Coordinator Dashboard"
    >

    <link
        rel="icon"
        type="image/svg+xml"
        href="../assets/images/sportsync-mark.svg"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        .coordinator-dashboard {
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .coordinator-hero {
            position: relative;
            overflow: hidden;
            padding: 32px;
            border-radius: 24px;
            background:
                linear-gradient(
                    135deg,
                    #0b1f4d 0%,
                    #1647a8 55%,
                    #2563eb 100%
                );
            color: #ffffff;
            box-shadow:
                0 18px 45px
                rgba(15, 35, 80, 0.18);
        }

        .coordinator-hero::before {
            content: "";
            position: absolute;
            width: 270px;
            height: 270px;
            border-radius: 50%;
            background:
                rgba(255, 255, 255, 0.07);
            right: -80px;
            top: -120px;
        }

        .coordinator-hero-content {
            position: relative;
            z-index: 2;
            max-width: 750px;
        }

        .coordinator-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding: 7px 13px;
            border-radius: 999px;
            background:
                rgba(255, 255, 255, 0.12);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .coordinator-hero h1 {
            margin: 0 0 10px;
            font-size: clamp(26px, 4vw, 38px);
            line-height: 1.15;
            font-weight: 800;
        }

        .coordinator-hero p {
            margin: 0;
            color:
                rgba(255, 255, 255, 0.82);
            font-size: 15px;
            line-height: 1.7;
        }

        .coordinator-stats {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .coordinator-stat {
            padding: 22px;
            border: 1px solid #e8edf5;
            border-radius: 18px;
            background: #ffffff;
            box-shadow:
                0 8px 24px
                rgba(20, 35, 65, 0.06);
            transition:
                transform 0.25s ease,
                box-shadow 0.25s ease;
        }

        .coordinator-stat:hover {
            transform: translateY(-4px);
            box-shadow:
                0 15px 32px
                rgba(20, 35, 65, 0.10);
        }

        .coordinator-stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            margin-bottom: 20px;
            border-radius: 14px;
            background: #eef4ff;
            font-size: 21px;
        }

        .coordinator-stat-number {
            margin: 0;
            color: #111827;
            font-size: 30px;
            font-weight: 800;
            line-height: 1;
        }

        .coordinator-stat-title {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
        }

        .coordinator-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 1.4fr)
                minmax(300px, 0.8fr);
            gap: 22px;
        }

        .coordinator-panel {
            overflow: hidden;
            border: 1px solid #e8edf5;
            border-radius: 20px;
            background: #ffffff;
            box-shadow:
                0 8px 24px
                rgba(20, 35, 65, 0.05);
        }

        .coordinator-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 22px 24px;
            border-bottom: 1px solid #edf1f6;
        }

        .coordinator-panel-title {
            margin: 0;
            color: #111827;
            font-size: 17px;
            font-weight: 750;
        }

        .coordinator-panel-subtitle {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 12px;
        }

        .coordinator-panel-link {
            color: #2563eb;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .coordinator-panel-link:hover {
            text-decoration: underline;
        }

        .coordinator-table-wrap {
            overflow-x: auto;
        }

        .coordinator-table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
        }

        .coordinator-table th {
            padding: 14px 20px;
            background: #f8fafc;
            color: #64748b;
            font-size: 11px;
            font-weight: 750;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .coordinator-table td {
            padding: 16px 20px;
            border-top: 1px solid #edf1f6;
            color: #334155;
            font-size: 13px;
        }

        .coordinator-name {
            color: #111827;
            font-weight: 700;
        }

        .coordinator-meta {
            margin-top: 4px;
            color: #94a3b8;
            font-size: 11px;
        }

        .coordinator-status {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 750;
        }

        .status-success {
            background: #ecfdf3;
            color: #15803d;
        }

        .status-warning {
            background: #fff7ed;
            color: #c2410c;
        }

        .status-danger {
            background: #fef2f2;
            color: #dc2626;
        }

        .status-neutral {
            background: #f1f5f9;
            color: #475569;
        }

        .coordinator-actions {
            display: flex;
            flex-direction: column;
        }

        .coordinator-action {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 17px 22px;
            border-bottom: 1px solid #edf1f6;
            color: #1e293b;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .coordinator-action:last-child {
            border-bottom: 0;
        }

        .coordinator-action:hover {
            background: #f8fbff;
        }

        .coordinator-action-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            flex: 0 0 auto;
            border-radius: 12px;
            background: #eef4ff;
            font-size: 18px;
        }

        .coordinator-action-content {
            flex: 1;
        }

        .coordinator-action-title {
            display: block;
            font-size: 13px;
            font-weight: 750;
        }

        .coordinator-action-description {
            display: block;
            margin-top: 3px;
            color: #94a3b8;
            font-size: 11px;
        }

        .coordinator-arrow {
            color: #94a3b8;
            font-size: 18px;
        }

        .coordinator-empty {
            padding: 40px 24px;
            color: #94a3b8;
            font-size: 13px;
            text-align: center;
        }

        @media (max-width: 1100px) {

            .coordinator-stats {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .coordinator-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 700px) {

            .coordinator-hero {
                padding: 24px;
                border-radius: 20px;
            }

            .coordinator-stats {
                grid-template-columns: 1fr;
            }

            .coordinator-panel-header {
                padding: 18px;
            }

            .coordinator-table td,
            .coordinator-table th {
                padding-left: 15px;
                padding-right: 15px;
            }

        }

    </style>

</head>

<body>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-container">

    <main class="coordinator-dashboard">

        <!-- HERO -->

        <section class="coordinator-hero">

            <div class="coordinator-hero-content">

                <div class="coordinator-label">
                    🏆 Sports Coordinator
                </div>

                <h1>
                    Welcome back,
                    <?= e($user['full_name'] ?? 'Coordinator'); ?> 👋
                </h1>

                <p>
                    Coordinate tournaments, teams, matches,
                    results and sports activities across
                    SportSync from one central workspace.
                </p>

            </div>

        </section>


        <!-- STATISTICS -->

        <section class="coordinator-stats">

            <article class="coordinator-stat">

                <div class="coordinator-stat-icon">
                    🧑‍🎓
                </div>

                <p class="coordinator-stat-number">
                    <?= $totalPlayers; ?>
                </p>

                <p class="coordinator-stat-title">
                    Approved Players
                </p>

            </article>


            <article class="coordinator-stat">

                <div class="coordinator-stat-icon">
                    👥
                </div>

                <p class="coordinator-stat-number">
                    <?= $totalTeams; ?>
                </p>

                <p class="coordinator-stat-title">
                    Teams
                </p>

            </article>


            <article class="coordinator-stat">

                <div class="coordinator-stat-icon">
                    🏆
                </div>

                <p class="coordinator-stat-number">
                    <?= $totalTournaments; ?>
                </p>

                <p class="coordinator-stat-title">
                    Tournaments
                </p>

            </article>


            <article class="coordinator-stat">

                <div class="coordinator-stat-icon">
                    ⚽
                </div>

                <p class="coordinator-stat-number">
                    <?= $totalMatches; ?>
                </p>

                <p class="coordinator-stat-title">
                    Total Matches
                </p>

            </article>

        </section>


        <!-- MAIN GRID -->

        <section class="coordinator-grid">

            <!-- RECENT TOURNAMENTS -->

            <div class="coordinator-panel">

                <div class="coordinator-panel-header">

                    <div>

                        <h2 class="coordinator-panel-title">
                            Recent Tournaments
                        </h2>

                        <p class="coordinator-panel-subtitle">
                            Latest competitions in SportSync
                        </p>

                    </div>

                    <a
                        href="admin-tournaments.php"
                        class="coordinator-panel-link"
                    >
                        Manage →
                    </a>

                </div>


                <?php if (!empty($recentTournaments)): ?>

                    <div class="coordinator-table-wrap">

                        <table class="coordinator-table">

                            <thead>

                                <tr>
                                    <th>Tournament</th>
                                    <th>Sport</th>
                                    <th>Status</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($recentTournaments as $tournament): ?>

                                <tr>

                                    <td>

                                        <div class="coordinator-name">
                                            <?= e(
                                                $tournament['tournament_name']
                                            ); ?>
                                        </div>

                                        <div class="coordinator-meta">
                                            <?= e(
                                                $tournament['start_date']
                                            ); ?>
                                            -
                                            <?= e(
                                                $tournament['end_date']
                                            ); ?>
                                        </div>

                                    </td>

                                    <td>
                                        <?= e(
                                            $tournament['sport_name']
                                            ?? 'Sport'
                                        ); ?>
                                    </td>

                                    <td>

                                        <span
                                            class="coordinator-status <?= coordinatorStatusClass(
                                                $tournament['tournament_status']
                                                ?? ''
                                            ); ?>"
                                        >
                                            <?= e(
                                                $tournament['tournament_status']
                                                ?? 'N/A'
                                            ); ?>
                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="coordinator-empty">
                        No tournaments available.
                    </div>

                <?php endif; ?>

            </div>


            <!-- ACTIONS -->

            <div class="coordinator-panel">

                <div class="coordinator-panel-header">

                    <div>

                        <h2 class="coordinator-panel-title">
                            Coordinator Tools
                        </h2>

                        <p class="coordinator-panel-subtitle">
                            Manage your sports operations
                        </p>

                    </div>

                </div>


                <div class="coordinator-actions">

                    <a
                        href="admin-tournaments.php"
                        class="coordinator-action"
                    >

                        <span class="coordinator-action-icon">
                            🏆
                        </span>

                        <span class="coordinator-action-content">

                            <span class="coordinator-action-title">
                                Manage Tournaments
                            </span>

                            <span class="coordinator-action-description">
                                Create and manage competitions
                            </span>

                        </span>

                        <span class="coordinator-arrow">
                            →
                        </span>

                    </a>


                    <a
                        href="admin-teams.php"
                        class="coordinator-action"
                    >

                        <span class="coordinator-action-icon">
                            👥
                        </span>

                        <span class="coordinator-action-content">

                            <span class="coordinator-action-title">
                                Manage Teams
                            </span>

                            <span class="coordinator-action-description">
                                View teams and player membership
                            </span>

                        </span>

                        <span class="coordinator-arrow">
                            →
                        </span>

                    </a>


                    <a
                        href="match-results.php"
                        class="coordinator-action"
                    >

                        <span class="coordinator-action-icon">
                            📝
                        </span>

                        <span class="coordinator-action-content">

                            <span class="coordinator-action-title">
                                Match Results
                            </span>

                            <span class="coordinator-action-description">
                                Enter and manage match results
                            </span>

                        </span>

                        <span class="coordinator-arrow">
                            →
                        </span>

                    </a>


                    <a
                        href="tournament-standings.php"
                        class="coordinator-action"
                    >

                        <span class="coordinator-action-icon">
                            📊
                        </span>

                        <span class="coordinator-action-content">

                            <span class="coordinator-action-title">
                                Tournament Standings
                            </span>

                            <span class="coordinator-action-description">
                                View competition standings
                            </span>

                        </span>

                        <span class="coordinator-arrow">
                            →
                        </span>

                    </a>

                </div>

            </div>

        </section>


        <!-- MATCH OVERVIEW -->

        <section class="coordinator-panel">

            <div class="coordinator-panel-header">

                <div>

                    <h2 class="coordinator-panel-title">
                        Recent Matches
                    </h2>

                    <p class="coordinator-panel-subtitle">
                        Latest scheduled and completed matches
                    </p>

                </div>

                <a
                    href="match-results.php"
                    class="coordinator-panel-link"
                >
                    Manage Results →
                </a>

            </div>


            <?php if (!empty($recentMatches)): ?>

                <div class="coordinator-table-wrap">

                    <table class="coordinator-table">

                        <thead>

                            <tr>
                                <th>Match</th>
                                <th>Tournament</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($recentMatches as $match): ?>

                            <tr>

                                <td>

                                    <div class="coordinator-name">

                                        Match
                                        #<?= e(
                                            (string) (
                                                $match['match_number']
                                                ?? $match['match_id']
                                            )
                                        ); ?>

                                    </div>

                                    <div class="coordinator-meta">

                                        <?= e(
                                            $match['home_team']
                                            ?? 'Home Team'
                                        ); ?>

                                        vs

                                        <?= e(
                                            $match['away_team']
                                            ?? 'Away Team'
                                        ); ?>

                                    </div>

                                </td>

                                <td>
                                    <?= e(
                                        $match['tournament_name']
                                        ?? 'Tournament'
                                    ); ?>
                                </td>

                                <td>
                                    <?= e(
                                        $match['match_date']
                                        ?? ''
                                    ); ?>
                                </td>

                                <td>

                                    <span
                                        class="coordinator-status <?= coordinatorStatusClass(
                                            $match['match_status']
                                            ?? ''
                                        ); ?>"
                                    >
                                        <?= e(
                                            $match['match_status']
                                            ?? 'N/A'
                                        ); ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="coordinator-empty">
                    No matches are available yet.
                </div>

            <?php endif; ?>

        </section>


        <!-- SUMMARY -->

        <section class="coordinator-stats">

            <article class="coordinator-stat">

                <div class="coordinator-stat-icon">
                    📅
                </div>

                <p class="coordinator-stat-number">
                    <?= $upcomingMatches; ?>
                </p>

                <p class="coordinator-stat-title">
                    Upcoming Matches
                </p>

            </article>


            <article class="coordinator-stat">

                <div class="coordinator-stat-icon">
                    ✅
                </div>

                <p class="coordinator-stat-number">
                    <?= $completedMatches; ?>
                </p>

                <p class="coordinator-stat-title">
                    Completed Matches
                </p>

            </article>


            <article class="coordinator-stat">

                <div class="coordinator-stat-icon">
                    📊
                </div>

                <p class="coordinator-stat-number">
                    <?= $totalMatches > 0
                        ? round(
                            ($completedMatches / $totalMatches) * 100
                        )
                        : 0; ?>%
                </p>

                <p class="coordinator-stat-title">
                    Matches Completed
                </p>

            </article>


            <article class="coordinator-stat">

                <div class="coordinator-stat-icon">
                    🚀
                </div>

                <p class="coordinator-stat-number">
                    SportSync
                </p>

                <p class="coordinator-stat-title">
                    Sports Management
                </p>

            </article>

        </section>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>