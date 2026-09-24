<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

$teamId = filter_var(
    $_GET['team_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $teamId === false
    || $teamId === null
    || $teamId <= 0
) {
    http_response_code(400);
    exit('Invalid team ID.');
}

/*
|--------------------------------------------------------------------------
| Get team
|--------------------------------------------------------------------------
*/

$teamStatement = $pdo->prepare(
    'SELECT
        t.team_id,
        t.sport_id,
        t.team_name,
        t.team_category,
        t.roster_limit,
        t.team_status,
        s.sport_name,
        t.coach_id
     FROM teams t
     INNER JOIN sports s
        ON s.sport_id = t.sport_id
     WHERE t.team_id = :team_id
     LIMIT 1'
);

$teamStatement->execute([
    ':team_id' => $teamId
]);

$team = $teamStatement->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    http_response_code(404);
    exit('Team not found.');
}

/*
|--------------------------------------------------------------------------
| Get coaches
|--------------------------------------------------------------------------
*/

$coachStatement = $pdo->prepare(
    'SELECT
        cp.coach_id,
        u.full_name,
        cp.specialization
     FROM coach_profiles cp
     INNER JOIN users u
        ON u.user_id = cp.user_id
     WHERE cp.coach_status = :coach_status
       AND u.account_status = :account_status
     ORDER BY u.full_name ASC'
);

$coachStatement->execute([
    ':coach_status' => 'ACTIVE',
    ':account_status' => 'APPROVED'
]);

$coaches = $coachStatement->fetchAll(PDO::FETCH_ASSOC);

$message = $_GET['message'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Edit Team
    </title>

</head>

<body>

<h1>
    Sports Management System
</h1>

<h2>
    Edit Team
</h2>

<p>

    <a href="manage-team.php?team_id=<?= (int) $teamId ?>">
        ← Back to Manage Team
    </a>

    |

    <a href="admin-teams.php">
        Team Management
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

<?php if ($message === 'updated'): ?>

    <p style="color: green;">

        <strong>
            Team updated successfully.
        </strong>

    </p>

<?php endif; ?>

<h3>
    Edit:
    <?= htmlspecialchars(
        $team['team_name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</h3>

<form
    action="edit-team-process.php"
    method="POST"
>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(
            csrfToken(),
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <input
        type="hidden"
        name="team_id"
        value="<?= (int) $teamId ?>"
    >

    <p>

        <label for="sport">
            <strong>Sport:</strong>
        </label>

        <br>

        <input
            type="text"
            id="sport"
            value="<?= htmlspecialchars(
                $team['sport_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            readonly
        >

        <br>

        <small>
            Sport cannot be changed after team creation.
        </small>

    </p>

    <p>

        <label for="team_name">
            <strong>Team Name:</strong>
        </label>

        <br>

        <input
            type="text"
            name="team_name"
            id="team_name"
            maxlength="120"
            value="<?= htmlspecialchars(
                $team['team_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            required
        >

    </p>

    <p>

        <label for="team_category">
            <strong>Team Category:</strong>
        </label>

        <br>

        <select
            name="team_category"
            id="team_category"
            required
        >

            <?php
            $categories = [
                'OPEN',
                'MEN',
                'WOMEN',
                'MIXED'
            ];
            ?>

            <?php foreach ($categories as $category): ?>

                <option
                    value="<?= $category ?>"
                    <?= $team['team_category'] === $category
                        ? 'selected'
                        : '' ?>
                >
                    <?= $category ?>
                </option>

            <?php endforeach; ?>

        </select>

    </p>

    <p>

        <label for="coach_id">
            <strong>Coach:</strong>
        </label>

        <br>

        <select
            name="coach_id"
            id="coach_id"
        >

            <option value="">
                -- No Coach Assigned --
            </option>

            <?php foreach ($coaches as $coach): ?>

                <option
                    value="<?= (int) $coach['coach_id'] ?>"
                    <?= $team['coach_id'] !== null
                        && (int) $team['coach_id']
                            === (int) $coach['coach_id']
                        ? 'selected'
                        : '' ?>
                >

                    <?= htmlspecialchars(
                        $coach['full_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                    -

                    <?= htmlspecialchars(
                        $coach['specialization'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </p>

    <p>

        <label for="roster_limit">
            <strong>Roster Limit:</strong>
        </label>

        <br>

        <input
            type="number"
            name="roster_limit"
            id="roster_limit"
            min="1"
            max="500"
            value="<?= (int) $team['roster_limit'] ?>"
            required
        >

    </p>

    <p>

        <label for="team_status">
            <strong>Team Status:</strong>
        </label>

        <br>

        <select
            name="team_status"
            id="team_status"
            required
        >

            <option
                value="ACTIVE"
                <?= $team['team_status'] === 'ACTIVE'
                    ? 'selected'
                    : '' ?>
            >
                ACTIVE
            </option>

            <option
                value="INACTIVE"
                <?= $team['team_status'] === 'INACTIVE'
                    ? 'selected'
                    : '' ?>
            >
                INACTIVE
            </option>

        </select>

    </p>

    <button type="submit">
        Save Changes
    </button>

    <a
        href="manage-team.php?team_id=<?= (int) $teamId ?>"
    >
        Cancel
    </a>

</form>

</body>

</html>