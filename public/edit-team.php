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

/*
|--------------------------------------------------------------------------
| Page helpers
|--------------------------------------------------------------------------
*/

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$teamName = (string) $team['team_name'];
$sportName = (string) $team['sport_name'];
$teamCategory = (string) $team['team_category'];
$teamStatus = (string) $team['team_status'];
$rosterLimit = (int) $team['roster_limit'];

$statusClass = $teamStatus === 'ACTIVE'
    ? 'status-active'
    : 'status-inactive';

$categories = [
    'OPEN',
    'MEN',
    'WOMEN',
    'MIXED'
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Edit Team | SportSync</title>

    <style>
        :root {
            --primary: #3155d9;
            --primary-dark: #2443b8;
            --primary-light: #edf1ff;
            --text: #182230;
            --muted: #687386;
            --border: #e4e8f0;
            --surface: #ffffff;
            --background: #f5f7fc;
            --success: #16804a;
            --success-bg: #eaf8f0;
            --warning: #a85d12;
            --warning-bg: #fff5e8;
            --danger: #b42318;
            --danger-bg: #fff0ef;
            --shadow: 0 12px 35px rgba(25, 42, 90, 0.07);
            --radius: 18px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family:
                Inter,
                "Segoe UI",
                Roboto,
                Arial,
                sans-serif;
            line-height: 1.5;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select {
            font: inherit;
        }

        .app-shell {
            min-height: 100vh;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 16px clamp(18px, 4vw, 54px);
            background: rgba(255, 255, 255, 0.94);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(12px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 43px;
            height: 43px;
            flex: 0 0 43px;
            border-radius: 13px;
            color: #fff;
            background: linear-gradient(135deg, #4267f5, #2443b8);
            box-shadow: 0 7px 16px rgba(49, 85, 217, 0.24);
            font-size: 21px;
            font-weight: 800;
        }

        .brand-name {
            margin: 0;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .brand-caption {
            margin: 1px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .topbar-links {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 8px;
        }

        .topbar-links a {
            padding: 9px 13px;
            border-radius: 10px;
            color: #536075;
            font-size: 13px;
            font-weight: 650;
            transition:
                background 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease;
        }

        .topbar-links a:hover {
            color: var(--primary);
            background: var(--primary-light);
            transform: translateY(-1px);
        }

        .topbar-links .logout-link {
            color: #b42318;
            background: #fff1f0;
        }

        .page-container {
            width: min(1120px, calc(100% - 36px));
            margin: 0 auto;
            padding: 32px 0 56px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 9px;
            margin-bottom: 22px;
            color: var(--muted);
            font-size: 13px;
        }

        .breadcrumb a {
            color: var(--primary);
            font-weight: 650;
        }

        .breadcrumb .separator {
            color: #a3adbd;
        }

        .page-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 25px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 9px;
            color: var(--primary);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .eyebrow-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--primary);
        }

        h1 {
            margin: 0;
            font-size: clamp(27px, 4vw, 36px);
            line-height: 1.15;
            letter-spacing: -1.1px;
        }

        .page-description {
            max-width: 620px;
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 15px;
        }

        .team-summary {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 235px;
            padding: 15px 17px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: 0 5px 18px rgba(25, 42, 90, 0.035);
        }

        .team-summary-icon {
            display: grid;
            place-items: center;
            width: 45px;
            height: 45px;
            flex: 0 0 45px;
            border-radius: 13px;
            color: var(--primary);
            background: var(--primary-light);
            font-size: 21px;
        }

        .summary-label {
            margin: 0 0 3px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 750;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .summary-name {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .summary-sport {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .notice {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            margin-bottom: 22px;
            padding: 14px 16px;
            border: 1px solid transparent;
            border-radius: 13px;
            font-size: 14px;
        }

        .notice-icon {
            display: grid;
            place-items: center;
            width: 23px;
            height: 23px;
            flex: 0 0 23px;
            border-radius: 50%;
            font-size: 13px;
            font-weight: 800;
        }

        .notice-success {
            color: var(--success);
            background: var(--success-bg);
            border-color: #ccebd8;
        }

        .notice-success .notice-icon {
            color: white;
            background: var(--success);
        }

        .notice-info {
            color: #35508d;
            background: #edf3ff;
            border-color: #d8e4ff;
        }

        .notice-info .notice-icon {
            color: white;
            background: #4267c9;
        }

        .edit-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            align-items: start;
            gap: 23px;
        }

        .form-card,
        .side-card {
            overflow: hidden;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .card-heading {
            padding: 23px 25px 20px;
            border-bottom: 1px solid var(--border);
        }

        .card-heading h2 {
            margin: 0;
            font-size: 18px;
            letter-spacing: -0.3px;
        }

        .card-heading p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .form-body {
            padding: 25px;
        }

        .form-section + .form-section {
            margin-top: 27px;
            padding-top: 25px;
            border-top: 1px solid var(--border);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 19px;
            font-size: 14px;
            font-weight: 800;
        }

        .section-number {
            display: grid;
            place-items: center;
            width: 27px;
            height: 27px;
            border-radius: 9px;
            color: var(--primary);
            background: var(--primary-light);
            font-size: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 19px 17px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #303c50;
            font-size: 13px;
            font-weight: 750;
        }

        .required {
            color: #d92d20;
        }

        .field-control {
            display: block;
            width: 100%;
            min-height: 47px;
            padding: 11px 13px;
            color: var(--text);
            background: #fff;
            border: 1px solid #d8deea;
            border-radius: 11px;
            outline: none;
            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        .field-control:hover {
            border-color: #aebbe0;
        }

        .field-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(49, 85, 217, 0.11);
        }

        .field-control[readonly] {
            color: #59677e;
            background: #f5f7fb;
            cursor: not-allowed;
        }

        .field-help {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .field-help strong {
            color: #46546b;
        }

        .form-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 13px;
            margin-top: 29px;
            padding-top: 22px;
            border-top: 1px solid var(--border);
        }

        .footer-note {
            max-width: 340px;
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .form-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 11px 17px;
            border: 1px solid transparent;
            border-radius: 11px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 750;
            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                transform 0.2s ease;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button-primary {
            color: #fff;
            background: var(--primary);
            box-shadow: 0 7px 15px rgba(49, 85, 217, 0.2);
        }

        .button-primary:hover {
            background: var(--primary-dark);
            box-shadow: 0 9px 18px rgba(49, 85, 217, 0.26);
        }

        .button-secondary {
            color: #46546b;
            background: #fff;
            border-color: #d8deea;
        }

        .button-secondary:hover {
            background: #f7f8fc;
            border-color: #b8c2d5;
        }

        .side-column {
            display: grid;
            gap: 18px;
        }

        .side-card {
            padding: 21px;
        }

        .side-card h3 {
            margin: 0;
            font-size: 15px;
            letter-spacing: -0.2px;
        }

        .side-card-intro {
            margin: 7px 0 17px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .team-detail-list {
            display: grid;
            gap: 15px;
            margin: 0;
        }

        .team-detail {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
        }

        .team-detail dt {
            color: var(--muted);
            font-size: 12px;
        }

        .team-detail dd {
            margin: 0;
            text-align: right;
            font-size: 12px;
            font-weight: 750;
            overflow-wrap: anywhere;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 9px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 800;
        }

        .status-badge::before {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            content: "";
        }

        .status-active {
            color: var(--success);
            background: var(--success-bg);
        }

        .status-active::before {
            background: var(--success);
        }

        .status-inactive {
            color: #687386;
            background: #eef1f5;
        }

        .status-inactive::before {
            background: #8994a5;
        }

        .info-callout {
            padding: 13px;
            border-radius: 12px;
            background: #f3f6ff;
            color: #4a5d91;
            font-size: 12px;
            line-height: 1.6;
        }

        .info-callout strong {
            display: block;
            margin-bottom: 4px;
            color: #2e478e;
        }

        .quick-links {
            display: grid;
            gap: 9px;
            margin-top: 16px;
        }

        .quick-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 11px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: #47556b;
            font-size: 12px;
            font-weight: 700;
            transition:
                border-color 0.2s ease,
                background 0.2s ease,
                color 0.2s ease;
        }

        .quick-link:hover {
            color: var(--primary);
            background: #f7f8ff;
            border-color: #cbd5ff;
        }

        .quick-link span:last-child {
            color: #9aa4b5;
            font-size: 16px;
        }

        .page-footer {
            margin-top: 27px;
            color: #8791a2;
            text-align: center;
            font-size: 11px;
        }

        @media (max-width: 900px) {
            .edit-layout {
                grid-template-columns: minmax(0, 1fr);
            }

            .side-column {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .page-heading {
                align-items: stretch;
            }

            .team-summary {
                min-width: 215px;
            }
        }

        @media (max-width: 650px) {
            .topbar {
                align-items: flex-start;
                flex-direction: column;
                gap: 13px;
                padding: 14px 18px;
            }

            .topbar-links {
                justify-content: flex-start;
                width: 100%;
                gap: 4px;
            }

            .topbar-links a {
                padding: 8px 10px;
                font-size: 12px;
            }

            .page-container {
                width: min(100% - 28px, 600px);
                padding-top: 24px;
            }

            .page-heading {
                flex-direction: column;
                gap: 17px;
            }

            .team-summary {
                width: 100%;
            }

            .form-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .form-group.full-width {
                grid-column: auto;
            }

            .card-heading,
            .form-body {
                padding: 20px;
            }

            .side-column {
                grid-template-columns: minmax(0, 1fr);
            }

            .form-footer {
                align-items: stretch;
                flex-direction: column;
            }

            .form-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                width: 100%;
            }

            .form-actions .button {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>
</head>

<body>
<div class="app-shell">

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
            <a href="admin-teams.php">Team Management</a>
            <a href="manage-team.php?team_id=<?= (int) $teamId ?>">
                Manage Team
            </a>
            <a class="logout-link" href="logout.php">Logout</a>
        </nav>
    </header>

    <main class="page-container">

        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span class="separator">/</span>
            <a href="admin-teams.php">Team Management</a>
            <span class="separator">/</span>
            <a href="manage-team.php?team_id=<?= (int) $teamId ?>">
                <?= e($teamName) ?>
            </a>
            <span class="separator">/</span>
            <span>Edit Team</span>
        </div>

        <section class="page-heading">
            <div>
                <div class="eyebrow">
                    <span class="eyebrow-dot"></span>
                    Team settings
                </div>

                <h1>Edit Team</h1>

                <p class="page-description">
                    Update the team name, category, coach, roster capacity,
                    and current status.
                </p>
            </div>

            <div class="team-summary">
                <div class="team-summary-icon" aria-hidden="true">⚽</div>

                <div>
                    <p class="summary-label">Editing team</p>
                    <p class="summary-name"><?= e($teamName) ?></p>
                    <p class="summary-sport"><?= e($sportName) ?></p>
                </div>
            </div>
        </section>

        <?php if ($message === 'updated'): ?>
            <div class="notice notice-success" role="status">
                <span class="notice-icon">✓</span>
                <div>
                    <strong>Team updated successfully.</strong>
                    <div>Your team information has been saved.</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="notice notice-info">
            <span class="notice-icon">i</span>
            <div>
                <strong>Before saving</strong>
                Check the team details carefully. The sport is fixed after
                team creation and cannot be changed from this page.
            </div>
        </div>

        <div class="edit-layout">

            <section class="form-card">
                <div class="card-heading">
                    <h2>Team information</h2>
                    <p>
                        Fields marked with <span class="required">*</span>
                        are required.
                    </p>
                </div>

                <form
                    action="edit-team-process.php"
                    method="POST"
                    id="editTeamForm"
                >
                    <div class="form-body">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrfToken()) ?>"
                        >

                        <input
                            type="hidden"
                            name="team_id"
                            value="<?= (int) $teamId ?>"
                        >

                        <section class="form-section">
                            <h3 class="section-title">
                                <span class="section-number">1</span>
                                Basic details
                            </h3>

                            <div class="form-grid">

                                <div class="form-group">
                                    <label for="sport">
                                        Sport
                                    </label>

                                    <input
                                        class="field-control"
                                        type="text"
                                        id="sport"
                                        value="<?= e($sportName) ?>"
                                        readonly
                                    >

                                    <small class="field-help">
                                        Sport cannot be changed after team
                                        creation.
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="team_category">
                                        Team category
                                        <span class="required">*</span>
                                    </label>

                                    <select
                                        class="field-control"
                                        name="team_category"
                                        id="team_category"
                                        required
                                    >
                                        <?php foreach ($categories as $category): ?>
                                            <option
                                                value="<?= e($category) ?>"
                                                <?= $teamCategory === $category
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= e($category) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <small class="field-help">
                                        Choose the category that applies to
                                        this team.
                                    </small>
                                </div>

                                <div class="form-group full-width">
                                    <label for="team_name">
                                        Team name
                                        <span class="required">*</span>
                                    </label>

                                    <input
                                        class="field-control"
                                        type="text"
                                        name="team_name"
                                        id="team_name"
                                        maxlength="120"
                                        value="<?= e($teamName) ?>"
                                        placeholder="Enter team name"
                                        autocomplete="off"
                                        required
                                    >

                                    <small class="field-help">
                                        Use a clear name that players and
                                        coordinators can recognize.
                                    </small>
                                </div>

                            </div>
                        </section>

                        <section class="form-section">
                            <h3 class="section-title">
                                <span class="section-number">2</span>
                                Team organization
                            </h3>

                            <div class="form-grid">

                                <div class="form-group full-width">
                                    <label for="coach_id">
                                        Assigned coach
                                    </label>

                                    <select
                                        class="field-control"
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
                                                <?= e($coach['full_name']) ?>
                                                <?php if (!empty($coach['specialization'])): ?>
                                                    — <?= e($coach['specialization']) ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <small class="field-help">
                                        Select an active, approved coach or
                                        leave the team without an assigned coach.
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="roster_limit">
                                        Roster limit
                                        <span class="required">*</span>
                                    </label>

                                    <input
                                        class="field-control"
                                        type="number"
                                        name="roster_limit"
                                        id="roster_limit"
                                        min="1"
                                        max="500"
                                        value="<?= $rosterLimit ?>"
                                        required
                                    >

                                    <small class="field-help">
                                        Maximum number of players allowed
                                        on this team.
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="team_status">
                                        Team status
                                        <span class="required">*</span>
                                    </label>

                                    <select
                                        class="field-control"
                                        name="team_status"
                                        id="team_status"
                                        required
                                    >
                                        <option
                                            value="ACTIVE"
                                            <?= $teamStatus === 'ACTIVE'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Active
                                        </option>

                                        <option
                                            value="INACTIVE"
                                            <?= $teamStatus === 'INACTIVE'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Inactive
                                        </option>
                                    </select>

                                    <small class="field-help">
                                        Inactive teams are marked as not
                                        currently active.
                                    </small>
                                </div>

                            </div>
                        </section>

                        <div class="form-footer">
                            <p class="footer-note">
                                Review your changes before saving.
                                Your updates will be processed securely.
                            </p>

                            <div class="form-actions">
                                <a
                                    class="button button-secondary"
                                    href="manage-team.php?team_id=<?= (int) $teamId ?>"
                                >
                                    Cancel
                                </a>

                                <button
                                    class="button button-primary"
                                    type="submit"
                                >
                                    <span aria-hidden="true">✓</span>
                                    Save Changes
                                </button>
                            </div>
                        </div>

                    </div>
                </form>
            </section>

            <aside class="side-column">

                <section class="side-card">
                    <h3>Current team overview</h3>

                    <p class="side-card-intro">
                        Quick reference for the team you are editing.
                    </p>

                    <dl class="team-detail-list">

                        <div class="team-detail">
                            <dt>Team ID</dt>
                            <dd>#<?= (int) $teamId ?></dd>
                        </div>

                        <div class="team-detail">
                            <dt>Sport</dt>
                            <dd><?= e($sportName) ?></dd>
                        </div>

                        <div class="team-detail">
                            <dt>Category</dt>
                            <dd><?= e($teamCategory) ?></dd>
                        </div>

                        <div class="team-detail">
                            <dt>Roster limit</dt>
                            <dd><?= $rosterLimit ?> players</dd>
                        </div>

                        <div class="team-detail">
                            <dt>Status</dt>
                            <dd>
                                <span class="status-badge <?= e($statusClass) ?>">
                                    <?= e($teamStatus) ?>
                                </span>
                            </dd>
                        </div>

                    </dl>
                </section>

                <section class="side-card">
                    <h3>Helpful information</h3>

                    <p class="side-card-intro">
                        Keep these points in mind while updating your team.
                    </p>

                    <div class="info-callout">
                        <strong>Roster capacity</strong>
                        Set the maximum number of players your team is allowed
                        to have. Make sure the limit matches your sport and
                        college rules.
                    </div>

                    <div class="quick-links">
                        <a
                            class="quick-link"
                            href="manage-team.php?team_id=<?= (int) $teamId ?>"
                        >
                            <span>Return to team management</span>
                            <span aria-hidden="true">→</span>
                        </a>

                        <a
                            class="quick-link"
                            href="admin-teams.php"
                        >
                            <span>View all teams</span>
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </section>

            </aside>

        </div>

        <footer class="page-footer">
            SportSync · Sports Management System
        </footer>

    </main>
</div>

<script>
    const editTeamForm = document.getElementById('editTeamForm');

    editTeamForm.addEventListener('submit', function (event) {
        const teamName = document.getElementById('team_name').value.trim();
        const rosterLimit = Number(
            document.getElementById('roster_limit').value
        );

        if (teamName.length === 0) {
            event.preventDefault();
            alert('Please enter a team name.');
            document.getElementById('team_name').focus();
            return;
        }

        if (
            !Number.isInteger(rosterLimit)
            || rosterLimit < 1
            || rosterLimit > 500
        ) {
            event.preventDefault();
            alert('Roster limit must be between 1 and 500.');
            document.getElementById('roster_limit').focus();
        }
    });
</script>

</body>
</html>