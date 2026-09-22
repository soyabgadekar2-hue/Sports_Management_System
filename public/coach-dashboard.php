<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';

requireRole('COACH');

$user = currentUser();
$userId = (int) $user['id'];

$db = db();

/*
|--------------------------------------------------------------------------
| Coach Information
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("
    SELECT
        u.full_name,
        u.email,
        u.phone,
        cp.coach_id,
        cp.employee_id,
        cp.designation,
        cp.specialization
    FROM users u
    INNER JOIN coach_profiles cp
        ON cp.user_id = u.user_id
    WHERE u.user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);
$coach = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coach) {
    http_response_code(404);
    exit('Coach profile not found.');
}

$coachId = (int) $coach['coach_id'];

/*
|--------------------------------------------------------------------------
| My Teams
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM teams
    WHERE coach_id = ?
      AND team_status = 'ACTIVE'
");

$stmt->execute([$coachId]);
$myTeams = (int) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| My Players
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT tp.player_id)
    FROM team_players tp
    INNER JOIN teams t
        ON t.team_id = tp.team_id
    INNER JOIN player_profiles pp
        ON pp.player_id = tp.player_id
    INNER JOIN users u
        ON u.user_id = pp.user_id
    WHERE t.coach_id = ?
      AND t.team_status = 'ACTIVE'
      AND tp.membership_status = 'ACTIVE'
      AND tp.left_at IS NULL
      AND pp.player_status = 'ACTIVE'
      AND u.account_status = 'APPROVED'
");

$stmt->execute([$coachId]);
$myPlayers = (int) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Upcoming Matches
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM matches m
    INNER JOIN teams ta
        ON ta.team_id = m.team_a_id
    INNER JOIN teams tb
        ON tb.team_id = m.team_b_id
    WHERE (ta.coach_id = ? OR tb.coach_id = ?)
      AND m.match_status = 'SCHEDULED'
      AND m.scheduled_start >= NOW()
");

$stmt->execute([$coachId, $coachId]);
$upcomingMatches = (int) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Completed Matches
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM matches m
    INNER JOIN teams ta
        ON ta.team_id = m.team_a_id
    INNER JOIN teams tb
        ON tb.team_id = m.team_b_id
    WHERE (ta.coach_id = ? OR tb.coach_id = ?)
      AND m.match_status = 'COMPLETED'
");

$stmt->execute([$coachId, $coachId]);
$completedMatches = (int) $stmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="coach-dashboard">

    <!-- Welcome -->
    <section class="coach-welcome">
        <div class="coach-welcome-content">
            <span class="coach-welcome-label">COACH PORTAL</span>

            <h1>
                Welcome, <?= htmlspecialchars($coach['full_name']) ?> 👋
            </h1>

            <p>
                Manage your teams, players and matches from one place.
            </p>
        </div>

        <div class="coach-welcome-icon">
            ⚽
        </div>
    </section>


    <!-- Statistics -->
    <section class="coach-stats-grid">

        <div class="coach-stat-card">
            <div class="coach-stat-icon teams-icon">
                👥
            </div>

            <div>
                <span>My Teams</span>
                <strong><?= $myTeams ?></strong>
            </div>
        </div>


        <div class="coach-stat-card">
            <div class="coach-stat-icon players-icon">
                🏃
            </div>

            <div>
                <span>My Players</span>
                <strong><?= $myPlayers ?></strong>
            </div>
        </div>


        <div class="coach-stat-card">
            <div class="coach-stat-icon upcoming-icon">
                ⚽
            </div>

            <div>
                <span>Upcoming Matches</span>
                <strong><?= $upcomingMatches ?></strong>
            </div>
        </div>


        <div class="coach-stat-card">
            <div class="coach-stat-icon completed-icon">
                ✓
            </div>

            <div>
                <span>Completed Matches</span>
                <strong><?= $completedMatches ?></strong>
            </div>
        </div>

    </section>


    <!-- Coach Information -->
    <section class="coach-profile-card">

        <div class="coach-profile-header">

            <div class="coach-profile-title">

                <div class="coach-profile-avatar">
                    <?= strtoupper(substr($coach['full_name'], 0, 1)) ?>
                </div>

                <div>
                    <h2>Coach Information</h2>
                    <p>Your coaching account details</p>
                </div>

            </div>

            <span class="coach-role-badge">
                COACH
            </span>

        </div>


        <div class="coach-profile-grid">

            <div class="coach-profile-item">
                <span>Name</span>
                <strong>
                    <?= htmlspecialchars($coach['full_name']) ?>
                </strong>
            </div>


            <div class="coach-profile-item">
                <span>Employee ID</span>
                <strong>
                    <?= htmlspecialchars($coach['employee_id']) ?>
                </strong>
            </div>


            <div class="coach-profile-item">
                <span>Email</span>
                <strong>
                    <?= htmlspecialchars($coach['email']) ?>
                </strong>
            </div>


            <div class="coach-profile-item">
                <span>Phone</span>
                <strong>
                    <?= htmlspecialchars($coach['phone'] ?? 'Not provided') ?>
                </strong>
            </div>


            <div class="coach-profile-item">
                <span>Designation</span>
                <strong>
                    <?= htmlspecialchars($coach['designation']) ?>
                </strong>
            </div>


            <div class="coach-profile-item">
                <span>Specialization</span>
                <strong>
                    <?= htmlspecialchars($coach['specialization']) ?>
                </strong>
            </div>

        </div>

    </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>