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
| Get Coach Profile
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
| Get Coach Teams
|--------------------------------------------------------------------------
*/
$teamsStmt = $db->prepare("
    SELECT
        t.team_id,
        t.team_name,
        t.team_category,
        t.roster_limit,
        t.team_status,
        s.sport_id,
        s.sport_name,

        (
            SELECT COUNT(*)
            FROM team_players tp
            WHERE tp.team_id = t.team_id
              AND tp.membership_status = 'ACTIVE'
              AND tp.left_at IS NULL
        ) AS player_count

    FROM teams t

    INNER JOIN sports s
        ON s.sport_id = t.sport_id

    WHERE t.coach_id = :coach_id

    ORDER BY
        s.sport_name ASC,
        t.team_name ASC
");

$teamsStmt->execute([
    ':coach_id' => $coachId
]);

$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Calculate Summary
|--------------------------------------------------------------------------
*/
$totalTeams = count($teams);
$totalPlayers = 0;
$totalRosterCapacity = 0;

foreach ($teams as $team) {
    $totalPlayers += (int) $team['player_count'];
    $totalRosterCapacity += max(0, (int) $team['roster_limit']);
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/
function coachTeamStatusClass(string $status): string
{
    return match (strtoupper($status)) {
        'ACTIVE' => 'ct-status-active',
        'INACTIVE' => 'ct-status-inactive',
        'PENDING' => 'ct-status-pending',
        'COMPLETED' => 'ct-status-completed',
        default => 'ct-status-neutral',
    };
}

function coachTeamRosterPercentage(int $players, int $limit): int
{
    if ($limit <= 0) {
        return 0;
    }

    return min(100, (int) round(($players / $limit) * 100));
}

require_once __DIR__ . '/../includes/header.php';

?>

<style>
/* =========================================================
   COACH TEAMS PAGE
   Scoped styles to avoid affecting other pages.
   ========================================================= */

.coach-teams-page {
    --ct-primary: #2457d6;
    --ct-primary-light: #edf3ff;
    --ct-text: #172033;
    --ct-muted: #667085;
    --ct-border: #e5eaf2;
    --ct-surface: #ffffff;

    display: flex;
    flex-direction: column;
    gap: 24px;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding-bottom: 32px;
    color: var(--ct-text);
}

.coach-teams-page *,
.coach-teams-page *::before,
.coach-teams-page *::after {
    box-sizing: border-box;
}

/* Page heading */

.coach-teams-page .ct-page-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
}

.coach-teams-page .ct-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: var(--ct-primary);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.1px;
    text-transform: uppercase;
}

.coach-teams-page .ct-eyebrow::before {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
    content: "";
}

.coach-teams-page .ct-page-heading h1 {
    margin: 0;
    color: var(--ct-text);
    font-size: clamp(25px, 3vw, 32px);
    font-weight: 800;
    line-height: 1.25;
}

.coach-teams-page .ct-page-heading p {
    margin: 9px 0 0;
    color: var(--ct-muted);
    font-size: 14px;
    line-height: 1.6;
}

.coach-teams-page .ct-coach-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    padding: 10px 14px;
    border: 1px solid #dce6fb;
    border-radius: 12px;
    background: #f5f8ff;
    color: var(--ct-primary);
    font-size: 12px;
    font-weight: 750;
}

/* Summary cards */

.coach-teams-page .ct-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.coach-teams-page .ct-summary-card {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 0;
    padding: 22px;
    border: 1px solid var(--ct-border);
    border-radius: 17px;
    background: var(--ct-surface);
    box-shadow: 0 5px 20px rgba(25, 45, 85, 0.035);
    transition:
        transform 180ms ease,
        box-shadow 180ms ease;
}

.coach-teams-page .ct-summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 26px rgba(25, 45, 85, 0.07);
}

.coach-teams-page .ct-summary-icon {
    display: flex;
    flex: 0 0 48px;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: var(--ct-primary-light);
    color: var(--ct-primary);
    font-size: 23px;
}

.coach-teams-page .ct-summary-content {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 0;
}

.coach-teams-page .ct-summary-label {
    color: var(--ct-muted);
    font-size: 12px;
    font-weight: 650;
}

.coach-teams-page .ct-summary-value {
    color: var(--ct-text);
    font-size: 27px;
    font-weight: 850;
    line-height: 1.1;
}

.coach-teams-page .ct-summary-note {
    color: var(--ct-muted);
    font-size: 11px;
    line-height: 1.4;
}

/* Section heading */

.coach-teams-page .ct-section {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.coach-teams-page .ct-section-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.coach-teams-page .ct-section-heading h2 {
    margin: 0;
    color: var(--ct-text);
    font-size: 19px;
    font-weight: 800;
}

.coach-teams-page .ct-section-heading p {
    margin: 6px 0 0;
    color: var(--ct-muted);
    font-size: 13px;
    line-height: 1.5;
}

.coach-teams-page .ct-team-count {
    flex-shrink: 0;
    padding: 7px 11px;
    border: 1px solid #dce6fb;
    border-radius: 999px;
    background: #f5f8ff;
    color: var(--ct-primary);
    font-size: 12px;
    font-weight: 800;
}

/* Team card grid */

.coach-teams-page .ct-team-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.coach-teams-page .ct-team-card {
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 22px;
    border: 1px solid var(--ct-border);
    border-radius: 17px;
    background: var(--ct-surface);
    box-shadow: 0 5px 20px rgba(25, 45, 85, 0.035);
    transition:
        transform 180ms ease,
        box-shadow 180ms ease,
        border-color 180ms ease;
}

.coach-teams-page .ct-team-card:hover {
    transform: translateY(-2px);
    border-color: #d4def3;
    box-shadow: 0 10px 26px rgba(25, 45, 85, 0.07);
}

.coach-teams-page .ct-team-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
}

.coach-teams-page .ct-team-identity {
    display: flex;
    align-items: flex-start;
    gap: 13px;
    min-width: 0;
}

.coach-teams-page .ct-team-icon {
    display: flex;
    flex: 0 0 46px;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: var(--ct-primary-light);
    color: var(--ct-primary);
    font-size: 22px;
}

.coach-teams-page .ct-team-title {
    min-width: 0;
}

.coach-teams-page .ct-team-title h3 {
    margin: 1px 0 5px;
    color: var(--ct-text);
    font-size: 17px;
    font-weight: 800;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.coach-teams-page .ct-team-sport {
    color: var(--ct-muted);
    font-size: 12px;
    line-height: 1.5;
}

.coach-teams-page .ct-team-status {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    padding: 6px 9px;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.25px;
    line-height: 1.4;
    text-transform: uppercase;
}

.coach-teams-page .ct-status-active {
    border-color: #bdebd2;
    background: #eafaf1;
    color: #147a46;
}

.coach-teams-page .ct-status-inactive {
    border-color: #dfe4ec;
    background: #f3f5f8;
    color: #475467;
}

.coach-teams-page .ct-status-pending {
    border-color: #f6df9d;
    background: #fff8e5;
    color: #946200;
}

.coach-teams-page .ct-status-completed {
    border-color: #d7ccff;
    background: #f3efff;
    color: #6941c6;
}

.coach-teams-page .ct-status-neutral {
    border-color: #dfe4ec;
    background: #f3f5f8;
    color: #475467;
}

/* Team category */

.coach-teams-page .ct-team-category {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: fit-content;
    max-width: 100%;
    margin-top: 17px;
    padding: 6px 10px;
    border-radius: 8px;
    background: #f5f7fb;
    color: #475467;
    font-size: 11px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

/* Roster information */

.coach-teams-page .ct-roster {
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #edf0f5;
}

.coach-teams-page .ct-roster-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}

.coach-teams-page .ct-roster-heading span:first-child {
    color: var(--ct-muted);
    font-size: 12px;
    font-weight: 650;
}

.coach-teams-page .ct-roster-heading strong {
    color: var(--ct-text);
    font-size: 13px;
    font-weight: 800;
}

.coach-teams-page .ct-roster-track {
    width: 100%;
    height: 8px;
    overflow: hidden;
    border-radius: 999px;
    background: #edf1f7;
}

.coach-teams-page .ct-roster-progress {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #2457d6, #6c8ff0);
    transition: width 250ms ease;
}

.coach-teams-page .ct-roster-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 9px;
    color: var(--ct-muted);
    font-size: 11px;
}

.coach-teams-page .ct-roster-footer strong {
    color: var(--ct-text);
    font-weight: 750;
}

/* Empty state */

.coach-teams-page .ct-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 260px;
    padding: 32px 20px;
    border: 1px dashed #d8e0ec;
    border-radius: 18px;
    background: #fbfcfe;
    text-align: center;
}

.coach-teams-page .ct-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    margin-bottom: 16px;
    border-radius: 20px;
    background: var(--ct-primary-light);
    font-size: 29px;
}

.coach-teams-page .ct-empty-state h3 {
    margin: 0;
    color: var(--ct-text);
    font-size: 18px;
    font-weight: 800;
}

.coach-teams-page .ct-empty-state p {
    max-width: 430px;
    margin: 8px 0 0;
    color: var(--ct-muted);
    font-size: 13px;
    line-height: 1.7;
}

/* Responsive */

@media (max-width: 900px) {
    .coach-teams-page .ct-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .coach-teams-page .ct-summary-card:last-child {
        grid-column: 1 / -1;
    }

    .coach-teams-page .ct-team-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .coach-teams-page {
        gap: 18px;
    }

    .coach-teams-page .ct-page-heading {
        flex-direction: column;
        gap: 12px;
    }

    .coach-teams-page .ct-summary-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .coach-teams-page .ct-summary-card:last-child {
        grid-column: auto;
    }

    .coach-teams-page .ct-summary-card {
        padding: 17px;
    }

    .coach-teams-page .ct-team-card {
        padding: 18px;
    }

    .coach-teams-page .ct-team-card-header {
        flex-wrap: wrap;
    }

    .coach-teams-page .ct-section-heading {
        align-items: center;
    }
}

@media (max-width: 400px) {
    .coach-teams-page .ct-team-identity {
        gap: 10px;
    }

    .coach-teams-page .ct-team-icon {
        flex-basis: 40px;
        width: 40px;
        height: 40px;
        border-radius: 12px;
        font-size: 19px;
    }

    .coach-teams-page .ct-team-title h3 {
        font-size: 15px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .coach-teams-page .ct-summary-card,
    .coach-teams-page .ct-team-card,
    .coach-teams-page .ct-roster-progress {
        transition: none;
    }
}
</style>

<div class="dashboard-page coach-teams-page">

    <!-- Page heading -->
    <header class="ct-page-heading">
        <div>
            <div class="ct-eyebrow">Coach Portal</div>
            <h1>My Teams 👥</h1>
            <p>
                View your assigned teams and monitor their player rosters.
            </p>
        </div>

        <div class="ct-coach-label">
            🧑‍🏫
            <?= htmlspecialchars(
                (string) $coach['full_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    </header>

    <!-- Summary -->
    <section class="ct-summary-grid" aria-label="Team summary">

        <article class="ct-summary-card">
            <div class="ct-summary-icon" aria-hidden="true">👥</div>

            <div class="ct-summary-content">
                <span class="ct-summary-label">Assigned Teams</span>
                <strong class="ct-summary-value">
                    <?= $totalTeams ?>
                </strong>
                <span class="ct-summary-note">
                    Teams assigned to you
                </span>
            </div>
        </article>

        <article class="ct-summary-card">
            <div class="ct-summary-icon" aria-hidden="true">🏃</div>

            <div class="ct-summary-content">
                <span class="ct-summary-label">Total Players</span>
                <strong class="ct-summary-value">
                    <?= $totalPlayers ?>
                </strong>
                <span class="ct-summary-note">
                    Active team memberships
                </span>
            </div>
        </article>

        <article class="ct-summary-card">
            <div class="ct-summary-icon" aria-hidden="true">📋</div>

            <div class="ct-summary-content">
                <span class="ct-summary-label">Roster Capacity</span>
                <strong class="ct-summary-value">
                    <?= $totalRosterCapacity ?>
                </strong>
                <span class="ct-summary-note">
                    Combined roster limits
                </span>
            </div>
        </article>

    </section>

    <!-- Assigned teams -->
    <section class="ct-section">

        <div class="ct-section-heading">
            <div>
                <h2>Assigned Teams</h2>
                <p>
                    Teams currently assigned to you.
                </p>
            </div>

            <span class="ct-team-count">
                <?= $totalTeams ?>
                <?= $totalTeams === 1 ? 'Team' : 'Teams' ?>
            </span>
        </div>

        <?php if (empty($teams)): ?>

            <div class="ct-empty-state">
                <div class="ct-empty-icon" aria-hidden="true">👥</div>

                <h3>No Teams Assigned</h3>

                <p>
                    You currently do not have any teams assigned to you.
                    Contact your Sports Coordinator or administrator if
                    you think a team should be assigned to your account.
                </p>
            </div>

        <?php else: ?>

            <div class="ct-team-grid">

                <?php foreach ($teams as $team): ?>

                    <?php
                    $status = strtoupper((string) $team['team_status']);
                    $playerCount = (int) $team['player_count'];
                    $rosterLimit = max(0, (int) $team['roster_limit']);
                    $rosterPercentage = coachTeamRosterPercentage(
                        $playerCount,
                        $rosterLimit
                    );

                    $availableSpots = max(0, $rosterLimit - $playerCount);
                    ?>

                    <article class="ct-team-card">

                        <div class="ct-team-card-header">

                            <div class="ct-team-identity">

                                <div class="ct-team-icon" aria-hidden="true">
                                    👥
                                </div>

                                <div class="ct-team-title">

                                    <h3>
                                        <?= htmlspecialchars(
                                            (string) $team['team_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </h3>

                                    <div class="ct-team-sport">
                                        <?= htmlspecialchars(
                                            (string) $team['sport_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                </div>

                            </div>

                            <span class="ct-team-status <?= htmlspecialchars(
                                coachTeamStatusClass($status),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">
                                <?= htmlspecialchars(
                                    $status,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </div>

                        <div class="ct-team-category">
                            🏷️
                            Category:
                            <?= htmlspecialchars(
                            ($team['team_category'] === null || trim((string) $team['team_category']) === '')
                                ? 'Not specified'
                                : (string) $team['team_category'],
                            ENT_QUOTES,
                            'UTF-8'
                            ) ?>
                        </div>

                        <div class="ct-roster">

                            <div class="ct-roster-heading">
                                <span>Team Roster</span>

                                <strong>
                                    <?= $playerCount ?>
                                    /
                                    <?= $rosterLimit ?>
                                    players
                                </strong>
                            </div>

                            <div
                                class="ct-roster-track"
                                role="progressbar"
                                aria-label="Team roster capacity"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-valuenow="<?= $rosterPercentage ?>"
                            >
                                <div
                                    class="ct-roster-progress"
                                    style="width: <?= $rosterPercentage ?>%;"
                                ></div>
                            </div>

                            <div class="ct-roster-footer">
                                <span>
                                    <?= $rosterPercentage ?>% filled
                                </span>

                                <strong>
                                    <?php if ($rosterLimit <= 0): ?>
                                        Limit not set
                                    <?php elseif ($availableSpots === 0): ?>
                                        Roster full
                                    <?php else: ?>
                                        <?= $availableSpots ?>
                                        <?= $availableSpots === 1 ? 'spot' : 'spots' ?>
                                        available
                                    <?php endif; ?>
                                </strong>
                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>