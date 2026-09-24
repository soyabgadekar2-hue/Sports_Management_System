<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

/*
|--------------------------------------------------------------------------
| Get Active Sports
|--------------------------------------------------------------------------
*/

$sportsStmt = $pdo->query("
    SELECT
        sport_id,
        sport_name
    FROM sports
    WHERE sport_status = 'ACTIVE'
    ORDER BY sport_name
");

$sports = $sportsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Get Available Venues
|--------------------------------------------------------------------------
|
| IMPORTANT:
| venues table uses availability_status.
| It does NOT use venue_status.
|
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

    <title>Create Tournament</title>

</head>

<body>

<h1>Sports Management System</h1>

<h2>Create Tournament</h2>

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


<?php if ($error !== ''): ?>

    <p style="color: red;">
        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

<?php endif; ?>


<form
    method="POST"
    action="create-tournament-process.php"
>


    <?= csrfField() ?>


    <!-- SPORT -->

    <p>

        <label for="sport_id">
            Sport:
        </label>

        <br>

        <select
            name="sport_id"
            id="sport_id"
            required
        >

            <option value="">
                Select Sport
            </option>

            <?php foreach ($sports as $sport): ?>

                <option
                    value="<?= (int) $sport['sport_id'] ?>"
                >

                    <?= htmlspecialchars(
                        $sport['sport_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </p>


    <!-- VENUE -->

    <p>

        <label for="venue_id">
            Venue:
        </label>

        <br>

        <select
            name="venue_id"
            id="venue_id"
        >

            <option value="">
                Not assigned
            </option>

            <?php foreach ($venues as $venue): ?>

                <option
                    value="<?= (int) $venue['venue_id'] ?>"
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


    <!-- TOURNAMENT NAME -->

    <p>

        <label for="tournament_name">
            Tournament Name:
        </label>

        <br>

        <input
            type="text"
            name="tournament_name"
            id="tournament_name"
            maxlength="150"
            required
        >

    </p>


    <!-- DESCRIPTION -->

    <p>

        <label for="tournament_description">
            Description:
        </label>

        <br>

        <textarea
            name="tournament_description"
            id="tournament_description"
            rows="4"
            cols="60"
        ></textarea>

    </p>


    <!-- START DATE -->

    <p>

        <label for="start_date">
            Start Date:
        </label>

        <br>

        <input
            type="date"
            name="start_date"
            id="start_date"
            required
        >

    </p>


    <!-- END DATE -->

    <p>

        <label for="end_date">
            End Date:
        </label>

        <br>

        <input
            type="date"
            name="end_date"
            id="end_date"
            required
        >

    </p>


    <!-- FORMAT -->

    <p>

        <label for="tournament_format">
            Tournament Format:
        </label>

        <br>

        <select
            name="tournament_format"
            id="tournament_format"
            required
        >

            <option value="LEAGUE">
                League
            </option>

        </select>

    </p>


    <h3>Points System</h3>


    <!-- WIN -->

    <p>

        <label for="points_win">
            Points for Win:
        </label>

        <br>

        <input
            type="number"
            name="points_win"
            id="points_win"
            min="0"
            step="0.01"
            value="3"
            required
        >

    </p>


    <!-- DRAW -->

    <p>

        <label for="points_draw">
            Points for Draw:
        </label>

        <br>

        <input
            type="number"
            name="points_draw"
            id="points_draw"
            min="0"
            step="0.01"
            value="1"
            required
        >

    </p>


    <!-- LOSS -->

    <p>

        <label for="points_loss">
            Points for Loss:
        </label>

        <br>

        <input
            type="number"
            name="points_loss"
            id="points_loss"
            min="0"
            step="0.01"
            value="0"
            required
        >

    </p>


    <!-- RULES -->

    <p>

        <label for="rules_information">
            Rules:
        </label>

        <br>

        <textarea
            name="rules_information"
            id="rules_information"
            rows="6"
            cols="60"
        ></textarea>

    </p>


    <!-- STATUS -->

    <p>

        <label for="tournament_status">
            Status:
        </label>

        <br>

        <select
            name="tournament_status"
            id="tournament_status"
            required
        >

            <option value="DRAFT">
                Draft
            </option>

            <option value="REGISTRATION_OPEN">
                Registration Open
            </option>

            <option value="ACTIVE">
                Active
            </option>

            <option value="COMPLETED">
                Completed
            </option>

            <option value="CANCELLED">
                Cancelled
            </option>

        </select>

    </p>


    <button type="submit">
        Create Tournament
    </button>


</form>

</body>

</html>