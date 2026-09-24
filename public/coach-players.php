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
| Get players from coach's teams
|--------------------------------------------------------------------------
*/
$playersStmt = $db->prepare("
    SELECT
        pp.player_id,
        pp.student_id,
        pp.department,
        pp.course,
        pp.academic_year,
        pp.semester,
        pp.gender,
        pp.player_status,

        u.full_name,
        u.email,
        u.phone,
        u.account_status,

        t.team_id,
        t.team_name,
        t.team_category,

        s.sport_name

    FROM team_players tp

    INNER JOIN teams t
        ON t.team_id = tp.team_id

    INNER JOIN sports s
        ON s.sport_id = t.sport_id

    INNER JOIN player_profiles pp
        ON pp.player_id = tp.player_id

    INNER JOIN users u
        ON u.user_id = pp.user_id

    WHERE t.coach_id = :coach_id
      AND tp.membership_status = 'ACTIVE'
      AND tp.left_at IS NULL
      AND pp.player_status = 'ACTIVE'
      AND u.account_status = 'APPROVED'

    ORDER BY
        t.team_name ASC,
        u.full_name ASC
");

$playersStmt->execute([
    ':coach_id' => $coachId
]);

$players = $playersStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Group players by team
|--------------------------------------------------------------------------
*/
$playersByTeam = [];

foreach ($players as $player) {

    $teamId = (int) $player['team_id'];

    if (!isset($playersByTeam[$teamId])) {
        $playersByTeam[$teamId] = [
            'team_name' => $player['team_name'],
            'team_category' => $player['team_category'],
            'sport_name' => $player['sport_name'],
            'players' => []
        ];
    }

    $playersByTeam[$teamId]['players'][] = $player;
}

$totalPlayers = count($players);

require_once __DIR__ . '/../includes/header.php';

?>

<div class="page-header">

    <div>

        <span class="eyebrow">Coach Portal</span>

        <h1>Team Players 👥</h1>

        <p>
            View the approved players currently assigned to your teams.
        </p>

    </div>

</div>

<!-- Summary -->
<div class="dashboard-grid">

    <div class="stat-card">

        <div class="stat-icon">👥</div>

        <div>

            <span class="stat-label">Total Players</span>

            <strong>
                <?= $totalPlayers ?>
            </strong>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon">🏆</div>

        <div>

            <span class="stat-label">My Teams</span>

            <strong>
                <?= count($playersByTeam) ?>
            </strong>

        </div>

    </div>

</div>

<?php if (empty($playersByTeam)): ?>

    <section class="dashboard-section">

        <div class="empty-state">

            <div class="empty-icon">👥</div>

            <h3>No Players Found</h3>

            <p>
                There are currently no approved active players
                assigned to your teams.
            </p>

        </div>

    </section>

<?php else: ?>

    <?php foreach ($playersByTeam as $team): ?>

        <section class="dashboard-section">

            <div class="section-heading">

                <div>

                    <h2>
                        <?= htmlspecialchars($team['team_name']) ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars($team['sport_name']) ?>

                        <?php if (!empty($team['team_category'])): ?>

                            • <?= htmlspecialchars($team['team_category']) ?>

                        <?php endif; ?>

                    </p>

                </div>

                <div>

                    <span class="badge badge-approved">
                        <?= count($team['players']) ?> Players
                    </span>

                </div>

            </div>

            <div class="table-wrapper">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>Player</th>

                            <th>Student ID</th>

                            <th>Department</th>

                            <th>Course</th>

                            <th>Year</th>

                            <th>Email</th>

                            <th>Status</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($team['players'] as $player): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= htmlspecialchars($player['full_name']) ?>
                                </strong>

                            </td>

                            <td>

                                <?= htmlspecialchars($player['student_id']) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($player['department'] ?? '-') ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($player['course'] ?? '-') ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($player['academic_year'] ?? '-') ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($player['email']) ?>

                            </td>

                            <td>

                                <?php
                                $status = strtoupper(
                                    (string) $player['player_status']
                                );
                                ?>

                                <span class="badge badge-<?= strtolower($status) ?>">

                                    <?= htmlspecialchars($status) ?>

                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endforeach; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>