<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$user = currentUser();

if (($user['role'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    exit('Access denied.');
}

$pdo = db();

$admin = null;

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
        ':user_id' => (int) ($user['user_id'] ?? 0),
    ]);

    $admin = $statement->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log(
        'Admin profile load failed: ' .
        $exception->getMessage()
    );
}

if (!$admin) {
    $admin = [
        'user_id' => $user['user_id'] ?? '',
        'full_name' => $user['full_name'] ?? 'Administrator',
        'email' => $user['email'] ?? '',
        'account_status' => $user['account_status'] ?? 'APPROVED',
        'last_login_at' => null,
        'role_name' => 'ADMIN',
    ];
}

function e(string|int|null $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$fullName = trim(
    (string) ($admin['full_name'] ?? 'Administrator')
);

$email = (string) ($admin['email'] ?? '');

$userId = (int) ($admin['user_id'] ?? 0);

$roleName = (string) ($admin['role_name'] ?? 'ADMIN');

$accountStatus = (string) (
    $admin['account_status'] ?? 'APPROVED'
);

$initial = strtoupper(
    substr(
        preg_replace(
            '/[^A-Za-z]/',
            '',
            $fullName
        ) ?: 'A',
        0,
        1
    )
);

$roleLabel = ucwords(
    strtolower(
        str_replace(
            '_',
            ' ',
            $roleName
        )
    )
);

$statusLabel = ucwords(
    strtolower(
        str_replace(
            '_',
            ' ',
            $accountStatus
        )
    )
);

$lastLogin = $admin['last_login_at'] ?? null;

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


/* =========================================================
   PROFILE HERO
   ========================================================= */

.admin-profile-hero {

    display: grid;

    grid-template-columns:
        minmax(0, 1.5fr)
        minmax(280px, 0.8fr);

    gap: 24px;

    margin-bottom: 24px;

}


/* =========================================================
   PROFILE CARDS
   ========================================================= */

.admin-profile-card,
.admin-profile-security,
.admin-profile-info {

    background: #ffffff;

    border: 1px solid #e4e7ec;

    border-radius: 18px;

    box-shadow:
        0 8px 24px rgba(15, 23, 42, 0.06);

}


/* =========================================================
   MAIN PROFILE CARD
   ========================================================= */

.admin-profile-card {

    padding: 30px;

}


/* =========================================================
   PROFILE IDENTITY
   ========================================================= */

.admin-profile-identity {

    display: flex;

    align-items: center;

    gap: 22px;

}


/* =========================================================
   PROFILE AVATAR
   ========================================================= */

.admin-profile-avatar {

    width: 104px;

    height: 104px;

    flex: 0 0 104px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #1d4ed8
        );

    color: #ffffff;

    font-size: 38px;

    font-weight: 800;

    box-shadow:
        0 10px 24px rgba(
            37,
            99,
            235,
            0.25
        );

}


/* =========================================================
   EYEBROW
   ========================================================= */

.admin-profile-eyebrow {

    display: inline-block;

    margin-bottom: 7px;

    color: #2563eb;

    font-size: 12px;

    font-weight: 800;

    letter-spacing: 0.08em;

    text-transform: uppercase;

}


/* =========================================================
   NAME
   ========================================================= */

.admin-profile-name {

    margin: 0 0 7px;

    color: #101828;

    font-size: 28px;

    line-height: 1.2;

}


/* =========================================================
   EMAIL
   ========================================================= */

.admin-profile-email {

    margin: 0;

    color: #667085;

    font-size: 15px;

    word-break: break-word;

}


/* =========================================================
   ROLE BADGE
   ========================================================= */

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


/* =========================================================
   STATUS CARD
   ========================================================= */

.admin-profile-status-card {

    padding: 26px;

    display: flex;

    flex-direction: column;

    justify-content: center;

}


/* =========================================================
   STATUS TITLE
   ========================================================= */

.admin-profile-status-title {

    margin: 0 0 8px;

    color: #101828;

    font-size: 18px;

}


/* =========================================================
   STATUS TEXT
   ========================================================= */

.admin-profile-status-text {

    margin: 0 0 18px;

    color: #667085;

    font-size: 14px;

    line-height: 1.6;

}


/* =========================================================
   STATUS BADGE
   ========================================================= */

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


/* =========================================================
   STATUS DOT
   ========================================================= */

.admin-profile-status-dot {

    width: 8px;

    height: 8px;

    border-radius: 50%;

    background: #12b76a;

}


/* =========================================================
   SECTION TITLE
   ========================================================= */

.admin-profile-section-title {

    margin: 0 0 5px;

    color: #101828;

    font-size: 20px;

}


/* =========================================================
   SECTION SUBTITLE
   ========================================================= */

.admin-profile-section-subtitle {

    margin: 0 0 22px;

    color: #667085;

    font-size: 14px;

    line-height: 1.6;

}


/* =========================================================
   ACCOUNT INFORMATION
   ========================================================= */

.admin-profile-info {

    padding: 26px;

    margin-bottom: 24px;

}


/* =========================================================
   DETAILS GRID
   ========================================================= */

.admin-profile-details {

    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0, 1fr)
        );

    gap: 16px;

}


/* =========================================================
   DETAIL CARD
   ========================================================= */

.admin-profile-detail {

    padding: 18px;

    border: 1px solid #eaecf0;

    border-radius: 13px;

    background: #f9fafb;

}


/* =========================================================
   DETAIL LABEL
   ========================================================= */

.admin-profile-detail-label {

    display: block;

    margin-bottom: 7px;

    color: #667085;

    font-size: 12px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 0.04em;

}


/* =========================================================
   DETAIL VALUE
   ========================================================= */

.admin-profile-detail-value {

    display: block;

    color: #101828;

    font-size: 15px;

    font-weight: 700;

    word-break: break-word;

}


/* =========================================================
   SECURITY
   ========================================================= */

.admin-profile-security {

    padding: 26px;

    margin-bottom: 24px;

}


/* =========================================================
   SECURITY ROW
   ========================================================= */

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


/* =========================================================
   SECURITY LABEL
   ========================================================= */

.admin-profile-security-label {

    margin: 0 0 4px;

    color: #101828;

    font-size: 15px;

    font-weight: 700;

}


/* =========================================================
   SECURITY DESCRIPTION
   ========================================================= */

.admin-profile-security-description {

    margin: 0;

    color: #667085;

    font-size: 13px;

    line-height: 1.5;

}


/* =========================================================
   SECURITY VALUE
   ========================================================= */

.admin-profile-security-value {

    flex: 0 0 auto;

    padding: 7px 11px;

    border-radius: 8px;

    background: #f2f4f7;

    color: #475467;

    font-size: 12px;

    font-weight: 700;

}


/* =========================================================
   PHOTO NOTE
   ========================================================= */

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
   TABLET
   ========================================================= */

@media (max-width: 850px) {

    .admin-profile-hero {

        grid-template-columns: 1fr;

    }

}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 600px) {

    .admin-profile-card,
    .admin-profile-status-card,
    .admin-profile-info,
    .admin-profile-security {

        padding: 20px;

        border-radius: 14px;

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

}

</style>


<div class="admin-profile-page">


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
                View your administrator account information
                and security status.
            </p>

        </div>

    </div>


    <!-- =====================================================
         PROFILE HERO
         ===================================================== -->

    <section class="admin-profile-hero">


        <!-- =================================================
             PROFILE
             ================================================= -->

        <div class="admin-profile-card">

            <div class="admin-profile-identity">


                <!-- Avatar -->

                <div
                    class="admin-profile-avatar"
                    aria-label="Admin profile avatar"
                >

                    <?= e($initial) ?>

                </div>


                <!-- Identity -->

                <div>

                    <span class="admin-profile-eyebrow">
                        Administrator Account
                    </span>

                    <h2 class="admin-profile-name">
                        <?= e($fullName) ?>
                    </h2>

                    <p class="admin-profile-email">
                        <?= e($email) ?>
                    </p>

                    <span class="admin-profile-role">

                        🛡️

                        <?= e($roleLabel) ?>

                    </span>

                </div>

            </div>


            <!-- Profile Photo Information -->

            <div class="admin-profile-photo-note">

                <strong>
                    Profile photo:
                </strong>

                Your current profile uses a secure fallback
                avatar. Profile photo upload will be added
                later as part of the common profile-photo
                feature for all SportSync roles.

            </div>

        </div>


        <!-- =================================================
             ACCOUNT STATUS
             ================================================= -->

        <div
            class="
                admin-profile-card
                admin-profile-status-card
            "
        >

            <h2 class="admin-profile-status-title">
                Account Status
            </h2>

            <p class="admin-profile-status-text">

                This is the current status of your
                administrator account.

            </p>

            <span class="admin-profile-status">

                <span
                    class="admin-profile-status-dot"
                ></span>

                <?= e($statusLabel) ?>

            </span>

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

            These details identify your SportSync
            administrator account.

        </p>


        <div class="admin-profile-details">


            <!-- User ID -->

            <div class="admin-profile-detail">

                <span
                    class="admin-profile-detail-label"
                >
                    User ID
                </span>

                <span
                    class="admin-profile-detail-value"
                >
                    #<?= e($userId) ?>
                </span>

            </div>


            <!-- Full Name -->

            <div class="admin-profile-detail">

                <span
                    class="admin-profile-detail-label"
                >
                    Full Name
                </span>

                <span
                    class="admin-profile-detail-value"
                >
                    <?= e($fullName) ?>
                </span>

            </div>


            <!-- Email -->

            <div class="admin-profile-detail">

                <span
                    class="admin-profile-detail-label"
                >
                    Email Address
                </span>

                <span
                    class="admin-profile-detail-value"
                >
                    <?= e($email) ?>
                </span>

            </div>


            <!-- Role -->

            <div class="admin-profile-detail">

                <span
                    class="admin-profile-detail-label"
                >
                    Account Role
                </span>

                <span
                    class="admin-profile-detail-value"
                >
                    <?= e($roleLabel) ?>
                </span>

            </div>


            <!-- Login Status -->

            <div class="admin-profile-detail">

                <span
                    class="admin-profile-detail-label"
                >
                    Login Status
                </span>

                <span
                    class="admin-profile-detail-value"
                >
                    Active
                </span>

            </div>


            <!-- Last Login -->

            <div class="admin-profile-detail">

                <span
                    class="admin-profile-detail-label"
                >
                    Last Login
                </span>

                <span
                    class="admin-profile-detail-value"
                >

                    <?php if ($lastLogin): ?>

                        <?= e(
                            date(
                                'd M Y, h:i A',
                                strtotime(
                                    (string) $lastLogin
                                )
                            )
                        ) ?>

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

            Basic security information for your
            administrator account.

        </p>


        <!-- Password -->

        <div class="admin-profile-security-row">

            <div>

                <p
                    class="admin-profile-security-label"
                >
                    Password
                </p>

                <p
                    class="
                        admin-profile-security-description
                    "
                >

                    Your password is stored using the
                    application's secure password-hashing
                    system.

                </p>

            </div>

            <span
                class="admin-profile-security-value"
            >
                Protected
            </span>

        </div>


        <!-- Session -->

        <div class="admin-profile-security-row">

            <div>

                <p
                    class="admin-profile-security-label"
                >
                    Session
                </p>

                <p
                    class="
                        admin-profile-security-description
                    "
                >

                    Your administrator session is protected
                    by the SportSync authentication system.

                </p>

            </div>

            <span
                class="admin-profile-security-value"
            >
                Active
            </span>

        </div>


        <!-- Administrator Access -->

        <div class="admin-profile-security-row">

            <div>

                <p
                    class="admin-profile-security-label"
                >
                    Administrator Access
                </p>

                <p
                    class="
                        admin-profile-security-description
                    "
                >

                    This account has access to the
                    administrator area according to its
                    assigned role.

                </p>

            </div>

            <span
                class="admin-profile-security-value"
            >
                <?= e($roleLabel) ?>
            </span>

        </div>

    </section>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>