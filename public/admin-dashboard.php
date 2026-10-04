<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SportSync - Admin Dashboard
|--------------------------------------------------------------------------
| Admin is responsible for:
| 1. Student accounts
| 2. Sports
| 3. Event formats (Solo / Team)
| 4. Teams
| 5. Overall system management
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$user = currentUser();

if (($user['role'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    exit('Access denied.');
}

$pdo = db();

$pageTitle = 'Admin Dashboard';

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function adminDashboardEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Dashboard statistics
|--------------------------------------------------------------------------
*/

$totalStudents = 0;
$pendingStudents = 0;
$totalSports = 0;
$activeSports = 0;
$totalEventFormats = 0;
$totalTeams = 0;
$totalCompetitions = 0;

$dashboardError = false;

/*
|--------------------------------------------------------------------------
| Students
|--------------------------------------------------------------------------
*/

try {
    $totalStudents = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM users
         WHERE role_id = 4"
    )->fetchColumn();

    $pendingStudents = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM users
         WHERE role_id = 4
           AND account_status = 'PENDING'"
    )->fetchColumn();
} catch (Throwable $exception) {
    $dashboardError = true;

    error_log(
        'Admin dashboard student statistics error: '
        . $exception->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Sports
|--------------------------------------------------------------------------
*/

try {
    $totalSports = (int) $pdo->query(
        "SELECT COUNT(*) FROM sports"
    )->fetchColumn();

    $activeSports = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM sports
         WHERE sport_status = 'ACTIVE'"
    )->fetchColumn();
} catch (Throwable $exception) {
    $dashboardError = true;

    error_log(
        'Admin dashboard sports statistics error: '
        . $exception->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Event formats
|--------------------------------------------------------------------------
|
| Examples:
| Athletics -> 100m Running -> SOLO
| Athletics -> 4x100m Relay -> TEAM
| Badminton -> Singles -> SOLO
| Badminton -> Doubles -> TEAM
|
*/

try {
    $totalEventFormats = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM sport_events
         WHERE event_status = 'ACTIVE'"
    )->fetchColumn();
} catch (Throwable $exception) {
    $dashboardError = true;

    error_log(
        'Admin dashboard event format statistics error: '
        . $exception->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Teams
|--------------------------------------------------------------------------
*/

try {
    $totalTeams = (int) $pdo->query(
        "SELECT COUNT(*) FROM teams"
    )->fetchColumn();
} catch (Throwable $exception) {
    $dashboardError = true;

    error_log(
        'Admin dashboard team statistics error: '
        . $exception->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Competitions
|--------------------------------------------------------------------------
|
| The existing "events" table represents actual scheduled competitions.
|
*/

try {
    $totalCompetitions = (int) $pdo->query(
        "SELECT COUNT(*) FROM events"
    )->fetchColumn();
} catch (Throwable $exception) {
    $dashboardError = true;

    error_log(
        'Admin dashboard competition statistics error: '
        . $exception->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Recent competitions
|--------------------------------------------------------------------------
*/

$recentCompetitions = [];

try {
    $statement = $pdo->query(
        "SELECT
            e.event_id,
            e.event_title,
            e.event_start,
            e.event_status,
            s.sport_name
         FROM events e
         INNER JOIN sports s
            ON s.sport_id = e.sport_id
         ORDER BY e.created_at DESC
         LIMIT 5"
    );

    $recentCompetitions = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    $dashboardError = true;

    error_log(
        'Admin dashboard competitions error: '
        . $exception->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Event format summary
|--------------------------------------------------------------------------
*/

$eventFormatSummary = [];

try {
    $statement = $pdo->query(
        "SELECT
            s.sport_name,
            se.event_type,
            COUNT(*) AS format_count
         FROM sport_events se
         INNER JOIN sports s
            ON s.sport_id = se.sport_id
         WHERE se.event_status = 'ACTIVE'
         GROUP BY s.sport_name, se.event_type
         ORDER BY s.sport_name, se.event_type"
    );

    $eventFormatSummary = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    $dashboardError = true;

    error_log(
        'Admin dashboard format summary error: '
        . $exception->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Date helper
|--------------------------------------------------------------------------
*/

function adminDashboardDate(?string $date): string
{
    if (!$date) {
        return '—';
    }

    $timestamp = strtotime($date);

    return $timestamp
        ? date('M j, Y • g:i A', $timestamp)
        : '—';
}

/*
|--------------------------------------------------------------------------
| Status helper
|--------------------------------------------------------------------------
*/

function adminDashboardStatusClass(?string $status): string
{
    return match (strtoupper($status ?? '')) {
        'ACTIVE',
        'OPEN',
        'ONGOING',
        'COMPLETED'
            => 'admin-status-success',

        'DRAFT',
        'UPCOMING',
        'CLOSED'
            => 'admin-status-warning',

        'CANCELLED',
        'INACTIVE'
            => 'admin-status-danger',

        default
            => 'admin-status-neutral',
    };
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

    <title>Admin Dashboard | SportSync</title>

    <meta
        name="description"
        content="SportSync administrator dashboard"
    >

    <link
        rel="icon"
        type="image/svg+xml"
        href="../assets/images/sportsync-mark.svg"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        /*
        |--------------------------------------------------------------------------
        | ADMIN DASHBOARD
        |--------------------------------------------------------------------------
        | Existing SportSync blue and white theme is preserved.
        */

        .admin-dashboard {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .admin-dashboard *,
        .admin-dashboard *::before,
        .admin-dashboard *::after {
            box-sizing: border-box;
        }

        /*
        |--------------------------------------------------------------------------
        | Welcome
        |--------------------------------------------------------------------------
        */

        .admin-hero {
            position: relative;
            overflow: hidden;
            padding: 28px 30px;
            border-radius: 22px;
            color: #fff;
            background: linear-gradient(
                135deg,
                #0b1f4d 0%,
                #1647a8 55%,
                #2563eb 100%
            );
            box-shadow: 0 16px 40px rgba(15, 35, 80, .16);
        }

        .admin-hero::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            right: -70px;
            top: -115px;
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 50%;
            box-shadow:
                0 0 0 35px rgba(255, 255, 255, .035),
                0 0 0 70px rgba(255, 255, 255, .025);
            pointer-events: none;
        }

        .admin-hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
        }

        .admin-hero-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .13);
            font-size: 11px;
            font-weight: 750;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .admin-hero h1 {
            margin: 0 0 10px;
            color: #fff;
            font-size: clamp(25px, 3vw, 35px);
            font-weight: 800;
            line-height: 1.2;
        }

        .admin-hero p {
            max-width: 760px;
            margin: 0;
            color: rgba(255, 255, 255, .86);
            font-size: 14px;
            line-height: 1.7;
        }

        /*
        |--------------------------------------------------------------------------
        | Section heading
        |--------------------------------------------------------------------------
        */

        .admin-section-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .admin-section-heading h2 {
            margin: 0;
            color: #111827;
            font-size: 19px;
            font-weight: 800;
        }

        .admin-section-heading p {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        /*
        |--------------------------------------------------------------------------
        | Statistics
        |--------------------------------------------------------------------------
        */

        .admin-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .admin-stat-card {
            min-width: 0;
            padding: 20px;
            border: 1px solid #e8edf5;
            border-radius: 17px;
            background: #fff;
            box-shadow: 0 7px 22px rgba(20, 35, 65, .045);
            transition:
                transform .18s ease,
                box-shadow .18s ease;
        }

        .admin-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(20, 35, 65, .08);
        }

        .admin-stat-icon {
            display: grid;
            place-items: center;
            width: 43px;
            height: 43px;
            margin-bottom: 17px;
            border-radius: 13px;
            background: #eef4ff;
            color: #2563eb;
            font-size: 19px;
        }

        .admin-stat-number {
            margin: 0;
            color: #111827;
            font-size: 29px;
            font-weight: 850;
            line-height: 1.1;
        }

        .admin-stat-title {
            margin: 7px 0 0;
            color: #64748b;
            font-size: 12px;
            font-weight: 650;
        }

        /*
        |--------------------------------------------------------------------------
        | Pending students
        |--------------------------------------------------------------------------
        */

        .admin-pending-notice {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px 20px;
            border: 1px solid #f4d49b;
            border-radius: 16px;
            background: #fffaf0;
        }

        .admin-pending-notice-icon {
            display: grid;
            place-items: center;
            flex: 0 0 42px;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #fff0cc;
            font-size: 19px;
        }

        .admin-pending-notice-content {
            flex: 1;
            min-width: 0;
        }

        .admin-pending-notice h2 {
            margin: 0;
            color: #854d0e;
            font-size: 15px;
            font-weight: 800;
        }

        .admin-pending-notice p {
            margin: 4px 0 0;
            color: #92400e;
            font-size: 12px;
            line-height: 1.5;
        }

        /*
        |--------------------------------------------------------------------------
        | Buttons
        |--------------------------------------------------------------------------
        */

        .admin-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 43px;
            padding: 0 17px;
            border: 0;
            border-radius: 10px;
            background: #2563eb;
            color: #fff;
            font-size: 12px;
            font-weight: 750;
            text-decoration: none;
            white-space: nowrap;
            transition:
                background .18s ease,
                transform .18s ease;
        }

        .admin-button:hover {
            background: #1d4ed8;
            color: #fff;
            transform: translateY(-1px);
        }

        .admin-button-secondary {
            background: #eef4ff;
            color: #2563eb;
        }

        .admin-button-secondary:hover {
            background: #dbeafe;
            color: #1d4ed8;
        }

        /*
        |--------------------------------------------------------------------------
        | Main responsibilities
        |--------------------------------------------------------------------------
        */

        .admin-responsibilities {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .admin-responsibility {
            display: flex;
            flex-direction: column;
            min-width: 0;
            padding: 19px;
            border: 1px solid #e8edf5;
            border-radius: 17px;
            background: #fff;
            box-shadow: 0 7px 22px rgba(20, 35, 65, .045);
        }

        .admin-responsibility-top {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-responsibility-icon {
            display: grid;
            place-items: center;
            flex: 0 0 44px;
            width: 44px;
            height: 44px;
            border-radius: 13px;
            background: #eef4ff;
            font-size: 20px;
        }

        .admin-responsibility h3 {
            margin: 0;
            color: #172033;
            font-size: 14px;
            font-weight: 800;
        }

        .admin-responsibility p {
            margin: 13px 0 17px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }

        .admin-responsibility-link {
            margin-top: auto;
            color: #2563eb;
            font-size: 12px;
            font-weight: 750;
            text-decoration: none;
        }

        .admin-responsibility-link:hover {
            text-decoration: underline;
        }

        /*
        |--------------------------------------------------------------------------
        | Format overview
        |--------------------------------------------------------------------------
        */

        .admin-format-panel {
            min-width: 0;
            overflow: hidden;
            border: 1px solid #e8edf5;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 7px 22px rgba(20, 35, 65, .045);
        }

        .admin-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 20px 22px;
            border-bottom: 1px solid #edf1f6;
        }

        .admin-panel-header h2 {
            margin: 0;
            color: #111827;
            font-size: 16px;
            font-weight: 800;
        }

        .admin-panel-header p {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .admin-panel-link {
            color: #2563eb;
            font-size: 12px;
            font-weight: 750;
            text-decoration: none;
            white-space: nowrap;
        }

        .admin-panel-link:hover {
            text-decoration: underline;
        }

        .admin-format-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .admin-format-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 16px 20px;
            border-bottom: 1px solid #edf1f6;
        }

        .admin-format-row:nth-child(odd) {
            border-right: 1px solid #edf1f6;
        }

        .admin-format-sport {
            color: #172033;
            font-size: 13px;
            font-weight: 800;
        }

        .admin-format-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 5px;
            color: #64748b;
            font-size: 11px;
        }

        .admin-format-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 28px;
            padding: 0 9px;
            border-radius: 999px;
            background: #eef4ff;
            color: #2563eb;
            font-size: 11px;
            font-weight: 800;
        }

        /*
        |--------------------------------------------------------------------------
        | Recent competitions
        |--------------------------------------------------------------------------
        */

        .admin-competition-list {
            display: flex;
            flex-direction: column;
        }

        .admin-competition {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 15px 20px;
            border-bottom: 1px solid #edf1f6;
        }

        .admin-competition:last-child {
            border-bottom: 0;
        }

        .admin-competition-icon {
            display: grid;
            place-items: center;
            flex: 0 0 42px;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #eef4ff;
            font-size: 18px;
        }

        .admin-competition-info {
            flex: 1;
            min-width: 0;
        }

        .admin-competition-name {
            overflow: hidden;
            color: #172033;
            font-size: 12px;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .admin-competition-meta {
            margin-top: 5px;
            color: #94a3b8;
            font-size: 11px;
            line-height: 1.5;
        }

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        .admin-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 9px;
            border-radius: 999px;
            max-width: 150px;
            font-size: 10px;
            font-weight: 800;
            line-height: 1.4;
            text-align: center;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .admin-status-success {
            background: #ecfdf3;
            color: #15803d;
        }

        .admin-status-warning {
            background: #fff7ed;
            color: #c2410c;
        }

        .admin-status-danger {
            background: #fef2f2;
            color: #dc2626;
        }

        .admin-status-neutral {
            background: #f1f5f9;
            color: #475569;
        }

        /*
        |--------------------------------------------------------------------------
        | Empty state
        |--------------------------------------------------------------------------
        */

        .admin-empty {
            padding: 30px 22px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
            text-align: center;
        }

        .admin-empty strong {
            display: block;
            margin-bottom: 5px;
            color: #172033;
            font-size: 14px;
        }

        /*
        |--------------------------------------------------------------------------
        | Error notice
        |--------------------------------------------------------------------------
        */

        .admin-dashboard-notice {
            padding: 12px 15px;
            border: 1px solid #fed7aa;
            border-radius: 12px;
            background: #fff7ed;
            color: #9a3412;
            font-size: 12px;
            line-height: 1.5;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1100px) {

            .admin-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .admin-responsibilities {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {

            .admin-format-list {
                grid-template-columns: 1fr;
            }

            .admin-format-row:nth-child(odd) {
                border-right: 0;
            }
        }

        @media (max-width: 700px) {

            .admin-dashboard {
                gap: 19px;
            }

            .admin-hero {
                padding: 24px 21px;
                border-radius: 18px;
            }

            .admin-pending-notice {
                align-items: stretch;
                flex-direction: column;
            }

            .admin-button {
                width: 100%;
            }

            .admin-stats {
                gap: 12px;
            }

            .admin-stat-card {
                padding: 16px;
            }

            .admin-stat-number {
                font-size: 25px;
            }

            .admin-panel-header {
                padding: 17px;
            }

            .admin-competition {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .admin-competition .admin-status {
                margin-left: 55px;
            }
        }

        @media (max-width: 420px) {

            .admin-stats {
                grid-template-columns: 1fr;
            }

            .admin-stat-card {
                display: grid;
                grid-template-columns: 44px 1fr;
                column-gap: 13px;
                align-items: center;
            }

            .admin-stat-icon {
                grid-row: span 2;
                margin: 0;
            }

            .admin-stat-number {
                font-size: 24px;
            }

            .admin-stat-title {
                margin-top: 3px;
            }

            .admin-panel-header {
                align-items: flex-start;
            }

            .admin-format-row {
                padding: 15px 16px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .admin-dashboard *,
            .admin-dashboard *::before,
            .admin-dashboard *::after {
                animation: none !important;
                transition: none !important;
                scroll-behavior: auto !important;
            }
        }

    </style>

</head>

<body>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="admin-dashboard">

    <!-- =========================================================
         WELCOME
    ========================================================== -->

    <section class="admin-hero">

        <div class="admin-hero-content">

            <div class="admin-hero-label">
                🛡️ Admin Control Center
            </div>

            <h1>
                Welcome back,
                <?= adminDashboardEscape($user['full_name'] ?? 'Administrator'); ?>!
            </h1>

            <p>
                Manage the SportSync system, sports, event formats,
                student accounts, and teams from one place.
            </p>

        </div>

    </section>


    <!-- =========================================================
         ERROR NOTICE
    ========================================================== -->

    <?php if ($dashboardError): ?>

        <div
            class="admin-dashboard-notice"
            role="status"
        >
            Some dashboard information could not be loaded.
            Please refresh the page or check the application error log.
        </div>

    <?php endif; ?>


    <!-- =========================================================
         SYSTEM OVERVIEW
    ========================================================== -->

    <section aria-labelledby="admin-overview-title">

        <div class="admin-section-heading">

            <div>

                <h2 id="admin-overview-title">
                    System overview
                </h2>

                <p>
                    A quick view of the main SportSync records.
                </p>

            </div>

        </div>


        <div class="admin-stats">

            <!-- Students -->

            <article class="admin-stat-card">

                <div
                    class="admin-stat-icon"
                    aria-hidden="true"
                >
                    👨‍🎓
                </div>

                <p class="admin-stat-number">
                    <?= number_format($totalStudents); ?>
                </p>

                <p class="admin-stat-title">
                    Registered students
                </p>

            </article>


            <!-- Sports -->

            <article class="admin-stat-card">

                <div
                    class="admin-stat-icon"
                    aria-hidden="true"
                >
                    🏅
                </div>

                <p class="admin-stat-number">
                    <?= number_format($activeSports); ?>
                </p>

                <p class="admin-stat-title">
                    Active sports
                </p>

            </article>


            <!-- Event formats -->

            <article class="admin-stat-card">

                <div
                    class="admin-stat-icon"
                    aria-hidden="true"
                >
                    📋
                </div>

                <p class="admin-stat-number">
                    <?= number_format($totalEventFormats); ?>
                </p>

                <p class="admin-stat-title">
                    Active event formats
                </p>

            </article>


            <!-- Teams -->

            <article class="admin-stat-card">

                <div
                    class="admin-stat-icon"
                    aria-hidden="true"
                >
                    👥
                </div>

                <p class="admin-stat-number">
                    <?= number_format($totalTeams); ?>
                </p>

                <p class="admin-stat-title">
                    Teams
                </p>

            </article>

        </div>

    </section>


    <!-- =========================================================
         PENDING STUDENTS
    ========================================================== -->

    <?php if ($pendingStudents > 0): ?>

        <section
            class="admin-pending-notice"
            aria-labelledby="admin-pending-title"
        >

            <div
                class="admin-pending-notice-icon"
                aria-hidden="true"
            >
                ⏳
            </div>

            <div class="admin-pending-notice-content">

                <h2 id="admin-pending-title">
                    Student approvals needed
                </h2>

                <p>

                    <?= number_format($pendingStudents); ?>

                    student account
                    <?= $pendingStudents === 1 ? 'is' : 'are'; ?>

                    waiting for review.

                </p>

            </div>

            <a
                class="admin-button"
                href="admin-pending-students.php"
            >
                Review students
                <span aria-hidden="true">→</span>
            </a>

        </section>

    <?php endif; ?>


    <!-- =========================================================
         ADMIN RESPONSIBILITIES
    ========================================================== -->

    <section aria-labelledby="admin-responsibilities-title">

        <div class="admin-section-heading">

            <div>

                <h2 id="admin-responsibilities-title">
                    What you manage
                </h2>

                <p>
                    These are the main responsibilities of the Admin.
                </p>

            </div>

        </div>


        <div class="admin-responsibilities">

            <!-- Students -->

            <article class="admin-responsibility">

                <div class="admin-responsibility-top">

                    <div
                        class="admin-responsibility-icon"
                        aria-hidden="true"
                    >
                        👨‍🎓
                    </div>

                    <h3>
                        Student Accounts
                    </h3>

                </div>

                <p>
                    Review new student accounts and control
                    access to the SportSync system.
                </p>

                <a
                    class="admin-responsibility-link"
                    href="admin-pending-students.php"
                >
                    Manage students →
                </a>

            </article>


            <!-- Sports -->

            <article class="admin-responsibility">

                <div class="admin-responsibility-top">

                    <div
                        class="admin-responsibility-icon"
                        aria-hidden="true"
                    >
                        🏅
                    </div>

                    <h3>
                        Sports & Formats
                    </h3>

                </div>

                <p>
                    Manage sports and define their event formats,
                    such as Solo and Team events.
                </p>

                <a
                    class="admin-responsibility-link"
                    href="admin-sports.php"
                >
                    Manage sports →
                </a>

            </article>


            <!-- Teams -->

            <article class="admin-responsibility">

                <div class="admin-responsibility-top">

                    <div
                        class="admin-responsibility-icon"
                        aria-hidden="true"
                    >
                        👥
                    </div>

                    <h3>
                        Teams
                    </h3>

                </div>

                <p>
                    View and manage the teams available
                    in the college sports system.
                </p>

                <a
                    class="admin-responsibility-link"
                    href="admin-teams.php"
                >
                    Manage teams →
                </a>

            </article>

        </div>

    </section>


    <!-- =========================================================
         EVENT FORMAT OVERVIEW
    ========================================================== -->

    <section
        class="admin-format-panel"
        aria-labelledby="admin-format-title"
    >

        <div class="admin-panel-header">

            <div>

                <h2 id="admin-format-title">
                    Sport event formats
                </h2>

                <p>
                    Active Solo and Team formats configured for each sport.
                </p>

            </div>

            <a
                class="admin-panel-link"
                href="admin-sports.php"
            >
                Manage formats →
            </a>

        </div>


        <?php if ($eventFormatSummary): ?>

            <div class="admin-format-list">

                <?php foreach ($eventFormatSummary as $format): ?>

                    <div class="admin-format-row">

                        <div>

                            <div class="admin-format-sport">

                                <?= adminDashboardEscape(
                                    $format['sport_name'] ?? 'Sport'
                                ); ?>

                            </div>

                            <div class="admin-format-type">

                                <?php if (($format['event_type'] ?? '') === 'SOLO'): ?>

                                    👤 Solo event

                                <?php else: ?>

                                    👥 Team event

                                <?php endif; ?>

                            </div>

                        </div>

                        <span class="admin-format-count">

                            <?= number_format(
                                (int) ($format['format_count'] ?? 0)
                            ); ?>

                        </span>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="admin-empty">

                <strong>
                    No event formats found
                </strong>

                Add event formats such as:

                <br>

                <b>100m Running → Solo</b>

                <br>

                <b>4x100m Relay → Team</b>

                <br><br>

                <a
                    class="admin-button"
                    href="admin-sports.php"
                >
                    Add event format →
                </a>

            </div>

        <?php endif; ?>

    </section>


    <!-- =========================================================
         RECENT COMPETITIONS
    ========================================================== -->

    <section
        class="admin-format-panel"
        aria-labelledby="admin-competitions-title"
    >

        <div class="admin-panel-header">

            <div>

                <h2 id="admin-competitions-title">
                    Recent competitions
                </h2>

                <p>
                    Latest competitions created in SportSync.
                </p>

            </div>

            <a
                class="admin-panel-link"
                href="admin-tournaments.php"
            >
                View all →
            </a>

        </div>


        <?php if ($recentCompetitions): ?>

            <div class="admin-competition-list">

                <?php foreach ($recentCompetitions as $competition): ?>

                    <article class="admin-competition">

                        <div
                            class="admin-competition-icon"
                            aria-hidden="true"
                        >
                            🏆
                        </div>

                        <div class="admin-competition-info">

                            <div class="admin-competition-name">

                                <?= adminDashboardEscape(
                                    $competition['event_title'] ?? 'Competition'
                                ); ?>

                            </div>

                            <div class="admin-competition-meta">

                                <?= adminDashboardEscape(
                                    $competition['sport_name'] ?? 'Sport not specified'
                                ); ?>

                                &nbsp;·&nbsp;

                                <?= adminDashboardEscape(
                                    adminDashboardDate(
                                        $competition['event_start'] ?? null
                                    )
                                ); ?>

                            </div>

                        </div>

                        <span
                            class="admin-status
                            <?= adminDashboardEscape(
                                adminDashboardStatusClass(
                                    $competition['event_status'] ?? null
                                )
                            ); ?>"
                        >

                            <?= adminDashboardEscape(
                                str_replace(
                                    '_',
                                    ' ',
                                    $competition['event_status'] ?? 'Unknown'
                                )
                            ); ?>

                        </span>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="admin-empty">

                <strong>
                    No competitions yet
                </strong>

                Competitions created by the Sports Coordinator
                will appear here.

            </div>

        <?php endif; ?>

    </section>


    <!-- =========================================================
         SIMPLE ROLE GUIDE
    ========================================================== -->

    <section
        class="admin-format-panel"
        aria-labelledby="admin-role-guide-title"
    >

        <div class="admin-panel-header">

            <div>

                <h2 id="admin-role-guide-title">
                    SportSync role guide
                </h2>

                <p>
                    Keep responsibilities simple and separate.
                </p>

            </div>

        </div>


        <div class="admin-format-list">

            <div class="admin-format-row">

                <div>

                    <div class="admin-format-sport">
                        🛡️ Admin
                    </div>

                    <div class="admin-format-type">
                        Manages accounts, sports, event formats and system records.
                    </div>

                </div>

            </div>


            <div class="admin-format-row">

                <div>

                    <div class="admin-format-sport">
                        📅 Sports Coordinator
                    </div>

                    <div class="admin-format-type">
                        Creates competitions, manages registrations and schedules events.
                    </div>

                </div>

            </div>


            <div class="admin-format-row">

                <div>

                    <div class="admin-format-sport">
                        👨‍🏫 Coach
                    </div>

                    <div class="admin-format-type">
                        Manages assigned teams, players and participation.
                    </div>

                </div>

            </div>


            <div class="admin-format-row">

                <div>

                    <div class="admin-format-sport">
                        🏃 Player
                    </div>

                    <div class="admin-format-type">
                        Selects sports, chooses available events and registers.
                    </div>

                </div>

            </div>

        </div>

    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>