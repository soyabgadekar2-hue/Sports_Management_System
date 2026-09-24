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
| Get Player Matches
|--------------------------------------------------------------------------
|
| A match appears when:
|
| 1. The player is an ACTIVE member of the team.
| 2. The team is participating in the tournament.
| 3. The player belongs to one of the two teams in the match.
|
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
        m.scheduled_start DESC,
        m.match_number ASC
");

$matchesStmt->execute([
    ':player_id' => $playerId
]);

$matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Helper: Match Status Class
|--------------------------------------------------------------------------
*/
function matchStatusClass(string $status): string
{
    return match ($status) {
        'COMPLETED' => 'status-completed',
        'POSTPONED' => 'status-pending',
        'CANCELLED' => 'status-rejected',
        default => 'status-active',
    };
}

/*
|--------------------------------------------------------------------------
| Helper: Match Result
|--------------------------------------------------------------------------
*/
function getPlayerMatchResult(array $match, int $playerId): string
{
    if (
        $match['team_a_score'] === null ||
        $match['team_b_score'] === null
    ) {
        return 'Upcoming';
    }

    $teamAScore = (float) $match['team_a_score'];
    $teamBScore = (float) $match['team_b_score'];
    $winnerTeamId = $match['winner_team_id'] !== null
        ? (int) $match['winner_team_id']
        : null;

    /*
     * Find which team the player belongs to in this participation.
     */
    $playerTeamId = isset($match['player_team_id'])
        ? (int) $match['player_team_id']
        : 0;

    if ($winnerTeamId !== null) {
        if ($winnerTeamId === $playerTeamId) {
            return 'Won';
        }

        return 'Lost';
    }

    if ($teamAScore === $teamBScore) {
        return 'Draw';
    }

    if ($playerTeamId === (int) $match['team_a_id']) {
        return $teamAScore > $teamBScore
            ? 'Won'
            : 'Lost';
    }

    if ($playerTeamId === (int) $match['team_b_id']) {
        return $teamBScore > $teamAScore
            ? 'Won'
            : 'Lost';
    }

    return 'Completed';
}

/*
|--------------------------------------------------------------------------
| Add player's team ID to each match
|--------------------------------------------------------------------------
*/
foreach ($matches as &$match) {

    /*
     * Find the player's team for this match directly from
     * player_match_participation.
     */
    $teamStmt = $db->prepare("
        SELECT team_id
        FROM player_match_participation
        WHERE match_participation_id = (
            SELECT MIN(match_participation_id)
            FROM player_match_participation
            WHERE match_id = :match_id
              AND player_id = :player_id
        )
        LIMIT 1
    ");

    $teamStmt->execute([
        ':match_id' => $match['match_id'],
        ':player_id' => $playerId
    ]);

    $playerTeamId = $teamStmt->fetchColumn();

    $match['player_team_id'] = $playerTeamId !== false
        ? (int) $playerTeamId
        : 0;
}

unset($match);

/*
|--------------------------------------------------------------------------
| Calculate Summary
|--------------------------------------------------------------------------
*/
$totalMatches = count($matches);
$completedMatches = 0;
$upcomingMatches = 0;
$wonMatches = 0;
$drawMatches = 0;
$lostMatches = 0;

foreach ($matches as $match) {

    if ($match['match_status'] === 'COMPLETED') {

        $completedMatches++;

        $result = getPlayerMatchResult(
            $match,
            $playerId
        );

        if ($result === 'Won') {
            $wonMatches++;
        } elseif ($result === 'Draw') {
            $drawMatches++;
        } elseif ($result === 'Lost') {
            $lostMatches++;
        }

    } elseif (
        $match['match_status'] === 'SCHEDULED'
    ) {
        $upcomingMatches++;
    }
}

/*
|--------------------------------------------------------------------------
| Header
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
                MY MATCHES
            </div>

            <h1>
                My Matches
            </h1>

            <p>
                View your scheduled and completed tournament matches.
            </p>

        </div>

        <div class="welcome-icon">
            ⚽
        </div>

    </section>


    <!-- =========================================================
         SUMMARY
         ========================================================= -->

    <section class="dashboard-stats">

        <div class="stat-card">

            <div class="stat-icon">
                ⚽
            </div>

            <div class="stat-content">

                <span>
                    Total Matches
                </span>

                <strong>
                    <?= $totalMatches ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                📅
            </div>

            <div class="stat-content">

                <span>
                    Upcoming
                </span>

                <strong>
                    <?= $upcomingMatches ?>
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
                    <?= $completedMatches ?>
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
                    <?= $wonMatches ?>
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
                    <?= $drawMatches ?>
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
                    <?= $lostMatches ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- =========================================================
         MATCH LIST
         ========================================================= -->

    <?php if (!empty($matches)): ?>

        <?php foreach ($matches as $match): ?>

            <?php

            $status = (string) $match['match_status'];

            $playerTeamId = (int) $match['player_team_id'];

            $result = getPlayerMatchResult(
                $match,
                $playerId
            );

            $resultClass = match ($result) {
                'Won' => 'status-completed',
                'Draw' => 'status-active',
                'Lost' => 'status-rejected',
                default => 'status-pending',
            };

            ?>

            <section class="dashboard-card tournament-player-card">

                <!-- =================================================
                     MATCH HEADER
                     ================================================= -->

                <div class="card-header">

                    <div>

                        <h2>

                            Match #<?= htmlspecialchars(
                                (string) $match['match_number']
                            ) ?>

                        </h2>

                        <p>

                            <?= htmlspecialchars(
                                (string) $match['tournament_name']
                            ) ?>

                            ·

                            <?= htmlspecialchars(
                                (string) $match['sport_name']
                            ) ?>

                        </p>

                    </div>


                    <div>

                        <span class="status-badge <?= htmlspecialchars(
                            matchStatusClass($status)
                        ) ?>">

                            <?= htmlspecialchars($status) ?>

                        </span>

                    </div>

                </div>


                <!-- =================================================
                     TEAMS
                     ================================================= -->

                <div class="match-teams">

                    <div class="match-team">

                        <span class="match-team-label">
                            Team A
                        </span>

                        <strong
                            class="<?= $playerTeamId === (int) $match['team_a_id']
                                ? 'player-team-highlight'
                                : '' ?>"
                        >

                            <?= htmlspecialchars(
                                (string) $match['team_a_name']
                            ) ?>

                        </strong>

                    </div>


                    <div class="match-score">

                        <?php if ($match['team_a_score'] !== null): ?>

                            <strong>

                                <?= htmlspecialchars(
                                    (string) $match['team_a_score']
                                ) ?>

                                :

                                <?= htmlspecialchars(
                                    (string) $match['team_b_score']
                                ) ?>

                            </strong>

                        <?php else: ?>

                            <strong>
                                VS
                            </strong>

                        <?php endif; ?>

                    </div>


                    <div class="match-team">

                        <span class="match-team-label">
                            Team B
                        </span>

                        <strong
                            class="<?= $playerTeamId === (int) $match['team_b_id']
                                ? 'player-team-highlight'
                                : '' ?>"
                        >

                            <?= htmlspecialchars(
                                (string) $match['team_b_name']
                            ) ?>

                        </strong>

                    </div>

                </div>


                <!-- =================================================
                     MATCH DETAILS
                     ================================================= -->

                <div class="profile-details">

                    <div class="profile-item">

                        <span>
                            Tournament
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                (string) $match['tournament_name']
                            ) ?>

                        </strong>

                    </div>


                    <div class="profile-item">

                        <span>
                            Format
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                (string) $match['tournament_format']
                            ) ?>

                        </strong>

                    </div>


                    <div class="profile-item">

                        <span>
                            Date
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                date(
                                    'd M Y',
                                    strtotime(
                                        (string) $match['scheduled_start']
                                    )
                                )
                            ) ?>

                        </strong>

                    </div>


                    <div class="profile-item">

                        <span>
                            Time
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                date(
                                    'h:i A',
                                    strtotime(
                                        (string) $match['scheduled_start']
                                    )
                                )
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
                                    $match['venue_name']
                                    ?? 'Not specified'
                                )
                            ) ?>

                        </strong>

                    </div>


                    <div class="profile-item">

                        <span>
                            Your Team
                        </span>

                        <strong>

                            <?= $playerTeamId === (int) $match['team_a_id']
                                ? htmlspecialchars(
                                    (string) $match['team_a_name']
                                )
                                : htmlspecialchars(
                                    (string) $match['team_b_name']
                                ) ?>

                        </strong>

                    </div>

                </div>


                <!-- =================================================
                     RESULT
                     ================================================= -->

                <?php if ($status === 'COMPLETED'): ?>

                    <div class="tournament-info-box">

                        <div class="card-header">

                            <div>

                                <h3>
                                    Match Result
                                </h3>

                            </div>

                            <span class="status-badge <?= $resultClass ?>">

                                <?= htmlspecialchars($result) ?>

                            </span>

                        </div>


                        <?php if (!empty($match['result_notes'])): ?>

                            <p>
                                <?= htmlspecialchars(
                                    (string) $match['result_notes']
                                ) ?>
                            </p>

                        <?php endif; ?>

                    </div>

                <?php elseif ($status === 'SCHEDULED'): ?>

                    <div class="tournament-info-box">

                        <h3>
                            Upcoming Match
                        </h3>

                        <p>
                            Make sure you are available at the scheduled
                            time and venue.
                        </p>

                    </div>

                <?php elseif ($status === 'POSTPONED'): ?>

                    <div class="tournament-info-box">

                        <h3>
                            Match Postponed
                        </h3>

                        <p>
                            This match has been postponed. Check for
                            an updated schedule.
                        </p>

                    </div>

                <?php elseif ($status === 'CANCELLED'): ?>

                    <div class="tournament-info-box">

                        <h3>
                            Match Cancelled
                        </h3>

                        <p>
                            This match has been cancelled.
                        </p>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     NOTES
                     ================================================= -->

                <?php if (!empty($match['notes'])): ?>

                    <div class="feature-placeholder">

                        <strong>
                            Match Notes
                        </strong>

                        <p>
                            <?= nl2br(
                                htmlspecialchars(
                                    (string) $match['notes']
                                )
                            ) ?>
                        </p>

                    </div>

                <?php endif; ?>

            </section>

        <?php endforeach; ?>

    <?php else: ?>

        <!-- =====================================================
             EMPTY STATE
             ===================================================== -->

        <section class="dashboard-card">

            <div class="empty-dashboard">

                <div style="font-size: 42px;">
                    ⚽
                </div>

                <strong>
                    No matches found
                </strong>

                <p>
                    You do not have any match participation records yet.
                </p>

            </div>

        </section>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>