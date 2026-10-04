<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../config/database.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

/*
|--------------------------------------------------------------------------
| Get Teams
|--------------------------------------------------------------------------
*/
$statement = $pdo->query(
    'SELECT
        t.team_id,
        t.team_name,
        t.team_category,
        t.roster_limit,
        t.team_status,
        s.sport_name,
        u.full_name AS coach_name,
        COUNT(
            CASE
                WHEN tp.membership_status = "ACTIVE"
                THEN tp.team_player_id
            END
        ) AS current_players
     FROM teams t
     INNER JOIN sports s
        ON s.sport_id = t.sport_id
     LEFT JOIN coach_profiles cp
        ON cp.coach_id = t.coach_id
     LEFT JOIN users u
        ON u.user_id = cp.user_id
     LEFT JOIN team_players tp
        ON tp.team_id = t.team_id
     GROUP BY
        t.team_id,
        t.team_name,
        t.team_category,
        t.roster_limit,
        t.team_status,
        s.sport_name,
        u.full_name
     ORDER BY
        s.sport_name ASC,
        t.team_name ASC'
);

$teams = $statement->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/
$message = $_GET['message'] ?? '';

/*
|--------------------------------------------------------------------------
| Summary information
|--------------------------------------------------------------------------
*/
$totalTeams = count($teams);
$activeTeams = 0;
$totalPlayers = 0;
$teamsWithoutCoach = 0;

foreach ($teams as $team) {

    $teamStatus = strtoupper(
        (string) $team['team_status']
    );

    if ($teamStatus === 'ACTIVE') {
        $activeTeams++;
    }

    $totalPlayers += (int) $team['current_players'];

    if (empty($team['coach_name'])) {
        $teamsWithoutCoach++;
    }
}

/*
|--------------------------------------------------------------------------
| Escape helper
|--------------------------------------------------------------------------
*/
function adminTeamsEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$pageTitle = 'Team Management';

require_once __DIR__ . '/../includes/header.php';

?>

<style>

/* =========================================================
   TEAM MANAGEMENT PAGE
   ========================================================= */

.team-management-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.team-management-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 20px;
}

.team-management-header-content {
    flex: 1;
}

.team-management-label {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 999px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    margin-bottom: 10px;
}

.team-management-header h1 {
    margin: 0 0 8px;
    color: #111827;
    font-size: 30px;
    line-height: 1.2;
}

.team-management-header p {
    margin: 0;
    max-width: 720px;
    color: #6b7280;
    font-size: 15px;
    line-height: 1.6;
}

.team-management-header-icon {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 18px;
    background: #eff6ff;
    font-size: 30px;
}


/* =========================================================
   SUCCESS MESSAGE
   ========================================================= */

.team-success-message {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding: 14px 16px;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    background: #f0fdf4;
    color: #166534;
}

.team-success-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 50%;
    background: #dcfce7;
    font-size: 16px;
}

.team-success-message strong {
    font-size: 14px;
}


/* =========================================================
   WORKFLOW CARD
   ========================================================= */

.team-workflow-card {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 20px;
    padding: 16px 18px;
    border: 1px solid #dbeafe;
    border-radius: 14px;
    background: #f8fbff;
}

.team-workflow-icon {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 11px;
    background: #ffffff;
    font-size: 21px;
}

.team-workflow-content strong {
    display: block;
    margin-bottom: 4px;
    color: #111827;
    font-size: 14px;
}

.team-workflow-content p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
}

.team-workflow-steps {
    color: #2563eb;
    font-weight: 600;
}


/* =========================================================
   SUMMARY CARDS
   ========================================================= */

.team-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.team-summary-card {
    display: flex;
    align-items: center;
    gap: 13px;
    min-height: 82px;
    padding: 16px;
    box-sizing: border-box;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 5px 18px rgba(15, 23, 42, 0.04);
}

.team-summary-icon {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 11px;
    background: #eff6ff;
    font-size: 20px;
}

.team-summary-card strong {
    display: block;
    margin-bottom: 3px;
    color: #111827;
    font-size: 22px;
    line-height: 1;
}

.team-summary-card span {
    color: #64748b;
    font-size: 12px;
}


/* =========================================================
   ACTION BAR
   ========================================================= */

.team-management-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.team-management-actions-left {
    color: #64748b;
    font-size: 13px;
}

.create-team-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    padding: 10px 18px;
    border: 1px solid #2563eb;
    border-radius: 10px;
    background: #2563eb;
    color: #ffffff;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition:
        background 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.create-team-button:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
    transform: translateY(-1px);
    box-shadow: 0 7px 18px rgba(37, 99, 235, 0.18);
}


/* =========================================================
   TEAMS CARD
   ========================================================= */

.teams-card {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

.teams-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 22px 24px;
    border-bottom: 1px solid #e5e7eb;
}

.teams-card-header-content h2 {
    margin: 0 0 5px;
    color: #111827;
    font-size: 20px;
}

.teams-card-header-content p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.5;
}

.team-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    height: 34px;
    padding: 0 12px;
    border-radius: 999px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 13px;
    font-weight: 700;
}


/* =========================================================
   SEARCH
   ========================================================= */

.teams-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 16px 24px;
    border-bottom: 1px solid #eef2f7;
    background: #ffffff;
}

.team-search-box {
    position: relative;
    width: min(350px, 100%);
}

.team-search-box input {
    width: 100%;
    height: 42px;
    box-sizing: border-box;
    padding: 0 14px 0 40px;
    border: 1px solid #dbe2ea;
    border-radius: 10px;
    outline: none;
    background: #ffffff;
    color: #111827;
    font-size: 14px;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.team-search-box input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
}

.team-search-icon {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 15px;
    pointer-events: none;
}

.team-search-info {
    color: #64748b;
    font-size: 13px;
}


/* =========================================================
   TABLE
   ========================================================= */

.teams-table-wrapper {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.teams-table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
}

.teams-table thead th {
    padding: 14px 16px;
    border-bottom: 1px solid #e5e7eb;
    background: #f8fafc;
    color: #475569;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.teams-table tbody td {
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #374151;
    font-size: 14px;
    vertical-align: middle;
}

.teams-table tbody tr:last-child td {
    border-bottom: none;
}

.teams-table tbody tr {
    transition: background 0.15s ease;
}

.teams-table tbody tr:hover {
    background: #f8fafc;
}


/* =========================================================
   TEAM INFORMATION
   ========================================================= */

.team-id {
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.team-name-cell {
    min-width: 190px;
}

.team-name {
    display: block;
    color: #111827;
    font-weight: 700;
}

.team-name-subtitle {
    display: block;
    margin-top: 3px;
    color: #94a3b8;
    font-size: 12px;
}


/* =========================================================
   BADGES
   ========================================================= */

.sport-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 8px;
    background: #f1f5f9;
    color: #334155;
    font-size: 12px;
    font-weight: 600;
}

.category-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 8px;
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
    font-size: 12px;
    font-weight: 600;
}


/* =========================================================
   COACH
   ========================================================= */

.coach-name {
    color: #374151;
    font-weight: 500;
}

.coach-unassigned {
    display: inline-flex;
    align-items: center;
    padding: 5px 9px;
    border-radius: 7px;
    background: #fff7ed;
    color: #c2410c;
    font-size: 12px;
    font-weight: 600;
}


/* =========================================================
   PLAYERS
   ========================================================= */

.player-count {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #374151;
    font-weight: 600;
}


/* =========================================================
   ROSTER
   ========================================================= */

.roster-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 90px;
}

.roster-number {
    color: #111827;
    font-weight: 600;
    font-size: 13px;
}

.roster-bar {
    width: 80px;
    height: 5px;
    overflow: hidden;
    border-radius: 999px;
    background: #e5e7eb;
}

.roster-bar-fill {
    height: 100%;
    border-radius: 999px;
    background: #2563eb;
}


/* =========================================================
   STATUS
   ========================================================= */

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.status-active {
    background: #dcfce7;
    color: #15803d;
}

.status-inactive {
    background: #fef2f2;
    color: #b91c1c;
}

.status-other {
    background: #f1f5f9;
    color: #475569;
}


/* =========================================================
   MANAGE BUTTON
   ========================================================= */

.manage-team-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 8px 13px;
    border: 1px solid #dbeafe;
    border-radius: 8px;
    background: #eff6ff;
    color: #2563eb;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    transition:
        background 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease;
}

.manage-team-button:hover {
    background: #dbeafe;
    border-color: #bfdbfe;
    transform: translateY(-1px);
}


/* =========================================================
   EMPTY STATE
   ========================================================= */

.teams-empty-state {
    padding: 60px 25px;
    text-align: center;
}

.teams-empty-icon {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    border-radius: 18px;
    background: #eff6ff;
    font-size: 28px;
}

.teams-empty-state h3 {
    margin: 0 0 7px;
    color: #111827;
    font-size: 18px;
}

.teams-empty-state p {
    max-width: 450px;
    margin: 0 auto 20px;
    color: #6b7280;
    font-size: 14px;
    line-height: 1.6;
}


/* =========================================================
   NO SEARCH RESULTS
   ========================================================= */

.no-team-results {
    padding: 50px 20px;
    text-align: center;
}

.no-team-results-icon {
    width: 54px;
    height: 54px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    border-radius: 50%;
    background: #f1f5f9;
    font-size: 23px;
}

.no-team-results strong {
    display: block;
    margin-bottom: 5px;
    color: #111827;
}

.no-team-results p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1000px) {

    .team-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {

    .team-management-page {
        padding: 16px;
    }

    .team-management-header {
        align-items: flex-start;
    }

    .team-management-header h1 {
        font-size: 25px;
    }

    .team-management-header-icon {
        width: 52px;
        height: 52px;
        font-size: 25px;
    }

    .team-workflow-card {
        align-items: flex-start;
    }

    .team-summary-grid {
        grid-template-columns: 1fr;
    }

    .team-management-actions {
        align-items: stretch;
    }

    .team-management-actions-left {
        width: 100%;
    }

    .create-team-button {
        width: 100%;
    }

    .teams-card-header {
        padding: 18px;
    }

    .teams-toolbar {
        align-items: stretch;
        flex-direction: column;
        padding: 16px 18px;
    }

    .team-search-box {
        width: 100%;
    }
}

</style>


<div class="dashboard-page">

    <div class="team-management-page">


        <!-- =========================================================
             PAGE HEADER
             ========================================================= -->

        <section class="team-management-header">

            <div class="team-management-header-content">

                <div class="team-management-label">
                    TEAM MANAGEMENT
                </div>

                <h1>
                    Teams
                </h1>

                <p>
                    Create teams, organize players, assign coaches,
                    and keep track of every team roster in SportSync.
                </p>

            </div>

            <div class="team-management-header-icon">
                👥
            </div>

        </section>


        <!-- =========================================================
             SUCCESS MESSAGE
             ========================================================= -->

        <?php if ($message === 'created'): ?>

            <div class="team-success-message">

                <div class="team-success-icon">
                    ✓
                </div>

                <div>
                    <strong>
                        Team created successfully.
                    </strong>
                </div>

            </div>

        <?php endif; ?>


        <!-- =========================================================
             SIMPLE TEAM WORKFLOW
             ========================================================= -->

        <div class="team-workflow-card">

            <div class="team-workflow-icon">
                🏆
            </div>

            <div class="team-workflow-content">

                <strong>
                    Team Management Flow
                </strong>

                <p>
                    <span class="team-workflow-steps">
                        Create Team
                    </span>
                    →
                    Select Sport
                    →
                    Add Players
                    →
                    Assign Coach
                    →
                    Check Roster
                    →
                    Team Ready
                </p>

            </div>

        </div>


        <!-- =========================================================
             SUMMARY
             ========================================================= -->

        <section class="team-summary-grid">


            <!-- Total Teams -->
            <div class="team-summary-card">

                <div class="team-summary-icon">
                    👥
                </div>

                <div>

                    <strong>
                        <?= $totalTeams ?>
                    </strong>

                    <span>
                        Total Teams
                    </span>

                </div>

            </div>


            <!-- Active Teams -->
            <div class="team-summary-card">

                <div class="team-summary-icon">
                    ✓
                </div>

                <div>

                    <strong>
                        <?= $activeTeams ?>
                    </strong>

                    <span>
                        Active Teams
                    </span>

                </div>

            </div>


            <!-- Players -->
            <div class="team-summary-card">

                <div class="team-summary-icon">
                    🧑‍🤝‍🧑
                </div>

                <div>

                    <strong>
                        <?= $totalPlayers ?>
                    </strong>

                    <span>
                        Active Team Players
                    </span>

                </div>

            </div>


            <!-- Coach Assignment -->
            <div class="team-summary-card">

                <div class="team-summary-icon">
                    🧑‍🏫
                </div>

                <div>

                    <strong>
                        <?= $teamsWithoutCoach ?>
                    </strong>

                    <span>
                        Teams Without Coach
                    </span>

                </div>

            </div>

        </section>


        <!-- =========================================================
             ACTION BAR
             ========================================================= -->

        <div class="team-management-actions">

            <div class="team-management-actions-left">
                Manage team structure before registering teams for
                competitions.
            </div>

            <a
                href="create-team.php"
                class="create-team-button"
            >
                <span>＋</span>
                Create New Team
            </a>

        </div>


        <!-- =========================================================
             EXISTING TEAMS
             ========================================================= -->

        <section class="teams-card">


            <!-- CARD HEADER -->
            <div class="teams-card-header">

                <div class="teams-card-header-content">

                    <h2>
                        Existing Teams
                    </h2>

                    <p>
                        View sport, coach, players, roster capacity,
                        and team status.
                    </p>

                </div>

                <div class="team-count-badge">
                    <?= $totalTeams ?>
                </div>

            </div>


            <?php if (empty($teams)): ?>


                <!-- =================================================
                     EMPTY STATE
                     ================================================= -->

                <div class="teams-empty-state">

                    <div class="teams-empty-icon">
                        👥
                    </div>

                    <h3>
                        No teams found
                    </h3>

                    <p>
                        Create your first sports team to start
                        organizing players and coaches.
                    </p>

                    <a
                        href="create-team.php"
                        class="create-team-button"
                    >
                        ＋ Create New Team
                    </a>

                </div>


            <?php else: ?>


                <!-- =================================================
                     SEARCH TOOLBAR
                     ================================================= -->

                <div class="teams-toolbar">

                    <div class="team-search-box">

                        <span class="team-search-icon">
                            🔎
                        </span>

                        <input
                            type="search"
                            id="teamSearch"
                            placeholder="Search team, sport, coach..."
                            autocomplete="off"
                        >

                    </div>

                    <div
                        class="team-search-info"
                        id="teamSearchInfo"
                    >
                        Showing <?= $totalTeams ?> team<?= $totalTeams === 1 ? '' : 's' ?>
                    </div>

                </div>


                <!-- =================================================
                     TABLE
                     ================================================= -->

                <div class="teams-table-wrapper">

                    <table
                        class="teams-table"
                        id="teamsTable"
                    >

                        <thead>

                            <tr>

                                <th>
                                    ID
                                </th>

                                <th>
                                    Team
                                </th>

                                <th>
                                    Sport
                                </th>

                                <th>
                                    Category
                                </th>

                                <th>
                                    Coach
                                </th>

                                <th>
                                    Players
                                </th>

                                <th>
                                    Roster
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($teams as $team): ?>

                            <?php

                            $currentPlayers =
                                (int) $team['current_players'];

                            $rosterLimit =
                                $team['roster_limit'] !== null
                                    ? (int) $team['roster_limit']
                                    : null;

                            $teamStatus = strtoupper(
                                (string) $team['team_status']
                            );


                            /*
                             * Calculate roster percentage.
                             */
                            if (
                                $rosterLimit !== null
                                && $rosterLimit > 0
                            ) {

                                $rosterPercentage = min(
                                    100,
                                    (
                                        $currentPlayers
                                        / $rosterLimit
                                    ) * 100
                                );

                            } else {

                                $rosterPercentage = 0;
                            }


                            /*
                             * Status class.
                             */
                            if ($teamStatus === 'ACTIVE') {

                                $statusClass = 'status-active';

                            } elseif (
                                in_array(
                                    $teamStatus,
                                    [
                                        'INACTIVE',
                                        'SUSPENDED'
                                    ],
                                    true
                                )
                            ) {

                                $statusClass = 'status-inactive';

                            } else {

                                $statusClass = 'status-other';
                            }

                            ?>

                            <tr class="team-row">


                                <!-- ID -->
                                <td>

                                    <span class="team-id">
                                        #<?= (int) $team['team_id'] ?>
                                    </span>

                                </td>


                                <!-- TEAM -->
                                <td class="team-name-cell">

                                    <span class="team-name">
                                        <?= adminTeamsEscape(
                                            $team['team_name']
                                        ) ?>
                                    </span>

                                    <span class="team-name-subtitle">
                                        SportSync Team
                                    </span>

                                </td>


                                <!-- SPORT -->
                                <td>

                                    <span class="sport-badge">
                                        <?= adminTeamsEscape(
                                            $team['sport_name']
                                        ) ?>
                                    </span>

                                </td>


                                <!-- CATEGORY -->
                                <td>

                                    <span class="category-badge">
                                        <?= adminTeamsEscape(
                                            $team['team_category']
                                        ) ?>
                                    </span>

                                </td>


                                <!-- COACH -->
                                <td>

                                    <?php if (!empty($team['coach_name'])): ?>

                                        <span class="coach-name">
                                            <?= adminTeamsEscape(
                                                $team['coach_name']
                                            ) ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="coach-unassigned">
                                            Not assigned
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- PLAYERS -->
                                <td>

                                    <span class="player-count">
                                        👤
                                        <?= $currentPlayers ?>
                                    </span>

                                </td>


                                <!-- ROSTER -->
                                <td>

                                    <div class="roster-info">

                                        <span class="roster-number">

                                            <?php if ($rosterLimit !== null): ?>

                                                <?= $currentPlayers ?>
                                                /
                                                <?= $rosterLimit ?>

                                            <?php else: ?>

                                                Unlimited

                                            <?php endif; ?>

                                        </span>


                                        <?php if ($rosterLimit !== null): ?>

                                            <div class="roster-bar">

                                                <div
                                                    class="roster-bar-fill"
                                                    style="width: <?= number_format($rosterPercentage, 2, '.', '') ?>%;"
                                                ></div>

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                </td>


                                <!-- STATUS -->
                                <td>

                                    <span
                                        class="status-badge <?= adminTeamsEscape($statusClass) ?>"
                                    >

                                        <span class="status-dot"></span>

                                        <?= adminTeamsEscape(
                                            $teamStatus
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACTION -->
                                <td>

                                    <a
                                        href="manage-team.php?team_id=<?= (int) $team['team_id'] ?>"
                                        class="manage-team-button"
                                    >
                                        Manage →
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <!-- =================================================
                     NO SEARCH RESULTS
                     ================================================= -->

                <div
                    id="noTeamResults"
                    class="no-team-results"
                    hidden
                >

                    <div class="no-team-results-icon">
                        🔎
                    </div>

                    <strong>
                        No teams found
                    </strong>

                    <p>
                        Try searching with a different team name,
                        sport, category, or coach name.
                    </p>

                </div>


            <?php endif; ?>


        </section>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Team Search
|--------------------------------------------------------------------------
|
| Searches the currently displayed team table.
| No database request is required.
|
*/

document.addEventListener('DOMContentLoaded', function () {

    const searchInput =
        document.getElementById('teamSearch');

    const table =
        document.getElementById('teamsTable');

    const noResults =
        document.getElementById('noTeamResults');

    const searchInfo =
        document.getElementById('teamSearchInfo');

    if (!searchInput || !table) {
        return;
    }

    const rows =
        table.querySelectorAll(
            'tbody .team-row'
        );

    const totalRows = rows.length;

    searchInput.addEventListener(
        'input',
        function () {

            const searchTerm =
                searchInput.value
                    .trim()
                    .toLowerCase();

            let visibleRows = 0;

            rows.forEach(function (row) {

                const rowText =
                    row.textContent
                        .toLowerCase();

                const matches =
                    rowText.includes(searchTerm);

                row.style.display =
                    matches ? '' : 'none';

                if (matches) {
                    visibleRows++;
                }

            });


            /*
             * Update search information.
             */
            if (searchInfo) {

                if (searchTerm === '') {

                    searchInfo.textContent =
                        'Showing ' +
                        totalRows +
                        ' team' +
                        (totalRows === 1 ? '' : 's');

                } else {

                    searchInfo.textContent =
                        visibleRows +
                        ' result' +
                        (visibleRows === 1 ? '' : 's') +
                        ' found';

                }
            }


            /*
             * Show empty search state.
             */
            if (noResults) {

                noResults.hidden =
                    visibleRows !== 0;

            }

        }
    );

});

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>