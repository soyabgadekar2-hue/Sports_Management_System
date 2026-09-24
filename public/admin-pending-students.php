<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit.php';

requireLogin();
requireRole('ADMIN', 'SPORTS_COORDINATOR');

$pdo = db();

$statement = $pdo->query(
    'SELECT
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        u.account_status,
        u.created_at,
        pp.student_id,
        pp.department,
        pp.course,
        pp.academic_year,
        pp.semester,
        pp.gender
     FROM users u
     INNER JOIN player_profiles pp
        ON pp.user_id = u.user_id
     WHERE u.role_id = 4
       AND u.account_status = "PENDING"
     ORDER BY u.created_at DESC'
);

$students = $statement->fetchAll(PDO::FETCH_ASSOC);

$currentUser = currentUser();
$userRole = $_SESSION['role'] ?? '';

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
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
        content="SportSync - Pending Student Registrations"
    >

    <title>Pending Students | SportSync</title>

    <link
        rel="icon"
        type="image/svg+xml"
        href="../assets/images/sportsync-mark.svg"
    >

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;

            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Roboto,
                Arial,
                sans-serif;

            color: #172033;

            background:
                radial-gradient(
                    circle at top left,
                    rgba(37, 99, 235, 0.10),
                    transparent 30%
                ),
                #f5f8ff;
        }

        .topbar {
            height: 72px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 34px;

            background: #ffffff;

            border-bottom: 1px solid #e5e7eb;

            box-shadow:
                0 3px 15px rgba(15, 23, 42, 0.04);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-name {
            color: #155eef;
            font-size: 21px;
            font-weight: 800;
            line-height: 1.1;
        }

        .brand-tagline {
            margin-top: 3px;
            color: #98a2b3;
            font-size: 10px;
            font-weight: 500;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            color: #344054;
            font-size: 13px;
            font-weight: 700;
        }

        .user-role {
            margin-top: 2px;
            color: #667085;
            font-size: 11px;
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            height: 38px;

            padding: 0 16px;

            border-radius: 9px;

            text-decoration: none;

            background: #ffffff;
            border: 1px solid #d0d5dd;

            color: #344054;

            font-size: 13px;
            font-weight: 700;

            transition:
                background 0.2s ease,
                transform 0.2s ease;
        }

        .logout-btn:hover {
            background: #f9fafb;
            transform: translateY(-1px);
        }

        .page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 34px 28px 50px;
        }

        .breadcrumb {
            margin-bottom: 20px;
        }

        .breadcrumb a {
            color: #155eef;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .breadcrumb span {
            color: #98a2b3;
            margin: 0 7px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;

            gap: 20px;

            margin-bottom: 28px;
        }

        .page-title h1 {
            color: #101828;
            font-size: 29px;
            font-weight: 800;
            letter-spacing: -0.6px;
        }

        .page-title p {
            margin-top: 7px;
            color: #667085;
            font-size: 14px;
        }

        .pending-count {
            min-width: 120px;

            padding: 13px 17px;

            border-radius: 12px;

            background: #fff8e7;
            border: 1px solid #f2d48b;

            text-align: center;
        }

        .pending-count strong {
            display: block;

            color: #946200;

            font-size: 21px;
            font-weight: 800;
        }

        .pending-count span {
            display: block;

            margin-top: 2px;

            color: #7a5a12;

            font-size: 11px;
            font-weight: 600;
        }

        .card {
            background: #ffffff;

            border: 1px solid #e4e9f2;

            border-radius: 18px;

            box-shadow:
                0 10px 35px rgba(15, 23, 42, 0.06);

            overflow: hidden;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 20px 22px;

            border-bottom: 1px solid #eaecf0;
        }

        .card-header h2 {
            color: #101828;
            font-size: 17px;
            font-weight: 750;
        }

        .card-header p {
            margin-top: 4px;
            color: #667085;
            font-size: 12px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1050px;

            border-collapse: collapse;
        }

        th {
            padding: 13px 17px;

            background: #f8fafc;

            border-bottom: 1px solid #eaecf0;

            color: #667085;

            font-size: 11px;
            font-weight: 750;

            text-align: left;

            text-transform: uppercase;
            letter-spacing: 0.4px;

            white-space: nowrap;
        }

        td {
            padding: 17px;

            border-bottom: 1px solid #f0f2f5;

            color: #344054;

            font-size: 13px;

            vertical-align: middle;
        }

        tbody tr {
            transition: background 0.15s ease;
        }

        tbody tr:hover {
            background: #fafcff;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .student-name {
            color: #101828;
            font-weight: 700;
        }

        .student-email {
            margin-top: 4px;
            color: #667085;
            font-size: 12px;
        }

        .student-id {
            display: inline-flex;

            padding: 5px 9px;

            border-radius: 7px;

            background: #eef4ff;

            color: #155eef;

            font-size: 11px;
            font-weight: 750;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;

            padding: 5px 9px;

            border-radius: 999px;

            background: #fff8e7;
            color: #946200;

            font-size: 11px;
            font-weight: 750;
        }

        .status-dot {
            width: 6px;
            height: 6px;

            border-radius: 50%;

            background: #e9a400;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-form {
            display: inline;
            margin: 0;
        }

        .action-btn {
            height: 34px;

            padding: 0 12px;

            border-radius: 8px;

            border: 1px solid transparent;

            font-size: 12px;
            font-weight: 700;

            cursor: pointer;

            transition:
                background 0.2s ease,
                transform 0.2s ease;
        }

        .approve-btn {
            background: #e8f7ee;
            border-color: #b8e4c8;
            color: #16743a;
        }

        .approve-btn:hover {
            background: #d9f2e2;
            transform: translateY(-1px);
        }

        .reject-btn {
            background: #fff0ef;
            border-color: #f2c4c0;
            color: #b42318;
        }

        .reject-btn:hover {
            background: #ffe4e1;
            transform: translateY(-1px);
        }

        .empty-state {
            padding: 70px 25px;

            text-align: center;
        }

        .empty-icon {
            width: 68px;
            height: 68px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 17px;

            border-radius: 50%;

            background: #eef4ff;

            color: #155eef;

            font-size: 28px;
            font-weight: 800;
        }

        .empty-state h3 {
            color: #101828;
            font-size: 19px;
            margin-bottom: 7px;
        }

        .empty-state p {
            max-width: 430px;
            margin: 0 auto;

            color: #667085;

            font-size: 13px;
            line-height: 1.6;
        }

        .footer {
            margin-top: 24px;

            text-align: center;

            color: #98a2b3;

            font-size: 11px;
        }

        @media (max-width: 760px) {

            .topbar {
                height: auto;
                padding: 15px 18px;
                gap: 15px;
            }

            .topbar-right {
                gap: 10px;
            }

            .user-info {
                display: none;
            }

            .page {
                padding: 25px 15px 40px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .pending-count {
                width: 100%;
            }

            .page-title h1 {
                font-size: 25px;
            }
        }

    </style>

</head>

<body>

<header class="topbar">

    <div class="brand">

        <img
            src="../assets/images/sportsync-mark.svg"
            alt="SportSync"
            class="brand-logo"
        >

        <div class="brand-text">

            <div class="brand-name">
                SportSync
            </div>

            <div class="brand-tagline">
                Smart Sports Management System
            </div>

        </div>

    </div>

    <div class="topbar-right">

        <div class="user-info">

            <div class="user-name">
                <?= e($currentUser['full_name'] ?? 'Administrator') ?>
            </div>

            <div class="user-role">
                <?= e(str_replace('_', ' ', $userRole)) ?>
            </div>

        </div>

        <a
            href="logout.php"
            class="logout-btn"
        >
            Logout
        </a>

    </div>

</header>

<main class="page">

    <div class="breadcrumb">

        <a href="dashboard.php">
            Dashboard
        </a>

        <span>›</span>

        <span>
            Pending Students
        </span>

    </div>

    <div class="page-header">

        <div class="page-title">

            <h1>
                Pending Student Registrations
            </h1>

            <p>
                Review and approve student accounts waiting for access
                to SportSync.
            </p>

        </div>

        <div class="pending-count">

            <strong>
                <?= count($students) ?>
            </strong>

            <span>
                Pending Registration<?= count($students) === 1 ? '' : 's' ?>
            </span>

        </div>

    </div>

    <section class="card">

        <div class="card-header">

            <div>

                <h2>
                    Students Awaiting Approval
                </h2>

                <p>
                    Approve valid registrations or reject applications
                    that should not receive access.
                </p>

            </div>

        </div>

        <?php if (empty($students)): ?>

            <div class="empty-state">

                <div class="empty-icon">
                    ✓
                </div>

                <h3>
                    No Pending Registrations
                </h3>

                <p>
                    There are currently no student accounts waiting
                    for approval. New student registrations will appear
                    here automatically.
                </p>

            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                Student ID
                            </th>

                            <th>
                                Department
                            </th>

                            <th>
                                Course
                            </th>

                            <th>
                                Academic Year
                            </th>

                            <th>
                                Semester
                            </th>

                            <th>
                                Gender
                            </th>

                            <th>
                                Registered
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($students as $student): ?>

                        <tr>

                            <td>

                                <div class="student-name">
                                    <?= e($student['full_name']) ?>
                                </div>

                                <div class="student-email">
                                    <?= e($student['email']) ?>
                                </div>

                            </td>

                            <td>

                                <span class="student-id">
                                    <?= e($student['student_id']) ?>
                                </span>

                            </td>

                            <td>
                                <?= e($student['department']) ?>
                            </td>

                            <td>
                                <?= e($student['course']) ?: '—' ?>
                            </td>

                            <td>
                                <?= e($student['academic_year']) ?>
                            </td>

                            <td>
                                <?= e(
                                    $student['semester'] !== null
                                        ? (string) $student['semester']
                                        : '—'
                                ) ?>
                            </td>

                            <td>
                                <?= e($student['gender']) ?: '—' ?>
                            </td>

                            <td>
                                <?= e($student['created_at']) ?>
                            </td>

                            <td>

                                <span class="status-badge">

                                    <span class="status-dot"></span>

                                    Pending

                                </span>

                            </td>

                            <td>

                                <div class="actions">

                                    <form
                                        method="POST"
                                        action="approve-student.php"
                                        class="action-form"
                                    >

                                        <?= csrfField() ?>

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int) $student['user_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="action-btn approve-btn"
                                        >
                                            Approve
                                        </button>

                                    </form>

                                    <form
                                        method="POST"
                                        action="reject-student.php"
                                        class="action-form"
                                    >

                                        <?= csrfField() ?>

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int) $student['user_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="action-btn reject-btn"
                                        >
                                            Reject
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

    <div class="footer">

        SportSync · Smart Sports Management System

    </div>

</main>

</body>

</html>