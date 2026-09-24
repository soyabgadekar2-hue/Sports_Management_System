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
                MY TOURNAMENTS
            </div>

            <h1>
                My Tournaments
            </h1>

            <p>
                View tournaments in which your active team is participating.
            </p>

        </div>

        <div class="welcome-icon">
            🏆
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
                🏆
            </div>

            <div class="stat-content">

                <span>
                    Tournaments
                </span>

                <strong>
                    <?= count($tournaments) ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- =========================================================
         TOURNAMENTS
         ========================================================= -->

    <?php if (!empty($tournaments)): ?>

        <?php foreach ($tournaments as $tournament): ?>

            <section class="dashboard-card tournament-player-card">

                <!-- =================================================
                     TOURNAMENT HEADER
                     ================================================= -->

                <div class="card-header">

                    <div>

                        <h2>
                            <?= htmlspecialchars(
                                (string) $tournament['tournament_name']
                            ) ?>
                        </h2>

                        <p>
                            <?= htmlspecialchars(
                                (string) $tournament['sport_name']
                            ) ?>
                            ·
                            <?= htmlspecialchars(
                                (string) $tournament['tournament_format']
                            ) ?>
                        </p>

                    </div>


                    <div>

                        <span class="status-badge status-active">

                            <?= htmlspecialchars(
                                (string) $tournament['tournament_status']
                            ) ?>

                        </span>

                    </div>

                </div>


                <!-- =================================================
                     TOURNAMENT INFORMATION
                     ================================================= -->

                <div class="profile-details">

                    <div class="profile-item">

                        <span>
                            My Team
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                (string) $tournament['team_name']
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-item">

                        <span>
                            Sport
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                (string) $tournament['sport_name']
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-item">

                        <span>
                            Format
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                (string) $tournament['tournament_format']
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-item">

                        <span>
                            Venue
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                (string) (
                                    $tournament['venue_name']
                                    ?? 'Not specified'
                                )
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-item">

                        <span>
                            Start Date
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                date(
                                    'd M Y',
                                    strtotime(
                                        (string) $tournament['start_date']
                                    )
                                )
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-item">

                        <span>
                            End Date
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                date(
                                    'd M Y',
                                    strtotime(
                                        (string) $tournament['end_date']
                                    )
                                )
                            ) ?>
                        </strong>

                    </div>

                </div>


                <!-- =================================================
                     POINT SYSTEM
                     ================================================= -->

                <div class="tournament-info-box">

                    <h3>
                        Points System
                    </h3>

                    <div class="profile-details">

                        <div class="profile-item">

                            <span>
                                Win
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    (string) $tournament['points_win']
                                ) ?>
                            </strong>

                        </div>


                        <div class="profile-item">

                            <span>
                                Draw
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    (string) $tournament['points_draw']
                                ) ?>
                            </strong>

                        </div>


                        <div class="profile-item">

                            <span>
                                Loss
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    (string) $tournament['points_loss']
                                ) ?>
                            </strong>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     TEAM STANDING
                     ================================================= -->

                <div class="tournament-info-box">

                    <div class="card-header">

                        <div>

                            <h3>
                                My Team Standing
                            </h3>

                            <p>
                                Current tournament performance
                            </p>

                        </div>

                        <div class="card-header-icon">
                            📊
                        </div>

                    </div>


                    <?php if ($tournament['standing_rank'] !== null): ?>

                        <div class="dashboard-stats">

                            <div class="stat-card">

                                <div class="stat-icon">
                                    🥇
                                </div>

                                <div class="stat-content">

                                    <span>
                                        Rank
                                    </span>

                                    <strong>
                                        #<?= htmlspecialchars(
                                            (string) $tournament['standing_rank']
                                        ) ?>
                                    </strong>

                                </div>

                            </div>


                            <div class="stat-card">

                                <div class="stat-icon">
                                    🎯
                                </div>

                                <div class="stat-content">

                                    <span>
                                        Points
                                    </span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            (string) $tournament['points']
                                        ) ?>
                                    </strong>

                                </div>

                            </div>


                            <div class="stat-card">

                                <div class="stat-icon">
                                    ⚽
                                </div>

                                <div class="stat-content">

                                    <span>
                                        Played
                                    </span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            (string) $tournament['matches_played']
                                        ) ?>
                                    </strong>

                                </div>

                            </div>


                            <div class="stat-card">

                                <div class="stat-icon">
                                    ✅
                                </div>

                                <div class="stat-content">

                                    <span>
                                        Wins
                                    </span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            (string) $tournament['wins']
                                        ) ?>
                                    </strong>

                                </div>

                            </div>


                            <div class="stat-card">

                                <div class="stat-icon">
                                    🤝
                                </div>

                                <div class="stat-content">

                                    <span>
                                        Draws
                                    </span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            (string) $tournament['draws']
                                        ) ?>
                                    </strong>

                                </div>

                            </div>


                            <div class="stat-card">

                                <div class="stat-icon">
                                    ❌
                                </div>

                                <div class="stat-content">

                                    <span>
                                        Losses
                                    </span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            (string) $tournament['losses']
                                        ) ?>
                                    </strong>

                                </div>

                            </div>

                        </div>


                        <!-- Detailed Standing -->

                        <div class="profile-details">

                            <div class="profile-item">

                                <span>
                                    Score For
                                </span>

                                <strong>
                                    <?= htmlspecialchars(
                                        (string) $tournament['score_for']
                                    ) ?>
                                </strong>

                            </div>


                            <div class="profile-item">

                                <span>
                                    Score Against
                                </span>

                                <strong>
                                    <?= htmlspecialchars(
                                        (string) $tournament['score_against']
                                    ) ?>
                                </strong>

                            </div>


                            <div class="profile-item">

                                <span>
                                    Score Difference
                                </span>

                                <strong>
                                    <?= htmlspecialchars(
                                        (string) $tournament['score_difference']
                                    ) ?>
                                </strong>

                            </div>


                            <div class="profile-item">

                                <span>
                                    Tournament Points
                                </span>

                                <strong>
                                    <?= htmlspecialchars(
                                        (string) $tournament['points']
                                    ) ?>
                                </strong>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="empty-dashboard">

                            <strong>
                                Standings not available yet
                            </strong>

                            <p>
                                Your team does not have a calculated
                                tournament standing yet.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

        <?php endforeach; ?>

    <?php else: ?>

        <!-- =====================================================
             NO TOURNAMENTS
             ===================================================== -->

        <section class="dashboard-card">

            <div class="empty-dashboard">

                <strong>
                    No tournaments found
                </strong>

                <p>
                    Your active team is not currently registered
                    in any tournament.
                </p>

            </div>

        </section>

    <?php endif; ?>


    <!-- =========================================================
         INFORMATION
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Tournament Information
                </h2>

                <p>
                    How tournament participation works.
                </p>

            </div>

            <div class="card-header-icon">
                ℹ️
            </div>

        </div>


        <div class="feature-placeholder">

            <p>
                Tournament participation is managed at the team level.
                Your tournaments appear here when your active team is
                registered for a tournament.
            </p>

            <p>
                Tournament standings are calculated from completed
                match results.
            </p>

        </div>

    </section>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>