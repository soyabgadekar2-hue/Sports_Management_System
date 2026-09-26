<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

/* Get and validate team ID */
$teamId = filter_var(
    $_GET['team_id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($teamId === false || $teamId === null || $teamId <= 0) {
    http_response_code(400);
    exit('Invalid team ID.');
}

/* Get team details */
$teamStatement = $pdo->prepare(
    'SELECT
        t.team_id,
        t.sport_id,
        t.team_name,
        t.team_category,
        t.roster_limit,
        t.team_status,
        s.sport_name,
        cp.coach_id,
        u.full_name AS coach_name
     FROM teams t
     INNER JOIN sports s
        ON s.sport_id = t.sport_id
     LEFT JOIN coach_profiles cp
        ON cp.coach_id = t.coach_id
     LEFT JOIN users u
        ON u.user_id = cp.user_id
     WHERE t.team_id = :team_id
     LIMIT 1'
);

$teamStatement->execute([
    ':team_id' => $teamId
]);

$team = $teamStatement->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    http_response_code(404);
    exit('Team not found.');
}

/* Get team players */
$playersStatement = $pdo->prepare(
    'SELECT
        tp.team_player_id,
        tp.player_id,
        tp.joined_at,
        tp.left_at,
        tp.membership_status,
        pp.student_id,
        u.full_name,
        u.email,
        pp.department,
        pp.course,
        pp.academic_year
     FROM team_players tp
     INNER JOIN player_profiles pp
        ON pp.player_id = tp.player_id
     INNER JOIN users u
        ON u.user_id = pp.user_id
     WHERE tp.team_id = :team_id
     ORDER BY
        CASE
            WHEN tp.membership_status = "ACTIVE" THEN 1
            ELSE 2
        END,
        tp.joined_at ASC'
);

$playersStatement->execute([
    ':team_id' => $teamId
]);

$players = $playersStatement->fetchAll(PDO::FETCH_ASSOC);

/* Count active players */
$countStatement = $pdo->prepare(
    'SELECT COUNT(*)
     FROM team_players
     WHERE team_id = :team_id
       AND membership_status = "ACTIVE"'
);

$countStatement->execute([
    ':team_id' => $teamId
]);

$activePlayerCount = (int) $countStatement->fetchColumn();

/* Get eligible players */
$eligiblePlayersStatement = $pdo->prepare(
    'SELECT
        pp.player_id,
        pp.student_id,
        u.full_name,
        u.email
     FROM player_profiles pp
     INNER JOIN users u
        ON u.user_id = pp.user_id
     INNER JOIN player_sports ps
        ON ps.player_id = pp.player_id
       AND ps.sport_id = :sport_id
       AND ps.participation_status = "ACTIVE"
     WHERE pp.player_status = "ACTIVE"
       AND u.account_status = "APPROVED"
       AND NOT EXISTS (
            SELECT 1
            FROM team_players existing_tp
            INNER JOIN teams existing_team
                ON existing_team.team_id = existing_tp.team_id
            WHERE existing_tp.player_id = pp.player_id
              AND existing_tp.membership_status = "ACTIVE"
              AND existing_team.sport_id = :sport_id_check
       )
     ORDER BY u.full_name ASC'
);

$eligiblePlayersStatement->execute([
    ':sport_id' => $team['sport_id'],
    ':sport_id_check' => $team['sport_id']
]);

$eligiblePlayers = $eligiblePlayersStatement->fetchAll(PDO::FETCH_ASSOC);

/* Messages */
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

/* Roster percentage */
$rosterLimit = (int) $team['roster_limit'];

$rosterPercentage = $rosterLimit > 0
    ? min(100, ($activePlayerCount / $rosterLimit) * 100)
    : 0;

/* Team status */
$teamStatus = strtoupper((string) $team['team_status']);

if ($teamStatus === 'ACTIVE') {
    $statusClass = 'status-active';
} elseif (in_array($teamStatus, ['INACTIVE', 'SUSPENDED'], true)) {
    $statusClass = 'status-inactive';
} else {
    $statusClass = 'status-other';
}

/* Helpers */
$escape = static function ($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$pageTitle = 'Manage Team';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* =========================================================
   SPORTSYNC - MANAGE TEAM
   ========================================================= */

.manage-team-page {
    max-width: 1240px;
    margin: 0 auto;
    padding-bottom: 32px;
    color: #172033;
}

.manage-team-page *,
.manage-team-page *::before,
.manage-team-page *::after {
    box-sizing: border-box;
}

.manage-team-page a {
    text-decoration: none;
}

.team-back-link {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin: 0 0 20px;
    color: #4263eb;
    font-size: 14px;
    font-weight: 700;
    transition: color 0.2s ease;
}

.team-back-link:hover {
    color: #2448d8;
}

.team-page-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}

.team-heading-copy {
    min-width: 0;
}

.team-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    color: #4263eb;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.team-eyebrow::before {
    width: 22px;
    height: 2px;
    border-radius: 10px;
    background: #4263eb;
    content: "";
}

.team-page-heading h1 {
    margin: 0 0 8px;
    color: #111827;
    font-size: clamp(25px, 3vw, 34px);
    font-weight: 800;
    letter-spacing: -0.035em;
    line-height: 1.15;
}

.team-page-heading p {
    max-width: 650px;
    margin: 0;
    color: #718096;
    font-size: 14px;
    line-height: 1.65;
}

.team-heading-icon {
    display: flex;
    width: 62px;
    height: 62px;
    flex: 0 0 62px;
    align-items: center;
    justify-content: center;
    border: 1px solid #dbe5ff;
    border-radius: 18px;
    background: linear-gradient(145deg, #f3f6ff, #e7edff);
    color: #4263eb;
    font-size: 27px;
    box-shadow: 0 8px 22px rgba(66, 99, 235, 0.08);
}

/* Notices */

.team-notice {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 18px;
    padding: 14px 16px;
    border: 1px solid;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.5;
}

.team-notice-icon {
    display: flex;
    width: 27px;
    height: 27px;
    flex: 0 0 27px;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-weight: 800;
}

.team-notice-success {
    border-color: #bbf7d0;
    background: #f0fdf4;
    color: #166534;
}

.team-notice-success .team-notice-icon {
    background: #dcfce7;
}

.team-notice-error {
    border-color: #fecaca;
    background: #fff5f5;
    color: #991b1b;
}

.team-notice-error .team-notice-icon {
    background: #fee2e2;
}

/* Main team overview */

.team-overview {
    overflow: hidden;
    margin-bottom: 22px;
    border: 1px solid #e5eaf2;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.045);
}

.team-overview-banner {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 24px;
    border-bottom: 1px solid #e9edf5;
    background:
        radial-gradient(circle at 92% 15%, rgba(66, 99, 235, 0.10), transparent 28%),
        linear-gradient(110deg, #ffffff, #f8faff);
}

.team-overview-identity {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 15px;
}

.team-avatar {
    display: flex;
    width: 56px;
    height: 56px;
    flex: 0 0 56px;
    align-items: center;
    justify-content: center;
    border: 1px solid #dce5ff;
    border-radius: 16px;
    background: #edf2ff;
    color: #4263eb;
    font-size: 25px;
}

.team-overview-identity h2 {
    overflow-wrap: anywhere;
    margin: 0 0 6px;
    color: #172033;
    font-size: clamp(19px, 2vw, 24px);
    font-weight: 800;
    letter-spacing: -0.025em;
}

.team-overview-identity p {
    margin: 0;
    color: #778399;
    font-size: 13px;
}

.team-edit-button {
    display: inline-flex;
    min-height: 42px;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 15px;
    border: 1px solid #dbe4ff;
    border-radius: 10px;
    background: #fff;
    color: #3155d9;
    font-size: 13px;
    font-weight: 750;
    transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
}

.team-edit-button:hover {
    transform: translateY(-1px);
    background: #f4f6ff;
    box-shadow: 0 5px 14px rgba(66, 99, 235, 0.10);
}

.team-overview-details {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.team-detail {
    min-width: 0;
    padding: 19px 22px;
    border-right: 1px solid #edf0f5;
    border-bottom: 1px solid #edf0f5;
}

.team-detail:nth-child(4n) {
    border-right: 0;
}

.team-detail-label {
    margin-bottom: 7px;
    color: #8994a7;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.team-detail-value {
    overflow-wrap: anywhere;
    color: #253047;
    font-size: 14px;
    font-weight: 750;
}

.team-detail-muted {
    color: #9aa4b4;
    font-weight: 500;
}

.team-status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
}

.team-status::before {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    content: "";
}

.status-active {
    background: #eafaf0;
    color: #16834a;
}

.status-inactive {
    background: #fff0f0;
    color: #c24141;
}

.status-other {
    background: #f1f4f8;
    color: #667085;
}

/* Roster meter */

.roster-capacity {
    grid-column: 1 / -1;
    padding: 20px 22px 22px;
}

.roster-capacity-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 11px;
}

.roster-capacity-title {
    color: #46536a;
    font-size: 13px;
    font-weight: 750;
}

.roster-capacity-count {
    color: #1d2b45;
    font-size: 13px;
    font-weight: 800;
    white-space: nowrap;
}

.roster-meter {
    height: 9px;
    overflow: hidden;
    border-radius: 999px;
    background: #edf1f7;
}

.roster-meter-fill {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #4263eb, #7290ff);
    transition: width 0.35s ease;
}

.roster-capacity-note {
    margin-top: 9px;
    color: #8994a7;
    font-size: 11px;
}

/* Content columns */

.team-content-grid {
    display: grid;
    grid-template-columns: minmax(280px, 0.85fr) minmax(0, 1.15fr);
    align-items: start;
    gap: 22px;
    margin-bottom: 22px;
}

.team-panel {
    min-width: 0;
    overflow: hidden;
    border: 1px solid #e5eaf2;
    border-radius: 17px;
    background: #fff;
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.04);
}

.team-panel-heading {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px;
    border-bottom: 1px solid #edf0f5;
}

.team-panel-icon {
    display: flex;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    align-items: center;
    justify-content: center;
    border: 1px solid #e0e8ff;
    border-radius: 12px;
    background: #f0f4ff;
    color: #4263eb;
    font-size: 19px;
}

.team-panel-heading h2 {
    margin: 0 0 4px;
    color: #202b40;
    font-size: 17px;
    font-weight: 800;
}

.team-panel-heading p {
    margin: 0;
    color: #8994a7;
    font-size: 12px;
    line-height: 1.5;
}

.team-panel-body {
    padding: 20px;
}

/* Add player form */

.add-player-label {
    display: block;
    margin-bottom: 8px;
    color: #3f4b60;
    font-size: 12px;
    font-weight: 800;
}

.add-player-select {
    display: block;
    width: 100%;
    min-height: 46px;
    padding: 10px 12px;
    border: 1px solid #d7deea;
    border-radius: 10px;
    outline: none;
    background: #fff;
    color: #263249;
    font: inherit;
    font-size: 13px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.add-player-select:focus {
    border-color: #6581f1;
    box-shadow: 0 0 0 3px rgba(66, 99, 235, 0.12);
}

.add-player-button {
    display: flex;
    width: 100%;
    min-height: 45px;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 13px;
    padding: 11px 15px;
    border: 1px solid #4263eb;
    border-radius: 10px;
    background: #4263eb;
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
}

.add-player-button:hover {
    transform: translateY(-1px);
    background: #3153dc;
    box-shadow: 0 7px 15px rgba(66, 99, 235, 0.18);
}

.add-player-button:focus-visible,
.team-edit-button:focus-visible,
.remove-player-button:focus-visible {
    outline: 3px solid rgba(66, 99, 235, 0.25);
    outline-offset: 2px;
}

/* Information boxes */

.team-info-box {
    margin-top: 18px;
    padding: 15px;
    border: 1px solid #dfe8ff;
    border-radius: 12px;
    background: #f7f9ff;
}

.team-info-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 9px;
    color: #3153b8;
    font-size: 12px;
    font-weight: 800;
}

.team-info-box ul {
    margin: 0;
    padding-left: 18px;
    color: #56678e;
    font-size: 12px;
    line-height: 1.85;
}

.team-alert-box {
    margin: 20px;
    padding: 15px;
    border: 1px solid #fecaca;
    border-radius: 11px;
    background: #fff5f5;
    color: #a12c2c;
    font-size: 13px;
    line-height: 1.6;
}

.team-empty-eligible {
    padding: 25px 20px;
    text-align: center;
}

.team-empty-icon {
    display: flex;
    width: 52px;
    height: 52px;
    align-items: center;
    justify-content: center;
    margin: 0 auto 13px;
    border: 1px solid #e5eaf2;
    border-radius: 15px;
    background: #f7f9fc;
    font-size: 23px;
}

.team-empty-eligible h3 {
    margin: 0 0 7px;
    color: #253047;
    font-size: 15px;
}

.team-empty-eligible p {
    margin: 0;
    color: #8792a5;
    font-size: 12px;
    line-height: 1.6;
}

/* Membership rules */

.team-rules-list {
    display: grid;
    gap: 13px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.team-rules-list li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    color: #5e6b80;
    font-size: 12px;
    line-height: 1.65;
}

.team-rules-check {
    display: flex;
    width: 21px;
    height: 21px;
    flex: 0 0 21px;
    align-items: center;
    justify-content: center;
    margin-top: 1px;
    border-radius: 7px;
    background: #eafaf0;
    color: #16834a;
    font-size: 11px;
    font-weight: 900;
}

/* Team players */

.team-players-panel {
    margin-bottom: 24px;
}

.team-players-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 20px 22px;
    border-bottom: 1px solid #edf0f5;
}

.team-players-heading h2 {
    margin: 0 0 5px;
    color: #202b40;
    font-size: 18px;
    font-weight: 800;
}

.team-players-heading p {
    margin: 0;
    color: #8994a7;
    font-size: 12px;
}

.team-players-badge {
    display: inline-flex;
    min-width: 38px;
    height: 34px;
    align-items: center;
    justify-content: center;
    padding: 0 11px;
    border: 1px solid #dfe7ff;
    border-radius: 10px;
    background: #f0f4ff;
    color: #3155d9;
    font-size: 13px;
    font-weight: 800;
}

.team-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.team-players-table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
}

.team-players-table th {
    padding: 13px 15px;
    border-bottom: 1px solid #e9edf4;
    background: #f8faff;
    color: #7d899d;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-align: left;
    text-transform: uppercase;
    white-space: nowrap;
}

.team-players-table td {
    padding: 14px 15px;
    border-bottom: 1px solid #f0f2f6;
    color: #4c586d;
    font-size: 12px;
    vertical-align: middle;
}

.team-players-table tbody tr {
    transition: background 0.15s ease;
}

.team-players-table tbody tr:hover {
    background: #f9fbff;
}

.team-players-table tbody tr:last-child td {
    border-bottom: 0;
}

.player-id {
    color: #94a0b2;
    font-weight: 700;
}

.player-name {
    color: #253047;
    font-weight: 800;
}

.player-email {
    color: #7d899d;
}

.player-membership-status {
    display: inline-flex;
    align-items: center;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.player-status-active {
    background: #eafaf0;
    color: #16834a;
}

.player-status-inactive {
    background: #f1f4f8;
    color: #667085;
}

.remove-player-form {
    margin: 0;
}

.remove-player-button {
    min-height: 33px;
    padding: 7px 11px;
    border: 1px solid #ffd1d1;
    border-radius: 8px;
    background: #fff5f5;
    color: #b83232;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
    transition: background 0.2s ease, border-color 0.2s ease;
}

.remove-player-button:hover {
    border-color: #f6aaaa;
    background: #ffe8e8;
}

.team-empty-roster {
    padding: 48px 20px;
    text-align: center;
}

.team-empty-roster-icon {
    display: flex;
    width: 58px;
    height: 58px;
    align-items: center;
    justify-content: center;
    margin: 0 auto 14px;
    border: 1px solid #e0e8ff;
    border-radius: 17px;
    background: #f0f4ff;
    font-size: 25px;
}

.team-empty-roster h3 {
    margin: 0 0 7px;
    color: #253047;
    font-size: 16px;
}

.team-empty-roster p {
    margin: 0;
    color: #8994a7;
    font-size: 12px;
    line-height: 1.6;
}

/* Responsive */

@media (max-width: 1000px) {
    .team-overview-details {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .team-detail:nth-child(4n) {
        border-right: 1px solid #edf0f5;
    }

    .team-detail:nth-child(2n) {
        border-right: 0;
    }

    .team-content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 650px) {
    .manage-team-page {
        padding-bottom: 20px;
    }

    .team-page-heading {
        align-items: flex-start;
    }

    .team-heading-icon {
        width: 48px;
        height: 48px;
        flex-basis: 48px;
        border-radius: 14px;
        font-size: 22px;
    }

    .team-page-heading p {
        font-size: 13px;
    }

    .team-overview-banner {
        align-items: flex-start;
        flex-direction: column;
        padding: 19px;
    }

    .team-edit-button {
        width: 100%;
    }

    .team-overview-details {
        grid-template-columns: 1fr 1fr;
    }

    .team-detail {
        padding: 16px;
    }

    .team-detail:nth-child(2n),
    .team-detail:nth-child(4n) {
        border-right: 0;
    }

    .roster-capacity {
        padding: 17px 16px 19px;
    }

    .team-panel-heading,
    .team-panel-body {
        padding: 17px;
    }

    .team-players-heading {
        padding: 17px;
    }

    .team-players-heading h2 {
        font-size: 16px;
    }
}

@media (max-width: 420px) {
    .team-overview-details {
        grid-template-columns: 1fr;
    }

    .team-detail,
    .team-detail:nth-child(2n),
    .team-detail:nth-child(4n) {
        border-right: 0;
    }

    .team-overview-identity {
        align-items: flex-start;
    }

    .team-avatar {
        width: 46px;
        height: 46px;
        flex-basis: 46px;
        border-radius: 13px;
        font-size: 21px;
    }
}
</style>

<div class="dashboard-page">
    <main class="manage-team-page">

        <a href="admin-teams.php" class="team-back-link">
            <span aria-hidden="true">←</span>
            Back to Team Management
        </a>

        <header class="team-page-heading">
            <div class="team-heading-copy">
                <div class="team-eyebrow">Team Management</div>
                <h1>Manage Team</h1>
                <p>
                    View team information, manage the roster, and add or remove
                    eligible players.
                </p>
            </div>

            <div class="team-heading-icon" aria-hidden="true">👥</div>
        </header>

        <?php if ($message === 'player_added'): ?>
            <div class="team-notice team-notice-success" role="status">
                <span class="team-notice-icon">✓</span>
                <div><strong>Player added successfully.</strong> The team roster has been updated.</div>
            </div>
        <?php elseif ($message === 'player_removed'): ?>
            <div class="team-notice team-notice-success" role="status">
                <span class="team-notice-icon">✓</span>
                <div><strong>Player removed successfully.</strong> Their active membership has ended.</div>
            </div>
        <?php elseif ($message === 'updated'): ?>
            <div class="team-notice team-notice-success" role="status">
                <span class="team-notice-icon">✓</span>
                <div><strong>Team updated successfully.</strong> Your changes have been saved.</div>
            </div>
        <?php endif; ?>

        <?php if ($error === 'invalid'): ?>
            <div class="team-notice team-notice-error" role="alert">
                <span class="team-notice-icon">!</span>
                <div><strong>Invalid request.</strong> Please check the information and try again.</div>
            </div>
        <?php elseif ($error === 'not_enrolled'): ?>
            <div class="team-notice team-notice-error" role="alert">
                <span class="team-notice-icon">!</span>
                <div><strong>Player not eligible.</strong> This player is not enrolled in this sport.</div>
            </div>
        <?php elseif ($error === 'already_in_team'): ?>
            <div class="team-notice team-notice-error" role="alert">
                <span class="team-notice-icon">!</span>
                <div><strong>Player already belongs to a team.</strong> A player can have only one active team in the same sport.</div>
            </div>
        <?php endif; ?>

        <!-- Team overview -->
        <section class="team-overview">
            <div class="team-overview-banner">
                <div class="team-overview-identity">
                    <div class="team-avatar" aria-hidden="true">🏆</div>

                    <div>
                        <h2><?= $escape($team['team_name']) ?></h2>
                        <p>
                            Team #<?= (int) $team['team_id'] ?>
                            <span aria-hidden="true">·</span>
                            <?= $escape($team['sport_name']) ?>
                        </p>
                    </div>
                </div>

                <a
                    href="edit-team.php?team_id=<?= (int) $team['team_id'] ?>"
                    class="team-edit-button"
                >
                    <span aria-hidden="true">✏️</span>
                    Edit Team
                </a>
            </div>

            <div class="team-overview-details">
                <div class="team-detail">
                    <div class="team-detail-label">Sport</div>
                    <div class="team-detail-value"><?= $escape($team['sport_name']) ?></div>
                </div>

                <div class="team-detail">
                    <div class="team-detail-label">Category</div>
                    <div class="team-detail-value"><?= $escape($team['team_category']) ?></div>
                </div>

                <div class="team-detail">
                    <div class="team-detail-label">Coach</div>
                    <div class="team-detail-value">
                        <?php if (!empty($team['coach_name'])): ?>
                            <?= $escape($team['coach_name']) ?>
                        <?php else: ?>
                            <span class="team-detail-muted">Not assigned</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="team-detail">
                    <div class="team-detail-label">Team Status</div>
                    <div class="team-detail-value">
                        <span class="team-status <?= $statusClass ?>">
                            <?= $escape($teamStatus) ?>
                        </span>
                    </div>
                </div>

                <div class="team-detail">
                    <div class="team-detail-label">Team ID</div>
                    <div class="team-detail-value">#<?= (int) $team['team_id'] ?></div>
                </div>

                <div class="team-detail">
                    <div class="team-detail-label">Active Players</div>
                    <div class="team-detail-value">
                        <?= $activePlayerCount ?> / <?= $rosterLimit ?>
                    </div>
                </div>

                <div class="team-detail">
                    <div class="team-detail-label">Available Slots</div>
                    <div class="team-detail-value">
                        <?= max(0, $rosterLimit - $activePlayerCount) ?>
                    </div>
                </div>

                <div class="team-detail">
                    <div class="team-detail-label">Roster Capacity</div>
                    <div class="team-detail-value">
                        <?= $rosterLimit > 0 ? number_format($rosterPercentage, 0) . '%' : 'Not set' ?>
                    </div>
                </div>

                <div class="roster-capacity">
                    <div class="roster-capacity-top">
                        <span class="roster-capacity-title">Roster Capacity</span>
                        <span class="roster-capacity-count">
                            <?= $activePlayerCount ?> of <?= $rosterLimit ?> players
                        </span>
                    </div>

                    <div
                        class="roster-meter"
                        role="progressbar"
                        aria-label="Roster capacity"
                        aria-valuemin="0"
                        aria-valuemax="<?= max(1, $rosterLimit) ?>"
                        aria-valuenow="<?= min($activePlayerCount, max(1, $rosterLimit)) ?>"
                    >
                        <div
                            class="roster-meter-fill"
                            style="width: <?= number_format($rosterPercentage, 2, '.', '') ?>%;"
                        ></div>
                    </div>

                    <div class="roster-capacity-note">
                        <?php if ($activePlayerCount >= $rosterLimit): ?>
                            The roster has reached its maximum capacity.
                        <?php else: ?>
                            <?= max(0, $rosterLimit - $activePlayerCount) ?> roster
                            <?= max(0, $rosterLimit - $activePlayerCount) === 1 ? 'slot is' : 'slots are' ?>
                            available.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Add player and team rules -->
        <div class="team-content-grid">

            <section class="team-panel">
                <div class="team-panel-heading">
                    <div class="team-panel-icon" aria-hidden="true">＋</div>
                    <div>
                        <h2>Add Player</h2>
                        <p>Add an eligible player to this team.</p>
                    </div>
                </div>

                <?php if ($activePlayerCount >= $rosterLimit): ?>

                    <div class="team-alert-box">
                        <strong>This team is full.</strong><br>
                        Remove a current player or update the roster limit before adding another player.
                    </div>

                <?php elseif (empty($eligiblePlayers)): ?>

                    <div class="team-empty-eligible">
                        <div class="team-empty-icon" aria-hidden="true">👤</div>
                        <h3>No eligible players available</h3>
                        <p>
                            No players currently meet all requirements for this team.
                            Eligible players must have an approved account, an active
                            profile, enrollment in this sport, and no other active team
                            in the same sport.
                        </p>
                    </div>

                <?php else: ?>

                    <div class="team-panel-body">
                        <form action="add-team-player.php" method="POST">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= $escape(csrfToken()) ?>"
                            >

                            <input
                                type="hidden"
                                name="team_id"
                                value="<?= (int) $team['team_id'] ?>"
                            >

                            <label class="add-player-label" for="player_id">
                                Select an eligible player
                            </label>

                            <select
                                name="player_id"
                                id="player_id"
                                class="add-player-select"
                                required
                            >
                                <option value="">Choose a player...</option>

                                <?php foreach ($eligiblePlayers as $player): ?>
                                    <option value="<?= (int) $player['player_id'] ?>">
                                        <?= $escape($player['full_name']) ?>
                                        — <?= $escape($player['student_id']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" class="add-player-button">
                                <span aria-hidden="true">＋</span>
                                Add Player to Team
                            </button>
                        </form>

                        <div class="team-info-box">
                            <div class="team-info-title">
                                <span aria-hidden="true">ⓘ</span>
                                Player eligibility
                            </div>
                            <ul>
                                <li>Player profile must be active.</li>
                                <li>Player account must be approved.</li>
                                <li>Player must be enrolled in <?= $escape($team['sport_name']) ?>.</li>
                                <li>Player must not have another active team in this sport.</li>
                            </ul>
                        </div>
                    </div>

                <?php endif; ?>
            </section>

            <section class="team-panel">
                <div class="team-panel-heading">
                    <div class="team-panel-icon" aria-hidden="true">ⓘ</div>
                    <div>
                        <h2>Team Information</h2>
                        <p>Membership rules and roster guidance.</p>
                    </div>
                </div>

                <div class="team-panel-body">
                    <ul class="team-rules-list">
                        <li>
                            <span class="team-rules-check">✓</span>
                            <span>Players must have an approved account before joining a team.</span>
                        </li>
                        <li>
                            <span class="team-rules-check">✓</span>
                            <span>Players must be enrolled in the team's sport.</span>
                        </li>
                        <li>
                            <span class="team-rules-check">✓</span>
                            <span>A player can have only one active team in the same sport.</span>
                        </li>
                        <li>
                            <span class="team-rules-check">✓</span>
                            <span>The team cannot exceed its configured roster limit.</span>
                        </li>
                        <li>
                            <span class="team-rules-check">✓</span>
                            <span>Removing a player ends their active membership in this team.</span>
                        </li>
                    </ul>

                    <div class="team-info-box">
                        <div class="team-info-title">
                            <span aria-hidden="true">🏅</span>
                            Current team
                        </div>
                        <ul>
                            <li>Sport: <?= $escape($team['sport_name']) ?></li>
                            <li>Category: <?= $escape($team['team_category']) ?></li>
                            <li>Roster limit: <?= $rosterLimit ?> players</li>
                            <li>Current active roster: <?= $activePlayerCount ?> players</li>
                        </ul>
                    </div>
                </div>
            </section>

        </div>

        <!-- Team players -->
        <section class="team-panel team-players-panel">
            <div class="team-players-heading">
                <div>
                    <h2>Team Players</h2>
                    <p>View player details and manage active team membership.</p>
                </div>

                <div class="team-players-badge" aria-label="<?= count($players) ?> players">
                    <?= count($players) ?>
                </div>
            </div>

            <?php if (empty($players)): ?>

                <div class="team-empty-roster">
                    <div class="team-empty-roster-icon" aria-hidden="true">👥</div>
                    <h3>No players added yet</h3>
                    <p>Add an eligible player using the form above to build this team's roster.</p>
                </div>

            <?php else: ?>

                <div class="team-table-wrap">
                    <table class="team-players-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student ID</th>
                                <th>Player</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Course</th>
                                <th>Academic Year</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($players as $player): ?>
                                <?php
                                $playerMembershipStatus = strtoupper(
                                    (string) $player['membership_status']
                                );

                                $playerStatusClass = $playerMembershipStatus === 'ACTIVE'
                                    ? 'player-status-active'
                                    : 'player-status-inactive';
                                ?>

                                <tr>
                                    <td>
                                        <span class="player-id">
                                            #<?= (int) $player['team_player_id'] ?>
                                        </span>
                                    </td>

                                    <td><?= $escape($player['student_id']) ?></td>

                                    <td>
                                        <span class="player-name">
                                            <?= $escape($player['full_name']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="player-email">
                                            <?= $escape($player['email']) ?>
                                        </span>
                                    </td>

                                    <td><?= $escape($player['department']) ?></td>
                                    <td><?= $escape($player['course']) ?></td>
                                    <td><?= $escape($player['academic_year']) ?></td>
                                    <td><?= $escape($player['joined_at']) ?></td>

                                    <td>
                                        <span class="player-membership-status <?= $playerStatusClass ?>">
                                            <?= $escape($playerMembershipStatus) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if ($playerMembershipStatus === 'ACTIVE'): ?>
                                            <form
                                                action="remove-team-player.php"
                                                method="POST"
                                                class="remove-player-form"
                                                onsubmit="return confirm('Remove this player from the team?');"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= $escape(csrfToken()) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="team_id"
                                                    value="<?= (int) $team['team_id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="team_player_id"
                                                    value="<?= (int) $player['team_player_id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="remove-player-button"
                                                >
                                                    Remove
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="team-detail-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </section>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>