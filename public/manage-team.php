```php
<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

/*
|--------------------------------------------------------------------------
| Get Team ID
|--------------------------------------------------------------------------
*/

$teamId = filter_var(
    $_GET['team_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $teamId === false ||
    $teamId === null ||
    $teamId <= 0
) {
    http_response_code(400);
    exit('Invalid team ID.');
}


/*
|--------------------------------------------------------------------------
| Get Team Details
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Get Team Players
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Count Active Players
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Get Eligible Players
|--------------------------------------------------------------------------
|
| A player must:
| 1. Have an active player profile
| 2. Have an approved account
| 3. Be enrolled in this team's sport
| 4. Not already belong to another active team
|    in the same sport
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';


/*
|--------------------------------------------------------------------------
| Roster Percentage
|--------------------------------------------------------------------------
*/

$rosterLimit = (int) $team['roster_limit'];

if ($rosterLimit > 0) {
    $rosterPercentage = min(
        100,
        ($activePlayerCount / $rosterLimit) * 100
    );
} else {
    $rosterPercentage = 0;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

$teamStatus = strtoupper(
    (string) $team['team_status']
);

if ($teamStatus === 'ACTIVE') {
    $statusClass = 'manage-status-active';
} elseif (
    in_array(
        $teamStatus,
        ['INACTIVE', 'SUSPENDED'],
        true
    )
) {
    $statusClass = 'manage-status-inactive';
} else {
    $statusClass = 'manage-status-other';
}


/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

$pageTitle = 'Manage Team';

require_once __DIR__ . '/../includes/header.php';

?>

<style>

/* =========================================================
   MANAGE TEAM PAGE
   ========================================================= */

.manage-team-page {
    max-width: 1200px;
    margin: 0 auto;
}


/* =========================================================
   BACK LINK
   ========================================================= */

.manage-team-back {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 20px;
    color: #2563eb;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}

.manage-team-back:hover {
    color: #1d4ed8;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.manage-team-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}

.manage-team-header-content {
    flex: 1;
}

.manage-team-label {
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

.manage-team-header h1 {
    margin: 0 0 8px;
    color: #111827;
    font-size: 30px;
    line-height: 1.2;
}

.manage-team-header p {
    margin: 0;
    color: #6b7280;
    font-size: 15px;
}

.manage-team-header-icon {
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
   MESSAGES
   ========================================================= */

.manage-message {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding: 14px 16px;
    border-radius: 12px;
    font-size: 14px;
}

.manage-message-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 50%;
}

.manage-message-success {
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #166534;
}

.manage-message-success .manage-message-icon {
    background: #dcfce7;
}

.manage-message-error {
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #991b1b;
}

.manage-message-error .manage-message-icon {
    background: #fee2e2;
}


/* =========================================================
   TEAM OVERVIEW CARD
   ========================================================= */

.team-overview-card {
    overflow: hidden;
    margin-bottom: 22px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

.team-overview-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    padding: 24px;
    border-bottom: 1px solid #e5e7eb;
}

.team-overview-title {
    display: flex;
    align-items: center;
    gap: 14px;
}

.team-overview-icon {
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 14px;
    background: #eff6ff;
    font-size: 25px;
}

.team-overview-title h2 {
    margin: 0 0 5px;
    color: #111827;
    font-size: 22px;
}

.team-overview-title p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
}

.edit-team-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 9px 15px;
    border: 1px solid #dbeafe;
    border-radius: 9px;
    background: #eff6ff;
    color: #2563eb;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    transition: 0.2s ease;
}

.edit-team-button:hover {
    background: #dbeafe;
}


/* =========================================================
   TEAM DETAILS
   ========================================================= */

.team-details-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.team-detail {
    padding: 20px 24px;
    border-right: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
}

.team-detail:nth-child(3n) {
    border-right: none;
}

.team-detail-label {
    margin-bottom: 6px;
    color: #94a3b8;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.team-detail-value {
    color: #111827;
    font-size: 14px;
    font-weight: 600;
}

.team-detail-value.muted {
    color: #9ca3af;
    font-weight: 500;
}


/* =========================================================
   ROSTER DETAIL
   ========================================================= */

.team-roster-detail {
    grid-column: span 3;
    padding: 20px 24px 24px;
}

.team-roster-heading {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 10px;
}

.team-roster-heading span:first-child {
    color: #475569;
    font-size: 13px;
    font-weight: 600;
}

.team-roster-count {
    color: #111827;
    font-size: 13px;
    font-weight: 700;
}

.team-roster-bar {
    width: 100%;
    height: 8px;
    overflow: hidden;
    border-radius: 999px;
    background: #e5e7eb;
}

.team-roster-fill {
    height: 100%;
    border-radius: 999px;
    background: #2563eb;
}


/* =========================================================
   STATUS
   ========================================================= */

.manage-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.manage-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.manage-status-active {
    background: #dcfce7;
    color: #15803d;
}

.manage-status-inactive {
    background: #fef2f2;
    color: #b91c1c;
}

.manage-status-other {
    background: #f1f5f9;
    color: #475569;
}


/* =========================================================
   TWO COLUMN SECTION
   ========================================================= */

.manage-team-grid {
    display: grid;
    grid-template-columns: minmax(300px, 0.75fr) minmax(0, 1.25fr);
    gap: 22px;
    margin-bottom: 22px;
}


/* =========================================================
   CARD
   ========================================================= */

.manage-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

.manage-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.manage-card-header-icon {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 11px;
    background: #eff6ff;
    font-size: 19px;
}

.manage-card-header h2 {
    margin: 0 0 4px;
    color: #111827;
    font-size: 18px;
}

.manage-card-header p {
    margin: 0;
    color: #6b7280;
    font-size: 12px;
}


/* =========================================================
   ADD PLAYER
   ========================================================= */

.add-player-body {
    padding: 20px;
}

.add-player-body label {
    display: block;
    margin-bottom: 8px;
    color: #374151;
    font-size: 13px;
    font-weight: 600;
}

.add-player-body select {
    width: 100%;
    min-height: 45px;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    background: #ffffff;
    color: #111827;
    font-size: 14px;
    outline: none;
    box-sizing: border-box;
}

.add-player-body select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.add-player-button {
    width: 100%;
    min-height: 44px;
    margin-top: 14px;
    padding: 10px 16px;
    border: 1px solid #2563eb;
    border-radius: 9px;
    background: #2563eb;
    color: #ffffff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s ease;
}

.add-player-button:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
}


/* =========================================================
   ELIGIBILITY INFO
   ========================================================= */

.eligibility-box {
    margin-top: 16px;
    padding: 14px;
    border: 1px solid #dbeafe;
    border-radius: 10px;
    background: #eff6ff;
}

.eligibility-box-title {
    margin-bottom: 8px;
    color: #1e40af;
    font-size: 12px;
    font-weight: 700;
}

.eligibility-box ul {
    margin: 0;
    padding-left: 18px;
    color: #1e40af;
    font-size: 12px;
    line-height: 1.8;
}


/* =========================================================
   NO ELIGIBLE PLAYERS
   ========================================================= */

.no-players-box {
    padding: 20px;
}

.no-players-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    border-radius: 13px;
    background: #f8fafc;
    font-size: 21px;
}

.no-players-box h3 {
    margin: 0 0 7px;
    color: #111827;
    font-size: 15px;
}

.no-players-box p {
    margin: 0 0 12px;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.5;
}

.no-players-box ul {
    margin: 0;
    padding-left: 18px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.8;
}


/* =========================================================
   TEAM FULL
   ========================================================= */

.team-full-box {
    margin: 20px;
    padding: 15px;
    border: 1px solid #fecaca;
    border-radius: 10px;
    background: #fef2f2;
    color: #991b1b;
    font-size: 13px;
}


/* =========================================================
   TEAM PLAYERS CARD
   ========================================================= */

.team-players-card {
    overflow: hidden;
    margin-bottom: 25px;
}

.team-players-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 20px 22px;
    border-bottom: 1px solid #e5e7eb;
}

.team-players-header h2 {
    margin: 0 0 4px;
    color: #111827;
    font-size: 19px;
}

.team-players-header p {
    margin: 0;
    color: #6b7280;
    font-size: 12px;
}

.team-players-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 32px;
    padding: 0 11px;
    border-radius: 999px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 12px;
    font-weight: 700;
}


/* =========================================================
   PLAYERS TABLE
   ========================================================= */

.team-players-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.team-players-table {
    width: 100%;
    min-width: 1000px;
    border-collapse: collapse;
}

.team-players-table th {
    padding: 13px 15px;
    border-bottom: 1px solid #e5e7eb;
    background: #f8fafc;
    color: #475569;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.team-players-table td {
    padding: 15px;
    border-bottom: 1px solid #f1f5f9;
    color: #374151;
    font-size: 13px;
    vertical-align: middle;
}

.team-players-table tbody tr:last-child td {
    border-bottom: none;
}

.team-players-table tbody tr:hover {
    background: #f8fafc;
}

.player-student-id {
    color: #64748b;
    font-weight: 600;
}

.player-name {
    color: #111827;
    font-weight: 700;
}

.player-email {
    color: #64748b;
}

.player-status {
    display: inline-flex;
    align-items: center;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.player-status-active {
    background: #dcfce7;
    color: #15803d;
}

.player-status-inactive {
    background: #f1f5f9;
    color: #64748b;
}


/* =========================================================
   REMOVE BUTTON
   ========================================================= */

.remove-player-form {
    margin: 0;
}

.remove-player-button {
    min-height: 34px;
    padding: 7px 11px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.2s ease;
}

.remove-player-button:hover {
    background: #fee2e2;
    border-color: #fca5a5;
}


/* =========================================================
   EMPTY TEAM
   ========================================================= */

.empty-team-players {
    padding: 50px 25px;
    text-align: center;
}

.empty-team-players-icon {
    width: 58px;
    height: 58px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 14px;
    border-radius: 16px;
    background: #eff6ff;
    font-size: 25px;
}

.empty-team-players h3 {
    margin: 0 0 7px;
    color: #111827;
    font-size: 17px;
}

.empty-team-players p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 900px) {

    .manage-team-grid {
        grid-template-columns: 1fr;
    }

    .team-details-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .team-detail:nth-child(3n) {
        border-right: 1px solid #e5e7eb;
    }

    .team-detail:nth-child(2n) {
        border-right: none;
    }

    .team-roster-detail {
        grid-column: span 2;
    }

}


@media (max-width: 650px) {

    .manage-team-header {
        align-items: flex-start;
    }

    .manage-team-header h1 {
        font-size: 25px;
    }

    .manage-team-header-icon {
        width: 52px;
        height: 52px;
        font-size: 25px;
    }

    .team-overview-top {
        flex-direction: column;
    }

    .edit-team-button {
        width: 100%;
    }

    .team-details-grid {
        grid-template-columns: 1fr;
    }

    .team-detail,
    .team-detail:nth-child(2n),
    .team-detail:nth-child(3n) {
        border-right: none;
    }

    .team-roster-detail {
        grid-column: auto;
    }

    .team-players-header {
        align-items: flex-start;
    }

}

</style>


<div class="dashboard-page">

    <div class="manage-team-page">


        <!-- =====================================================
             BACK LINK
             ===================================================== -->

        <a
            href="admin-teams.php"
            class="manage-team-back"
        >
            ← Back to Team Management
        </a>


        <!-- =====================================================
             PAGE HEADER
             ===================================================== -->

        <section class="manage-team-header">

            <div class="manage-team-header-content">

                <div class="manage-team-label">
                    TEAM MANAGEMENT
                </div>

                <h1>
                    Manage Team
                </h1>

                <p>
                    Manage team information, players, roster and
                    team membership.
                </p>

            </div>

            <div class="manage-team-header-icon">
                👥
            </div>

        </section>


        <!-- =====================================================
             SUCCESS / ERROR MESSAGES
             ===================================================== -->

        <?php if ($message === 'player_added'): ?>

            <div class="manage-message manage-message-success">

                <div class="manage-message-icon">
                    ✓
                </div>

                <strong>
                    Player added to the team successfully.
                </strong>

            </div>

        <?php elseif ($message === 'player_removed'): ?>

            <div class="manage-message manage-message-success">

                <div class="manage-message-icon">
                    ✓
                </div>

                <strong>
                    Player removed from the team successfully.
                </strong>

            </div>

        <?php elseif ($message === 'updated'): ?>

            <div class="manage-message manage-message-success">

                <div class="manage-message-icon">
                    ✓
                </div>

                <strong>
                    Team updated successfully.
                </strong>

            </div>

        <?php endif; ?>


        <?php if ($error === 'invalid'): ?>

            <div class="manage-message manage-message-error">

                <div class="manage-message-icon">
                    !
                </div>

                <strong>
                    Invalid request.
                </strong>

            </div>

        <?php elseif ($error === 'not_enrolled'): ?>

            <div class="manage-message manage-message-error">

                <div class="manage-message-icon">
                    !
                </div>

                <strong>
                    Player is not enrolled in this sport.
                </strong>

            </div>

        <?php elseif ($error === 'already_in_team'): ?>

            <div class="manage-message manage-message-error">

                <div class="manage-message-icon">
                    !
                </div>

                <strong>
                    Player already belongs to another team in this sport.
                </strong>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             TEAM OVERVIEW
             ===================================================== -->

        <section class="team-overview-card">

            <div class="team-overview-top">

                <div class="team-overview-title">

                    <div class="team-overview-icon">
                        🏆
                    </div>

                    <div>

                        <h2>

                            <?= htmlspecialchars(
                                (string) $team['team_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </h2>

                        <p>
                            Team ID #<?= (int) $team['team_id'] ?>
                            · <?= htmlspecialchars(
                                (string) $team['sport_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                    </div>

                </div>


                <a
                    href="edit-team.php?team_id=<?= (int) $team['team_id'] ?>"
                    class="edit-team-button"
                >
                    ✏️ Edit Team
                </a>

            </div>


            <div class="team-details-grid">


                <!-- SPORT -->

                <div class="team-detail">

                    <div class="team-detail-label">
                        Sport
                    </div>

                    <div class="team-detail-value">

                        <?= htmlspecialchars(
                            (string) $team['sport_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


                <!-- CATEGORY -->

                <div class="team-detail">

                    <div class="team-detail-label">
                        Category
                    </div>

                    <div class="team-detail-value">

                        <?= htmlspecialchars(
                            (string) $team['team_category'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


                <!-- COACH -->

                <div class="team-detail">

                    <div class="team-detail-label">
                        Coach
                    </div>

                    <div class="team-detail-value">

                        <?php if (!empty($team['coach_name'])): ?>

                            <?= htmlspecialchars(
                                (string) $team['coach_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        <?php else: ?>

                            <span class="muted">
                                Not assigned
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- STATUS -->

                <div class="team-detail">

                    <div class="team-detail-label">
                        Status
                    </div>

                    <div class="team-detail-value">

                        <span
                            class="manage-status <?= $statusClass ?>"
                        >

                            <span class="manage-status-dot"></span>

                            <?= htmlspecialchars(
                                $teamStatus,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>

                    </div>

                </div>


                <!-- TEAM ID -->

                <div class="team-detail">

                    <div class="team-detail-label">
                        Team ID
                    </div>

                    <div class="team-detail-value">
                        #<?= (int) $team['team_id'] ?>
                    </div>

                </div>


                <!-- ACTIVE PLAYERS -->

                <div class="team-detail">

                    <div class="team-detail-label">
                        Active Players
                    </div>

                    <div class="team-detail-value">

                        <?= $activePlayerCount ?>

                        /

                        <?= $rosterLimit ?>

                    </div>

                </div>


                <!-- ROSTER -->

                <div class="team-roster-detail">

                    <div class="team-roster-heading">

                        <span>
                            Roster Capacity
                        </span>

                        <span class="team-roster-count">

                            <?= $activePlayerCount ?>

                            /

                            <?= $rosterLimit ?>

                        </span>

                    </div>

                    <div class="team-roster-bar">

                        <div
                            class="team-roster-fill"
                            style="width: <?= number_format($rosterPercentage, 2, '.', '') ?>%;"
                        ></div>

                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             ADD PLAYER + INFORMATION
             ===================================================== -->

        <div class="manage-team-grid">


            <!-- =================================================
                 ADD PLAYER CARD
                 ================================================= -->

            <section class="manage-card">

                <div class="manage-card-header">

                    <div class="manage-card-header-icon">
                        ➕
                    </div>

                    <div>

                        <h2>
                            Add Player
                        </h2>

                        <p>
                            Add an eligible player to this team.
                        </p>

                    </div>

                </div>


                <?php if ($activePlayerCount >= $rosterLimit): ?>

                    <div class="team-full-box">

                        <strong>
                            This team is full.
                        </strong>

                        <br>

                        No more players can be added until
                        a player is removed.

                    </div>


                <?php elseif (empty($eligiblePlayers)): ?>

                    <div class="no-players-box">

                        <div class="no-players-icon">
                            👤
                        </div>

                        <h3>
                            No eligible players available
                        </h3>

                        <p>
                            There are currently no players who meet
                            all requirements for this team.
                        </p>

                        <div class="eligibility-box">

                            <div class="eligibility-box-title">
                                A player must:
                            </div>

                            <ul>

                                <li>
                                    Have an active player profile
                                </li>

                                <li>
                                    Have an approved account
                                </li>

                                <li>
                                    Be enrolled in
                                    <?= htmlspecialchars(
                                        (string) $team['sport_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </li>

                                <li>
                                    Not already belong to another
                                    team in this sport
                                </li>

                            </ul>

                        </div>

                    </div>


                <?php else: ?>

                    <div class="add-player-body">

                        <form
                            action="add-team-player.php"
                            method="POST"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    csrfToken(),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="team_id"
                                value="<?= (int) $team['team_id'] ?>"
                            >


                            <label for="player_id">
                                Select Player
                            </label>

                            <select
                                name="player_id"
                                id="player_id"
                                required
                            >

                                <option value="">
                                    -- Select Player --
                                </option>

                                <?php foreach ($eligiblePlayers as $player): ?>

                                    <option
                                        value="<?= (int) $player['player_id'] ?>"
                                    >

                                        <?= htmlspecialchars(
                                            (string) $player['full_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                        -

                                        <?= htmlspecialchars(
                                            (string) $player['student_id'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>


                            <button
                                type="submit"
                                class="add-player-button"
                            >
                                ＋ Add Player
                            </button>

                        </form>


                        <div class="eligibility-box">

                            <div class="eligibility-box-title">
                                Player eligibility
                            </div>

                            <ul>

                                <li>
                                    Active player profile
                                </li>

                                <li>
                                    Approved account
                                </li>

                                <li>
                                    Enrolled in
                                    <?= htmlspecialchars(
                                        (string) $team['sport_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </li>

                                <li>
                                    No active team in this sport
                                </li>

                            </ul>

                        </div>

                    </div>

                <?php endif; ?>

            </section>


            <!-- =================================================
                 TEAM RULES CARD
                 ================================================= -->

            <section class="manage-card">

                <div class="manage-card-header">

                    <div class="manage-card-header-icon">
                        ℹ️
                    </div>

                    <div>

                        <h2>
                            Team Information
                        </h2>

                        <p>
                            Current team membership rules.
                        </p>

                    </div>

                </div>


                <div class="add-player-body">

                    <div class="eligibility-box">

                        <div class="eligibility-box-title">
                            Membership rules
                        </div>

                        <ul>

                            <li>
                                Players must be approved before joining.
                            </li>

                            <li>
                                Players must be enrolled in this sport.
                            </li>

                            <li>
                                A player can have only one active team
                                in the same sport.
                            </li>

                            <li>
                                The team cannot exceed its roster limit.
                            </li>

                            <li>
                                Removing a player ends their active
                                membership in this team.
                            </li>

                        </ul>

                    </div>

                </div>

            </section>

        </div>


        <!-- =====================================================
             TEAM PLAYERS
             ===================================================== -->

        <section class="manage-card team-players-card">

            <div class="team-players-header">

                <div>

                    <h2>
                        Team Players
                    </h2>

                    <p>
                        Players currently associated with this team.
                    </p>

                </div>

                <div class="team-players-count">
                    <?= count($players) ?>
                </div>

            </div>


            <?php if (empty($players)): ?>

                <div class="empty-team-players">

                    <div class="empty-team-players-icon">
                        👥
                    </div>

                    <h3>
                        No players added yet
                    </h3>

                    <p>
                        Add an eligible player above to build this team's roster.
                    </p>

                </div>


            <?php else: ?>

                <div class="team-players-table-wrapper">

                    <table class="team-players-table">

                        <thead>

                            <tr>

                                <th>
                                    ID
                                </th>

                                <th>
                                    Student ID
                                </th>

                                <th>
                                    Player
                                </th>

                                <th>
                                    Email
                                </th>

                                <th>
                                    Department
                                </th>

                                <th>
                                    Course
                                </th>

                                <th>
                                    Academic Year
                                </th>

                                <th>
                                    Joined
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

                            <?php foreach ($players as $player): ?>

                                <?php

                                $playerMembershipStatus =
                                    strtoupper(
                                        (string) $player['membership_status']
                                    );

                                $playerStatusClass =
                                    $playerMembershipStatus === 'ACTIVE'
                                        ? 'player-status-active'
                                        : 'player-status-inactive';

                                ?>

                                <tr>


                                    <!-- ID -->

                                    <td>

                                        #
                                        <?= (int) $player['team_player_id'] ?>

                                    </td>


                                    <!-- STUDENT ID -->

                                    <td>

                                        <span class="player-student-id">

                                            <?= htmlspecialchars(
                                                (string) $player['student_id'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- NAME -->

                                    <td>

                                        <span class="player-name">

                                            <?= htmlspecialchars(
                                                (string) $player['full_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- EMAIL -->

                                    <td>

                                        <span class="player-email">

                                            <?= htmlspecialchars(
                                                (string) $player['email'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- DEPARTMENT -->

                                    <td>

                                        <?= htmlspecialchars(
                                            (string) $player['department'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <!-- COURSE -->

                                    <td>

                                        <?= htmlspecialchars(
                                            (string) (
                                                $player['course'] ?? ''
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <!-- ACADEMIC YEAR -->

                                    <td>

                                        <?= htmlspecialchars(
                                            (string) $player['academic_year'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <!-- JOINED -->

                                    <td>

                                        <?= htmlspecialchars(
                                            (string) $player['joined_at'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="player-status <?= $playerStatusClass ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $playerMembershipStatus,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- ACTION -->

                                    <td>

                                        <?php if (
                                            $playerMembershipStatus === 'ACTIVE'
                                        ): ?>

                                            <form
                                                action="remove-team-player.php"
                                                method="POST"
                                                class="remove-player-form"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars(
                                                        csrfToken(),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
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
                                                    onclick="return confirm('Remove this player from the team?');"
                                                >
                                                    Remove
                                                </button>

                                            </form>

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    </div>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
```
