<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

$matchId = filter_input(
    INPUT_GET,
    'match_id',
    FILTER_VALIDATE_INT
);

if (!$matchId) {
    exit('Invalid match ID.');
}

/*
|--------------------------------------------------------------------------
| Get Match
|--------------------------------------------------------------------------
*/
$matchStmt = $pdo->prepare("
    SELECT
        m.match_id,
        m.match_number,
        m.tournament_id,
        m.team_a_id,
        m.team_b_id,
        m.scheduled_start,
        m.scheduled_end,
        m.match_status,

        tournament.tournament_name,

        team_a.team_name AS team_a_name,
        team_b.team_name AS team_b_name,

        v.venue_name,

        mr.team_a_score,
        mr.team_b_score,
        mr.result_notes

    FROM matches m

    INNER JOIN tournaments tournament
        ON tournament.tournament_id = m.tournament_id

    INNER JOIN teams team_a
        ON team_a.team_id = m.team_a_id

    INNER JOIN teams team_b
        ON team_b.team_id = m.team_b_id

    INNER JOIN venues v
        ON v.venue_id = m.venue_id

    LEFT JOIN match_results mr
        ON mr.match_id = m.match_id

    WHERE m.match_id = :match_id

    LIMIT 1
");

$matchStmt->execute([
    ':match_id' => $matchId
]);

$match = $matchStmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    exit('Match not found.');
}

if ($match['match_status'] !== 'SCHEDULED') {
    exit('This match is not available for result entry.');
}

$error = $_GET['error'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>
        Enter Match Result -
        <?= htmlspecialchars(
            $match['tournament_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </title>

</head>

<body>

<h1>Sports Management System</h1>

<h2>Enter Match Result</h2>

<p>

    <a href="match-results.php?tournament_id=<?= (int) $match['tournament_id'] ?>">
        ← Back to Match Results
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

<h3>Match Information</h3>

<table border="1" cellpadding="8" cellspacing="0">

    <tr>

        <th>Tournament</th>

        <td>
            <?= htmlspecialchars(
                $match['tournament_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </td>

    </tr>

    <tr>

        <th>Match</th>

        <td>
            #<?= (int) $match['match_number'] ?>
        </td>

    </tr>

    <tr>

        <th>Team A</th>

        <td>
            <?= htmlspecialchars(
                $match['team_a_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </td>

    </tr>

    <tr>

        <th>Team B</th>

        <td>
            <?= htmlspecialchars(
                $match['team_b_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </td>

    </tr>

    <tr>

        <th>Venue</th>

        <td>
            <?= htmlspecialchars(
                $match['venue_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </td>

    </tr>

    <tr>

        <th>Scheduled</th>

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

    </tr>

</table>

<hr>

<?php if ($error !== ''): ?>

    <p style="color: red;">

        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </p>

<?php endif; ?>

<h3>Enter Result</h3>

<form method="POST" action="save-match-result.php">

    <?= csrfField() ?>

    <input
        type="hidden"
        name="match_id"
        value="<?= (int) $matchId ?>"
    >

    <p>

        <label for="team_a_score">

            <strong>
                <?= htmlspecialchars(
                    $match['team_a_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

            Score:

        </label>

        <input
            type="number"
            id="team_a_score"
            name="team_a_score"
            min="0"
            step="0.01"
            required
        >

    </p>

    <p>

        <label for="team_b_score">

            <strong>
                <?= htmlspecialchars(
                    $match['team_b_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

            Score:

        </label>

        <input
            type="number"
            id="team_b_score"
            name="team_b_score"
            min="0"
            step="0.01"
            required
        >

    </p>

    <p>

        <label for="result_notes">
            Result Notes:
        </label>

        <br>

        <textarea
            id="result_notes"
            name="result_notes"
            rows="5"
            cols="50"
            maxlength="1000"
        ></textarea>

    </p>

    <button type="submit">
        Save Match Result
    </button>

</form>

</body>

</html>