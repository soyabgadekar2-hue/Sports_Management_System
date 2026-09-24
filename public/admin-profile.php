<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SportSync - Admin Profile
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

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

$fullName = $user['full_name'] ?? 'Administrator';
$email = $user['email'] ?? 'Not available';
$userId = (string) ($user['user_id'] ?? 'N/A');
$role = $user['role'] ?? 'ADMIN';
$status = $user['account_status'] ?? 'APPROVED';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Profile | SportSync</title>

    <meta
        name="description"
        content="SportSync Administrator Profile"
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

        .admin-profile-page {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .profile-header-card {
            display: flex;
            align-items: center;
            gap: 22px;
            padding: 28px;
            border: 1px solid #e5eaf2;
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(20, 35, 65, 0.06);
        }

        .profile-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 88px;
            height: 88px;
            flex: 0 0 auto;
            border-radius: 50%;
            background:
                linear-gradient(
                    135deg,
                    #0b1f4d,
                    #2563eb
                );
            color: #ffffff;
            font-size: 32px;
            font-weight: 800;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.2);
        }

        .profile-header-content {
            min-width: 0;
        }

        .profile-label {
            margin: 0 0 7px;
            color: #2563eb;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .profile-header-content h1 {
            margin: 0;
            color: #111827;
            font-size: 27px;
            font-weight: 800;
        }

        .profile-header-content p {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .profile-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 1.35fr)
                minmax(300px, 0.8fr);
            gap: 22px;
        }

        .profile-card {
            overflow: hidden;
            border: 1px solid #e8edf5;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(20, 35, 65, 0.05);
        }

        .profile-card-header {
            padding: 21px 24px;
            border-bottom: 1px solid #edf1f6;
        }

        .profile-card-header h2 {
            margin: 0;
            color: #111827;
            font-size: 17px;
            font-weight: 800;
        }

        .profile-card-header p {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .profile-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0;
        }

        .profile-detail {
            padding: 19px 22px;
            border-bottom: 1px solid #edf1f6;
        }

        .profile-detail:nth-child(odd) {
            border-right: 1px solid #edf1f6;
        }

        .profile-detail-label {
            margin: 0 0 7px;
            color: #64748b;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .profile-detail-value {
            margin: 0;
            color: #111827;
            font-size: 14px;
            font-weight: 700;
            word-break: break-word;
        }

        .profile-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #ecfdf3;
            color: #15803d;
            font-size: 11px;
            font-weight: 800;
        }

        .profile-status::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
        }

        .profile-role {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eef4ff;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 800;
        }

        .profile-security {
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 22px;
        }

        .security-item {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            padding: 15px;
            border-radius: 14px;
            background: #f8fafc;
        }

        .security-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            flex: 0 0 auto;
            border-radius: 11px;
            background: #eef4ff;
            font-size: 17px;
        }

        .security-content {
            min-width: 0;
        }

        .security-content h3 {
            margin: 0;
            color: #111827;
            font-size: 13px;
            font-weight: 750;
        }

        .security-content p {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 11px;
            line-height: 1.5;
        }

        .profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 20px 22px;
            border-top: 1px solid #edf1f6;
        }

        .profile-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 16px;
            border-radius: 10px;
            background: #2563eb;
            color: #ffffff;
            font-size: 12px;
            font-weight: 750;
            text-decoration: none;
        }

        .profile-action:hover {
            background: #1d4ed8;
        }

        .profile-action.secondary {
            background: #f1f5f9;
            color: #334155;
        }

        .profile-action.secondary:hover {
            background: #e2e8f0;
        }

        @media (max-width: 900px) {

            .profile-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 650px) {

            .profile-header-card {
                flex-direction: column;
                align-items: flex-start;
                padding: 22px;
            }

            .profile-details {
                grid-template-columns: 1fr;
            }

            .profile-detail:nth-child(odd) {
                border-right: 0;
            }

        }

    </style>

</head>

<body>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-container">

    <main class="admin-profile-page">

        <section class="profile-header-card">

            <div class="profile-avatar">
                <?= e(strtoupper(substr($fullName, 0, 1))); ?>
            </div>

            <div class="profile-header-content">

                <p class="profile-label">
                    Administrator Profile
                </p>

                <h1>
                    <?= e($fullName); ?>
                </h1>

                <p>
                    <?= e($email); ?>
                </p>

            </div>

        </section>

        <section class="profile-grid">

            <div class="profile-card">

                <div class="profile-card-header">

                    <h2>
                        Account Information
                    </h2>

                    <p>
                        Your identity and account information in SportSync.
                    </p>

                </div>

                <div class="profile-details">

                    <div class="profile-detail">

                        <p class="profile-detail-label">
                            Full Name
                        </p>

                        <p class="profile-detail-value">
                            <?= e($fullName); ?>
                        </p>

                    </div>

                    <div class="profile-detail">

                        <p class="profile-detail-label">
                            Email Address
                        </p>

                        <p class="profile-detail-value">
                            <?= e($email); ?>
                        </p>

                    </div>

                    <div class="profile-detail">

                        <p class="profile-detail-label">
                            User ID
                        </p>

                        <p class="profile-detail-value">
                            <?= e($userId); ?>
                        </p>

                    </div>

                    <div class="profile-detail">

                        <p class="profile-detail-label">
                            Account Role
                        </p>

                        <p class="profile-detail-value">

                            <span class="profile-role">
                                <?= e($role); ?>
                            </span>

                        </p>

                    </div>

                    <div class="profile-detail">

                        <p class="profile-detail-label">
                            Account Status
                        </p>

                        <p class="profile-detail-value">

                            <span class="profile-status">
                                <?= e($status); ?>
                            </span>

                        </p>

                    </div>

                    <div class="profile-detail">

                        <p class="profile-detail-label">
                            Login Status
                        </p>

                        <p class="profile-detail-value">

                            <span class="profile-status">
                                Active
                            </span>

                        </p>

                    </div>

                </div>

            </div>

            <div class="profile-card">

                <div class="profile-card-header">

                    <h2>
                        Account & Security
                    </h2>

                    <p>
                        Important information about keeping your account secure.
                    </p>

                </div>

                <div class="profile-security">

                    <div class="security-item">

                        <div class="security-icon">
                            🔐
                        </div>

                        <div class="security-content">

                            <h3>
                                Secure Login
                            </h3>

                            <p>
                                Your SportSync session is protected using
                                the application's authentication system.
                            </p>

                        </div>

                    </div>

                    <div class="security-item">

                        <div class="security-icon">
                            🛡️
                        </div>

                        <div class="security-content">

                            <h3>
                                Administrator Access
                            </h3>

                            <p>
                                Your administrator permissions allow you to
                                manage system-level sports operations.
                            </p>

                        </div>

                    </div>

                    <div class="security-item">

                        <div class="security-icon">
                            👤
                        </div>

                        <div class="security-content">

                            <h3>
                                Profile Information
                            </h3>

                            <p>
                                Keep your personal account information
                                accurate and up to date.
                            </p>

                        </div>

                    </div>

                </div>

                <div class="profile-actions">

                    <a
                        href="admin-dashboard.php"
                        class="profile-action secondary"
                    >
                        ← Dashboard
                    </a>

                    <a
                        href="logout.php"
                        class="profile-action"
                    >
                        Logout
                    </a>

                </div>

            </div>

        </section>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>