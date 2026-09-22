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
| Get tournament
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
| Only LEAGUE format is supported
|--------------------------------------------------------------------------
*/

if ($tournament['tournament_format'] !== 'LEAGUE') {
    exit('Only league tournaments are supported.');
}

/*
|--------------------------------------------------------------------------
| Get registered teams
|--------------------------------------------------------------------------
*/

$teamsStmt = $pdo->prepare("
    SELECT
        tt.team_id,
        tm.team_name
    FROM tournament_teams tt
    INNER JOIN teams tm
        ON tm.team_id = tt.team_id
    WHERE tt.tournament_id = :tournament_id
      AND tt.participation_status = 'ACTIVE'
      AND tm.team_status = 'ACTIVE'
      AND tm.sport_id = :sport_id
    ORDER BY tm.team_name
");

$teamsStmt->execute([
    ':tournament_id' => $tournamentId,
    ':sport_id' => $tournament['sport_id']
]);

$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($teams) < 2) {
    exit('At least two registered teams are required to schedule a match.');
}

/*
|--------------------------------------------------------------------------
| Get available venues
|--------------------------------------------------------------------------
*/

$venuesStmt = $pdo->query("
    SELECT
        venue_id,
        venue_name
    FROM venues
    WHERE availability_status = 'AVAILABLE'
    ORDER BY venue_name
");

$venues = $venuesStmt->fetchAll(PDO::FETCH_ASSOC);

$error = $_GET['error'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Schedule Match</title>

</head>

<body>

<h1>Sports Management System</h1>

<h2>Schedule League Match</h2>

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

        <th>Tournament Dates</th>

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

        <th>Tournament Venue</th>

        <td>

            <?= htmlspecialchars(
                $tournament['venue_name'] ?? 'Not assigned',
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

<h3>Create Match</h3>

<form
    method="POST"
    action="schedule-match-process.php"
>

    <?= csrfField() ?>

    <input
        type="hidden"
        name="tournament_id"
        value="<?= (int) $tournamentId ?>"
    >

    <p>

        <label for="team_a_id">
            <strong>Team A:</strong>
        </label>

        <br>

        <select
            name="team_a_id"
            id="team_a_id"
            required
        >

            <option value="">
                Select Team A
            </option>

            <?php foreach ($teams as $team): ?>

                <option
                    value="<?= (int) $team['team_id'] ?>"
                >

                    <?= htmlspecialchars(
                        $team['team_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </p>


    <p>

        <label for="team_b_id">
            <strong>Team B:</strong>
        </label>

        <br>

        <select
            name="team_b_id"
            id="team_b_id"
            required
        >

            <option value="">
                Select Team B
            </option>

            <?php foreach ($teams as $team): ?>

                <option
                    value="<?= (int) $team['team_id'] ?>"
                >

                    <?= htmlspecialchars(
                        $team['team_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </p>


    <p>

        <label for="venue_id">
            <strong>Venue:</strong>
        </label>

        <br>

        <select
            name="venue_id"
            id="venue_id"
            required
        >

            <option value="">
                Select Venue
            </option>

            <?php foreach ($venues as $venue): ?>

                <option
                    value="<?= (int) $venue['venue_id'] ?>"
                    <?php
                    if (
                        !empty($tournament['venue_id'])
                        &&
                        (int) $tournament['venue_id'] ===
                        (int) $venue['venue_id']
                    ) {
                        echo 'selected';
                    }
                    ?>
                >

                    <?= htmlspecialchars(
                        $venue['venue_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </p>


    <p>

        <label for="scheduled_start">
            <strong>Start Date & Time:</strong>
        </label>

        <br>

        <input
            type="datetime-local"
            name="scheduled_start"
            id="scheduled_start"
            required
        >

    </p>


    <p>

        <label for="scheduled_end">
            <strong>End Date & Time:</strong>
        </label>

        <br>

        <input
            type="datetime-local"
            name="scheduled_end"
            id="scheduled_end"
            required
        >

    </p>


    <p>

        <label for="notes">
            <strong>Notes:</strong>
        </label>

        <br>

        <textarea
            name="notes"
            id="notes"
            rows="4"
            cols="60"
            maxlength="1000"
            placeholder="Optional match notes"
        ></textarea>

    </p>


    <button type="submit">
        Schedule Match
    </button>

</form>

</body>

</html>