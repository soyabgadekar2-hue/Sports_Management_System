<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';

requireRole('PLAYER');

$user = currentUser();
$db = db();

$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Get Player Profile
|--------------------------------------------------------------------------
*/
$profileStmt = $db->prepare("
    SELECT
        p.player_id,
        p.student_id,
        p.department,
        p.course,
        p.academic_year,
        p.semester,
        p.gender,
        p.date_of_birth,
        p.player_status,

        u.full_name,
        u.email,
        u.phone,
        u.account_status

    FROM player_profiles p

    INNER JOIN users u
        ON u.user_id = p.user_id

    WHERE p.user_id = :user_id

    LIMIT 1
");

$profileStmt->execute([
    ':user_id' => $userId
]);

$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    exit('Player profile not found.');
}

/*
|--------------------------------------------------------------------------
| Get Player Sports
|--------------------------------------------------------------------------
*/
$sportsStmt = $db->prepare("
    SELECT
        s.sport_name

    FROM player_sports ps

    INNER JOIN sports s
        ON s.sport_id = ps.sport_id

    WHERE ps.player_id = :player_id

    ORDER BY s.sport_name ASC
");

$sportsStmt->execute([
    ':player_id' => (int) $profile['player_id']
]);

$sports = $sportsStmt->fetchAll(PDO::FETCH_COLUMN);

/*
|--------------------------------------------------------------------------
| Get Active Teams
|--------------------------------------------------------------------------
*/
$teamsStmt = $db->prepare("
    SELECT
        t.team_name,
        s.sport_name

    FROM team_players tp

    INNER JOIN teams t
        ON t.team_id = tp.team_id

    INNER JOIN sports s
        ON s.sport_id = t.sport_id

    WHERE tp.player_id = :player_id
      AND tp.membership_status = 'ACTIVE'
      AND tp.left_at IS NULL

    ORDER BY s.sport_name ASC, t.team_name ASC
");

$teamsStmt->execute([
    ':player_id' => (int) $profile['player_id']
]);

$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/
function profileValue(mixed $value, string $fallback = 'Not provided'): string
{
    if ($value === null || trim((string) $value) === '') {
        return $fallback;
    }

    return (string) $value;
}

function statusClass(string $status): string
{
    return match ($status) {
        'APPROVED',
        'ACTIVE' => 'status-completed',

        'PENDING' => 'status-pending',

        'REJECTED',
        'SUSPENDED' => 'status-rejected',

        default => 'status-active',
    };
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">

    <!-- =========================================================
         PAGE HEADER
         ========================================================= -->

    <section class="welcome-card">

        <div class="welcome-content">

            <div class="welcome-label">
                MY PROFILE
            </div>

            <h1>
                My Profile
            </h1>

            <p>
                View your personal, academic and player information.
            </p>

        </div>

        <div class="welcome-icon">
            👤
        </div>

    </section>


    <!-- =========================================================
         PROFILE HEADER
         ========================================================= -->

    <section class="dashboard-card">

        <div class="profile-header">

            <div class="profile-avatar">
                👤
            </div>

            <div class="profile-header-info">

                <h2>
                    <?= htmlspecialchars(
                        profileValue($profile['full_name'])
                    ) ?>
                </h2>

                <p>
                    Student ID:
                    <strong>
                        <?= htmlspecialchars(
                            profileValue($profile['student_id'])
                        ) ?>
                    </strong>
                </p>

                <div class="profile-status-row">

                    <span class="status-badge <?= htmlspecialchars(
                        statusClass(
                            (string) $profile['account_status']
                        )
                    ) ?>">

                        Account:
                        <?= htmlspecialchars(
                            (string) $profile['account_status']
                        ) ?>

                    </span>


                    <span class="status-badge <?= htmlspecialchars(
                        statusClass(
                            (string) $profile['player_status']
                        )
                    ) ?>">

                        Player:
                        <?= htmlspecialchars(
                            (string) $profile['player_status']
                        ) ?>

                    </span>

                </div>

            </div>

        </div>

    </section>


    <!-- =========================================================
         PERSONAL INFORMATION
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Personal Information
                </h2>

                <p>
                    Your registered personal details.
                </p>

            </div>

            <div class="card-header-icon">
                👤
            </div>

        </div>


        <div class="profile-details">

            <div class="profile-item">

                <span>
                    Full Name
                </span>

                <strong>
                    <?= htmlspecialchars(
                        profileValue($profile['full_name'])
                    ) ?>
                </strong>

            </div>


            <div class="profile-item">

                <span>
                    Student ID
                </span>

                <strong>
                    <?= htmlspecialchars(
                        profileValue($profile['student_id'])
                    ) ?>
                </strong>

            </div>


            <div class="profile-item">

                <span>
                    Email
                </span>

                <strong>
                    <?= htmlspecialchars(
                        profileValue($profile['email'])
                    ) ?>
                </strong>

            </div>


            <div class="profile-item">

                <span>
                    Phone
                </span>

                <strong>
                    <?= htmlspecialchars(
                        profileValue($profile['phone'])
                    ) ?>
                </strong>

            </div>


            <div class="profile-item">

                <span>
                    Gender
                </span>

                <strong>
                    <?= htmlspecialchars(
                        profileValue($profile['gender'])
                    ) ?>
                </strong>

            </div>


            <div class="profile-item">

                <span>
                    Date of Birth
                </span>

                <strong>

                    <?php if (!empty($profile['date_of_birth'])): ?>

                        <?= htmlspecialchars(
                            date(
                                'd M Y',
                                strtotime(
                                    (string) $profile['date_of_birth']
                                )
                            )
                        ) ?>

                    <?php else: ?>

                        Not provided

                    <?php endif; ?>

                </strong>

            </div>

        </div>

    </section>


    <!-- =========================================================
         ACADEMIC INFORMATION
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Academic Information
                </h2>

                <p>
                    Your college and academic details.
                </p>

            </div>

            <div class="card-header-icon">
                🎓
            </div>

        </div>


        <div class="profile-details">

            <div class="profile-item">

                <span>
                    Department
                </span>

                <strong>
                    <?= htmlspecialchars(
                        profileValue($profile['department'])
                    ) ?>
                </strong>

            </div>


            <div class="profile-item">

                <span>
                    Course
                </span>

                <strong>
                    <?= htmlspecialchars(
                        profileValue($profile['course'])
                    ) ?>
                </strong>

            </div>


            <div class="profile-item">

                <span>
                    Academic Year
                </span>

                <strong>
                    <?= htmlspecialchars(
                        profileValue($profile['academic_year'])
                    ) ?>
                </strong>

            </div>


            <div class="profile-item">

                <span>
                    Semester
                </span>

                <strong>
                    <?= htmlspecialchars(
                        profileValue($profile['semester'])
                    ) ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- =========================================================
         SPORTS
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    My Sports
                </h2>

                <p>
                    Sports you are registered for.
                </p>

            </div>

            <div class="card-header-icon">
                🏅
            </div>

        </div>


        <?php if (!empty($sports)): ?>

            <div class="tag-list">

                <?php foreach ($sports as $sport): ?>

                    <span class="info-tag">

                        🏅
                        <?= htmlspecialchars(
                            (string) $sport
                        ) ?>

                    </span>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-dashboard">

                <strong>
                    No sports registered
                </strong>

                <p>
                    You are not currently registered for any sport.
                </p>

            </div>

        <?php endif; ?>

    </section>


    <!-- =========================================================
         TEAMS
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    My Active Teams
                </h2>

                <p>
                    Teams you currently belong to.
                </p>

            </div>

            <div class="card-header-icon">
                👥
            </div>

        </div>


        <?php if (!empty($teams)): ?>

            <div class="profile-details">

                <?php foreach ($teams as $team): ?>

                    <div class="profile-item">

                        <span>
                            <?= htmlspecialchars(
                                (string) $team['sport_name']
                            ) ?>
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                (string) $team['team_name']
                            ) ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-dashboard">

                <strong>
                    No active teams
                </strong>

                <p>
                    You are not currently assigned to any team.
                </p>

            </div>

        <?php endif; ?>

    </section>


    <!-- =========================================================
         ACCOUNT INFORMATION
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Account Information
                </h2>

                <p>
                    Current account and player status.
                </p>

            </div>

            <div class="card-header-icon">
                🔐
            </div>

        </div>


        <div class="profile-details">

            <div class="profile-item">

                <span>
                    Account Status
                </span>

                <strong>

                    <span class="status-badge <?= htmlspecialchars(
                        statusClass(
                            (string) $profile['account_status']
                        )
                    ) ?>">

                        <?= htmlspecialchars(
                            (string) $profile['account_status']
                        ) ?>

                    </span>

                </strong>

            </div>


            <div class="profile-item">

                <span>
                    Player Status
                </span>

                <strong>

                    <span class="status-badge <?= htmlspecialchars(
                        statusClass(
                            (string) $profile['player_status']
                        )
                    ) ?>">

                        <?= htmlspecialchars(
                            (string) $profile['player_status']
                        ) ?>

                    </span>

                </strong>

            </div>

        </div>

    </section>


    <!-- =========================================================
         PROFILE NOTICE
         ========================================================= -->

    <section class="dashboard-card">

        <div class="feature-placeholder">

            <strong>
                Profile Information
            </strong>

            <p>
                Your profile information is managed by the
                Sports Management System administrators.
            </p>

            <p>
                If any information is incorrect, contact the
                Sports Coordinator or administrator.
            </p>

        </div>

    </section>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>