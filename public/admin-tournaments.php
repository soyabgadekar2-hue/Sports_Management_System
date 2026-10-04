<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

$pageTitle = 'Tournament Management';

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

/*
|--------------------------------------------------------------------------
| Get Tournaments
|--------------------------------------------------------------------------
|
| tournament_format is the competition structure such as League,
| Knockout, etc.
|
| Sport event formats such as 100m Running, Singles, Doubles,
| 4x100m Relay, etc. are managed separately through sport_events.
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        t.tournament_id,
        t.tournament_name,
        t.start_date,
        t.end_date,
        t.tournament_format,
        t.points_win,
        t.points_draw,
        t.points_loss,
        t.tournament_status,
        s.sport_name,
        v.venue_name,
        COUNT(DISTINCT tt.tournament_team_id) AS team_count
    FROM tournaments t
    INNER JOIN sports s
        ON s.sport_id = t.sport_id
    LEFT JOIN venues v
        ON v.venue_id = t.venue_id
    LEFT JOIN tournament_teams tt
        ON tt.tournament_id = t.tournament_id
        AND tt.participation_status = 'ACTIVE'
    GROUP BY
        t.tournament_id,
        t.tournament_name,
        t.start_date,
        t.end_date,
        t.tournament_format,
        t.points_win,
        t.points_draw,
        t.points_loss,
        t.tournament_status,
        s.sport_name,
        v.venue_name
    ORDER BY
        t.start_date DESC,
        t.tournament_id DESC
";

$stmt = $pdo->query($sql);

$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

$totalTournaments = count($tournaments);

$activeTournaments = 0;
$registrationOpen = 0;
$completedTournaments = 0;
$totalTournamentTeams = 0;

foreach ($tournaments as $tournament) {

    $status = strtoupper(
        (string) $tournament['tournament_status']
    );

    if (
        in_array(
            $status,
            [
                'ACTIVE',
                'ONGOING'
            ],
            true
        )
    ) {
        $activeTournaments++;
    }

    if (
        in_array(
            $status,
            [
                'REGISTRATION_OPEN',
                'OPEN'
            ],
            true
        )
    ) {
        $registrationOpen++;
    }

    if (
        in_array(
            $status,
            [
                'COMPLETED',
                'CLOSED'
            ],
            true
        )
    ) {
        $completedTournaments++;
    }

    $totalTournamentTeams +=
        (int) $tournament['team_count'];
}


/*
|--------------------------------------------------------------------------
| Escape Helper
|--------------------------------------------------------------------------
*/

function adminTournamentsEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Status Helper
|--------------------------------------------------------------------------
*/

function adminTournamentStatusClass(string $status): string
{
    $status = strtoupper($status);

    if (
        in_array(
            $status,
            [
                'ACTIVE',
                'ONGOING',
                'REGISTRATION_OPEN',
                'OPEN'
            ],
            true
        )
    ) {
        return 'badge-success';
    }

    if (
        in_array(
            $status,
            [
                'COMPLETED',
                'CLOSED'
            ],
            true
        )
    ) {
        return 'badge-blue';
    }

    if (
        in_array(
            $status,
            [
                'CANCELLED',
                'REJECTED'
            ],
            true
        )
    ) {
        return 'badge-danger';
    }

    return 'badge-gray';
}


/*
|--------------------------------------------------------------------------
| Date Formatting
|--------------------------------------------------------------------------
*/

function adminTournamentDate(?string $date): string
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    return date('d M Y', $timestamp);
}


require_once __DIR__ . '/../includes/header.php';

?>


<style>

/* =========================================================
   PAGE
   ========================================================= */

.tournament-management-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.tournament-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 20px;
}

.tournament-page-header-content {
    flex: 1;
}

.tournament-page-label {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    margin-bottom: 10px;
    border-radius: 999px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
}

.tournament-page-header h1 {
    margin: 0 0 7px;
    color: #111827;
    font-size: 30px;
    line-height: 1.2;
}

.tournament-page-header p {
    max-width: 750px;
    margin: 0;
    color: #6b7280;
    font-size: 15px;
    line-height: 1.6;
}

.tournament-page-header-icon {
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
   ALERTS
   ========================================================= */

.tournament-alert {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 20px;
    padding: 14px 16px;
    border-radius: 12px;
    font-size: 14px;
}

.tournament-alert-icon {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 50%;
    font-weight: 700;
}

.tournament-alert-success {
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #166534;
}

.tournament-alert-success .tournament-alert-icon {
    background: #dcfce7;
}

.tournament-alert-danger {
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #991b1b;
}

.tournament-alert-danger .tournament-alert-icon {
    background: #fee2e2;
}


/* =========================================================
   WORKFLOW
   ========================================================= */

.tournament-workflow {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 20px;
    padding: 16px 18px;
    border: 1px solid #dbeafe;
    border-radius: 14px;
    background: #f8fbff;
}

.tournament-workflow-icon {
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

.tournament-workflow-content strong {
    display: block;
    margin-bottom: 4px;
    color: #111827;
    font-size: 14px;
}

.tournament-workflow-content p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.6;
}

.workflow-highlight {
    color: #2563eb;
    font-weight: 700;
}


/* =========================================================
   SUMMARY
   ========================================================= */

.tournament-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.tournament-summary-card {
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

.tournament-summary-icon {
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

.tournament-summary-card strong {
    display: block;
    margin-bottom: 3px;
    color: #111827;
    font-size: 22px;
    line-height: 1;
}

.tournament-summary-card span {
    color: #64748b;
    font-size: 12px;
}


/* =========================================================
   ACTION BAR
   ========================================================= */

.tournament-action-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.tournament-action-help {
    color: #64748b;
    font-size: 13px;
}

.create-tournament-button {
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

.create-tournament-button:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
    transform: translateY(-1px);
    box-shadow: 0 7px 18px rgba(37, 99, 235, 0.18);
}


/* =========================================================
   CARD
   ========================================================= */

.tournaments-card {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

.tournaments-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 22px 24px;
    border-bottom: 1px solid #e5e7eb;
}

.tournaments-card-header h2 {
    margin: 0 0 5px;
    color: #111827;
    font-size: 20px;
}

.tournaments-card-header p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.5;
}

.tournament-count-badge {
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
   TOOLBAR
   ========================================================= */

.tournament-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 16px 24px;
    border-bottom: 1px solid #eef2f7;
}

.tournament-search-box {
    position: relative;
    width: min(360px, 100%);
}

.tournament-search-box input {
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

.tournament-search-box input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
}

.tournament-search-icon {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 15px;
    pointer-events: none;
}

.tournament-search-info {
    color: #64748b;
    font-size: 13px;
}


/* =========================================================
   TABLE
   ========================================================= */

.tournament-table-wrapper {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.tournament-table {
    width: 100%;
    min-width: 1150px;
    border-collapse: collapse;
}

.tournament-table thead th {
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

.tournament-table tbody td {
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #374151;
    font-size: 14px;
    vertical-align: middle;
}

.tournament-table tbody tr {
    transition: background 0.15s ease;
}

.tournament-table tbody tr:hover {
    background: #f8fafc;
}

.tournament-table tbody tr:last-child td {
    border-bottom: none;
}


/* =========================================================
   TOURNAMENT NAME
   ========================================================= */

.tournament-name {
    min-width: 200px;
    color: #111827;
    font-weight: 700;
}

.tournament-id {
    margin-top: 4px;
    color: #94a3b8;
    font-size: 11px;
}


/* =========================================================
   BADGES
   ========================================================= */

.badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.badge-blue {
    background: #eff6ff;
    color: #2563eb;
}

.badge-gray {
    background: #f1f5f9;
    color: #475569;
}

.badge-success {
    background: #dcfce7;
    color: #15803d;
}

.badge-danger {
    background: #fef2f2;
    color: #b91c1c;
}


/* =========================================================
   DATES
   ========================================================= */

.date-primary {
    color: #111827;
    font-weight: 600;
    white-space: nowrap;
}

.date-secondary {
    display: block;
    margin-top: 4px;
    color: #64748b;
    font-size: 12px;
    white-space: nowrap;
}


/* =========================================================
   POINTS
   ========================================================= */

.points-list {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 55px;
    color: #64748b;
    font-size: 12px;
}

.points-list strong {
    color: #334155;
}


/* =========================================================
   TEAMS
   ========================================================= */

.team-count {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #111827;
    font-weight: 700;
}


/* =========================================================
   ACTION
   ========================================================= */

.manage-tournament-button {
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
    white-space: nowrap;
    transition:
        background 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease;
}

.manage-tournament-button:hover {
    background: #dbeafe;
    border-color: #bfdbfe;
    transform: translateY(-1px);
}


/* =========================================================
   EMPTY STATE
   ========================================================= */

.tournaments-empty-state {
    padding: 60px 25px;
    text-align: center;
}

.tournaments-empty-icon {
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

.tournaments-empty-state h3 {
    margin: 0 0 7px;
    color: #111827;
    font-size: 18px;
}

.tournaments-empty-state p {
    max-width: 450px;
    margin: 0 auto 20px;
    color: #6b7280;
    font-size: 14px;
    line-height: 1.6;
}


/* =========================================================
   SEARCH EMPTY STATE
   ========================================================= */

.no-tournament-results {
    padding: 50px 20px;
    text-align: center;
}

.no-tournament-results-icon {
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

.no-tournament-results strong {
    display: block;
    margin-bottom: 5px;
    color: #111827;
}

.no-tournament-results p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 1000px) {

    .tournament-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {

    .tournament-management-page {
        padding: 16px;
    }

    .tournament-page-header {
        align-items: flex-start;
    }

    .tournament-page-header h1 {
        font-size: 25px;
    }

    .tournament-page-header-icon {
        width: 52px;
        height: 52px;
        font-size: 25px;
    }

    .tournament-summary-grid {
        grid-template-columns: 1fr;
    }

    .tournament-action-bar {
        align-items: stretch;
    }

    .tournament-action-help {
        width: 100%;
    }

    .create-tournament-button {
        width: 100%;
    }

    .tournaments-card-header {
        padding: 18px;
    }

    .tournament-toolbar {
        align-items: stretch;
        flex-direction: column;
        padding: 16px 18px;
    }

    .tournament-search-box {
        width: 100%;
    }

}

</style>


<div class="dashboard-page">

    <div class="tournament-management-page">


        <!-- =========================================================
             PAGE HEADER
             ========================================================= -->

        <section class="tournament-page-header">

            <div class="tournament-page-header-content">

                <div class="tournament-page-label">
                    COMPETITION MANAGEMENT
                </div>

                <h1>
                    Tournaments
                </h1>

                <p>
                    Create and manage college competitions, add teams,
                    and prepare tournaments for matches and results.
                </p>

            </div>

            <div class="tournament-page-header-icon">
                🏆
            </div>

        </section>


        <!-- =========================================================
             SUCCESS / ERROR
             ========================================================= -->

        <?php if ($message === 'created'): ?>

            <div class="tournament-alert tournament-alert-success">

                <div class="tournament-alert-icon">
                    ✓
                </div>

                <strong>
                    Tournament created successfully.
                </strong>

            </div>

        <?php elseif ($message === 'updated'): ?>

            <div class="tournament-alert tournament-alert-success">

                <div class="tournament-alert-icon">
                    ✓
                </div>

                <strong>
                    Tournament updated successfully.
                </strong>

            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div class="tournament-alert tournament-alert-danger">

                <div class="tournament-alert-icon">
                    !
                </div>

                <strong>
                    <?= adminTournamentsEscape($error) ?>
                </strong>

            </div>

        <?php endif; ?>


        <!-- =========================================================
             WORKFLOW
             ========================================================= -->

        <div class="tournament-workflow">

            <div class="tournament-workflow-icon">
                📋
            </div>

            <div class="tournament-workflow-content">

                <strong>
                    Tournament Workflow
                </strong>

                <p>

                    <span class="workflow-highlight">
                        Create Tournament
                    </span>

                    →
                    Select Sport
                    →
                    Add Teams
                    →
                    Schedule Matches
                    →
                    Record Results
                    →
                    View Standings

                </p>

            </div>

        </div>


        <!-- =========================================================
             SUMMARY
             ========================================================= -->

        <section class="tournament-summary-grid">


            <!-- Total -->
            <div class="tournament-summary-card">

                <div class="tournament-summary-icon">
                    🏆
                </div>

                <div>

                    <strong>
                        <?= $totalTournaments ?>
                    </strong>

                    <span>
                        Total Tournaments
                    </span>

                </div>

            </div>


            <!-- Active -->
            <div class="tournament-summary-card">

                <div class="tournament-summary-icon">
                    ▶
                </div>

                <div>

                    <strong>
                        <?= $activeTournaments ?>
                    </strong>

                    <span>
                        Active / Ongoing
                    </span>

                </div>

            </div>


            <!-- Registration -->
            <div class="tournament-summary-card">

                <div class="tournament-summary-icon">
                    📝
                </div>

                <div>

                    <strong>
                        <?= $registrationOpen ?>
                    </strong>

                    <span>
                        Registration Open
                    </span>

                </div>

            </div>


            <!-- Teams -->
            <div class="tournament-summary-card">

                <div class="tournament-summary-icon">
                    👥
                </div>

                <div>

                    <strong>
                        <?= $totalTournamentTeams ?>
                    </strong>

                    <span>
                        Active Tournament Teams
                    </span>

                </div>

            </div>

        </section>


        <!-- =========================================================
             ACTION BAR
             ========================================================= -->

        <div class="tournament-action-bar">

            <div class="tournament-action-help">

                Use tournaments for team-based competitions.
                Individual event formats such as Athletics and
                Singles are handled through the sport event structure.

            </div>

            <a
                href="create-tournament.php"
                class="create-tournament-button"
            >
                <span>＋</span>
                Create New Tournament
            </a>

        </div>


        <!-- =========================================================
             TOURNAMENTS CARD
             ========================================================= -->

        <section class="tournaments-card">


            <!-- HEADER -->

            <div class="tournaments-card-header">

                <div>

                    <h2>
                        Existing Tournaments
                    </h2>

                    <p>
                        View sport, venue, dates, tournament structure,
                        participating teams, and status.
                    </p>

                </div>

                <div class="tournament-count-badge">
                    <?= $totalTournaments ?>
                </div>

            </div>


            <?php if (empty($tournaments)): ?>


                <!-- =================================================
                     EMPTY STATE
                     ================================================= -->

                <div class="tournaments-empty-state">

                    <div class="tournaments-empty-icon">
                        🏆
                    </div>

                    <h3>
                        No tournaments found
                    </h3>

                    <p>
                        Create your first tournament to start the
                        competition management workflow.
                    </p>

                    <a
                        href="create-tournament.php"
                        class="create-tournament-button"
                    >
                        ＋ Create Tournament
                    </a>

                </div>


            <?php else: ?>


                <!-- =================================================
                     SEARCH
                     ================================================= -->

                <div class="tournament-toolbar">

                    <div class="tournament-search-box">

                        <span class="tournament-search-icon">
                            🔎
                        </span>

                        <input
                            type="search"
                            id="tournamentSearch"
                            placeholder="Search tournament, sport, venue..."
                            autocomplete="off"
                        >

                    </div>

                    <div
                        class="tournament-search-info"
                        id="tournamentSearchInfo"
                    >
                        Showing
                        <?= $totalTournaments ?>
                        tournament<?= $totalTournaments === 1 ? '' : 's' ?>
                    </div>

                </div>


                <!-- =================================================
                     TABLE
                     ================================================= -->

                <div class="tournament-table-wrapper">

                    <table
                        class="tournament-table"
                        id="tournamentTable"
                    >

                        <thead>

                            <tr>

                                <th>
                                    ID
                                </th>

                                <th>
                                    Tournament
                                </th>

                                <th>
                                    Sport
                                </th>

                                <th>
                                    Venue
                                </th>

                                <th>
                                    Dates
                                </th>

                                <th>
                                    Structure
                                </th>

                                <th>
                                    Points
                                </th>

                                <th>
                                    Teams
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

                        <?php foreach ($tournaments as $tournament): ?>

                            <?php

                            $status = strtoupper(
                                (string) $tournament['tournament_status']
                            );

                            $statusClass =
                                adminTournamentStatusClass(
                                    $status
                                );

                            $teamCount =
                                (int) $tournament['team_count'];

                            ?>

                            <tr class="tournament-row">


                                <!-- ID -->

                                <td>

                                    <span>
                                        #<?= (int) $tournament['tournament_id'] ?>
                                    </span>

                                </td>


                                <!-- TOURNAMENT -->

                                <td>

                                    <div class="tournament-name">

                                        <?= adminTournamentsEscape(
                                            $tournament['tournament_name']
                                        ) ?>

                                    </div>

                                    <div class="tournament-id">

                                        Tournament ID:
                                        <?= (int) $tournament['tournament_id'] ?>

                                    </div>

                                </td>


                                <!-- SPORT -->

                                <td>

                                    <span class="badge badge-blue">

                                        <?= adminTournamentsEscape(
                                            $tournament['sport_name']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- VENUE -->

                                <td>

                                    <?= adminTournamentsEscape(
                                        $tournament['venue_name']
                                            ?? 'Not assigned'
                                    ) ?>

                                </td>


                                <!-- DATES -->

                                <td>

                                    <span class="date-primary">

                                        <?= adminTournamentsEscape(
                                            adminTournamentDate(
                                                (string) $tournament['start_date']
                                            )
                                        ) ?>

                                    </span>

                                    <span class="date-secondary">

                                        to

                                        <?= adminTournamentsEscape(
                                            adminTournamentDate(
                                                (string) $tournament['end_date']
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- TOURNAMENT STRUCTURE -->

                                <td>

                                    <span class="badge badge-gray">

                                        <?= adminTournamentsEscape(
                                            $tournament['tournament_format']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- POINTS -->

                                <td>

                                    <div class="points-list">

                                        <div>
                                            <strong>W:</strong>
                                            <?= adminTournamentsEscape(
                                                $tournament['points_win']
                                            ) ?>
                                        </div>

                                        <div>
                                            <strong>D:</strong>
                                            <?= adminTournamentsEscape(
                                                $tournament['points_draw']
                                            ) ?>
                                        </div>

                                        <div>
                                            <strong>L:</strong>
                                            <?= adminTournamentsEscape(
                                                $tournament['points_loss']
                                            ) ?>
                                        </div>

                                    </div>

                                </td>


                                <!-- TEAMS -->

                                <td>

                                    <span class="team-count">
                                        👥
                                        <?= $teamCount ?>
                                    </span>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="badge <?= adminTournamentsEscape($statusClass) ?>"
                                    >

                                        <?= adminTournamentsEscape(
                                            $status
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACTION -->

                                <td>

                                    <a
                                        href="manage-tournament.php?tournament_id=<?= (int) $tournament['tournament_id'] ?>"
                                        class="manage-tournament-button"
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
                    id="noTournamentResults"
                    class="no-tournament-results"
                    hidden
                >

                    <div class="no-tournament-results-icon">
                        🔎
                    </div>

                    <strong>
                        No tournaments found
                    </strong>

                    <p>
                        Try searching with a different tournament,
                        sport, or venue name.
                    </p>

                </div>


            <?php endif; ?>

        </section>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Tournament Search
|--------------------------------------------------------------------------
|
| Searches the currently displayed table.
| No database request is required.
|
*/

document.addEventListener('DOMContentLoaded', function () {

    const searchInput =
        document.getElementById('tournamentSearch');

    const table =
        document.getElementById('tournamentTable');

    const noResults =
        document.getElementById('noTournamentResults');

    const searchInfo =
        document.getElementById('tournamentSearchInfo');

    if (!searchInput || !table) {
        return;
    }

    const rows =
        table.querySelectorAll(
            'tbody .tournament-row'
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
             * Update result count.
             */

            if (searchInfo) {

                if (searchTerm === '') {

                    searchInfo.textContent =
                        'Showing ' +
                        totalRows +
                        ' tournament' +
                        (
                            totalRows === 1
                                ? ''
                                : 's'
                        );

                } else {

                    searchInfo.textContent =
                        visibleRows +
                        ' result' +
                        (
                            visibleRows === 1
                                ? ''
                                : 's'
                        ) +
                        ' found';

                }

            }


            /*
             * Show / hide no-results message.
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