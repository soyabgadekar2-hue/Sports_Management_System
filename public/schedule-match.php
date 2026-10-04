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
| Get tournament
|--------------------------------------------------------------------------
*/

$tournamentStmt = $pdo->prepare("
    SELECT
        t.tournament_id,
        t.tournament_name,
        t.sport_id,
        t.venue_id,
        t.start_date,
        t.end_date,
        t.tournament_format,
        t.tournament_status,
        s.sport_name,
        v.venue_name
    FROM tournaments t
    INNER JOIN sports s
        ON s.sport_id = t.sport_id
    LEFT JOIN venues v
        ON v.venue_id = t.venue_id
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
| Only LEAGUE format is supported
|--------------------------------------------------------------------------
*/

if ($tournament['tournament_format'] !== 'LEAGUE') {
    exit('Only league tournaments are supported.');
}

/*
|--------------------------------------------------------------------------
| Get registered teams
|--------------------------------------------------------------------------
*/

$teamsStmt = $pdo->prepare("
    SELECT
        tt.team_id,
        tm.team_name
    FROM tournament_teams tt
    INNER JOIN teams tm
        ON tm.team_id = tt.team_id
    WHERE tt.tournament_id = :tournament_id
      AND tt.participation_status = 'ACTIVE'
      AND tm.team_status = 'ACTIVE'
      AND tm.sport_id = :sport_id
    ORDER BY tm.team_name
");

$teamsStmt->execute([
    'tournament_id' => $tournamentId,
    'sport_id' => $tournament['sport_id']
]);

$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($teams) < 2) {
    exit('At least two registered teams are required to schedule a match.');
}

/*
|--------------------------------------------------------------------------
| Get available venues
|--------------------------------------------------------------------------
*/

$venuesStmt = $pdo->query("
    SELECT
        venue_id,
        venue_name
    FROM venues
    WHERE availability_status = 'AVAILABLE'
    ORDER BY venue_name
");

$venues = $venuesStmt->fetchAll(PDO::FETCH_ASSOC);

$error = $_GET['error'] ?? '';

function pageEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Format tournament dates
|--------------------------------------------------------------------------
*/

function formatTournamentDate(?string $date): string
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

function formatTournamentDateTime(?string $date): string
{
    if (!$date) {
        return 'Not available';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    return date('d M Y, h:i A', $timestamp);
}

$tournamentStatus = strtoupper(
    (string) ($tournament['tournament_status'] ?? '')
);

$statusClass = match ($tournamentStatus) {
    'ACTIVE', 'OPEN' => 'status-active',
    'COMPLETED' => 'status-completed',
    'CANCELLED' => 'status-cancelled',
    default => 'status-default',
};

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .schedule-page {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding-bottom: 40px;
    }

    .schedule-hero {
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

    .schedule-hero-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
    }

    .schedule-hero-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.16);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 27px;
        flex-shrink: 0;
    }

    .schedule-hero-content {
        flex: 1;
    }

    .schedule-hero h1 {
        margin: 0 0 7px;
        font-size: 28px;
        line-height: 1.2;
        font-weight: 800;
    }

    .schedule-hero p {
        margin: 0;
        opacity: 0.9;
        font-size: 14px;
        line-height: 1.6;
    }

    .schedule-breadcrumb {
        margin-top: 18px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }

    .schedule-breadcrumb a {
        color: #ffffff;
        text-decoration: none;
        opacity: 0.9;
    }

    .schedule-breadcrumb a:hover {
        text-decoration: underline;
        opacity: 1;
    }

    .schedule-breadcrumb span {
        opacity: 0.55;
    }

    .schedule-layout {
        display: grid;
        grid-template-columns: minmax(280px, 0.85fr) minmax(420px, 1.5fr);
        gap: 24px;
        align-items: start;
    }

    .schedule-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        box-shadow: 0 7px 24px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }

    .schedule-card-header {
        padding: 20px 22px;
        border-bottom: 1px solid #eef0f3;
        background: #fafbfc;
    }

    .schedule-card-header h2 {
        margin: 0 0 5px;
        font-size: 18px;
        color: #172033;
    }

    .schedule-card-header p {
        margin: 0;
        color: #6b7280;
        font-size: 13px;
        line-height: 1.5;
    }

    .schedule-card-body {
        padding: 22px;
    }

    .tournament-summary {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .summary-title {
        font-size: 20px;
        font-weight: 800;
        color: #172033;
        line-height: 1.3;
    }

    .summary-sport {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        gap: 7px;
        padding: 7px 11px;
        background: #eef5ff;
        color: #1257a6;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
    }

    .summary-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 5px;
    }

    .summary-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px;
        border-radius: 11px;
        background: #f8fafc;
    }

    .summary-item-icon {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: #eaf2ff;
        color: #145db2;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 16px;
    }

    .summary-item-content {
        min-width: 0;
    }

    .summary-item-label {
        display: block;
        color: #6b7280;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 3px;
    }

    .summary-item-value {
        display: block;
        color: #1f2937;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.03em;
    }

    .status-active {
        background: #e8f7ee;
        color: #137333;
    }

    .status-completed {
        background: #e8f0ff;
        color: #2459a6;
    }

    .status-cancelled {
        background: #fdecec;
        color: #b42318;
    }

    .status-default {
        background: #f1f3f5;
        color: #495057;
    }

    .team-count-box {
        margin-top: 4px;
        padding: 14px;
        border: 1px solid #dbe8f8;
        background: #f4f8fd;
        border-radius: 12px;
    }

    .team-count-number {
        font-size: 25px;
        line-height: 1;
        font-weight: 800;
        color: #145db2;
    }

    .team-count-text {
        margin-top: 5px;
        color: #5f6b7a;
        font-size: 12px;
    }

    .match-form {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .form-section {
        padding-bottom: 20px;
        border-bottom: 1px solid #edf0f3;
    }

    .form-section:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .form-section-title {
        margin: 0 0 14px;
        color: #172033;
        font-size: 14px;
        font-weight: 800;
    }

    .form-section-subtitle {
        margin: -7px 0 15px;
        color: #737b87;
        font-size: 12px;
        line-height: 1.5;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .form-group {
        min-width: 0;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        display: block;
        margin-bottom: 7px;
        color: #273244;
        font-size: 13px;
        font-weight: 700;
    }

    .required-mark {
        color: #d92d20;
        margin-left: 2px;
    }

    .form-control {
        width: 100%;
        box-sizing: border-box;
        min-height: 44px;
        padding: 10px 12px;
        border: 1px solid #d4d9e0;
        border-radius: 10px;
        background: #ffffff;
        color: #172033;
        font-family: inherit;
        font-size: 13px;
        outline: none;
        transition:
            border-color 0.18s ease,
            box-shadow 0.18s ease;
    }

    .form-control:focus {
        border-color: #1976d2;
        box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.12);
    }

    textarea.form-control {
        min-height: 105px;
        resize: vertical;
        line-height: 1.5;
    }

    .team-vs {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 44px minmax(0, 1fr);
        align-items: end;
        gap: 10px;
    }

    .vs-badge {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #eef3f9;
        color: #36516f;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 900;
        margin-bottom: 0;
    }

    .form-help {
        margin-top: 6px;
        color: #7a8491;
        font-size: 11px;
        line-height: 1.45;
    }

    .error-alert {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 20px;
        padding: 13px 15px;
        border: 1px solid #f5c2c7;
        border-radius: 11px;
        background: #fff1f2;
        color: #b42318;
        font-size: 13px;
        line-height: 1.5;
    }

    .error-alert-icon {
        font-size: 16px;
        flex-shrink: 0;
    }

    .schedule-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding-top: 4px;
    }

    .back-link {
        color: #536174;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
    }

    .back-link:hover {
        color: #145db2;
        text-decoration: underline;
    }

    .schedule-submit {
        border: 0;
        border-radius: 10px;
        padding: 12px 20px;
        background: #1565c0;
        color: #ffffff;
        font-family: inherit;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        box-shadow: 0 5px 14px rgba(21, 101, 192, 0.2);
        transition:
            transform 0.18s ease,
            box-shadow 0.18s ease,
            background 0.18s ease;
    }

    .schedule-submit:hover {
        background: #0d5cad;
        transform: translateY(-1px);
        box-shadow: 0 7px 18px rgba(21, 101, 192, 0.25);
    }

    .schedule-submit:active {
        transform: translateY(0);
    }

    .info-strip {
        margin-top: 24px;
        padding: 16px 18px;
        border: 1px solid #dbe8f8;
        border-radius: 13px;
        background: #f5f9ff;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .info-strip-icon {
        font-size: 18px;
        flex-shrink: 0;
    }

    .info-strip strong {
        display: block;
        margin-bottom: 3px;
        color: #194c82;
        font-size: 13px;
    }

    .info-strip p {
        margin: 0;
        color: #5d6b7a;
        font-size: 12px;
        line-height: 1.5;
    }

    @media (max-width: 900px) {
        .schedule-layout {
            grid-template-columns: 1fr;
        }

        .schedule-hero-top {
            align-items: flex-start;
        }
    }

    @media (max-width: 650px) {
        .schedule-page {
            padding-bottom: 24px;
        }

        .schedule-hero {
            padding: 22px 18px;
            border-radius: 14px;
        }

        .schedule-hero-top {
            gap: 13px;
        }

        .schedule-hero-icon {
            width: 44px;
            height: 44px;
            font-size: 22px;
            border-radius: 11px;
        }

        .schedule-hero h1 {
            font-size: 22px;
        }

        .schedule-card {
            border-radius: 13px;
        }

        .schedule-card-header,
        .schedule-card-body {
            padding: 17px;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-group.full-width {
            grid-column: auto;
        }

        .team-vs {
            grid-template-columns: 1fr;
        }

        .vs-badge {
            margin: -2px auto;
        }

        .schedule-actions {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .schedule-submit,
        .back-link {
            width: 100%;
            text-align: center;
            box-sizing: border-box;
        }
    }

    @media (max-width: 430px) {
        .schedule-hero {
            padding: 19px 15px;
        }

        .schedule-hero h1 {
            font-size: 20px;
        }

        .schedule-card-header,
        .schedule-card-body {
            padding: 15px;
        }

        .summary-title {
            font-size: 18px;
        }

        .form-control {
            min-height: 46px;
        }
    }
</style>

<div class="schedule-page">

    <!-- Page Header -->
    <section class="schedule-hero">
        <div class="schedule-hero-top">

            <div class="schedule-hero-icon">
                📅
            </div>

            <div class="schedule-hero-content">
                <h1>Schedule League Match</h1>

                <p>
                    Create a match between registered teams and add it to the
                    tournament schedule.
                </p>

                <div class="schedule-breadcrumb">
                    <a href="manage-tournament.php?tournament_id=<?= (int) $tournamentId ?>">
                        Tournament
                    </a>

                    <span>›</span>

                    <span>Schedule Match</span>
                </div>
            </div>

        </div>
    </section>

    <?php if ($error !== ''): ?>

        <div class="error-alert">
            <div class="error-alert-icon">⚠️</div>

            <div>
                <?= pageEscape($error) ?>
            </div>
        </div>

    <?php endif; ?>

    <div class="schedule-layout">

        <!-- Tournament Information -->
        <section class="schedule-card">

            <div class="schedule-card-header">
                <h2>Tournament Information</h2>

                <p>
                    Confirm the tournament details before scheduling the match.
                </p>
            </div>

            <div class="schedule-card-body">

                <div class="tournament-summary">

                    <div>
                        <div class="summary-title">
                            <?= pageEscape($tournament['tournament_name']) ?>
                        </div>

                        <div class="summary-sport">
                            🏆
                            <?= pageEscape($tournament['sport_name']) ?>
                        </div>
                    </div>

                    <div class="summary-list">

                        <div class="summary-item">
                            <div class="summary-item-icon">
                                📅
                            </div>

                            <div class="summary-item-content">
                                <span class="summary-item-label">
                                    Tournament Dates
                                </span>

                                <span class="summary-item-value">
                                    <?= pageEscape(
                                        formatTournamentDate($tournament['start_date'])
                                    ) ?>

                                    —

                                    <?= pageEscape(
                                        formatTournamentDate($tournament['end_date'])
                                    ) ?>
                                </span>
                            </div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-item-icon">
                                📍
                            </div>

                            <div class="summary-item-content">
                                <span class="summary-item-label">
                                    Tournament Venue
                                </span>

                                <span class="summary-item-value">
                                    <?= pageEscape(
                                        $tournament['venue_name'] ?? 'Not assigned'
                                    ) ?>
                                </span>
                            </div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-item-icon">
                                🏆
                            </div>

                            <div class="summary-item-content">
                                <span class="summary-item-label">
                                    Format
                                </span>

                                <span class="summary-item-value">
                                    League
                                </span>
                            </div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-item-icon">
                                🔄
                            </div>

                            <div class="summary-item-content">
                                <span class="summary-item-label">
                                    Tournament Status
                                </span>

                                <span class="summary-item-value">
                                    <span class="status-pill <?= pageEscape($statusClass) ?>">
                                        <?= pageEscape($tournamentStatus) ?>
                                    </span>
                                </span>
                            </div>
                        </div>

                    </div>

                    <div class="team-count-box">
                        <div class="team-count-number">
                            <?= count($teams) ?>
                        </div>

                        <div class="team-count-text">
                            Active teams registered for this tournament
                        </div>
                    </div>

                </div>

            </div>

        </section>

        <!-- Schedule Form -->
        <section class="schedule-card">

            <div class="schedule-card-header">
                <h2>Create Match</h2>

                <p>
                    Select two different teams, venue and match timing.
                </p>
            </div>

            <div class="schedule-card-body">

                <form
                    method="POST"
                    action="schedule-match-process.php"
                    class="match-form"
                    id="scheduleMatchForm"
                >

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="tournament_id"
                        value="<?= (int) $tournamentId ?>"
                    >

                    <!-- Teams -->
                    <div class="form-section">

                        <h3 class="form-section-title">
                            ⚽ Select Teams
                        </h3>

                        <p class="form-section-subtitle">
                            Choose the two registered teams that will play this match.
                        </p>

                        <div class="team-vs">

                            <div class="form-group">

                                <label
                                    for="team_a_id"
                                    class="form-label"
                                >
                                    Team A
                                    <span class="required-mark">*</span>
                                </label>

                                <select
                                    name="team_a_id"
                                    id="team_a_id"
                                    class="form-control"
                                    required
                                >

                                    <option value="">
                                        Select Team A
                                    </option>

                                    <?php foreach ($teams as $team): ?>

                                        <option
                                            value="<?= (int) $team['team_id'] ?>"
                                        >
                                            <?= pageEscape($team['team_name']) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="vs-badge">
                                VS
                            </div>

                            <div class="form-group">

                                <label
                                    for="team_b_id"
                                    class="form-label"
                                >
                                    Team B
                                    <span class="required-mark">*</span>
                                </label>

                                <select
                                    name="team_b_id"
                                    id="team_b_id"
                                    class="form-control"
                                    required
                                >

                                    <option value="">
                                        Select Team B
                                    </option>

                                    <?php foreach ($teams as $team): ?>

                                        <option
                                            value="<?= (int) $team['team_id'] ?>"
                                        >
                                            <?= pageEscape($team['team_name']) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        </div>

                        <div class="form-help">
                            Team A and Team B must be different teams.
                        </div>

                    </div>

                    <!-- Venue -->
                    <div class="form-section">

                        <h3 class="form-section-title">
                            📍 Match Location
                        </h3>

                        <div class="form-grid">

                            <div class="form-group full-width">

                                <label
                                    for="venue_id"
                                    class="form-label"
                                >
                                    Match Venue
                                    <span class="required-mark">*</span>
                                </label>

                                <select
                                    name="venue_id"
                                    id="venue_id"
                                    class="form-control"
                                    required
                                >

                                    <option value="">
                                        Select Venue
                                    </option>

                                    <?php foreach ($venues as $venue): ?>

                                        <option
                                            value="<?= (int) $venue['venue_id'] ?>"
                                            <?php
                                            if (
                                                !empty($tournament['venue_id'])
                                                &&
                                                (int) $tournament['venue_id'] ===
                                                (int) $venue['venue_id']
                                            ) {
                                                echo 'selected';
                                            }
                                            ?>
                                        >
                                            <?= pageEscape($venue['venue_name']) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <div class="form-help">
                                    Only venues currently marked as available are shown.
                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- Date and Time -->
                    <div class="form-section">

                        <h3 class="form-section-title">
                            🕐 Match Timing
                        </h3>

                        <p class="form-section-subtitle">
                            Schedule the match within the tournament period.
                        </p>

                        <div class="form-grid">

                            <div class="form-group">

                                <label
                                    for="scheduled_start"
                                    class="form-label"
                                >
                                    Start Date & Time
                                    <span class="required-mark">*</span>
                                </label>

                                <input
                                    type="datetime-local"
                                    name="scheduled_start"
                                    id="scheduled_start"
                                    class="form-control"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label
                                    for="scheduled_end"
                                    class="form-label"
                                >
                                    End Date & Time
                                    <span class="required-mark">*</span>
                                </label>

                                <input
                                    type="datetime-local"
                                    name="scheduled_end"
                                    id="scheduled_end"
                                    class="form-control"
                                    required
                                >

                            </div>

                        </div>

                        <div class="form-help">
                            The match should start and end within the tournament dates.
                        </div>

                    </div>

                    <!-- Notes -->
                    <div class="form-section">

                        <h3 class="form-section-title">
                            📝 Match Notes
                        </h3>

                        <div class="form-group">

                            <label
                                for="notes"
                                class="form-label"
                            >
                                Notes
                            </label>

                            <textarea
                                name="notes"
                                id="notes"
                                class="form-control"
                                rows="4"
                                maxlength="1000"
                                placeholder="Add optional instructions, match information or special notes..."
                            ></textarea>

                            <div class="form-help">
                                Optional. Maximum 1000 characters.
                            </div>

                        </div>

                    </div>

                    <!-- Actions -->
                    <div class="schedule-actions">

                        <a
                            href="manage-tournament.php?tournament_id=<?= (int) $tournamentId ?>"
                            class="back-link"
                        >
                            ← Back to Tournament
                        </a>

                        <button
                            type="submit"
                            class="schedule-submit"
                        >
                            📅 Schedule Match
                        </button>

                    </div>

                </form>

            </div>

        </section>

    </div>

    <div class="info-strip">

        <div class="info-strip-icon">
            💡
        </div>

        <div>
            <strong>League match workflow</strong>

            <p>
                Select the participating teams, assign a venue and timing,
                then schedule the match. After scheduling, the match can be
                managed from the tournament page.
            </p>
        </div>

    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const form = document.getElementById('scheduleMatchForm');
        const teamA = document.getElementById('team_a_id');
        const teamB = document.getElementById('team_b_id');
        const startInput = document.getElementById('scheduled_start');
        const endInput = document.getElementById('scheduled_end');

        if (!form) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent selecting the same team
        |--------------------------------------------------------------------------
        */

        function updateTeamOptions() {

            const selectedA = teamA.value;
            const selectedB = teamB.value;

            Array.from(teamB.options).forEach(function (option) {

                if (!option.value) {
                    return;
                }

                option.disabled =
                    option.value === selectedA;

            });

            Array.from(teamA.options).forEach(function (option) {

                if (!option.value) {
                    return;
                }

                option.disabled =
                    option.value === selectedB;

            });
        }

        teamA.addEventListener('change', updateTeamOptions);
        teamB.addEventListener('change', updateTeamOptions);

        /*
        |--------------------------------------------------------------------------
        | Start/end time validation
        |--------------------------------------------------------------------------
        */

        function validateMatchTime() {

            if (
                startInput.value &&
                endInput.value &&
                endInput.value <= startInput.value
            ) {
                endInput.setCustomValidity(
                    'End date and time must be after the start date and time.'
                );
            } else {
                endInput.setCustomValidity('');
            }
        }

        startInput.addEventListener('change', validateMatchTime);
        endInput.addEventListener('change', validateMatchTime);

        /*
        |--------------------------------------------------------------------------
        | Form validation
        |--------------------------------------------------------------------------
        */

        form.addEventListener('submit', function (event) {

            if (
                teamA.value &&
                teamB.value &&
                teamA.value === teamB.value
            ) {
                event.preventDefault();

                teamB.setCustomValidity(
                    'Team A and Team B must be different teams.'
                );

                teamB.reportValidity();

                return;
            }

            teamB.setCustomValidity('');
            validateMatchTime();

        });

    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>