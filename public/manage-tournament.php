<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

$tournamentId = filter_input(
    INPUT_GET,
    'tournament_id',
    FILTER_VALIDATE_INT
);

if (!$tournamentId) {
    exit('Invalid tournament ID.');
}

/*
|--------------------------------------------------------------------------
| Get Tournament
|--------------------------------------------------------------------------
*/

$tournamentStmt = $pdo->prepare("
    SELECT
        t.tournament_id,
        t.tournament_name,
        t.sport_id,
        t.venue_id,
        t.start_date,
        t.end_date,
        t.tournament_format,
        t.points_win,
        t.points_draw,
        t.points_loss,
        t.rules_information,
        t.tournament_status,
        s.sport_name,
        v.venue_name
    FROM tournaments t
    INNER JOIN sports s
        ON s.sport_id = t.sport_id
    LEFT JOIN venues v
        ON v.venue_id = t.venue_id
    WHERE t.tournament_id = :tournament_id
    LIMIT 1
");

$tournamentStmt->execute([
    ':tournament_id' => $tournamentId
]);

$tournament = $tournamentStmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    exit('Tournament not found.');
}

/*
|--------------------------------------------------------------------------
| Get Registered Teams
|--------------------------------------------------------------------------
*/

$registeredStmt = $pdo->prepare("
    SELECT
        tt.tournament_team_id,
        tt.team_id,
        tt.participation_status,
        tt.registered_at,
        tt.remarks,

        tm.team_name,
        tm.team_category,
        tm.team_status,

        s.sport_name,

        cp.designation,

        u.full_name AS coach_name

    FROM tournament_teams tt

    INNER JOIN teams tm
        ON tm.team_id = tt.team_id

    INNER JOIN sports s
        ON s.sport_id = tm.sport_id

    LEFT JOIN coach_profiles cp
        ON cp.coach_id = tm.coach_id

    LEFT JOIN users u
        ON u.user_id = cp.user_id

    WHERE tt.tournament_id = :tournament_id

    ORDER BY
        tt.participation_status ASC,
        tm.team_name ASC
");

$registeredStmt->execute([
    ':tournament_id' => $tournamentId
]);

$registeredTeams = $registeredStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Get Eligible Teams
|--------------------------------------------------------------------------
*/

$eligibleStmt = $pdo->prepare("
    SELECT
        tm.team_id,
        tm.team_name,
        tm.team_category,
        tm.team_status
    FROM teams tm
    WHERE tm.sport_id = :sport_id
      AND tm.team_status = 'ACTIVE'
      AND NOT EXISTS (
          SELECT 1
          FROM tournament_teams tt
          WHERE tt.tournament_id = :tournament_id
            AND tt.team_id = tm.team_id
            AND tt.participation_status = 'ACTIVE'
      )
    ORDER BY tm.team_name
");

$eligibleStmt->execute([
    ':sport_id' => $tournament['sport_id'],
    ':tournament_id' => $tournamentId
]);

$eligibleTeams = $eligibleStmt->fetchAll(PDO::FETCH_ASSOC);

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>
        Manage Tournament -
        <?= htmlspecialchars(
            $tournament['tournament_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </title>

</head>

<body>

<h1>Sports Management System</h1>

<h2>Manage Tournament</h2>

<p>

    <a href="admin-tournaments.php">
        ← Back to Tournament Management
    </a>

    |

    <a href="dashboard.php">
        Dashboard
    </a>

    |

    <a href="logout.php">
        Logout
    </a>

</p>

<hr>

<?php if ($message === 'team_registered'): ?>

    <p style="color: green;">
        Team registered successfully.
    </p>

<?php elseif ($message === 'team_withdrawn'): ?>

    <p style="color: green;">
        Team withdrawn successfully.
    </p>

<?php elseif ($message === 'match_scheduled'): ?>

    <p style="color: green;">
        Match scheduled successfully.
    </p>

<?php endif; ?>


<?php if ($error !== ''): ?>

    <p style="color: red;">

        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </p>

<?php endif; ?>

<hr>

<h3>Tournament Information</h3>

<table border="1" cellpadding="8" cellspacing="0">

    <tr>

        <th>Tournament ID</th>

        <td>
            <?= (int) $tournament['tournament_id'] ?>
        </td>

    </tr>

    <tr>

        <th>Name</th>

        <td>

            <?= htmlspecialchars(
                $tournament['tournament_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </td>

    </tr>

    <tr>

        <th>Sport</th>

        <td>

            <?= htmlspecialchars(
                $tournament['sport_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </td>

    </tr>

    <tr>

        <th>Venue</th>

        <td>

            <?= htmlspecialchars(
                $tournament['venue_name'] ?? 'Not assigned',
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </td>

    </tr>

    <tr>

        <th>Dates</th>

        <td>

            <?= htmlspecialchars(
                $tournament['start_date'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

            to

            <?= htmlspecialchars(
                $tournament['end_date'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </td>

    </tr>

    <tr>

        <th>Format</th>

        <td>

            <?= htmlspecialchars(
                $tournament['tournament_format'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </td>

    </tr>

    <tr>

        <th>Points</th>

        <td>

            Win:
            <?= htmlspecialchars(
                $tournament['points_win'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

            |

            Draw:
            <?= htmlspecialchars(
                $tournament['points_draw'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

            |

            Loss:
            <?= htmlspecialchars(
                $tournament['points_loss'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </td>

    </tr>

    <tr>

        <th>Status</th>

        <td>

            <?= htmlspecialchars(
                $tournament['tournament_status'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </td>

    </tr>

</table>


<?php if (
    $tournament['tournament_format'] === 'LEAGUE'
    && count($registeredTeams) >= 2
): ?>

    <hr>

    <h3>Match Scheduling</h3>

    <p>
        <?= count($registeredTeams) ?> teams are currently registered.
    </p>

    <p>

        <a href="schedule-match.php?tournament_id=<?= (int) $tournamentId ?>">

            <button type="button">
                Schedule Match
            </button>

        </a>

    </p>

    <p>

        <a href="match-results.php?tournament_id=<?= (int) $tournamentId ?>">

            View Match Results

        </a>

    </p>

    <p>

        <a href="tournament-standings.php?tournament_id=<?= (int) $tournamentId ?>">

            View Tournament Standings

        </a>

    </p>

<?php endif; ?>


<hr>

<h3>Register Team</h3>

<?php if (
    $tournament['tournament_status'] !== 'REGISTRATION_OPEN'
): ?>

    <p>
        Team registration is currently closed.
    </p>

<?php elseif (!$eligibleTeams): ?>

    <p>
        No eligible teams available for registration.
    </p>

<?php else: ?>

    <form method="POST" action="register-tournament-team.php">

        <?= csrfField() ?>

        <input
            type="hidden"
            name="tournament_id"
            value="<?= (int) $tournamentId ?>"
        >

        <p>

            <label for="team_id">
                Select Team:
            </label>

            <select
                name="team_id"
                id="team_id"
                required
            >

                <option value="">
                    Select Team
                </option>

                <?php foreach ($eligibleTeams as $team): ?>

                    <option
                        value="<?= (int) $team['team_id'] ?>"
                    >

                        <?= htmlspecialchars(
                            $team['team_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                        -

                        <?= htmlspecialchars(
                            $team['team_category'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </p>

        <p>

            <label for="remarks">
                Remarks:
            </label>

            <br>

            <textarea
                name="remarks"
                id="remarks"
                rows="4"
                cols="50"
                maxlength="500"
            ></textarea>

        </p>

        <button type="submit">
            Register Team
        </button>

    </form>

<?php endif; ?>


<hr>

<h3>Registered Teams</h3>

<?php if (!$registeredTeams): ?>

    <p>
        No teams registered yet.
    </p>

<?php else: ?>

    <table border="1" cellpadding="8" cellspacing="0">

        <thead>

            <tr>

                <th>ID</th>

                <th>Team</th>

                <th>Sport</th>

                <th>Category</th>

                <th>Coach</th>

                <th>Registered At</th>

                <th>Status</th>

                <th>Action</th>

            </tr>

        </thead>

        <tbody>

        <?php foreach ($registeredTeams as $team): ?>

            <tr>

                <td>

                    <?= (int) $team['team_id'] ?>

                </td>

                <td>

                    <?= htmlspecialchars(
                        $team['team_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

                <td>

                    <?= htmlspecialchars(
                        $team['sport_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

                <td>

                    <?= htmlspecialchars(
                        $team['team_category'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

                <td>

                    <?php if ($team['coach_name']): ?>

                        <?= htmlspecialchars(
                            $team['coach_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    <?php else: ?>

                        Not assigned

                    <?php endif; ?>

                </td>

                <td>

                    <?= htmlspecialchars(
                        $team['registered_at'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

                <td>

                    <?= htmlspecialchars(
                        $team['participation_status'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

                <td>

                    <?php if (
                        $team['participation_status'] === 'ACTIVE'
                        && $tournament['tournament_status'] === 'REGISTRATION_OPEN'
                    ): ?>

                        <form
                            method="POST"
                            action="withdraw-tournament-team.php"
                        >

                            <?= csrfField() ?>

                            <input
                                type="hidden"
                                name="tournament_id"
                                value="<?= (int) $tournamentId ?>"
                            >

                            <input
                                type="hidden"
                                name="team_id"
                                value="<?= (int) $team['team_id'] ?>"
                            >

                            <button type="submit">
                                Withdraw
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

<?php endif; ?>

</body>

</html>