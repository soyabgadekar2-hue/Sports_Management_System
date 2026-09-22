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
| Get Match Information
|--------------------------------------------------------------------------
*/
$matchStmt = $pdo->prepare(
    "
    SELECT
        m.match_id,
        m.match_number,
        m.tournament_id,
        m.team_a_id,
        m.team_b_id,
        m.scheduled_start,
        m.match_status,
        t.tournament_name,
        team_a.team_name AS team_a_name,
        team_b.team_name AS team_b_name
    FROM matches m

    INNER JOIN tournaments t
        ON t.tournament_id = m.tournament_id

    INNER JOIN teams team_a
        ON team_a.team_id = m.team_a_id

    INNER JOIN teams team_b
        ON team_b.team_id = m.team_b_id

    WHERE m.match_id = :match_id

    LIMIT 1
    "
);

$matchStmt->execute([
    ':match_id' => $matchId
]);

$match = $matchStmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    exit('Match not found.');
}

/*
|--------------------------------------------------------------------------
| Get Team A Players
|--------------------------------------------------------------------------
|
| Only players who satisfy ALL conditions are displayed:
|
| 1. Active team membership
| 2. No left_at date
| 3. Active player profile
| 4. Approved user account
|
*/
$teamAPlayersStmt = $pdo->prepare(
    "
    SELECT
        tp.team_player_id,
        p.player_id,
        u.full_name,
        p.student_id,
        p.department,
        p.course,
        p.player_status,
        u.account_status

    FROM team_players tp

    INNER JOIN player_profiles p
        ON p.player_id = tp.player_id

    INNER JOIN users u
        ON u.user_id = p.user_id

    WHERE
        tp.team_id = :team_id
        AND tp.membership_status = 'ACTIVE'
        AND tp.left_at IS NULL
        AND p.player_status = 'ACTIVE'
        AND u.account_status = 'APPROVED'

    ORDER BY u.full_name ASC
    "
);

$teamAPlayersStmt->execute([
    ':team_id' => (int) $match['team_a_id']
]);

$teamAPlayers = $teamAPlayersStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Get Team B Players
|--------------------------------------------------------------------------
*/
$teamBPlayersStmt = $pdo->prepare(
    "
    SELECT
        tp.team_player_id,
        p.player_id,
        u.full_name,
        p.student_id,
        p.department,
        p.course,
        p.player_status,
        u.account_status

    FROM team_players tp

    INNER JOIN player_profiles p
        ON p.player_id = tp.player_id

    INNER JOIN users u
        ON u.user_id = p.user_id

    WHERE
        tp.team_id = :team_id
        AND tp.membership_status = 'ACTIVE'
        AND tp.left_at IS NULL
        AND p.player_status = 'ACTIVE'
        AND u.account_status = 'APPROVED'

    ORDER BY u.full_name ASC
    "
);

$teamBPlayersStmt->execute([
    ':team_id' => (int) $match['team_b_id']
]);

$teamBPlayers = $teamBPlayersStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Extra server-side status filtering
|--------------------------------------------------------------------------
|
| This provides an additional protection layer before displaying players.
|
*/
$teamAPlayers = array_values(
    array_filter(
        $teamAPlayers,
        static function (array $player): bool {
            return
                strtoupper((string) ($player['player_status'] ?? '')) === 'ACTIVE'
                &&
                strtoupper((string) ($player['account_status'] ?? '')) === 'APPROVED';
        }
    )
);

$teamBPlayers = array_values(
    array_filter(
        $teamBPlayers,
        static function (array $player): bool {
            return
                strtoupper((string) ($player['player_status'] ?? '')) === 'ACTIVE'
                &&
                strtoupper((string) ($player['account_status'] ?? '')) === 'APPROVED';
        }
    )
);

/*
|--------------------------------------------------------------------------
| Get Existing Participation
|--------------------------------------------------------------------------
*/
$participationStmt = $pdo->prepare(
    "
    SELECT player_id
    FROM player_match_participation
    WHERE match_id = :match_id
    "
);

$participationStmt->execute([
    ':match_id' => $matchId
]);

$participatingPlayerIds = [];

while ($row = $participationStmt->fetch(PDO::FETCH_ASSOC)) {
    $participatingPlayerIds[] = (int) $row['player_id'];
}

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <title>
        Match Players -
        <?= htmlspecialchars(
            $match['tournament_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </title>
</head>

<body>

<h1>Sports Management System</h1>

<h2>Match Players</h2>

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

<?php if ($message === 'participation_saved'): ?>

    <p style="color: green;">
        Match participation saved successfully.
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

    <tr>

        <th>Status</th>

        <td>
            <?= htmlspecialchars(
                $match['match_status'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </td>

    </tr>

</table>

<hr>

<form
    method="POST"
    action="save-match-participation.php"
>

    <?= csrfField() ?>

    <input
        type="hidden"
        name="match_id"
        value="<?= (int) $matchId ?>"
    >

    <h3>

        <?= htmlspecialchars(
            $match['team_a_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>

        - Players

    </h3>

    <?php if (!$teamAPlayers): ?>

        <p>
            No approved active players found for this team.
        </p>

    <?php else: ?>

        <table
            border="1"
            cellpadding="8"
            cellspacing="0"
        >

            <thead>

                <tr>

                    <th>Select</th>
                    <th>Player Name</th>
                    <th>Student ID</th>
                    <th>Department</th>
                    <th>Course</th>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($teamAPlayers as $player): ?>

                <tr>

                    <td>

                        <input
                            type="checkbox"
                            name="player_ids[]"
                            value="<?= (int) $player['player_id'] ?>"
                            <?= in_array(
                                (int) $player['player_id'],
                                $participatingPlayerIds,
                                true
                            ) ? 'checked' : '' ?>
                        >

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $player['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $player['student_id'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $player['department'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $player['course'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

    <hr>

    <h3>

        <?= htmlspecialchars(
            $match['team_b_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>

        - Players

    </h3>

    <?php if (!$teamBPlayers): ?>

        <p>
            No approved active players found for this team.
        </p>

    <?php else: ?>

        <table
            border="1"
            cellpadding="8"
            cellspacing="0"
        >

            <thead>

                <tr>

                    <th>Select</th>
                    <th>Player Name</th>
                    <th>Student ID</th>
                    <th>Department</th>
                    <th>Course</th>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($teamBPlayers as $player): ?>

                <tr>

                    <td>

                        <input
                            type="checkbox"
                            name="player_ids[]"
                            value="<?= (int) $player['player_id'] ?>"
                            <?= in_array(
                                (int) $player['player_id'],
                                $participatingPlayerIds,
                                true
                            ) ? 'checked' : '' ?>
                        >

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $player['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $player['student_id'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $player['department'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $player['course'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

    <br>

    <button type="submit">
        Save Match Participation
    </button>

</form>

</body>

</html>