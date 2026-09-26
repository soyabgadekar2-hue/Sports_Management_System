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
| Helpers
|--------------------------------------------------------------------------
*/
function profileValue(mixed $value, string $fallback = 'Not provided'): string
{
    if ($value === null || trim((string) $value) === '') {
        return $fallback;
    }

    return (string) $value;
}

function profileStatusClass(string $status): string
{
    return match (strtoupper($status)) {
        'APPROVED',
        'ACTIVE' => 'pp-status-success',

        'PENDING' => 'pp-status-pending',

        'REJECTED',
        'SUSPENDED' => 'pp-status-danger',

        default => 'pp-status-neutral',
    };
}

function profileDate(mixed $date): string
{
    if (empty($date)) {
        return 'Not provided';
    }

    $timestamp = strtotime((string) $date);

    if ($timestamp === false) {
        return 'Not provided';
    }

    return date('d M Y', $timestamp);
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* =========================================================
   PLAYER PROFILE PAGE
   All styles are scoped to this page.
   ========================================================= */

.player-profile-page {
    --pp-primary: #2457d6;
    --pp-primary-soft: #edf3ff;
    --pp-text: #172033;
    --pp-muted: #667085;
    --pp-border: #e5eaf2;
    --pp-surface: #ffffff;
    --pp-page-bg: #f6f8fc;

    display: flex;
    flex-direction: column;
    gap: 22px;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding-bottom: 32px;
    color: var(--pp-text);
}

.player-profile-page *,
.player-profile-page *::before,
.player-profile-page *::after {
    box-sizing: border-box;
}

/* Page heading */

.player-profile-page .pp-page-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 4px 0;
}

.player-profile-page .pp-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 8px;
    color: var(--pp-primary);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
}

.player-profile-page .pp-eyebrow::before {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
    content: "";
}

.player-profile-page .pp-page-heading h1 {
    margin: 0;
    color: var(--pp-text);
    font-size: clamp(25px, 3vw, 32px);
    font-weight: 800;
    line-height: 1.2;
}

.player-profile-page .pp-page-heading p {
    margin: 9px 0 0;
    color: var(--pp-muted);
    font-size: 14px;
    line-height: 1.6;
}

/* Main profile overview */

.player-profile-page .pp-profile-overview {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    overflow: hidden;
    padding: 28px;
    border: 1px solid #dfe8fb;
    border-radius: 20px;
    background:
        radial-gradient(circle at 95% 10%, rgba(36, 87, 214, 0.12), transparent 28%),
        linear-gradient(135deg, #ffffff 0%, #f5f8ff 100%);
    box-shadow: 0 8px 28px rgba(25, 45, 85, 0.04);
}

.player-profile-page .pp-profile-main {
    display: flex;
    align-items: center;
    gap: 20px;
    min-width: 0;
}

.player-profile-page .pp-avatar {
    display: flex;
    flex: 0 0 76px;
    align-items: center;
    justify-content: center;
    width: 76px;
    height: 76px;
    border: 1px solid #d8e4ff;
    border-radius: 22px;
    background: #eaf0ff;
    color: var(--pp-primary);
    font-size: 34px;
}

.player-profile-page .pp-profile-text {
    min-width: 0;
}

.player-profile-page .pp-profile-text h2 {
    margin: 0;
    color: var(--pp-text);
    font-size: clamp(21px, 2.4vw, 27px);
    font-weight: 800;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.player-profile-page .pp-student-id {
    margin: 7px 0 0;
    color: var(--pp-muted);
    font-size: 14px;
}

.player-profile-page .pp-student-id strong {
    color: var(--pp-text);
    font-weight: 700;
}

.player-profile-page .pp-statuses {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 15px;
}

.player-profile-page .pp-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.2px;
    line-height: 1.4;
}

.player-profile-page .pp-status-success {
    border-color: #bdebd2;
    background: #eafaf1;
    color: #147a46;
}

.player-profile-page .pp-status-pending {
    border-color: #f6df9d;
    background: #fff8e5;
    color: #946200;
}

.player-profile-page .pp-status-danger {
    border-color: #f4c6c6;
    background: #fff0f0;
    color: #b42318;
}

.player-profile-page .pp-status-neutral {
    border-color: #dfe4ec;
    background: #f3f5f8;
    color: #475467;
}

.player-profile-page .pp-overview-mark {
    display: flex;
    flex: 0 0 64px;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border-radius: 18px;
    background: rgba(36, 87, 214, 0.08);
    color: var(--pp-primary);
    font-size: 30px;
}

/* Section cards */

.player-profile-page .pp-section {
    min-width: 0;
    padding: 24px;
    border: 1px solid var(--pp-border);
    border-radius: 18px;
    background: var(--pp-surface);
    box-shadow: 0 5px 20px rgba(25, 45, 85, 0.035);
}

.player-profile-page .pp-section-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 22px;
}

.player-profile-page .pp-section-title {
    display: flex;
    align-items: flex-start;
    gap: 13px;
    min-width: 0;
}

.player-profile-page .pp-section-icon {
    display: flex;
    flex: 0 0 42px;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: var(--pp-primary-soft);
    color: var(--pp-primary);
    font-size: 20px;
}

.player-profile-page .pp-section-heading h2 {
    margin: 0;
    color: var(--pp-text);
    font-size: 18px;
    font-weight: 800;
    line-height: 1.4;
}

.player-profile-page .pp-section-heading p {
    margin: 5px 0 0;
    color: var(--pp-muted);
    font-size: 13px;
    line-height: 1.5;
}

/* Detail grids */

.player-profile-page .pp-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.player-profile-page .pp-detail {
    min-width: 0;
    padding: 15px 16px;
    border: 1px solid #edf0f5;
    border-radius: 12px;
    background: #fbfcfe;
}

.player-profile-page .pp-detail-label {
    display: block;
    margin-bottom: 7px;
    color: var(--pp-muted);
    font-size: 12px;
    font-weight: 600;
}

.player-profile-page .pp-detail-value {
    display: block;
    color: var(--pp-text);
    font-size: 14px;
    font-weight: 700;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

/* Sports and teams */

.player-profile-page .pp-activity-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
}

.player-profile-page .pp-activity-card {
    min-width: 0;
    padding: 22px;
    border: 1px solid var(--pp-border);
    border-radius: 18px;
    background: var(--pp-surface);
    box-shadow: 0 5px 20px rgba(25, 45, 85, 0.035);
}

.player-profile-page .pp-activity-card .pp-section-heading {
    margin-bottom: 18px;
}

.player-profile-page .pp-item-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.player-profile-page .pp-list-item {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    padding: 12px 14px;
    border: 1px solid #edf0f5;
    border-radius: 12px;
    background: #fbfcfe;
}

.player-profile-page .pp-list-item-icon {
    display: flex;
    flex: 0 0 34px;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: var(--pp-primary-soft);
    color: var(--pp-primary);
    font-size: 16px;
}

.player-profile-page .pp-list-item-text {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.player-profile-page .pp-list-item-text strong {
    color: var(--pp-text);
    font-size: 13px;
    font-weight: 750;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.player-profile-page .pp-list-item-text span {
    color: var(--pp-muted);
    font-size: 12px;
    line-height: 1.4;
}

/* Empty states */

.player-profile-page .pp-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 145px;
    padding: 22px;
    border: 1px dashed #d9e0eb;
    border-radius: 13px;
    background: #fbfcfe;
    text-align: center;
}

.player-profile-page .pp-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    margin-bottom: 10px;
    border-radius: 13px;
    background: #f0f3f8;
    font-size: 20px;
}

.player-profile-page .pp-empty-state strong {
    color: var(--pp-text);
    font-size: 13px;
    font-weight: 800;
}

.player-profile-page .pp-empty-state p {
    margin: 6px 0 0;
    color: var(--pp-muted);
    font-size: 12px;
    line-height: 1.6;
}

/* Account and help notice */

.player-profile-page .pp-account-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.player-profile-page .pp-account-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    min-width: 0;
    padding: 16px;
    border: 1px solid #edf0f5;
    border-radius: 12px;
    background: #fbfcfe;
}

.player-profile-page .pp-account-item > span:first-child {
    color: var(--pp-muted);
    font-size: 13px;
    font-weight: 600;
}

.player-profile-page .pp-help-notice {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 20px 22px;
    border: 1px solid #dce7ff;
    border-radius: 16px;
    background: #f4f7ff;
}

.player-profile-page .pp-help-icon {
    display: flex;
    flex: 0 0 40px;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: #e4ecff;
    color: var(--pp-primary);
    font-size: 19px;
}

.player-profile-page .pp-help-notice h3 {
    margin: 0;
    color: var(--pp-text);
    font-size: 14px;
    font-weight: 800;
}

.player-profile-page .pp-help-notice p {
    margin: 6px 0 0;
    color: var(--pp-muted);
    font-size: 13px;
    line-height: 1.65;
}

/* Responsive layout */

@media (max-width: 900px) {
    .player-profile-page .pp-activity-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 650px) {
    .player-profile-page {
        gap: 16px;
    }

    .player-profile-page .pp-profile-overview {
        align-items: flex-start;
        padding: 20px;
        border-radius: 16px;
    }

    .player-profile-page .pp-profile-main {
        align-items: flex-start;
        gap: 14px;
    }

    .player-profile-page .pp-avatar {
        flex-basis: 56px;
        width: 56px;
        height: 56px;
        border-radius: 16px;
        font-size: 26px;
    }

    .player-profile-page .pp-overview-mark {
        display: none;
    }

    .player-profile-page .pp-section,
    .player-profile-page .pp-activity-card {
        padding: 18px;
        border-radius: 15px;
    }

    .player-profile-page .pp-section-heading {
        margin-bottom: 17px;
    }

    .player-profile-page .pp-detail-grid,
    .player-profile-page .pp-account-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .player-profile-page .pp-detail {
        padding: 13px 14px;
    }

    .player-profile-page .pp-help-notice {
        padding: 17px;
    }
}

@media (max-width: 420px) {
    .player-profile-page .pp-profile-overview {
        padding: 16px;
    }

    .player-profile-page .pp-profile-main {
        gap: 11px;
    }

    .player-profile-page .pp-avatar {
        flex-basis: 46px;
        width: 46px;
        height: 46px;
        border-radius: 13px;
        font-size: 22px;
    }

    .player-profile-page .pp-statuses {
        gap: 6px;
    }

    .player-profile-page .pp-status {
        padding: 5px 8px;
        font-size: 10px;
    }

    .player-profile-page .pp-section,
    .player-profile-page .pp-activity-card {
        padding: 15px;
    }

    .player-profile-page .pp-section-icon {
        flex-basis: 36px;
        width: 36px;
        height: 36px;
        border-radius: 11px;
        font-size: 17px;
    }

    .player-profile-page .pp-section-heading h2 {
        font-size: 16px;
    }
}
</style>

<div class="dashboard-page player-profile-page">

    <!-- Page heading -->
    <header class="pp-page-heading">
        <div>
            <div class="pp-eyebrow">Player account</div>
            <h1>My Profile</h1>
            <p>View your personal, academic, and sports information.</p>
        </div>
    </header>

    <!-- Profile overview -->
    <section class="pp-profile-overview" aria-label="Profile overview">

        <div class="pp-profile-main">

            <div class="pp-avatar" aria-hidden="true">
                👤
            </div>

            <div class="pp-profile-text">

                <h2>
                    <?= htmlspecialchars(
                        profileValue($profile['full_name']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h2>

                <p class="pp-student-id">
                    Student ID:
                    <strong>
                        <?= htmlspecialchars(
                            profileValue($profile['student_id']),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>
                </p>

                <div class="pp-statuses">

                    <span class="pp-status <?= htmlspecialchars(
                        profileStatusClass((string) $profile['account_status']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">
                        Account:
                        <?= htmlspecialchars(
                            (string) $profile['account_status'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                    <span class="pp-status <?= htmlspecialchars(
                        profileStatusClass((string) $profile['player_status']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">
                        Player:
                        <?= htmlspecialchars(
                            (string) $profile['player_status'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                </div>
            </div>
        </div>

        <div class="pp-overview-mark" aria-hidden="true">
            🏅
        </div>

    </section>

    <!-- Personal information -->
    <section class="pp-section">

        <div class="pp-section-heading">
            <div class="pp-section-title">
                <div class="pp-section-icon" aria-hidden="true">👤</div>
                <div>
                    <h2>Personal Information</h2>
                    <p>Your registered personal details.</p>
                </div>
            </div>
        </div>

        <div class="pp-detail-grid">

            <div class="pp-detail">
                <span class="pp-detail-label">Full Name</span>
                <strong class="pp-detail-value">
                    <?= htmlspecialchars(
                        profileValue($profile['full_name']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </div>

            <div class="pp-detail">
                <span class="pp-detail-label">Student ID</span>
                <strong class="pp-detail-value">
                    <?= htmlspecialchars(
                        profileValue($profile['student_id']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </div>

            <div class="pp-detail">
                <span class="pp-detail-label">Email Address</span>
                <strong class="pp-detail-value">
                    <?= htmlspecialchars(
                        profileValue($profile['email']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </div>

            <div class="pp-detail">
                <span class="pp-detail-label">Phone Number</span>
                <strong class="pp-detail-value">
                    <?= htmlspecialchars(
                        profileValue($profile['phone']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </div>

            <div class="pp-detail">
                <span class="pp-detail-label">Gender</span>
                <strong class="pp-detail-value">
                    <?= htmlspecialchars(
                        profileValue($profile['gender']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </div>

            <div class="pp-detail">
                <span class="pp-detail-label">Date of Birth</span>
                <strong class="pp-detail-value">
                    <?= htmlspecialchars(
                        profileDate($profile['date_of_birth']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </div>

        </div>
    </section>

    <!-- Academic information -->
    <section class="pp-section">

        <div class="pp-section-heading">
            <div class="pp-section-title">
                <div class="pp-section-icon" aria-hidden="true">🎓</div>
                <div>
                    <h2>Academic Information</h2>
                    <p>Your college and academic details.</p>
                </div>
            </div>
        </div>

        <div class="pp-detail-grid">

            <div class="pp-detail">
                <span class="pp-detail-label">Department</span>
                <strong class="pp-detail-value">
                    <?= htmlspecialchars(
                        profileValue($profile['department']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </div>

            <div class="pp-detail">
                <span class="pp-detail-label">Course</span>
                <strong class="pp-detail-value">
                    <?= htmlspecialchars(
                        profileValue($profile['course']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </div>

            <div class="pp-detail">
                <span class="pp-detail-label">Academic Year</span>
                <strong class="pp-detail-value">
                    <?= htmlspecialchars(
                        profileValue($profile['academic_year']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </div>

            <div class="pp-detail">
                <span class="pp-detail-label">Semester</span>
                <strong class="pp-detail-value">
                    <?= htmlspecialchars(
                        profileValue($profile['semester']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </div>

        </div>
    </section>

    <!-- Sports and teams -->
    <div class="pp-activity-grid">

        <!-- Registered sports -->
        <section class="pp-activity-card">

            <div class="pp-section-heading">
                <div class="pp-section-title">
                    <div class="pp-section-icon" aria-hidden="true">🏅</div>
                    <div>
                        <h2>My Sports</h2>
                        <p>Sports you are registered for.</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($sports)): ?>

                <div class="pp-item-list">

                    <?php foreach ($sports as $sport): ?>
                        <div class="pp-list-item">

                            <div class="pp-list-item-icon" aria-hidden="true">
                                🏅
                            </div>

                            <div class="pp-list-item-text">
                                <strong>
                                    <?= htmlspecialchars(
                                        (string) $sport,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                                <span>Registered sport</span>
                            </div>

                        </div>
                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="pp-empty-state">
                    <div class="pp-empty-icon" aria-hidden="true">🏅</div>
                    <strong>No sports registered</strong>
                    <p>You are not currently registered for any sport.</p>
                </div>

            <?php endif; ?>

        </section>

        <!-- Active teams -->
        <section class="pp-activity-card">

            <div class="pp-section-heading">
                <div class="pp-section-title">
                    <div class="pp-section-icon" aria-hidden="true">👥</div>
                    <div>
                        <h2>My Active Teams</h2>
                        <p>Teams you currently belong to.</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($teams)): ?>

                <div class="pp-item-list">

                    <?php foreach ($teams as $team): ?>
                        <div class="pp-list-item">

                            <div class="pp-list-item-icon" aria-hidden="true">
                                👥
                            </div>

                            <div class="pp-list-item-text">
                                <strong>
                                    <?= htmlspecialchars(
                                        (string) $team['team_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                                <span>
                                    <?= htmlspecialchars(
                                        (string) $team['sport_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </div>

                        </div>
                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="pp-empty-state">
                    <div class="pp-empty-icon" aria-hidden="true">👥</div>
                    <strong>No active teams</strong>
                    <p>You are not currently assigned to any team.</p>
                </div>

            <?php endif; ?>

        </section>

    </div>

    <!-- Account information -->
    <section class="pp-section">

        <div class="pp-section-heading">
            <div class="pp-section-title">
                <div class="pp-section-icon" aria-hidden="true">🔐</div>
                <div>
                    <h2>Account Information</h2>
                    <p>Current account and player status.</p>
                </div>
            </div>
        </div>

        <div class="pp-account-grid">

            <div class="pp-account-item">
                <span>Account Status</span>

                <span class="pp-status <?= htmlspecialchars(
                    profileStatusClass((string) $profile['account_status']),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>">
                    <?= htmlspecialchars(
                        (string) $profile['account_status'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>
            </div>

            <div class="pp-account-item">
                <span>Player Status</span>

                <span class="pp-status <?= htmlspecialchars(
                    profileStatusClass((string) $profile['player_status']),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>">
                    <?= htmlspecialchars(
                        (string) $profile['player_status'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>
            </div>

        </div>
    </section>

    <!-- Profile help notice -->
    <aside class="pp-help-notice">

        <div class="pp-help-icon" aria-hidden="true">
            ℹ️
        </div>

        <div>
            <h3>Need to update your profile?</h3>
            <p>
                Your profile information is managed by the Sports Management
                System administrators. If any information is incorrect,
                contact your Sports Coordinator or administrator.
            </p>
        </div>

    </aside>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>