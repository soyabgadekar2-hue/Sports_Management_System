<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Temporary error display for development
|--------------------------------------------------------------------------
*/
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| Required files
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/password.php';
require_once __DIR__ . '/../includes/audit.php';


/*
|--------------------------------------------------------------------------
| Only POST requests are allowed
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}


/*
|--------------------------------------------------------------------------
| CSRF protection
|--------------------------------------------------------------------------
*/
requireValidCsrfToken($_POST['csrf_token'] ?? null);


/*
|--------------------------------------------------------------------------
| Get form values
|--------------------------------------------------------------------------
*/
$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$studentId = trim($_POST['student_id'] ?? '');
$department = trim($_POST['department'] ?? '');
$course = trim($_POST['course'] ?? '');
$academicYear = trim($_POST['academic_year'] ?? '');
$semester = trim($_POST['semester'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$dateOfBirth = trim($_POST['date_of_birth'] ?? '');


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/
$errors = [];


/* Full name */
if (
    $fullName === '' ||
    mb_strlen($fullName) < 2 ||
    mb_strlen($fullName) > 120
) {
    $errors[] = 'Full name must be between 2 and 120 characters.';
}


/* Email */
if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    mb_strlen($email) > 150
) {
    $errors[] = 'Please enter a valid email address.';
}


/* Phone */
if ($phone !== '' && mb_strlen($phone) > 20) {
    $errors[] = 'Phone number cannot exceed 20 characters.';
}


/* Password */
if (mb_strlen($password) < 8) {
    $errors[] = 'Password must contain at least 8 characters.';
}


/* Student ID */
if (
    $studentId === '' ||
    mb_strlen($studentId) > 40
) {
    $errors[] = 'Student ID is required and cannot exceed 40 characters.';
}


/* Department */
if (
    $department === '' ||
    mb_strlen($department) > 100
) {
    $errors[] = 'Department is required and cannot exceed 100 characters.';
}


/* Course */
if ($course !== '' && mb_strlen($course) > 100) {
    $errors[] = 'Course cannot exceed 100 characters.';
}


/* Academic year */
if (
    $academicYear === '' ||
    mb_strlen($academicYear) > 30
) {
    $errors[] = 'Academic year is required and cannot exceed 30 characters.';
}


/* Semester */
$semesterNumber = null;

if ($semester !== '') {

    $semesterNumber = filter_var(
        $semester,
        FILTER_VALIDATE_INT
    );

    if (
        $semesterNumber === false ||
        $semesterNumber < 1 ||
        $semesterNumber > 12
    ) {
        $errors[] = 'Semester must be between 1 and 12.';
    }
}


/* Gender */
$allowedGenders = [
    'MALE',
    'FEMALE',
    'OTHER',
    'PREFER_NOT_TO_SAY'
];

if (
    $gender !== '' &&
    !in_array($gender, $allowedGenders, true)
) {
    $errors[] = 'Invalid gender selected.';
}


/* Date of birth */
if ($dateOfBirth !== '') {

    $date = DateTime::createFromFormat(
        'Y-m-d',
        $dateOfBirth
    );

    if (
        !$date ||
        $date->format('Y-m-d') !== $dateOfBirth
    ) {
        $errors[] = 'Invalid date of birth.';
    }
}


/*
|--------------------------------------------------------------------------
| Common page styles
|--------------------------------------------------------------------------
*/
function renderPageStart(string $title): void
{
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';

    echo '<meta charset="UTF-8">';

    echo '<meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >';

    echo '<meta
        name="description"
        content="SportSync - Smart Sports Management System"
    >';

    echo '<title>';
    echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo ' | SportSync</title>';

    echo '<link
        rel="icon"
        type="image/svg+xml"
        href="../assets/images/sportsync-mark.svg"
    >';

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
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
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

        .success-icon {
            background: #e8f7ee;
            color: #16803c;
        }

        .error-icon {
            background: #fff0ef;
            color: #d92d20;
        }

        .info-icon {
            background: #eef4ff;
            color: #155eef;
        }

        .result-card h1 {
            color: #101828;
            font-size: 26px;
            margin-bottom: 12px;
        }

        .result-card .lead {
            color: #475467;
            font-size: 15px;
            line-height: 1.65;
            margin-bottom: 24px;
        }

        .status-box {
            padding: 17px 18px;

            border-radius: 12px;

            margin-bottom: 22px;

            text-align: left;
        }

        .pending-box {
            background: #fff8e7;
            border: 1px solid #f5d58a;
        }

        .pending-box strong {
            display: block;
            color: #9a6700;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .pending-box span {
            color: #7a5a12;
            font-size: 13px;
            line-height: 1.5;
        }

        .error-box {
            background: #fff7f6;
            border: 1px solid #f4c7c3;

            text-align: left;
            margin-bottom: 22px;
            padding: 17px 18px;
            border-radius: 12px;
        }

        .error-box strong {
            display: block;
            color: #b42318;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .error-list {
            padding-left: 18px;
            color: #7a271a;
        }

        .error-list li {
            margin-bottom: 6px;
            font-size: 13px;
            line-height: 1.5;
        }

        .development-error {
            margin-top: 16px;
            padding: 14px;

            background: #f8fafc;
            border: 1px solid #e4e7ec;
            border-radius: 10px;

            text-align: left;

            color: #667085;
            font-size: 12px;
            line-height: 1.5;

            word-break: break-word;
        }

        .development-error strong {
            color: #344054;
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

            box-shadow: 0 6px 16px rgba(21, 94, 239, 0.22);
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


function renderPageEnd(): void
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
| Stop if validation failed
|--------------------------------------------------------------------------
*/
if (!empty($errors)) {

    http_response_code(422);

    renderPageStart('Registration Failed');

    echo '<div class="result-card">';

    echo '<div class="status-icon error-icon">
        !
    </div>';

    echo '<h1>
        Registration Could Not Be Completed
    </h1>';

    echo '<p class="lead">
        Please correct the following information and try registering again.
    </p>';

    echo '<div class="error-box">';

    echo '<strong>
        Please check these fields:
    </strong>';

    echo '<ul class="error-list">';

    foreach ($errors as $error) {

        echo '<li>';

        echo htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        );

        echo '</li>';
    }

    echo '</ul>';

    echo '</div>';

    echo '<div class="actions">';

    echo '<a
        href="register.php"
        class="button primary-button"
    >
        Back to Registration
    </a>';

    echo '</div>';

    echo '</div>';

    renderPageEnd();

    exit;
}


/*
|--------------------------------------------------------------------------
| Database transaction
|--------------------------------------------------------------------------
*/
try {

    $pdo = db();

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Check duplicate email
    |--------------------------------------------------------------------------
    */
    $emailCheck = $pdo->prepare(
        'SELECT user_id
         FROM users
         WHERE email = :email
         LIMIT 1'
    );

    $emailCheck->execute([
        ':email' => $email
    ]);

    if ($emailCheck->fetch()) {
        throw new RuntimeException(
            'Email address is already registered.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check duplicate Student ID
    |--------------------------------------------------------------------------
    */
    $studentCheck = $pdo->prepare(
        'SELECT player_id
         FROM player_profiles
         WHERE student_id = :student_id
         LIMIT 1'
    );

    $studentCheck->execute([
        ':student_id' => $studentId
    ]);

    if ($studentCheck->fetch()) {
        throw new RuntimeException(
            'Student ID is already registered.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Hash password
    |--------------------------------------------------------------------------
    */
    $passwordHash = hashPassword($password);


    /*
    |--------------------------------------------------------------------------
    | Create user account
    |
    | role_id 4 = PLAYER
    | account_status = PENDING
    |--------------------------------------------------------------------------
    */
    $userInsert = $pdo->prepare(
        'INSERT INTO users (
            role_id,
            full_name,
            email,
            phone,
            password_hash,
            account_status
        )
        VALUES (
            :role_id,
            :full_name,
            :email,
            :phone,
            :password_hash,
            :account_status
        )'
    );

    $userInsert->execute([
        ':role_id' => 4,
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone !== '' ? $phone : null,
        ':password_hash' => $passwordHash,
        ':account_status' => 'PENDING'
    ]);

    $userId = (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Create player profile
    |--------------------------------------------------------------------------
    */
    $playerInsert = $pdo->prepare(
        'INSERT INTO player_profiles (
            user_id,
            student_id,
            department,
            course,
            academic_year,
            semester,
            gender,
            date_of_birth,
            player_status
        )
        VALUES (
            :user_id,
            :student_id,
            :department,
            :course,
            :academic_year,
            :semester,
            :gender,
            :date_of_birth,
            :player_status
        )'
    );

    $playerInsert->execute([
        ':user_id' => $userId,
        ':student_id' => $studentId,
        ':department' => $department,
        ':course' => $course !== '' ? $course : null,
        ':academic_year' => $academicYear,
        ':semester' => $semesterNumber,
        ':gender' => $gender !== '' ? $gender : null,
        ':date_of_birth' => $dateOfBirth !== '' ? $dateOfBirth : null,
        ':player_status' => 'ACTIVE'
    ]);

    $playerId = (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    */
    auditLog(
        'STUDENT_REGISTRATION',
        'player_profiles',
        $playerId,
        'New student registration submitted.',
        null,
        json_encode([
            'user_id' => $userId,
            'student_id' => $studentId,
            'account_status' => 'PENDING'
        ], JSON_THROW_ON_ERROR)
    );


    /*
    |--------------------------------------------------------------------------
    | Commit transaction
    |--------------------------------------------------------------------------
    */
    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Success response
    |--------------------------------------------------------------------------
    */
    renderPageStart('Registration Successful');

    echo '<div class="result-card">';

    echo '<div class="status-icon success-icon">
        ✓
    </div>';

    echo '<h1>
        Registration Submitted
    </h1>';

    echo '<p class="lead">
        Your SportSync student account has been created successfully.
    </p>';

    echo '<div class="status-box pending-box">';

    echo '<strong>
        Account Status: Pending Approval
    </strong>';

    echo '<span>
        An Admin or Sports Coordinator must approve your account
        before you can log in and access SportSync.
    </span>';

    echo '</div>';

    echo '<p class="lead" style="margin-bottom: 0;">
        Once your account is approved, you can use your registered
        email and password to log in.
    </p>';

    echo '<div class="actions">';

    echo '<a
        href="login.php"
        class="button primary-button"
    >
        Go to Login
    </a>';

    echo '<a
        href="register.php"
        class="button secondary-button"
    >
        Register Another Student
    </a>';

    echo '</div>';

    echo '</div>';

    renderPageEnd();

} catch (Throwable $exception) {

    /*
    |--------------------------------------------------------------------------
    | Rollback if transaction is active
    |--------------------------------------------------------------------------
    */
    if (
        isset($pdo) &&
        $pdo instanceof PDO &&
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }


    /*
    |--------------------------------------------------------------------------
    | Log technical error
    |--------------------------------------------------------------------------
    */
    error_log(
        'Student registration failed: ' .
        $exception->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | Show development error
    |--------------------------------------------------------------------------
    */
    http_response_code(500);

    renderPageStart('Registration Failed');

    echo '<div class="result-card">';

    echo '<div class="status-icon error-icon">
        ×
    </div>';

    echo '<h1>
        Registration Failed
    </h1>';

    echo '<p class="lead">
        Something went wrong while creating your SportSync account.
        Your registration was not completed.
    </p>';

    echo '<div class="error-box">';

    echo '<strong>
        Please try again
    </strong>';

    echo '<div class="error-list">';

    echo '<p>
        The system could not complete the registration.
        No incomplete account was saved.
    </p>';

    echo '</div>';

    echo '</div>';

    echo '<div class="development-error">';

    echo '<strong>
        Development error:
    </strong><br>';

    echo htmlspecialchars(
        $exception->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    );

    echo '</div>';

    echo '<div class="actions">';

    echo '<a
        href="register.php"
        class="button primary-button"
    >
        Try Again
    </a>';

    echo '<a
        href="login.php"
        class="button secondary-button"
    >
        Back to Login
    </a>';

    echo '</div>';

    echo '</div>';

    renderPageEnd();
}