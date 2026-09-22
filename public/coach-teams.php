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
| Get coach teams
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

require_once __DIR__ . '/../includes/header.php';

?>

<div class="page-header">

    <div>

        <span class="eyebrow">Coach Portal</span>

        <h1>My Teams 👥</h1>

        <p>
            View the teams assigned to you and monitor their roster.
        </p>

    </div>

</div>

<!-- Summary -->
<div class="dashboard-grid">

    <div class="stat-card">

        <div class="stat-icon">👥</div>

        <div>

            <span class="stat-label">Total Teams</span>

            <strong>
                <?= count($teams) ?>
            </strong>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon">🏃</div>

        <div>

            <span class="stat-label">Total Players</span>

            <strong>
                <?php

                $totalPlayers = 0;

                foreach ($teams as $team) {
                    $totalPlayers += (int) $team['player_count'];
                }

                echo $totalPlayers;

                ?>
            </strong>

        </div>

    </div>

</div>

<!-- Teams -->
<section class="dashboard-section">

    <div class="section-heading">

        <div>

            <h2>Assigned Teams</h2>

            <p>
                Teams currently assigned to <?= htmlspecialchars($coach['full_name']) ?>.
            </p>

        </div>

    </div>

    <?php if (empty($teams)): ?>

        <div class="empty-state">

            <div class="empty-icon">👥</div>

            <h3>No Teams Assigned</h3>

            <p>
                You currently do not have any teams assigned to you.
            </p>

        </div>

    <?php else: ?>

        <div class="table-wrapper">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>Team</th>

                        <th>Sport</th>

                        <th>Category</th>

                        <th>Players</th>

                        <th>Roster Limit</th>

                        <th>Status</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($teams as $team): ?>

                    <?php
                    $status = strtoupper((string) $team['team_status']);
                    ?>

                    <tr>

                        <td>

                            <strong>
                                <?= htmlspecialchars($team['team_name']) ?>
                            </strong>

                        </td>

                        <td>

                            <?= htmlspecialchars($team['sport_name']) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $team['team_category'] ?? '-'
                            ) ?>

                        </td>

                        <td>

                            <strong>
                                <?= (int) $team['player_count'] ?>
                            </strong>

                        </td>

                        <td>

                            <?= (int) $team['roster_limit'] ?>

                        </td>

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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>