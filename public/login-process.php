<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/password.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

requireValidCsrfToken($_POST['csrf_token'] ?? null);

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$selectedRole = strtoupper(trim($_POST['selected_role'] ?? ''));

$allowedRoles = [
    'ADMIN',
    'SPORTS_COORDINATOR',
    'COACH',
    'PLAYER'
];

/*
|--------------------------------------------------------------------------
| SportSync Response Page Helpers
|--------------------------------------------------------------------------
*/

function renderLoginPageStart(string $title): void
{
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';

    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';

    echo '<meta name="description" content="SportSync - Smart Sports Management System">';

    echo '<title>';
    echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo ' | SportSync</title>';

    echo '<link rel="icon" type="image/svg+xml" href="../assets/images/sportsync-mark.svg">';

    echo '<style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 18px;

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
                    rgba(37, 99, 235, 0.16),
                    transparent 35%
                ),
                radial-gradient(
                    circle at bottom right,
                    rgba(59, 130, 246, 0.10),
                    transparent 35%
                ),
                #f5f8ff;
        }

        .page-wrapper {
            width: 100%;
            max-width: 600px;
        }

        .brand {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-logo {
            width: 58px;
            height: 58px;
            object-fit: contain;
            margin-bottom: 9px;
        }

        .brand-name {
            color: #155eef;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.7px;
        }

        .brand-tagline {
            margin-top: 4px;
            color: #667085;
            font-size: 13px;
        }

        .result-card {
            background: #ffffff;
            border: 1px solid #e4e9f2;
            border-radius: 22px;
            padding: 40px;
            text-align: center;

            box-shadow:
                0 18px 50px rgba(15, 23, 42, 0.08);
        }

        .status-icon {
            width: 72px;
            height: 72px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 22px;

            border-radius: 50%;

            font-size: 34px;
            font-weight: 800;
        }

        .pending-icon {
            background: #fff4d6;
            color: #b7791f;
        }

        .error-icon {
            background: #fff0ef;
            color: #d92d20;
        }

        .warning-icon {
            background: #fff7e6;
            color: #c77a00;
        }

        .info-icon {
            background: #eef4ff;
            color: #155eef;
        }

        .success-icon {
            background: #e8f7ee;
            color: #16803c;
        }

        .result-card h1 {
            color: #101828;
            font-size: 26px;
            margin-bottom: 12px;
        }

        .lead {
            color: #475467;
            font-size: 15px;
            line-height: 1.65;
            margin-bottom: 24px;
        }

        .status-box {
            padding: 18px;
            border-radius: 13px;
            margin-bottom: 22px;
            text-align: left;
        }

        .pending-box {
            background: #fff9e8;
            border: 1px solid #f4d58d;
        }

        .pending-box strong {
            display: block;
            color: #946200;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .pending-box span {
            color: #73520e;
            font-size: 13px;
            line-height: 1.55;
        }

        .error-box {
            background: #fff7f6;
            border: 1px solid #f4c7c3;
            padding: 18px;
            border-radius: 13px;
            margin-bottom: 22px;
            text-align: left;
        }

        .error-box strong {
            display: block;
            color: #b42318;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .error-box span {
            color: #7a271a;
            font-size: 13px;
            line-height: 1.55;
        }

        .info-box {
            background: #f1f6ff;
            border: 1px solid #cbdcff;
            padding: 18px;
            border-radius: 13px;
            margin-bottom: 22px;
            text-align: left;
        }

        .info-box strong {
            display: block;
            color: #155eef;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .info-box span {
            color: #344054;
            font-size: 13px;
            line-height: 1.55;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .button {
            min-width: 170px;
            height: 45px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 0 20px;

            border-radius: 10px;
            text-decoration: none;

            font-size: 14px;
            font-weight: 700;

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        .primary-button {
            background: #155eef;
            color: #ffffff;

            box-shadow:
                0 6px 16px rgba(21, 94, 239, 0.22);
        }

        .primary-button:hover {
            background: #0f4dcc;
            transform: translateY(-1px);
        }

        .secondary-button {
            background: #ffffff;
            color: #344054;
            border: 1px solid #d0d5dd;
        }

        .secondary-button:hover {
            background: #f9fafb;
            transform: translateY(-1px);
        }

        .page-footer {
            text-align: center;
            margin-top: 20px;
            color: #98a2b3;
            font-size: 12px;
        }

        @media (max-width: 600px) {

            body {
                padding: 22px 14px;
            }

            .result-card {
                padding: 30px 22px;
                border-radius: 17px;
            }

            .brand-name {
                font-size: 27px;
            }

            .result-card h1 {
                font-size: 23px;
            }

            .actions {
                flex-direction: column;
            }

            .button {
                width: 100%;
            }
        }

    </style>';

    echo '</head>';
    echo '<body>';

    echo '<div class="page-wrapper">';

    echo '<div class="brand">';

    echo '<img
        src="../assets/images/sportsync-mark.svg"
        alt="SportSync"
        class="brand-logo"
    >';

    echo '<div class="brand-name">SportSync</div>';

    echo '<div class="brand-tagline">
        Smart Sports Management System
    </div>';

    echo '</div>';
}

function renderLoginPageEnd(): void
{
    echo '<div class="page-footer">
        SportSync · Smart Sports Management System
    </div>';

    echo '</div>';
    echo '</body>';
    echo '</html>';
}

/*
|--------------------------------------------------------------------------
| Basic Validation
|--------------------------------------------------------------------------
*/

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    http_response_code(401);

    renderLoginPageStart('Login Failed');

    echo '<div class="result-card">';

    echo '<div class="status-icon error-icon">×</div>';

    echo '<h1>Login Failed</h1>';

    echo '<p class="lead">
        Please check your email address and password and try again.
    </p>';

    echo '<div class="error-box">
        <strong>Invalid Login Details</strong>
        <span>
            The email address or password you entered is incorrect.
        </span>
    </div>';

    echo '<div class="actions">
        <a href="login.php" class="button primary-button">
            Back to Login
        </a>
    </div>';

    echo '</div>';

    renderLoginPageEnd();

    exit;
}

if ($password === '') {

    http_response_code(401);

    renderLoginPageStart('Login Failed');

    echo '<div class="result-card">';

    echo '<div class="status-icon error-icon">×</div>';

    echo '<h1>Login Failed</h1>';

    echo '<p class="lead">
        Please enter your password to continue.
    </p>';

    echo '<div class="error-box">
        <strong>Password Required</strong>
        <span>
            Enter the password associated with your SportSync account.
        </span>
    </div>';

    echo '<div class="actions">
        <a href="login.php" class="button primary-button">
            Back to Login
        </a>
    </div>';

    echo '</div>';

    renderLoginPageEnd();

    exit;
}

if (!in_array($selectedRole, $allowedRoles, true)) {

    http_response_code(400);

    renderLoginPageStart('Account Type Required');

    echo '<div class="result-card">';

    echo '<div class="status-icon info-icon">i</div>';

    echo '<h1>Account Type Required</h1>';

    echo '<p class="lead">
        Please select your account type before logging in.
    </p>';

    echo '<div class="info-box">
        <strong>Select Your Account Type</strong>
        <span>
            Choose Admin, Sports Coordinator, Coach, or Player
            according to your SportSync account.
        </span>
    </div>';

    echo '<div class="actions">
        <a href="login.php" class="button primary-button">
            Back to Login
        </a>
    </div>';

    echo '</div>';

    renderLoginPageEnd();

    exit;
}

/*
|--------------------------------------------------------------------------
| Database Login
|--------------------------------------------------------------------------
*/

try {

    $pdo = db();

    $statement = $pdo->prepare(
        'SELECT
            u.user_id,
            u.full_name,
            u.email,
            u.password_hash,
            u.account_status,
            u.role_id
         FROM users u
         WHERE u.email = :email
         LIMIT 1'
    );

    $statement->execute([
        ':email' => $email
    ]);

    $user = $statement->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Invalid Credentials
    |--------------------------------------------------------------------------
    */

    if (!$user || !verifyPassword($password, $user['password_hash'])) {

        http_response_code(401);

        renderLoginPageStart('Login Failed');

        echo '<div class="result-card">';

        echo '<div class="status-icon error-icon">×</div>';

        echo '<h1>Login Failed</h1>';

        echo '<p class="lead">
            We could not sign you in with the details provided.
        </p>';

        echo '<div class="error-box">
            <strong>Invalid Email or Password</strong>
            <span>
                Please verify your email address and password and try again.
            </span>
        </div>';

        echo '<div class="actions">
            <a href="login.php" class="button primary-button">
                Try Again
            </a>

            <a href="register.php" class="button secondary-button">
                Create Account
            </a>
        </div>';

        echo '</div>';

        renderLoginPageEnd();

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Account Status
    |--------------------------------------------------------------------------
    */

    if ($user['account_status'] !== 'APPROVED') {

        $status = $user['account_status'];

        http_response_code(403);

        renderLoginPageStart('Login Not Allowed');

        echo '<div class="result-card">';

        if ($status === 'PENDING') {

            echo '<div class="status-icon pending-icon">⏳</div>';

            echo '<h1>Approval Required</h1>';

            echo '<p class="lead">
                Your SportSync account has been created successfully,
                but it is not active yet.
            </p>';

            echo '<div class="status-box pending-box">';

            echo '<strong>Account Status: Pending Approval</strong>';

            echo '<span>
                Your registration is waiting for approval from an
                Admin or Sports Coordinator. Once approved, you can
                return here and log in normally.
            </span>';

            echo '</div>';

            echo '<div class="actions">
                <a href="login.php" class="button primary-button">
                    Back to Login
                </a>

                <a href="register.php" class="button secondary-button">
                    Register Another Student
                </a>
            </div>';

        } elseif ($status === 'REJECTED') {

            echo '<div class="status-icon error-icon">×</div>';

            echo '<h1>Registration Rejected</h1>';

            echo '<p class="lead">
                Your SportSync account registration has been rejected.
            </p>';

            echo '<div class="error-box">';

            echo '<strong>Account Status: Rejected</strong>';

            echo '<span>
                Please contact your Admin or Sports Coordinator
                if you believe this was a mistake.
            </span>';

            echo '</div>';

            echo '<div class="actions">
                <a href="login.php" class="button primary-button">
                    Back to Login
                </a>
            </div>';

        } elseif ($status === 'SUSPENDED') {

            echo '<div class="status-icon warning-icon">!</div>';

            echo '<h1>Account Suspended</h1>';

            echo '<p class="lead">
                Your SportSync account is currently suspended.
            </p>';

            echo '<div class="error-box">';

            echo '<strong>Account Status: Suspended</strong>';

            echo '<span>
                You cannot log in while your account is suspended.
                Please contact an Admin or Sports Coordinator.
            </span>';

            echo '</div>';

            echo '<div class="actions">
                <a href="login.php" class="button primary-button">
                    Back to Login
                </a>
            </div>';

        } else {

            echo '<div class="status-icon info-icon">i</div>';

            echo '<h1>Login Not Available</h1>';

            echo '<p class="lead">
                Your account cannot currently be used to access SportSync.
            </p>';

            echo '<div class="info-box">';

            echo '<strong>Account Status</strong>';

            echo '<span>
                Your current account status does not allow login.
                Please contact the system administrator.
            </span>';

            echo '</div>';

            echo '<div class="actions">
                <a href="login.php" class="button primary-button">
                    Back to Login
                </a>
            </div>';
        }

        echo '</div>';

        renderLoginPageEnd();

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Role Validation
    |--------------------------------------------------------------------------
    */

    $roleMap = [
        1 => 'ADMIN',
        2 => 'SPORTS_COORDINATOR',
        3 => 'COACH',
        4 => 'PLAYER'
    ];

    $roleId = (int) $user['role_id'];

    if (!isset($roleMap[$roleId])) {

        http_response_code(403);

        renderLoginPageStart('Invalid Account');

        echo '<div class="result-card">';

        echo '<div class="status-icon error-icon">!</div>';

        echo '<h1>Invalid Account</h1>';

        echo '<p class="lead">
            Your account has an invalid role configuration.
        </p>';

        echo '<div class="error-box">
            <strong>Please contact the administrator</strong>
            <span>
                The role assigned to this account is not recognized
                by SportSync.
            </span>
        </div>';

        echo '<div class="actions">
            <a href="login.php" class="button primary-button">
                Back to Login
            </a>
        </div>';

        echo '</div>';

        renderLoginPageEnd();

        exit;
    }

    $role = $roleMap[$roleId];

    /*
    |--------------------------------------------------------------------------
    | Selected Role Must Match Actual Role
    |--------------------------------------------------------------------------
    */

    if ($selectedRole !== $role) {

        http_response_code(403);

        $roleNames = [
            'ADMIN' => 'Admin',
            'SPORTS_COORDINATOR' => 'Sports Coordinator',
            'COACH' => 'Coach',
            'PLAYER' => 'Player'
        ];

        $actualRoleName = $roleNames[$role] ?? 'your account';

        renderLoginPageStart('Wrong Account Type');

        echo '<div class="result-card">';

        echo '<div class="status-icon warning-icon">!</div>';

        echo '<h1>Wrong Account Type</h1>';

        echo '<p class="lead">
            The account type selected on the login page does not
            match this email account.
        </p>';

        echo '<div class="info-box">';

        echo '<strong>This email belongs to a ' .
            htmlspecialchars(
                $actualRoleName,
                ENT_QUOTES,
                'UTF-8'
            ) .
            ' account.</strong>';

        echo '<span>
            Please select the correct account type and try logging in again.
        </span>';

        echo '</div>';

        echo '<div class="actions">
            <a href="login.php" class="button primary-button">
                Choose Correct Account Type
            </a>
        </div>';

        echo '</div>';

        renderLoginPageEnd();

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Successful Login
    |--------------------------------------------------------------------------
    */

    $updateLogin = $pdo->prepare(
        'UPDATE users
         SET last_login_at = NOW()
         WHERE user_id = :user_id'
    );

    $updateLogin->execute([
        ':user_id' => $user['user_id']
    ]);

    loginUser(
        (int) $user['user_id'],
        $role
    );

    auditLog(
        'LOGIN',
        'users',
        (int) $user['user_id'],
        'User logged into SportSync.'
    );

    header('Location: dashboard.php');
    exit;

} catch (Throwable $exception) {

    error_log(
        'Login failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    renderLoginPageStart('Login Error');

    echo '<div class="result-card">';

    echo '<div class="status-icon error-icon">×</div>';

    echo '<h1>Something Went Wrong</h1>';

    echo '<p class="lead">
        SportSync could not complete the login process.
        Please try again.
    </p>';

    echo '<div class="error-box">';

    echo '<strong>Login could not be completed</strong>';

    echo '<span>
        Please return to the login page and try again.
        If the problem continues, contact the administrator.
    </span>';

    echo '</div>';

    echo '<div class="actions">
        <a href="login.php" class="button primary-button">
            Try Again
        </a>
    </div>';

    echo '<div style="
        margin-top: 18px;
        padding: 13px;
        background: #f8fafc;
        border: 1px solid #e4e7ec;
        border-radius: 10px;
        text-align: left;
        color: #667085;
        font-size: 12px;
        line-height: 1.5;
        word-break: break-word;
    ">';

    echo '<strong style="color:#344054;">
        Development error:
    </strong><br>';

    echo htmlspecialchars(
        $exception->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    );

    echo '</div>';

    echo '</div>';

    renderLoginPageEnd();
}