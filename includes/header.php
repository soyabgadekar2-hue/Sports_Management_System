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
| This prevents the old dashboard.php page from being opened accidentally.
|--------------------------------------------------------------------------
*/

$dashboardPage = match ($role) {
    'ADMIN' => 'admin-dashboard.php',
    'SPORTS_COORDINATOR' => 'coordinator-dashboard.php',
    'COACH' => 'coach-dashboard.php',
    'PLAYER' => 'player-dashboard.php',
    default => 'dashboard.php',
};

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
        SportSync | Smart Sports Management System
    </title>

    <link
        rel="icon"
        type="image/svg+xml"
        href="../assets/images/sportsync-mark.svg"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <!--
    |--------------------------------------------------------------------------
    | Header / Sidebar Alignment Fix
    |--------------------------------------------------------------------------
    | Keeps the fixed sidebar and main content aligned consistently.
    |--------------------------------------------------------------------------
    -->

    <style>
        /* =========================================================
           GLOBAL APP LAYOUT ALIGNMENT
           ========================================================= */

        .app-layout {
            min-height: 100vh;
            width: 100%;
            position: relative;
        }

        /* =========================================================
           SIDEBAR
           ========================================================= */

        .app-layout .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            min-height: 100vh;
            z-index: 1000;

            display: flex;
            flex-direction: column;

            overflow-y: auto;
            overflow-x: hidden;
        }

        /* Sidebar brand stays at the very top */

        .app-layout .sidebar-brand {
            width: 100%;
            flex-shrink: 0;
        }

        /* Navigation takes remaining sidebar space */

        .app-layout .sidebar-nav {
            flex: 1;
            width: 100%;
        }

        /* Footer remains at the bottom */

        .app-layout .sidebar-footer {
            width: 100%;
            flex-shrink: 0;
        }

        /* =========================================================
           MAIN CONTENT
           ========================================================= */

        .app-layout .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;

            display: flex;
            flex-direction: column;
        }

        /* =========================================================
           TOPBAR
           ========================================================= */

        .app-layout .topbar {
            width: 100%;
            min-height: 72px;
            height: 72px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            flex-shrink: 0;
        }

        .app-layout .topbar-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            height: 100%;
        }

        .app-layout .topbar-user {
            display: flex;
            align-items: center;
        }

        /* =========================================================
           PAGE CONTAINER
           ========================================================= */

        .app-layout .page-container {
            width: 100%;
            box-sizing: border-box;
        }

        /* =========================================================
           MOBILE
           ========================================================= */

        @media (max-width: 900px) {

            .app-layout .sidebar {
                transform: translateX(-100%);
                transition: transform 0.25s ease;
            }

            .app-layout .sidebar.open {
                transform: translateX(0);
            }

            .app-layout .main-content {
                margin-left: 0;
                width: 100%;
            }

            .app-layout .topbar {
                width: 100%;
            }

            .app-layout .sidebar-overlay {
                display: none;
            }

            .app-layout .sidebar-overlay.active {
                display: block;
            }
        }

        /* =========================================================
           SMALL MOBILE
           ========================================================= */

        @media (max-width: 600px) {

            .app-layout .topbar {
                min-height: 64px;
                height: 64px;
            }

            .app-layout .topbar-user-info {
                display: none;
            }
        }
    </style>

</head>

<body>

<div class="app-layout">

    <!-- =========================================================
         SIDEBAR
         ========================================================= -->

    <aside
        class="sidebar"
        id="sidebar"
    >

        <!-- =====================================================
             BRAND
             ===================================================== -->

        <div class="sidebar-brand">

            <a
                href="<?= htmlspecialchars(
                    $dashboardPage,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="brand-link"
                aria-label="SportSync Dashboard"
            >

                <div class="brand-icon">

                    <img
                        src="../assets/images/sportsync-mark.svg"
                        alt="SportSync"
                    >

                </div>

                <div class="brand-text">

                    <strong>
                        SportSync
                    </strong>

                    <span>
                        Smart Sports Management
                    </span>

                </div>

            </a>

        </div>


        <!-- =====================================================
             NAVIGATION
             ===================================================== -->

        <nav
            class="sidebar-nav"
            aria-label="Main navigation"
        >

            <!-- =================================================
                 ADMIN / SPORTS COORDINATOR
                 ================================================= -->

            <?php if (
                $role === 'ADMIN' ||
                $role === 'SPORTS_COORDINATOR'
            ): ?>

                <!-- Dashboard -->

                <a
                    href="<?= htmlspecialchars(
                        $dashboardPage,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    class="<?= $currentPage === $dashboardPage ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        🏠
                    </span>

                    <span class="nav-label">
                        Dashboard
                    </span>

                </a>


                <!-- Tournaments -->

                <a
                    href="admin-tournaments.php"
                    class="<?= $currentPage === 'admin-tournaments.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        🏆
                    </span>

                    <span class="nav-label">
                        Tournaments
                    </span>

                </a>


                <!-- Teams -->

                <a
                    href="admin-teams.php"
                    class="<?= $currentPage === 'admin-teams.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        👥
                    </span>

                    <span class="nav-label">
                        Teams
                    </span>

                </a>


                <!-- Approved Players -->

                <a
                    href="admin-students.php"
                    class="<?= $currentPage === 'admin-students.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        🏃
                    </span>

                    <span class="nav-label">
                        Players
                    </span>

                </a>


                <!-- Pending Players -->

                <a
                    href="admin-pending-students.php"
                    class="<?= $currentPage === 'admin-pending-students.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        ⏳
                    </span>

                    <span class="nav-label">
                        Pending Players
                    </span>

                </a>


                <!-- Rejected Players -->

                <a
                    href="admin-rejected-students.php"
                    class="<?= $currentPage === 'admin-rejected-students.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        🚫
                    </span>

                    <span class="nav-label">
                        Rejected Players
                    </span>

                </a>


                <!-- Create Tournament -->

                <a
                    href="create-tournament.php"
                    class="<?= $currentPage === 'create-tournament.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        ➕
                    </span>

                    <span class="nav-label">
                        Create Tournament
                    </span>

                </a>


                <!-- Create Team -->

                <a
                    href="create-team.php"
                    class="<?= $currentPage === 'create-team.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        ➕
                    </span>

                    <span class="nav-label">
                        Create Team
                    </span>

                </a>

            <?php endif; ?>


            <!-- =================================================
                 ADMIN PROFILE
                 ================================================= -->

            <?php if ($role === 'ADMIN'): ?>

                <a
                    href="admin-profile.php"
                    class="<?= $currentPage === 'admin-profile.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        👤
                    </span>

                    <span class="nav-label">
                        Profile
                    </span>

                </a>

            <?php endif; ?>


            <!-- =================================================
                 PLAYER
                 ================================================= -->

            <?php if ($role === 'PLAYER'): ?>

                <!-- Dashboard -->

                <a
                    href="player-dashboard.php"
                    class="<?= $currentPage === 'player-dashboard.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        🏠
                    </span>

                    <span class="nav-label">
                        Dashboard
                    </span>

                </a>


                <!-- My Sports -->

                <a
                    href="player-sports.php"
                    class="<?= $currentPage === 'player-sports.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        🏅
                    </span>

                    <span class="nav-label">
                        My Sports
                    </span>

                </a>


                <!-- My Teams -->

                <a
                    href="player-teams.php"
                    class="<?= $currentPage === 'player-teams.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        👥
                    </span>

                    <span class="nav-label">
                        My Teams
                    </span>

                </a>


                <!-- My Tournaments -->

                <a
                    href="player-tournaments.php"
                    class="<?= $currentPage === 'player-tournaments.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        🏆
                    </span>

                    <span class="nav-label">
                        My Tournaments
                    </span>

                </a>


                <!-- My Matches -->

                <a
                    href="player-matches.php"
                    class="<?= $currentPage === 'player-matches.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        ⚽
                    </span>

                    <span class="nav-label">
                        My Matches
                    </span>

                </a>


                <!-- My Statistics -->

                <a
                    href="player-statistics.php"
                    class="<?= $currentPage === 'player-statistics.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        📊
                    </span>

                    <span class="nav-label">
                        My Statistics
                    </span>

                </a>


                <!-- My Profile -->

                <a
                    href="player-profile.php"
                    class="<?= $currentPage === 'player-profile.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        👤
                    </span>

                    <span class="nav-label">
                        My Profile
                    </span>

                </a>

            <?php endif; ?>


            <!-- =================================================
                 COACH
                 ================================================= -->

            <?php if ($role === 'COACH'): ?>

                <!-- Dashboard -->

                <a
                    href="coach-dashboard.php"
                    class="<?= $currentPage === 'coach-dashboard.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        🏠
                    </span>

                    <span class="nav-label">
                        Dashboard
                    </span>

                </a>


                <!-- My Teams -->

                <a
                    href="coach-teams.php"
                    class="<?= $currentPage === 'coach-teams.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        👥
                    </span>

                    <span class="nav-label">
                        My Teams
                    </span>

                </a>


                <!-- Team Players -->

                <a
                    href="coach-players.php"
                    class="<?= $currentPage === 'coach-players.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        🏃
                    </span>

                    <span class="nav-label">
                        Team Players
                    </span>

                </a>


                <!-- My Matches -->

                <a
                    href="coach-matches.php"
                    class="<?= $currentPage === 'coach-matches.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        ⚽
                    </span>

                    <span class="nav-label">
                        My Matches
                    </span>

                </a>


                <!-- Team Statistics -->

                <a
                    href="coach-statistics.php"
                    class="<?= $currentPage === 'coach-statistics.php' ? 'active' : '' ?>"
                >

                    <span class="nav-icon">
                        📊
                    </span>

                    <span class="nav-label">
                        Team Statistics
                    </span>

                </a>

            <?php endif; ?>


            <!-- =================================================
                 DIVIDER
                 ================================================= -->

            <div class="sidebar-divider"></div>


            <!-- =================================================
                 LOGOUT
                 ================================================= -->

            <a
                href="logout.php"
                class="logout-link"
            >

                <span class="nav-icon">
                    🚪
                </span>

                <span class="nav-label">
                    Logout
                </span>

            </a>

        </nav>


        <!-- =====================================================
             SIDEBAR FOOTER
             ===================================================== -->

        <div class="sidebar-footer">

            <div class="sidebar-footer-line"></div>

            <span>
                SportSync
            </span>

            <small>
                Smart Sports Management System
            </small>

        </div>

    </aside>


    <!-- =========================================================
         MOBILE OVERLAY
         ========================================================= -->

    <div
        class="sidebar-overlay"
        id="sidebarOverlay"
    ></div>


    <!-- =========================================================
         MAIN CONTENT
         ========================================================= -->

    <main class="main-content">


        <!-- =====================================================
             TOPBAR
             ===================================================== -->

        <header class="topbar">

            <button
                type="button"
                class="menu-button"
                id="menuButton"
                aria-label="Open navigation menu"
            >
                ☰
            </button>


            <div class="topbar-right">

                <!-- User Information -->

                <?php if ($role === 'ADMIN'): ?>

                    <a
                        href="admin-profile.php"
                        class="topbar-user"
                        aria-label="Open Admin Profile"
                    >

                        <div class="topbar-avatar">
                            👤
                        </div>

                        <div class="topbar-user-info">

                            <strong>
                                <?= htmlspecialchars(
                                    $user['full_name'] ?? 'User',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                            <span>
                                <?= htmlspecialchars(
                                    ucwords(
                                        strtolower(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $role
                                            )
                                        )
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </div>

                    </a>

                <?php else: ?>

                    <div class="topbar-user">

                        <div class="topbar-avatar">
                            👤
                        </div>

                        <div class="topbar-user-info">

                            <strong>
                                <?= htmlspecialchars(
                                    $user['full_name'] ?? 'User',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                            <span>
                                <?= htmlspecialchars(
                                    ucwords(
                                        strtolower(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $role
                                            )
                                        )
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </header>


        <!-- =====================================================
             PAGE CONTENT
             ===================================================== -->

        <div class="page-container">