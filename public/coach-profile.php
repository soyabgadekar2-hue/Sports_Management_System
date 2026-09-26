<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$user = currentUser();

if ($user === null || ($user['role'] ?? '') !== 'COACH') {
    http_response_code(403);
    exit('Forbidden');
}

$userId = (int) ($user['id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    exit('Unauthorized');
}

$db = db();

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['coach_profile_csrf'])
    || !is_string($_SESSION['coach_profile_csrf'])
) {
    $_SESSION['coach_profile_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['coach_profile_csrf'];

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function coachProfileEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function coachProfileLength(string $value): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

function coachProfileInitial(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return 'C';
    }

    return function_exists('mb_substr')
        ? strtoupper(mb_substr($name, 0, 1, 'UTF-8'))
        : strtoupper(substr($name, 0, 1));
}

function coachProfileStatusClass(string $status): string
{
    return match (strtoupper($status)) {
        'ACTIVE', 'APPROVED' => 'status-success',
        'PENDING' => 'status-warning',
        'INACTIVE', 'SUSPENDED', 'REJECTED' => 'status-danger',
        default => 'status-neutral',
    };
}

function coachProfileFormatDate(mixed $date): string
{
    if (!$date) {
        return 'Not available';
    }

    $timestamp = strtotime((string) $date);

    return $timestamp === false
        ? 'Not available'
        : date('d M Y', $timestamp);
}

function coachProfileFormatDateTime(mixed $date): string
{
    if (!$date) {
        return 'Not available';
    }

    $timestamp = strtotime((string) $date);

    return $timestamp === false
        ? 'Not available'
        : date('d M Y, h:i A', $timestamp);
}

/*
|--------------------------------------------------------------------------
| Load Coach Profile
|--------------------------------------------------------------------------
*/

$profileQuery = $db->prepare(
    'SELECT
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        u.account_status,
        u.last_login_at,
        u.created_at AS user_created_at,
        cp.coach_id,
        cp.employee_id,
        cp.designation,
        cp.specialization,
        cp.coach_status,
        cp.created_at AS coach_created_at,
        cp.updated_at AS coach_updated_at
     FROM users u
     INNER JOIN coach_profiles cp
        ON cp.user_id = u.user_id
     WHERE u.user_id = :user_id
     LIMIT 1'
);

$profileQuery->execute(['user_id' => $userId]);
$coach = $profileQuery->fetch();

if (!$coach) {
    http_response_code(404);
    exit('Coach profile not found.');
}

/*
|--------------------------------------------------------------------------
| Page State
|--------------------------------------------------------------------------
*/

$errors = [];
$successMessage = (string) ($_SESSION['coach_profile_success'] ?? '');
unset($_SESSION['coach_profile_success']);

$isEditing = isset($_GET['edit']) && $_GET['edit'] === '1';

/*
|--------------------------------------------------------------------------
| Handle Profile Update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isEditing = true;

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if (
        $submittedToken === ''
        || !hash_equals($csrfToken, $submittedToken)
    ) {
        $errors[] = 'Your session security token is invalid. Please refresh the page and try again.';
    }

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $designation = trim((string) ($_POST['designation'] ?? ''));
    $specialization = trim((string) ($_POST['specialization'] ?? ''));

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    } elseif (coachProfileLength($fullName) < 2) {
        $errors[] = 'Full name must contain at least 2 characters.';
    } elseif (coachProfileLength($fullName) > 120) {
        $errors[] = 'Full name cannot exceed 120 characters.';
    }

    if ($phone !== '' && coachProfileLength($phone) > 20) {
        $errors[] = 'Phone number cannot exceed 20 characters.';
    }

    if ($phone !== '' && !preg_match('/^[0-9+\-\s().]+$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }

    if ($designation !== '' && coachProfileLength($designation) > 100) {
        $errors[] = 'Designation cannot exceed 100 characters.';
    }

    if (
        $specialization !== ''
        && coachProfileLength($specialization) > 150
    ) {
        $errors[] = 'Specialization cannot exceed 150 characters.';
    }

    if (!$errors) {
        try {
            $db->beginTransaction();

            $updateUser = $db->prepare(
                'UPDATE users
                 SET
                    full_name = :full_name,
                    phone = :phone,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE user_id = :user_id'
            );

            $updateUser->execute([
                'full_name' => $fullName,
                'phone' => $phone !== '' ? $phone : null,
                'user_id' => $userId,
            ]);

            $updateCoach = $db->prepare(
                'UPDATE coach_profiles
                 SET
                    designation = :designation,
                    specialization = :specialization,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE coach_id = :coach_id
                   AND user_id = :user_id'
            );

            $updateCoach->execute([
                'designation' => $designation !== '' ? $designation : null,
                'specialization' => $specialization !== '' ? $specialization : null,
                'coach_id' => (int) $coach['coach_id'],
                'user_id' => $userId,
            ]);

            $db->commit();

            $_SESSION['coach_profile_csrf'] = bin2hex(random_bytes(32));
            $_SESSION['coach_profile_success'] =
                'Your profile has been updated successfully.';

            header('Location: coach-profile.php');
            exit;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log(
                'Coach profile update failed: ' . $exception->getMessage()
            );

            $errors[] = 'We could not update your profile right now. Please try again.';
        }
    }

    /*
     * Keep entered values visible if validation or saving fails.
     */
    $coach['full_name'] = $fullName;
    $coach['phone'] = $phone;
    $coach['designation'] = $designation;
    $coach['specialization'] = $specialization;
}

/*
|--------------------------------------------------------------------------
| Display Values
|--------------------------------------------------------------------------
*/

$fullName = trim((string) ($coach['full_name'] ?? ''));
if ($fullName === '') {
    $fullName = 'Coach';
}

$email = trim((string) ($coach['email'] ?? ''));
$phone = trim((string) ($coach['phone'] ?? ''));
$employeeId = trim((string) ($coach['employee_id'] ?? ''));
$designation = trim((string) ($coach['designation'] ?? ''));
$specialization = trim((string) ($coach['specialization'] ?? ''));

$coachStatus = strtoupper(trim((string) ($coach['coach_status'] ?? '')));
$accountStatus = strtoupper(trim((string) ($coach['account_status'] ?? '')));

$initial = coachProfileInitial($fullName);

$memberSince = $coach['coach_created_at']
    ?? $coach['user_created_at']
    ?? null;

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* =========================================================
   COACH PROFILE
========================================================= */

.coach-profile-page {
    width: 100%;
    max-width: 1180px;
    margin: 0 auto;
    padding: 28px 24px 40px;
    box-sizing: border-box;
    color: #172033;
}

.coach-profile-page * {
    box-sizing: border-box;
}

.coach-profile-page .profile-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 24px;
}

.coach-profile-page .profile-page-title {
    margin: 0;
    font-size: 28px;
    line-height: 1.2;
    font-weight: 800;
    letter-spacing: -0.5px;
}

.coach-profile-page .profile-page-subtitle {
    margin: 7px 0 0;
    color: #64748b;
    font-size: 14px;
    line-height: 1.6;
}

/* Alerts */

.coach-profile-page .profile-alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 20px;
    padding: 14px 16px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.55;
}

.coach-profile-page .profile-alert-success {
    color: #166534;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
}

.coach-profile-page .profile-alert-error {
    color: #991b1b;
    background: #fef2f2;
    border: 1px solid #fecaca;
}

.coach-profile-page .profile-alert ul {
    margin: 6px 0 0;
    padding-left: 18px;
}

/* Profile header card */

.coach-profile-page .profile-hero {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 20px;
    padding: 24px;
    margin-bottom: 22px;
    background: linear-gradient(135deg, #eff6ff 0%, #ffffff 65%);
    border: 1px solid #dbeafe;
    border-radius: 18px;
    box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06);
}

.coach-profile-page .profile-avatar {
    width: 76px;
    height: 76px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 20px;
    background: #2563eb;
    color: #fff;
    font-size: 30px;
    font-weight: 800;
    box-shadow: 0 10px 24px rgba(37, 99, 235, 0.22);
}

.coach-profile-page .profile-hero-name {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
}

.coach-profile-page .profile-hero-role {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 13px;
}

.coach-profile-page .profile-statuses {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
}

/* Status badges */

.coach-profile-page .profile-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 11px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.2px;
    white-space: nowrap;
}

.coach-profile-page .profile-status::before {
    content: "";
    width: 6px;
    height: 6px;
    flex: 0 0 6px;
    border-radius: 50%;
    background: currentColor;
}

.coach-profile-page .status-success {
    color: #166534;
    background: #dcfce7;
}

.coach-profile-page .status-warning {
    color: #92400e;
    background: #fef3c7;
}

.coach-profile-page .status-danger {
    color: #991b1b;
    background: #fee2e2;
}

.coach-profile-page .status-neutral {
    color: #475569;
    background: #f1f5f9;
}

/* Main columns */

.coach-profile-page .profile-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.8fr);
    gap: 22px;
    align-items: stretch;
}

.coach-profile-page .profile-grid > .profile-card {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.coach-profile-page .profile-grid > .profile-card > .profile-card-body {
    flex: 1;
}

.coach-profile-page .profile-card {
    min-width: 0;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    box-shadow: 0 5px 22px rgba(15, 23, 42, 0.05);
}

.coach-profile-page .profile-card-header {
    padding: 19px 20px;
    border-bottom: 1px solid #eef2f7;
}

.coach-profile-page .profile-card-title {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
}

.coach-profile-page .profile-card-description {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}

.coach-profile-page .profile-card-body {
    padding: 20px;
}

/* Detail groups */

.coach-profile-page .profile-detail-group + .profile-detail-group {
    margin-top: 25px;
    padding-top: 22px;
    border-top: 1px solid #eef2f7;
}

.coach-profile-page .profile-group-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0 0 15px;
    color: #334155;
    font-size: 13px;
    font-weight: 800;
}

.coach-profile-page .profile-group-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 29px;
    height: 29px;
    border-radius: 9px;
    color: #1d4ed8;
    background: #eff6ff;
    font-size: 14px;
}

.coach-profile-page .profile-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.coach-profile-page .profile-detail {
    min-width: 0;
    padding: 13px 14px;
    background: #f8fafc;
    border: 1px solid #eef2f7;
    border-radius: 11px;
}

.coach-profile-page .profile-detail-label {
    display: block;
    margin-bottom: 6px;
    color: #64748b;
    font-size: 11px;
    font-weight: 650;
}

.coach-profile-page .profile-detail-value {
    display: block;
    color: #172033;
    font-size: 13px;
    font-weight: 750;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.coach-profile-page .profile-detail-value-muted {
    color: #94a3b8;
    font-weight: 500;
}

/* Form */

.coach-profile-page .profile-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.coach-profile-page .profile-form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
    min-width: 0;
}

.coach-profile-page .profile-label {
    color: #334155;
    font-size: 12px;
    font-weight: 750;
}

.coach-profile-page .profile-required {
    color: #dc2626;
}

.coach-profile-page .profile-input {
    width: 100%;
    min-height: 44px;
    padding: 11px 12px;
    color: #172033;
    background: #fff;
    border: 1px solid #d8dee8;
    border-radius: 10px;
    outline: none;
    font-family: inherit;
    font-size: 13px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.coach-profile-page .profile-input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
}

.coach-profile-page .profile-input-readonly {
    color: #64748b;
    background: #f8fafc;
    cursor: not-allowed;
}

.coach-profile-page .profile-help {
    margin: 0;
    color: #94a3b8;
    font-size: 11px;
    line-height: 1.4;
}

.coach-profile-page .profile-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1px solid #eef2f7;
}

/* Buttons */

.coach-profile-page .profile-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
    padding: 0 17px;
    border: 0;
    border-radius: 10px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 750;
    cursor: pointer;
    text-decoration: none;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}

.coach-profile-page .profile-button:hover {
    transform: translateY(-1px);
}

.coach-profile-page .profile-button-primary {
    color: #fff;
    background: #2563eb;
    box-shadow: 0 6px 15px rgba(37, 99, 235, 0.18);
}

.coach-profile-page .profile-button-primary:hover {
    background: #1d4ed8;
}

.coach-profile-page .profile-button-secondary {
    color: #475569;
    background: #f1f5f9;
}

.coach-profile-page .profile-button-secondary:hover {
    background: #e2e8f0;
}

/* Account information */

.coach-profile-page .profile-info-list {
    display: flex;
    flex-direction: column;
}

.coach-profile-page .profile-info-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 14px 0;
    border-bottom: 1px solid #eef2f7;
}

.coach-profile-page .profile-info-item:first-child {
    padding-top: 0;
}

.coach-profile-page .profile-info-item:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.coach-profile-page .profile-info-label {
    color: #64748b;
    font-size: 12px;
}

.coach-profile-page .profile-info-value {
    max-width: 62%;
    color: #172033;
    font-size: 12px;
    font-weight: 750;
    text-align: right;
    overflow-wrap: anywhere;
}

.coach-profile-page .profile-notice {
    margin-top: 18px;
    padding: 13px 14px;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 11px;
    line-height: 1.55;
}

.coach-profile-page .profile-notice strong {
    color: #334155;
}

/* Responsive */

@media (max-width: 900px) {
    .coach-profile-page .profile-grid {
        grid-template-columns: 1fr;
    }

    .coach-profile-page .profile-hero {
        grid-template-columns: auto minmax(0, 1fr);
    }

    .coach-profile-page .profile-statuses {
        grid-column: 1 / -1;
        justify-content: flex-start;
    }
}

@media (max-width: 700px) {
    .coach-profile-page {
        padding: 20px 16px 32px;
    }

    .coach-profile-page .profile-page-title {
        font-size: 24px;
    }

    .coach-profile-page .profile-hero {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .coach-profile-page .profile-avatar {
        margin: 0 auto;
    }

    .coach-profile-page .profile-statuses {
        justify-content: center;
    }

    .coach-profile-page .profile-detail-grid,
    .coach-profile-page .profile-form-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .coach-profile-page {
        padding-left: 12px;
        padding-right: 12px;
    }

    .coach-profile-page .profile-page-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .coach-profile-page .profile-page-header .profile-button {
        width: 100%;
    }

    .coach-profile-page .profile-card-body,
    .coach-profile-page .profile-card-header,
    .coach-profile-page .profile-hero {
        padding: 16px;
    }

    .coach-profile-page .profile-form-actions {
        flex-direction: column-reverse;
        align-items: stretch;
    }

    .coach-profile-page .profile-form-actions .profile-button {
        width: 100%;
    }

    .coach-profile-page .profile-info-item {
        flex-direction: column;
        gap: 5px;
    }

    .coach-profile-page .profile-info-value {
        max-width: 100%;
        text-align: left;
    }
}

@media (prefers-reduced-motion: reduce) {
    .coach-profile-page .profile-input,
    .coach-profile-page .profile-button {
        transition: none;
    }

    .coach-profile-page .profile-button:hover {
        transform: none;
    }
}
</style>

<div class="coach-profile-page">

    <div class="profile-page-header">
        <div>
            <h1 class="profile-page-title">My Profile</h1>
            <p class="profile-page-subtitle">
                View and manage your personal, professional, and account details.
            </p>
        </div>

        <?php if (!$isEditing): ?>
            <a
                href="coach-profile.php?edit=1"
                class="profile-button profile-button-primary"
            >
                <span aria-hidden="true">✎</span>
                Edit Profile
            </a>
        <?php endif; ?>
    </div>

    <?php if ($successMessage !== ''): ?>
        <div class="profile-alert profile-alert-success" role="status">
            <span aria-hidden="true">✓</span>
            <div><?= coachProfileEscape($successMessage) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="profile-alert profile-alert-error" role="alert">
            <span aria-hidden="true">!</span>
            <div>
                <strong>Please check the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= coachProfileEscape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <section class="profile-hero">
        <div class="profile-avatar" aria-hidden="true">
            <?= coachProfileEscape($initial) ?>
        </div>

        <div>
            <h2 class="profile-hero-name">
                <?= coachProfileEscape($fullName) ?>
            </h2>
            <p class="profile-hero-role">
                <?= coachProfileEscape($designation !== '' ? $designation : 'Coach') ?>
            </p>
        </div>

        <div class="profile-statuses">
            <span class="profile-status <?= coachProfileEscape(coachProfileStatusClass($coachStatus)) ?>">
                Coach: <?= coachProfileEscape($coachStatus !== '' ? $coachStatus : 'UNKNOWN') ?>
            </span>
            <span class="profile-status <?= coachProfileEscape(coachProfileStatusClass($accountStatus)) ?>">
                Account: <?= coachProfileEscape($accountStatus !== '' ? $accountStatus : 'UNKNOWN') ?>
            </span>
        </div>
    </section>

    <div class="profile-grid">

        <!-- PERSONAL & PROFESSIONAL INFORMATION -->
        <section class="profile-card">
            <div class="profile-card-header">
                <h2 class="profile-card-title">Personal & Professional Information</h2>
                <p class="profile-card-description">
                    Your personal contact and professional details.
                </p>
            </div>

            <div class="profile-card-body">

                <?php if ($isEditing): ?>

                    <form method="post" action="coach-profile.php" autocomplete="off">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= coachProfileEscape($csrfToken) ?>"
                        >

                        <div class="profile-detail-group">
                            <h3 class="profile-group-title">
                                <span class="profile-group-icon" aria-hidden="true">♙</span>
                                Personal Details
                            </h3>

                            <div class="profile-form-grid">
                                <div class="profile-form-group">
                                    <label class="profile-label" for="full_name">
                                        Full Name <span class="profile-required">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="full_name"
                                        name="full_name"
                                        class="profile-input"
                                        value="<?= coachProfileEscape($fullName) ?>"
                                        maxlength="120"
                                        required
                                    >
                                </div>

                                <div class="profile-form-group">
                                    <label class="profile-label" for="phone">
                                        Phone Number
                                    </label>
                                    <input
                                        type="tel"
                                        id="phone"
                                        name="phone"
                                        class="profile-input"
                                        value="<?= coachProfileEscape($phone) ?>"
                                        maxlength="20"
                                        placeholder="Enter phone number"
                                    >
                                </div>

                                <div class="profile-form-group">
                                    <label class="profile-label" for="email">
                                        Email Address
                                    </label>
                                    <input
                                        type="email"
                                        id="email"
                                        class="profile-input profile-input-readonly"
                                        value="<?= coachProfileEscape($email) ?>"
                                        readonly
                                        aria-readonly="true"
                                    >
                                    <p class="profile-help">Email is managed by the system.</p>
                                </div>
                            </div>
                        </div>

                        <div class="profile-detail-group">
                            <h3 class="profile-group-title">
                                <span class="profile-group-icon" aria-hidden="true">▣</span>
                                Professional Details
                            </h3>

                            <div class="profile-form-grid">
                                <div class="profile-form-group">
                                    <label class="profile-label" for="employee_id">
                                        Employee ID
                                    </label>
                                    <input
                                        type="text"
                                        id="employee_id"
                                        class="profile-input profile-input-readonly"
                                        value="<?= coachProfileEscape($employeeId) ?>"
                                        readonly
                                        aria-readonly="true"
                                    >
                                    <p class="profile-help">Employee ID cannot be changed here.</p>
                                </div>

                                <div class="profile-form-group">
                                    <label class="profile-label" for="designation">
                                        Designation
                                    </label>
                                    <input
                                        type="text"
                                        id="designation"
                                        name="designation"
                                        class="profile-input"
                                        value="<?= coachProfileEscape($designation) ?>"
                                        maxlength="100"
                                        placeholder="e.g. Head Coach"
                                    >
                                </div>

                                <div class="profile-form-group">
                                    <label class="profile-label" for="specialization">
                                        Specialization
                                    </label>
                                    <input
                                        type="text"
                                        id="specialization"
                                        name="specialization"
                                        class="profile-input"
                                        value="<?= coachProfileEscape($specialization) ?>"
                                        maxlength="150"
                                        placeholder="e.g. Football / Fitness"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="profile-form-actions">
                            <a
                                href="coach-profile.php"
                                class="profile-button profile-button-secondary"
                            >
                                Cancel
                            </a>
                            <button
                                type="submit"
                                class="profile-button profile-button-primary"
                            >
                                <span aria-hidden="true">✓</span>
                                Save Changes
                            </button>
                        </div>
                    </form>

                <?php else: ?>

                    <div class="profile-detail-group">
                        <h3 class="profile-group-title">
                            <span class="profile-group-icon" aria-hidden="true">♙</span>
                            Personal Details
                        </h3>

                        <div class="profile-detail-grid">
                            <div class="profile-detail">
                                <span class="profile-detail-label">Full Name</span>
                                <span class="profile-detail-value">
                                    <?= coachProfileEscape($fullName) ?>
                                </span>
                            </div>

                            <div class="profile-detail">
                                <span class="profile-detail-label">Phone Number</span>
                                <span class="profile-detail-value">
                                    <?= $phone !== ''
                                        ? coachProfileEscape($phone)
                                        : '<span class="profile-detail-value-muted">Not specified</span>'
                                    ?>
                                </span>
                            </div>

                            <div class="profile-detail">
                                <span class="profile-detail-label">Email Address</span>
                                <span class="profile-detail-value">
                                    <?= $email !== ''
                                        ? coachProfileEscape($email)
                                        : '<span class="profile-detail-value-muted">Not specified</span>'
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="profile-detail-group">
                        <h3 class="profile-group-title">
                            <span class="profile-group-icon" aria-hidden="true">▣</span>
                            Professional Details
                        </h3>

                        <div class="profile-detail-grid">
                            <div class="profile-detail">
                                <span class="profile-detail-label">Employee ID</span>
                                <span class="profile-detail-value">
                                    <?= $employeeId !== ''
                                        ? coachProfileEscape($employeeId)
                                        : '<span class="profile-detail-value-muted">Not specified</span>'
                                    ?>
                                </span>
                            </div>

                            <div class="profile-detail">
                                <span class="profile-detail-label">Designation</span>
                                <span class="profile-detail-value">
                                    <?= $designation !== ''
                                        ? coachProfileEscape($designation)
                                        : '<span class="profile-detail-value-muted">Not specified</span>'
                                    ?>
                                </span>
                            </div>

                            <div class="profile-detail">
                                <span class="profile-detail-label">Specialization</span>
                                <span class="profile-detail-value">
                                    <?= $specialization !== ''
                                        ? coachProfileEscape($specialization)
                                        : '<span class="profile-detail-value-muted">Not specified</span>'
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </section>

        <!-- ACCOUNT INFORMATION -->
        <aside class="profile-card">
            <div class="profile-card-header">
                <h2 class="profile-card-title">Account Information</h2>
                <p class="profile-card-description">
                    System-managed account details and activity.
                </p>
            </div>

            <div class="profile-card-body">

                <div class="profile-detail-group">
                    <h3 class="profile-group-title">
                        <span class="profile-group-icon" aria-hidden="true">▤</span>
                        Account Details
                    </h3>

                    <div class="profile-info-list">
                        <div class="profile-info-item">
                            <span class="profile-info-label">Coach ID</span>
                            <span class="profile-info-value">
                                #<?= coachProfileEscape((string) ($coach['coach_id'] ?? '')) ?>
                            </span>
                        </div>

                        <div class="profile-info-item">
                            <span class="profile-info-label">Coach Status</span>
                            <span class="profile-info-value">
                                <span class="profile-status <?= coachProfileEscape(coachProfileStatusClass($coachStatus)) ?>">
                                    <?= coachProfileEscape($coachStatus !== '' ? $coachStatus : 'UNKNOWN') ?>
                                </span>
                            </span>
                        </div>

                        <div class="profile-info-item">
                            <span class="profile-info-label">Account Status</span>
                            <span class="profile-info-value">
                                <span class="profile-status <?= coachProfileEscape(coachProfileStatusClass($accountStatus)) ?>">
                                    <?= coachProfileEscape($accountStatus !== '' ? $accountStatus : 'UNKNOWN') ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="profile-detail-group">
                    <h3 class="profile-group-title">
                        <span class="profile-group-icon" aria-hidden="true">◷</span>
                        Activity
                    </h3>

                    <div class="profile-info-list">
                        <div class="profile-info-item">
                            <span class="profile-info-label">Member Since</span>
                            <span class="profile-info-value">
                                <?= coachProfileEscape(coachProfileFormatDate($memberSince)) ?>
                            </span>
                        </div>

                        <div class="profile-info-item">
                            <span class="profile-info-label">Last Login</span>
                            <span class="profile-info-value">
                                <?= coachProfileEscape(
                                    coachProfileFormatDateTime($coach['last_login_at'] ?? null)
                                ) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="profile-notice">
                    <strong>Note:</strong>
                    Email address and employee ID are controlled by the system
                    and cannot be edited from this page.
                </div>

            </div>
        </aside>

    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';