<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole('PLAYER');

$user = currentUser();

if ($user === null) {
    exit('Unauthorized');
}

$db = db();
$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function playerSportsEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Player Profile
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
| Registration
|--------------------------------------------------------------------------
|
| Flow:
|
| Sport
|   -> Sport Event / Format
|   -> Scheduled Competition
|   -> Register
|
| The current database stores the actual registration against
| events.event_id. The selected sport_event format is validated here
| but is not stored because the current events table has no
| sport_event_id column.
|
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    requireValidCsrfToken(
        $_POST['csrf_token'] ?? null
    );

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'register_event') {

        $eventId = (int) ($_POST['event_id'] ?? 0);
        $sportEventId = (int) ($_POST['sport_event_id'] ?? 0);
        $selectedSportId = (int) ($_POST['sport_id'] ?? 0);

        if (
            $eventId <= 0 ||
            $sportEventId <= 0 ||
            $selectedSportId <= 0
        ) {
            header('Location: player-sports.php');
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Get Selected Sport Event / Format
        |--------------------------------------------------------------------------
        */

        $sportEventStmt = $db->prepare("
            SELECT
                se.event_id,
                se.sport_id,
                se.event_name,
                se.event_type,
                se.max_participants,
                s.sport_name
            FROM sport_events se
            INNER JOIN sports s
                ON s.sport_id = se.sport_id
            WHERE se.event_id = :sport_event_id
              AND se.sport_id = :sport_id
              AND se.event_status = 'ACTIVE'
              AND s.sport_status = 'ACTIVE'
            LIMIT 1
        ");

        $sportEventStmt->execute([
            ':sport_event_id' => $sportEventId,
            ':sport_id' => $selectedSportId
        ]);

        $selectedSportEvent = $sportEventStmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$selectedSportEvent) {
            header('Location: player-sports.php');
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Get Scheduled Competition
        |--------------------------------------------------------------------------
        */

        $eventStmt = $db->prepare("
            SELECT
                e.event_id,
                e.sport_id,
                e.event_title,
                e.event_description,
                e.event_start,
                e.event_end,
                e.registration_deadline,
                e.max_participants,
                e.event_status,
                s.sport_name
            FROM events e
            INNER JOIN sports s
                ON s.sport_id = e.sport_id
            WHERE e.event_id = :event_id
              AND e.sport_id = :sport_id
              AND e.event_status = 'OPEN'
              AND s.sport_status = 'ACTIVE'
            LIMIT 1
        ");

        $eventStmt->execute([
            ':event_id' => $eventId,
            ':sport_id' => $selectedSportId
        ]);

        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            header('Location: player-sports.php');
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Registration Deadline
        |--------------------------------------------------------------------------
        */

        if (
            !empty($event['registration_deadline']) &&
            strtotime((string) $event['registration_deadline']) < time()
        ) {
            header('Location: player-sports.php');
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Registration
        |--------------------------------------------------------------------------
        */

        $duplicateStmt = $db->prepare("
            SELECT event_registration_id
            FROM event_registrations
            WHERE event_id = :event_id
              AND player_id = :player_id
            LIMIT 1
        ");

        $duplicateStmt->execute([
            ':event_id' => $eventId,
            ':player_id' => $playerId
        ]);

        if ($duplicateStmt->fetch()) {
            header('Location: player-sports.php');
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Check Event Capacity
        |--------------------------------------------------------------------------
        */

        $countStmt = $db->prepare("
            SELECT COUNT(*)
            FROM event_registrations
            WHERE event_id = :event_id
              AND registration_status IN ('PENDING', 'APPROVED')
        ");

        $countStmt->execute([
            ':event_id' => $eventId
        ]);

        $currentParticipants = (int) $countStmt->fetchColumn();

        $maxParticipants = (int) $event['max_participants'];

        if (
            $maxParticipants > 0 &&
            $currentParticipants >= $maxParticipants
        ) {
            header('Location: player-sports.php');
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Add Sport To Player
        |--------------------------------------------------------------------------
        */

        $sportId = (int) $event['sport_id'];

        $sportCheckStmt = $db->prepare("
            SELECT 1
            FROM player_sports
            WHERE player_id = :player_id
              AND sport_id = :sport_id
            LIMIT 1
        ");

        $sportCheckStmt->execute([
            ':player_id' => $playerId,
            ':sport_id' => $sportId
        ]);

        if (!$sportCheckStmt->fetchColumn()) {

            $sportInsertStmt = $db->prepare("
                INSERT INTO player_sports (
                    player_id,
                    sport_id
                )
                VALUES (
                    :player_id,
                    :sport_id
                )
            ");

            $sportInsertStmt->execute([
                ':player_id' => $playerId,
                ':sport_id' => $sportId
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Register Player For Scheduled Event
        |--------------------------------------------------------------------------
        */

        $insertStmt = $db->prepare("
            INSERT INTO event_registrations (
                event_id,
                player_id,
                registration_status,
                registered_at
            )
            VALUES (
                :event_id,
                :player_id,
                'PENDING',
                NOW()
            )
        ");

        $insertStmt->execute([
            ':event_id' => $eventId,
            ':player_id' => $playerId
        ]);

        header('Location: player-sports.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| All Active Sports
|--------------------------------------------------------------------------
*/

$allSportsStmt = $db->query("
    SELECT
        sport_id,
        sport_name,
        category,
        description
    FROM sports
    WHERE sport_status = 'ACTIVE'
    ORDER BY sport_name ASC
");

$allSports = $allSportsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Player-Friendly Category
|--------------------------------------------------------------------------
*/

function playerSportsCategory(array $sport): string
{
    $sportName = strtolower(
        trim((string) ($sport['sport_name'] ?? ''))
    );

    if ($sportName === 'athletics') {
        return 'Athletics';
    }

    if ($sportName === 'swimming') {
        return 'Water Sports';
    }

    if (
        in_array(
            $sportName,
            [
                'football',
                'cricket',
                'volleyball',
                'kabaddi',
                'kho-kho',
                'handball',
                'hockey',
                'tug of war'
            ],
            true
        )
    ) {
        return 'Outdoor Team Sports';
    }

    if (
        in_array(
            $sportName,
            [
                'basketball',
                'badminton',
                'table tennis',
                'chess',
                'carrom'
            ],
            true
        )
    ) {
        return 'Indoor Sports';
    }

    return (
        (string) ($sport['category'] ?? '') === 'Indoor'
            ? 'Indoor Sports'
            : 'Outdoor Sports'
    );
}

foreach ($allSports as &$sport) {
    $sport['player_category'] = playerSportsCategory($sport);
}

unset($sport);

/*
|--------------------------------------------------------------------------
| Sport Events / Formats
|--------------------------------------------------------------------------
*/

$sportEvents = [];

$sportEventStmt = $db->prepare("
    SELECT
        event_id,
        sport_id,
        event_name,
        event_type,
        description,
        max_participants
    FROM sport_events
    WHERE sport_id = :sport_id
      AND event_status = 'ACTIVE'
    ORDER BY
        CASE
            WHEN event_type = 'SOLO' THEN 1
            WHEN event_type = 'TEAM' THEN 2
            ELSE 3
        END,
        event_name ASC
");

foreach ($allSports as $sport) {

    $sportId = (int) $sport['sport_id'];

    $sportEventStmt->execute([
        ':sport_id' => $sportId
    ]);

    $sportEvents[$sportId] = $sportEventStmt->fetchAll(
        PDO::FETCH_ASSOC
    );
}

/*
|--------------------------------------------------------------------------
| Available Scheduled Events
|--------------------------------------------------------------------------
*/

$availableEventsStmt = $db->query("
    SELECT
        e.event_id,
        e.sport_id,
        e.event_title,
        e.event_description,
        e.event_start,
        e.event_end,
        e.registration_deadline,
        e.max_participants,
        e.event_status,
        s.sport_name
    FROM events e
    INNER JOIN sports s
        ON s.sport_id = e.sport_id
    WHERE e.event_status = 'OPEN'
      AND e.registration_deadline >= NOW()
      AND s.sport_status = 'ACTIVE'
    ORDER BY e.event_start ASC
");

$availableEvents = $availableEventsStmt->fetchAll(
    PDO::FETCH_ASSOC
);

/*
|--------------------------------------------------------------------------
| Registration Status For Available Events
|--------------------------------------------------------------------------
*/

$eventRegistrationStatus = [];

if (!empty($availableEvents)) {

    $eventIds = array_map(
        static fn(array $event): int =>
            (int) $event['event_id'],
        $availableEvents
    );

    $eventPlaceholders = implode(
        ',',
        array_fill(
            0,
            count($eventIds),
            '?'
        )
    );

    $registrationStmt = $db->prepare("
        SELECT
            event_id,
            registration_status
        FROM event_registrations
        WHERE player_id = ?
          AND event_id IN ($eventPlaceholders)
    ");

    $registrationStmt->execute([
        $playerId,
        ...$eventIds
    ]);

    foreach (
        $registrationStmt->fetchAll(PDO::FETCH_ASSOC)
        as $registration
    ) {
        $eventRegistrationStatus[
            (int) $registration['event_id']
        ] = (string) $registration['registration_status'];
    }
}

/*
|--------------------------------------------------------------------------
| Currently Registered Sports
|--------------------------------------------------------------------------
*/

$sportsStmt = $db->prepare("
    SELECT
        s.sport_id,
        s.sport_name,
        s.category,
        s.description
    FROM player_sports ps
    INNER JOIN sports s
        ON s.sport_id = ps.sport_id
    WHERE ps.player_id = :player_id
      AND s.sport_status = 'ACTIVE'
    ORDER BY s.sport_name ASC
");

$sportsStmt->execute([
    ':player_id' => $playerId
]);

$sports = $sportsStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($sports as &$sport) {
    $sport['player_category'] = playerSportsCategory($sport);
}

unset($sport);

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = 'My Sports';

require_once __DIR__ . '/../includes/header.php';

?>

<style>

/* ================================================================
   PAGE
   ================================================================ */

.dashboard-page {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
    box-sizing: border-box;
}

.dashboard-page > section {
    margin: 0 0 24px 0;
}

.dashboard-page > section:last-child {
    margin-bottom: 0;
}

/* ================================================================
   CARD
   ================================================================ */

.dashboard-card {
    width: 100%;
    margin: 0 0 24px 0;
    padding: 28px;
    box-sizing: border-box;
    border-radius: 18px;
}

/* ================================================================
   CARD HEADER
   ================================================================ */

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin: 0 0 24px 0;
    padding: 0;
}

.card-header h2 {
    margin: 0 0 7px 0;
}

.card-header p {
    margin: 0;
}

/* ================================================================
   REGISTRATION BOX
   ================================================================ */

.registration-box {
    width: 100%;
    padding: 22px;
    border: 1px solid #dbe3ef;
    border-radius: 16px;
    background: #ffffff;
    box-sizing: border-box;
}

/* ================================================================
   REGISTRATION GRID
   ================================================================ */

.registration-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 18px;
}

/* ================================================================
   FORM FIELD
   ================================================================ */

.registration-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.registration-field.full-width {
    grid-column: 1 / -1;
}

.registration-field label {
    color: #374151;
    font-size: 13px;
    font-weight: 700;
}

.registration-field select {
    width: 100%;
    min-height: 50px;
    padding: 12px 14px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    background: #ffffff;
    color: #111827;
    font-size: 14px;
    outline: none;
    box-sizing: border-box;
    cursor: pointer;
}

.registration-field select:focus {
    border-color: #2563eb;
    box-shadow:
        0 0 0 3px rgba(
            37,
            99,
            235,
            0.10
        );
}

.registration-field select:disabled {
    background: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
}

/* ================================================================
   PARTICIPATION TYPE
   ================================================================ */

.participation-box {
    display: none;
    align-items: center;
    gap: 12px;
    min-height: 50px;
    padding: 11px 14px;
    border-radius: 10px;
    box-sizing: border-box;
}

.participation-box.show {
    display: flex;
}

.participation-box.solo {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
}

.participation-box.team {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
}

.participation-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 8px;
    background: #ffffff;
    font-size: 18px;
}

.participation-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.participation-content strong {
    color: #111827;
    font-size: 13px;
}

.participation-content span {
    color: #6b7280;
    font-size: 11px;
}

/* ================================================================
   REGISTER ACTION
   ================================================================ */

.registration-action {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.main-register-form {
    margin: 0;
}

.main-register-button {
    width: 100%;
    min-height: 52px;
    border: none;
    border-radius: 11px;
    padding: 13px 18px;
    background: #2563eb;
    color: #ffffff;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition:
        background 0.2s ease,
        transform 0.2s ease,
        opacity 0.2s ease;
}

.main-register-button:hover:not(:disabled) {
    background: #1d4ed8;
    transform: translateY(-1px);
}

.main-register-button:disabled {
    background: #cbd5e1;
    color: #64748b;
    cursor: not-allowed;
    transform: none;
}

/* ================================================================
   SELECTION SUMMARY
   ================================================================ */

.selection-summary {
    display: none;
    margin-top: 18px;
    padding: 14px 16px;
    border-radius: 11px;
    background: #f8fafc;
    color: #475569;
    font-size: 13px;
    line-height: 1.6;
}

.selection-summary.show {
    display: block;
}

.selection-summary strong {
    color: #111827;
}

/* ================================================================
   NOTE
   ================================================================ */

.simple-selector-note {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-top: 18px;
    padding: 13px 15px;
    border-radius: 11px;
    background: #f8fafc;
    color: #64748b;
    font-size: 13px;
    line-height: 1.6;
}

/* ================================================================
   AVAILABLE EVENTS
   ================================================================ */

.available-events-grid {
    display: grid;
    grid-template-columns:
        repeat(
            auto-fit,
            minmax(290px, 1fr)
        );
    gap: 18px;
    margin: 0;
    padding: 0;
}

.available-event-card {
    padding: 20px;
    border: 1px solid #e5e7eb;
    border-radius: 15px;
    background: #ffffff;
    box-sizing: border-box;
}

.available-event-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin: 0 0 14px 0;
    padding: 0;
}

.available-event-title-area {
    min-width: 0;
}

.available-event-title {
    margin: 0;
    color: #111827;
    font-size: 17px;
    line-height: 1.4;
}

.available-event-sport {
    display: inline-block;
    margin-top: 5px;
    color: #2563eb;
    font-size: 12px;
    font-weight: 600;
}

.available-event-description {
    margin: 0 0 16px 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.6;
}

.available-event-meta {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin: 0 0 18px 0;
}

.event-meta-item {
    padding: 11px;
    border-radius: 10px;
    background: #f8fafc;
}

.event-meta-label {
    display: block;
    margin-bottom: 4px;
    color: #6b7280;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}

.event-meta-value {
    display: block;
    color: #111827;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.4;
}

/* ================================================================
   EVENT BUTTON
   ================================================================ */

.event-register-form {
    margin: 0;
    padding: 0;
}

.event-register-button {
    width: 100%;
    border: none;
    border-radius: 10px;
    padding: 12px 16px;
    background: #2563eb;
    color: #ffffff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition:
        background 0.2s ease,
        transform 0.2s ease;
}

.event-register-button:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}

/* ================================================================
   STATUS
   ================================================================ */

.event-status-message {
    padding: 11px 13px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    text-align: center;
}

.event-status-message.pending {
    color: #92400e;
    background: #fef3c7;
}

.event-status-message.approved {
    color: #166534;
    background: #dcfce7;
}

.event-status-message.waitlisted {
    color: #1e40af;
    background: #dbeafe;
}

.event-status-message.cancelled {
    color: #991b1b;
    background: #fee2e2;
}

/* ================================================================
   REGISTERED SPORTS
   ================================================================ */

.registered-sports-grid {
    display: grid;
    grid-template-columns:
        repeat(
            auto-fit,
            minmax(220px, 1fr)
        );
    gap: 16px;
}

.registered-sport-card {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 17px;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: #ffffff;
    box-sizing: border-box;
}

.registered-sport-icon {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 11px;
    background: #eff6ff;
    font-size: 21px;
}

.registered-sport-content {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.registered-sport-content strong {
    color: #111827;
    font-size: 14px;
}

.registered-sport-content span {
    color: #6b7280;
    font-size: 11px;
}

/* ================================================================
   EMPTY
   ================================================================ */

.empty-dashboard {
    margin: 0;
    padding: 28px;
    border-radius: 14px;
    text-align: center;
}

/* ================================================================
   HELP
   ================================================================ */

.sports-help-text {
    margin: 0 0 22px 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.6;
}

/* ================================================================
   MOBILE
   ================================================================ */

@media (max-width: 700px) {

    .dashboard-page {
        padding: 16px;
    }

    .dashboard-card {
        padding: 19px;
        border-radius: 15px;
    }

    .registration-box {
        padding: 15px;
    }

    .registration-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .registration-field.full-width {
        grid-column: auto;
    }

    .available-events-grid,
    .registered-sports-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .available-event-meta {
        grid-template-columns: 1fr;
    }

    .available-event-header {
        flex-direction: column;
    }

    .main-register-button {
        min-height: 50px;
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
                Choose a sport, select an event and register for the
                competition you want to participate in.
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
                    <?= playerSportsEscape(
                        $player['full_name']
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
                    <?= playerSportsEscape(
                        $player['student_id']
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
         REGISTER FOR A SPORT
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Register for a Sport
                </h2>

                <p>
                    Select your sport and competition to register.
                </p>

            </div>

            <div class="card-header-icon">
                🏅
            </div>

        </div>


        <div class="registration-box">

            <div class="registration-grid">

                <!-- =================================================
                     SPORT
                     ================================================= -->

                <div class="registration-field">

                    <label for="registrationSport">

                        1. Choose Sport

                    </label>

                    <select
                        id="registrationSport"
                    >

                        <option value="">
                            Select Sport
                        </option>

                        <?php foreach ($allSports as $sport): ?>

                            <option
                                value="<?= (int) $sport['sport_id'] ?>"
                            >

                                <?= playerSportsEscape(
                                    $sport['sport_name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- =================================================
                     SPORT EVENT / FORMAT
                     ================================================= -->

                <div class="registration-field">

                    <label for="registrationSportEvent">

                        2. Choose Event

                    </label>

                    <select
                        id="registrationSportEvent"
                        disabled
                    >

                        <option value="">
                            First choose a sport
                        </option>

                    </select>

                </div>


                <!-- =================================================
                     PARTICIPATION TYPE
                     ================================================= -->

                <div class="registration-field">

                    <label>
                        3. Participation Type
                    </label>

                    <div
                        id="participationBox"
                        class="participation-box"
                    >

                        <div
                            id="participationIcon"
                            class="participation-icon"
                        >
                            🏅
                        </div>

                        <div class="participation-content">

                            <strong
                                id="participationTitle"
                            >
                                Participation Type
                            </strong>

                            <span
                                id="participationDescription"
                            >
                                Select an event to continue.
                            </span>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     COMPETITION
                     ================================================= -->

                <div class="registration-field">

                    <label for="registrationCompetition">

                        4. Choose Competition

                    </label>

                    <select
                        id="registrationCompetition"
                        disabled
                    >

                        <option value="">
                            First choose an event
                        </option>

                    </select>

                </div>

            </div>


            <!-- =====================================================
                 SELECTION SUMMARY
                 ===================================================== -->

            <div
                id="selectionSummary"
                class="selection-summary"
            >
                <strong>Selected:</strong>
                <span id="summaryText"></span>
            </div>


            <!-- =====================================================
                 REGISTER BUTTON
                 ===================================================== -->

            <div class="registration-action">

                <form
                    method="POST"
                    action="player-sports.php"
                    class="main-register-form"
                    id="mainRegisterForm"
                >

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="register_event"
                    >

                    <input
                        type="hidden"
                        name="sport_id"
                        id="registerSportId"
                        value=""
                    >

                    <input
                        type="hidden"
                        name="sport_event_id"
                        id="registerSportEventId"
                        value=""
                    >

                    <input
                        type="hidden"
                        name="event_id"
                        id="registerEventId"
                        value=""
                    >

                    <button
                        type="submit"
                        class="main-register-button"
                        id="mainRegisterButton"
                        disabled
                    >
                        📝 Register for Sport
                    </button>

                </form>

            </div>


            <!-- =====================================================
                 NOTE
                 ===================================================== -->

            <div class="simple-selector-note">

                <span>
                    💡
                </span>

                <span>
                    Select a sport first. SportSync will then show its
                    available events such as 100m Running, Singles,
                    Doubles, etc. The Individual / Solo or Team / Group
                    type is automatically detected.
                </span>

            </div>

        </div>

    </section>


    <!-- =========================================================
         AVAILABLE EVENTS
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Available Events
                </h2>

                <p>
                    Upcoming competitions created by the administrator
                    or sports coordinator.
                </p>

            </div>

            <div class="card-header-icon">
                📅
            </div>

        </div>


        <div class="sports-help-text">

            Select a sport above to see its competitions. You can also
            use the Register button on an event card below.

        </div>


        <?php if (!empty($availableEvents)): ?>

            <div
                class="available-events-grid"
                id="availableEventsGrid"
            >

                <?php foreach ($availableEvents as $event): ?>

                    <?php

                    $eventId = (int) $event['event_id'];

                    $sportId = (int) $event['sport_id'];

                    $registrationStatus =
                        $eventRegistrationStatus[$eventId]
                        ?? null;

                    $startTime = strtotime(
                        (string) $event['event_start']
                    );

                    $endTime = strtotime(
                        (string) $event['event_end']
                    );

                    $deadlineTime = strtotime(
                        (string) $event['registration_deadline']
                    );

                    ?>

                    <article
                        class="available-event-card"
                        data-event-sport="<?= $sportId ?>"
                    >

                        <div class="available-event-header">

                            <div class="available-event-title-area">

                                <h3 class="available-event-title">

                                    <?= playerSportsEscape(
                                        $event['event_title']
                                    ) ?>

                                </h3>

                                <span class="available-event-sport">

                                    🏅

                                    <?= playerSportsEscape(
                                        $event['sport_name']
                                    ) ?>

                                </span>

                            </div>

                            <span
                                style="
                                    display:inline-flex;
                                    padding:5px 9px;
                                    border-radius:999px;
                                    background:#dcfce7;
                                    color:#166534;
                                    font-size:11px;
                                    font-weight:700;
                                "
                            >
                                OPEN
                            </span>

                        </div>


                        <?php if (
                            !empty($event['event_description'])
                        ): ?>

                            <p class="available-event-description">

                                <?= playerSportsEscape(
                                    $event['event_description']
                                ) ?>

                            </p>

                        <?php endif; ?>


                        <div class="available-event-meta">

                            <div class="event-meta-item">

                                <span class="event-meta-label">
                                    Starts
                                </span>

                                <span class="event-meta-value">

                                    <?= $startTime !== false
                                        ? date(
                                            'd M Y, h:i A',
                                            $startTime
                                        )
                                        : playerSportsEscape(
                                            $event['event_start']
                                        )
                                    ?>

                                </span>

                            </div>


                            <div class="event-meta-item">

                                <span class="event-meta-label">
                                    Ends
                                </span>

                                <span class="event-meta-value">

                                    <?= $endTime !== false
                                        ? date(
                                            'd M Y, h:i A',
                                            $endTime
                                        )
                                        : playerSportsEscape(
                                            $event['event_end']
                                        )
                                    ?>

                                </span>

                            </div>


                            <div class="event-meta-item">

                                <span class="event-meta-label">
                                    Registration Deadline
                                </span>

                                <span class="event-meta-value">

                                    <?= $deadlineTime !== false
                                        ? date(
                                            'd M Y, h:i A',
                                            $deadlineTime
                                        )
                                        : playerSportsEscape(
                                            $event['registration_deadline']
                                        )
                                    ?>

                                </span>

                            </div>


                            <div class="event-meta-item">

                                <span class="event-meta-label">
                                    Maximum Participants
                                </span>

                                <span class="event-meta-value">

                                    <?= (int) $event[
                                        'max_participants'
                                    ] ?>

                                </span>

                            </div>

                        </div>


                        <!-- =================================================
                             EVENT CARD REGISTRATION
                             ================================================= -->

                        <?php if ($registrationStatus === null): ?>

                            <button
                                type="button"
                                class="event-register-button quick-register-button"
                                data-event-id="<?= $eventId ?>"
                                data-sport-id="<?= $sportId ?>"
                            >
                                📝 Select & Register
                            </button>

                        <?php elseif (
                            $registrationStatus === 'PENDING'
                        ): ?>

                            <div
                                class="event-status-message pending"
                            >
                                ⏳ Registration Pending
                            </div>

                        <?php elseif (
                            $registrationStatus === 'APPROVED'
                        ): ?>

                            <div
                                class="event-status-message approved"
                            >
                                ✅ Registration Approved
                            </div>

                        <?php elseif (
                            $registrationStatus === 'WAITLISTED'
                        ): ?>

                            <div
                                class="event-status-message waitlisted"
                            >
                                📋 Waitlisted
                            </div>

                        <?php elseif (
                            $registrationStatus === 'CANCELLED'
                        ): ?>

                            <div
                                class="event-status-message cancelled"
                            >
                                ❌ Registration Cancelled
                            </div>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>


            <div
                id="noFilteredEvents"
                class="empty-dashboard"
                style="display:none;"
            >

                <strong>
                    No upcoming competitions for this sport
                </strong>

                <p>
                    Try another sport.
                </p>

            </div>

        <?php else: ?>

            <div class="empty-dashboard">

                <strong>
                    No upcoming events available
                </strong>

                <p>
                    There are currently no open competitions.
                </p>

            </div>

        <?php endif; ?>

    </section>


    <!-- =========================================================
         CURRENTLY REGISTERED SPORTS
         ========================================================= -->

    <section class="dashboard-card">

        <div class="card-header">

            <div>

                <h2>
                    Currently Registered Sports
                </h2>

                <p>
                    Sports associated with your player profile.
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

                                <?= playerSportsEscape(
                                    $sport['sport_name']
                                ) ?>

                            </strong>

                            <span>

                                <?= playerSportsEscape(
                                    $sport['player_category']
                                ) ?>

                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-dashboard">

                <strong>
                    No sports registered yet
                </strong>

                <p>
                    Choose a sport and competition above to register.
                </p>

            </div>

        <?php endif; ?>

    </section>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
        |--------------------------------------------------------------------------
        | Elements
        |--------------------------------------------------------------------------
        */

        const sportSelect =
            document.getElementById(
                'registrationSport'
            );

        const sportEventSelect =
            document.getElementById(
                'registrationSportEvent'
            );

        const competitionSelect =
            document.getElementById(
                'registrationCompetition'
            );

        const participationBox =
            document.getElementById(
                'participationBox'
            );

        const participationIcon =
            document.getElementById(
                'participationIcon'
            );

        const participationTitle =
            document.getElementById(
                'participationTitle'
            );

        const participationDescription =
            document.getElementById(
                'participationDescription'
            );

        const registerSportId =
            document.getElementById(
                'registerSportId'
            );

        const registerSportEventId =
            document.getElementById(
                'registerSportEventId'
            );

        const registerEventId =
            document.getElementById(
                'registerEventId'
            );

        const registerButton =
            document.getElementById(
                'mainRegisterButton'
            );

        const selectionSummary =
            document.getElementById(
                'selectionSummary'
            );

        const summaryText =
            document.getElementById(
                'summaryText'
            );


        /*
        |--------------------------------------------------------------------------
        | PHP Data
        |--------------------------------------------------------------------------
        */

        const sportsData =
            <?= json_encode(
                $allSports,
                JSON_UNESCAPED_UNICODE |
                JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_AMP |
                JSON_HEX_QUOT
            ) ?>;

        const sportEventsData =
            <?= json_encode(
                $sportEvents,
                JSON_UNESCAPED_UNICODE |
                JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_AMP |
                JSON_HEX_QUOT
            ) ?>;

        const availableEvents =
            <?= json_encode(
                $availableEvents,
                JSON_UNESCAPED_UNICODE |
                JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_AMP |
                JSON_HEX_QUOT
            ) ?>;


        /*
        |--------------------------------------------------------------------------
        | Initial State
        |--------------------------------------------------------------------------
        */

        resetSportEvent();

        resetCompetition();

        updateRegisterButton();


        /*
        |--------------------------------------------------------------------------
        | Sport Changed
        |--------------------------------------------------------------------------
        */

        sportSelect.addEventListener(
            'change',
            function () {

                const sportId =
                    parseInt(
                        this.value,
                        10
                    ) || 0;

                resetSportEvent();

                resetCompetition();

                resetParticipation();

                registerSportId.value =
                    sportId > 0
                        ? String(sportId)
                        : '';

                registerSportEventId.value = '';

                registerEventId.value = '';

                updateRegisterButton();

                if (!sportId) {
                    filterAvailableEvents(0);
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Load Sport Events
                |--------------------------------------------------------------------------
                */

                const formats =
                    sportEventsData[sportId]
                    || [];

                if (!formats.length) {

                    const option =
                        document.createElement(
                            'option'
                        );

                    option.value = '';

                    option.textContent =
                        'No events configured';

                    sportEventSelect.appendChild(
                        option
                    );

                    sportEventSelect.disabled = true;

                } else {

                    const defaultOption =
                        document.createElement(
                            'option'
                        );

                    defaultOption.value = '';

                    defaultOption.textContent =
                        'Select Event';

                    sportEventSelect.appendChild(
                        defaultOption
                    );

                    formats.forEach(
                        function (event) {

                            const option =
                                document.createElement(
                                    'option'
                                );

                            option.value =
                                String(
                                    event.event_id
                                );

                            option.textContent =
                                event.event_name +
                                (
                                    event.event_type === 'TEAM'
                                        ? ' — Team / Group'
                                        : ' — Individual / Solo'
                                );

                            option.dataset.eventType =
                                event.event_type;

                            option.dataset.eventName =
                                event.event_name;

                            sportEventSelect.appendChild(
                                option
                            );
                        }
                    );

                    sportEventSelect.disabled = false;
                }

                /*
                |--------------------------------------------------------------------------
                | Filter Available Competition Cards
                |--------------------------------------------------------------------------
                */

                filterAvailableEvents(
                    sportId
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Sport Event Changed
        |--------------------------------------------------------------------------
        */

        sportEventSelect.addEventListener(
            'change',
            function () {

                const sportEventId =
                    parseInt(
                        this.value,
                        10
                    ) || 0;

                const selectedOption =
                    this.options[
                        this.selectedIndex
                    ];

                registerSportEventId.value =
                    sportEventId > 0
                        ? String(sportEventId)
                        : '';

                registerEventId.value = '';

                resetCompetition();

                resetParticipation();


                if (!sportEventId) {

                    updateRegisterButton();

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Get Event Type
                |--------------------------------------------------------------------------
                */

                const eventType =
                    selectedOption.dataset.eventType
                    || '';

                const eventName =
                    selectedOption.dataset.eventName
                    || '';


                /*
                |--------------------------------------------------------------------------
                | Show Participation Type
                |--------------------------------------------------------------------------
                */

                if (eventType === 'SOLO') {

                    participationBox.className =
                        'participation-box solo show';

                    participationIcon.textContent =
                        '🧍';

                    participationTitle.textContent =
                        'Individual / Solo';

                    participationDescription.textContent =
                        'You will participate individually.';

                } else if (eventType === 'TEAM') {

                    participationBox.className =
                        'participation-box team show';

                    participationIcon.textContent =
                        '👥';

                    participationTitle.textContent =
                        'Team / Group';

                    participationDescription.textContent =
                        'This competition uses a team or group format.';

                } else {

                    participationBox.className =
                        'participation-box';

                }


                /*
                |--------------------------------------------------------------------------
                | Load Competitions For Selected Sport
                |--------------------------------------------------------------------------
                |
                | IMPORTANT:
                |
                | The current events table has sport_id but does not have
                | sport_event_id. Therefore competitions are filtered
                | by SPORT here. The selected sport event/format is still
                | validated by PHP during registration.
                |
                */

                const selectedSportId =
                    parseInt(
                        sportSelect.value,
                        10
                    ) || 0;

                const competitions =
                    availableEvents.filter(
                        function (event) {

                            return (
                                parseInt(
                                    event.sport_id,
                                    10
                                ) === selectedSportId
                            );

                        }
                    );


                if (!competitions.length) {

                    const option =
                        document.createElement(
                            'option'
                        );

                    option.value = '';

                    option.textContent =
                        'No open competitions available';

                    competitionSelect.appendChild(
                        option
                    );

                    competitionSelect.disabled = true;

                } else {

                    const defaultOption =
                        document.createElement(
                            'option'
                        );

                    defaultOption.value = '';

                    defaultOption.textContent =
                        'Select Competition';

                    competitionSelect.appendChild(
                        defaultOption
                    );


                    competitions.forEach(
                        function (event) {

                            const option =
                                document.createElement(
                                    'option'
                                );

                            option.value =
                                String(
                                    event.event_id
                                );

                            option.textContent =
                                event.event_title;

                            competitionSelect.appendChild(
                                option
                            );

                        }
                    );

                    competitionSelect.disabled = false;
                }


                updateSummary(
                    eventName,
                    eventType
                );

                updateRegisterButton();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Competition Changed
        |--------------------------------------------------------------------------
        */

        competitionSelect.addEventListener(
            'change',
            function () {

                const eventId =
                    parseInt(
                        this.value,
                        10
                    ) || 0;

                registerEventId.value =
                    eventId > 0
                        ? String(eventId)
                        : '';

                updateRegisterButton();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Quick Register Buttons
        |--------------------------------------------------------------------------
        */

        const quickButtons =
            document.querySelectorAll(
                '.quick-register-button'
            );

        quickButtons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        const sportId =
                            parseInt(
                                this.dataset.eventSport,
                                10
                            ) || 0;

                        const eventId =
                            parseInt(
                                this.dataset.eventId,
                                10
                            ) || 0;


                        /*
                        |--------------------------------------------------------------------------
                        | Select Sport
                        |--------------------------------------------------------------------------
                        */

                        if (sportId > 0) {

                            sportSelect.value =
                                String(
                                    sportId
                                );

                            sportSelect.dispatchEvent(
                                new Event(
                                    'change'
                                )
                            );

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Scroll To Registration Box
                        |--------------------------------------------------------------------------
                        */

                        const registrationBox =
                            document.querySelector(
                                '.registration-box'
                            );

                        if (registrationBox) {

                            registrationBox.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Select First Available Format
                        |--------------------------------------------------------------------------
                        |
                        | Because the event table currently does not
                        | connect a scheduled event directly to
                        | sport_events, we choose the first format
                        | for the sport.
                        |
                        */

                        setTimeout(
                            function () {

                                if (
                                    sportEventSelect.options.length > 1
                                ) {

                                    sportEventSelect.selectedIndex =
                                        1;

                                    sportEventSelect.dispatchEvent(
                                        new Event(
                                            'change'
                                        )
                                    );

                                }


                                setTimeout(
                                    function () {

                                        competitionSelect.value =
                                            String(
                                                eventId
                                            );

                                        competitionSelect.dispatchEvent(
                                            new Event(
                                                'change'
                                            )
                                        );

                                    },
                                    50
                                );

                            },
                            100
                        );

                    }
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Functions
        |--------------------------------------------------------------------------
        */

        function resetSportEvent() {

            sportEventSelect.innerHTML = '';

            const option =
                document.createElement(
                    'option'
                );

            option.value = '';

            option.textContent =
                'First choose a sport';

            sportEventSelect.appendChild(
                option
            );

            sportEventSelect.disabled = true;

        }


        function resetCompetition() {

            competitionSelect.innerHTML = '';

            const option =
                document.createElement(
                    'option'
                );

            option.value = '';

            option.textContent =
                'First choose an event';

            competitionSelect.appendChild(
                option
            );

            competitionSelect.disabled = true;

        }


        function resetParticipation() {

            participationBox.className =
                'participation-box';

            participationIcon.textContent =
                '🏅';

            participationTitle.textContent =
                'Participation Type';

            participationDescription.textContent =
                'Select an event to continue.';

        }


        function updateRegisterButton() {

            const sportId =
                parseInt(
                    sportSelect.value,
                    10
                ) || 0;

            const sportEventId =
                parseInt(
                    sportEventSelect.value,
                    10
                ) || 0;

            const eventId =
                parseInt(
                    competitionSelect.value,
                    10
                ) || 0;


            const ready =
                sportId > 0 &&
                sportEventId > 0 &&
                eventId > 0;


            registerButton.disabled =
                !ready;

        }


        function updateSummary(
            eventName,
            eventType
        ) {

            if (!eventName) {

                selectionSummary.classList.remove(
                    'show'
                );

                return;

            }


            const sportName =
                sportSelect.options[
                    sportSelect.selectedIndex
                ]?.textContent.trim()
                || '';


            const participation =
                eventType === 'TEAM'
                    ? 'Team / Group'
                    : 'Individual / Solo';


            summaryText.textContent =
                sportName +
                ' → ' +
                eventName +
                ' → ' +
                participation;


            selectionSummary.classList.add(
                'show'
            );

        }


        function filterAvailableEvents(
            sportId
        ) {

            const eventCards =
                document.querySelectorAll(
                    '.available-event-card'
                );

            const emptyMessage =
                document.getElementById(
                    'noFilteredEvents'
                );


            if (!eventCards.length) {
                return;
            }


            let visibleCount = 0;


            eventCards.forEach(
                function (card) {

                    const cardSportId =
                        parseInt(
                            card.dataset.eventSport,
                            10
                        );


                    if (
                        !sportId ||
                        cardSportId === sportId
                    ) {

                        card.style.display =
                            '';

                        visibleCount++;

                    } else {

                        card.style.display =
                            'none';

                    }

                }
            );


            if (emptyMessage) {

                emptyMessage.style.display =
                    sportId &&
                    visibleCount === 0
                        ? ''
                        : 'none';

            }

        }

    }
);

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>