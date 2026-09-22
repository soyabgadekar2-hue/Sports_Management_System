<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole('PLAYER');

$user = currentUser();
$db = db();

$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Get Player
|--------------------------------------------------------------------------
*/
$playerStmt = $db->prepare("
    SELECT
        p.player_id,
        p.student_id,
        u.full_name
    FROM player_profiles p
    INNER JOIN users u
        ON u.user_id = p.user_id
    WHERE p.user_id = :user_id
    LIMIT 1
");

$playerStmt->execute([
    ':user_id' => $userId
]);

$player = $playerStmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    exit('Player profile not found.');
}

$playerId = (int) $player['player_id'];

/*
|--------------------------------------------------------------------------
| Get All Available Sports
|--------------------------------------------------------------------------
*/
$allSportsStmt = $db->query("
    SELECT
        sport_id,
        sport_name
    FROM sports
    ORDER BY sport_name ASC
");

$allSports = $allSportsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Get Player's Registered Sports
|--------------------------------------------------------------------------
*/
$registeredSportsStmt = $db->prepare("
    SELECT
        sport_id
    FROM player_sports
    WHERE player_id = :player_id
");

$registeredSportsStmt->execute([
    ':player_id' => $playerId
]);

$registeredSportIds = $registeredSportsStmt->fetchAll(PDO::FETCH_COLUMN);

$registeredSportIds = array_map(
    'intval',
    $registeredSportIds
);

/*
|--------------------------------------------------------------------------
| Get Player Sports For Display
|--------------------------------------------------------------------------
*/
$sportsStmt = $db->prepare("
    SELECT
        s.sport_id,
        s.sport_name
    FROM player_sports ps
    INNER JOIN sports s
        ON s.sport_id = ps.sport_id
    WHERE ps.player_id = :player_id
    ORDER BY s.sport_name ASC
");

$sportsStmt->execute([
    ':player_id' => $playerId
]);

$sports = $sportsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Common Header
|--------------------------------------------------------------------------
*/
$pageTitle = 'My Sports';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .sports-selection-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-top: 20px;
    }

    .sport-selection-card {
        position: relative;
    }

    .sport-selection-card input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .sport-selection-label {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 20px;
        border: 2px solid #e5e7eb;
        border-radius: 14px;
        background: #ffffff;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .sport-selection-label:hover {
        border-color: #2563eb;
        transform: translateY(-2px);
    }

    .sport-selection-card input:checked + .sport-selection-label {
        border-color: #2563eb;
        background: #eff6ff;
    }

    .sport-selection-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: #eff6ff;
        font-size: 24px;
        flex-shrink: 0;
    }

    .sport-selection-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .sport-selection-info strong {
        color: #111827;
        font-size: 16px;
    }

    .sport-selection-info span {
        color: #6b7280;
        font-size: 13px;
    }

    .sports-form-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 24px;
    }

    .sports-save-button {
        border: none;
        border-radius: 10px;
        padding: 12px 22px;
        background: #2563eb;
        color: #ffffff;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .sports-save-button:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
    }

    .sports-help-text {
        margin-top: 8px;
        color: #6b7280;
        font-size: 14px;
    }

    .registered-sports-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }

    .registered-sport-card {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 18px;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        background: #ffffff;
    }

    .registered-sport-icon {
        width: 46px;
        height: 46px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: #eff6ff;
        font-size: 22px;
        flex-shrink: 0;
    }

    .registered-sport-content {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .registered-sport-content strong {
        color: #111827;
        font-size: 15px;
    }

    .registered-sport-content span {
        color: #6b7280;
        font-size: 12px;
    }

    @media (max-width: 640px) {
        .sports-selection-grid,
        .registered-sports-grid {
            grid-template-columns: 1fr;
        }

        .sports-form-actions {
            justify-content: stretch;
        }

        .sports-save-button {
            width: 100%;
        }
    }
</style>

<div class="dashboard-page">

    <!-- =========================================================
         PAGE HEADER
         ========================================================= -->

    <section class="welcome-card">

        <div class="welcome-content">

            <div class="welcome-label">
                MY SPORTS
            </div>

            <h1>
                My Sports
            </h1>

            <p>
                Select the sports you participate in and manage your
                sports registration.
            </p>

        </div>

        <div class="welcome-icon">
            🏅
        </div>

    </section>


    <!-- =========================================================
         PLAYER SUMMARY
         ========================================================= -->

    <section class="dashboard-stats">

        <div class="stat-card">

            <div class="stat-icon">
                👤
            </div>

            <div class="stat-content">

                <span>
                    Player
                </span>

                <strong>
                    <?= htmlspecialchars(
                        (string) $player['full_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                🪪
            </div>

            <div class="stat-content">

                <span>
                    Student ID
                </span>

                <strong>
                    <?= htmlspecialchars(
                        (string) $player['student_id'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                🏅
            </div>

            <div class="stat-content">

                <span>
                    Registered Sports
                </span>

                <strong>
                    <?= count($sports) ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- =========================================================
         SELECT SPORTS
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Select Your Sports
                </h2>

                <p>
                    Choose all sports in which you want to participate.
                </p>

            </div>

            <div class="card-header-icon">
                🏅
            </div>

        </div>


        <?php if (!empty($allSports)): ?>

            <form
                method="POST"
                action="player-sports-process.php"
            >

                <?= csrfField() ?>

                <div class="sports-selection-grid">

                    <?php foreach ($allSports as $sport): ?>

                        <?php
                        $sportId = (int) $sport['sport_id'];

                        $isRegistered = in_array(
                            $sportId,
                            $registeredSportIds,
                            true
                        );
                        ?>

                        <div class="sport-selection-card">

                            <input
                                type="checkbox"
                                id="sport_<?= $sportId ?>"
                                name="sport_ids[]"
                                value="<?= $sportId ?>"
                                <?= $isRegistered ? 'checked' : '' ?>
                            >

                            <label
                                for="sport_<?= $sportId ?>"
                                class="sport-selection-label"
                            >

                                <div class="sport-selection-icon">
                                    🏅
                                </div>

                                <div class="sport-selection-info">

                                    <strong>
                                        <?= htmlspecialchars(
                                            (string) $sport['sport_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= $isRegistered
                                            ? 'Currently registered'
                                            : 'Not registered' ?>
                                    </span>

                                </div>

                            </label>

                        </div>

                    <?php endforeach; ?>

                </div>


                <p class="sports-help-text">
                    You can select more than one sport.
                </p>


                <div class="sports-form-actions">

                    <button
                        type="submit"
                        class="sports-save-button"
                    >
                        Save Sports
                    </button>

                </div>

            </form>

        <?php else: ?>

            <div class="empty-dashboard">

                <strong>
                    No sports available
                </strong>

                <p>
                    There are currently no sports available for registration.
                </p>

            </div>

        <?php endif; ?>

    </section>


    <!-- =========================================================
         CURRENT REGISTERED SPORTS
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Currently Registered
                </h2>

                <p>
                    Sports currently associated with your player profile.
                </p>

            </div>

            <div class="card-header-icon">
                ✅
            </div>

        </div>


        <?php if (!empty($sports)): ?>

            <div class="registered-sports-grid">

                <?php foreach ($sports as $sport): ?>

                    <div class="registered-sport-card">

                        <div class="registered-sport-icon">
                            🏅
                        </div>

                        <div class="registered-sport-content">

                            <strong>
                                <?= htmlspecialchars(
                                    (string) $sport['sport_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                            <span>
                                Sport ID:
                                <?= htmlspecialchars(
                                    (string) $sport['sport_id'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-dashboard">

                <strong>
                    No sports registered
                </strong>

                <p>
                    Select your sports above and click
                    <strong>Save Sports</strong>.
                </p>

            </div>

        <?php endif; ?>

    </section>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>