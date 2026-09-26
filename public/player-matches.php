<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';

requireRole('PLAYER');

$user = currentUser();
$db = db();

$userId = (int) $user['id'];

/*
|-------------------------------------------------------------------------- 
| Get Player Profile
|-------------------------------------------------------------------------- 
*/
$playerStmt = $db->prepare("
    SELECT
        p.player_id,
        p.student_id,
        u.full_name
    FROM player_profiles p
    INNER JOIN users u
        ON u.user_id = p.user_id
    WHERE p.user_id = :user_id
    LIMIT 1
");

$playerStmt->execute([
    ':user_id' => $userId,
]);

$player = $playerStmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    exit('Player profile not found.');
}

$playerId = (int) $player['player_id'];

/*
|-------------------------------------------------------------------------- 
| Get Player Matches
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

        pmp.team_id AS player_team_id,

        t.tournament_id,
        t.tournament_name,
        t.tournament_format,

        s.sport_name,

        v.venue_name,

        team_a.team_id AS team_a_id,
        team_a.team_name AS team_a_name,

        team_b.team_id AS team_b_id,
        team_b.team_name AS team_b_name,

        mr.team_a_score,
        mr.team_b_score,
        mr.winner_team_id,
        mr.result_notes

    FROM player_match_participation pmp

    INNER JOIN matches m
        ON m.match_id = pmp.match_id

    INNER JOIN tournaments t
        ON t.tournament_id = m.tournament_id

    INNER JOIN sports s
        ON s.sport_id = t.sport_id

    LEFT JOIN venues v
        ON v.venue_id = m.venue_id

    INNER JOIN teams team_a
        ON team_a.team_id = m.team_a_id

    INNER JOIN teams team_b
        ON team_b.team_id = m.team_b_id

    LEFT JOIN match_results mr
        ON mr.match_id = m.match_id

    INNER JOIN team_players tp
        ON tp.player_id = pmp.player_id
       AND tp.team_id = pmp.team_id
       AND tp.membership_status = 'ACTIVE'
       AND tp.left_at IS NULL

    WHERE pmp.player_id = :player_id

    ORDER BY
        CASE
            WHEN m.match_status = 'SCHEDULED' THEN 0
            ELSE 1
        END,
        CASE
            WHEN m.match_status = 'SCHEDULED' THEN m.scheduled_start
            ELSE NULL
        END ASC,
        CASE
            WHEN m.match_status <> 'SCHEDULED' THEN m.scheduled_start
            ELSE NULL
        END DESC,
        m.match_number ASC
");

$matchesStmt->execute([
    ':player_id' => $playerId,
]);

$matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|-------------------------------------------------------------------------- 
| Helpers
|-------------------------------------------------------------------------- 
*/
function playerMatchEscape(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

function playerMatchStatusClass(string $status): string
{
    return match ($status) {
        'COMPLETED' => 'pm-status-completed',
        'POSTPONED' => 'pm-status-postponed',
        'CANCELLED' => 'pm-status-cancelled',
        'SCHEDULED' => 'pm-status-scheduled',
        default => 'pm-status-default',
    };
}

function playerMatchResult(array $match): string
{
    if (
        $match['team_a_score'] === null ||
        $match['team_b_score'] === null
    ) {
        return 'Result not available';
    }

    $teamAScore = (float) $match['team_a_score'];
    $teamBScore = (float) $match['team_b_score'];

    $playerTeamId = (int) ($match['player_team_id'] ?? 0);

    if ($teamAScore === $teamBScore) {
        return 'Draw';
    }

    if (
        $match['winner_team_id'] !== null &&
        $playerTeamId > 0
    ) {
        return (int) $match['winner_team_id'] === $playerTeamId
            ? 'Won'
            : 'Lost';
    }

    if ($playerTeamId === (int) $match['team_a_id']) {
        return $teamAScore > $teamBScore ? 'Won' : 'Lost';
    }

    if ($playerTeamId === (int) $match['team_b_id']) {
        return $teamBScore > $teamAScore ? 'Won' : 'Lost';
    }

    return 'Result available';
}

function playerMatchResultClass(string $result): string
{
    return match ($result) {
        'Won' => 'pm-result-won',
        'Lost' => 'pm-result-lost',
        'Draw' => 'pm-result-draw',
        default => 'pm-result-neutral',
    };
}

function playerMatchDate(?string $date, string $format): string
{
    if (!$date) {
        return 'Not scheduled';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return 'Not scheduled';
    }

    return date($format, $timestamp);
}

/*
|-------------------------------------------------------------------------- 
| Summary and Match Groups
|-------------------------------------------------------------------------- 
*/
$totalMatches = count($matches);
$completedMatches = 0;
$upcomingMatches = 0;
$wonMatches = 0;
$drawMatches = 0;
$lostMatches = 0;

$upcomingList = [];
$otherMatchesList = [];

foreach ($matches as $match) {
    $status = (string) $match['match_status'];

    if ($status === 'COMPLETED') {
        $completedMatches++;

        $result = playerMatchResult($match);

        if ($result === 'Won') {
            $wonMatches++;
        } elseif ($result === 'Draw') {
            $drawMatches++;
        } elseif ($result === 'Lost') {
            $lostMatches++;
        }
    }

    if ($status === 'SCHEDULED') {
        $upcomingMatches++;
        $upcomingList[] = $match;
    } else {
        $otherMatchesList[] = $match;
    }
}

/*
|-------------------------------------------------------------------------- 
| Header
|-------------------------------------------------------------------------- 
*/
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* =========================================================
   PLAYER MATCHES PAGE
   Scoped styles to avoid affecting other pages.
   ========================================================= */

.player-matches-page {
    --pm-primary: #2457d6;
    --pm-primary-soft: #edf3ff;
    --pm-text: #172033;
    --pm-muted: #687386;
    --pm-border: #e6eaf1;
    --pm-surface: #ffffff;
    --pm-page-bg: #f6f8fc;

    color: var(--pm-text);
    width: 100%;
    max-width: 1440px;
    margin: 0 auto;
    padding: 4px 0 28px;
}

.player-matches-page * {
    box-sizing: border-box;
}

.pm-page-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}

.pm-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: var(--pm-primary);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.3px;
    text-transform: uppercase;
}

.pm-eyebrow::before {
    content: "";
    width: 22px;
    height: 3px;
    border-radius: 10px;
    background: var(--pm-primary);
}

.pm-page-heading h1 {
    margin: 0;
    color: var(--pm-text);
    font-size: clamp(25px, 3vw, 34px);
    line-height: 1.2;
    font-weight: 800;
    letter-spacing: -0.7px;
}

.pm-page-heading p {
    margin: 9px 0 0;
    color: var(--pm-muted);
    font-size: 14px;
    line-height: 1.6;
}

.pm-heading-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 58px;
    width: 58px;
    height: 58px;
    border: 1px solid #dce6ff;
    border-radius: 18px;
    background: var(--pm-primary-soft);
    color: var(--pm-primary);
    font-size: 26px;
}

/* Summary cards */
.pm-summary-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 30px;
}

.pm-summary-card {
    display: flex;
    align-items: center;
    gap: 13px;
    min-width: 0;
    padding: 17px;
    border: 1px solid var(--pm-border);
    border-radius: 15px;
    background: var(--pm-surface);
    box-shadow: 0 3px 12px rgba(26, 39, 68, 0.035);
}

.pm-summary-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 42px;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: var(--pm-primary-soft);
    color: var(--pm-primary);
    font-size: 18px;
    font-weight: 800;
}

.pm-summary-card:nth-child(2) .pm-summary-icon {
    background: #fff5e6;
    color: #b76a00;
}

.pm-summary-card:nth-child(3) .pm-summary-icon {
    background: #e9f8ef;
    color: #16834b;
}

.pm-summary-card:nth-child(4) .pm-summary-icon {
    background: #e9f8ef;
    color: #16834b;
}

.pm-summary-card:nth-child(5) .pm-summary-icon {
    background: #f0edff;
    color: #6b4acb;
}

.pm-summary-card:nth-child(6) .pm-summary-icon {
    background: #fff0f0;
    color: #c53d46;
}

.pm-summary-content {
    min-width: 0;
}

.pm-summary-label {
    display: block;
    margin-bottom: 5px;
    color: var(--pm-muted);
    font-size: 12px;
    font-weight: 600;
    line-height: 1.35;
}

.pm-summary-value {
    display: block;
    color: var(--pm-text);
    font-size: 25px;
    font-weight: 800;
    line-height: 1.1;
}

/* Section headings */
.pm-section {
    margin-top: 28px;
}

.pm-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 15px;
}

.pm-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    color: var(--pm-text);
    font-size: 19px;
    font-weight: 800;
}

.pm-section-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: var(--pm-primary);
}

.pm-section-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    height: 26px;
    padding: 0 9px;
    border-radius: 20px;
    background: #edf1f7;
    color: #566174;
    font-size: 12px;
    font-weight: 800;
}

/* Match card */
.pm-match-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

/* Match History cards use the full available section width. */
.pm-match-history-list {
    grid-template-columns: minmax(0, 1fr);
}

.pm-match-card {
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--pm-border);
    border-radius: 17px;
    background: var(--pm-surface);
    box-shadow: 0 4px 15px rgba(26, 39, 68, 0.045);
    transition: transform 160ms ease, box-shadow 160ms ease;
}

.pm-match-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(26, 39, 68, 0.08);
}

.pm-match-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 18px 20px;
    border-bottom: 1px solid #eef1f6;
    background: #fbfcff;
}

.pm-match-competition {
    min-width: 0;
}

.pm-match-number {
    display: block;
    margin-bottom: 5px;
    color: var(--pm-primary);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.7px;
    text-transform: uppercase;
}

.pm-match-competition h3 {
    margin: 0;
    color: var(--pm-text);
    font-size: 16px;
    font-weight: 800;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.pm-match-sport {
    margin-top: 4px;
    color: var(--pm-muted);
    font-size: 12px;
    font-weight: 600;
}

.pm-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    padding: 6px 10px;
    border-radius: 30px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.35px;
    text-transform: uppercase;
    white-space: nowrap;
}

.pm-status-scheduled {
    background: #eaf1ff;
    color: #2457c5;
}

.pm-status-completed {
    background: #e7f7ed;
    color: #167647;
}

.pm-status-postponed {
    background: #fff4df;
    color: #9a6100;
}

.pm-status-cancelled {
    background: #ffebec;
    color: #b4232d;
}

.pm-status-default {
    background: #edf0f5;
    color: #566174;
}

/* Teams and score */
.pm-matchup {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: 14px;
    padding: 24px 20px;
}

.pm-team {
    min-width: 0;
    text-align: center;
}

.pm-team-label {
    display: block;
    margin-bottom: 8px;
    color: #8a94a6;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}

.pm-team-name {
    display: block;
    color: #344054;
    font-size: 15px;
    font-weight: 750;
    line-height: 1.45;
    overflow-wrap: anywhere;
}

.pm-team-name.pm-my-team {
    color: var(--pm-primary);
}

.pm-you-label {
    display: inline-block;
    margin-top: 7px;
    padding: 3px 8px;
    border-radius: 20px;
    background: var(--pm-primary-soft);
    color: var(--pm-primary);
    font-size: 10px;
    font-weight: 800;
}

.pm-score-box {
    min-width: 90px;
    text-align: center;
}

.pm-score {
    display: block;
    color: var(--pm-text);
    font-size: clamp(22px, 2.5vw, 30px);
    font-weight: 900;
    letter-spacing: -0.8px;
    line-height: 1.2;
    white-space: nowrap;
}

.pm-versus {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border: 1px solid var(--pm-border);
    border-radius: 50%;
    background: #f8faff;
    color: #778196;
    font-size: 11px;
    font-weight: 900;
}

.pm-score-caption {
    display: block;
    margin-top: 6px;
    color: var(--pm-muted);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}

/* Match details */
.pm-match-details {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px 20px;
    padding: 17px 20px;
    border-top: 1px solid #eef1f6;
    border-bottom: 1px solid #eef1f6;
}

.pm-detail {
    min-width: 0;
}

.pm-detail-label {
    display: block;
    margin-bottom: 5px;
    color: #8791a3;
    font-size: 11px;
    font-weight: 650;
}

.pm-detail-value {
    display: block;
    color: #344054;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.45;
    overflow-wrap: anywhere;
}

/* Result / match message */
.pm-match-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 15px 20px;
}

.pm-result-label {
    color: var(--pm-muted);
    font-size: 12px;
    font-weight: 700;
}

.pm-result-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
}

.pm-result-won {
    background: #e7f7ed;
    color: #167647;
}

.pm-result-lost {
    background: #ffebec;
    color: #b4232d;
}

.pm-result-draw {
    background: #f0edff;
    color: #6b4acb;
}

.pm-result-neutral {
    background: #edf1f7;
    color: #566174;
}

.pm-match-note {
    margin: 0;
    padding: 0 20px 17px;
    color: var(--pm-muted);
    font-size: 12px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.pm-match-note strong {
    color: #465166;
}

/* Empty state */
.pm-empty-state {
    padding: 44px 20px;
    border: 1px dashed #cfd7e5;
    border-radius: 17px;
    background: #fbfcff;
    text-align: center;
}

.pm-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 58px;
    height: 58px;
    margin: 0 auto 15px;
    border-radius: 18px;
    background: var(--pm-primary-soft);
    color: var(--pm-primary);
    font-size: 25px;
}

.pm-empty-state h3 {
    margin: 0 0 8px;
    color: var(--pm-text);
    font-size: 18px;
    font-weight: 800;
}

.pm-empty-state p {
    max-width: 430px;
    margin: 0 auto;
    color: var(--pm-muted);
    font-size: 13px;
    line-height: 1.6;
}

/* Responsive layout */
@media (max-width: 1250px) {
    .pm-summary-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .pm-match-list {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .player-matches-page {
        padding-top: 0;
    }

    .pm-page-heading {
        margin-bottom: 20px;
    }

    .pm-heading-icon {
        flex-basis: 48px;
        width: 48px;
        height: 48px;
        border-radius: 14px;
        font-size: 22px;
    }

    .pm-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 25px;
    }

    .pm-summary-card {
        gap: 10px;
        padding: 13px;
    }

    .pm-summary-icon {
        flex-basis: 36px;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        font-size: 15px;
    }

    .pm-summary-value {
        font-size: 22px;
    }

    .pm-match-top {
        padding: 15px;
    }

    .pm-matchup {
        gap: 8px;
        padding: 20px 12px;
    }

    .pm-team-name {
        font-size: 13px;
    }

    .pm-score-box {
        min-width: 68px;
    }

    .pm-versus {
        width: 32px;
        height: 32px;
    }

    .pm-match-details {
        gap: 13px;
        padding: 15px;
    }

    .pm-match-footer {
        padding: 14px 15px;
    }

    .pm-match-note {
        padding: 0 15px 15px;
    }
}

@media (max-width: 390px) {
    .pm-summary-card {
        align-items: flex-start;
        flex-direction: column;
    }

    .pm-match-top {
        flex-direction: column;
        align-items: flex-start;
    }

    .pm-match-details {
        grid-template-columns: 1fr;
    }
}

@media (prefers-reduced-motion: reduce) {
    .pm-match-card {
        transition: none;
    }

    .pm-match-card:hover {
        transform: none;
    }
}
</style>

<div class="dashboard-page player-matches-page">

    <!-- Page heading -->
    <header class="pm-page-heading">
        <div>
            <div class="pm-eyebrow">Player Area</div>
            <h1>My Matches</h1>
            <p>
                Track your upcoming fixtures, review completed matches,
                and check your results.
            </p>
        </div>

        <div class="pm-heading-icon" aria-hidden="true">⚽</div>
    </header>

    <!-- Match summary -->
    <section class="pm-summary-grid" aria-label="Match summary">

        <div class="pm-summary-card">
            <div class="pm-summary-icon" aria-hidden="true">M</div>
            <div class="pm-summary-content">
                <span class="pm-summary-label">Total Matches</span>
                <strong class="pm-summary-value"><?= $totalMatches ?></strong>
            </div>
        </div>

        <div class="pm-summary-card">
            <div class="pm-summary-icon" aria-hidden="true">◷</div>
            <div class="pm-summary-content">
                <span class="pm-summary-label">Upcoming</span>
                <strong class="pm-summary-value"><?= $upcomingMatches ?></strong>
            </div>
        </div>

        <div class="pm-summary-card">
            <div class="pm-summary-icon" aria-hidden="true">✓</div>
            <div class="pm-summary-content">
                <span class="pm-summary-label">Completed</span>
                <strong class="pm-summary-value"><?= $completedMatches ?></strong>
            </div>
        </div>

        <div class="pm-summary-card">
            <div class="pm-summary-icon" aria-hidden="true">W</div>
            <div class="pm-summary-content">
                <span class="pm-summary-label">Wins</span>
                <strong class="pm-summary-value"><?= $wonMatches ?></strong>
            </div>
        </div>

        <div class="pm-summary-card">
            <div class="pm-summary-icon" aria-hidden="true">D</div>
            <div class="pm-summary-content">
                <span class="pm-summary-label">Draws</span>
                <strong class="pm-summary-value"><?= $drawMatches ?></strong>
            </div>
        </div>

        <div class="pm-summary-card">
            <div class="pm-summary-icon" aria-hidden="true">L</div>
            <div class="pm-summary-content">
                <span class="pm-summary-label">Losses</span>
                <strong class="pm-summary-value"><?= $lostMatches ?></strong>
            </div>
        </div>

    </section>

    <?php if (empty($matches)): ?>

        <section class="pm-empty-state">
            <div class="pm-empty-icon" aria-hidden="true">⚽</div>
            <h3>No matches found</h3>
            <p>
                You do not have any match participation records yet.
                Your matches will appear here once you are included in a fixture.
            </p>
        </section>

    <?php else: ?>

        <!-- Upcoming matches -->
        <?php if (!empty($upcomingList)): ?>

            <section class="pm-section">
                <div class="pm-section-heading">
                    <h2 class="pm-section-title">
                        <span class="pm-section-dot"></span>
                        Upcoming Matches
                    </h2>

                    <span class="pm-section-count">
                        <?= count($upcomingList) ?>
                    </span>
                </div>

                <div class="pm-match-list">

                    <?php foreach ($upcomingList as $match): ?>
                        <?php
                        $status = (string) $match['match_status'];
                        $playerTeamId = (int) $match['player_team_id'];

                        $isPlayerTeamA = $playerTeamId === (int) $match['team_a_id'];
                        $isPlayerTeamB = $playerTeamId === (int) $match['team_b_id'];
                        ?>

                        <article class="pm-match-card">

                            <div class="pm-match-top">
                                <div class="pm-match-competition">
                                    <span class="pm-match-number">
                                        Match #<?= playerMatchEscape((string) $match['match_number']) ?>
                                    </span>

                                    <h3>
                                        <?= playerMatchEscape((string) $match['tournament_name']) ?>
                                    </h3>

                                    <div class="pm-match-sport">
                                        <?= playerMatchEscape((string) $match['sport_name']) ?>
                                        ·
                                        <?= playerMatchEscape((string) $match['tournament_format']) ?>
                                    </div>
                                </div>

                                <span class="pm-status-badge <?= playerMatchStatusClass($status) ?>">
                                    <?= playerMatchEscape($status) ?>
                                </span>
                            </div>

                            <div class="pm-matchup">

                                <div class="pm-team">
                                    <span class="pm-team-label">Team A</span>

                                    <strong class="pm-team-name <?= $isPlayerTeamA ? 'pm-my-team' : '' ?>">
                                        <?= playerMatchEscape((string) $match['team_a_name']) ?>
                                    </strong>

                                    <?php if ($isPlayerTeamA): ?>
                                        <span class="pm-you-label">Your Team</span>
                                    <?php endif; ?>
                                </div>

                                <div class="pm-score-box">
                                    <span class="pm-versus">VS</span>
                                    <span class="pm-score-caption">Not played</span>
                                </div>

                                <div class="pm-team">
                                    <span class="pm-team-label">Team B</span>

                                    <strong class="pm-team-name <?= $isPlayerTeamB ? 'pm-my-team' : '' ?>">
                                        <?= playerMatchEscape((string) $match['team_b_name']) ?>
                                    </strong>

                                    <?php if ($isPlayerTeamB): ?>
                                        <span class="pm-you-label">Your Team</span>
                                    <?php endif; ?>
                                </div>

                            </div>

                            <div class="pm-match-details">

                                <div class="pm-detail">
                                    <span class="pm-detail-label">Date</span>
                                    <strong class="pm-detail-value">
                                        <?= playerMatchEscape(playerMatchDate(
                                            $match['scheduled_start'],
                                            'd M Y'
                                        )) ?>
                                    </strong>
                                </div>

                                <div class="pm-detail">
                                    <span class="pm-detail-label">Time</span>
                                    <strong class="pm-detail-value">
                                        <?= playerMatchEscape(playerMatchDate(
                                            $match['scheduled_start'],
                                            'h:i A'
                                        )) ?>
                                    </strong>
                                </div>

                                <div class="pm-detail">
                                    <span class="pm-detail-label">Venue</span>
                                    <strong class="pm-detail-value">
                                        <?= playerMatchEscape(
                                            $match['venue_name'] !== null
                                                ? (string) $match['venue_name']
                                                : 'Not specified'
                                        ) ?>
                                    </strong>
                                </div>

                                <div class="pm-detail">
                                    <span class="pm-detail-label">Your Team</span>
                                    <strong class="pm-detail-value">
                                        <?= playerMatchEscape(
                                            $isPlayerTeamA
                                                ? (string) $match['team_a_name']
                                                : (string) $match['team_b_name']
                                        ) ?>
                                    </strong>
                                </div>

                            </div>

                            <div class="pm-match-footer">
                                <span class="pm-result-label">
                                    Be ready for your scheduled match
                                </span>

                                <span class="pm-result-badge pm-result-neutral">
                                    Upcoming
                                </span>
                            </div>

                            <?php if (!empty($match['notes'])): ?>
                                <p class="pm-match-note">
                                    <strong>Match Notes:</strong>
                                    <?= nl2br(playerMatchEscape((string) $match['notes'])) ?>
                                </p>
                            <?php endif; ?>

                        </article>
                    <?php endforeach; ?>

                </div>
            </section>

        <?php endif; ?>

        <!-- Completed and other matches -->
        <?php if (!empty($otherMatchesList)): ?>

            <section class="pm-section">
                <div class="pm-section-heading">
                    <h2 class="pm-section-title">
                        <span class="pm-section-dot"></span>
                        Match History
                    </h2>

                    <span class="pm-section-count">
                        <?= count($otherMatchesList) ?>
                    </span>
                </div>

                <!-- History cards span the full available width -->
                <div class="pm-match-list pm-match-history-list">

                    <?php foreach ($otherMatchesList as $match): ?>
                        <?php
                        $status = (string) $match['match_status'];
                        $playerTeamId = (int) $match['player_team_id'];

                        $isPlayerTeamA = $playerTeamId === (int) $match['team_a_id'];
                        $isPlayerTeamB = $playerTeamId === (int) $match['team_b_id'];

                        $hasScore = (
                            $match['team_a_score'] !== null &&
                            $match['team_b_score'] !== null
                        );

                        $result = $status === 'COMPLETED'
                            ? playerMatchResult($match)
                            : $status;

                        $resultClass = playerMatchResultClass($result);
                        ?>

                        <article class="pm-match-card">

                            <div class="pm-match-top">
                                <div class="pm-match-competition">
                                    <span class="pm-match-number">
                                        Match #<?= playerMatchEscape((string) $match['match_number']) ?>
                                    </span>

                                    <h3>
                                        <?= playerMatchEscape((string) $match['tournament_name']) ?>
                                    </h3>

                                    <div class="pm-match-sport">
                                        <?= playerMatchEscape((string) $match['sport_name']) ?>
                                        ·
                                        <?= playerMatchEscape((string) $match['tournament_format']) ?>
                                    </div>
                                </div>

                                <span class="pm-status-badge <?= playerMatchStatusClass($status) ?>">
                                    <?= playerMatchEscape($status) ?>
                                </span>
                            </div>

                            <div class="pm-matchup">

                                <div class="pm-team">
                                    <span class="pm-team-label">Team A</span>

                                    <strong class="pm-team-name <?= $isPlayerTeamA ? 'pm-my-team' : '' ?>">
                                        <?= playerMatchEscape((string) $match['team_a_name']) ?>
                                    </strong>

                                    <?php if ($isPlayerTeamA): ?>
                                        <span class="pm-you-label">Your Team</span>
                                    <?php endif; ?>
                                </div>

                                <div class="pm-score-box">
                                    <?php if ($hasScore): ?>
                                        <strong class="pm-score">
                                            <?= playerMatchEscape((string) $match['team_a_score']) ?>
                                            <span style="color:#9aa3b2;">:</span>
                                            <?= playerMatchEscape((string) $match['team_b_score']) ?>
                                        </strong>
                                        <span class="pm-score-caption">Final score</span>
                                    <?php else: ?>
                                        <span class="pm-versus">VS</span>
                                        <span class="pm-score-caption">No score</span>
                                    <?php endif; ?>
                                </div>

                                <div class="pm-team">
                                    <span class="pm-team-label">Team B</span>

                                    <strong class="pm-team-name <?= $isPlayerTeamB ? 'pm-my-team' : '' ?>">
                                        <?= playerMatchEscape((string) $match['team_b_name']) ?>
                                    </strong>

                                    <?php if ($isPlayerTeamB): ?>
                                        <span class="pm-you-label">Your Team</span>
                                    <?php endif; ?>
                                </div>

                            </div>

                            <div class="pm-match-details">

                                <div class="pm-detail">
                                    <span class="pm-detail-label">Date</span>
                                    <strong class="pm-detail-value">
                                        <?= playerMatchEscape(playerMatchDate(
                                            $match['scheduled_start'],
                                            'd M Y'
                                        )) ?>
                                    </strong>
                                </div>

                                <div class="pm-detail">
                                    <span class="pm-detail-label">Time</span>
                                    <strong class="pm-detail-value">
                                        <?= playerMatchEscape(playerMatchDate(
                                            $match['scheduled_start'],
                                            'h:i A'
                                        )) ?>
                                    </strong>
                                </div>

                                <div class="pm-detail">
                                    <span class="pm-detail-label">Venue</span>
                                    <strong class="pm-detail-value">
                                        <?= playerMatchEscape(
                                            $match['venue_name'] !== null
                                                ? (string) $match['venue_name']
                                                : 'Not specified'
                                        ) ?>
                                    </strong>
                                </div>

                                <div class="pm-detail">
                                    <span class="pm-detail-label">Your Team</span>
                                    <strong class="pm-detail-value">
                                        <?= playerMatchEscape(
                                            $isPlayerTeamA
                                                ? (string) $match['team_a_name']
                                                : (string) $match['team_b_name']
                                        ) ?>
                                    </strong>
                                </div>

                            </div>

                            <div class="pm-match-footer">
                                <span class="pm-result-label">
                                    <?= $status === 'COMPLETED' ? 'Your match result' : 'Match update' ?>
                                </span>

                                <span class="pm-result-badge <?= $resultClass ?>">
                                    <?= playerMatchEscape($result) ?>
                                </span>
                            </div>

                            <?php if (!empty($match['result_notes'])): ?>
                                <p class="pm-match-note">
                                    <strong>Result Notes:</strong>
                                    <?= nl2br(playerMatchEscape((string) $match['result_notes'])) ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($match['notes'])): ?>
                                <p class="pm-match-note">
                                    <strong>Match Notes:</strong>
                                    <?= nl2br(playerMatchEscape((string) $match['notes'])) ?>
                                </p>
                            <?php endif; ?>

                        </article>
                    <?php endforeach; ?>

                </div>
            </section>

        <?php endif; ?>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>