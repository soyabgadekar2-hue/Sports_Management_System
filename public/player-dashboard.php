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
        p.player_status,
        u.full_name,
        u.email,
        u.account_status
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
| Count Sports
|--------------------------------------------------------------------------
*/
$sportsStmt = $db->prepare("
    SELECT COUNT(*)
    FROM player_sports
    WHERE player_id = :player_id
");

$sportsStmt->execute([
    ':player_id' => $playerId
]);

$sportsCount = (int) $sportsStmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Count Active Teams
|--------------------------------------------------------------------------
*/
$teamsStmt = $db->prepare("
    SELECT COUNT(*)
    FROM team_players
    WHERE player_id = :player_id
      AND membership_status = 'ACTIVE'
      AND left_at IS NULL
");

$teamsStmt->execute([
    ':player_id' => $playerId
]);

$teamsCount = (int) $teamsStmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Count Tournaments
|--------------------------------------------------------------------------
*/
$tournamentsStmt = $db->prepare("
    SELECT COUNT(DISTINCT tt.tournament_id)
    FROM team_players tp
    INNER JOIN tournament_teams tt
        ON tt.team_id = tp.team_id
       AND tt.participation_status = 'ACTIVE'
    WHERE tp.player_id = :player_id
      AND tp.membership_status = 'ACTIVE'
      AND tp.left_at IS NULL
");

$tournamentsStmt->execute([
    ':player_id' => $playerId
]);

$tournamentsCount = (int) $tournamentsStmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Count Matches
|--------------------------------------------------------------------------
*/
$matchesStmt = $db->prepare("
    SELECT COUNT(DISTINCT pmp.match_id)
    FROM player_match_participation pmp
    WHERE pmp.player_id = :player_id
");

$matchesStmt->execute([
    ':player_id' => $playerId
]);

$matchesCount = (int) $matchesStmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Count Completed Matches
|--------------------------------------------------------------------------
*/
$completedMatchesStmt = $db->prepare("
    SELECT COUNT(DISTINCT pmp.match_id)
    FROM player_match_participation pmp
    INNER JOIN matches m
        ON m.match_id = pmp.match_id
    WHERE pmp.player_id = :player_id
      AND m.match_status = 'COMPLETED'
");

$completedMatchesStmt->execute([
    ':player_id' => $playerId
]);

$completedMatchesCount = (int) $completedMatchesStmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Status Class
|--------------------------------------------------------------------------
*/
function dashboardStatusClass(string $status): string
{
    return match ($status) {
        'APPROVED',
        'ACTIVE' => 'status-completed',

        'PENDING' => 'status-pending',

        'REJECTED',
        'SUSPENDED' => 'status-rejected',

        default => 'status-active',
    };
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">

    <!-- =========================================================
         WELCOME
         ========================================================= -->

    <section class="welcome-card">

        <div class="welcome-content">

            <div class="welcome-label">
                PLAYER DASHBOARD
            </div>

            <h1>
                Welcome,
                <?= htmlspecialchars(
                    (string) $player['full_name']
                ) ?>
                👋
            </h1>

            <p>
                Here's a quick overview of your sports activities.
            </p>

        </div>

        <div class="welcome-icon">
            🏆
        </div>

    </section>


    <!-- =========================================================
         SUMMARY CARDS
         ========================================================= -->

    <section class="dashboard-stats">

        <div class="stat-card">

            <div class="stat-icon">
                🏅
            </div>

            <div class="stat-content">

                <span>
                    My Sports
                </span>

                <strong>
                    <?= $sportsCount ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                👥
            </div>

            <div class="stat-content">

                <span>
                    My Teams
                </span>

                <strong>
                    <?= $teamsCount ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                🏆
            </div>

            <div class="stat-content">

                <span>
                    Tournaments
                </span>

                <strong>
                    <?= $tournamentsCount ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                ⚽
            </div>

            <div class="stat-content">

                <span>
                    Matches
                </span>

                <strong>
                    <?= $matchesCount ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                🏁
            </div>

            <div class="stat-content">

                <span>
                    Completed
                </span>

                <strong>
                    <?= $completedMatchesCount ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- =========================================================
         PLAYER OVERVIEW
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Player Overview
                </h2>

                <p>
                    Your current account information.
                </p>

            </div>

            <div class="card-header-icon">
                👤
            </div>

        </div>


        <div class="profile-details">

            <div class="profile-item">

                <span>
                    Full Name
                </span>

                <strong>
                    <?= htmlspecialchars(
                        (string) $player['full_name']
                    ) ?>
                </strong>

            </div>


            <div class="profile-item">

                <span>
                    Student ID
                </span>

                <strong>
                    <?= htmlspecialchars(
                        (string) $player['student_id']
                    ) ?>
                </strong>

            </div>


            <div class="profile-item">

                <span>
                    Email
                </span>

                <strong>
                    <?= htmlspecialchars(
                        (string) $player['email']
                    ) ?>
                </strong>

            </div>


            <div class="profile-item">

                <span>
                    Account Status
                </span>

                <strong>

                    <span class="status-badge <?= htmlspecialchars(
                        dashboardStatusClass(
                            (string) $player['account_status']
                        )
                    ) ?>">

                        <?= htmlspecialchars(
                            (string) $player['account_status']
                        ) ?>

                    </span>

                </strong>

            </div>


            <div class="profile-item">

                <span>
                    Player Status
                </span>

                <strong>

                    <span class="status-badge <?= htmlspecialchars(
                        dashboardStatusClass(
                            (string) $player['player_status']
                        )
                    ) ?>">

                        <?= htmlspecialchars(
                            (string) $player['player_status']
                        ) ?>

                    </span>

                </strong>

            </div>

        </div>

    </section>


    <!-- =========================================================
         SIMPLE INFORMATION
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Sports Management System
                </h2>

                <p>
                    Your central player dashboard.
                </p>

            </div>

            <div class="card-header-icon">
                🏆
            </div>

        </div>


        <div class="feature-placeholder">

            <p>
                Use the sidebar to access your sports, teams,
                tournaments, matches, statistics and profile.
            </p>

        </div>

    </section>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>