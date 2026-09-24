<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$matchId = filter_input(INPUT_GET, 'match_id', FILTER_VALIDATE_INT);

if (!$matchId) {
    exit('Invalid match ID.');
}

$db = db();


/*
|--------------------------------------------------------------------------
| Fetch Match
|--------------------------------------------------------------------------
*/
$matchStmt = $db->prepare("
    SELECT
        m.match_id,
        m.match_number,
        m.match_status,
        m.scheduled_start,
        m.scheduled_end,

        t.tournament_id,
        t.tournament_name,

        ta.team_id AS team_a_id,
        ta.team_name AS team_a_name,

        tb.team_id AS team_b_id,
        tb.team_name AS team_b_name,

        s.sport_name,

        v.venue_name

    FROM matches m

    INNER JOIN tournaments t
        ON t.tournament_id = m.tournament_id

    INNER JOIN teams ta
        ON ta.team_id = m.team_a_id

    INNER JOIN teams tb
        ON tb.team_id = m.team_b_id

    INNER JOIN sports s
        ON s.sport_id = t.sport_id

    INNER JOIN venues v
        ON v.venue_id = m.venue_id

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


/*
|--------------------------------------------------------------------------
| Fetch Participating Players
|--------------------------------------------------------------------------
*/
$playerStmt = $db->prepare("
    SELECT
        pmp.match_participation_id,
        pmp.player_id,
        pmp.team_id,

        pp.student_id,
        pp.department,
        pp.course,

        u.full_name,

        tm.team_name

    FROM player_match_participation pmp

    INNER JOIN player_profiles pp
        ON pp.player_id = pmp.player_id

    INNER JOIN users u
        ON u.user_id = pp.user_id

    INNER JOIN teams tm
        ON tm.team_id = pmp.team_id

    WHERE pmp.match_id = :match_id
      AND pp.player_status = 'ACTIVE'
      AND u.account_status = 'APPROVED'

    ORDER BY tm.team_name ASC, u.full_name ASC
");

$playerStmt->execute([
    ':match_id' => $matchId
]);

$players = $playerStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Fetch Existing Statistics
|--------------------------------------------------------------------------
*/
$existingStats = [];

if (!empty($players)) {

    $participationIds = array_column(
        $players,
        'match_participation_id'
    );

    $placeholders = implode(
        ',',
        array_fill(0, count($participationIds), '?')
    );

    $statsStmt = $db->prepare("
        SELECT
            player_match_statistic_id,
            match_participation_id,
            metric_code,
            metric_value

        FROM player_match_statistics

        WHERE match_participation_id IN ($placeholders)

        ORDER BY player_match_statistic_id ASC
    ");

    $statsStmt->execute($participationIds);

    while ($stat = $statsStmt->fetch(PDO::FETCH_ASSOC)) {

        $participationId =
            (int) $stat['match_participation_id'];

        $existingStats[$participationId][] = $stat;
    }
}


/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
|
| The project's csrf.php defines csrfToken().
|
*/
$csrfToken = csrfToken();

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
        Player Match Statistics
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #222;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .header {
            background: #ffffff;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .header h1 {
            margin: 0 0 10px;
        }

        .match-info {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(200px, 1fr)
            );
            gap: 15px;
            margin-top: 20px;
        }

        .info-box {
            background: #f7f8fa;
            padding: 15px;
            border-radius: 8px;
        }

        .info-box strong {
            display: block;
            margin-bottom: 5px;
        }

        .players-container {
            background: #ffffff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .player-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .player-header {
            background: #f1f3f5;
            padding: 15px;
        }

        .player-header h3 {
            margin: 0 0 5px;
        }

        .player-details {
            color: #555;
            font-size: 14px;
        }

        .statistics {
            padding: 15px;
        }

        .stat-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }

        .stat-row input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }

        .metric-code {
            flex: 2;
        }

        .metric-value {
            flex: 1;
        }

        .remove-stat {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 14px;
            border-radius: 6px;
            cursor: pointer;
        }

        .remove-stat:hover {
            background: #bb2d3b;
        }

        .add-stat {
            background: #198754;
            color: white;
            border: none;
            padding: 9px 14px;
            border-radius: 6px;
            cursor: pointer;
        }

        .add-stat:hover {
            background: #157347;
        }

        .save-button {
            width: 100%;
            background: #0d6efd;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 7px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 15px;
        }

        .save-button:hover {
            background: #0b5ed7;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #666;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 15px;
            text-decoration: none;
            color: #0d6efd;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 13px;
            background: #e9ecef;
        }

        @media (max-width: 600px) {

            body {
                padding: 15px;
            }

            .stat-row {
                flex-direction: column;
                align-items: stretch;
            }

            .metric-code,
            .metric-value {
                width: 100%;
            }

            .remove-stat {
                width: 100%;
            }

        }

    </style>

</head>


<body>

<div class="container">


    <!-- Back Link -->

    <a
        href="match-players.php?match_id=<?= (int) $matchId ?>"
        class="back-link"
    >
        ← Back to Match Players
    </a>


    <!-- Match Header -->

    <div class="header">

        <h1>
            Player Match Statistics
        </h1>

        <p>
            <?= htmlspecialchars(
                $match['tournament_name']
            ) ?>
        </p>


        <div class="match-info">


            <!-- Match -->

            <div class="info-box">

                <strong>
                    Match
                </strong>

                #<?= (int) $match['match_number'] ?>

            </div>


            <!-- Sport -->

            <div class="info-box">

                <strong>
                    Sport
                </strong>

                <?= htmlspecialchars(
                    $match['sport_name']
                ) ?>

            </div>


            <!-- Teams -->

            <div class="info-box">

                <strong>
                    Teams
                </strong>

                <?= htmlspecialchars(
                    $match['team_a_name']
                ) ?>

                vs

                <?= htmlspecialchars(
                    $match['team_b_name']
                ) ?>

            </div>


            <!-- Status -->

            <div class="info-box">

                <strong>
                    Status
                </strong>

                <span class="status">

                    <?= htmlspecialchars(
                        $match['match_status']
                    ) ?>

                </span>

            </div>


            <!-- Venue -->

            <div class="info-box">

                <strong>
                    Venue
                </strong>

                <?= htmlspecialchars(
                    $match['venue_name']
                ) ?>

            </div>


            <!-- Date -->

            <div class="info-box">

                <strong>
                    Date
                </strong>

                <?= date(
                    'd M Y, h:i A',
                    strtotime(
                        $match['scheduled_start']
                    )
                ) ?>

            </div>


        </div>

    </div>


    <!-- Players -->

    <div class="players-container">

        <h2>
            Participating Players
        </h2>


        <?php if (empty($players)): ?>


            <div class="empty">

                No participating players found
                for this match.

            </div>


        <?php else: ?>


            <form
                method="POST"
                action="save-player-statistics.php"
            >


                <!-- CSRF Token -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrfToken,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >


                <!-- Match ID -->

                <input
                    type="hidden"
                    name="match_id"
                    value="<?= (int) $matchId ?>"
                >


                <?php foreach ($players as $player): ?>


                    <?php

                    $participationId =
                        (int) $player[
                            'match_participation_id'
                        ];

                    $playerStats =
                        $existingStats[
                            $participationId
                        ] ?? [];


                    /*
                     * If no statistics exist yet,
                     * create one empty row.
                     */

                    if (empty($playerStats)) {

                        $playerStats = [

                            [
                                'metric_code' => '',
                                'metric_value' => '0'
                            ]

                        ];

                    }

                    ?>


                    <!-- Player Card -->

                    <div class="player-card">


                        <!-- Player Header -->

                        <div class="player-header">


                            <h3>

                                <?= htmlspecialchars(
                                    $player['full_name']
                                ) ?>

                            </h3>


                            <div class="player-details">

                                Student ID:

                                <?= htmlspecialchars(
                                    $player['student_id']
                                ) ?>

                                |

                                Team:

                                <?= htmlspecialchars(
                                    $player['team_name']
                                ) ?>

                                |

                                <?= htmlspecialchars(
                                    $player['course']
                                ) ?>

                                |

                                <?= htmlspecialchars(
                                    $player['department']
                                ) ?>

                            </div>


                        </div>


                        <!-- Statistics -->

                        <div
                            class="statistics"
                            data-participation-id="<?=
                                $participationId
                            ?>"
                        >


                            <?php foreach (
                                $playerStats
                                as $stat
                            ): ?>


                                <div class="stat-row">


                                    <!-- Metric Code -->

                                    <input
                                        type="text"
                                        name="stats[
                                            <?= $participationId ?>
                                        ][metric_code][]"
                                        class="metric-code"
                                        placeholder="Metric code e.g. goals"
                                        value="<?= htmlspecialchars(
                                            $stat['metric_code'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        maxlength="50"
                                    >


                                    <!-- Metric Value -->

                                    <input
                                        type="number"
                                        step="0.01"
                                        name="stats[
                                            <?= $participationId ?>
                                        ][metric_value][]"
                                        class="metric-value"
                                        value="<?= htmlspecialchars(
                                            $stat['metric_value'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >


                                    <!-- Remove -->

                                    <button
                                        type="button"
                                        class="remove-stat"
                                        onclick="removeStat(this)"
                                    >
                                        Remove
                                    </button>


                                </div>


                            <?php endforeach; ?>


                            <!-- Add Statistic -->

                            <button
                                type="button"
                                class="add-stat"
                                onclick="addStat(this)"
                            >
                                + Add Statistic
                            </button>


                        </div>


                    </div>


                <?php endforeach; ?>


                <!-- Save -->

                <button
                    type="submit"
                    class="save-button"
                >
                    Save Player Statistics
                </button>


            </form>


        <?php endif; ?>


    </div>


</div>


<script>


/*
|--------------------------------------------------------------------------
| Add Statistic
|--------------------------------------------------------------------------
*/

function addStat(button) {

    const container =
        button.parentElement;

    const participationId =
        container.dataset.participationId;

    const row =
        document.createElement('div');

    row.className = 'stat-row';


    row.innerHTML = `

        <input
            type="text"
            name="stats[${participationId}][metric_code][]"
            class="metric-code"
            placeholder="Metric code e.g. goals"
            maxlength="50"
        >

        <input
            type="number"
            step="0.01"
            name="stats[${participationId}][metric_value][]"
            class="metric-value"
            value="0"
        >

        <button
            type="button"
            class="remove-stat"
            onclick="removeStat(this)"
        >
            Remove
        </button>

    `;


    container.insertBefore(
        row,
        button
    );
}


/*
|--------------------------------------------------------------------------
| Remove Statistic
|--------------------------------------------------------------------------
*/

function removeStat(button) {

    const row =
        button.parentElement;

    const container =
        row.parentElement;

    const rows =
        container.querySelectorAll(
            '.stat-row'
        );


    /*
     * Keep at least one row.
     */

    if (rows.length <= 1) {

        const metricCode =
            row.querySelector(
                '.metric-code'
            );

        const metricValue =
            row.querySelector(
                '.metric-value'
            );


        if (metricCode) {
            metricCode.value = '';
        }

        if (metricValue) {
            metricValue.value = '0';
        }

        return;
    }


    row.remove();
}


</script>


</body>

</html>