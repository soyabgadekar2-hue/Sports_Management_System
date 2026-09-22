<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

$pageTitle = 'Tournament Management';

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

$sql = "
    SELECT
        t.tournament_id,
        t.tournament_name,
        t.start_date,
        t.end_date,
        t.tournament_format,
        t.points_win,
        t.points_draw,
        t.points_loss,
        t.tournament_status,
        s.sport_name,
        v.venue_name,
        COUNT(DISTINCT tt.tournament_team_id) AS team_count
    FROM tournaments t
    INNER JOIN sports s
        ON s.sport_id = t.sport_id
    LEFT JOIN venues v
        ON v.venue_id = t.venue_id
    LEFT JOIN tournament_teams tt
        ON tt.tournament_id = t.tournament_id
        AND tt.participation_status = 'ACTIVE'
    GROUP BY
        t.tournament_id,
        t.tournament_name,
        t.start_date,
        t.end_date,
        t.tournament_format,
        t.points_win,
        t.points_draw,
        t.points_loss,
        t.tournament_status,
        s.sport_name,
        v.venue_name
    ORDER BY t.start_date DESC, t.tournament_id DESC
";

$stmt = $pdo->query($sql);

$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';

?>

<!-- Page Heading -->
<div class="page-heading">

    <div>
        <h1>Tournament Management</h1>

        <p>
            Create, manage and monitor college sports tournaments.
        </p>
    </div>

    <a
        href="create-tournament.php"
        class="btn btn-primary"
    >
        + Create New Tournament
    </a>

</div>


<!-- Success Messages -->

<?php if ($message === 'created'): ?>

    <div class="alert alert-success">
        ✓ Tournament created successfully.
    </div>

<?php elseif ($message === 'updated'): ?>

    <div class="alert alert-success">
        ✓ Tournament updated successfully.
    </div>

<?php endif; ?>


<!-- Error Message -->

<?php if ($error !== ''): ?>

    <div class="alert alert-danger">

        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

<?php endif; ?>


<!-- Tournament Card -->

<div class="card">

    <div class="card-header">

        <h2>
            Existing Tournaments
        </h2>

        <p>
            View and manage all tournaments created in the system.
        </p>

    </div>


    <div class="card-body">

        <?php if (empty($tournaments)): ?>

            <div class="empty-state">

                <div class="empty-icon">
                    🏆
                </div>

                <h3>
                    No tournaments found
                </h3>

                <p>
                    Create your first tournament to get started.
                </p>

                <br>

                <a
                    href="create-tournament.php"
                    class="btn btn-primary"
                >
                    + Create Tournament
                </a>

            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table class="data-table">

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Tournament</th>
                            <th>Sport</th>
                            <th>Venue</th>
                            <th>Dates</th>
                            <th>Format</th>
                            <th>Points</th>
                            <th>Teams</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($tournaments as $tournament): ?>

                        <tr>

                            <!-- ID -->

                            <td>
                                #<?= (int) $tournament['tournament_id'] ?>
                            </td>


                            <!-- Tournament -->

                            <td>

                                <div class="tournament-name">

                                    <?= htmlspecialchars(
                                        $tournament['tournament_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                                <div class="tournament-id">

                                    Tournament ID:
                                    <?= (int) $tournament['tournament_id'] ?>

                                </div>

                            </td>


                            <!-- Sport -->

                            <td>

                                <span class="badge badge-blue">

                                    <?= htmlspecialchars(
                                        $tournament['sport_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>

                            </td>


                            <!-- Venue -->

                            <td>

                                <?= htmlspecialchars(
                                    $tournament['venue_name'] ?? 'Not assigned',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <!-- Dates -->

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $tournament['start_date'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                                <br>

                                <span class="date-secondary">
                                    to
                                    <?= htmlspecialchars(
                                        $tournament['end_date'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </td>


                            <!-- Format -->

                            <td>

                                <span class="badge badge-gray">

                                    <?= htmlspecialchars(
                                        $tournament['tournament_format'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>

                            </td>


                            <!-- Points -->

                            <td>

                                <div class="points-list">

                                    <div>
                                        <strong>W:</strong>
                                        <?= htmlspecialchars(
                                            (string) $tournament['points_win'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <div>
                                        <strong>D:</strong>
                                        <?= htmlspecialchars(
                                            (string) $tournament['points_draw'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <div>
                                        <strong>L:</strong>
                                        <?= htmlspecialchars(
                                            (string) $tournament['points_loss'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                </div>

                            </td>


                            <!-- Teams -->

                            <td>

                                <strong>
                                    <?= (int) $tournament['team_count'] ?>
                                </strong>

                            </td>


                            <!-- Status -->

                            <td>

                                <?php

                                $status = strtoupper(
                                    (string) $tournament['tournament_status']
                                );

                                $statusClass = 'badge-gray';

                                if (
                                    in_array(
                                        $status,
                                        [
                                            'ACTIVE',
                                            'ONGOING',
                                            'REGISTRATION_OPEN'
                                        ],
                                        true
                                    )
                                ) {
                                    $statusClass = 'badge-success';

                                } elseif (
                                    in_array(
                                        $status,
                                        [
                                            'COMPLETED',
                                            'CLOSED'
                                        ],
                                        true
                                    )
                                ) {
                                    $statusClass = 'badge-blue';

                                } elseif (
                                    in_array(
                                        $status,
                                        [
                                            'CANCELLED',
                                            'REJECTED'
                                        ],
                                        true
                                    )
                                ) {
                                    $statusClass = 'badge-danger';
                                }

                                ?>

                                <span class="badge <?= $statusClass ?>">

                                    <?= htmlspecialchars(
                                        $status,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>

                            </td>


                            <!-- Action -->

                            <td>

                                <a
                                    href="manage-tournament.php?tournament_id=<?= (int) $tournament['tournament_id'] ?>"
                                    class="action-link"
                                >
                                    Manage →
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>