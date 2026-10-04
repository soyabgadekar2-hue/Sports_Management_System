<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

$tournamentId = filter_input(
    INPUT_GET,
    'tournament_id',
    FILTER_VALIDATE_INT
);

if (!$tournamentId) {
    exit('Invalid tournament ID.');
}

/*
|--------------------------------------------------------------------------
| Get Tournament
|--------------------------------------------------------------------------
*/

$tournamentStmt = $pdo->prepare("
    SELECT
        t.tournament_id,
        t.tournament_name,
        t.start_date,
        t.end_date,
        t.tournament_status,
        s.sport_name
    FROM tournaments t
    INNER JOIN sports s
        ON s.sport_id = t.sport_id
    WHERE t.tournament_id = :tournament_id
    LIMIT 1
");

$tournamentStmt->execute([
    'tournament_id' => $tournamentId
]);

$tournament = $tournamentStmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    exit('Tournament not found.');
}

/*
|--------------------------------------------------------------------------
| Get Matches
|--------------------------------------------------------------------------
*/

$matchesStmt = $pdo->prepare("
    SELECT
        m.match_id,
        m.match_number,
        m.scheduled_start,
        m.scheduled_end,
        m.match_status,
        m.notes,
        m.team_a_id,
        m.team_b_id,
        team_a.team_name AS team_a_name,
        team_b.team_name AS team_b_name,
        v.venue_name,
        mr.team_a_score,
        mr.team_b_score,
        mr.winner_team_id,
        mr.result_notes
    FROM matches m
    INNER JOIN teams team_a
        ON team_a.team_id = m.team_a_id
    INNER JOIN teams team_b
        ON team_b.team_id = m.team_b_id
    INNER JOIN venues v
        ON v.venue_id = m.venue_id
    LEFT JOIN match_results mr
        ON mr.match_id = m.match_id
    WHERE m.tournament_id = :tournament_id
    ORDER BY
        m.match_number ASC,
        m.scheduled_start ASC
");

$matchesStmt->execute([
    'tournament_id' => $tournamentId
]);

$matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function pageEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatMatchDateTime(?string $dateTime): string
{
    if (!$dateTime) {
        return 'Not available';
    }

    $timestamp = strtotime($dateTime);

    if ($timestamp === false) {
        return $dateTime;
    }

    return date('d M Y, h:i A', $timestamp);
}

function formatDateOnly(?string $date): string
{
    if (!$date) {
        return 'Not available';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    return date('d M Y', $timestamp);
}

function getMatchStatusClass(string $status): string
{
    return match (strtoupper($status)) {
        'SCHEDULED' => 'status-scheduled',
        'COMPLETED' => 'status-completed',
        'CANCELLED' => 'status-cancelled',
        'IN_PROGRESS' => 'status-progress',
        default => 'status-default',
    };
}

/*
|--------------------------------------------------------------------------
| Match Statistics
|--------------------------------------------------------------------------
*/

$totalMatches = count($matches);
$completedMatches = 0;
$scheduledMatches = 0;
$cancelledMatches = 0;

foreach ($matches as $match) {
    $status = strtoupper((string) $match['match_status']);

    if ($status === 'COMPLETED') {
        $completedMatches++;
    } elseif ($status === 'SCHEDULED') {
        $scheduledMatches++;
    } elseif ($status === 'CANCELLED') {
        $cancelledMatches++;
    }
}

$progressPercentage = $totalMatches > 0
    ? (int) round(($completedMatches / $totalMatches) * 100)
    : 0;

$tournamentStatus = strtoupper(
    (string) ($tournament['tournament_status'] ?? '')
);

$tournamentStatusClass = match ($tournamentStatus) {
    'ACTIVE', 'OPEN' => 'status-scheduled',
    'COMPLETED' => 'status-completed',
    'CANCELLED' => 'status-cancelled',
    default => 'status-default',
};

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .results-page {
        width: 100%;
        max-width: 1250px;
        margin: 0 auto;
        padding-bottom: 40px;
    }

    /*
    |--------------------------------------------------------------------------
    | Hero
    |--------------------------------------------------------------------------
    */

    .results-hero {
        background: linear-gradient(
            135deg,
            #0d47a1 0%,
            #1565c0 55%,
            #1976d2 100%
        );
        color: #ffffff;
        border-radius: 18px;
        padding: 28px 30px;
        margin-bottom: 24px;
        box-shadow: 0 12px 30px rgba(13, 71, 161, 0.18);
    }

    .results-hero-content {
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }

    .results-hero-icon {
        width: 54px;
        height: 54px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.16);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 27px;
        flex-shrink: 0;
    }

    .results-hero h1 {
        margin: 0 0 7px;
        font-size: 28px;
        line-height: 1.2;
        font-weight: 800;
    }

    .results-hero p {
        margin: 0;
        font-size: 14px;
        line-height: 1.6;
        opacity: 0.9;
    }

    .results-breadcrumb {
        margin-top: 17px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }

    .results-breadcrumb a {
        color: #ffffff;
        text-decoration: none;
        opacity: 0.9;
    }

    .results-breadcrumb a:hover {
        opacity: 1;
        text-decoration: underline;
    }

    .results-breadcrumb span {
        opacity: 0.55;
    }

    /*
    |--------------------------------------------------------------------------
    | Alerts
    |--------------------------------------------------------------------------
    */

    .results-alert {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        padding: 13px 15px;
        border-radius: 11px;
        margin-bottom: 20px;
        font-size: 13px;
        line-height: 1.5;
    }

    .results-alert-icon {
        flex-shrink: 0;
        font-size: 16px;
    }

    .results-alert.success {
        color: #146c43;
        background: #eaf8f0;
        border: 1px solid #b7e4c7;
    }

    .results-alert.error {
        color: #b42318;
        background: #fff1f2;
        border: 1px solid #f5c2c7;
    }

    /*
    |--------------------------------------------------------------------------
    | Tournament Summary
    |--------------------------------------------------------------------------
    */

    .tournament-summary-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        box-shadow: 0 7px 24px rgba(15, 23, 42, 0.06);
        padding: 22px;
        margin-bottom: 24px;
    }

    .summary-main {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .summary-heading {
        min-width: 0;
    }

    .summary-label {
        margin-bottom: 5px;
        color: #718096;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .summary-title {
        margin: 0;
        color: #172033;
        font-size: 21px;
        font-weight: 800;
        line-height: 1.3;
    }

    .summary-sport {
        margin-top: 7px;
        color: #5f6b7a;
        font-size: 13px;
    }

    .summary-status {
        flex-shrink: 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Stats
    |--------------------------------------------------------------------------
    */

    .results-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 17px;
        box-shadow: 0 5px 18px rgba(15, 23, 42, 0.05);
    }

    .stat-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .stat-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: #edf4ff;
        color: #145db2;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
    }

    .stat-number {
        margin-top: 12px;
        color: #172033;
        font-size: 24px;
        line-height: 1;
        font-weight: 800;
    }

    .stat-label {
        margin-top: 6px;
        color: #6b7280;
        font-size: 12px;
    }

    /*
    |--------------------------------------------------------------------------
    | Progress
    |--------------------------------------------------------------------------
    */

    .progress-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 19px 22px;
        margin-bottom: 24px;
        box-shadow: 0 6px 20px rgba(15, 23, 42, 0.05);
    }

    .progress-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        margin-bottom: 10px;
    }

    .progress-header strong {
        color: #253047;
        font-size: 13px;
    }

    .progress-percent {
        color: #145db2;
        font-size: 13px;
        font-weight: 800;
    }

    .progress-track {
        width: 100%;
        height: 9px;
        overflow: hidden;
        background: #e9eef5;
        border-radius: 999px;
    }

    .progress-fill {
        height: 100%;
        width: <?= $progressPercentage ?>%;
        background: #1565c0;
        border-radius: inherit;
        transition: width 0.4s ease;
    }

    /*
    |--------------------------------------------------------------------------
    | Main Matches Card
    |--------------------------------------------------------------------------
    */

    .matches-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        box-shadow: 0 7px 24px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }

    .matches-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 20px 22px;
        border-bottom: 1px solid #eef0f3;
        background: #fafbfc;
    }

    .matches-card-header h2 {
        margin: 0 0 4px;
        color: #172033;
        font-size: 18px;
    }

    .matches-card-header p {
        margin: 0;
        color: #6b7280;
        font-size: 12px;
    }

    .match-filter {
        min-width: 170px;
        padding: 9px 11px;
        border: 1px solid #d4d9e0;
        border-radius: 9px;
        background: #ffffff;
        color: #273244;
        font-family: inherit;
        font-size: 12px;
        outline: none;
    }

    .match-filter:focus {
        border-color: #1976d2;
        box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
    }

    /*
    |--------------------------------------------------------------------------
    | Match List
    |--------------------------------------------------------------------------
    */

    .match-list {
        display: flex;
        flex-direction: column;
    }

    .match-item {
        padding: 21px 22px;
        border-bottom: 1px solid #edf0f3;
        transition: background 0.18s ease;
    }

    .match-item:last-child {
        border-bottom: 0;
    }

    .match-item:hover {
        background: #fbfcfe;
    }

    .match-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        margin-bottom: 17px;
    }

    .match-number {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: #526174;
        font-size: 12px;
        font-weight: 800;
    }

    .match-number-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 27px;
        padding: 0 8px;
        border-radius: 8px;
        background: #edf4ff;
        color: #145db2;
        font-size: 11px;
        font-weight: 900;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.04em;
    }

    .status-scheduled {
        background: #eef5ff;
        color: #145db2;
    }

    .status-completed {
        background: #e8f7ee;
        color: #137333;
    }

    .status-cancelled {
        background: #fdecec;
        color: #b42318;
    }

    .status-progress {
        background: #fff6df;
        color: #946200;
    }

    .status-default {
        background: #f1f3f5;
        color: #495057;
    }

    /*
    |--------------------------------------------------------------------------
    | Teams / Score
    |--------------------------------------------------------------------------
    */

    .match-versus {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 76px minmax(0, 1fr);
        align-items: center;
        gap: 12px;
        margin-bottom: 17px;
    }

    .match-team {
        min-width: 0;
        padding: 17px;
        border: 1px solid #e4e9ef;
        border-radius: 13px;
        background: #fafbfd;
    }

    .team-label {
        margin-bottom: 5px;
        color: #8490a0;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .team-name {
        color: #1d2939;
        font-size: 15px;
        font-weight: 800;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .team-score {
        margin-top: 7px;
        color: #145db2;
        font-size: 24px;
        font-weight: 900;
        line-height: 1;
    }

    .team-score-label {
        margin-top: 4px;
        color: #7b8796;
        font-size: 10px;
    }

    .versus-center {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .versus-circle {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #eef3f9;
        color: #36516f;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 900;
    }

    .winner-label {
        color: #137333;
        font-size: 10px;
        font-weight: 800;
        text-align: center;
    }

    /*
    |--------------------------------------------------------------------------
    | Match Details
    |--------------------------------------------------------------------------
    */

    .match-details {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 17px;
    }

    .match-detail {
        padding: 11px 12px;
        border-radius: 10px;
        background: #f8fafc;
    }

    .match-detail-label {
        display: block;
        margin-bottom: 4px;
        color: #7a8491;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .match-detail-value {
        display: block;
        color: #344054;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.45;
    }

    .match-notes {
        margin-bottom: 15px;
        padding: 11px 13px;
        border-left: 3px solid #b8d1ee;
        background: #f5f9ff;
        color: #5d6b7a;
        font-size: 11px;
        line-height: 1.5;
        border-radius: 0 8px 8px 0;
    }

    .match-result-notes {
        margin-bottom: 15px;
        padding: 11px 13px;
        border-left: 3px solid #a8d5b8;
        background: #f0faf4;
        color: #4e6858;
        font-size: 11px;
        line-height: 1.5;
        border-radius: 0 8px 8px 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Action
    |--------------------------------------------------------------------------
    */

    .match-action {
        display: flex;
        justify-content: flex-end;
    }

    .result-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 38px;
        padding: 9px 14px;
        border-radius: 9px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 800;
        transition:
            transform 0.18s ease,
            box-shadow 0.18s ease,
            background 0.18s ease;
    }

    .result-button.primary {
        background: #1565c0;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(21, 101, 192, 0.18);
    }

    .result-button.primary:hover {
        background: #0d5cad;
        transform: translateY(-1px);
        box-shadow: 0 6px 15px rgba(21, 101, 192, 0.24);
    }

    .result-button.secondary {
        background: #eef5ff;
        color: #145db2;
        border: 1px solid #d7e6f8;
    }

    .result-button.secondary:hover {
        background: #e4effd;
    }

    .cancelled-text {
        display: inline-flex;
        align-items: center;
        min-height: 38px;
        padding: 9px 13px;
        border-radius: 9px;
        background: #fff3f3;
        color: #b42318;
        font-size: 12px;
        font-weight: 800;
    }

    /*
    |--------------------------------------------------------------------------
    | Empty State
    |--------------------------------------------------------------------------
    */

    .empty-state {
        padding: 55px 24px;
        text-align: center;
    }

    .empty-icon {
        width: 62px;
        height: 62px;
        margin: 0 auto 14px;
        border-radius: 16px;
        background: #eef5ff;
        color: #145db2;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }

    .empty-state h3 {
        margin: 0 0 7px;
        color: #253047;
        font-size: 18px;
    }

    .empty-state p {
        max-width: 450px;
        margin: 0 auto;
        color: #6b7280;
        font-size: 13px;
        line-height: 1.6;
    }

    /*
    |--------------------------------------------------------------------------
    | Footer Information
    |--------------------------------------------------------------------------
    */

    .results-info {
        margin-top: 24px;
        padding: 16px 18px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        border: 1px solid #dbe8f8;
        border-radius: 13px;
        background: #f5f9ff;
    }

    .results-info-icon {
        font-size: 18px;
        flex-shrink: 0;
    }

    .results-info strong {
        display: block;
        margin-bottom: 3px;
        color: #194c82;
        font-size: 13px;
    }

    .results-info p {
        margin: 0;
        color: #5d6b7a;
        font-size: 12px;
        line-height: 1.5;
    }

    /*
    |--------------------------------------------------------------------------
    | Responsive
    |--------------------------------------------------------------------------
    */

    @media (max-width: 900px) {
        .results-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .match-details {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .summary-main {
            align-items: flex-start;
            flex-direction: column;
        }

        .summary-status {
            align-self: flex-start;
        }

        .matches-card-header {
            align-items: stretch;
            flex-direction: column;
        }

        .match-filter {
            width: 100%;
            box-sizing: border-box;
        }

        .match-versus {
            grid-template-columns: 1fr;
            gap: 8px;
        }

        .versus-center {
            flex-direction: row;
        }

        .versus-circle {
            width: 38px;
            height: 38px;
        }

        .match-action {
            justify-content: stretch;
        }

        .result-button,
        .cancelled-text {
            width: 100%;
            box-sizing: border-box;
        }
    }

    @media (max-width: 550px) {
        .results-page {
            padding-bottom: 25px;
        }

        .results-hero {
            padding: 21px 17px;
            border-radius: 14px;
        }

        .results-hero-content {
            gap: 12px;
        }

        .results-hero-icon {
            width: 44px;
            height: 44px;
            font-size: 22px;
            border-radius: 11px;
        }

        .results-hero h1 {
            font-size: 21px;
        }

        .results-hero p {
            font-size: 12px;
        }

        .tournament-summary-card {
            padding: 17px;
            border-radius: 13px;
        }

        .summary-title {
            font-size: 18px;
        }

        .results-stats {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .stat-card {
            padding: 14px;
        }

        .stat-number {
            font-size: 21px;
        }

        .matches-card {
            border-radius: 13px;
        }

        .matches-card-header,
        .match-item {
            padding: 17px;
        }

        .match-team {
            padding: 14px;
        }

        .match-details {
            grid-template-columns: 1fr;
        }

        .match-top {
            align-items: flex-start;
        }
    }

    @media (max-width: 390px) {
        .results-stats {
            grid-template-columns: 1fr;
        }

        .results-hero {
            padding: 18px 14px;
        }

        .results-hero h1 {
            font-size: 19px;
        }

        .matches-card-header,
        .match-item {
            padding: 15px;
        }
    }
</style>

<div class="results-page">

    <!-- Page Header -->
    <section class="results-hero">

        <div class="results-hero-content">

            <div class="results-hero-icon">
                🏆
            </div>

            <div>
                <h1>Match Results</h1>

                <p>
                    Record, review and manage the results of matches
                    played in this tournament.
                </p>

                <div class="results-breadcrumb">
                    <a href="manage-tournament.php?tournament_id=<?= (int) $tournamentId ?>">
                        Tournament
                    </a>

                    <span>›</span>

                    <span>Match Results</span>
                </div>
            </div>

        </div>

    </section>

    <!-- Success / Error Messages -->

    <?php if ($message === 'result_saved'): ?>

        <div class="results-alert success">
            <div class="results-alert-icon">✅</div>

            <div>
                Match result saved successfully.
            </div>
        </div>

    <?php elseif ($message === 'result_updated'): ?>

        <div class="results-alert success">
            <div class="results-alert-icon">✅</div>

            <div>
                Match result updated successfully and standings recalculated.
            </div>
        </div>

    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="results-alert error">
            <div class="results-alert-icon">⚠️</div>

            <div>
                <?= pageEscape($error) ?>
            </div>
        </div>

    <?php endif; ?>

    <!-- Tournament Summary -->

    <section class="tournament-summary-card">

        <div class="summary-main">

            <div class="summary-heading">

                <div class="summary-label">
                    Tournament
                </div>

                <h2 class="summary-title">
                    <?= pageEscape($tournament['tournament_name']) ?>
                </h2>

                <div class="summary-sport">
                    🏅 <?= pageEscape($tournament['sport_name']) ?>

                    &nbsp;•&nbsp;

                    <?= pageEscape(
                        formatDateOnly($tournament['start_date'])
                    ) ?>

                    —
                    <?= pageEscape(
                        formatDateOnly($tournament['end_date'])
                    ) ?>
                </div>

            </div>

            <div class="summary-status">

                <span class="status-pill <?= pageEscape($tournamentStatusClass) ?>">
                    <?= pageEscape($tournamentStatus) ?>
                </span>

            </div>

        </div>

    </section>

    <!-- Statistics -->

    <section class="results-stats">

        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon">📋</div>
            </div>

            <div class="stat-number">
                <?= $totalMatches ?>
            </div>

            <div class="stat-label">
                Total Matches
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon">📅</div>
            </div>

            <div class="stat-number">
                <?= $scheduledMatches ?>
            </div>

            <div class="stat-label">
                Scheduled
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon">✅</div>
            </div>

            <div class="stat-number">
                <?= $completedMatches ?>
            </div>

            <div class="stat-label">
                Results Entered
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-top">
                <div class="stat-icon">🚫</div>
            </div>

            <div class="stat-number">
                <?= $cancelledMatches ?>
            </div>

            <div class="stat-label">
                Cancelled
            </div>

        </div>

    </section>

    <!-- Progress -->

    <?php if ($totalMatches > 0): ?>

        <section class="progress-card">

            <div class="progress-header">

                <strong>
                    Tournament Match Progress
                </strong>

                <span class="progress-percent">
                    <?= $progressPercentage ?>%
                </span>

            </div>

            <div class="progress-track">
                <div class="progress-fill"></div>
            </div>

        </section>

    <?php endif; ?>

    <!-- Matches -->

    <section class="matches-card">

        <div class="matches-card-header">

            <div>
                <h2>Scheduled Matches</h2>

                <p>
                    Enter results for completed matches or correct an existing result.
                </p>
            </div>

            <?php if ($matches): ?>

                <select
                    class="match-filter"
                    id="matchStatusFilter"
                    aria-label="Filter matches by status"
                >
                    <option value="ALL">
                        All Matches
                    </option>

                    <option value="SCHEDULED">
                        Scheduled
                    </option>

                    <option value="COMPLETED">
                        Completed
                    </option>

                    <option value="CANCELLED">
                        Cancelled
                    </option>
                </select>

            <?php endif; ?>

        </div>

        <?php if (!$matches): ?>

            <div class="empty-state">

                <div class="empty-icon">
                    📅
                </div>

                <h3>No Matches Scheduled</h3>

                <p>
                    No matches have been scheduled for this tournament yet.
                    Schedule matches from the tournament management page first.
                </p>

            </div>

        <?php else: ?>

            <div class="match-list" id="matchList">

                <?php foreach ($matches as $match): ?>

                    <?php
                    $matchStatus = strtoupper(
                        (string) $match['match_status']
                    );

                    $hasResult = $match['team_a_score'] !== null;

                    $winnerTeamId = $match['winner_team_id'] !== null
                        ? (int) $match['winner_team_id']
                        : null;

                    $teamAId = (int) $match['team_a_id'];
                    $teamBId = (int) $match['team_b_id'];
                    ?>

                    <article
                        class="match-item"
                        data-status="<?= pageEscape($matchStatus) ?>"
                    >

                        <!-- Match Header -->

                        <div class="match-top">

                            <div class="match-number">

                                <span class="match-number-badge">
                                    #<?= (int) $match['match_number'] ?>
                                </span>

                                Match
                            </div>

                            <span class="status-pill <?= pageEscape(
                                getMatchStatusClass($matchStatus)
                            ) ?>">
                                <?= pageEscape($matchStatus) ?>
                            </span>

                        </div>

                        <!-- Teams -->

                        <div class="match-versus">

                            <div class="match-team">

                                <div class="team-label">
                                    Team A
                                </div>

                                <div class="team-name">
                                    <?= pageEscape($match['team_a_name']) ?>
                                </div>

                                <?php if ($hasResult): ?>

                                    <div class="team-score">
                                        <?= pageEscape($match['team_a_score']) ?>
                                    </div>

                                    <div class="team-score-label">
                                        Final Score
                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="versus-center">

                                <div class="versus-circle">
                                    VS
                                </div>

                                <?php if ($hasResult && $winnerTeamId): ?>

                                    <div class="winner-label">
                                        <?php if ($winnerTeamId === $teamAId): ?>
                                            Winner: Team A
                                        <?php elseif ($winnerTeamId === $teamBId): ?>
                                            Winner: Team B
                                        <?php endif; ?>
                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="match-team">

                                <div class="team-label">
                                    Team B
                                </div>

                                <div class="team-name">
                                    <?= pageEscape($match['team_b_name']) ?>
                                </div>

                                <?php if ($hasResult): ?>

                                    <div class="team-score">
                                        <?= pageEscape($match['team_b_score']) ?>
                                    </div>

                                    <div class="team-score-label">
                                        Final Score
                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                        <!-- Match Details -->

                        <div class="match-details">

                            <div class="match-detail">

                                <span class="match-detail-label">
                                    Date & Time
                                </span>

                                <span class="match-detail-value">
                                    <?= pageEscape(
                                        formatMatchDateTime(
                                            $match['scheduled_start']
                                        )
                                    ) ?>
                                </span>

                            </div>

                            <div class="match-detail">

                                <span class="match-detail-label">
                                    End Time
                                </span>

                                <span class="match-detail-value">
                                    <?= pageEscape(
                                        formatMatchDateTime(
                                            $match['scheduled_end']
                                        )
                                    ) ?>
                                </span>

                            </div>

                            <div class="match-detail">

                                <span class="match-detail-label">
                                    Venue
                                </span>

                                <span class="match-detail-value">
                                    📍
                                    <?= pageEscape($match['venue_name']) ?>
                                </span>

                            </div>

                        </div>

                        <!-- Match Notes -->

                        <?php if (
                            !empty($match['notes'])
                        ): ?>

                            <div class="match-notes">

                                <strong>Match Notes:</strong>

                                <?= pageEscape($match['notes']) ?>

                            </div>

                        <?php endif; ?>

                        <!-- Result Notes -->

                        <?php if (
                            $hasResult &&
                            !empty($match['result_notes'])
                        ): ?>

                            <div class="match-result-notes">

                                <strong>Result Notes:</strong>

                                <?= pageEscape($match['result_notes']) ?>

                            </div>

                        <?php endif; ?>

                        <!-- Action -->

                        <div class="match-action">

                            <?php if ($matchStatus === 'SCHEDULED'): ?>

                                <a
                                    href="enter-match-result.php?match_id=<?= (int) $match['match_id'] ?>"
                                    class="result-button primary"
                                >
                                    📝 Enter Result
                                </a>

                            <?php elseif ($matchStatus === 'COMPLETED'): ?>

                                <a
                                    href="edit-match-result.php?match_id=<?= (int) $match['match_id'] ?>"
                                    class="result-button secondary"
                                >
                                    ✏️ Correct Result
                                </a>

                            <?php elseif ($matchStatus === 'CANCELLED'): ?>

                                <span class="cancelled-text">
                                    🚫 Match Cancelled
                                </span>

                            <?php else: ?>

                                <span class="cancelled-text">
                                    <?= pageEscape($matchStatus) ?>
                                </span>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <!-- Information -->

    <div class="results-info">

        <div class="results-info-icon">
            💡
        </div>

        <div>

            <strong>
                Result and standings workflow
            </strong>

            <p>
                Enter the result after a scheduled match is completed.
                When the result is saved, the tournament standings can be
                recalculated using the existing tournament points system.
            </p>

        </div>

    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const filter = document.getElementById('matchStatusFilter');
        const matchItems = document.querySelectorAll('.match-item');

        if (!filter) {
            return;
        }

        filter.addEventListener('change', function () {

            const selectedStatus = filter.value;

            matchItems.forEach(function (item) {

                const itemStatus = item.dataset.status;

                if (
                    selectedStatus === 'ALL' ||
                    itemStatus === selectedStatus
                ) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }

            });

        });

    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>