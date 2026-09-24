<?php
<<<<<<< HEAD

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SportSync - Admin Dashboard
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/
<<<<<<< HEAD

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
requireLogin();

$user = currentUser();

/*
|--------------------------------------------------------------------------
| Admin-only access
|--------------------------------------------------------------------------
*/
<<<<<<< HEAD

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
if (($user['role'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    exit('Access denied.');
}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/
<<<<<<< HEAD

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
$pdo = db();

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/
<<<<<<< HEAD

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
$totalStudents = 0;
$pendingStudents = 0;
$totalSports = 0;
$totalTeams = 0;
$totalCoaches = 0;
$totalTournaments = 0;
$totalMatches = 0;

try {
<<<<<<< HEAD
    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM users
=======
    // Total registered students / users
    $stmt = $pdo->query(
        "SELECT COUNT(*) 
         FROM users 
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
         WHERE role_id = 4"
    );
    $totalStudents = (int) $stmt->fetchColumn();

<<<<<<< HEAD
    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM users
         WHERE role_id = 4
=======
    // Pending student accounts
    $stmt = $pdo->query(
        "SELECT COUNT(*) 
         FROM users 
         WHERE role_id = 4 
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
         AND account_status = 'PENDING'"
    );
    $pendingStudents = (int) $stmt->fetchColumn();

<<<<<<< HEAD
    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM sports"
    );
    $totalSports = (int) $stmt->fetchColumn();

    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM teams"
    );
    $totalTeams = (int) $stmt->fetchColumn();

    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM users
=======
    // Sports
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM sports"
    );
    $totalSports = (int) $stmt->fetchColumn();

    // Teams
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM teams"
    );
    $totalTeams = (int) $stmt->fetchColumn();

    // Coaches
    $stmt = $pdo->query(
        "SELECT COUNT(*) 
         FROM users 
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
         WHERE role_id = 3"
    );
    $totalCoaches = (int) $stmt->fetchColumn();

<<<<<<< HEAD
    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM tournaments"
    );
    $totalTournaments = (int) $stmt->fetchColumn();

    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM matches"
    );
    $totalMatches = (int) $stmt->fetchColumn();
=======
    // Tournaments
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM tournaments"
    );
    $totalTournaments = (int) $stmt->fetchColumn();

    // Matches
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM matches"
    );
    $totalMatches = (int) $stmt->fetchColumn();

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
} catch (Throwable $e) {
    error_log(
        'Admin dashboard statistics error: ' .
        $e->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Recent Pending Students
|--------------------------------------------------------------------------
*/
<<<<<<< HEAD

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
$recentPendingStudents = [];

try {
    $stmt = $pdo->query(
        "SELECT
            user_id,
            full_name,
            email,
            created_at
         FROM users
         WHERE role_id = 4
         AND account_status = 'PENDING'
         ORDER BY created_at DESC
         LIMIT 5"
    );

    $recentPendingStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
} catch (Throwable $e) {
    error_log(
        'Admin pending students error: ' .
        $e->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Recent Tournaments
|--------------------------------------------------------------------------
*/
<<<<<<< HEAD

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
$recentTournaments = [];

try {
    $stmt = $pdo->query(
        "SELECT
            t.tournament_id,
            t.tournament_name,
            t.start_date,
            t.end_date,
            t.tournament_status,
            s.sport_name
         FROM tournaments t
         LEFT JOIN sports s
            ON s.sport_id = t.sport_id
         ORDER BY t.created_at DESC
         LIMIT 5"
    );

    $recentTournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
} catch (Throwable $e) {
    error_log(
        'Admin tournaments error: ' .
        $e->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/
<<<<<<< HEAD

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

function dashboardStatusClass(?string $status): string
{
    $status = strtoupper($status ?? '');

    return match ($status) {
        'APPROVED',
        'ACTIVE',
        'REGISTRATION_OPEN',
        'ONGOING',
        'COMPLETED' => 'status-success',

        'PENDING',
        'UPCOMING',
        'DRAFT' => 'status-warning',

        'REJECTED',
        'SUSPENDED',
        'CANCELLED' => 'status-danger',

        default => 'status-neutral'
    };
}

<<<<<<< HEAD
/*
|--------------------------------------------------------------------------
| Admin Next Step
|--------------------------------------------------------------------------
*/

$hasAdminAction = $pendingStudents > 0;

if ($hasAdminAction) {
    $nextStepTitle = 'Review Pending Student Approvals';

    $nextStepDescription =
        'There are ' .
        $pendingStudents .
        ' student account' .
        ($pendingStudents === 1 ? '' : 's') .
        ' waiting for your approval.';

    $nextStepButton = 'Review Pending Students';
    $nextStepLink = 'admin-pending-students.php';
    $nextStepIcon = '👨‍🎓';
} else {
    $nextStepTitle = 'You are all caught up';

    $nextStepDescription =
        'There are no student registrations waiting for approval. ' .
        'You can continue managing teams or tournaments.';

    $nextStepButton = 'Manage Teams';
    $nextStepLink = 'admin-teams.php';
    $nextStepIcon = '✓';
}

?>

=======
?>
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
<!DOCTYPE html>
<html lang="en">

<head>
<<<<<<< HEAD

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard | SportSync</title>

    <meta
        name="description"
        content="SportSync Smart Sports Management System Admin Dashboard"
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
<<<<<<< HEAD

        /* =========================================================
           SPORTSYNC ADMIN DASHBOARD
=======
        /* =========================================================
           SportSync Admin Dashboard
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
        ========================================================= */

        .admin-dashboard {
            display: flex;
            flex-direction: column;
<<<<<<< HEAD
            gap: 26px;
        }

        /* =========================================================
           HERO
        ========================================================= */

        .admin-hero {
            position: relative;
            overflow: hidden;
            padding: 30px 32px;
=======
            gap: 28px;
        }

        .admin-hero {
            position: relative;
            overflow: hidden;
            padding: 32px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            border-radius: 24px;
            background:
                linear-gradient(
                    135deg,
                    #0b1f4d 0%,
                    #1647a8 55%,
                    #2563eb 100%
                );
            color: #ffffff;
            box-shadow: 0 18px 45px rgba(15, 35, 80, 0.18);
        }

        .admin-hero::before {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.07);
            right: -70px;
            top: -110px;
        }

        .admin-hero::after {
            content: "";
            position: absolute;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.12);
            right: 100px;
            bottom: -110px;
        }

        .admin-hero-content {
            position: relative;
            z-index: 2;
<<<<<<< HEAD
            max-width: 780px;
=======
            max-width: 760px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
        }

        .admin-hero-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding: 7px 13px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .admin-hero h1 {
            margin: 0 0 10px;
            font-size: clamp(26px, 4vw, 38px);
            line-height: 1.15;
            font-weight: 800;
        }

        .admin-hero p {
            margin: 0;
<<<<<<< HEAD
            max-width: 700px;
            color: rgba(255, 255, 255, 0.84);
=======
            max-width: 680px;
            color: rgba(255, 255, 255, 0.82);
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            font-size: 15px;
            line-height: 1.7;
        }

<<<<<<< HEAD
        /* =========================================================
           NEXT STEP
        ========================================================= */

        .admin-next-step {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 24px 26px;
            border: 1px solid #dbe7ff;
            border-radius: 20px;
            background: #f8fbff;
            box-shadow: 0 8px 24px rgba(20, 35, 65, 0.05);
        }

        .admin-next-step-main {
            display: flex;
            align-items: center;
            gap: 17px;
            min-width: 0;
        }

        .admin-next-step-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 52px;
            height: 52px;
            border-radius: 15px;
            background: #e8f0ff;
            color: #2563eb;
            font-size: 22px;
        }

        .admin-next-step-label {
            margin: 0 0 5px;
            color: #2563eb;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .admin-next-step-title {
            margin: 0;
            color: #111827;
            font-size: 18px;
            font-weight: 800;
        }

        .admin-next-step-description {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .admin-next-step-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 11px;
            background: #2563eb;
            color: #ffffff;
            font-size: 12px;
            font-weight: 750;
            text-decoration: none;
            white-space: nowrap;
            transition:
                background 0.2s ease,
                transform 0.2s ease;
        }

        .admin-next-step-button:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        /* =========================================================
           SECTION HEADING
        ========================================================= */

        .admin-section-heading {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 15px;
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
        }

        /* =========================================================
           STATISTICS
        ========================================================= */

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
        .admin-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .admin-stat-card {
            position: relative;
            overflow: hidden;
<<<<<<< HEAD
            padding: 21px;
=======
            padding: 22px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            border: 1px solid #e8edf5;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(20, 35, 65, 0.06);
<<<<<<< HEAD
=======
            transition:
                transform 0.25s ease,
                box-shadow 0.25s ease;
        }

        .admin-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 32px rgba(20, 35, 65, 0.10);
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
        }

        .admin-stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
<<<<<<< HEAD
            margin-bottom: 18px;
=======
            margin-bottom: 20px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
        }

        .admin-stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
<<<<<<< HEAD
            width: 45px;
            height: 45px;
            border-radius: 13px;
            background: #eef4ff;
            color: #1d4ed8;
            font-size: 20px;
=======
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: #eef4ff;
            color: #1d4ed8;
            font-size: 21px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
        }

        .admin-stat-number {
            margin: 0;
            color: #111827;
<<<<<<< HEAD
            font-size: 29px;
=======
            font-size: 30px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            font-weight: 800;
            line-height: 1;
        }

        .admin-stat-title {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
        }

<<<<<<< HEAD
        /* =========================================================
           MAIN WORK GRID
        ========================================================= */

        .admin-main-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 1.45fr)
                minmax(300px, 0.8fr);
=======
        .admin-stat-link {
            display: inline-block;
            margin-top: 16px;
            color: #2563eb;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .admin-stat-link:hover {
            text-decoration: underline;
        }

        .admin-main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(300px, 0.8fr);
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            gap: 22px;
        }

        .admin-panel {
            min-width: 0;
            overflow: hidden;
            border: 1px solid #e8edf5;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(20, 35, 65, 0.05);
        }

        .admin-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
<<<<<<< HEAD
            padding: 21px 24px;
=======
            padding: 22px 24px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            border-bottom: 1px solid #edf1f6;
        }

        .admin-panel-title {
            margin: 0;
            color: #111827;
            font-size: 17px;
            font-weight: 750;
        }

        .admin-panel-subtitle {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 12px;
        }

        .admin-panel-link {
            color: #2563eb;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .admin-panel-link:hover {
            text-decoration: underline;
        }

<<<<<<< HEAD
        /* =========================================================
           PENDING STUDENTS TABLE
        ========================================================= */

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
        .admin-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
<<<<<<< HEAD
            min-width: 560px;
            border-collapse: collapse;
        }

        .admin-table th {
            padding: 13px 20px;
=======
            border-collapse: collapse;
            min-width: 580px;
        }

        .admin-table th {
            padding: 14px 20px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            background: #f8fafc;
            color: #64748b;
            font-size: 11px;
            font-weight: 750;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .admin-table td {
<<<<<<< HEAD
            padding: 15px 20px;
=======
            padding: 16px 20px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            border-top: 1px solid #edf1f6;
            color: #334155;
            font-size: 13px;
        }

        .admin-student-name {
            color: #111827;
            font-weight: 700;
        }

        .admin-student-email {
            margin-top: 3px;
            color: #94a3b8;
            font-size: 11px;
        }

        .admin-status {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 750;
            letter-spacing: 0.03em;
        }

        .status-success {
            background: #ecfdf3;
            color: #15803d;
        }

        .status-warning {
            background: #fff7ed;
            color: #c2410c;
        }

        .status-danger {
            background: #fef2f2;
            color: #dc2626;
        }

        .status-neutral {
            background: #f1f5f9;
            color: #475569;
        }

<<<<<<< HEAD
        .admin-empty {
            padding: 38px 24px;
            color: #64748b;
            font-size: 13px;
            text-align: center;
        }

        /* =========================================================
           COMMON TASKS
        ========================================================= */

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
        .admin-actions {
            display: flex;
            flex-direction: column;
        }

        .admin-action {
            display: flex;
            align-items: center;
            gap: 15px;
<<<<<<< HEAD
            padding: 16px 22px;
=======
            padding: 17px 22px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            border-bottom: 1px solid #edf1f6;
            color: #1e293b;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .admin-action:last-child {
            border-bottom: 0;
        }

        .admin-action:hover {
            background: #f8fbff;
        }

        .admin-action-icon {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #eef4ff;
            color: #2563eb;
            font-size: 18px;
        }

        .admin-action-content {
            flex: 1;
        }

        .admin-action-title {
            display: block;
            font-size: 13px;
            font-weight: 750;
        }

        .admin-action-description {
            display: block;
            margin-top: 3px;
            color: #94a3b8;
            font-size: 11px;
        }

        .admin-action-arrow {
            color: #94a3b8;
            font-size: 18px;
        }

<<<<<<< HEAD
        /* =========================================================
           RECENT TOURNAMENTS
        ========================================================= */

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
        .admin-tournament-list {
            display: flex;
            flex-direction: column;
        }

        .admin-tournament {
            display: flex;
            align-items: center;
            gap: 15px;
<<<<<<< HEAD
            padding: 17px 22px;
=======
            padding: 18px 22px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            border-bottom: 1px solid #edf1f6;
        }

        .admin-tournament:last-child {
            border-bottom: 0;
        }

        .admin-tournament-icon {
            display: flex;
            align-items: center;
            justify-content: center;
<<<<<<< HEAD
            width: 43px;
            height: 43px;
=======
            width: 44px;
            height: 44px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            flex: 0 0 auto;
            border-radius: 13px;
            background: #f0fdf4;
            font-size: 19px;
        }

        .admin-tournament-info {
            flex: 1;
            min-width: 0;
        }

        .admin-tournament-name {
            overflow: hidden;
            color: #1e293b;
            font-size: 13px;
            font-weight: 750;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .admin-tournament-meta {
            margin-top: 5px;
            color: #94a3b8;
            font-size: 11px;
        }

<<<<<<< HEAD
        /* =========================================================
           SYSTEM INFORMATION
        ========================================================= */

        .admin-overview-grid {
=======
        .admin-empty {
            padding: 40px 24px;
            color: #94a3b8;
            font-size: 13px;
            text-align: center;
        }

        .admin-bottom-grid {
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

<<<<<<< HEAD
        .admin-overview-card {
            padding: 21px;
=======
        .admin-mini-card {
            padding: 22px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            border: 1px solid #e8edf5;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(20, 35, 65, 0.05);
        }

<<<<<<< HEAD
        .admin-overview-icon {
=======
        .admin-mini-card-icon {
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
<<<<<<< HEAD
            margin-bottom: 15px;
=======
            margin-bottom: 17px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            border-radius: 12px;
            background: #eef4ff;
            font-size: 18px;
        }

<<<<<<< HEAD
        .admin-overview-card h3 {
=======
        .admin-mini-card h3 {
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            margin: 0;
            color: #111827;
            font-size: 15px;
        }

<<<<<<< HEAD
        .admin-overview-card p {
            margin: 7px 0 13px;
=======
        .admin-mini-card p {
            margin: 7px 0 18px;
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }

<<<<<<< HEAD
        .admin-overview-value {
            color: #2563eb;
            font-size: 21px;
            font-weight: 800;
        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1100px) {

=======
        .admin-mini-card a {
            color: #2563eb;
            font-size: 12px;
            font-weight: 750;
            text-decoration: none;
        }

        .admin-mini-card a:hover {
            text-decoration: underline;
        }

        @media (max-width: 1100px) {
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            .admin-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .admin-main-grid {
                grid-template-columns: 1fr;
            }

<<<<<<< HEAD
            .admin-overview-grid {
=======
            .admin-bottom-grid {
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
<<<<<<< HEAD

            .admin-dashboard {
                gap: 20px;
            }

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            .admin-hero {
                padding: 24px;
                border-radius: 20px;
            }

<<<<<<< HEAD
            .admin-next-step {
                flex-direction: column;
                align-items: stretch;
                padding: 20px;
            }

            .admin-next-step-button {
                width: 100%;
            }

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            .admin-stats {
                grid-template-columns: 1fr;
            }

            .admin-panel-header {
                padding: 18px;
            }

            .admin-table td,
            .admin-table th {
                padding-left: 15px;
                padding-right: 15px;
            }
        }
<<<<<<< HEAD

    </style>

=======
    </style>
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
</head>

<body>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-container">

    <main class="admin-dashboard">

        <!-- =====================================================
             HERO
        ====================================================== -->
<<<<<<< HEAD

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
        <section class="admin-hero">

            <div class="admin-hero-content">

                <div class="admin-hero-label">
                    🛡️ Admin Control Center
                </div>

                <h1>
                    Welcome back,
                    <?= e($user['full_name'] ?? 'Administrator'); ?> 👋
                </h1>

                <p>
<<<<<<< HEAD
                    Your role is to keep SportSync organized and running
                    smoothly. Manage student access, teams and
                    tournaments, and monitor the overall sports system.
=======
                    Manage students, sports, teams, tournaments and
                    matches from one central SportSync administration
                    dashboard.
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                </p>

            </div>

        </section>

<<<<<<< HEAD
        <!-- =====================================================
             YOUR NEXT STEP
        ====================================================== -->

        <section class="admin-next-step">

            <div class="admin-next-step-main">

                <div class="admin-next-step-icon">
                    <?= $nextStepIcon; ?>
                </div>

                <div>

                    <p class="admin-next-step-label">
                        Your Next Step
                    </p>

                    <h2 class="admin-next-step-title">
                        <?= e($nextStepTitle); ?>
                    </h2>

                    <p class="admin-next-step-description">
                        <?= e($nextStepDescription); ?>
                    </p>

                </div>

            </div>

            <a
                href="<?= e($nextStepLink); ?>"
                class="admin-next-step-button"
            >
                <?= e($nextStepButton); ?> →
            </a>

        </section>

        <!-- =====================================================
             SYSTEM OVERVIEW
        ====================================================== -->

        <section>

            <div class="admin-section-heading">

                <div>

                    <h2>
                        System Overview
                    </h2>

                    <p>
                        A quick view of the information you manage.
                    </p>

                </div>

            </div>

        </section>

        <!-- =====================================================
             PRIMARY STATISTICS
        ====================================================== -->

=======

        <!-- =====================================================
             STATISTICS
        ====================================================== -->
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
        <section class="admin-stats">

            <article class="admin-stat-card">

                <div class="admin-stat-top">

                    <div class="admin-stat-icon">
                        👨‍🎓
                    </div>

                </div>

                <p class="admin-stat-number">
                    <?= $totalStudents; ?>
                </p>

                <p class="admin-stat-title">
                    Registered Students
                </p>

<<<<<<< HEAD
            </article>

=======
                <a
                    href="admin-pending-students.php"
                    class="admin-stat-link"
                >
                    Manage Students →
                </a>

            </article>


>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            <article class="admin-stat-card">

                <div class="admin-stat-top">

                    <div class="admin-stat-icon">
                        🏃
                    </div>

                </div>

                <p class="admin-stat-number">
                    <?= $totalSports; ?>
                </p>

                <p class="admin-stat-title">
                    Sports
                </p>

<<<<<<< HEAD
            </article>

=======
                <a
                    href="admin-dashboard.php"
                    class="admin-stat-link"
                >
                    View Sports →
                </a>

            </article>


>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            <article class="admin-stat-card">

                <div class="admin-stat-top">

                    <div class="admin-stat-icon">
                        👥
                    </div>

                </div>

                <p class="admin-stat-number">
                    <?= $totalTeams; ?>
                </p>

                <p class="admin-stat-title">
                    Teams
                </p>

<<<<<<< HEAD
            </article>

=======
                <a
                    href="admin-teams.php"
                    class="admin-stat-link"
                >
                    Manage Teams →
                </a>

            </article>


>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            <article class="admin-stat-card">

                <div class="admin-stat-top">

                    <div class="admin-stat-icon">
                        🏆
                    </div>

                </div>

                <p class="admin-stat-number">
                    <?= $totalTournaments; ?>
                </p>

                <p class="admin-stat-title">
                    Tournaments
                </p>

<<<<<<< HEAD
=======
                <a
                    href="admin-tournaments.php"
                    class="admin-stat-link"
                >
                    Manage Tournaments →
                </a>

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            </article>

        </section>

<<<<<<< HEAD
        <!-- =====================================================
             MAIN WORK AREA
        ====================================================== -->

        <section class="admin-main-grid">

            <!-- STUDENT APPROVALS -->

=======

        <!-- =====================================================
             MAIN GRID
        ====================================================== -->
        <section class="admin-main-grid">

            <!-- Pending Students -->
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            <div class="admin-panel">

                <div class="admin-panel-header">

                    <div>
<<<<<<< HEAD

                        <h2 class="admin-panel-title">
                            Student Approvals
                        </h2>

                        <p class="admin-panel-subtitle">
                            Student registrations that need your decision
                        </p>

=======
                        <h2 class="admin-panel-title">
                            Pending Student Approvals
                        </h2>

                        <p class="admin-panel-subtitle">
                            Students waiting for administrator approval
                        </p>
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                    </div>

                    <a
                        href="admin-pending-students.php"
                        class="admin-panel-link"
                    >
                        View All →
                    </a>

                </div>

                <?php if (!empty($recentPendingStudents)): ?>

                    <div class="admin-table-wrap">

                        <table class="admin-table">

                            <thead>
<<<<<<< HEAD

                                <tr>

                                    <th>
                                        Student
                                    </th>

                                    <th>
                                        Registered
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

=======
                                <tr>
                                    <th>Student</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                </tr>
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                            </thead>

                            <tbody>

                            <?php foreach ($recentPendingStudents as $student): ?>

                                <tr>

                                    <td>

                                        <div class="admin-student-name">
                                            <?= e($student['full_name']); ?>
                                        </div>

                                        <div class="admin-student-email">
                                            <?= e($student['email']); ?>
                                        </div>

                                    </td>

                                    <td>
                                        <?= e($student['created_at']); ?>
                                    </td>

                                    <td>

                                        <span class="admin-status status-warning">
                                            PENDING
                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="admin-empty">
                        🎉 No students are waiting for approval.
                    </div>

                <?php endif; ?>

            </div>

<<<<<<< HEAD
            <!-- COMMON TASKS -->

=======

            <!-- Quick Actions -->
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            <div class="admin-panel">

                <div class="admin-panel-header">

                    <div>
<<<<<<< HEAD

                        <h2 class="admin-panel-title">
                            Common Tasks
                        </h2>

                        <p class="admin-panel-subtitle">
                            Actions you may need to perform regularly
                        </p>

=======
                        <h2 class="admin-panel-title">
                            Quick Management
                        </h2>

                        <p class="admin-panel-subtitle">
                            Frequently used administration tools
                        </p>
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                    </div>

                </div>

                <div class="admin-actions">

                    <a
<<<<<<< HEAD
                        href="admin-students.php"
=======
                        href="admin-pending-students.php"
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                        class="admin-action"
                    >

                        <span class="admin-action-icon">
                            👨‍🎓
                        </span>

                        <span class="admin-action-content">

                            <span class="admin-action-title">
<<<<<<< HEAD
                                Manage Students
                            </span>

                            <span class="admin-action-description">
                                View and manage registered students
=======
                                Student Approvals
                            </span>

                            <span class="admin-action-description">
                                <?= $pendingStudents; ?>
                                pending approval
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                            </span>

                        </span>

                        <span class="admin-action-arrow">
                            →
                        </span>

                    </a>

<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                    <a
                        href="admin-teams.php"
                        class="admin-action"
                    >

                        <span class="admin-action-icon">
                            👥
                        </span>

                        <span class="admin-action-content">

                            <span class="admin-action-title">
                                Manage Teams
                            </span>

                            <span class="admin-action-description">
<<<<<<< HEAD
                                View teams and manage team setup
=======
                                View and manage sports teams
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                            </span>

                        </span>

                        <span class="admin-action-arrow">
                            →
                        </span>

                    </a>

<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                    <a
                        href="admin-tournaments.php"
                        class="admin-action"
                    >

                        <span class="admin-action-icon">
                            🏆
                        </span>

                        <span class="admin-action-content">

                            <span class="admin-action-title">
                                Manage Tournaments
                            </span>

                            <span class="admin-action-description">
                                Create and manage competitions
                            </span>

                        </span>

                        <span class="admin-action-arrow">
                            →
                        </span>

                    </a>

<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                    <a
                        href="create-team.php"
                        class="admin-action"
                    >

                        <span class="admin-action-icon">
                            ➕
                        </span>

                        <span class="admin-action-content">

                            <span class="admin-action-title">
                                Create Team
                            </span>

                            <span class="admin-action-description">
                                Add a new sports team
                            </span>

                        </span>

                        <span class="admin-action-arrow">
                            →
                        </span>

                    </a>

                </div>

            </div>

        </section>

<<<<<<< HEAD
        <!-- =====================================================
             RECENT TOURNAMENTS
        ====================================================== -->

=======

        <!-- =====================================================
             RECENT TOURNAMENTS
        ====================================================== -->
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
        <section class="admin-panel">

            <div class="admin-panel-header">

                <div>
<<<<<<< HEAD

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                    <h2 class="admin-panel-title">
                        Recent Tournaments
                    </h2>

                    <p class="admin-panel-subtitle">
                        Latest competitions created in SportSync
                    </p>
<<<<<<< HEAD

=======
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                </div>

                <a
                    href="admin-tournaments.php"
                    class="admin-panel-link"
                >
                    Manage All →
                </a>

            </div>

<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            <?php if (!empty($recentTournaments)): ?>

                <div class="admin-tournament-list">

                    <?php foreach ($recentTournaments as $tournament): ?>

                        <div class="admin-tournament">

                            <div class="admin-tournament-icon">
                                🏆
                            </div>

                            <div class="admin-tournament-info">

                                <div class="admin-tournament-name">
                                    <?= e($tournament['tournament_name']); ?>
                                </div>

                                <div class="admin-tournament-meta">

                                    <?= e($tournament['sport_name'] ?? 'Sport'); ?>

                                    •

                                    <?= e($tournament['start_date']); ?>

                                    to

                                    <?= e($tournament['end_date']); ?>

                                </div>

                            </div>

                            <span
<<<<<<< HEAD
                                class="admin-status <?= dashboardStatusClass(
                                    $tournament['tournament_status'] ?? ''
                                ); ?>"
                            >
                                <?= e(
                                    $tournament['tournament_status'] ?? 'N/A'
                                ); ?>
=======
                                class="admin-status <?= dashboardStatusClass($tournament['tournament_status'] ?? ''); ?>"
                            >
                                <?= e($tournament['tournament_status'] ?? 'N/A'); ?>
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="admin-empty">
                    No tournaments have been created yet.
                </div>

            <?php endif; ?>

        </section>

<<<<<<< HEAD
        <!-- =====================================================
             SYSTEM SNAPSHOT
        ====================================================== -->

        <section>

            <div class="admin-section-heading">

                <div>

                    <h2>
                        System Snapshot
                    </h2>

                    <p>
                        Additional information useful for administration.
                    </p>

                </div>

            </div>

        </section>

        <section class="admin-overview-grid">

            <!-- Coaches -->

            <article class="admin-overview-card">

                <div class="admin-overview-icon">
=======

        <!-- =====================================================
             SYSTEM OVERVIEW
        ====================================================== -->
        <section class="admin-bottom-grid">

            <article class="admin-mini-card">

                <div class="admin-mini-card-icon">
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                    👨‍🏫
                </div>

                <h3>
                    Coaches
                </h3>

                <p>
<<<<<<< HEAD
                    Coaches currently registered in SportSync.
                </p>

                <div class="admin-overview-value">
                    <?= $totalCoaches; ?>
                </div>

            </article>

            <!-- Matches -->

            <article class="admin-overview-card">

                <div class="admin-overview-icon">
=======
                    Currently registered coaches in the SportSync
                    system.
                </p>

                <strong>
                    <?= $totalCoaches; ?> Coaches
                </strong>

            </article>


            <article class="admin-mini-card">

                <div class="admin-mini-card-icon">
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                    ⚽
                </div>

                <h3>
                    Matches
                </h3>

                <p>
<<<<<<< HEAD
                    Matches currently recorded in the system.
                </p>

                <div class="admin-overview-value">
                    <?= $totalMatches; ?>
                </div>

            </article>

            <!-- Sports -->

            <article class="admin-overview-card">

                <div class="admin-overview-icon">
                    🏃
                </div>

                <h3>
                    Sports
                </h3>

                <p>
                    Sports currently available in SportSync.
                </p>

                <div class="admin-overview-value">
                    <?= $totalSports; ?>
                </div>
=======
                    Total matches currently scheduled across
                    tournaments.
                </p>

                <strong>
                    <?= $totalMatches; ?> Matches
                </strong>

            </article>


            <article class="admin-mini-card">

                <div class="admin-mini-card-icon">
                    ⏳
                </div>

                <h3>
                    Pending Approvals
                </h3>

                <p>
                    Student registrations that require
                    administrator action.
                </p>

                <strong>
                    <?= $pendingStudents; ?> Pending
                </strong>
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72

            </article>

        </section>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>