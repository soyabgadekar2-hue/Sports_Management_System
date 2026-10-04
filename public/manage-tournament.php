<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$tournamentId = filter_input(INPUT_GET, 'tournament_id', FILTER_VALIDATE_INT);

if (!$tournamentId || $tournamentId <= 0) {
    http_response_code(400);
    exit('Invalid tournament ID.');
}

/* Tournament information */
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
    INNER JOIN sports s ON s.sport_id = t.sport_id
    LEFT JOIN venues v ON v.venue_id = t.venue_id
    WHERE t.tournament_id = :tournament_id
    LIMIT 1
");

$tournamentStmt->execute([':tournament_id' => $tournamentId]);
$tournament = $tournamentStmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    http_response_code(404);
    exit('Tournament not found.');
}

/* Registered teams */
$registeredStmt = $pdo->prepare("
    SELECT
        tt.team_id,
        tt.participation_status,
        tt.registered_at,
        tm.team_name,
        tm.team_category,
        s.sport_name,
        u.full_name AS coach_name
    FROM tournament_teams tt
    INNER JOIN teams tm ON tm.team_id = tt.team_id
    INNER JOIN sports s ON s.sport_id = tm.sport_id
    LEFT JOIN coach_profiles cp ON cp.coach_id = tm.coach_id
    LEFT JOIN users u ON u.user_id = cp.user_id
    WHERE tt.tournament_id = :tournament_id
    ORDER BY tt.participation_status ASC, tm.team_name ASC
");

$registeredStmt->execute([':tournament_id' => $tournamentId]);
$registeredTeams = $registeredStmt->fetchAll(PDO::FETCH_ASSOC);

/* Teams available for registration */
$eligibleStmt = $pdo->prepare("
    SELECT
        tm.team_id,
        tm.team_name,
        tm.team_category
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

$message = (string) ($_GET['message'] ?? '');
$error = (string) ($_GET['error'] ?? '');

$tournamentStatus = (string) $tournament['tournament_status'];
$tournamentFormat = (string) $tournament['tournament_format'];
$registrationOpen = $tournamentStatus === 'REGISTRATION_OPEN';
$canSchedule = $tournamentFormat === 'LEAGUE'
    && count(array_filter(
        $registeredTeams,
        static fn(array $team): bool =>
            $team['participation_status'] === 'ACTIVE'
    )) >= 2;

$statusClass = match ($tournamentStatus) {
    'REGISTRATION_OPEN' => 'open',
    'IN_PROGRESS' => 'progress',
    'COMPLETED' => 'completed',
    'CANCELLED' => 'cancelled',
    default => 'default'
};

$activeTeamCount = count(array_filter(
    $registeredTeams,
    static fn(array $team): bool =>
        $team['participation_status'] === 'ACTIVE'
));

$statusLabel = ucwords(strtolower(str_replace('_', ' ', $tournamentStatus)));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tournament | <?= e($tournament['tournament_name']) ?></title>

  <style>
    :root {
        --primary: #3155d9;
        --primary-dark: #2443b8;
        --primary-light: #edf1ff;
        --text: #182230;
        --muted: #687386;
        --border: #e4e8f0;
        --background: #f5f7fc;
        --success: #16804a;
        --success-bg: #eaf8f0;
        --danger: #b42318;
        --danger-bg: #fff0ef;
        --radius: 16px;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        color: var(--text);
        background: var(--background);
        font-family: Inter, "Segoe UI", Arial, sans-serif;
        line-height: 1.5;
    }

    a {
        color: inherit;
        text-decoration: none;
    }

    button,
    input,
    select,
    textarea {
        font: inherit;
    }

    /* Header */
    .topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 14px clamp(18px, 4vw, 50px);
        background: #fff;
        border-bottom: 1px solid var(--border);
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 11px;
    }

    .brand-mark {
        display: grid;
        place-items: center;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        color: #fff;
        background: linear-gradient(135deg, #4267f5, #2443b8);
        font-size: 20px;
        font-weight: 800;
    }

    .brand-name {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
    }

    .brand-caption {
        margin: 0;
        color: var(--muted);
        font-size: 12px;
    }

    .topbar-links {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
    }

    .topbar-links a {
        padding: 8px 11px;
        border-radius: 9px;
        color: #536075;
        font-size: 13px;
        font-weight: 650;
    }

    .topbar-links a:hover {
        background: var(--primary-light);
        color: var(--primary);
    }

    .topbar-links .logout-link {
        color: var(--danger);
        background: #fff1f0;
    }

    /* Page */
    .page-container {
        width: min(1150px, calc(100% - 32px));
        margin: 0 auto;
        padding: 28px 0 50px;
    }

    .breadcrumb {
        display: flex;
        flex-wrap: wrap;
        gap: 9px;
        margin-bottom: 20px;
        color: var(--muted);
        font-size: 13px;
    }

    .breadcrumb a {
        color: var(--primary);
        font-weight: 650;
    }

    .page-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
        margin-bottom: 22px;
    }

    .eyebrow {
        margin-bottom: 7px;
        color: var(--primary);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    h1 {
        margin: 0;
        font-size: clamp(26px, 4vw, 34px);
        line-height: 1.2;
        letter-spacing: -0.7px;
    }

    .page-description {
        margin: 8px 0 0;
        color: var(--muted);
        font-size: 14px;
    }

    .heading-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    /* Buttons */
    .button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 9px 13px;
        border: 1px solid transparent;
        border-radius: 10px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 750;
        transition: transform .15s ease, background .15s ease;
    }

    .button:hover {
        transform: translateY(-1px);
    }

    .button-primary {
        color: #fff;
        background: var(--primary);
    }

    .button-primary:hover {
        background: var(--primary-dark);
    }

    .button-secondary {
        color: #46546b;
        background: #fff;
        border-color: #d8deea;
    }

    .button-secondary:hover {
        background: #f7f8fc;
    }

    /* Notices */
    .notice {
        margin-bottom: 16px;
        padding: 13px 15px;
        border: 1px solid transparent;
        border-radius: 12px;
        font-size: 13px;
    }

    .notice-success {
        color: var(--success);
        background: var(--success-bg);
        border-color: #ccebd8;
    }

    .notice-error {
        color: var(--danger);
        background: var(--danger-bg);
        border-color: #f2c9c5;
    }

    .notice-info {
        color: #35508d;
        background: #edf3ff;
        border-color: #d8e4ff;
    }

    /* Summary cards */
    .summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .summary-item {
        min-width: 0;
        padding: 16px;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 13px;
    }

    .summary-label {
        margin: 0 0 5px;
        color: var(--muted);
        font-size: 11px;
        font-weight: 750;
        text-transform: uppercase;
    }

    .summary-value {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        overflow-wrap: anywhere;
    }

    /* Tournament status */
    .status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 9px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 800;
    }

    .status::before {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        content: "";
    }

    .status.open {
        color: var(--success);
        background: var(--success-bg);
    }

    .status.open::before {
        background: var(--success);
    }

    .status.progress {
        color: #3558b8;
        background: #edf1ff;
    }

    .status.progress::before {
        background: #4267f5;
    }

    .status.completed {
        color: #586579;
        background: #eef1f5;
    }

    .status.completed::before {
        background: #8994a5;
    }

    .status.cancelled {
        color: var(--danger);
        background: var(--danger-bg);
    }

    .status.cancelled::before {
        background: var(--danger);
    }

    .status.default {
        color: #8a5b14;
        background: #fff5e8;
    }

    .status.default::before {
        background: #c58a2c;
    }

    /* Main section alignment */
    .layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 320px;
        grid-template-areas:
            "details register"
            "teams tools";
        align-items: stretch;
        gap: 20px;
    }

    .main-column,
    .side-column {
        display: contents;
    }

    .main-column > .card:first-child {
        grid-area: details;
    }

    .main-column > .card:nth-child(2) {
        grid-area: teams;
    }

    .side-column > .card:first-child {
        grid-area: register;
    }

    .side-column > .card:nth-child(2) {
        grid-area: tools;
    }

    /* Cards */
    .card {
        width: 100%;
        min-width: 0;
        align-self: stretch;
        overflow: hidden;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: 0 8px 24px rgba(25, 42, 90, .045);
    }

    .card-heading {
        min-height: 78px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 18px 20px;
        border-bottom: 1px solid var(--border);
    }

    .card-heading h2 {
        margin: 0;
        font-size: 16px;
    }

    .card-heading p {
        margin: 5px 0 0;
        color: var(--muted);
        font-size: 12px;
    }

    .card-body {
        padding: 20px;
    }

    /* Tournament details */
    .details-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .detail-label {
        display: block;
        margin-bottom: 4px;
        color: var(--muted);
        font-size: 11px;
        font-weight: 750;
        text-transform: uppercase;
    }

    .detail-value {
        font-size: 13px;
        font-weight: 750;
        overflow-wrap: anywhere;
    }

    details.extra-details {
        margin-top: 18px;
        border-top: 1px solid var(--border);
        padding-top: 14px;
    }

    details summary {
        color: var(--primary);
        cursor: pointer;
        font-size: 13px;
        font-weight: 750;
    }

    .extra-content {
        padding-top: 14px;
    }

    .points-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .point {
        padding: 10px 12px;
        background: #f7f8fc;
        border-radius: 10px;
        font-size: 12px;
    }

    .point strong {
        display: block;
        font-size: 17px;
    }

    /* Registration form */
    .field-group {
        margin-bottom: 15px;
    }

    .field-group label {
        display: block;
        margin-bottom: 7px;
        font-size: 13px;
        font-weight: 750;
    }

    .field-control {
        width: 100%;
        min-height: 43px;
        padding: 10px 12px;
        border: 1px solid #d8deea;
        border-radius: 9px;
        background: #fff;
        outline: none;
    }

    .field-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(49, 85, 217, .1);
    }

    textarea.field-control {
        min-height: 85px;
        resize: vertical;
    }

    .field-help {
        display: block;
        margin-top: 6px;
        color: var(--muted);
        font-size: 11px;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
    }

    /* Registered teams */
    .team-list {
        display: grid;
        gap: 10px;
    }

    .team-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 13px;
        border: 1px solid var(--border);
        border-radius: 11px;
    }

    .team-item:hover {
        background: #fbfcff;
        border-color: #cbd5ff;
    }

    .team-name {
        margin: 0;
        font-size: 13px;
        font-weight: 800;
    }

    .team-meta {
        margin: 4px 0 0;
        color: var(--muted);
        font-size: 11px;
    }

    .team-status {
        display: inline-block;
        margin-top: 6px;
        padding: 3px 8px;
        border-radius: 20px;
        background: #eef1f5;
        font-size: 10px;
        font-weight: 800;
    }

    .team-status.active {
        color: var(--success);
        background: var(--success-bg);
    }

    .withdraw-button {
        padding: 7px 10px;
        border: 1px solid #f2c9c5;
        border-radius: 8px;
        color: var(--danger);
        background: #fff;
        cursor: pointer;
        font-size: 11px;
        font-weight: 750;
    }

    .withdraw-button:hover {
        background: #fff5f4;
    }

    /* Tournament tools */
    .action-list {
        display: grid;
        gap: 9px;
    }

    .action-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 12px 13px;
        border: 1px solid var(--border);
        border-radius: 10px;
        font-size: 12px;
        font-weight: 750;
    }

    .action-link:hover {
        color: var(--primary);
        background: #f7f8ff;
        border-color: #cbd5ff;
    }

    /* Empty state and footer */
    .empty-state {
        padding: 20px 10px;
        color: var(--muted);
        text-align: center;
        font-size: 13px;
    }

    .empty-state strong {
        display: block;
        margin-bottom: 5px;
        color: var(--text);
    }

    .page-footer {
        margin-top: 25px;
        color: #8791a2;
        text-align: center;
        font-size: 11px;
    }

    /* Tablet */
    @media (max-width: 900px) {
        .layout {
            grid-template-columns: minmax(0, 1fr);
            grid-template-areas:
                "details"
                "register"
                "teams"
                "tools";
        }

        .summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    /* Mobile */
    @media (max-width: 600px) {
        .topbar,
        .page-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        .topbar-links {
            width: 100%;
        }

        .page-container {
            width: calc(100% - 24px);
            padding-top: 20px;
        }

        .heading-actions {
            width: 100%;
        }

        .heading-actions .button {
            flex: 1;
        }

        .summary {
            gap: 8px;
        }

        .summary-item {
            padding: 12px;
        }

        .summary-value {
            font-size: 14px;
        }

        .details-grid {
            grid-template-columns: 1fr;
        }

        .card-heading,
        .card-body {
            padding: 16px;
        }

        .card-heading {
            min-height: auto;
        }

        .team-item {
            align-items: flex-start;
            flex-direction: column;
        }

        .withdraw-form,
        .withdraw-button {
            width: 100%;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            transition-duration: .01ms !important;
        }
    }
  </style>
</head>
<body>

<header class="topbar">
    <a class="brand" href="dashboard.php" aria-label="SportSync dashboard">
        <div class="brand-mark">S</div>
        <div>
            <p class="brand-name">SportSync</p>
            <p class="brand-caption">Sports Management System</p>
        </div>
    </a>

    <nav class="topbar-links" aria-label="Main navigation">
        <a href="dashboard.php">Dashboard</a>
        <a href="admin-tournaments.php">Tournaments</a>
        <a href="logout.php" class="logout-link">Logout</a>
    </nav>
</header>

<main class="page-container">
    <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        <span>/</span>
        <a href="admin-tournaments.php">Tournaments</a>
        <span>/</span>
        <span>Manage Tournament</span>
    </div>

    <section class="page-heading">
        <div>
            <div class="eyebrow">Tournament management</div>
            <h1><?= e($tournament['tournament_name']) ?></h1>
            <p class="page-description">
                Register teams and open tournament tools from one place.
            </p>
        </div>

        <div class="heading-actions">
            <?php if ($canSchedule): ?>
                <a class="button button-primary"
                   href="schedule-match.php?tournament_id=<?= (int) $tournamentId ?>">
                    Schedule Match
                </a>
            <?php endif; ?>

            <a class="button button-secondary" href="admin-tournaments.php">
                ← All Tournaments
            </a>
        </div>
    </section>

    <?php if ($message === 'team_registered'): ?>
        <div class="notice notice-success" role="status">
            Team registered successfully.
        </div>
    <?php elseif ($message === 'team_withdrawn'): ?>
        <div class="notice notice-success" role="status">
            Team withdrawn successfully.
        </div>
    <?php elseif ($message === 'match_scheduled'): ?>
        <div class="notice notice-success" role="status">
            Match scheduled successfully.
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice notice-error" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <section class="summary" aria-label="Tournament summary">
        <article class="summary-item">
            <p class="summary-label">Sport</p>
            <p class="summary-value"><?= e($tournament['sport_name']) ?></p>
        </article>

        <article class="summary-item">
            <p class="summary-label">Teams registered</p>
            <p class="summary-value"><?= $activeTeamCount ?></p>
        </article>

        <article class="summary-item">
            <p class="summary-label">Format</p>
            <p class="summary-value"><?= e($tournamentFormat) ?></p>
        </article>

        <article class="summary-item">
            <p class="summary-label">Status</p>
            <p class="summary-value">
                <span class="status <?= e($statusClass) ?>">
                    <?= e($statusLabel) ?>
                </span>
            </p>
        </article>
    </section>

    <div class="layout">
        <div class="main-column">

            <section class="card">
                <div class="card-heading">
                    <h2>Tournament details</h2>
                    <p>Key information and dates.</p>
                </div>

                <div class="card-body">
                    <div class="details-grid">
                        <div>
                            <span class="detail-label">Tournament name</span>
                            <span class="detail-value"><?= e($tournament['tournament_name']) ?></span>
                        </div>

                        <div>
                            <span class="detail-label">Venue</span>
                            <span class="detail-value"><?= e($tournament['venue_name'] ?? 'Not assigned') ?></span>
                        </div>

                        <div>
                            <span class="detail-label">Start date</span>
                            <span class="detail-value"><?= e($tournament['start_date']) ?></span>
                        </div>

                        <div>
                            <span class="detail-label">End date</span>
                            <span class="detail-value"><?= e($tournament['end_date']) ?></span>
                        </div>
                    </div>

                    <details class="extra-details">
                        <summary>View points and rules</summary>
                        <div class="extra-content">
                            <div class="points-grid">
                                <div class="point">
                                    Win
                                    <strong><?= e($tournament['points_win']) ?></strong>
                                </div>
                                <div class="point">
                                    Draw
                                    <strong><?= e($tournament['points_draw']) ?></strong>
                                </div>
                                <div class="point">
                                    Loss
                                    <strong><?= e($tournament['points_loss']) ?></strong>
                                </div>
                            </div>

                            <?php if (!empty($tournament['rules_information'])): ?>
                                <p><?= nl2br(e($tournament['rules_information'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
            </section>

            <section class="card">
                <div class="card-heading">
                    <h2>Registered teams</h2>
                    <p>
                        <?= $activeTeamCount ?> active team(s) in this tournament.
                    </p>
                </div>

                <div class="card-body">
                    <?php if (!$registeredTeams): ?>
                        <div class="empty-state">
                            <strong>No teams registered yet</strong>
                            Register an eligible team using the form.
                        </div>
                    <?php else: ?>
                        <div class="team-list">
                            <?php foreach ($registeredTeams as $team): ?>
                                <?php
                                $participationStatus = (string) $team['participation_status'];
                                $isActive = $participationStatus === 'ACTIVE';
                                ?>

                                <article class="team-item">
                                    <div>
                                        <p class="team-name"><?= e($team['team_name']) ?></p>
                                        <p class="team-meta">
                                            <?= e($team['sport_name']) ?> ·
                                            <?= e($team['team_category']) ?><br>
                                            Coach: <?= e($team['coach_name'] ?? 'Not assigned') ?><br>
                                            Registered: <?= e($team['registered_at']) ?>
                                        </p>
                                        <span class="team-status <?= $isActive ? 'active' : '' ?>">
                                            <?= e(ucwords(strtolower(str_replace('_', ' ', $participationStatus)))) ?>
                                        </span>
                                    </div>

                                    <?php if ($isActive && $registrationOpen): ?>
                                        <form
                                            class="withdraw-form"
                                            method="POST"
                                            action="withdraw-tournament-team.php"
                                            onsubmit="return confirm('Withdraw this team from the tournament?');"
                                        >
                                            <?= csrfField() ?>

                                            <input type="hidden" name="tournament_id"
                                                   value="<?= (int) $tournamentId ?>">
                                            <input type="hidden" name="team_id"
                                                   value="<?= (int) $team['team_id'] ?>">

                                            <button class="withdraw-button" type="submit">
                                                Withdraw
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <aside class="side-column">
            <section class="card">
                <div class="card-heading">
                    <h2>Register a team</h2>
                    <p>Choose an active team from this sport.</p>
                </div>

                <div class="card-body">
                    <?php if (!$registrationOpen): ?>
                        <div class="notice notice-info">
                            Registration is closed for this tournament.
                        </div>
                    <?php elseif (!$eligibleTeams): ?>
                        <div class="empty-state">
                            <strong>No eligible teams</strong>
                            All active teams may already be registered.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="register-tournament-team.php">
                            <?= csrfField() ?>

                            <input type="hidden" name="tournament_id"
                                   value="<?= (int) $tournamentId ?>">

                            <div class="field-group">
                                <label for="team_id">Select team</label>
                                <select class="field-control" name="team_id" id="team_id" required>
                                    <option value="">Choose a team</option>
                                    <?php foreach ($eligibleTeams as $team): ?>
                                        <option value="<?= (int) $team['team_id'] ?>">
                                            <?= e($team['team_name']) ?> — <?= e($team['team_category']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="field-help">
                                    Only active teams from this sport are listed.
                                </small>
                            </div>

                            <div class="field-group">
                                <label for="remarks">Remarks (optional)</label>
                                <textarea
                                    class="field-control"
                                    name="remarks"
                                    id="remarks"
                                    rows="3"
                                    maxlength="500"
                                    placeholder="Add a note if needed"
                                ></textarea>
                            </div>

                            <div class="form-actions">
                                <button class="button button-primary" type="submit">
                                    + Register Team
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <div class="card-heading">
                    <h2>Tournament tools</h2>
                    <p>Open these tools when needed.</p>
                </div>

                <div class="card-body">
                    <div class="action-list">
                        <?php if ($canSchedule): ?>
                            <a class="action-link"
                               href="schedule-match.php?tournament_id=<?= (int) $tournamentId ?>">
                                Schedule match <span>→</span>
                            </a>
                            <a class="action-link"
                               href="match-results.php?tournament_id=<?= (int) $tournamentId ?>">
                                Match results <span>→</span>
                            </a>
                            <a class="action-link"
                               href="tournament-standings.php?tournament_id=<?= (int) $tournamentId ?>">
                                Tournament standings <span>→</span>
                            </a>
                        <?php else: ?>
                            <div class="notice notice-info">
                                Match scheduling is available for league tournaments
                                after at least two active teams are registered.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <footer class="page-footer">
        SportSync · Sports Management System
    </footer>
</main>

</body>
</html>