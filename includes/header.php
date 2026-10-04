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
        default => ucwords(
            strtolower(
                str_replace('_', ' ', $role)
            )
        ),
    };
}

/*
|--------------------------------------------------------------------------
| Load Latest User Name
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

    $databaseName = trim(
        (string) $nameQuery->fetchColumn()
    );

    if ($databaseName !== '') {
        $fullName = $databaseName;
    }

} catch (Throwable $error) {

    // Keep header usable if database name cannot be loaded.

}

$roleLabel = formatRoleName($role);

$initial = strtoupper(
    substr($fullName, 0, 1)
);

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

    <title>
        SportSync - Smart Sports Management System
    </title>

    <link
        rel="icon"
        type="image/svg+xml"
        href="../assets/images/sportsync-mark.svg"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=3"
    >

    <style>

        /* ================================================================
           SPORTSYNC APPLICATION SHELL
        ================================================================ */

        :root {

            --sportsync-sidebar-width: 260px;

            --sportsync-header-height: 72px;

        }


        /* ================================================================
           GLOBAL
        ================================================================ */

        html,
        body {

            margin: 0;
            padding: 0;

            min-height: 100%;

        }

        html {

            overflow-x: hidden;

        }

        body {

            overflow-x: hidden;

        }


        /*
         * Lock the complete page when mobile sidebar is open.
         *
         * JavaScript adds this class to both html and body.
         */
        html.sidebar-menu-open,
        body.sidebar-menu-open {

            overflow: hidden !important;

            width: 100% !important;

            height: 100% !important;

            overscroll-behavior: none !important;

        }


        body.sidebar-menu-open {

            position: fixed !important;

            top: 0 !important;

            left: 0 !important;

            right: 0 !important;

            height: 100vh !important;

            height: 100dvh !important;

            touch-action: none !important;

        }


        .app-layout {

            position: relative !important;

            width: 100% !important;

            min-height: 100vh !important;

            margin: 0 !important;

            padding: 0 !important;

        }


        /* ================================================================
           DESKTOP SIDEBAR
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

            z-index: 2000 !important;

            box-sizing: border-box !important;

            overflow: hidden !important;

            /*
             * Sidebar itself is a vertical flex container.
             * Navigation can scroll independently while footer stays visible.
             */
            display: flex !important;

            flex-direction: column !important;

        }


        /* ================================================================
           SIDEBAR BRAND
        ================================================================ */

        .app-layout .sidebar-brand {

            width: 100% !important;

            height: var(--sportsync-header-height) !important;

            min-height: var(--sportsync-header-height) !important;

            max-height: var(--sportsync-header-height) !important;

            flex: 0 0 var(--sportsync-header-height) !important;

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

            /*
             * This is the only part of the sidebar that is allowed
             * to scroll.
             */
            flex: 1 1 auto !important;

            min-height: 0 !important;

            overflow-x: hidden !important;

            overflow-y: auto !important;

            -webkit-overflow-scrolling: touch !important;

            /*
             * Hide scrollbar while keeping scrolling enabled.
             */
            scrollbar-width: none !important;

            -ms-overflow-style: none !important;

        }


        /*
         * Hide scrollbar in Chrome, Edge and Safari.
         *
         * IMPORTANT:
         * This does NOT disable sidebar scrolling.
         */
        .app-layout .sidebar-nav::-webkit-scrollbar {

            display: none !important;

            width: 0 !important;

            height: 0 !important;

        }


        .app-layout .sidebar-nav a {

            box-sizing: border-box !important;

        }


        /*
         * Prevent accidental horizontal scrolling inside sidebar.
         */
        .app-layout .sidebar-nav,
        .app-layout .sidebar-nav * {

            max-width: 100%;

        }


        /* ================================================================
           MAIN CONTENT
        ================================================================ */

        .app-layout .main-content {

            width: calc(
                100% - var(--sportsync-sidebar-width)
            ) !important;

            min-width: 0 !important;

            margin-left: var(--sportsync-sidebar-width) !important;

            min-height: 100vh !important;

            box-sizing: border-box !important;

        }


        /* ================================================================
           TOPBAR
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

    z-index: 3000 !important;

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
        ================================================================ */

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
           SIDEBAR FOOTER
        ================================================================ */

        .app-layout .sidebar-footer {

            /*
             * Footer never participates in the navigation scroll.
             * It always remains visible at the bottom.
             */
            flex: 0 0 auto !important;

            width: 100% !important;

            box-sizing: border-box !important;

            margin-top: auto !important;

            padding: 14px 16px !important;

            overflow: hidden !important;

        }


        .app-layout .sidebar-logout {

            display: flex !important;

            align-items: center !important;

            justify-content: flex-start !important;

            gap: 12px !important;

            width: 100% !important;

            min-height: 46px !important;

            padding: 12px 14px !important;

            box-sizing: border-box !important;

            border-radius: 10px !important;

            text-decoration: none !important;

            touch-action: manipulation !important;

            -webkit-tap-highlight-color: transparent !important;

        }


        /* ================================================================
           MOBILE SIDEBAR
        ================================================================ */

        @media (max-width: 1024px) {

            :root {

                --sportsync-header-height: 64px;

            }


            /* ------------------------------------------------------------
               Main content
            ------------------------------------------------------------ */

            .app-layout .main-content {

                width: 100% !important;

                margin-left: 0 !important;

                min-width: 0 !important;

            }


            /* ------------------------------------------------------------
               SIDEBAR CLOSED
            ------------------------------------------------------------ */

            .app-layout .sidebar {

                position: fixed !important;

                top: 0 !important;

                left: 0 !important;

                width: 280px !important;

                min-width: 0 !important;

                max-width: 86vw !important;

                height: 100vh !important;

                height: 100dvh !important;

                min-height: 100vh !important;

                max-height: 100dvh !important;

                display: flex !important;

                flex-direction: column !important;

                box-sizing: border-box !important;

                overflow: hidden !important;

                /*
                 * Sidebar must be above the page and header.
                 */
                z-index: 2000 !important;

                transform: translate3d(
                    -110%,
                    0,
                    0
                ) !important;

                visibility: hidden !important;

                opacity: 1 !important;

                transition:
                    transform 0.25s ease,
                    visibility 0s linear 0.25s !important;

                -webkit-overflow-scrolling: touch !important;

            }


            /* ------------------------------------------------------------
               SIDEBAR OPEN
            ------------------------------------------------------------ */

            .app-layout .sidebar.open,
            .app-layout .sidebar.active,
            .app-layout .sidebar.sidebar-open {

                transform: translate3d(
                    0,
                    0,
                    0
                ) !important;

                visibility: visible !important;

                transition:
                    transform 0.25s ease,
                    visibility 0s linear 0s !important;

            }


            /* ------------------------------------------------------------
               MOBILE SIDEBAR BRAND
            ------------------------------------------------------------ */

            .app-layout .sidebar-brand {

                flex: 0 0 var(--sportsync-header-height) !important;

            }


            /* ------------------------------------------------------------
               SIDEBAR NAV SCROLL
            ------------------------------------------------------------ */

            .app-layout .sidebar-nav {

                flex: 1 1 auto !important;

                min-height: 0 !important;

                width: 100% !important;

                overflow-x: hidden !important;

                overflow-y: auto !important;

                -webkit-overflow-scrolling: touch !important;

                /*
                 * Keep enough space between the last menu item and
                 * the permanently visible logout button.
                 */
                padding-bottom: 8px !important;

                /*
                 * Hide scrollbar but keep scrolling.
                 */
                scrollbar-width: none !important;

                -ms-overflow-style: none !important;

            }


            /* ------------------------------------------------------------
               MOBILE SIDEBAR FOOTER
            ------------------------------------------------------------ */

            .app-layout .sidebar-footer {

                display: block !important;

                flex: 0 0 auto !important;

                width: 100% !important;

                margin-top: 0 !important;

                padding:
                    12px
                    16px
                    calc(
                        12px +
                        env(safe-area-inset-bottom)
                    ) !important;

                box-sizing: border-box !important;

                /*
                 * Footer stays outside the scrolling navigation area.
                 */
                overflow: hidden !important;

                background: inherit !important;

            }


            .app-layout .sidebar-logout {

                display: flex !important;

                align-items: center !important;

                justify-content: flex-start !important;

                gap: 12px !important;

                width: 100% !important;

                min-height: 46px !important;

                padding: 12px 14px !important;

                box-sizing: border-box !important;

                border-radius: 10px !important;

                text-decoration: none !important;

                touch-action: manipulation !important;

                -webkit-tap-highlight-color:
                    transparent !important;

            }


            /* ------------------------------------------------------------
               MOBILE OVERLAY
            ------------------------------------------------------------ */

            .app-layout .sidebar-overlay {

                position: fixed !important;

                top: 0 !important;

                right: 0 !important;

                bottom: 0 !important;

                left: 0 !important;

                width: 100vw !important;

                height: 100vh !important;

                height: 100dvh !important;

                background:
                    rgba(
                        15,
                        23,
                        42,
                        0.55
                    ) !important;

                opacity: 0 !important;

                visibility: hidden !important;

                pointer-events: none !important;

                /*
                 * Overlay is below sidebar but above page.
                 */
                z-index: 1900 !important;

                transition:
                    opacity 0.25s ease,
                    visibility 0s linear 0.25s !important;

            }


            .app-layout .sidebar-overlay.is-visible {

                opacity: 1 !important;

                visibility: visible !important;

                pointer-events: auto !important;

                transition:
                    opacity 0.25s ease,
                    visibility 0s linear 0s !important;

            }


            /* ------------------------------------------------------------
               MOBILE MENU BUTTON
            ------------------------------------------------------------ */

            .app-layout .menu-toggle {

                position: relative !important;

                display: inline-flex !important;

                align-items: center !important;

                justify-content: center !important;

                width: 44px !important;

                height: 44px !important;

                min-width: 44px !important;

                min-height: 44px !important;

                margin: 0 !important;

                padding: 0 !important;

                border: 0 !important;

                outline: none !important;

                background: transparent !important;

                cursor: pointer !important;

                font-size: 24px !important;

                line-height: 1 !important;

                /*
                 * The button stays above the sidebar so it can
                 * be clicked again to close it.
                 */
                z-index: 2200 !important;

                touch-action: manipulation !important;

                -webkit-tap-highlight-color:
                    transparent !important;

                -webkit-user-select: none !important;

                user-select: none !important;

                pointer-events: auto !important;

            }


            .app-layout .menu-toggle:focus {

                outline: none !important;

            }


            /* ------------------------------------------------------------
               TOPBAR
            ------------------------------------------------------------ */

            .app-layout .topbar {

                width: 100% !important;

                padding: 0 18px !important;

                position: sticky !important;

                top: 0 !important;

                /*
                 * Keep topbar below sidebar and menu button.
                 */
                z-index: 1800 !important;

                background: #ffffff !important;

            }


            /*
             * When sidebar is open, the topbar should not cover
             * the sidebar.
             */
            body.sidebar-menu-open .app-layout .topbar {

                background: transparent !important;

            }


            /*
             * Keep the user information from interfering with
             * the open sidebar.
             */
            body.sidebar-menu-open .app-layout .topbar-right {

                visibility: hidden !important;

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


            .app-layout .menu-toggle {

                width: 44px !important;

                height: 44px !important;

                min-width: 44px !important;

                min-height: 44px !important;

                font-size: 23px !important;

            }


            .app-layout .topbar-avatar {

                width: 36px !important;

                height: 36px !important;

                min-width: 36px !important;

                min-height: 36px !important;

            }


            .app-layout .sidebar-footer {

                padding-left: 14px !important;

                padding-right: 14px !important;

            }

        }


        /* ================================================================
           VERY SMALL MOBILE
        ================================================================ */

        @media (max-width: 360px) {

            .app-layout .sidebar {

                width: 270px !important;

                max-width: 88vw !important;

            }

        }


        /* ================================================================
           REDUCED MOTION
        ================================================================ */

        @media (prefers-reduced-motion: reduce) {

            .app-layout .sidebar,
            .app-layout .sidebar-overlay {

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

                    <span aria-hidden="true">
                        🏠
                    </span>

                    <span>
                        Dashboard
                    </span>

                </a>


                <div class="sidebar-section-title">
                    Student Management
                </div>


                <a
                    href="admin-pending-students.php"
                    class="<?= navClass('admin-pending-students.php') ?>"
                >

                    <span aria-hidden="true">
                        ⏳
                    </span>

                    <span>
                        Pending Students
                    </span>

                </a>


                <a
                    href="admin-students.php"
                    class="<?= navClass('admin-students.php') ?>"
                >

                    <span aria-hidden="true">
                        👥
                    </span>

                    <span>
                        Approved Students
                    </span>

                </a>


                <a
                    href="admin-rejected-students.php"
                    class="<?= navClass('admin-rejected-students.php') ?>"
                >

                    <span aria-hidden="true">
                        🚫
                    </span>

                    <span>
                        Rejected / Suspended
                    </span>

                </a>


                <div class="sidebar-section-title">
                    Sports Management
                </div>


                <a
                    href="admin-sports.php"
                    class="<?= navClass('admin-sports.php') ?>"
                >

                    <span aria-hidden="true">
                        ⚽
                    </span>

                    <span>
                        Sports
                    </span>

                </a>

                <a
                    href="admin-teams.php"
                    class="<?= navClass('admin-teams.php') ?>"
                >

                    <span aria-hidden="true">
                        🛡️
                    </span>

                    <span>
                        Teams
                    </span>

                </a>


                <a
                    href="admin-tournaments.php"
                    class="<?= navClass('admin-tournaments.php') ?>"
                >

                    <span aria-hidden="true">
                        🏆
                    </span>

                    <span>
                        Tournaments
                    </span>

                </a>

                <a
                    href="admin-venues.php"
                    class="<?= navClass('admin-venues.php') ?>"
                >
                    <span aria-hidden="true">
                        📍
                    </span>

                    <span>
                        Venues
                    </span>

                </a>


                <div class="sidebar-section-title">
                    Account
                </div>


                <a
                    href="admin-profile.php"
                    class="<?= navClass('admin-profile.php') ?>"
                >

                    <span aria-hidden="true">
                        👤
                    </span>

                    <span>
                        My Profile
                    </span>

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

                    <span aria-hidden="true">
                        🏠
                    </span>

                    <span>
                        Dashboard
                    </span>

                </a>


                <div class="sidebar-section-title">
                    Competitions
                </div>


                <a
                    href="admin-tournaments.php"
                    class="<?= navClass('admin-tournaments.php') ?>"
                >

                    <span aria-hidden="true">
                        🏆
                    </span>

                    <span>
                        Tournaments
                    </span>

                </a>


                <a
                    href="tournament-standings.php"
                    class="<?= navClass('tournament-standings.php') ?>"
                >

                    <span aria-hidden="true">
                        📊
                    </span>

                    <span>
                        Standings
                    </span>

                </a>


                <div class="sidebar-section-title">
                    Matches
                </div>


                <a
                    href="schedule-match.php"
                    class="<?= navClass('schedule-match.php') ?>"
                >

                    <span aria-hidden="true">
                        📅
                    </span>

                    <span>
                        Schedule Match
                    </span>

                </a>


                <a
                    href="match-results.php"
                    class="<?= navClass('match-results.php') ?>"
                >

                    <span aria-hidden="true">
                        🏁
                    </span>

                    <span>
                        Match Results
                    </span>

                </a>


                <a
                    href="edit-match-result.php"
                    class="<?= navClass('edit-match-result.php') ?>"
                >

                    <span aria-hidden="true">
                        ✏️
                    </span>

                    <span>
                        Result Corrections
                    </span>

                </a>


                <div class="sidebar-section-title">
                    Sports
                </div>


                <a
                    href="admin-teams.php"
                    class="<?= navClass('admin-teams.php') ?>"
                >

                    <span aria-hidden="true">
                        🛡️
                    </span>

                    <span>
                        Teams
                    </span>

                </a>


                <a
                    href="admin-students.php"
                    class="<?= navClass('admin-students.php') ?>"
                >

                    <span aria-hidden="true">
                        👥
                    </span>

                    <span>
                        Players
                    </span>

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

                    <span aria-hidden="true">
                        🏠
                    </span>

                    <span>
                        Dashboard
                    </span>

                </a>


                <div class="sidebar-section-title">
                    My Team
                </div>


                <a
                    href="coach-teams.php"
                    class="<?= navClass('coach-teams.php') ?>"
                >

                    <span aria-hidden="true">
                        🛡️
                    </span>

                    <span>
                        My Teams
                    </span>

                </a>


                <a
                    href="coach-players.php"
                    class="<?= navClass('coach-players.php') ?>"
                >

                    <span aria-hidden="true">
                        👥
                    </span>

                    <span>
                        My Players
                    </span>

                </a>


                <div class="sidebar-section-title">
                    My Matches
                </div>


                <a
                    href="coach-matches.php"
                    class="<?= navClass('coach-matches.php') ?>"
                >

                    <span aria-hidden="true">
                        📅
                    </span>

                    <span>
                        My Matches
                    </span>

                </a>


                <div class="sidebar-section-title">
                    Performance
                </div>


                <a
                    href="coach-statistics.php"
                    class="<?= navClass('coach-statistics.php') ?>"
                >

                    <span aria-hidden="true">
                        📊
                    </span>

                    <span>
                        Team Statistics
                    </span>

                </a>


                <div class="sidebar-section-title">
                    Account
                </div>


                <a
                    href="coach-profile.php"
                    class="<?= navClass('coach-profile.php') ?>"
                >

                    <span aria-hidden="true">
                        👤
                    </span>

                    <span>
                        My Profile
                    </span>

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

                    <span aria-hidden="true">
                        🏠
                    </span>

                    <span>
                        Dashboard
                    </span>

                </a>


                <div class="sidebar-section-title">
                    My Sports
                </div>


                <a
                    href="player-sports.php"
                    class="<?= navClass('player-sports.php') ?>"
                >

                    <span aria-hidden="true">
                        ⚽
                    </span>

                    <span>
                        My Sports
                    </span>

                </a>


                <div class="sidebar-section-title">
                    My Team
                </div>


                <a
                    href="player-teams.php"
                    class="<?= navClass('player-teams.php') ?>"
                >

                    <span aria-hidden="true">
                        🛡️
                    </span>

                    <span>
                        My Team
                    </span>

                </a>


                <div class="sidebar-section-title">
                    My Competitions
                </div>


                <a
                    href="player-tournaments.php"
                    class="<?= navClass('player-tournaments.php') ?>"
                >

                    <span aria-hidden="true">
                        🏆
                    </span>

                    <span>
                        My Tournaments
                    </span>

                </a>


                <a
                    href="player-matches.php"
                    class="<?= navClass('player-matches.php') ?>"
                >

                    <span aria-hidden="true">
                        📅
                    </span>

                    <span>
                        My Matches
                    </span>

                </a>


                <div class="sidebar-section-title">
                    My Performance
                </div>


                <a
                    href="player-statistics.php"
                    class="<?= navClass('player-statistics.php') ?>"
                >

                    <span aria-hidden="true">
                        📊
                    </span>

                    <span>
                        My Statistics
                    </span>

                </a>


                <div class="sidebar-section-title">
                    Account
                </div>


                <a
                    href="player-profile.php"
                    class="<?= navClass('player-profile.php') ?>"
                >

                    <span aria-hidden="true">
                        👤
                    </span>

                    <span>
                        My Profile
                    </span>

                </a>


            <?php endif; ?>


        </nav>


        <!-- ================================================================
             SIDEBAR FOOTER
        ================================================================ -->

        <div class="sidebar-footer">

            <a
                href="logout.php"
                class="sidebar-logout"
            >

                <span aria-hidden="true">
                    🚪
                </span>

                <span>
                    Logout
                </span>

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


                        <?php if (
                            strcasecmp(
                                trim($fullName),
                                trim($roleLabel)
                            ) !== 0
                        ): ?>

                            <span class="topbar-user-role">

                                <?= headerEscape($roleLabel) ?>

                            </span>

                        <?php endif; ?>


                    </div>


                </div>


            </div>


        </header>


        <!-- ============================================================
             PAGE CONTAINER
        ============================================================ -->

        <div class="page-container">