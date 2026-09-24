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

$recentTournaments = [];
$recentMatches = [];

try {
    /*
    |--------------------------------------------------------------------------
    | Dashboard Statistics
    |--------------------------------------------------------------------------
    */

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
        'Coordinator dashboard statistics error: ' .
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| Recent Tournaments
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Recent Matches
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

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

$completionPercentage = $totalMatches > 0
    ? (int) round(
        ($completedMatches / $totalMatches) * 100
    )
    : 0;

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

        /*
        |--------------------------------------------------------------------------
        | Coordinator Dashboard
        |--------------------------------------------------------------------------
        */

        .coordinator-dashboard {
            display: flex;
            flex-direction: column;
            gap: 26px;
        }


        /*
        |--------------------------------------------------------------------------
        | Hero
        |--------------------------------------------------------------------------
        */

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
            max-width: 760px;
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


        /*
        |--------------------------------------------------------------------------
        | Next Step
        |--------------------------------------------------------------------------
        */

        .coordinator-next-step {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 24px;
            border: 1px solid #dbeafe;
            border-left: 5px solid #2563eb;
            border-radius: 18px;
            background: #ffffff;
            box-shadow:
                0 8px 24px
                rgba(20, 35, 65, 0.06);
        }

        .coordinator-next-step-content {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            flex: 1;
        }

        .coordinator-next-step-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            flex: 0 0 auto;
            border-radius: 14px;
            background: #eef4ff;
            font-size: 21px;
        }

        .coordinator-next-step-label {
            margin: 0 0 4px;
            color: #2563eb;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .coordinator-next-step-title {
            margin: 0;
            color: #111827;
            font-size: 18px;
            font-weight: 800;
        }

        .coordinator-next-step-description {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }

        .coordinator-next-step-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 150px;
            padding: 11px 17px;
            border-radius: 10px;
            background: #2563eb;
            color: #ffffff;
            font-size: 13px;
            font-weight: 750;
            text-decoration: none;
            transition:
                background 0.2s ease,
                transform 0.2s ease;
        }

        .coordinator-next-step-button:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .coordinator-next-step.complete {
            border-color: #bbf7d0;
            border-left-color: #16a34a;
        }

        .coordinator-next-step.complete
        .coordinator-next-step-icon {
            background: #ecfdf3;
        }

        .coordinator-next-step.complete
        .coordinator-next-step-label {
            color: #15803d;
        }


        /*
        |--------------------------------------------------------------------------
        | Overview
        |--------------------------------------------------------------------------
        */

        .coordinator-section-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 14px;
        }

        .coordinator-section-heading h2 {
            margin: 0;
            color: #111827;
            font-size: 18px;
            font-weight: 800;
        }

        .coordinator-section-heading p {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 12px;
        }

        .coordinator-stats {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .coordinator-stat {
            padding: 21px;
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
            transform: translateY(-3px);
            box-shadow:
                0 15px 32px
                rgba(20, 35, 65, 0.10);
        }

        .coordinator-stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            margin-bottom: 17px;
            border-radius: 13px;
            background: #eef4ff;
            font-size: 20px;
        }

        .coordinator-stat-number {
            margin: 0;
            color: #111827;
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
        }

        .coordinator-stat-title {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
        }


        /*
        |--------------------------------------------------------------------------
        | Main Grid
        |--------------------------------------------------------------------------
        */

        .coordinator-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 1.45fr)
                minmax(300px, 0.75fr);
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
            padding: 21px 24px;
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


        /*
        |--------------------------------------------------------------------------
        | Tables
        |--------------------------------------------------------------------------
        */

        .coordinator-table-wrap {
            overflow-x: auto;
        }

        .coordinator-table {
            width: 100%;
            min-width: 580px;
            border-collapse: collapse;
        }

        .coordinator-table th {
            padding: 13px 20px;
            background: #f8fafc;
            color: #64748b;
            font-size: 11px;
            font-weight: 750;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .coordinator-table td {
            padding: 15px 20px;
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


        /*
        |--------------------------------------------------------------------------
        | Coordinator Tools
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | Match Progress
        |--------------------------------------------------------------------------
        */

        .coordinator-progress {
            padding: 22px 24px;
        }

        .coordinator-progress-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 10px;
        }

        .coordinator-progress-label {
            color: #334155;
            font-size: 13px;
            font-weight: 700;
        }

        .coordinator-progress-value {
            color: #2563eb;
            font-size: 13px;
            font-weight: 800;
        }

        .coordinator-progress-track {
            width: 100%;
            height: 8px;
            overflow: hidden;
            border-radius: 999px;
            background: #e8eef7;
        }

        .coordinator-progress-bar {
            height: 100%;
            border-radius: inherit;
            background: #2563eb;
        }

        .coordinator-progress-note {
            margin: 9px 0 0;
            color: #94a3b8;
            font-size: 11px;
        }


        /*
        |--------------------------------------------------------------------------
        | Empty State
        |--------------------------------------------------------------------------
        */

        .coordinator-empty {
            padding: 38px 24px;
            color: #94a3b8;
            font-size: 13px;
            text-align: center;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

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

            .coordinator-dashboard {
                gap: 20px;
            }

            .coordinator-hero {
                padding: 24px;
                border-radius: 20px;
            }

            .coordinator-next-step {
                align-items: stretch;
                flex-direction: column;
                padding: 20px;
            }

            .coordinator-next-step-button {
                width: 100%;
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


        <!-- =====================================================
             HERO
             ===================================================== -->

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
                    You coordinate the sports operations of SportSync.
                    Your main work is to manage competitions, teams,
                    matches, results and standings.
                </p>

            </div>

        </section>


        <!-- =====================================================
             YOUR NEXT STEP
             ===================================================== -->

        <?php if ($upcomingMatches > 0): ?>

            <section class="coordinator-next-step">

                <div class="coordinator-next-step-content">

                    <div class="coordinator-next-step-icon">
                        📅
                    </div>

                    <div>

                        <p class="coordinator-next-step-label">
                            Your Next Step
                        </p>

                        <h2 class="coordinator-next-step-title">
                            Review upcoming matches
                        </h2>

                        <p class="coordinator-next-step-description">
                            There are
                            <strong><?= $upcomingMatches; ?></strong>
                            scheduled or upcoming matches.
                            Review the match schedule and manage
                            results as games are completed.
                        </p>

                    </div>

                </div>

                <a
                    href="match-results.php"
                    class="coordinator-next-step-button"
                >
                    Review Matches →
                </a>

            </section>

        <?php else: ?>

            <section class="coordinator-next-step complete">

                <div class="coordinator-next-step-content">

                    <div class="coordinator-next-step-icon">
                        ✅
                    </div>

                    <div>

                        <p class="coordinator-next-step-label">
                            No Immediate Action
                        </p>

                        <h2 class="coordinator-next-step-title">
                            You're all caught up
                        </h2>

                        <p class="coordinator-next-step-description">
                            There are no scheduled or upcoming matches
                            requiring immediate attention.
                            You can review tournaments, teams or standings.
                        </p>

                    </div>

                </div>

                <a
                    href="admin-tournaments.php"
                    class="coordinator-next-step-button"
                >
                    View Tournaments →
                </a>

            </section>

        <?php endif; ?>


        <!-- =====================================================
             OVERVIEW
             ===================================================== -->

        <section>

            <div class="coordinator-section-heading">

                <div>

                    <h2>
                        Sports Operations Overview
                    </h2>

                    <p>
                        Current activity across SportSync
                    </p>

                </div>

            </div>

            <div class="coordinator-stats">

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

            </div>

        </section>


        <!-- =====================================================
             CURRENT WORK
             ===================================================== -->

        <section class="coordinator-grid">


            <!-- =================================================
                 RECENT TOURNAMENTS
                 ================================================= -->

            <div class="coordinator-panel">

                <div class="coordinator-panel-header">

                    <div>

                        <h2 class="coordinator-panel-title">
                            Recent Tournaments
                        </h2>

                        <p class="coordinator-panel-subtitle">
                            Latest competitions created in SportSync
                        </p>

                    </div>

                    <a
                        href="admin-tournaments.php"
                        class="coordinator-panel-link"
                    >
                        View All →
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

                        No tournaments available yet.

                    </div>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 COORDINATOR TOOLS
                 ================================================= -->

            <div class="coordinator-panel">

                <div class="coordinator-panel-header">

                    <div>

                        <h2 class="coordinator-panel-title">
                            Coordinator Tools
                        </h2>

                        <p class="coordinator-panel-subtitle">
                            Go directly to your main tasks
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
                                Check competition standings
                            </span>

                        </span>

                        <span class="coordinator-arrow">
                            →
                        </span>

                    </a>


                </div>

            </div>

        </section>


        <!-- =====================================================
             MATCH ACTIVITY
             ===================================================== -->

        <section class="coordinator-panel">

            <div class="coordinator-panel-header">

                <div>

                    <h2 class="coordinator-panel-title">
                        Match Activity
                    </h2>

                    <p class="coordinator-panel-subtitle">
                        Recent scheduled and completed matches
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

                                        Match #

                                        <?= e(
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


        <!-- =====================================================
             MATCH PROGRESS
             ===================================================== -->

        <section class="coordinator-panel">

            <div class="coordinator-panel-header">

                <div>

                    <h2 class="coordinator-panel-title">
                        Competition Progress
                    </h2>

                    <p class="coordinator-panel-subtitle">
                        Match completion overview
                    </p>

                </div>

            </div>


            <div class="coordinator-progress">

                <div class="coordinator-progress-top">

                    <span class="coordinator-progress-label">
                        Matches Completed
                    </span>

                    <span class="coordinator-progress-value">
                        <?= $completionPercentage; ?>%
                    </span>

                </div>


                <div class="coordinator-progress-track">

                    <div
                        class="coordinator-progress-bar"
                        style="width: <?= $completionPercentage; ?>%;"
                    ></div>

                </div>


                <p class="coordinator-progress-note">

                    <?= $completedMatches; ?>
                    completed out of
                    <?= $totalMatches; ?>
                    total matches.

                    <?php if ($upcomingMatches > 0): ?>

                        <?= $upcomingMatches; ?>
                        upcoming matches remain.

                    <?php endif; ?>

                </p>

            </div>

        </section>


    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>