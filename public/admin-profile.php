<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireLogin();

$user = currentUser();

if ($user === null) {
    http_response_code(401);
    exit('Invalid user session.');
}

if (($user['role'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    exit('Access denied.');
}

$pdo = db();

$admin = null;

$userId = (int) ($user['id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    exit('Invalid user session.');
}

/*
|--------------------------------------------------------------------------
| Update Profile
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    requireValidCsrfToken($_POST['csrf_token'] ?? null);

    $submittedName = trim((string) ($_POST['full_name'] ?? ''));
    $submittedEmail = trim((string) ($_POST['email'] ?? ''));

    if ($submittedName === '') {

        $errorMessage = 'Full name is required.';

    } elseif (mb_strlen($submittedName) < 2) {

        $errorMessage = 'Full name must contain at least 2 characters.';

    } elseif ($submittedEmail === '') {

        $errorMessage = 'Email address is required.';

    } elseif (!filter_var($submittedEmail, FILTER_VALIDATE_EMAIL)) {

        $errorMessage = 'Please enter a valid email address.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Check whether another user already uses this email
            |--------------------------------------------------------------------------
            */

            $emailCheck = $pdo->prepare(
                'SELECT user_id
                 FROM users
                 WHERE email = :email
                   AND user_id <> :user_id
                 LIMIT 1'
            );

            $emailCheck->execute([
                ':email' => $submittedEmail,
                ':user_id' => $userId,
            ]);

            $existingUser = $emailCheck->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {

                $errorMessage =
                    'This email address is already used by another account.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Update User
                |--------------------------------------------------------------------------
                */

                $update = $pdo->prepare(
                    'UPDATE users
                     SET full_name = :full_name,
                         email = :email
                     WHERE user_id = :user_id
                     LIMIT 1'
                );

                $update->execute([
                    ':full_name' => $submittedName,
                    ':email' => $submittedEmail,
                    ':user_id' => $userId,
                ]);

                $successMessage = 'Profile updated successfully.';
            }

        } catch (Throwable $exception) {

            error_log(
                'Admin profile update failed: ' .
                $exception->getMessage()
            );

            $errorMessage =
                'Unable to update your profile right now. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load Current Admin
|--------------------------------------------------------------------------
*/

try {

    $statement = $pdo->prepare(
        'SELECT
            u.user_id,
            u.full_name,
            u.email,
            u.account_status,
            u.last_login_at,
            r.role_name
         FROM users u
         INNER JOIN roles r
             ON r.role_id = u.role_id
         WHERE u.user_id = :user_id
         LIMIT 1'
    );

    $statement->execute([
        ':user_id' => $userId,
    ]);

    $admin = $statement->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $exception) {

    error_log(
        'Admin profile load failed: ' .
        $exception->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Validate Loaded User
|--------------------------------------------------------------------------
*/

if (!$admin) {
    http_response_code(404);
    exit('Administrator account could not be found.');
}

/*
|--------------------------------------------------------------------------
| Prepare Display Values
|--------------------------------------------------------------------------
*/

$fullName = trim(
    (string) ($admin['full_name'] ?? 'Administrator')
);

if ($fullName === '') {
    $fullName = 'Administrator';
}

$email = (string) ($admin['email'] ?? '');

$displayUserId = (int) ($admin['user_id'] ?? $userId);

$roleName = (string) (
    $admin['role_name'] ?? 'ADMIN'
);

$accountStatus = (string) (
    $admin['account_status'] ?? 'APPROVED'
);

$lastLogin = $admin['last_login_at'] ?? null;

/*
|--------------------------------------------------------------------------
| Profile Initial
|--------------------------------------------------------------------------
*/

$cleanName = preg_replace(
    '/[^A-Za-z]/',
    '',
    $fullName
);

$cleanName = $cleanName ?: 'A';

$initial = strtoupper(
    substr(
        $cleanName,
        0,
        1
    )
);

/*
|--------------------------------------------------------------------------
| Role Label
|--------------------------------------------------------------------------
*/

$roleLabel = ucwords(
    strtolower(
        str_replace(
            '_',
            ' ',
            $roleName
        )
    )
);

/*
|--------------------------------------------------------------------------
| Status Label
|--------------------------------------------------------------------------
*/

$statusLabel = ucwords(
    strtolower(
        str_replace(
            '_',
            ' ',
            $accountStatus
        )
    )
);

/*
|--------------------------------------------------------------------------
| Local Escape Helper
|
| IMPORTANT:
| Do NOT use function e() here because auth.php already
| contains an e() function.
|--------------------------------------------------------------------------
*/

function adminProfileEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Edit Form State
|--------------------------------------------------------------------------
|
| Closed by default.
| If there is an error after submitting the form,
| keep it open so the user can correct the problem.
|--------------------------------------------------------------------------
*/

$editFormOpen = $errorMessage !== '';

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>

/* =========================================================
   ADMIN PROFILE
   ========================================================= */

.admin-profile-page {
    max-width: 1180px;
    margin: 0 auto;
}

.admin-profile-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.5fr) minmax(280px, 0.8fr);
    gap: 24px;
    margin-bottom: 24px;
}

.admin-profile-card,
.admin-profile-security,
.admin-profile-info,
.admin-profile-edit {
    background: #ffffff;
    border: 1px solid #e4e7ec;
    border-radius: 18px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

.admin-profile-card {
    padding: 30px;
}

.admin-profile-identity {
    display: flex;
    align-items: center;
    gap: 22px;
}

.admin-profile-avatar {
    width: 104px;
    height: 104px;
    flex: 0 0 104px;
    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #ffffff;

    font-size: 38px;
    font-weight: 800;

    box-shadow: 0 10px 24px rgba(37, 99, 235, 0.25);
}

.admin-profile-eyebrow {
    display: inline-block;
    margin-bottom: 7px;

    color: #2563eb;

    font-size: 12px;
    font-weight: 800;

    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.admin-profile-name {
    margin: 0 0 7px;

    color: #101828;

    font-size: 28px;
    line-height: 1.2;
}

.admin-profile-email {
    margin: 0;

    color: #667085;

    font-size: 15px;

    word-break: break-word;
}

.admin-profile-role {
    margin-top: 14px;

    display: inline-flex;
    align-items: center;
    gap: 7px;

    padding: 7px 11px;

    border-radius: 999px;

    background: #eff6ff;
    color: #1d4ed8;

    font-size: 12px;
    font-weight: 800;
}

.admin-profile-status-card {
    padding: 26px;

    display: flex;
    flex-direction: column;
    justify-content: center;
}

.admin-profile-status-title {
    margin: 0 0 8px;

    color: #101828;

    font-size: 18px;
}

.admin-profile-status-text {
    margin: 0 0 18px;

    color: #667085;

    font-size: 14px;
    line-height: 1.6;
}

.admin-profile-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    width: fit-content;

    padding: 9px 13px;

    border-radius: 999px;

    background: #ecfdf3;
    color: #027a48;

    font-size: 13px;
    font-weight: 800;
}

.admin-profile-status-dot {
    width: 8px;
    height: 8px;

    border-radius: 50%;

    background: #12b76a;
}

.admin-profile-section-title {
    margin: 0 0 5px;

    color: #101828;

    font-size: 20px;
}

.admin-profile-section-subtitle {
    margin: 0 0 22px;

    color: #667085;

    font-size: 14px;
    line-height: 1.6;
}

.admin-profile-info {
    padding: 26px;
    margin-bottom: 24px;
}

.admin-profile-details {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.admin-profile-detail {
    padding: 18px;

    border: 1px solid #eaecf0;
    border-radius: 13px;

    background: #f9fafb;
}

.admin-profile-detail-label {
    display: block;
    margin-bottom: 7px;

    color: #667085;

    font-size: 12px;
    font-weight: 700;

    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.admin-profile-detail-value {
    display: block;

    color: #101828;

    font-size: 15px;
    font-weight: 700;

    word-break: break-word;
}

.admin-profile-security {
    padding: 26px;
    margin-bottom: 24px;
}

.admin-profile-security-row {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;

    padding: 18px 0;

    border-top: 1px solid #eaecf0;
}

.admin-profile-security-row:first-of-type {
    border-top: 0;
    padding-top: 0;
}

.admin-profile-security-row:last-of-type {
    padding-bottom: 0;
}

.admin-profile-security-label {
    margin: 0 0 4px;

    color: #101828;

    font-size: 15px;
    font-weight: 700;
}

.admin-profile-security-description {
    margin: 0;

    color: #667085;

    font-size: 13px;
    line-height: 1.5;
}

.admin-profile-security-value {
    flex: 0 0 auto;

    padding: 7px 11px;

    border-radius: 8px;

    background: #f2f4f7;
    color: #475467;

    font-size: 12px;
    font-weight: 700;
}

.admin-profile-photo-note {
    margin-top: 20px;

    padding: 14px 16px;

    border-radius: 11px;

    border: 1px solid #dbeafe;

    background: #eff6ff;
    color: #1e40af;

    font-size: 13px;
    line-height: 1.55;
}


/* =========================================================
   EDIT PROFILE
   ========================================================= */

.admin-profile-edit {
    margin-bottom: 24px;
    padding: 0;

    overflow: hidden;
}

/* Edit Profile Header */

.admin-profile-edit-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;

    padding: 22px 26px;
}

.admin-profile-edit-heading {
    min-width: 0;
}

.admin-profile-edit-heading .admin-profile-section-title {
    margin-bottom: 5px;
}

.admin-profile-edit-heading .admin-profile-section-subtitle {
    margin: 0;
}

/* Edit Button */

.admin-profile-edit-button,
.admin-profile-cancel-button,
.admin-profile-save-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 7px;

    border-radius: 10px;

    padding: 10px 16px;

    font-family: inherit;
    font-size: 14px;
    font-weight: 700;

    cursor: pointer;

    transition:
        background 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.admin-profile-edit-button {
    flex: 0 0 auto;

    border: 1px solid #2563eb;

    background: #2563eb;
    color: #ffffff;
}

.admin-profile-edit-button:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;

    transform: translateY(-1px);

    box-shadow: 0 5px 12px rgba(37, 99, 235, 0.18);
}

/* Edit Form Area */

.admin-profile-edit-body {
    display: none;

    padding: 26px;

    border-top: 1px solid #eaecf0;

    background: #fcfcfd;
}

.admin-profile-edit.is-open .admin-profile-edit-body {
    display: block;
}

.admin-profile-edit.is-open .admin-profile-edit-header {
    background: #ffffff;
}

/* Form */

.admin-profile-form {
    display: grid;

    grid-template-columns: repeat(2, minmax(0, 1fr));

    gap: 18px;
}

.admin-profile-form-group {
    display: flex;
    flex-direction: column;
}

.admin-profile-form-group.full-width {
    grid-column: 1 / -1;
}

.admin-profile-form-label {
    margin-bottom: 7px;

    color: #344054;

    font-size: 13px;
    font-weight: 700;
}

.admin-profile-form-input {
    width: 100%;
    box-sizing: border-box;

    padding: 12px 14px;

    border: 1px solid #d0d5dd;
    border-radius: 10px;

    background: #ffffff;
    color: #101828;

    font-family: inherit;
    font-size: 14px;

    outline: none;

    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.admin-profile-form-input:focus {
    border-color: #2563eb;

    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.admin-profile-form-help {
    margin-top: 6px;

    color: #667085;

    font-size: 12px;
    line-height: 1.45;
}

/* Form Buttons */

.admin-profile-form-actions {
    grid-column: 1 / -1;

    display: flex;
    align-items: center;
    justify-content: flex-end;

    gap: 10px;

    margin-top: 4px;
}

.admin-profile-cancel-button {
    border: 1px solid #d0d5dd;

    background: #ffffff;
    color: #344054;
}

.admin-profile-cancel-button:hover {
    background: #f9fafb;
    border-color: #98a2b3;
}

.admin-profile-save-button {
    border: 0;

    background: #2563eb;
    color: #ffffff;
}

.admin-profile-save-button:hover {
    background: #1d4ed8;

    transform: translateY(-1px);

    box-shadow: 0 5px 12px rgba(37, 99, 235, 0.18);
}


/* =========================================================
   ALERT MESSAGES
   ========================================================= */

.admin-profile-alert {
    margin-bottom: 20px;

    padding: 13px 15px;

    border-radius: 10px;

    font-size: 13px;
    line-height: 1.5;
}

.admin-profile-alert.success {
    border: 1px solid #abefc6;

    background: #ecfdf3;
    color: #027a48;
}

.admin-profile-alert.error {
    border: 1px solid #fecdca;

    background: #fef3f2;
    color: #b42318;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 850px) {

    .admin-profile-hero {
        grid-template-columns: 1fr;
    }

    .admin-profile-form {
        grid-template-columns: 1fr;
    }

    .admin-profile-form-group.full-width {
        grid-column: auto;
    }

    .admin-profile-form-actions {
        grid-column: auto;
    }
}


@media (max-width: 600px) {

    .admin-profile-card,
    .admin-profile-status-card,
    .admin-profile-info,
    .admin-profile-security {
        padding: 20px;

        border-radius: 14px;
    }

    .admin-profile-edit {
        border-radius: 14px;
    }

    .admin-profile-edit-header {
        align-items: flex-start;
        flex-direction: column;

        padding: 20px;
    }

    .admin-profile-edit-button {
        width: 100%;
    }

    .admin-profile-edit-body {
        padding: 20px;
    }

    .admin-profile-identity {
        align-items: flex-start;

        flex-direction: column;
    }

    .admin-profile-avatar {
        width: 88px;
        height: 88px;

        flex-basis: 88px;

        font-size: 32px;
    }

    .admin-profile-name {
        font-size: 23px;
    }

    .admin-profile-details {
        grid-template-columns: 1fr;
    }

    .admin-profile-security-row {
        align-items: flex-start;

        flex-direction: column;
    }

    .admin-profile-form-actions {
        align-items: stretch;

        flex-direction: column;
    }

    .admin-profile-save-button,
    .admin-profile-cancel-button {
        width: 100%;
    }
}

</style>


<div
    class="admin-profile-page"
    id="adminProfilePage"
>

    <!-- =====================================================
         PAGE HEADING
         ===================================================== -->

    <div class="page-heading">

        <div>

            <span class="admin-profile-eyebrow">
                ACCOUNT
            </span>

            <h1>
                Admin Profile
            </h1>

            <p>
                View and manage your administrator account information.
            </p>

        </div>

    </div>


    <!-- =====================================================
         ALERT MESSAGES
         ===================================================== -->

    <?php if ($successMessage !== ''): ?>

        <div class="admin-profile-alert success">
            <?= adminProfileEscape($successMessage) ?>
        </div>

    <?php endif; ?>


    <?php if ($errorMessage !== ''): ?>

        <div class="admin-profile-alert error">
            <?= adminProfileEscape($errorMessage) ?>
        </div>

    <?php endif; ?>


    <!-- =====================================================
         PROFILE HERO
         ===================================================== -->

    <section class="admin-profile-hero">

        <div class="admin-profile-card">

            <div class="admin-profile-identity">

                <div
                    class="admin-profile-avatar"
                    aria-label="Admin profile avatar"
                >
                    <?= adminProfileEscape($initial) ?>
                </div>


                <div>

                    <span class="admin-profile-eyebrow">
                        Administrator Account
                    </span>

                    <h2 class="admin-profile-name">
                        <?= adminProfileEscape($fullName) ?>
                    </h2>

                    <p class="admin-profile-email">
                        <?= adminProfileEscape($email) ?>
                    </p>

                    <span class="admin-profile-role">
                        🛡️ <?= adminProfileEscape($roleLabel) ?>
                    </span>

                </div>

            </div>


            <div class="admin-profile-photo-note">

                <strong>Profile photo:</strong>

                Your current profile uses a secure fallback avatar.

                Profile photo upload can be added later as a common
                profile feature for all SportSync roles.

            </div>

        </div>


        <!-- =================================================
             ACCOUNT STATUS
             ================================================= -->

        <div class="admin-profile-card admin-profile-status-card">

            <h2 class="admin-profile-status-title">
                Account Status
            </h2>

            <p class="admin-profile-status-text">
                This is the current status of your administrator account.
            </p>

            <span class="admin-profile-status">

                <span class="admin-profile-status-dot"></span>

                <?= adminProfileEscape($statusLabel) ?>

            </span>

        </div>

    </section>


    <!-- =====================================================
         EDIT PROFILE
         ===================================================== -->

    <section
        class="admin-profile-edit <?= $editFormOpen ? 'is-open' : '' ?>"
        id="editProfileSection"
    >

        <!-- Edit Profile Header -->

        <div class="admin-profile-edit-header">

            <div class="admin-profile-edit-heading">

                <h2 class="admin-profile-section-title">
                    Edit Profile
                </h2>

                <p class="admin-profile-section-subtitle">
                    Update your administrator name and email address.
                </p>

            </div>


            <button
                type="button"
                class="admin-profile-edit-button"
                id="editProfileButton"
                aria-expanded="<?= $editFormOpen ? 'true' : 'false' ?>"
                aria-controls="editProfileBody"
            >

                <span id="editProfileButtonIcon">
                    ✏️
                </span>

                <span id="editProfileButtonText">
                    <?= $editFormOpen ? 'Close' : 'Edit Profile' ?>
                </span>

            </button>

        </div>


        <!-- Edit Profile Form -->

        <div
            class="admin-profile-edit-body"
            id="editProfileBody"
        >

            <form
                method="POST"
                action=""
                class="admin-profile-form"
                autocomplete="on"
            >

                <?= csrfField() ?>


                <!-- Full Name -->

                <div class="admin-profile-form-group">

                    <label
                        for="full_name"
                        class="admin-profile-form-label"
                    >
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        class="admin-profile-form-input"
                        value="<?= adminProfileEscape($fullName) ?>"
                        minlength="2"
                        maxlength="100"
                        autocomplete="name"
                        required
                    >

                    <span class="admin-profile-form-help">
                        Enter the name that should appear throughout SportSync.
                    </span>

                </div>


                <!-- Email -->

                <div class="admin-profile-form-group">

                    <label
                        for="email"
                        class="admin-profile-form-label"
                    >
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="admin-profile-form-input"
                        value="<?= adminProfileEscape($email) ?>"
                        maxlength="150"
                        autocomplete="email"
                        required
                    >

                    <span class="admin-profile-form-help">
                        This email must be unique in the SportSync system.
                    </span>

                </div>


                <!-- Buttons -->

                <div class="admin-profile-form-actions">

                    <button
                        type="button"
                        class="admin-profile-cancel-button"
                        id="cancelEditProfileButton"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="admin-profile-save-button"
                    >
                        Save Changes
                    </button>

                </div>

            </form>

        </div>

    </section>


    <!-- =====================================================
         ACCOUNT INFORMATION
         ===================================================== -->

    <section class="admin-profile-info">

        <h2 class="admin-profile-section-title">
            Account Information
        </h2>

        <p class="admin-profile-section-subtitle">
            These details identify your SportSync administrator account.
        </p>


        <div class="admin-profile-details">

            <div class="admin-profile-detail">

                <span class="admin-profile-detail-label">
                    User ID
                </span>

                <span class="admin-profile-detail-value">
                    #<?= adminProfileEscape($displayUserId) ?>
                </span>

            </div>


            <div class="admin-profile-detail">

                <span class="admin-profile-detail-label">
                    Full Name
                </span>

                <span class="admin-profile-detail-value">
                    <?= adminProfileEscape($fullName) ?>
                </span>

            </div>


            <div class="admin-profile-detail">

                <span class="admin-profile-detail-label">
                    Email Address
                </span>

                <span class="admin-profile-detail-value">
                    <?= adminProfileEscape($email) ?>
                </span>

            </div>


            <div class="admin-profile-detail">

                <span class="admin-profile-detail-label">
                    Account Role
                </span>

                <span class="admin-profile-detail-value">
                    <?= adminProfileEscape($roleLabel) ?>
                </span>

            </div>


            <div class="admin-profile-detail">

                <span class="admin-profile-detail-label">
                    Login Status
                </span>

                <span class="admin-profile-detail-value">
                    Active
                </span>

            </div>


            <div class="admin-profile-detail">

                <span class="admin-profile-detail-label">
                    Last Login
                </span>

                <span class="admin-profile-detail-value">

                    <?php if ($lastLogin): ?>

                        <?php

                        $lastLoginTimestamp = strtotime(
                            (string) $lastLogin
                        );

                        if ($lastLoginTimestamp !== false):

                        ?>

                            <?= adminProfileEscape(
                                date(
                                    'd M Y, h:i A',
                                    $lastLoginTimestamp
                                )
                            ) ?>

                        <?php else: ?>

                            <?= adminProfileEscape(
                                (string) $lastLogin
                            ) ?>

                        <?php endif; ?>

                    <?php else: ?>

                        Current session

                    <?php endif; ?>

                </span>

            </div>

        </div>

    </section>


    <!-- =====================================================
         SECURITY
         ===================================================== -->

    <section class="admin-profile-security">

        <h2 class="admin-profile-section-title">
            Account & Security
        </h2>

        <p class="admin-profile-section-subtitle">
            Basic security information for your administrator account.
        </p>


        <div class="admin-profile-security-row">

            <div>

                <p class="admin-profile-security-label">
                    Password
                </p>

                <p class="admin-profile-security-description">
                    Your password is stored using the application's
                    secure password-hashing system.
                </p>

            </div>

            <span class="admin-profile-security-value">
                Protected
            </span>

        </div>


        <div class="admin-profile-security-row">

            <div>

                <p class="admin-profile-security-label">
                    Session
                </p>

                <p class="admin-profile-security-description">
                    Your administrator session is protected by the
                    SportSync authentication system.
                </p>

            </div>

            <span class="admin-profile-security-value">
                Active
            </span>

        </div>


        <div class="admin-profile-security-row">

            <div>

                <p class="admin-profile-security-label">
                    Administrator Access
                </p>

                <p class="admin-profile-security-description">
                    This account has access to the administrator area
                    according to its assigned role.
                </p>

            </div>

            <span class="admin-profile-security-value">
                <?= adminProfileEscape($roleLabel) ?>
            </span>

        </div>

    </section>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const editSection =
        document.getElementById('editProfileSection');

    const editButton =
        document.getElementById('editProfileButton');

    const editButtonText =
        document.getElementById('editProfileButtonText');

    const editButtonIcon =
        document.getElementById('editProfileButtonIcon');

    const cancelButton =
        document.getElementById('cancelEditProfileButton');

    const nameInput =
        document.getElementById('full_name');


    if (!editSection || !editButton) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Open Edit Profile
    |--------------------------------------------------------------------------
    */

    function openEditProfile() {

        editSection.classList.add('is-open');

        editButton.setAttribute(
            'aria-expanded',
            'true'
        );

        editButtonText.textContent = 'Close';

        editButtonIcon.textContent = '✕';

        /*
        | Focus the first input after opening.
        */

        window.setTimeout(function () {

            if (nameInput) {
                nameInput.focus();
            }

        }, 100);

    }


    /*
    |--------------------------------------------------------------------------
    | Close Edit Profile
    |--------------------------------------------------------------------------
    */

    function closeEditProfile() {

        editSection.classList.remove('is-open');

        editButton.setAttribute(
            'aria-expanded',
            'false'
        );

        editButtonText.textContent = 'Edit Profile';

        editButtonIcon.textContent = '✏️';

    }


    /*
    |--------------------------------------------------------------------------
    | Toggle
    |--------------------------------------------------------------------------
    */

    editButton.addEventListener(
        'click',
        function () {

            if (editSection.classList.contains('is-open')) {

                closeEditProfile();

            } else {

                openEditProfile();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Cancel
    |--------------------------------------------------------------------------
    */

    if (cancelButton) {

        cancelButton.addEventListener(
            'click',
            function () {

                closeEditProfile();

            }
        );

    }

});

</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>