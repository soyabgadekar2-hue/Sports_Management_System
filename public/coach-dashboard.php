<?php
<<<<<<< HEAD
=======
declare(strict_types=1);
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72

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

<<<<<<< HEAD
$pageTitle = 'Coach Dashboard';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .coach-dashboard {
        max-width: 1200px;
        margin: 0 auto;
        padding: 10px 0 35px;
    }

    /* Welcome */
    .coach-welcome {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        overflow: hidden;
        margin-bottom: 24px;
        padding: 30px 34px;
        border-radius: 22px;
        background:
            radial-gradient(circle at 85% 20%, rgba(255,255,255,.18), transparent 30%),
            linear-gradient(135deg, #1d4ed8, #2563eb 55%, #3b82f6);
        color: #fff;
        box-shadow: 0 16px 38px rgba(37, 99, 235, .18);
    }

    .coach-welcome::after {
        content: "";
        position: absolute;
        width: 180px;
        height: 180px;
        right: -70px;
        bottom: -95px;
        border-radius: 50%;
        border: 28px solid rgba(255,255,255,.08);
    }

    .coach-welcome-content {
        position: relative;
        z-index: 1;
    }

    .coach-welcome-label {
        display: inline-flex;
        align-items: center;
        padding: 6px 11px;
        margin-bottom: 12px;
        border: 1px solid rgba(255,255,255,.24);
        border-radius: 999px;
        background: rgba(255,255,255,.12);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .12em;
    }

    .coach-welcome h1 {
        margin: 0 0 8px;
        font-size: clamp(26px, 4vw, 36px);
        line-height: 1.15;
        font-weight: 800;
    }

    .coach-welcome p {
        margin: 0;
        color: rgba(255,255,255,.88);
        font-size: 15px;
    }

    .coach-welcome-icon {
        position: relative;
        z-index: 1;
        display: grid;
        place-items: center;
        width: 82px;
        height: 82px;
        flex: 0 0 82px;
        border: 1px solid rgba(255,255,255,.25);
        border-radius: 24px;
        background: rgba(255,255,255,.13);
        font-size: 38px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.15);
    }

    /* Statistics */
    .coach-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .coach-stat-card {
        display: flex;
        align-items: center;
        gap: 15px;
        min-height: 105px;
        padding: 18px;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }

    .coach-stat-card:hover {
        transform: translateY(-3px);
        border-color: #bfdbfe;
        box-shadow: 0 14px 30px rgba(15, 23, 42, .09);
    }

    .coach-stat-icon {
        display: grid;
        place-items: center;
        width: 52px;
        height: 52px;
        flex: 0 0 52px;
        border-radius: 15px;
        font-size: 23px;
    }

    .teams-icon {
        background: #eff6ff;
    }

    .players-icon {
        background: #ecfdf5;
    }

    .upcoming-icon {
        background: #fff7ed;
    }

    .completed-icon {
        background: #f0fdf4;
        color: #15803d;
        font-weight: 800;
    }

    .coach-stat-card span {
        display: block;
        margin-bottom: 5px;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .coach-stat-card strong {
        display: block;
        color: #0f172a;
        font-size: 28px;
        line-height: 1;
        font-weight: 800;
    }

    /* Coach profile */
    .coach-profile-card {
        overflow: hidden;
        border: 1px solid #e5e7eb;
        border-radius: 22px;
        background: #fff;
        box-shadow: 0 8px 28px rgba(15, 23, 42, .06);
    }

    .coach-profile-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 24px 26px;
        border-bottom: 1px solid #eef2f7;
    }

    .coach-profile-title {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .coach-profile-avatar {
        display: grid;
        place-items: center;
        width: 52px;
        height: 52px;
        flex: 0 0 52px;
        border-radius: 16px;
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #dbeafe;
        font-size: 20px;
        font-weight: 800;
    }

    .coach-profile-title h2 {
        margin: 0 0 4px;
        color: #0f172a;
        font-size: 20px;
    }

    .coach-profile-title p {
        margin: 0;
        color: #64748b;
        font-size: 13px;
    }

    .coach-role-badge {
        display: inline-flex;
        align-items: center;
        padding: 7px 12px;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .08em;
    }

    .coach-profile-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0;
    }

    .coach-profile-item {
        min-height: 92px;
        padding: 19px 24px;
        border-right: 1px solid #eef2f7;
        border-bottom: 1px solid #eef2f7;
    }

    .coach-profile-item:nth-child(3n) {
        border-right: 0;
    }

    .coach-profile-item:nth-last-child(-n+3) {
        border-bottom: 0;
    }

    .coach-profile-item span {
        display: block;
        margin-bottom: 7px;
        color: #94a3b8;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .07em;
    }

    .coach-profile-item strong {
        display: block;
        color: #334155;
        font-size: 14px;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    @media (max-width: 950px) {
        .coach-stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .coach-profile-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .coach-profile-item:nth-child(3n) {
            border-right: 1px solid #eef2f7;
        }

        .coach-profile-item:nth-child(2n) {
            border-right: 0;
        }

        .coach-profile-item:nth-last-child(-n+3) {
            border-bottom: 1px solid #eef2f7;
        }

        .coach-profile-item:nth-last-child(-n+2) {
            border-bottom: 0;
        }
    }

    @media (max-width: 650px) {
        .coach-dashboard {
            padding-top: 0;
        }

        .coach-welcome {
            align-items: flex-start;
            padding: 24px 20px;
            border-radius: 18px;
        }

        .coach-welcome-icon {
            width: 58px;
            height: 58px;
            flex-basis: 58px;
            border-radius: 17px;
            font-size: 27px;
        }

        .coach-stats-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .coach-stat-card {
            min-height: 88px;
        }

        .coach-profile-header {
            align-items: flex-start;
            padding: 20px;
        }

        .coach-profile-title {
            align-items: flex-start;
        }

        .coach-profile-grid {
            grid-template-columns: 1fr;
        }

        .coach-profile-item,
        .coach-profile-item:nth-child(2n),
        .coach-profile-item:nth-child(3n) {
            border-right: 0;
            border-bottom: 1px solid #eef2f7;
        }

        .coach-profile-item:last-child {
            border-bottom: 0;
        }

        .coach-profile-item:nth-last-child(-n+2) {
            border-bottom: 1px solid #eef2f7;
        }

        .coach-profile-item:last-child {
            border-bottom: 0;
        }
    }

    @media (max-width: 430px) {
        .coach-welcome {
            gap: 12px;
        }

        .coach-welcome-icon {
            display: none;
        }

        .coach-profile-header {
            flex-direction: column;
        }

        .coach-role-badge {
            align-self: flex-start;
        }
    }
</style>

=======
require_once __DIR__ . '/../includes/header.php';
?>

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
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

<<<<<<< HEAD
        <div class="coach-welcome-icon" aria-hidden="true">
=======
        <div class="coach-welcome-icon">
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            ⚽
        </div>
    </section>

<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
    <!-- Statistics -->
    <section class="coach-stats-grid">

        <div class="coach-stat-card">
<<<<<<< HEAD
            <div class="coach-stat-icon teams-icon" aria-hidden="true">
=======
            <div class="coach-stat-icon teams-icon">
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                👥
            </div>

            <div>
                <span>My Teams</span>
                <strong><?= $myTeams ?></strong>
            </div>
        </div>

<<<<<<< HEAD
        <div class="coach-stat-card">
            <div class="coach-stat-icon players-icon" aria-hidden="true">
=======

        <div class="coach-stat-card">
            <div class="coach-stat-icon players-icon">
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                🏃
            </div>

            <div>
                <span>My Players</span>
                <strong><?= $myPlayers ?></strong>
            </div>
        </div>

<<<<<<< HEAD
        <div class="coach-stat-card">
            <div class="coach-stat-icon upcoming-icon" aria-hidden="true">
=======

        <div class="coach-stat-card">
            <div class="coach-stat-icon upcoming-icon">
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                ⚽
            </div>

            <div>
                <span>Upcoming Matches</span>
                <strong><?= $upcomingMatches ?></strong>
            </div>
        </div>

<<<<<<< HEAD
        <div class="coach-stat-card">
            <div class="coach-stat-icon completed-icon" aria-hidden="true">
=======

        <div class="coach-stat-card">
            <div class="coach-stat-icon completed-icon">
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
                ✓
            </div>

            <div>
                <span>Completed Matches</span>
                <strong><?= $completedMatches ?></strong>
            </div>
        </div>

    </section>

<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
    <!-- Coach Information -->
    <section class="coach-profile-card">

        <div class="coach-profile-header">

            <div class="coach-profile-title">

<<<<<<< HEAD
                <div class="coach-profile-avatar" aria-hidden="true">
                    <?= htmlspecialchars(strtoupper(substr($coach['full_name'], 0, 1))) ?>
=======
                <div class="coach-profile-avatar">
                    <?= strtoupper(substr($coach['full_name'], 0, 1)) ?>
>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
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

<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
        <div class="coach-profile-grid">

            <div class="coach-profile-item">
                <span>Name</span>
                <strong>
                    <?= htmlspecialchars($coach['full_name']) ?>
                </strong>
            </div>

<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            <div class="coach-profile-item">
                <span>Employee ID</span>
                <strong>
                    <?= htmlspecialchars($coach['employee_id']) ?>
                </strong>
            </div>

<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            <div class="coach-profile-item">
                <span>Email</span>
                <strong>
                    <?= htmlspecialchars($coach['email']) ?>
                </strong>
            </div>

<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            <div class="coach-profile-item">
                <span>Phone</span>
                <strong>
                    <?= htmlspecialchars($coach['phone'] ?? 'Not provided') ?>
                </strong>
            </div>

<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
            <div class="coach-profile-item">
                <span>Designation</span>
                <strong>
                    <?= htmlspecialchars($coach['designation']) ?>
                </strong>
            </div>

<<<<<<< HEAD
=======

>>>>>>> ccc7118f48dd83317d5b46fb434755dfa5a39d72
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