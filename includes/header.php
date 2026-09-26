<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

requireLogin();

$user = currentUser();

$role = $user['role'] ?? '';

$currentPage = basename($_SERVER['PHP_SELF']);

/*
|--------------------------------------------------------------------------
| Role-Based Dashboard
|--------------------------------------------------------------------------
| Each role has one official dashboard.
|--------------------------------------------------------------------------
*/

$dashboardPage = match ($role) {
    'ADMIN' => 'admin-dashboard.php',
    'SPORTS_COORDINATOR' => 'coordinator-dashboard.php',
    'COACH' => 'coach-dashboard.php',
    'PLAYER' => 'player-dashboard.php',
    default => 'dashboard.php',
};

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function headerEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function isCurrentPage(string $page): bool
{
    global $currentPage;

    return $currentPage === $page;
}

function navClass(string $page): string
{
    return isCurrentPage($page) ? 'active' : '';
}

function formatRoleName(string $role): string
{
    return match ($role) {
        'ADMIN' => 'Administrator',
        'SPORTS_COORDINATOR' => 'Sports Coordinator',
        'COACH' => 'Coach',
        'PLAYER' => 'Player',
        default => ucwords(strtolower(str_replace('_', ' ', $role))),
    };
}

/*
|--------------------------------------------------------------------------
| Load the latest name from the database
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';

$fullName = 'User';

try {
    $nameQuery = db()->prepare(
        'SELECT full_name
         FROM users
         WHERE user_id = :user_id
         LIMIT 1'
    );

    $nameQuery->execute([
        'user_id' => (int) ($user['id'] ?? 0),
    ]);

    $databaseName = trim((string) $nameQuery->fetchColumn());

    if ($databaseName !== '') {
        $fullName = $databaseName;
    }
} catch (Throwable $error) {
    // Keep the header usable if the name cannot be loaded.
}

$roleLabel = formatRoleName($role);

$initial = strtoupper(substr($fullName, 0, 1));

if ($initial === '') {
    $initial = 'U';
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

    <meta
        name="description"
        content="SportSync - Smart Sports Management System"
    >

    <meta
        name="theme-color"
        content="#2563eb"
    >

    <title>SportSync - Smart Sports Management System</title>

    <link
        rel="icon"
        type="image/svg+xml"
        href="../assets/images/sportsync-mark.svg"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=3"
    >

    <!--
    |--------------------------------------------------------------------------
    | SportSync Application Shell Alignment
    |--------------------------------------------------------------------------
    | These rules intentionally keep the existing design while making the
    | sidebar header and main page header use exactly the same height.
    |--------------------------------------------------------------------------
    -->

    <style>

        :root {
            --sportsync-sidebar-width: 260px;
            --sportsync-header-height: 72px;
        }

        /* ================================================================
           GLOBAL APP LAYOUT
        ================================================================ */

        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100%;
        }

        body {
            overflow-x: hidden;
        }

        .app-layout {
            min-height: 100vh !important;
            width: 100% !important;
            position: relative !important;
        }

        /* ================================================================
           SIDEBAR
        ================================================================ */

        .app-layout .sidebar {
            width: var(--sportsync-sidebar-width) !important;
            min-width: var(--sportsync-sidebar-width) !important;
            max-width: var(--sportsync-sidebar-width) !important;

            height: 100vh !important;
            min-height: 100vh !important;

            position: fixed !important;
            top: 0 !important;
            left: 0 !important;

            z-index: 1000 !important;

            box-sizing: border-box !important;

            overflow-x: hidden !important;
            overflow-y: auto !important;
        }

        /* ================================================================
           SIDEBAR BRAND / HEADER
        ================================================================ */

        .app-layout .sidebar-brand {
            width: 100% !important;

            height: var(--sportsync-header-height) !important;
            min-height: var(--sportsync-header-height) !important;
            max-height: var(--sportsync-header-height) !important;

            box-sizing: border-box !important;

            margin: 0 !important;
            padding: 0 16px !important;

            display: flex !important;
            align-items: center !important;

            overflow: hidden !important;
        }

        .app-layout .sidebar-brand-link {
            width: 100% !important;
            height: 100% !important;

            display: flex !important;
            flex-direction: row !important;

            align-items: center !important;
            justify-content: flex-start !important;

            gap: 10px !important;

            margin: 0 !important;
            padding: 0 !important;

            text-decoration: none !important;

            box-sizing: border-box !important;

            overflow: hidden !important;
        }

        .app-layout .sidebar-logo {
            width: 42px !important;
            height: 42px !important;

            min-width: 42px !important;
            min-height: 42px !important;

            max-width: 42px !important;
            max-height: 42px !important;

            flex: 0 0 42px !important;

            display: block !important;

            object-fit: contain !important;

            margin: 0 !important;
            padding: 0 !important;
        }

        .app-layout .sidebar-brand-text {
            min-width: 0 !important;

            display: flex !important;
            flex-direction: column !important;

            justify-content: center !important;
            align-items: flex-start !important;

            gap: 2px !important;

            overflow: hidden !important;
        }

        .app-layout .sidebar-brand-name {
            display: block !important;

            margin: 0 !important;
            padding: 0 !important;

            color: #ffffff !important;

            font-size: 19px !important;
            line-height: 1.1 !important;
            font-weight: 800 !important;

            letter-spacing: -0.3px !important;

            white-space: nowrap !important;
        }

        .app-layout .sidebar-brand-tagline {
            display: block !important;

            margin: 0 !important;
            padding: 0 !important;

            color: rgba(255, 255, 255, 0.68) !important;

            font-size: 9px !important;
            line-height: 1.2 !important;
            font-weight: 500 !important;

            white-space: nowrap !important;
        }

        /* ================================================================
           SIDEBAR NAVIGATION
        ================================================================ */

        .app-layout .sidebar-nav {
            width: 100% !important;
            box-sizing: border-box !important;
        }

        .app-layout .sidebar-nav a {
            box-sizing: border-box !important;
        }

        /* ================================================================
           MAIN CONTENT
        ================================================================ */

        .app-layout .main-content {
            width: calc(100% - var(--sportsync-sidebar-width)) !important;

            min-width: 0 !important;

            margin-left: var(--sportsync-sidebar-width) !important;

            min-height: 100vh !important;

            box-sizing: border-box !important;
        }

        /* ================================================================
           MAIN TOP HEADER
        ================================================================ */

        .app-layout .topbar {
            width: 100% !important;
            height: var(--sportsync-header-height) !important;
            min-height: var(--sportsync-header-height) !important;
            max-height: var(--sportsync-header-height) !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            padding: 0 24px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;

            position: sticky !important;
            top: 0 !important;
            background: #ffffff !important;
            z-index: 1100 !important;
        }

        .app-layout .topbar-left {
            min-width: 0 !important;

            height: 100% !important;

            display: flex !important;
            align-items: center !important;

            margin: 0 !important;
            padding: 0 !important;
        }

        .app-layout .topbar-right {
            height: 100% !important;

            display: flex !important;
            align-items: center !important;

            margin-left: auto !important;
        }

        /* ================================================================
           DESKTOP MENU BUTTON
           ================================================================

           Sidebar is permanently visible on desktop, therefore the menu
           button is unnecessary there.
        */

        .app-layout .menu-toggle {
            display: none !important;
        }

        /* ================================================================
           TOPBAR USER
        ================================================================ */

        .app-layout .topbar-user {
            height: 100% !important;

            display: flex !important;
            align-items: center !important;

            gap: 10px !important;

            margin: 0 !important;
            padding: 0 !important;
        }

        .app-layout .topbar-avatar {
            width: 38px !important;
            height: 38px !important;

            min-width: 38px !important;
            min-height: 38px !important;

            border-radius: 50% !important;

            display: flex !important;
            align-items: center !important;
            justify-content: center !important;

            box-sizing: border-box !important;

            font-weight: 800 !important;
        }

        .app-layout .topbar-user-info {
            display: flex !important;
            flex-direction: column !important;

            justify-content: center !important;

            line-height: 1.15 !important;
        }

        .app-layout .topbar-user-name {
            margin: 0 !important;
            padding: 0 !important;

            font-size: 13px !important;
            font-weight: 700 !important;

            white-space: nowrap !important;
        }

        .app-layout .topbar-user-role {
            margin: 3px 0 0 !important;
            padding: 0 !important;

            font-size: 10px !important;

            white-space: nowrap !important;
        }

        /* ================================================================
           PAGE CONTAINER
        ================================================================ */

        .app-layout .page-container {
            box-sizing: border-box !important;
        }

        /* ================================================================
           MOBILE
        ================================================================ */

        @media (max-width: 1024px) {

            :root {
                --sportsync-header-height: 64px;
            }

            .app-layout .sidebar {
                width: 260px !important;
                min-width: 260px !important;
                max-width: 260px !important;

                transform: translateX(-100%) !important;

                transition: transform 0.25s ease !important;
            }

            .app-layout .sidebar.open,
            .app-layout .sidebar.active,
            .app-layout .sidebar.sidebar-open {
                transform: translateX(0) !important;
            }

            .app-layout .main-content {
                width: 100% !important;
                margin-left: 0 !important;
            }

            .app-layout .menu-toggle {
                width: 40px !important;
                height: 40px !important;

                display: inline-flex !important;

                align-items: center !important;
                justify-content: center !important;

                margin: 0 12px 0 0 !important;
                padding: 0 !important;

                border: 0 !important;
                background: transparent !important;

                cursor: pointer !important;

                font-size: 22px !important;
            }

            .app-layout .topbar {
                padding: 0 18px !important;
            }

            .app-layout .topbar-user-info {
                display: none !important;
            }
        }

        /* ================================================================
           SMALL MOBILE
        ================================================================ */

        @media (max-width: 600px) {

            .app-layout .sidebar {
                width: 280px !important;
                max-width: 86vw !important;
            }

            .app-layout .sidebar-brand {
                padding: 0 14px !important;
            }

            .app-layout .sidebar-logo {
                width: 40px !important;
                height: 40px !important;

                min-width: 40px !important;
                min-height: 40px !important;

                max-width: 40px !important;
                max-height: 40px !important;
            }

            .app-layout .sidebar-brand-name {
                font-size: 18px !important;
            }

            .app-layout .sidebar-brand-tagline {
                font-size: 8px !important;
            }

            .app-layout .topbar {
                padding: 0 14px !important;
            }

            .app-layout .topbar-avatar {
                width: 36px !important;
                height: 36px !important;

                min-width: 36px !important;
                min-height: 36px !important;
            }
        }

        /* ================================================================
           ACCESSIBILITY
        ================================================================ */

        @media (prefers-reduced-motion: reduce) {

            .app-layout .sidebar {
                transition: none !important;
            }

        }

    </style>

</head>

<body>

<div class="app-layout">

    <!-- ================================================================
         SIDEBAR
         ================================================================ -->

    <aside
        class="sidebar"
        id="sidebar"
    >

        <!-- ============================================================
             SIDEBAR BRAND
             ============================================================ -->

        <div class="sidebar-brand">

            <a
                href="<?= headerEscape($dashboardPage) ?>"
                class="sidebar-brand-link"
                aria-label="SportSync Dashboard"
            >

                <img
                    src="../assets/images/sportsync-mark.svg"
                    alt="SportSync Logo"
                    class="sidebar-logo"
                >

                <span class="sidebar-brand-text">

                    <span class="sidebar-brand-name">
                        SportSync
                    </span>

                    <span class="sidebar-brand-tagline">
                        Smart Sports Management System
                    </span>

                </span>

            </a>

        </div>


        <!-- ============================================================
             SIDEBAR NAVIGATION
             ============================================================ -->

        <nav
            class="sidebar-nav"
            aria-label="Main navigation"
        >

            <?php if ($role === 'ADMIN'): ?>

                <!-- ====================================================
                     ADMIN
                     ==================================================== -->

                <div class="sidebar-section-title">
                    Main
                </div>

                <a
                    href="admin-dashboard.php"
                    class="<?= navClass('admin-dashboard.php') ?>"
                >
                    <span aria-hidden="true">🏠</span>
                    <span>Dashboard</span>
                </a>


                <div class="sidebar-section-title">
                    Student Management
                </div>

                <a
                    href="admin-pending-students.php"
                    class="<?= navClass('admin-pending-students.php') ?>"
                >
                    <span aria-hidden="true">⏳</span>
                    <span>Pending Students</span>
                </a>

                <a
                    href="admin-students.php"
                    class="<?= navClass('admin-students.php') ?>"
                >
                    <span aria-hidden="true">👥</span>
                    <span>Approved Students</span>
                </a>

                <a
                    href="admin-rejected-students.php"
                    class="<?= navClass('admin-rejected-students.php') ?>"
                >
                    <span aria-hidden="true">🚫</span>
                    <span>Rejected / Suspended</span>
                </a>


                <div class="sidebar-section-title">
                    Sports Management
                </div>

                <a
                    href="admin-teams.php"
                    class="<?= navClass('admin-teams.php') ?>"
                >
                    <span aria-hidden="true">🛡️</span>
                    <span>Teams</span>
                </a>

                <a
                    href="admin-tournaments.php"
                    class="<?= navClass('admin-tournaments.php') ?>"
                >
                    <span aria-hidden="true">🏆</span>
                    <span>Tournaments</span>
                </a>


                <div class="sidebar-section-title">
                    Account
                </div>

                <a
                    href="admin-profile.php"
                    class="<?= navClass('admin-profile.php') ?>"
                >
                    <span aria-hidden="true">👤</span>
                    <span>My Profile</span>
                </a>


            <?php elseif ($role === 'SPORTS_COORDINATOR'): ?>

                <!-- ====================================================
                     SPORTS COORDINATOR
                     ==================================================== -->

                <div class="sidebar-section-title">
                    Main
                </div>

                <a
                    href="coordinator-dashboard.php"
                    class="<?= navClass('coordinator-dashboard.php') ?>"
                >
                    <span aria-hidden="true">🏠</span>
                    <span>Dashboard</span>
                </a>


                <div class="sidebar-section-title">
                    Competitions
                </div>

                <a
                    href="admin-tournaments.php"
                    class="<?= navClass('admin-tournaments.php') ?>"
                >
                    <span aria-hidden="true">🏆</span>
                    <span>Tournaments</span>
                </a>

                <a
                    href="tournament-standings.php"
                    class="<?= navClass('tournament-standings.php') ?>"
                >
                    <span aria-hidden="true">📊</span>
                    <span>Standings</span>
                </a>


                <div class="sidebar-section-title">
                    Matches
                </div>

                <a
                    href="schedule-match.php"
                    class="<?= navClass('schedule-match.php') ?>"
                >
                    <span aria-hidden="true">📅</span>
                    <span>Schedule Match</span>
                </a>

                <a
                    href="match-results.php"
                    class="<?= navClass('match-results.php') ?>"
                >
                    <span aria-hidden="true">🏁</span>
                    <span>Match Results</span>
                </a>

                <a
                    href="edit-match-result.php"
                    class="<?= navClass('edit-match-result.php') ?>"
                >
                    <span aria-hidden="true">✏️</span>
                    <span>Result Corrections</span>
                </a>


                <div class="sidebar-section-title">
                    Sports
                </div>

                <a
                    href="admin-teams.php"
                    class="<?= navClass('admin-teams.php') ?>"
                >
                    <span aria-hidden="true">🛡️</span>
                    <span>Teams</span>
                </a>

                <a
                    href="admin-students.php"
                    class="<?= navClass('admin-students.php') ?>"
                >
                    <span aria-hidden="true">👥</span>
                    <span>Players</span>
                </a>


            <?php elseif ($role === 'COACH'): ?>

                <!-- ====================================================
                     COACH
                     ==================================================== -->

                <div class="sidebar-section-title">
                    Main
                </div>

                <a
                    href="coach-dashboard.php"
                    class="<?= navClass('coach-dashboard.php') ?>"
                >
                    <span aria-hidden="true">🏠</span>
                    <span>Dashboard</span>
                </a>


                <div class="sidebar-section-title">
                    My Team
                </div>

                <a
                    href="coach-teams.php"
                    class="<?= navClass('coach-teams.php') ?>"
                >
                    <span aria-hidden="true">🛡️</span>
                    <span>My Teams</span>
                </a>

                <a
                    href="coach-players.php"
                    class="<?= navClass('coach-players.php') ?>"
                >
                    <span aria-hidden="true">👥</span>
                    <span>My Players</span>
                </a>


                <div class="sidebar-section-title">
                    My Matches
                </div>

                <a
                    href="coach-matches.php"
                    class="<?= navClass('coach-matches.php') ?>"
                >
                    <span aria-hidden="true">📅</span>
                    <span>My Matches</span>
                </a>


                <div class="sidebar-section-title">
                    Performance
                </div>

                <a
                    href="coach-statistics.php"
                    class="<?= navClass('coach-statistics.php') ?>"
                >
                    <span aria-hidden="true">📊</span>
                    <span>Team Statistics</span>
                </a>
                
                <div class="sidebar-section-title">
                    Account
                </div>

                <a
                    href="coach-profile.php"
                    class="<?= navClass('coach-profile.php') ?>"
                >
                    <span aria-hidden="true">👤</span>
                    <span>My Profile</span>
                </a>

            <?php elseif ($role === 'PLAYER'): ?>

                <!-- ====================================================
                     PLAYER
                     ==================================================== -->

                <div class="sidebar-section-title">
                    Main
                </div>

                <a
                    href="player-dashboard.php"
                    class="<?= navClass('player-dashboard.php') ?>"
                >
                    <span aria-hidden="true">🏠</span>
                    <span>Dashboard</span>
                </a>


                <div class="sidebar-section-title">
                    My Sports
                </div>

                <a
                    href="player-sports.php"
                    class="<?= navClass('player-sports.php') ?>"
                >
                    <span aria-hidden="true">⚽</span>
                    <span>My Sports</span>
                </a>


                <div class="sidebar-section-title">
                    My Team
                </div>

                <a
                    href="player-teams.php"
                    class="<?= navClass('player-teams.php') ?>"
                >
                    <span aria-hidden="true">🛡️</span>
                    <span>My Team</span>
                </a>


                <div class="sidebar-section-title">
                    My Competitions
                </div>

                <a
                    href="player-tournaments.php"
                    class="<?= navClass('player-tournaments.php') ?>"
                >
                    <span aria-hidden="true">🏆</span>
                    <span>My Tournaments</span>
                </a>

                <a
                    href="player-matches.php"
                    class="<?= navClass('player-matches.php') ?>"
                >
                    <span aria-hidden="true">📅</span>
                    <span>My Matches</span>
                </a>


                <div class="sidebar-section-title">
                    My Performance
                </div>

                <a
                    href="player-statistics.php"
                    class="<?= navClass('player-statistics.php') ?>"
                >
                    <span aria-hidden="true">📊</span>
                    <span>My Statistics</span>
                </a>


                <div class="sidebar-section-title">
                    Account
                </div>

                <a
                    href="player-profile.php"
                    class="<?= navClass('player-profile.php') ?>"
                >
                    <span aria-hidden="true">👤</span>
                    <span>My Profile</span>
                </a>

            <?php endif; ?>

        </nav>


        <!-- ============================================================
             SIDEBAR FOOTER
             ============================================================ -->

        <div class="sidebar-footer">

            <a
                href="logout.php"
                class="sidebar-logout"
            >
                <span aria-hidden="true">🚪</span>
                <span>Logout</span>
            </a>

        </div>

    </aside>


    <!-- ================================================================
         MOBILE OVERLAY
         ================================================================ -->

    <div
        class="sidebar-overlay"
        id="sidebarOverlay"
        aria-hidden="true"
    ></div>


    <!-- ================================================================
         MAIN CONTENT
         ================================================================ -->

    <main class="main-content">

        <!-- ============================================================
             TOP HEADER
             ============================================================ -->

        <header class="topbar">

            <div class="topbar-left">

                <!--
                    Hidden on desktop.
                    Used only for mobile sidebar navigation.
                -->

                <button
                    type="button"
                    class="menu-toggle"
                    id="menuButton"
                    aria-label="Open navigation menu"
                    aria-controls="sidebar"
                    aria-expanded="false"
                >
                    ☰
                </button>

            </div>


            <div class="topbar-right">

                <div class="topbar-user">

                    <div
                        class="topbar-avatar"
                        aria-hidden="true"
                    >
                        <?= headerEscape($initial) ?>
                    </div>

                    <div class="topbar-user-info">

                        <strong class="topbar-user-name">
                            <?= headerEscape($fullName) ?>
                        </strong>

                        <span class="topbar-user-role">
                            <?= headerEscape($roleLabel) ?>
                        </span>

                    </div>

                </div>

            </div>

        </header>


        <!-- ============================================================
             PAGE CONTAINER
             ============================================================ -->

        <div class="page-container">