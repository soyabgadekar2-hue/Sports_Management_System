<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$user = currentUser();

$pageTitle = 'Dashboard';

require_once __DIR__ . '/../includes/header.php';
?>


<!-- =========================================================
     DASHBOARD HEADER
     ========================================================= -->

<div class="page-heading">

    <div>

        <h1>
            Dashboard
        </h1>

        <p>
            Welcome to the Sports Management System.
        </p>

    </div>

</div>


<!-- =========================================================
     WELCOME CARD
     ========================================================= -->

<div class="dashboard-welcome card">

    <div class="dashboard-welcome-content">

        <div>

            <span class="dashboard-label">
                Welcome back
            </span>

            <h2>
                <?= htmlspecialchars(
                    $user['role'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h2>

            <p>
                You are successfully logged in to the Sports
                Management System.
            </p>

        </div>

        <div class="dashboard-welcome-icon">
            🏆
        </div>

    </div>

</div>


<!-- =========================================================
     USER INFORMATION
     ========================================================= -->

<div class="dashboard-grid">


    <!-- User ID -->

    <div class="dashboard-stat card">

        <div class="dashboard-stat-icon">
            👤
        </div>

        <div>

            <span>
                User ID
            </span>

            <strong>
                <?= htmlspecialchars(
                    (string) $user['id'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

        </div>

    </div>


    <!-- Role -->

    <div class="dashboard-stat card">

        <div class="dashboard-stat-icon">
            🛡️
        </div>

        <div>

            <span>
                Account Role
            </span>

            <strong>
                <?= htmlspecialchars(
                    $user['role'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

        </div>

    </div>


    <!-- Login Status -->

    <div class="dashboard-stat card">

        <div class="dashboard-stat-icon">
            ✅
        </div>

        <div>

            <span>
                Login Status
            </span>

            <strong>
                Active
            </strong>

        </div>

    </div>

</div>


<!-- =========================================================
     QUICK ACTIONS
     ========================================================= -->

<div class="card dashboard-actions">

    <div class="card-header">

        <h2>
            Quick Actions
        </h2>

        <p>
            Access the main areas of the system.
        </p>

    </div>


    <div class="dashboard-action-grid">


        <a
            href="admin-tournaments.php"
            class="dashboard-action"
        >

            <span class="dashboard-action-icon">
                🏆
            </span>

            <span>

                <strong>
                    Tournaments
                </strong>

                <small>
                    Manage tournaments and matches
                </small>

            </span>

        </a>


        <a
            href="admin-teams.php"
            class="dashboard-action"
        >

            <span class="dashboard-action-icon">
                👥
            </span>

            <span>

                <strong>
                    Teams
                </strong>

                <small>
                    Manage sports teams
                </small>

            </span>

        </a>


        <a
            href="admin-players.php"
            class="dashboard-action"
        >

            <span class="dashboard-action-icon">
                🏃
            </span>

            <span>

                <strong>
                    Players
                </strong>

                <small>
                    Manage registered players
                </small>

            </span>

        </a>


        <a
            href="admin-sports.php"
            class="dashboard-action"
        >

            <span class="dashboard-action-icon">
                ⚽
            </span>

            <span>

                <strong>
                    Sports
                </strong>

                <small>
                    Manage available sports
                </small>

            </span>

        </a>

    </div>

</div>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>