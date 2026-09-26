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
| Get Player
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
    ':user_id' => $userId
]);

$player = $playerStmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    exit('Player profile not found.');
}

$playerId = (int) $player['player_id'];

/*
|--------------------------------------------------------------------------
| Get Player Tournaments
|--------------------------------------------------------------------------
|
| The tournament is shown only when:
|
| 1. Player belongs to the team
| 2. Team membership is ACTIVE
| 3. Player has not left the team
| 4. Team is registered in the tournament
| 5. Tournament-team participation is ACTIVE
|
|--------------------------------------------------------------------------
*/
$tournamentsStmt = $db->prepare("
    SELECT DISTINCT

        t.tournament_id,
        t.tournament_name,
        t.tournament_format,
        t.start_date,
        t.end_date,
        t.points_win,
        t.points_draw,
        t.points_loss,
        t.tournament_status,

        s.sport_id,
        s.sport_name,

        v.venue_name,

        tm.team_id,
        tm.team_name,

        ts.matches_played,
        ts.wins,
        ts.draws,
        ts.losses,
        ts.score_for,
        ts.score_against,
        ts.score_difference,
        ts.points,
        ts.standing_rank

    FROM team_players tp

    INNER JOIN teams tm
        ON tm.team_id = tp.team_id

    INNER JOIN tournament_teams tt
        ON tt.team_id = tm.team_id
       AND tt.participation_status = 'ACTIVE'

    INNER JOIN tournaments t
        ON t.tournament_id = tt.tournament_id

    INNER JOIN sports s
        ON s.sport_id = t.sport_id

    LEFT JOIN venues v
        ON v.venue_id = t.venue_id

    LEFT JOIN tournament_standings ts
        ON ts.tournament_id = t.tournament_id
       AND ts.team_id = tm.team_id

    WHERE tp.player_id = :player_id
      AND tp.membership_status = 'ACTIVE'
      AND tp.left_at IS NULL

    ORDER BY
        t.start_date DESC,
        t.tournament_name ASC
");

$tournamentsStmt->execute([
    ':player_id' => $playerId
]);

$tournaments = $tournamentsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Page Helpers
|--------------------------------------------------------------------------
*/
$escape = static function ($value): string {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

$formatDate = static function ($date): string {
    if (empty($date)) {
        return 'Not specified';
    }

    $timestamp = strtotime((string) $date);

    if ($timestamp === false) {
        return 'Not specified';
    }

    return date('d M Y', $timestamp);
};

$statusClass = static function ($status): string {
    $status = strtoupper(trim((string) $status));

    switch ($status) {
        case 'ONGOING':
        case 'IN_PROGRESS':
            return 'is-ongoing';

        case 'COMPLETED':
        case 'FINISHED':
            return 'is-completed';

        case 'CANCELLED':
        case 'CANCELED':
            return 'is-cancelled';

        case 'UPCOMING':
        case 'PLANNED':
        case 'REGISTRATION_OPEN':
            return 'is-upcoming';

        default:
            return 'is-default';
    }
};

$hasTournaments = !empty($tournaments);

/*
|--------------------------------------------------------------------------
| Common Header
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* =========================================================
   PLAYER TOURNAMENTS PAGE
   Styles are scoped to this page to avoid affecting others.
   ========================================================= */

.player-tournaments-page {
    --pt-primary: #2855d9;
    --pt-primary-dark: #1d3fa8;
    --pt-primary-soft: #eef3ff;
    --pt-text: #172033;
    --pt-muted: #697586;
    --pt-border: #e6eaf0;
    --pt-surface: #ffffff;
    --pt-background: #f7f9fc;

    width: 100%;
    max-width: 1500px;
    margin: 0 auto;
    color: var(--pt-text);
}

.player-tournaments-page *,
.player-tournaments-page *::before,
.player-tournaments-page *::after {
    box-sizing: border-box;
}

.player-tournaments-page .pt-page-header {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    overflow: hidden;
    padding: 30px 32px;
    margin-bottom: 24px;
    border-radius: 20px;
    color: #ffffff;
    background:
        radial-gradient(
            circle at 88% 20%,
            rgba(255, 255, 255, 0.18),
            transparent 27%
        ),
        linear-gradient(120deg, #2449bf 0%, #3568ed 60%, #4b7cf4 100%);
    box-shadow: 0 12px 30px rgba(40, 85, 217, 0.15);
}

.player-tournaments-page .pt-page-header::after {
    position: absolute;
    right: 110px;
    bottom: -95px;
    width: 230px;
    height: 230px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 50%;
    content: "";
    pointer-events: none;
}

.player-tournaments-page .pt-header-copy {
    position: relative;
    z-index: 1;
    min-width: 0;
}

.player-tournaments-page .pt-eyebrow {
    margin-bottom: 9px;
    color: rgba(255, 255, 255, 0.78);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.7px;
}

.player-tournaments-page .pt-page-header h1 {
    margin: 0 0 9px;
    color: #ffffff;
    font-size: clamp(25px, 3vw, 34px);
    font-weight: 800;
    line-height: 1.2;
}

.player-tournaments-page .pt-page-header p {
    max-width: 620px;
    margin: 0;
    color: rgba(255, 255, 255, 0.88);
    font-size: 14px;
    line-height: 1.7;
}

.player-tournaments-page .pt-header-icon {
    position: relative;
    z-index: 1;
    display: grid;
    flex: 0 0 76px;
    width: 76px;
    height: 76px;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.15);
    font-size: 36px;
    backdrop-filter: blur(8px);
}

/* Player summary */

.player-tournaments-page .pt-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}

.player-tournaments-page .pt-summary-card {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    padding: 19px 20px;
    border: 1px solid var(--pt-border);
    border-radius: 16px;
    background: var(--pt-surface);
    box-shadow: 0 4px 16px rgba(26, 39, 68, 0.035);
}

.player-tournaments-page .pt-summary-icon {
    display: grid;
    flex: 0 0 46px;
    width: 46px;
    height: 46px;
    place-items: center;
    border-radius: 13px;
    background: var(--pt-primary-soft);
    font-size: 21px;
}

.player-tournaments-page .pt-summary-copy {
    min-width: 0;
}

.player-tournaments-page .pt-summary-label {
    display: block;
    margin-bottom: 5px;
    color: var(--pt-muted);
    font-size: 12px;
    font-weight: 600;
}

.player-tournaments-page .pt-summary-value {
    display: block;
    overflow-wrap: anywhere;
    color: var(--pt-text);
    font-size: 17px;
    font-weight: 800;
    line-height: 1.35;
}

/* Section headings */

.player-tournaments-page .pt-section-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin: 0 0 16px;
}

.player-tournaments-page .pt-section-heading h2 {
    margin: 0 0 5px;
    color: var(--pt-text);
    font-size: 20px;
    font-weight: 800;
}

.player-tournaments-page .pt-section-heading p {
    margin: 0;
    color: var(--pt-muted);
    font-size: 13px;
    line-height: 1.6;
}

.player-tournaments-page .pt-count-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    padding: 6px 11px;
    border-radius: 999px;
    color: var(--pt-primary-dark);
    background: var(--pt-primary-soft);
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

/* Tournament card */

.player-tournaments-page .pt-tournament-list {
    display: grid;
    gap: 22px;
}

.player-tournaments-page .pt-tournament-card {
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--pt-border);
    border-radius: 18px;
    background: var(--pt-surface);
    box-shadow: 0 5px 20px rgba(26, 39, 68, 0.045);
}

.player-tournaments-page .pt-tournament-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    padding: 23px 25px;
    border-bottom: 1px solid var(--pt-border);
    background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
}

.player-tournaments-page .pt-tournament-title-wrap {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    min-width: 0;
}

.player-tournaments-page .pt-tournament-icon {
    display: grid;
    flex: 0 0 48px;
    width: 48px;
    height: 48px;
    place-items: center;
    border-radius: 14px;
    background: var(--pt-primary-soft);
    font-size: 23px;
}

.player-tournaments-page .pt-tournament-title {
    min-width: 0;
}

.player-tournaments-page .pt-tournament-title h3 {
    margin: 1px 0 7px;
    color: var(--pt-text);
    font-size: 20px;
    font-weight: 800;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.player-tournaments-page .pt-tournament-subtitle {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 7px;
    margin: 0;
    color: var(--pt-muted);
    font-size: 13px;
    line-height: 1.6;
}

.player-tournaments-page .pt-subtitle-dot {
    color: #a0a8b5;
}

.player-tournaments-page .pt-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding: 7px 11px;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    line-height: 1.3;
    text-align: center;
    text-transform: capitalize;
    white-space: nowrap;
}

.player-tournaments-page .pt-status.is-ongoing {
    color: #087443;
    border-color: #bcebd3;
    background: #e9fbf2;
}

.player-tournaments-page .pt-status.is-completed {
    color: #475569;
    border-color: #dce2ea;
    background: #f1f4f8;
}

.player-tournaments-page .pt-status.is-cancelled {
    color: #b42318;
    border-color: #ffd0cb;
    background: #fff0ee;
}

.player-tournaments-page .pt-status.is-upcoming {
    color: #2451c7;
    border-color: #d2ddff;
    background: #eef3ff;
}

.player-tournaments-page .pt-status.is-default {
    color: #5b6472;
    border-color: #e0e5ec;
    background: #f5f7fa;
}

/* Team banner */

.player-tournaments-page .pt-team-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 15px 25px;
    border-bottom: 1px solid var(--pt-border);
    background: #f8faff;
}

.player-tournaments-page .pt-team-label {
    display: block;
    margin-bottom: 4px;
    color: var(--pt-muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.player-tournaments-page .pt-team-name {
    color: var(--pt-text);
    font-size: 15px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.player-tournaments-page .pt-team-sport {
    color: var(--pt-muted);
    font-size: 12px;
    text-align: right;
}

/* Tournament details */

.player-tournaments-page .pt-card-body {
    padding: 23px 25px;
}

.player-tournaments-page .pt-block-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0 0 15px;
    color: var(--pt-text);
    font-size: 15px;
    font-weight: 800;
}

.player-tournaments-page .pt-block-title-icon {
    display: grid;
    width: 30px;
    height: 30px;
    place-items: center;
    border-radius: 9px;
    background: var(--pt-primary-soft);
    font-size: 15px;
}

.player-tournaments-page .pt-details-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.player-tournaments-page .pt-detail-item {
    min-width: 0;
    padding: 14px 15px;
    border: 1px solid #edf0f5;
    border-radius: 12px;
    background: #ffffff;
}

.player-tournaments-page .pt-detail-label {
    display: block;
    margin-bottom: 7px;
    color: var(--pt-muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.25px;
}

.player-tournaments-page .pt-detail-value {
    display: block;
    color: var(--pt-text);
    font-size: 13px;
    font-weight: 700;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

/* Points system */

.player-tournaments-page .pt-points-panel {
    margin-top: 22px;
    padding: 18px;
    border: 1px solid #e3eaff;
    border-radius: 15px;
    background: #f8faff;
}

.player-tournaments-page .pt-points-panel .pt-block-title {
    margin-bottom: 13px;
}

.player-tournaments-page .pt-points-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.player-tournaments-page .pt-point-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 14px;
    border: 1px solid #e7ecf8;
    border-radius: 11px;
    background: #ffffff;
}

.player-tournaments-page .pt-point-label {
    color: var(--pt-muted);
    font-size: 12px;
    font-weight: 600;
}

.player-tournaments-page .pt-point-value {
    color: var(--pt-primary-dark);
    font-size: 17px;
    font-weight: 800;
}

/* Team standing */

.player-tournaments-page .pt-standing-section {
    padding: 22px 25px 25px;
    border-top: 1px solid var(--pt-border);
    background: #fcfdff;
}

.player-tournaments-page .pt-standing-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 16px;
}

.player-tournaments-page .pt-standing-heading h4 {
    margin: 0 0 5px;
    color: var(--pt-text);
    font-size: 16px;
    font-weight: 800;
}

.player-tournaments-page .pt-standing-heading p {
    margin: 0;
    color: var(--pt-muted);
    font-size: 12px;
    line-height: 1.5;
}

.player-tournaments-page .pt-standing-icon {
    display: grid;
    flex: 0 0 40px;
    width: 40px;
    height: 40px;
    place-items: center;
    border-radius: 12px;
    background: #eef3ff;
    font-size: 19px;
}

.player-tournaments-page .pt-performance-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.player-tournaments-page .pt-performance-item {
    min-width: 0;
    padding: 15px;
    border: 1px solid var(--pt-border);
    border-radius: 12px;
    background: #ffffff;
}

.player-tournaments-page .pt-performance-label {
    display: block;
    margin-bottom: 8px;
    color: var(--pt-muted);
    font-size: 11px;
    font-weight: 700;
}

.player-tournaments-page .pt-performance-value {
    display: block;
    color: var(--pt-text);
    font-size: 23px;
    font-weight: 800;
    line-height: 1.2;
    overflow-wrap: anywhere;
}

.player-tournaments-page .pt-performance-item.is-highlight {
    border-color: #d4e0ff;
    background: #f1f5ff;
}

.player-tournaments-page .pt-performance-item.is-highlight .pt-performance-value {
    color: var(--pt-primary-dark);
}

.player-tournaments-page .pt-score-details {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-top: 12px;
}

.player-tournaments-page .pt-score-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 11px 12px;
    border-radius: 10px;
    background: #f1f4f8;
}

.player-tournaments-page .pt-score-item span {
    color: var(--pt-muted);
    font-size: 11px;
    font-weight: 600;
}

.player-tournaments-page .pt-score-item strong {
    color: var(--pt-text);
    font-size: 13px;
    font-weight: 800;
}

/* Empty states and information */

.player-tournaments-page .pt-empty-state {
    padding: 42px 24px;
    border: 1px dashed #cfd7e4;
    border-radius: 18px;
    background: #ffffff;
    text-align: center;
}

.player-tournaments-page .pt-empty-icon {
    display: grid;
    width: 64px;
    height: 64px;
    margin: 0 auto 16px;
    place-items: center;
    border-radius: 20px;
    background: var(--pt-primary-soft);
    font-size: 29px;
}

.player-tournaments-page .pt-empty-state h3 {
    margin: 0 0 8px;
    color: var(--pt-text);
    font-size: 18px;
    font-weight: 800;
}

.player-tournaments-page .pt-empty-state p {
    max-width: 520px;
    margin: 0 auto;
    color: var(--pt-muted);
    font-size: 13px;
    line-height: 1.7;
}

.player-tournaments-page .pt-info-card {
    margin-top: 25px;
    padding: 22px 24px;
    border: 1px solid var(--pt-border);
    border-radius: 16px;
    background: #ffffff;
}

.player-tournaments-page .pt-info-heading {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 12px;
}

.player-tournaments-page .pt-info-heading h2 {
    margin: 0;
    color: var(--pt-text);
    font-size: 16px;
    font-weight: 800;
}

.player-tournaments-page .pt-info-icon {
    display: grid;
    width: 36px;
    height: 36px;
    place-items: center;
    border-radius: 11px;
    background: var(--pt-primary-soft);
    font-size: 18px;
}

.player-tournaments-page .pt-info-card p {
    margin: 0;
    color: var(--pt-muted);
    font-size: 13px;
    line-height: 1.8;
}

.player-tournaments-page .pt-info-card p + p {
    margin-top: 7px;
}

/* Responsive */

@media (max-width: 1000px) {
    .player-tournaments-page .pt-performance-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .player-tournaments-page .pt-score-details {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .player-tournaments-page .pt-page-header {
        padding: 24px;
    }

    .player-tournaments-page .pt-summary-grid {
        grid-template-columns: 1fr;
        gap: 11px;
    }

    .player-tournaments-page .pt-details-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .player-tournaments-page .pt-tournament-top {
        padding: 20px;
    }

    .player-tournaments-page .pt-team-banner {
        padding: 14px 20px;
    }

    .player-tournaments-page .pt-card-body {
        padding: 20px;
    }

    .player-tournaments-page .pt-standing-section {
        padding: 20px;
    }
}

@media (max-width: 520px) {
    .player-tournaments-page .pt-page-header {
        align-items: flex-start;
        padding: 22px 19px;
        border-radius: 16px;
    }

    .player-tournaments-page .pt-header-icon {
        flex-basis: 48px;
        width: 48px;
        height: 48px;
        border-radius: 14px;
        font-size: 24px;
    }

    .player-tournaments-page .pt-page-header p {
        font-size: 13px;
    }

    .player-tournaments-page .pt-tournament-top {
        flex-direction: column;
        align-items: flex-start;
        gap: 13px;
    }

    .player-tournaments-page .pt-tournament-title h3 {
        font-size: 18px;
    }

    .player-tournaments-page .pt-team-banner {
        align-items: flex-start;
        flex-direction: column;
        gap: 7px;
    }

    .player-tournaments-page .pt-team-sport {
        text-align: left;
    }

    .player-tournaments-page .pt-details-grid,
    .player-tournaments-page .pt-points-grid,
    .player-tournaments-page .pt-performance-grid,
    .player-tournaments-page .pt-score-details {
        grid-template-columns: 1fr 1fr;
    }

    .player-tournaments-page .pt-point-item {
        align-items: flex-start;
        flex-direction: column;
        gap: 5px;
    }

    .player-tournaments-page .pt-performance-item {
        padding: 13px;
    }

    .player-tournaments-page .pt-performance-value {
        font-size: 21px;
    }

    .player-tournaments-page .pt-standing-heading {
        align-items: flex-start;
    }

    .player-tournaments-page .pt-info-card {
        padding: 19px;
    }
}
</style>

<div class="dashboard-page player-tournaments-page">

    <!-- PAGE HEADER -->
    <section class="pt-page-header">
        <div class="pt-header-copy">
            <div class="pt-eyebrow">PLAYER AREA / TOURNAMENTS</div>

            <h1>My Tournaments</h1>

            <p>
                View your team's tournament schedule, points system,
                and current performance in one place.
            </p>
        </div>

        <div class="pt-header-icon" aria-hidden="true">
            🏆
        </div>
    </section>

    <!-- PLAYER SUMMARY -->
    <section class="pt-summary-grid" aria-label="Player summary">

        <div class="pt-summary-card">
            <div class="pt-summary-icon" aria-hidden="true">👤</div>

            <div class="pt-summary-copy">
                <span class="pt-summary-label">Player</span>
                <strong class="pt-summary-value">
                    <?= $escape($player['full_name']) ?>
                </strong>
            </div>
        </div>

        <div class="pt-summary-card">
            <div class="pt-summary-icon" aria-hidden="true">🪪</div>

            <div class="pt-summary-copy">
                <span class="pt-summary-label">Student ID</span>
                <strong class="pt-summary-value">
                    <?= $escape($player['student_id']) ?>
                </strong>
            </div>
        </div>

        <div class="pt-summary-card">
            <div class="pt-summary-icon" aria-hidden="true">🏆</div>

            <div class="pt-summary-copy">
                <span class="pt-summary-label">My Tournaments</span>
                <strong class="pt-summary-value">
                    <?= count($tournaments) ?>
                </strong>
            </div>
        </div>

    </section>

    <!-- TOURNAMENT LIST -->
    <section class="pt-tournaments-section">

        <div class="pt-section-heading">
            <div>
                <h2>My Tournament List</h2>
                <p>
                    Tournaments registered by your active team.
                </p>
            </div>

            <span class="pt-count-pill">
                <?= count($tournaments) ?>
                <?= count($tournaments) === 1 ? 'Tournament' : 'Tournaments' ?>
            </span>
        </div>

        <?php if ($hasTournaments): ?>

            <div class="pt-tournament-list">

                <?php foreach ($tournaments as $tournament): ?>

                    <?php
                    $tournamentStatus = strtoupper(
                        trim((string) $tournament['tournament_status'])
                    );

                    $displayStatus = ucwords(
                        strtolower(
                            str_replace(
                                ['_', '-'],
                                ' ',
                                $tournamentStatus
                            )
                        )
                    );

                    $hasStanding = $tournament['standing_rank'] !== null;
                    ?>

                    <article class="pt-tournament-card">

                        <!-- Tournament heading -->
                        <div class="pt-tournament-top">

                            <div class="pt-tournament-title-wrap">

                                <div class="pt-tournament-icon" aria-hidden="true">
                                    🏆
                                </div>

                                <div class="pt-tournament-title">

                                    <h3>
                                        <?= $escape($tournament['tournament_name']) ?>
                                    </h3>

                                    <p class="pt-tournament-subtitle">
                                        <span>
                                            <?= $escape($tournament['sport_name']) ?>
                                        </span>

                                        <span class="pt-subtitle-dot">•</span>

                                        <span>
                                            <?= $escape($tournament['tournament_format']) ?>
                                        </span>
                                    </p>

                                </div>

                            </div>

                            <span class="pt-status <?= $escape($statusClass($tournamentStatus)) ?>">
                                <?= $escape($displayStatus !== '' ? $displayStatus : 'Status unavailable') ?>
                            </span>

                        </div>

                        <!-- Team name -->
                        <div class="pt-team-banner">

                            <div>
                                <span class="pt-team-label">Participating team</span>

                                <div class="pt-team-name">
                                    <?= $escape($tournament['team_name']) ?>
                                </div>
                            </div>

                            <div class="pt-team-sport">
                                <?= $escape($tournament['sport_name']) ?>
                            </div>

                        </div>

                        <!-- Tournament details -->
                        <div class="pt-card-body">

                            <h4 class="pt-block-title">
                                <span class="pt-block-title-icon" aria-hidden="true">📋</span>
                                Tournament Details
                            </h4>

                            <div class="pt-details-grid">

                                <div class="pt-detail-item">
                                    <span class="pt-detail-label">Sport</span>
                                    <strong class="pt-detail-value">
                                        <?= $escape($tournament['sport_name']) ?>
                                    </strong>
                                </div>

                                <div class="pt-detail-item">
                                    <span class="pt-detail-label">Format</span>
                                    <strong class="pt-detail-value">
                                        <?= $escape($tournament['tournament_format']) ?>
                                    </strong>
                                </div>

                                <div class="pt-detail-item">
                                    <span class="pt-detail-label">Venue</span>
                                    <strong class="pt-detail-value">
                                        <?= $escape(
                                            !empty($tournament['venue_name'])
                                                ? $tournament['venue_name']
                                                : 'Not specified'
                                        ) ?>
                                    </strong>
                                </div>

                                <div class="pt-detail-item">
                                    <span class="pt-detail-label">Start Date</span>
                                    <strong class="pt-detail-value">
                                        <?= $escape($formatDate($tournament['start_date'])) ?>
                                    </strong>
                                </div>

                                <div class="pt-detail-item">
                                    <span class="pt-detail-label">End Date</span>
                                    <strong class="pt-detail-value">
                                        <?= $escape($formatDate($tournament['end_date'])) ?>
                                    </strong>
                                </div>

                                <div class="pt-detail-item">
                                    <span class="pt-detail-label">Tournament ID</span>
                                    <strong class="pt-detail-value">
                                        #<?= (int) $tournament['tournament_id'] ?>
                                    </strong>
                                </div>

                            </div>

                            <!-- Points system -->
                            <div class="pt-points-panel">

                                <h4 class="pt-block-title">
                                    <span class="pt-block-title-icon" aria-hidden="true">🎯</span>
                                    Points System
                                </h4>

                                <div class="pt-points-grid">

                                    <div class="pt-point-item">
                                        <span class="pt-point-label">Win</span>
                                        <strong class="pt-point-value">
                                            <?= $escape($tournament['points_win']) ?>
                                        </strong>
                                    </div>

                                    <div class="pt-point-item">
                                        <span class="pt-point-label">Draw</span>
                                        <strong class="pt-point-value">
                                            <?= $escape($tournament['points_draw']) ?>
                                        </strong>
                                    </div>

                                    <div class="pt-point-item">
                                        <span class="pt-point-label">Loss</span>
                                        <strong class="pt-point-value">
                                            <?= $escape($tournament['points_loss']) ?>
                                        </strong>
                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- Team standing -->
                        <div class="pt-standing-section">

                            <div class="pt-standing-heading">

                                <div>
                                    <h4>My Team Standing</h4>
                                    <p>Current tournament performance</p>
                                </div>

                                <div class="pt-standing-icon" aria-hidden="true">
                                    📊
                                </div>

                            </div>

                            <?php if ($hasStanding): ?>

                                <div class="pt-performance-grid">

                                    <div class="pt-performance-item is-highlight">
                                        <span class="pt-performance-label">Rank</span>
                                        <strong class="pt-performance-value">
                                            #<?= $escape($tournament['standing_rank']) ?>
                                        </strong>
                                    </div>

                                    <div class="pt-performance-item is-highlight">
                                        <span class="pt-performance-label">Points</span>
                                        <strong class="pt-performance-value">
                                            <?= $escape($tournament['points']) ?>
                                        </strong>
                                    </div>

                                    <div class="pt-performance-item">
                                        <span class="pt-performance-label">Played</span>
                                        <strong class="pt-performance-value">
                                            <?= $escape($tournament['matches_played']) ?>
                                        </strong>
                                    </div>

                                    <div class="pt-performance-item">
                                        <span class="pt-performance-label">Wins</span>
                                        <strong class="pt-performance-value">
                                            <?= $escape($tournament['wins']) ?>
                                        </strong>
                                    </div>

                                </div>

                                <div class="pt-score-details">

                                    <div class="pt-score-item">
                                        <span>Draws</span>
                                        <strong><?= $escape($tournament['draws']) ?></strong>
                                    </div>

                                    <div class="pt-score-item">
                                        <span>Losses</span>
                                        <strong><?= $escape($tournament['losses']) ?></strong>
                                    </div>

                                    <div class="pt-score-item">
                                        <span>Score For</span>
                                        <strong><?= $escape($tournament['score_for']) ?></strong>
                                    </div>

                                    <div class="pt-score-item">
                                        <span>Score Against</span>
                                        <strong><?= $escape($tournament['score_against']) ?></strong>
                                    </div>

                                    <div class="pt-score-item">
                                        <span>Difference</span>
                                        <strong><?= $escape($tournament['score_difference']) ?></strong>
                                    </div>

                                    <div class="pt-score-item">
                                        <span>Tournament Points</span>
                                        <strong><?= $escape($tournament['points']) ?></strong>
                                    </div>

                                </div>

                            <?php else: ?>

                                <div class="pt-empty-state">
                                    <div class="pt-empty-icon" aria-hidden="true">📊</div>

                                    <h3>Standings not available yet</h3>

                                    <p>
                                        Your team does not have a calculated
                                        tournament standing yet. Standings will
                                        appear here when available.
                                    </p>
                                </div>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="pt-empty-state">
                <div class="pt-empty-icon" aria-hidden="true">🏆</div>

                <h3>No tournaments found</h3>

                <p>
                    Your active team is not currently registered in any
                    tournament. When your team joins a tournament, it will
                    appear here.
                </p>
            </div>

        <?php endif; ?>

    </section>

    <!-- TOURNAMENT INFORMATION -->
    <section class="pt-info-card">

        <div class="pt-info-heading">
            <div class="pt-info-icon" aria-hidden="true">ℹ️</div>
            <h2>Tournament Information</h2>
        </div>

        <p>
            Tournament participation is managed at the team level.
            Your tournaments appear here when your active team is registered
            for a tournament.
        </p>

        <p>
            Tournament standings are calculated from completed match results.
        </p>

    </section>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>