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

if (!$tournamentId || $tournamentId <= 0) {
    http_response_code(400);
    exit('Invalid tournament ID.');
}

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
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
    http_response_code(404);
    exit('Tournament not found.');
}

/*
|--------------------------------------------------------------------------
| Get Registered Teams
|--------------------------------------------------------------------------
*/

$registeredStmt = $pdo->prepare("
    SELECT
        tt.tournament_team_id,
        tt.team_id,
        tt.participation_status,
        tt.registered_at,
        tt.remarks,
        tm.team_name,
        tm.team_category,
        tm.team_status,
        s.sport_name,
        cp.designation,
        u.full_name AS coach_name
    FROM tournament_teams tt
    INNER JOIN teams tm
        ON tm.team_id = tt.team_id
    INNER JOIN sports s
        ON s.sport_id = tm.sport_id
    LEFT JOIN coach_profiles cp
        ON cp.coach_id = tm.coach_id
    LEFT JOIN users u
        ON u.user_id = cp.user_id
    WHERE tt.tournament_id = :tournament_id
    ORDER BY
        tt.participation_status ASC,
        tm.team_name ASC
");

$registeredStmt->execute([
    ':tournament_id' => $tournamentId
]);

$registeredTeams = $registeredStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Get Eligible Teams
|--------------------------------------------------------------------------
*/

$eligibleStmt = $pdo->prepare("
    SELECT
        tm.team_id,
        tm.team_name,
        tm.team_category,
        tm.team_status
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

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

$tournamentStatus = (string) $tournament['tournament_status'];
$tournamentFormat = (string) $tournament['tournament_format'];
$registrationOpen = $tournamentStatus === 'REGISTRATION_OPEN';
$canSchedule = $tournamentFormat === 'LEAGUE'
    && count($registeredTeams) >= 2;

$statusClass = match ($tournamentStatus) {
    'REGISTRATION_OPEN' => 'status-open',
    'IN_PROGRESS' => 'status-progress',
    'COMPLETED' => 'status-completed',
    'CANCELLED' => 'status-cancelled',
    default => 'status-default'
};

$activeTeamCount = 0;

foreach ($registeredTeams as $registeredTeam) {
    if ($registeredTeam['participation_status'] === 'ACTIVE') {
        $activeTeamCount++;
    }
}

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
        Manage Tournament | <?= e($tournament['tournament_name']) ?>
    </title>

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
        select,
        textarea {
            font: inherit;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 15px clamp(18px, 4vw, 54px);
            background: rgba(255, 255, 255, 0.95);
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
            gap: 7px;
        }

        .topbar-links a {
            padding: 9px 12px;
            border-radius: 10px;
            color: #536075;
            font-size: 13px;
            font-weight: 650;
            transition: 0.2s ease;
        }

        .topbar-links a:hover {
            color: var(--primary);
            background: var(--primary-light);
        }

        .topbar-links .logout-link {
            color: var(--danger);
            background: #fff1f0;
        }

        .page-container {
            width: min(1200px, calc(100% - 36px));
            margin: 0 auto;
            padding: 30px 0 55px;
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

        .page-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 22px;
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
            letter-spacing: -1px;
        }

        .page-description {
            max-width: 700px;
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 15px;
        }

        .heading-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 9px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 10px 15px;
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
            box-shadow: 0 7px 15px rgba(49, 85, 217, 0.18);
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
            border-color: #b8c2d5;
        }

        .button-danger {
            color: var(--danger);
            background: #fff;
            border-color: #f2c9c5;
        }

        .button-danger:hover {
            background: #fff5f4;
        }

        .notice {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            margin-bottom: 18px;
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
            color: #fff;
            background: var(--success);
        }

        .notice-error {
            color: var(--danger);
            background: var(--danger-bg);
            border-color: #f2c9c5;
        }

        .notice-error .notice-icon {
            color: #fff;
            background: var(--danger);
        }

        .notice-info {
            color: #35508d;
            background: #edf3ff;
            border-color: #d8e4ff;
        }

        .notice-info .notice-icon {
            color: #fff;
            background: #4267c9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 23px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
            padding: 18px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: 0 5px 18px rgba(25, 42, 90, 0.035);
        }

        .stat-icon {
            display: grid;
            place-items: center;
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            border-radius: 13px;
            color: var(--primary);
            background: var(--primary-light);
            font-size: 20px;
        }

        .stat-label {
            margin: 0 0 4px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 750;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .stat-value {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .stat-subtext {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 11px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 330px;
            align-items: start;
            gap: 22px;
        }

        .main-column,
        .side-column {
            display: grid;
            gap: 22px;
            min-width: 0;
        }

        .card {
            overflow: hidden;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .card-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            padding: 22px 23px 19px;
            border-bottom: 1px solid var(--border);
        }

        .card-heading h2 {
            margin: 0;
            font-size: 17px;
            letter-spacing: -0.3px;
        }

        .card-heading p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .card-body {
            padding: 22px 23px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-badge::before {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            content: "";
        }

        .status-open {
            color: var(--success);
            background: var(--success-bg);
        }

        .status-open::before {
            background: var(--success);
        }

        .status-progress {
            color: #3558b8;
            background: #edf1ff;
        }

        .status-progress::before {
            background: #4267f5;
        }

        .status-completed {
            color: #586579;
            background: #eef1f5;
        }

        .status-completed::before {
            background: #8994a5;
        }

        .status-cancelled {
            color: var(--danger);
            background: var(--danger-bg);
        }

        .status-cancelled::before {
            background: var(--danger);
        }

        .status-default {
            color: #8a5b14;
            background: #fff5e8;
        }

        .status-default::before {
            background: #c58a2c;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px 24px;
        }

        .detail-item {
            min-width: 0;
        }

        .detail-label {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 750;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .detail-value {
            display: block;
            color: #253147;
            font-size: 14px;
            font-weight: 750;
            overflow-wrap: anywhere;
        }

        .detail-subvalue {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 400;
        }

        .points-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 22px;
        }

        .points-item {
            padding: 13px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fafbfe;
        }

        .points-label {
            display: block;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }

        .points-value {
            display: block;
            margin-top: 4px;
            font-size: 20px;
            font-weight: 850;
        }

        .rules-box {
            margin-top: 20px;
            padding: 15px;
            border-radius: 12px;
            background: #f7f8fc;
        }

        .rules-box h3 {
            margin: 0 0 7px;
            font-size: 13px;
        }

        .rules-box p {
            margin: 0;
            color: #586579;
            font-size: 13px;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #303c50;
            font-size: 13px;
            font-weight: 750;
        }

        .field-control {
            display: block;
            width: 100%;
            min-height: 45px;
            padding: 10px 12px;
            color: var(--text);
            background: #fff;
            border: 1px solid #d8deea;
            border-radius: 10px;
            outline: none;
            transition: 0.2s ease;
        }

        .field-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(49, 85, 217, 0.11);
        }

        textarea.field-control {
            min-height: 95px;
            resize: vertical;
        }

        .field-help {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .register-form {
            display: grid;
            gap: 17px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
        }

        .empty-state {
            padding: 28px 18px;
            text-align: center;
        }

        .empty-icon {
            display: grid;
            place-items: center;
            width: 54px;
            height: 54px;
            margin: 0 auto 13px;
            border-radius: 16px;
            color: var(--primary);
            background: var(--primary-light);
            font-size: 23px;
        }

        .empty-state h3 {
            margin: 0;
            font-size: 15px;
        }

        .empty-state p {
            max-width: 360px;
            margin: 7px auto 0;
            color: var(--muted);
            font-size: 13px;
        }

        .team-list {
            display: grid;
            gap: 12px;
        }

        .team-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 13px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 13px;
            transition:
                border-color 0.2s ease,
                background 0.2s ease;
        }

        .team-item:hover {
            background: #fbfcff;
            border-color: #cbd5ff;
        }

        .team-item-main {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            min-width: 0;
        }

        .team-avatar {
            display: grid;
            place-items: center;
            width: 39px;
            height: 39px;
            flex: 0 0 39px;
            border-radius: 12px;
            color: var(--primary);
            background: var(--primary-light);
            font-size: 15px;
            font-weight: 850;
        }

        .team-item-name {
            margin: 0;
            font-size: 13px;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .team-item-meta {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.5;
        }

        .team-item-status {
            display: inline-block;
            margin-top: 7px;
            padding: 4px 8px;
            border-radius: 20px;
            color: #526075;
            background: #eef1f5;
            font-size: 10px;
            font-weight: 800;
        }

        .team-item-status.active {
            color: var(--success);
            background: var(--success-bg);
        }

        .withdraw-form {
            flex: 0 0 auto;
        }

        .withdraw-button {
            padding: 7px 10px;
            border: 1px solid #f2c9c5;
            border-radius: 9px;
            color: var(--danger);
            background: #fff;
            cursor: pointer;
            font-size: 11px;
            font-weight: 750;
        }

        .withdraw-button:hover {
            background: #fff5f4;
        }

        .side-link-list {
            display: grid;
            gap: 9px;
        }

        .side-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 13px 14px;
            border: 1px solid var(--border);
            border-radius: 11px;
            color: #47556b;
            font-size: 12px;
            font-weight: 750;
            transition: 0.2s ease;
        }

        .side-link:hover {
            color: var(--primary);
            background: #f7f8ff;
            border-color: #cbd5ff;
        }

        .side-link-arrow {
            color: #9aa4b5;
            font-size: 17px;
        }

        .info-callout {
            padding: 14px;
            border-radius: 12px;
            color: #4a5d91;
            background: #f3f6ff;
            font-size: 12px;
            line-height: 1.6;
        }

        .info-callout strong {
            display: block;
            margin-bottom: 5px;
            color: #2e478e;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .teams-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: left;
        }

        .teams-table th {
            padding: 12px 14px;
            color: #687386;
            background: #f8f9fc;
            border-bottom: 1px solid var(--border);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .teams-table td {
            padding: 14px;
            border-bottom: 1px solid #edf0f5;
            vertical-align: middle;
        }

        .teams-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .teams-table tbody tr:hover {
            background: #fbfcff;
        }

        .table-team-name {
            font-weight: 800;
            color: #27344a;
        }

        .table-muted {
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
        }

        .page-footer {
            margin-top: 27px;
            color: #8791a2;
            text-align: center;
            font-size: 11px;
        }

        @media (max-width: 1000px) {
            .content-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .side-column {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
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
            }

            .topbar-links a {
                padding: 8px 9px;
                font-size: 12px;
            }

            .page-container {
                width: calc(100% - 28px);
                padding-top: 23px;
            }

            .page-heading {
                flex-direction: column;
            }

            .heading-actions {
                width: 100%;
            }

            .heading-actions .button {
                flex: 1;
            }

            .stats-grid {
                gap: 10px;
            }

            .stat-card {
                align-items: flex-start;
                flex-direction: column;
                gap: 10px;
                padding: 14px;
            }

            .stat-value {
                font-size: 15px;
            }

            .card-heading,
            .card-body {
                padding: 18px;
            }

            .detail-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 15px;
            }

            .side-column {
                grid-template-columns: minmax(0, 1fr);
            }

            .points-grid {
                gap: 7px;
            }

            .points-item {
                padding: 10px;
            }

            .points-value {
                font-size: 18px;
            }

            .team-item {
                flex-direction: column;
            }

            .withdraw-form,
            .withdraw-button {
                width: 100%;
            }

            .teams-table {
                min-width: 760px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
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
            <div class="eyebrow">
                <span class="eyebrow-dot"></span>
                Tournament control center
            </div>

            <h1>Manage Tournament</h1>

            <p class="page-description">
                Manage tournament information, register teams, and access
                scheduling, match results, and standings.
            </p>
        </div>

        <div class="heading-actions">
            <a
                class="button button-secondary"
                href="admin-tournaments.php"
            >
                ← All Tournaments
            </a>
        </div>
    </section>

    <?php if ($message === 'team_registered'): ?>
        <div class="notice notice-success" role="status">
            <span class="notice-icon">✓</span>
            <div>
                <strong>Team registered successfully.</strong>
                <div>The team has been added to this tournament.</div>
            </div>
        </div>
    <?php elseif ($message === 'team_withdrawn'): ?>
        <div class="notice notice-success" role="status">
            <span class="notice-icon">✓</span>
            <div>
                <strong>Team withdrawn successfully.</strong>
                <div>The team has been withdrawn from this tournament.</div>
            </div>
        </div>
    <?php elseif ($message === 'match_scheduled'): ?>
        <div class="notice notice-success" role="status">
            <span class="notice-icon">✓</span>
            <div>
                <strong>Match scheduled successfully.</strong>
                <div>The match has been added to the tournament.</div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice notice-error" role="alert">
            <span class="notice-icon">!</span>
            <div>
                <strong>Action could not be completed.</strong>
                <div><?= e($error) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <section class="stats-grid" aria-label="Tournament summary">

        <article class="stat-card">
            <div class="stat-icon" aria-hidden="true">🏆</div>
            <div>
                <p class="stat-label">Tournament</p>
                <p class="stat-value"><?= e($tournament['tournament_name']) ?></p>
                <p class="stat-subtext">
                    ID #<?= (int) $tournament['tournament_id'] ?>
                </p>
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-icon" aria-hidden="true">🏅</div>
            <div>
                <p class="stat-label">Sport</p>
                <p class="stat-value"><?= e($tournament['sport_name']) ?></p>
                <p class="stat-subtext"><?= e($tournamentFormat) ?> format</p>
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-icon" aria-hidden="true">👥</div>
            <div>
                <p class="stat-label">Registered teams</p>
                <p class="stat-value"><?= $activeTeamCount ?></p>
                <p class="stat-subtext">Active registrations</p>
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-icon" aria-hidden="true">📌</div>
            <div>
                <p class="stat-label">Tournament status</p>
                <p class="stat-value">
                    <span class="status-badge <?= e($statusClass) ?>">
                        <?= e(str_replace('_', ' ', $tournamentStatus)) ?>
                    </span>
                </p>
                <p class="stat-subtext">Current status</p>
            </div>
        </article>

    </section>

    <div class="content-grid">

        <div class="main-column">

            <section class="card">
                <div class="card-heading">
                    <div>
                        <h2>Tournament information</h2>
                        <p>Overview of the tournament settings and schedule.</p>
                    </div>

                    <span class="status-badge <?= e($statusClass) ?>">
                        <?= e(str_replace('_', ' ', $tournamentStatus)) ?>
                    </span>
                </div>

                <div class="card-body">

                    <div class="detail-grid">

                        <div class="detail-item">
                            <span class="detail-label">Tournament name</span>
                            <span class="detail-value">
                                <?= e($tournament['tournament_name']) ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Sport</span>
                            <span class="detail-value">
                                <?= e($tournament['sport_name']) ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Venue</span>
                            <span class="detail-value">
                                <?= e($tournament['venue_name'] ?? 'Not assigned') ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Tournament format</span>
                            <span class="detail-value">
                                <?= e($tournamentFormat) ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Start date</span>
                            <span class="detail-value">
                                <?= e($tournament['start_date']) ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">End date</span>
                            <span class="detail-value">
                                <?= e($tournament['end_date']) ?>
                            </span>
                        </div>

                    </div>

                    <div class="points-grid">
                        <div class="points-item">
                            <span class="points-label">Points for win</span>
                            <span class="points-value">
                                <?= e($tournament['points_win']) ?>
                            </span>
                        </div>

                        <div class="points-item">
                            <span class="points-label">Points for draw</span>
                            <span class="points-value">
                                <?= e($tournament['points_draw']) ?>
                            </span>
                        </div>

                        <div class="points-item">
                            <span class="points-label">Points for loss</span>
                            <span class="points-value">
                                <?= e($tournament['points_loss']) ?>
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($tournament['rules_information'])): ?>
                        <div class="rules-box">
                            <h3>Tournament rules</h3>
                            <p><?= e($tournament['rules_information']) ?></p>
                        </div>
                    <?php endif; ?>

                </div>
            </section>

            <?php if ($canSchedule): ?>
                <section class="card">
                    <div class="card-heading">
                        <div>
                            <h2>Match management</h2>
                            <p>
                                Schedule matches and manage tournament results.
                            </p>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="side-link-list">
                            <a
                                class="side-link"
                                href="schedule-match.php?tournament_id=<?= (int) $tournamentId ?>"
                            >
                                <span>Schedule a match</span>
                                <span class="side-link-arrow">→</span>
                            </a>

                            <a
                                class="side-link"
                                href="match-results.php?tournament_id=<?= (int) $tournamentId ?>"
                            >
                                <span>View match results</span>
                                <span class="side-link-arrow">→</span>
                            </a>

                            <a
                                class="side-link"
                                href="tournament-standings.php?tournament_id=<?= (int) $tournamentId ?>"
                            >
                                <span>View tournament standings</span>
                                <span class="side-link-arrow">→</span>
                            </a>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="card">
                <div class="card-heading">
                    <div>
                        <h2>Registered teams</h2>
                        <p>
                            <?= count($registeredTeams) ?>
                            team registration record(s) for this tournament.
                        </p>
                    </div>
                </div>

                <div class="card-body">

                    <?php if (!$registeredTeams): ?>
                        <div class="empty-state">
                            <div class="empty-icon" aria-hidden="true">👥</div>
                            <h3>No teams registered yet</h3>
                            <p>
                                Register eligible teams to start preparing
                                this tournament.
                            </p>
                        </div>
                    <?php else: ?>

                        <div class="team-list">

                            <?php foreach ($registeredTeams as $team): ?>
                                <?php
                                $participationStatus = (string) $team['participation_status'];
                                $isActiveRegistration = $participationStatus === 'ACTIVE';
                                ?>

                                <article class="team-item">

                                    <div class="team-item-main">
                                        <div class="team-avatar" aria-hidden="true">
                                            <?= e(mb_strtoupper(mb_substr((string) $team['team_name'], 0, 1))) ?>
                                        </div>

                                        <div>
                                            <p class="team-item-name">
                                                <?= e($team['team_name']) ?>
                                            </p>

                                            <p class="team-item-meta">
                                                <?= e($team['sport_name']) ?>
                                                ·
                                                <?= e($team['team_category']) ?>
                                                <br>

                                                Coach:
                                                <?= e($team['coach_name'] ?? 'Not assigned') ?>
                                                <br>

                                                Registered:
                                                <?= e($team['registered_at']) ?>
                                            </p>

                                            <span class="team-item-status <?= $isActiveRegistration ? 'active' : '' ?>">
                                                <?= e(str_replace('_', ' ', $participationStatus)) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <?php if (
                                        $isActiveRegistration
                                        && $registrationOpen
                                    ): ?>
                                        <form
                                            class="withdraw-form"
                                            method="POST"
                                            action="withdraw-tournament-team.php"
                                            onsubmit="return confirm('Withdraw this team from the tournament?');"
                                        >
                                            <?= csrfField() ?>

                                            <input
                                                type="hidden"
                                                name="tournament_id"
                                                value="<?= (int) $tournamentId ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="team_id"
                                                value="<?= (int) $team['team_id'] ?>"
                                            >

                                            <button
                                                class="withdraw-button"
                                                type="submit"
                                            >
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
                    <div>
                        <h2>Register a team</h2>
                        <p>Add an eligible team to this tournament.</p>
                    </div>
                </div>

                <div class="card-body">

                    <?php if (!$registrationOpen): ?>
                        <div class="notice notice-info">
                            <span class="notice-icon">i</span>
                            <div>
                                <strong>Registration is closed</strong>
                                Team registration is currently unavailable
                                for this tournament.
                            </div>
                        </div>

                    <?php elseif (!$eligibleTeams): ?>
                        <div class="empty-state">
                            <div class="empty-icon" aria-hidden="true">✓</div>
                            <h3>No eligible teams</h3>
                            <p>
                                There are no active teams available for this
                                tournament's sport, or all eligible teams
                                are already registered.
                            </p>
                        </div>

                    <?php else: ?>
                        <form
                            class="register-form"
                            method="POST"
                            action="register-tournament-team.php"
                        >
                            <?= csrfField() ?>

                            <input
                                type="hidden"
                                name="tournament_id"
                                value="<?= (int) $tournamentId ?>"
                            >

                            <div class="form-group">
                                <label for="team_id">Select team</label>

                                <select
                                    class="field-control"
                                    name="team_id"
                                    id="team_id"
                                    required
                                >
                                    <option value="">Choose a team</option>

                                    <?php foreach ($eligibleTeams as $team): ?>
                                        <option value="<?= (int) $team['team_id'] ?>">
                                            <?= e($team['team_name']) ?>
                                            — <?= e($team['team_category']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <small class="field-help">
                                    Only active teams from this tournament's
                                    sport are listed.
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="remarks">Remarks (optional)</label>

                                <textarea
                                    class="field-control"
                                    name="remarks"
                                    id="remarks"
                                    rows="4"
                                    maxlength="500"
                                    placeholder="Add any notes about this registration"
                                ></textarea>
                            </div>

                            <div class="form-actions">
                                <button
                                    class="button button-primary"
                                    type="submit"
                                >
                                    + Register Team
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                </div>
            </section>

            <section class="card">
                <div class="card-heading">
                    <div>
                        <h2>Quick actions</h2>
                        <p>Open tournament tools.</p>
                    </div>
                </div>

                <div class="card-body">
                    <div class="side-link-list">

                        <?php if ($canSchedule): ?>
                            <a
                                class="side-link"
                                href="schedule-match.php?tournament_id=<?= (int) $tournamentId ?>"
                            >
                                <span>Schedule match</span>
                                <span class="side-link-arrow">→</span>
                            </a>

                            <a
                                class="side-link"
                                href="match-results.php?tournament_id=<?= (int) $tournamentId ?>"
                            >
                                <span>Match results</span>
                                <span class="side-link-arrow">→</span>
                            </a>

                            <a
                                class="side-link"
                                href="tournament-standings.php?tournament_id=<?= (int) $tournamentId ?>"
                            >
                                <span>Tournament standings</span>
                                <span class="side-link-arrow">→</span>
                            </a>
                        <?php else: ?>
                            <div class="info-callout">
                                <strong>Match scheduling</strong>
                                Match scheduling is available for league
                                tournaments after at least two teams are
                                registered.
                            </div>
                        <?php endif; ?>

                        <a
                            class="side-link"
                            href="admin-tournaments.php"
                        >
                            <span>Back to tournaments</span>
                            <span class="side-link-arrow">→</span>
                        </a>

                    </div>
                </div>
            </section>

            <div class="info-callout">
                <strong>Registration reminder</strong>
                Only active teams belonging to the same sport are shown
                for registration. Teams can be withdrawn while tournament
                registration remains open.
            </div>

        </aside>

    </div>

    <footer class="page-footer">
        SportSync · Sports Management System
    </footer>

</main>

</body>
</html>