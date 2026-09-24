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
| A coach can be responsible for either:
| - Team A
| - Team B
|
| Therefore both sides are checked.
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
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="page-header">

    <div>

        <span class="eyebrow">Coach Portal</span>

        <h1>My Matches ⚽</h1>

        <p>
            View scheduled and completed matches involving your teams.
        </p>

    </div>

</div>

<!-- Summary -->
<div class="dashboard-grid">

    <div class="stat-card">

        <div class="stat-icon">📅</div>

        <div>

            <span class="stat-label">Total Matches</span>

            <strong>
                <?= count($matches) ?>
            </strong>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon">⚽</div>

        <div>

            <span class="stat-label">Upcoming</span>

            <strong>
                <?= $upcomingCount ?>
            </strong>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon">🏁</div>

        <div>

            <span class="stat-label">Completed</span>

            <strong>
                <?= $completedCount ?>
            </strong>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon">⏸️</div>

        <div>

            <span class="stat-label">Postponed</span>

            <strong>
                <?= $postponedCount ?>
            </strong>

        </div>

    </div>

</div>

<!-- Matches -->
<section class="dashboard-section">

    <div class="section-heading">

        <div>

            <h2>Match Schedule</h2>

            <p>
                Matches involving your assigned teams.
            </p>

        </div>

    </div>

    <?php if (empty($matches)): ?>

        <div class="empty-state">

            <div class="empty-icon">⚽</div>

            <h3>No Matches Found</h3>

            <p>
                Your teams currently do not have any scheduled matches.
            </p>

        </div>

    <?php else: ?>

        <div class="table-wrapper">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>Match</th>

                        <th>Tournament</th>

                        <th>Teams</th>

                        <th>Date & Time</th>

                        <th>Venue</th>

                        <th>Result</th>

                        <th>Status</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($matches as $match): ?>

                    <?php

                    $status = strtoupper(
                        (string) $match['match_status']
                    );

                    $hasResult =
                        $match['team_a_score'] !== null &&
                        $match['team_b_score'] !== null;

                    $teamAScore = $hasResult
                        ? (float) $match['team_a_score']
                        : null;

                    $teamBScore = $hasResult
                        ? (float) $match['team_b_score']
                        : null;

                    ?>

                    <tr>

                        <!-- Match -->
                        <td>

                            <strong>
                                Match #<?= (int) $match['match_number'] ?>
                            </strong>

                        </td>

                        <!-- Tournament -->
                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $match['tournament_name']
                                ) ?>
                            </strong>

                            <br>

                            <small>
                                <?= htmlspecialchars(
                                    $match['sport_name']
                                ) ?>
                            </small>

                        </td>

                        <!-- Teams -->
                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $match['team_a_name']
                                ) ?>
                            </strong>

                            <br>

                            <span>vs</span>

                            <br>

                            <strong>
                                <?= htmlspecialchars(
                                    $match['team_b_name']
                                ) ?>
                            </strong>

                        </td>

                        <!-- Date -->
                        <td>

                            <?= date(
                                'd M Y',
                                strtotime($match['scheduled_start'])
                            ) ?>

                            <br>

                            <small>

                                <?= date(
                                    'h:i A',
                                    strtotime($match['scheduled_start'])
                                ) ?>

                            </small>

                        </td>

                        <!-- Venue -->
                        <td>

                            <?= htmlspecialchars(
                                $match['venue_name'] ?? '-'
                            ) ?>

                        </td>

                        <!-- Result -->
                        <td>

                            <?php if ($hasResult): ?>

                                <strong>

                                    <?= rtrim(
                                        rtrim(
                                            number_format(
                                                $teamAScore,
                                                2,
                                                '.',
                                                ''
                                            ),
                                            '0'
                                        ),
                                        '.'
                                    ) ?>

                                    -

                                    <?= rtrim(
                                        rtrim(
                                            number_format(
                                                $teamBScore,
                                                2,
                                                '.',
                                                ''
                                            ),
                                            '0'
                                        ),
                                        '.'
                                    ) ?>

                                </strong>

                            <?php else: ?>

                                <span class="text-muted">
                                    Not played
                                </span>

                            <?php endif; ?>

                        </td>

                        <!-- Status -->
                        <td>

                            <span class="badge badge-<?= strtolower($status) ?>">

                                <?= htmlspecialchars($status) ?>

                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>

<!-- Note -->
<section class="dashboard-section">

    <div class="simple-info-card">

        <div class="simple-info-icon">
            ⚽
        </div>

        <div>

            <h3>Match Information</h3>

            <p>
                Match results are managed by the Sports Coordinator
                or Administrator. Coaches can use this page to monitor
                their team's scheduled and completed matches.
            </p>

        </div>

    </div>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>