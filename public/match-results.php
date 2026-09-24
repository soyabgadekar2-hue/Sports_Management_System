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
| Get Matches
|--------------------------------------------------------------------------
*/

$matchesStmt = $pdo->prepare("
    SELECT
        m.match_id,
        m.match_number,
        m.scheduled_start,
        m.scheduled_end,
        m.match_status,
        m.notes,

        m.team_a_id,
        m.team_b_id,

        team_a.team_name AS team_a_name,
        team_b.team_name AS team_b_name,

        v.venue_name,

        mr.team_a_score,
        mr.team_b_score,
        mr.winner_team_id,
        mr.result_notes

    FROM matches m

    INNER JOIN teams team_a
        ON team_a.team_id = m.team_a_id

    INNER JOIN teams team_b
        ON team_b.team_id = m.team_b_id

    INNER JOIN venues v
        ON v.venue_id = m.venue_id

    LEFT JOIN match_results mr
        ON mr.match_id = m.match_id

    WHERE m.tournament_id = :tournament_id

    ORDER BY
        m.match_number ASC,
        m.scheduled_start ASC
");

$matchesStmt->execute([
    ':tournament_id' => $tournamentId
]);

$matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>
        Match Results -
        <?= htmlspecialchars(
            $tournament['tournament_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </title>

</head>

<body>

<h1>Sports Management System</h1>

<h2>Match Results</h2>

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

<?php if ($message === 'result_saved'): ?>

    <p style="color: green;">
        Match result saved successfully.
    </p>

<?php elseif ($message === 'result_updated'): ?>

    <p style="color: green;">
        Match result updated successfully and standings recalculated.
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

</table>

<hr>

<h3>Scheduled Matches</h3>

<?php if (!$matches): ?>

    <p>
        No matches have been scheduled yet.
    </p>

<?php else: ?>

<table border="1" cellpadding="8" cellspacing="0">

    <thead>

        <tr>

            <th>Match</th>

            <th>Team A</th>

            <th>Team B</th>

            <th>Venue</th>

            <th>Date & Time</th>

            <th>Status</th>

            <th>Result</th>

            <th>Action</th>

        </tr>

    </thead>

    <tbody>

    <?php foreach ($matches as $match): ?>

        <tr>

            <td>
                #<?= (int) $match['match_number'] ?>
            </td>

            <td>

                <?= htmlspecialchars(
                    $match['team_a_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </td>

            <td>

                <?= htmlspecialchars(
                    $match['team_b_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </td>

            <td>

                <?= htmlspecialchars(
                    $match['venue_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </td>

            <td>

                <?= htmlspecialchars(
                    date(
                        'd M Y, h:i A',
                        strtotime($match['scheduled_start'])
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </td>

            <td>

                <?= htmlspecialchars(
                    $match['match_status'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </td>

            <td>

                <?php if ($match['team_a_score'] !== null): ?>

                    <?= htmlspecialchars(
                        $match['team_a_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                    :

                    <?= htmlspecialchars(
                        $match['team_a_score'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                    <br>

                    <?= htmlspecialchars(
                        $match['team_b_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                    :

                    <?= htmlspecialchars(
                        $match['team_b_score'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                <?php else: ?>

                    Not entered

                <?php endif; ?>

            </td>

            <td>

                <?php if ($match['match_status'] === 'SCHEDULED'): ?>

                    <a href="enter-match-result.php?match_id=<?= (int) $match['match_id'] ?>">
                        Enter Result
                    </a>

                <?php elseif ($match['match_status'] === 'COMPLETED'): ?>

                    <a href="edit-match-result.php?match_id=<?= (int) $match['match_id'] ?>">
                        Correct Result
                    </a>

                <?php elseif ($match['match_status'] === 'CANCELLED'): ?>

                    Match Cancelled

                <?php else: ?>

                    <?= htmlspecialchars(
                        $match['match_status'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                <?php endif; ?>

            </td>

        </tr>

    <?php endforeach; ?>

    </tbody>

</table>

<?php endif; ?>

</body>

</html>