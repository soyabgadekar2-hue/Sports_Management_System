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
        t.start_date,
        t.end_date,
        t.tournament_status,
        t.points_win,
        t.points_draw,
        t.points_loss,
        s.sport_name
    FROM tournaments t
    INNER JOIN sports s
        ON s.sport_id = t.sport_id
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
| Get Standings
|--------------------------------------------------------------------------
*/

$standingsStmt = $pdo->prepare("
    SELECT
        ts.standing_rank,
        ts.team_id,
        tm.team_name,
        ts.matches_played,
        ts.wins,
        ts.draws,
        ts.losses,
        ts.score_for,
        ts.score_against,
        ts.score_difference,
        ts.points,
        ts.last_calculated_at
    FROM tournament_standings ts
    INNER JOIN teams tm
        ON tm.team_id = ts.team_id
    WHERE ts.tournament_id = :tournament_id
    ORDER BY
        ts.standing_rank ASC
");

$standingsStmt->execute([
    ':tournament_id' => $tournamentId
]);

$standings = $standingsStmt->fetchAll(PDO::FETCH_ASSOC);

$message = $_GET['message'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>
        Tournament Standings -
        <?= htmlspecialchars(
            $tournament['tournament_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </title>

</head>

<body>

<h1>Sports Management System</h1>

<h2>Tournament Standings</h2>

<p>

    <a href="manage-tournament.php?tournament_id=<?= (int) $tournamentId ?>">
        ← Back to Tournament
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

<?php if ($message === 'calculated'): ?>

    <p style="color: green;">
        Tournament standings calculated successfully.
    </p>

<?php endif; ?>

<h3>Tournament Information</h3>

<table border="1" cellpadding="8" cellspacing="0">

    <tr>

        <th>Tournament</th>

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

        <th>Status</th>

        <td>
            <?= htmlspecialchars(
                $tournament['tournament_status'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </td>

    </tr>

    <tr>

        <th>Points System</th>

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

</table>

<hr>

<h3>League Table</h3>

<?php if (!$standings): ?>

    <p>
        No standings have been calculated yet.
    </p>

    <p>
        <a href="calculate-standings.php?tournament_id=<?= (int) $tournamentId ?>">
            Calculate Standings
        </a>
    </p>

<?php else: ?>

    <p>
        <a href="calculate-standings.php?tournament_id=<?= (int) $tournamentId ?>">
            Recalculate Standings
        </a>
    </p>

    <table border="1" cellpadding="8" cellspacing="0">

        <thead>

            <tr>

                <th>Rank</th>

                <th>Team</th>

                <th>MP</th>

                <th>W</th>

                <th>D</th>

                <th>L</th>

                <th>Score For</th>

                <th>Score Against</th>

                <th>Score Difference</th>

                <th>Points</th>

            </tr>

        </thead>

        <tbody>

        <?php foreach ($standings as $standing): ?>

            <tr>

                <td>
                    <?= (int) $standing['standing_rank'] ?>
                </td>

                <td>

                    <?= htmlspecialchars(
                        $standing['team_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

                <td>
                    <?= (int) $standing['matches_played'] ?>
                </td>

                <td>
                    <?= (int) $standing['wins'] ?>
                </td>

                <td>
                    <?= (int) $standing['draws'] ?>
                </td>

                <td>
                    <?= (int) $standing['losses'] ?>
                </td>

                <td>
                    <?= number_format(
                        (float) $standing['score_for'],
                        2
                    ) ?>
                </td>

                <td>
                    <?= number_format(
                        (float) $standing['score_against'],
                        2
                    ) ?>
                </td>

                <td>
                    <?= number_format(
                        (float) $standing['score_difference'],
                        2
                    ) ?>
                </td>

                <td>
                    <strong>
                        <?= number_format(
                            (float) $standing['points'],
                            2
                        ) ?>
                    </strong>
                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

<?php endif; ?>

</body>

</html>