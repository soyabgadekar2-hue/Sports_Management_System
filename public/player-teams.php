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
| Get Player Teams
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
        tp.membership_status
    FROM team_players tp
    INNER JOIN teams t
        ON t.team_id = tp.team_id
    INNER JOIN sports s
        ON s.sport_id = t.sport_id
    WHERE tp.player_id = :player_id
      AND tp.membership_status = 'ACTIVE'
      AND tp.left_at IS NULL
    ORDER BY s.sport_name ASC, t.team_name ASC
");

$teamsStmt->execute([
    ':player_id' => $playerId
]);

$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Common Header
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">

    <!-- =========================================================
         PAGE HEADER
         ========================================================= -->
    <section class="welcome-card">

        <div class="welcome-content">

            <div class="welcome-label">
                MY TEAMS
            </div>

            <h1>
                My Teams
            </h1>

            <p>
                View the teams in which you are currently registered.
            </p>

        </div>

        <div class="welcome-icon">
            👥
        </div>

    </section>


    <!-- =========================================================
         PLAYER SUMMARY
         ========================================================= -->
    <section class="dashboard-stats">

        <div class="stat-card">

            <div class="stat-icon">
                👤
            </div>

            <div class="stat-content">

                <span>
                    Player
                </span>

                <strong>
                    <?= htmlspecialchars(
                        (string) $player['full_name']
                    ) ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                🪪
            </div>

            <div class="stat-content">

                <span>
                    Student ID
                </span>

                <strong>
                    <?= htmlspecialchars(
                        (string) $player['student_id']
                    ) ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                👥
            </div>

            <div class="stat-content">

                <span>
                    Active Teams
                </span>

                <strong>
                    <?= count($teams) ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- =========================================================
         TEAMS
         ========================================================= -->
    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    My Active Teams
                </h2>

                <p>
                    Teams in which you currently have active membership.
                </p>

            </div>

            <div class="card-header-icon">
                👥
            </div>

        </div>


        <?php if (!empty($teams)): ?>

            <div class="dashboard-list">

                <?php foreach ($teams as $team): ?>

                    <div class="dashboard-list-item">

                        <div class="list-item-icon">
                            👥
                        </div>


                        <div class="list-item-content">

                            <strong>
                                <?= htmlspecialchars(
                                    (string) $team['team_name']
                                ) ?>
                            </strong>

                            <span>
                                Sport:
                                <?= htmlspecialchars(
                                    (string) $team['sport_name']
                                ) ?>
                            </span>

                            <span>
                                Category:
                                <?= htmlspecialchars(
                                    (string) $team['team_category']
                                ) ?>
                            </span>

                            <span>
                                Team ID:
                                <?= htmlspecialchars(
                                    (string) $team['team_id']
                                ) ?>
                            </span>

                        </div>


                        <div class="team-status-area">

                            <span class="status-badge status-active">
                                <?= htmlspecialchars(
                                    (string) $team['membership_status']
                                ) ?>
                            </span>

                        </div>

                    </div>


                    <!-- Team Details -->

                    <div class="profile-details team-details">

                        <div class="profile-item">

                            <span>
                                Sport
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    (string) $team['sport_name']
                                ) ?>
                            </strong>

                        </div>


                        <div class="profile-item">

                            <span>
                                Category
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    (string) $team['team_category']
                                ) ?>
                            </strong>

                        </div>


                        <div class="profile-item">

                            <span>
                                Roster Limit
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    (string) $team['roster_limit']
                                ) ?>
                            </strong>

                        </div>


                        <div class="profile-item">

                            <span>
                                Team Status
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    (string) $team['team_status']
                                ) ?>
                            </strong>

                        </div>


                        <div class="profile-item">

                            <span>
                                Membership
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    (string) $team['membership_status']
                                ) ?>
                            </strong>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-dashboard">

                <strong>
                    No active team membership
                </strong>

                <p>
                    You are not currently a member of any active team.
                </p>

            </div>

        <?php endif; ?>

    </section>


    <!-- =========================================================
         INFORMATION
         ========================================================= -->
    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Team Membership
                </h2>

                <p>
                    About your current team membership.
                </p>

            </div>

            <div class="card-header-icon">
                ℹ️
            </div>

        </div>


        <div class="feature-placeholder">

            <p>
                Your team membership is managed by the Sports
                Coordinator or authorized administrator.
            </p>

            <p>
                You can participate in tournaments and matches
                through your active team membership.
            </p>

        </div>

    </section>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>