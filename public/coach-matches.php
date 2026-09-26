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

function coachMatchEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function coachMatchStatusClass(string $status): string
{
    return match (strtoupper($status)) {
        'SCHEDULED' => 'scheduled',
        'COMPLETED' => 'completed',
        'POSTPONED' => 'postponed',
        'CANCELLED' => 'cancelled',
        'IN_PROGRESS' => 'in-progress',
        default => 'default',
    };
}

function coachMatchFormatScore(mixed $score): string
{
    if ($score === null || $score === '') {
        return '—';
    }

    $number = (float) $score;

    return rtrim(
        rtrim(number_format($number, 2, '.', ''), '0'),
        '.'
    );
}

function coachMatchFormatDate(?string $date): string
{
    if (!$date) {
        return 'Date not set';
    }

    $timestamp = strtotime($date);

    return $timestamp === false
        ? 'Date not set'
        : date('d M Y', $timestamp);
}

function coachMatchFormatTime(?string $date): string
{
    if (!$date) {
        return 'Time not set';
    }

    $timestamp = strtotime($date);

    return $timestamp === false
        ? 'Time not set'
        : date('h:i A', $timestamp);
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
| Get matches involving coach's teams
|--------------------------------------------------------------------------
|
| A coach can be responsible for either Team A or Team B.
| Therefore, both sides are checked.
|--------------------------------------------------------------------------
*/

$matchesStmt = $db->prepare("
    SELECT
        m.match_id,
        m.match_number,
        m.scheduled_start,
        m.scheduled_end,
        m.match_status,
        m.notes,

        tournament.tournament_id,
        tournament.tournament_name,
        tournament.tournament_format,
        tournament.tournament_status,

        sport.sport_name,

        venue.venue_name,

        teamA.team_id AS team_a_id,
        teamA.team_name AS team_a_name,

        teamB.team_id AS team_b_id,
        teamB.team_name AS team_b_name,

        mr.team_a_score,
        mr.team_b_score,
        mr.winner_team_id,
        mr.result_notes

    FROM matches m

    INNER JOIN tournaments tournament
        ON tournament.tournament_id = m.tournament_id

    INNER JOIN sports sport
        ON sport.sport_id = tournament.sport_id

    LEFT JOIN venues venue
        ON venue.venue_id = m.venue_id

    INNER JOIN teams teamA
        ON teamA.team_id = m.team_a_id

    INNER JOIN teams teamB
        ON teamB.team_id = m.team_b_id

    LEFT JOIN match_results mr
        ON mr.match_id = m.match_id

    WHERE
        teamA.coach_id = :coach_id_a
        OR
        teamB.coach_id = :coach_id_b

    ORDER BY
        m.scheduled_start DESC,
        m.match_number ASC
");

$matchesStmt->execute([
    ':coach_id_a' => $coachId,
    ':coach_id_b' => $coachId
]);

$matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Calculate summary counts
|--------------------------------------------------------------------------
*/

$upcomingCount = 0;
$completedCount = 0;
$postponedCount = 0;
$cancelledCount = 0;
$inProgressCount = 0;

foreach ($matches as $match) {
    $status = strtoupper((string) $match['match_status']);

    if ($status === 'SCHEDULED') {
        $upcomingCount++;
    } elseif ($status === 'COMPLETED') {
        $completedCount++;
    } elseif ($status === 'POSTPONED') {
        $postponedCount++;
    } elseif ($status === 'CANCELLED') {
        $cancelledCount++;
    } elseif ($status === 'IN_PROGRESS') {
        $inProgressCount++;
    }
}

$totalMatches = count($matches);

require_once __DIR__ . '/../includes/header.php';

?>

<style>
    /*
    |--------------------------------------------------------------------------
    | Coach Matches Page
    |--------------------------------------------------------------------------
    | Scoped styles help prevent changes to other pages.
    |--------------------------------------------------------------------------
    */

    .coach-matches-page {
        --cm-primary: var(--primary-color, #3157d5);
        --cm-text: var(--text-color, #172033);
        --cm-muted: var(--muted-color, #667085);
        --cm-border: var(--border-color, #e5e7eb);
        --cm-surface: var(--card-bg, #ffffff);
        --cm-soft: #f6f8fc;

        color: var(--cm-text);
    }

    .coach-matches-page * {
        box-sizing: border-box;
    }

    .coach-matches-page .cm-page-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 26px;
    }

    .coach-matches-page .cm-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 8px;
        color: var(--cm-primary);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 1.2px;
        text-transform: uppercase;
    }

    .coach-matches-page .cm-page-heading h1 {
        margin: 0;
        font-size: clamp(25px, 3vw, 34px);
        line-height: 1.2;
        font-weight: 800;
    }

    .coach-matches-page .cm-page-heading p {
        max-width: 650px;
        margin: 10px 0 0;
        color: var(--cm-muted);
        font-size: 14px;
        line-height: 1.7;
    }

    .coach-matches-page .cm-heading-icon {
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

    .coach-matches-page .cm-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 30px;
    }

    .coach-matches-page .cm-summary-card {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
        padding: 20px;
        border: 1px solid var(--cm-border);
        border-radius: 17px;
        background: var(--cm-surface);
        box-shadow: 0 5px 18px rgba(15, 23, 42, 0.035);
        transition: transform 180ms ease, box-shadow 180ms ease;
    }

    .coach-matches-page .cm-summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07);
    }

    .coach-matches-page .cm-summary-icon {
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

    .coach-matches-page .cm-summary-card:nth-child(2) .cm-summary-icon {
        background: #fff7e6;
        color: #b7791f;
    }

    .coach-matches-page .cm-summary-card:nth-child(3) .cm-summary-icon {
        background: #ecfdf3;
        color: #16834a;
    }

    .coach-matches-page .cm-summary-card:nth-child(4) .cm-summary-icon {
        background: #fff1f2;
        color: #be123c;
    }

    .coach-matches-page .cm-summary-label {
        display: block;
        margin-bottom: 5px;
        color: var(--cm-muted);
        font-size: 12px;
        font-weight: 600;
    }

    .coach-matches-page .cm-summary-value {
        display: block;
        font-size: 27px;
        line-height: 1.1;
        font-weight: 800;
    }

    .coach-matches-page .cm-section-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin: 0 0 18px;
    }

    .coach-matches-page .cm-section-heading h2 {
        margin: 0;
        font-size: 21px;
        font-weight: 800;
    }

    .coach-matches-page .cm-section-heading p {
        margin: 5px 0 0;
        color: var(--cm-muted);
        font-size: 13px;
    }

    .coach-matches-page .cm-match-list {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 18px;
    }

    .coach-matches-page .cm-match-card {
        overflow: hidden;
        border: 1px solid var(--cm-border);
        border-radius: 18px;
        background: var(--cm-surface);
        box-shadow: 0 5px 18px rgba(15, 23, 42, 0.035);
        transition: transform 180ms ease, box-shadow 180ms ease;
    }

    .coach-matches-page .cm-match-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.07);
    }

    .coach-matches-page .cm-match-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 22px;
        border-bottom: 1px solid var(--cm-border);
        background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
    }

    .coach-matches-page .cm-match-number {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 7px;
        color: var(--cm-primary);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    .coach-matches-page .cm-tournament-name {
        margin: 0;
        overflow-wrap: anywhere;
        font-size: 17px;
        font-weight: 800;
        line-height: 1.4;
    }

    .coach-matches-page .cm-sport-name {
        margin-top: 5px;
        color: var(--cm-muted);
        font-size: 13px;
    }

    .coach-matches-page .cm-status {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        flex-shrink: 0;
        padding: 7px 11px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .coach-matches-page .cm-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .coach-matches-page .cm-status-scheduled {
        background: #fff7e6;
        color: #a16207;
    }

    .coach-matches-page .cm-status-completed {
        background: #ecfdf3;
        color: #167647;
    }

    .coach-matches-page .cm-status-postponed {
        background: #f3e8ff;
        color: #7e22ce;
    }

    .coach-matches-page .cm-status-cancelled {
        background: #fff1f2;
        color: #be123c;
    }

    .coach-matches-page .cm-status-in-progress {
        background: #eaf2ff;
        color: #1d4ed8;
    }

    .coach-matches-page .cm-status-default {
        background: #f2f4f7;
        color: #475467;
    }

    .coach-matches-page .cm-match-body {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(180px, 0.65fr);
        gap: 22px;
        padding: 22px;
    }

    .coach-matches-page .cm-team-versus {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 70px minmax(0, 1fr);
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .coach-matches-page .cm-team {
        min-width: 0;
        text-align: center;
    }

    .coach-matches-page .cm-team-name {
        margin: 0;
        overflow-wrap: anywhere;
        font-size: 15px;
        font-weight: 800;
        line-height: 1.5;
    }

    .coach-matches-page .cm-team-label {
        display: block;
        margin-bottom: 6px;
        color: var(--cm-muted);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.7px;
        text-transform: uppercase;
    }

    .coach-matches-page .cm-team-score {
        display: block;
        margin-top: 8px;
        font-size: 27px;
        line-height: 1.1;
        font-weight: 900;
    }

    .coach-matches-page .cm-versus {
        color: #98a2b3;
        font-size: 12px;
        font-weight: 800;
        text-align: center;
        text-transform: uppercase;
    }

    .coach-matches-page .cm-match-details {
        display: grid;
        align-content: center;
        gap: 12px;
        padding-left: 20px;
        border-left: 1px solid var(--cm-border);
    }

    .coach-matches-page .cm-detail-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        min-width: 0;
    }

    .coach-matches-page .cm-detail-icon {
        flex: 0 0 20px;
        width: 20px;
        color: var(--cm-primary);
        font-size: 16px;
        text-align: center;
    }

    .coach-matches-page .cm-detail-content {
        min-width: 0;
    }

    .coach-matches-page .cm-detail-label {
        display: block;
        margin-bottom: 3px;
        color: var(--cm-muted);
        font-size: 11px;
        font-weight: 700;
    }

    .coach-matches-page .cm-detail-value {
        display: block;
        overflow-wrap: anywhere;
        font-size: 13px;
        font-weight: 700;
        line-height: 1.5;
    }

    .coach-matches-page .cm-match-footer {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 14px 22px;
        border-top: 1px solid var(--cm-border);
        background: #fbfcfe;
        color: var(--cm-muted);
        font-size: 12px;
        line-height: 1.6;
    }

    .coach-matches-page .cm-match-footer strong {
        color: var(--cm-text);
    }

    .coach-matches-page .cm-empty-state {
        padding: 48px 24px;
        border: 1px dashed var(--cm-border);
        border-radius: 18px;
        background: var(--cm-surface);
        text-align: center;
    }

    .coach-matches-page .cm-empty-icon {
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

    .coach-matches-page .cm-empty-state h3 {
        margin: 0 0 8px;
        font-size: 19px;
        font-weight: 800;
    }

    .coach-matches-page .cm-empty-state p {
        max-width: 480px;
        margin: 0 auto;
        color: var(--cm-muted);
        font-size: 14px;
        line-height: 1.7;
    }

    .coach-matches-page .cm-info-card {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        margin-top: 24px;
        padding: 20px;
        border: 1px solid #dce5ff;
        border-radius: 16px;
        background: #f7f9ff;
    }

    .coach-matches-page .cm-info-icon {
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

    .coach-matches-page .cm-info-card h3 {
        margin: 0 0 5px;
        font-size: 14px;
        font-weight: 800;
    }

    .coach-matches-page .cm-info-card p {
        margin: 0;
        color: var(--cm-muted);
        font-size: 13px;
        line-height: 1.7;
    }

    @media (max-width: 1100px) {
        .coach-matches-page .cm-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .coach-matches-page .cm-match-body {
            grid-template-columns: minmax(0, 1fr);
        }

        .coach-matches-page .cm-match-details {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding: 16px 0 0;
            border-top: 1px solid var(--cm-border);
            border-left: 0;
        }
    }

    @media (max-width: 650px) {
        .coach-matches-page .cm-page-heading {
            gap: 12px;
        }

        .coach-matches-page .cm-heading-icon {
            flex-basis: 46px;
            width: 46px;
            height: 46px;
            border-radius: 14px;
            font-size: 22px;
        }

        .coach-matches-page .cm-summary-grid {
            gap: 12px;
        }

        .coach-matches-page .cm-summary-card {
            gap: 10px;
            padding: 14px;
        }

        .coach-matches-page .cm-summary-icon {
            flex-basis: 38px;
            width: 38px;
            height: 38px;
            border-radius: 11px;
            font-size: 18px;
        }

        .coach-matches-page .cm-summary-value {
            font-size: 23px;
        }

        .coach-matches-page .cm-match-top {
            flex-direction: column;
            padding: 18px;
        }

        .coach-matches-page .cm-match-body {
            padding: 18px;
        }

        .coach-matches-page .cm-team-versus {
            grid-template-columns: minmax(0, 1fr) 42px minmax(0, 1fr);
            gap: 8px;
        }

        .coach-matches-page .cm-team-name {
            font-size: 13px;
        }

        .coach-matches-page .cm-team-score {
            font-size: 24px;
        }

        .coach-matches-page .cm-match-details {
            grid-template-columns: minmax(0, 1fr);
        }

        .coach-matches-page .cm-match-footer {
            padding: 14px 18px;
        }
    }

    @media (max-width: 420px) {
        .coach-matches-page .cm-summary-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .coach-matches-page .cm-page-heading h1 {
            font-size: 25px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .coach-matches-page .cm-summary-card,
        .coach-matches-page .cm-match-card {
            transition: none;
        }
    }
</style>

<div class="coach-matches-page">

    <!-- Page heading -->
    <div class="cm-page-heading">
        <div>
            <span class="cm-eyebrow">Coach Portal</span>

            <h1>My Matches</h1>

            <p>
                Monitor match schedules, team opponents, venues,
                and results for your assigned teams.
            </p>
        </div>

        <div class="cm-heading-icon" aria-hidden="true">
            ⚽
        </div>
    </div>

    <!-- Summary cards -->
    <div class="cm-summary-grid">

        <div class="cm-summary-card">
            <div class="cm-summary-icon" aria-hidden="true">📅</div>

            <div>
                <span class="cm-summary-label">Total Matches</span>
                <strong class="cm-summary-value">
                    <?= $totalMatches ?>
                </strong>
            </div>
        </div>

        <div class="cm-summary-card">
            <div class="cm-summary-icon" aria-hidden="true">⏳</div>

            <div>
                <span class="cm-summary-label">Upcoming</span>
                <strong class="cm-summary-value">
                    <?= $upcomingCount ?>
                </strong>
            </div>
        </div>

        <div class="cm-summary-card">
            <div class="cm-summary-icon" aria-hidden="true">🏁</div>

            <div>
                <span class="cm-summary-label">Completed</span>
                <strong class="cm-summary-value">
                    <?= $completedCount ?>
                </strong>
            </div>
        </div>

        <div class="cm-summary-card">
            <div class="cm-summary-icon" aria-hidden="true">⏸️</div>

            <div>
                <span class="cm-summary-label">Postponed</span>
                <strong class="cm-summary-value">
                    <?= $postponedCount ?>
                </strong>
            </div>
        </div>

    </div>

    <!-- Match schedule -->
    <section>

        <div class="cm-section-heading">
            <div>
                <h2>Match Schedule</h2>
                <p>
                    Matches involving your assigned teams.
                </p>
            </div>
        </div>

        <?php if (empty($matches)): ?>

            <div class="cm-empty-state">
                <div class="cm-empty-icon" aria-hidden="true">
                    ⚽
                </div>

                <h3>No Matches Found</h3>

                <p>
                    Your teams currently do not have any matches.
                    Matches will appear here when they are created
                    and assigned to your teams.
                </p>
            </div>

        <?php else: ?>

            <div class="cm-match-list">

                <?php foreach ($matches as $match): ?>

                    <?php
                    $status = strtoupper(
                        (string) ($match['match_status'] ?? 'UNKNOWN')
                    );

                    $statusClass = coachMatchStatusClass($status);

                    $hasResult =
                        $match['team_a_score'] !== null &&
                        $match['team_b_score'] !== null;

                    $teamAScore = $hasResult
                        ? coachMatchFormatScore($match['team_a_score'])
                        : '—';

                    $teamBScore = $hasResult
                        ? coachMatchFormatScore($match['team_b_score'])
                        : '—';

                    $matchNotes = trim(
                        (string) ($match['notes'] ?? '')
                    );

                    $resultNotes = trim(
                        (string) ($match['result_notes'] ?? '')
                    );

                    $tournamentFormat = trim(
                        (string) ($match['tournament_format'] ?? '')
                    );

                    $scheduledStart = $match['scheduled_start'] ?? null;
                    ?>

                    <article class="cm-match-card">

                        <!-- Match heading -->
                        <div class="cm-match-top">

                            <div>
                                <span class="cm-match-number">
                                    Match #<?= (int) $match['match_number'] ?>
                                </span>

                                <h3 class="cm-tournament-name">
                                    <?= coachMatchEscape($match['tournament_name']) ?>
                                </h3>

                                <div class="cm-sport-name">
                                    <?= coachMatchEscape($match['sport_name']) ?>

                                    <?php if ($tournamentFormat !== ''): ?>
                                        &nbsp;·&nbsp;
                                        <?= coachMatchEscape($tournamentFormat) ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <span class="cm-status cm-status-<?= coachMatchEscape($statusClass) ?>">
                                <span class="cm-status-dot" aria-hidden="true"></span>
                                <?= coachMatchEscape($status) ?>
                            </span>

                        </div>

                        <!-- Teams and match details -->
                        <div class="cm-match-body">

                            <div class="cm-team-versus">

                                <!-- Team A -->
                                <div class="cm-team">
                                    <span class="cm-team-label">Team A</span>

                                    <h4 class="cm-team-name">
                                        <?= coachMatchEscape($match['team_a_name']) ?>
                                    </h4>

                                    <strong class="cm-team-score">
                                        <?= coachMatchEscape($teamAScore) ?>
                                    </strong>
                                </div>

                                <!-- Versus -->
                                <div class="cm-versus">
                                    VS
                                </div>

                                <!-- Team B -->
                                <div class="cm-team">
                                    <span class="cm-team-label">Team B</span>

                                    <h4 class="cm-team-name">
                                        <?= coachMatchEscape($match['team_b_name']) ?>
                                    </h4>

                                    <strong class="cm-team-score">
                                        <?= coachMatchEscape($teamBScore) ?>
                                    </strong>
                                </div>

                            </div>

                            <!-- Match information -->
                            <div class="cm-match-details">

                                <div class="cm-detail-row">
                                    <span class="cm-detail-icon" aria-hidden="true">
                                        📅
                                    </span>

                                    <div class="cm-detail-content">
                                        <span class="cm-detail-label">
                                            Date & Time
                                        </span>

                                        <span class="cm-detail-value">
                                            <?= coachMatchEscape(
                                                coachMatchFormatDate(
                                                    $scheduledStart !== null
                                                        ? (string) $scheduledStart
                                                        : null
                                                )
                                            ) ?>
                                            <br>
                                            <?= coachMatchEscape(
                                                coachMatchFormatTime(
                                                    $scheduledStart !== null
                                                        ? (string) $scheduledStart
                                                        : null
                                                )
                                            ) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="cm-detail-row">
                                    <span class="cm-detail-icon" aria-hidden="true">
                                        📍
                                    </span>

                                    <div class="cm-detail-content">
                                        <span class="cm-detail-label">
                                            Venue
                                        </span>

                                        <span class="cm-detail-value">
                                            <?php
                                            $venueName = trim(
                                                (string) ($match['venue_name'] ?? '')
                                            );
                                            ?>

                                            <?= coachMatchEscape(
                                                $venueName !== ''
                                                    ? $venueName
                                                    : 'Venue not assigned'
                                            ) ?>
                                        </span>
                                    </div>
                                </div>

                            </div>

                        </div>

                        <!-- Optional match notes -->
                        <?php if ($matchNotes !== '' || $resultNotes !== ''): ?>

                            <div class="cm-match-footer">
                                <span aria-hidden="true">ℹ️</span>

                                <div>
                                    <?php if ($matchNotes !== ''): ?>
                                        <strong>Match note:</strong>
                                        <?= coachMatchEscape($matchNotes) ?>
                                    <?php endif; ?>

                                    <?php if ($matchNotes !== '' && $resultNotes !== ''): ?>
                                        <br>
                                    <?php endif; ?>

                                    <?php if ($resultNotes !== ''): ?>
                                        <strong>Result note:</strong>
                                        <?= coachMatchEscape($resultNotes) ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <!-- Information note -->
    <section class="cm-info-card">
        <div class="cm-info-icon" aria-hidden="true">
            ℹ️
        </div>

        <div>
            <h3>Match Information</h3>

            <p>
                Match schedules and results are managed by the Sports
                Coordinator or Administrator. Coaches can use this page
                to monitor upcoming matches, opponents, venues, and
                completed results.
            </p>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>