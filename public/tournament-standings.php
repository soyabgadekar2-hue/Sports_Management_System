<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

function standingsEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$tournamentId = filter_input(
    INPUT_GET,
    'tournament_id',
    FILTER_VALIDATE_INT
);

$message = $_GET['message'] ?? '';

$tournament = null;
$standings = [];
$tournaments = [];

/*
|--------------------------------------------------------------------------
| If no tournament ID is provided, show the tournament list
|--------------------------------------------------------------------------
*/

if (!$tournamentId) {
    $tournamentsStmt = $pdo->query("
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
        ORDER BY
            t.start_date DESC,
            t.tournament_name ASC
    ");

    $tournaments = $tournamentsStmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| If a tournament ID is provided, load that tournament and its standings
|--------------------------------------------------------------------------
*/

if ($tournamentId) {
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
        'tournament_id' => $tournamentId,
    ]);

    $tournament = $tournamentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$tournament) {
        http_response_code(404);
        exit('Tournament not found.');
    }

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
        ORDER BY ts.standing_rank ASC
    ");

    $standingsStmt->execute([
        'tournament_id' => $tournamentId,
    ]);

    $standings = $standingsStmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| Shared SportSync header
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';

?>

<style>
    .standings-page {
        width: 100%;
        max-width: 1400px;
        margin: 0 auto;
        padding: 24px;
        box-sizing: border-box;
    }

    .standings-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 18px;
        margin-bottom: 24px;
    }

    .standings-heading h1 {
        margin: 0 0 8px;
        font-size: clamp(24px, 3vw, 32px);
        font-weight: 800;
    }

    .standings-heading p {
        margin: 0;
        color: #64748b;
        line-height: 1.6;
    }

    .standings-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 22px;
        margin-bottom: 22px;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
    }

    .standings-card h2 {
        margin: 0 0 18px;
        font-size: 20px;
        font-weight: 750;
    }

    .standings-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }

    .standings-info-item {
        padding: 14px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        min-width: 0;
    }

    .standings-info-label {
        display: block;
        margin-bottom: 6px;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .standings-info-value {
        display: block;
        color: #0f172a;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .standings-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin-bottom: 18px;
    }

    .standings-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 42px;
        padding: 10px 16px;
        border: 1px solid #2563eb;
        border-radius: 10px;
        background: #2563eb;
        color: #ffffff;
        text-decoration: none;
        font-size: 14px;
        font-weight: 700;
        transition: transform 0.2s ease, background 0.2s ease;
    }

    .standings-button:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
    }

    .standings-button.secondary {
        color: #1d4ed8;
        background: #ffffff;
        border-color: #cbd5e1;
    }

    .standings-button.secondary:hover {
        background: #eff6ff;
    }

    .standings-table-wrapper {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }

    .standings-table {
        width: 100%;
        min-width: 850px;
        border-collapse: collapse;
        background: #ffffff;
    }

    .standings-table th,
    .standings-table td {
        padding: 14px 12px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .standings-table th {
        background: #f8fafc;
        color: #475569;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .standings-table td {
        color: #334155;
        font-size: 14px;
    }

    .standings-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .standings-table tbody tr:hover {
        background: #f8fafc;
    }

    .standings-rank {
        font-weight: 800;
        color: #1d4ed8 !important;
    }

    .standings-team {
        font-weight: 750;
        color: #0f172a !important;
    }

    .standings-points {
        font-weight: 800;
        color: #1d4ed8 !important;
    }

    .standings-status {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 750;
    }

    .standings-empty {
        padding: 30px 20px;
        text-align: center;
        color: #64748b;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 12px;
    }

    .standings-empty strong {
        display: block;
        margin-bottom: 8px;
        color: #0f172a;
        font-size: 16px;
    }

    .tournament-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .tournament-list-card {
        display: flex;
        flex-direction: column;
        gap: 14px;
        padding: 20px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        transition: border-color 0.2s ease, transform 0.2s ease;
    }

    .tournament-list-card:hover {
        border-color: #93c5fd;
        transform: translateY(-2px);
    }

    .tournament-list-card h3 {
        margin: 0 0 8px;
        color: #0f172a;
        font-size: 17px;
    }

    .tournament-list-card p {
        margin: 5px 0;
        color: #64748b;
        font-size: 14px;
        line-height: 1.5;
    }

    .tournament-list-card .standings-button {
        align-self: flex-start;
        margin-top: auto;
    }

    .standings-notice {
        padding: 14px 16px;
        margin-bottom: 20px;
        border: 1px solid #bbf7d0;
        border-radius: 10px;
        background: #f0fdf4;
        color: #166534;
        font-size: 14px;
    }

    @media (max-width: 900px) {
        .standings-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .tournament-list {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 600px) {
        .standings-page {
            padding: 16px;
        }

        .standings-card {
            padding: 16px;
        }

        .standings-grid {
            grid-template-columns: 1fr;
        }

        .standings-actions {
            align-items: stretch;
        }

        .standings-actions .standings-button {
            width: 100%;
            box-sizing: border-box;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .standings-button,
        .tournament-list-card {
            transition: none;
        }
    }
</style>

<div class="standings-page">

    <?php if ($message === 'calculated'): ?>
        <div class="standings-notice">
            Tournament standings calculated successfully.
        </div>
    <?php endif; ?>

    <?php if (!$tournamentId): ?>

        <div class="standings-heading">
            <div>
                <h1>Tournament Standings</h1>
                <p>
                    Select a tournament to view its league table and team performance.
                </p>
            </div>
        </div>

        <?php if (!$tournaments): ?>

            <div class="standings-card">
                <div class="standings-empty">
                    <strong>No tournaments found</strong>
                    There are currently no tournaments to display.
                </div>
            </div>

        <?php else: ?>

            <div class="tournament-list">

                <?php foreach ($tournaments as $item): ?>

                    <article class="tournament-list-card">

                        <div>
                            <h3>
                                <?= standingsEscape($item['tournament_name']) ?>
                            </h3>

                            <p>
                                <strong>Sport:</strong>
                                <?= standingsEscape($item['sport_name']) ?>
                            </p>

                            <p>
                                <strong>Dates:</strong>
                                <?= standingsEscape($item['start_date']) ?>
                                –
                                <?= standingsEscape($item['end_date']) ?>
                            </p>

                            <p>
                                <strong>Status:</strong>
                                <span class="standings-status">
                                    <?= standingsEscape($item['tournament_status']) ?>
                                </span>
                            </p>
                        </div>

                        <a
                            class="standings-button"
                            href="tournament-standings.php?tournament_id=<?= (int) $item['tournament_id'] ?>"
                        >
                            View standings →
                        </a>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    <?php else: ?>

        <div class="standings-heading">

            <div>
                <h1>Tournament Standings</h1>
                <p>
                    View tournament details, team records, and points.
                </p>
            </div>

            <div class="standings-actions">
                <a
                    class="standings-button secondary"
                    href="tournament-standings.php"
                >
                    ← All tournaments
                </a>

                <a
                    class="standings-button secondary"
                    href="manage-tournament.php?tournament_id=<?= (int) $tournamentId ?>"
                >
                    Manage tournament
                </a>
            </div>

        </div>

        <section class="standings-card">

            <h2>Tournament Information</h2>

            <div class="standings-grid">

                <div class="standings-info-item">
                    <span class="standings-info-label">Tournament</span>
                    <span class="standings-info-value">
                        <?= standingsEscape($tournament['tournament_name']) ?>
                    </span>
                </div>

                <div class="standings-info-item">
                    <span class="standings-info-label">Sport</span>
                    <span class="standings-info-value">
                        <?= standingsEscape($tournament['sport_name']) ?>
                    </span>
                </div>

                <div class="standings-info-item">
                    <span class="standings-info-label">Status</span>
                    <span class="standings-info-value">
                        <?= standingsEscape($tournament['tournament_status']) ?>
                    </span>
                </div>

                <div class="standings-info-item">
                    <span class="standings-info-label">Start date</span>
                    <span class="standings-info-value">
                        <?= standingsEscape($tournament['start_date']) ?>
                    </span>
                </div>

                <div class="standings-info-item">
                    <span class="standings-info-label">End date</span>
                    <span class="standings-info-value">
                        <?= standingsEscape($tournament['end_date']) ?>
                    </span>
                </div>

                <div class="standings-info-item">
                    <span class="standings-info-label">Points system</span>
                    <span class="standings-info-value">
                        Win: <?= standingsEscape($tournament['points_win']) ?>
                        · Draw: <?= standingsEscape($tournament['points_draw']) ?>
                        · Loss: <?= standingsEscape($tournament['points_loss']) ?>
                    </span>
                </div>

            </div>

        </section>

        <section class="standings-card">

            <div class="standings-heading">
                <div>
                    <h2>League Table</h2>
                    <p>
                        Team rankings are displayed using the calculated standings.
                    </p>
                </div>
            </div>

            <div class="standings-actions">

                <a
                    class="standings-button"
                    href="calculate-standings.php?tournament_id=<?= (int) $tournamentId ?>"
                >
                    <?= $standings ? '↻ Recalculate standings' : 'Calculate standings' ?>
                </a>

            </div>

            <?php if (!$standings): ?>

                <div class="standings-empty">
                    <strong>No standings calculated yet</strong>
                    Calculate the standings to display the teams’ match records and points.
                </div>

            <?php else: ?>

                <div class="standings-table-wrapper">

                    <table class="standings-table">

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
                                <th>Difference</th>
                                <th>Points</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($standings as $standing): ?>

                                <tr>

                                    <td class="standings-rank">
                                        <?= (int) $standing['standing_rank'] ?>
                                    </td>

                                    <td class="standings-team">
                                        <?= standingsEscape($standing['team_name']) ?>
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
                                        <?= number_format((float) $standing['score_for'], 2) ?>
                                    </td>

                                    <td>
                                        <?= number_format((float) $standing['score_against'], 2) ?>
                                    </td>

                                    <td>
                                        <?= number_format((float) $standing['score_difference'], 2) ?>
                                    </td>

                                    <td class="standings-points">
                                        <?= number_format((float) $standing['points'], 2) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>