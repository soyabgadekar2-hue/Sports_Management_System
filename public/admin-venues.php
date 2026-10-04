<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireLogin();
requireRole('ADMIN');

$pdo = db();

$errorMessage = '';

function venueEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirectToVenues(): never
{
    header('Location: admin-venues.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (function_exists('verifyCsrfRequest')) {

            verifyCsrfRequest();

        } elseif (function_exists('verifyCsrfToken')) {

            $token = $_POST['csrf_token'] ?? '';

            if (!verifyCsrfToken($token)) {
                throw new RuntimeException(
                    'Invalid security token. Please refresh the page and try again.'
                );
            }
        }

        $action = trim((string) ($_POST['action'] ?? ''));

        /*
        |--------------------------------------------------------------------------
        | ADD VENUE
        |--------------------------------------------------------------------------
        */
        if ($action === 'add_venue') {

            $venueName = trim((string) ($_POST['venue_name'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));
            $capacity = trim((string) ($_POST['capacity'] ?? ''));
            $availabilityStatus = strtoupper(
                trim((string) ($_POST['availability_status'] ?? 'AVAILABLE'))
            );

            if ($venueName === '') {
                throw new RuntimeException('Venue name is required.');
            }

            if (mb_strlen($venueName) > 120) {
                throw new RuntimeException(
                    'Venue name cannot exceed 120 characters.'
                );
            }

            if ($location === '') {
                throw new RuntimeException('Location is required.');
            }

            if (mb_strlen($location) > 255) {
                throw new RuntimeException(
                    'Location cannot exceed 255 characters.'
                );
            }

            if ($capacity !== '') {

                if (!ctype_digit($capacity)) {
                    throw new RuntimeException(
                        'Capacity must be a valid number.'
                    );
                }

                $capacityValue = (int) $capacity;

                if ($capacityValue < 1) {
                    throw new RuntimeException(
                        'Capacity must be at least 1.'
                    );
                }

            } else {

                $capacityValue = null;
            }

            $allowedStatuses = [
                'AVAILABLE',
                'UNAVAILABLE',
                'MAINTENANCE'
            ];

            if (!in_array($availabilityStatus, $allowedStatuses, true)) {
                throw new RuntimeException(
                    'Invalid availability status.'
                );
            }

            $duplicateStmt = $pdo->prepare(
                'SELECT venue_id
                 FROM venues
                 WHERE venue_name = :venue_name
                 LIMIT 1'
            );

            $duplicateStmt->execute([
                'venue_name' => $venueName
            ]);

            if ($duplicateStmt->fetch()) {
                throw new RuntimeException(
                    'A venue with this name already exists.'
                );
            }

            $insertStmt = $pdo->prepare(
                'INSERT INTO venues
                    (
                        venue_name,
                        location,
                        capacity,
                        availability_status
                    )
                 VALUES
                    (
                        :venue_name,
                        :location,
                        :capacity,
                        :availability_status
                    )'
            );

            $insertStmt->execute([
                'venue_name' => $venueName,
                'location' => $location,
                'capacity' => $capacityValue,
                'availability_status' => $availabilityStatus
            ]);

            redirectToVenues();
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE VENUE
        |--------------------------------------------------------------------------
        */
        if ($action === 'update_venue') {

            $venueId = (int) ($_POST['venue_id'] ?? 0);

            $venueName = trim((string) ($_POST['venue_name'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));
            $capacity = trim((string) ($_POST['capacity'] ?? ''));
            $availabilityStatus = strtoupper(
                trim((string) ($_POST['availability_status'] ?? 'AVAILABLE'))
            );

            if ($venueId < 1) {
                throw new RuntimeException('Invalid venue.');
            }

            if ($venueName === '') {
                throw new RuntimeException('Venue name is required.');
            }

            if (mb_strlen($venueName) > 120) {
                throw new RuntimeException(
                    'Venue name cannot exceed 120 characters.'
                );
            }

            if ($location === '') {
                throw new RuntimeException('Location is required.');
            }

            if (mb_strlen($location) > 255) {
                throw new RuntimeException(
                    'Location cannot exceed 255 characters.'
                );
            }

            if ($capacity !== '') {

                if (!ctype_digit($capacity)) {
                    throw new RuntimeException(
                        'Capacity must be a valid number.'
                    );
                }

                $capacityValue = (int) $capacity;

                if ($capacityValue < 1) {
                    throw new RuntimeException(
                        'Capacity must be at least 1.'
                    );
                }

            } else {

                $capacityValue = null;
            }

            $allowedStatuses = [
                'AVAILABLE',
                'UNAVAILABLE',
                'MAINTENANCE'
            ];

            if (!in_array($availabilityStatus, $allowedStatuses, true)) {
                throw new RuntimeException(
                    'Invalid availability status.'
                );
            }

            $duplicateStmt = $pdo->prepare(
                'SELECT venue_id
                 FROM venues
                 WHERE venue_name = :venue_name
                 AND venue_id <> :venue_id
                 LIMIT 1'
            );

            $duplicateStmt->execute([
                'venue_name' => $venueName,
                'venue_id' => $venueId
            ]);

            if ($duplicateStmt->fetch()) {
                throw new RuntimeException(
                    'Another venue already uses this name.'
                );
            }

            $updateStmt = $pdo->prepare(
                'UPDATE venues
                 SET
                    venue_name = :venue_name,
                    location = :location,
                    capacity = :capacity,
                    availability_status = :availability_status
                 WHERE venue_id = :venue_id'
            );

            $updateStmt->execute([
                'venue_name' => $venueName,
                'location' => $location,
                'capacity' => $capacityValue,
                'availability_status' => $availabilityStatus,
                'venue_id' => $venueId
            ]);

            redirectToVenues();
        }

    } catch (Throwable $e) {

        $errorMessage = $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH / FILTER
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['search'] ?? ''));

$statusFilter = strtoupper(
    trim((string) ($_GET['status'] ?? ''))
);

$allowedFilterStatuses = [
    '',
    'AVAILABLE',
    'UNAVAILABLE',
    'MAINTENANCE'
];

if (!in_array($statusFilter, $allowedFilterStatuses, true)) {
    $statusFilter = '';
}

$where = [];
$params = [];

if ($search !== '') {

    $where[] = '(venue_name LIKE :search OR location LIKE :search)';

    $params['search'] = '%' . $search . '%';
}

if ($statusFilter !== '') {

    $where[] = 'availability_status = :status';

    $params['status'] = $statusFilter;
}

$sql = '
    SELECT
        venue_id,
        venue_name,
        location,
        capacity,
        availability_status,
        created_at
    FROM venues
';

if ($where !== []) {

    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY venue_name ASC';

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$venues = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalVenues = (int) $pdo
    ->query('SELECT COUNT(*) FROM venues')
    ->fetchColumn();

$availableVenues = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM venues
         WHERE availability_status = 'AVAILABLE'"
    )
    ->fetchColumn();

$unavailableVenues = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM venues
         WHERE availability_status = 'UNAVAILABLE'"
    )
    ->fetchColumn();

$maintenanceVenues = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM venues
         WHERE availability_status = 'MAINTENANCE'"
    )
    ->fetchColumn();

require_once __DIR__ . '/../includes/header.php';

?>

<main class="admin-venues-page">

    <div class="admin-venues-container">

        <!-- PAGE HEADER -->

        <div class="admin-venues-page-header">

            <div>

                <div class="admin-venues-breadcrumb">
                    Admin / Venues
                </div>

                <h1>Venue Management</h1>

                <p>
                    Manage sports venues and their availability for SportSync tournaments and matches.
                </p>

            </div>

            <button
                type="button"
                class="admin-venues-add-button"
                id="openAddVenueButton"
            >
                <span class="admin-venues-add-icon">+</span>
                <span>Add Venue</span>
            </button>

        </div>

        <!-- ERROR -->

        <?php if ($errorMessage !== ''): ?>

            <div class="admin-venues-alert admin-venues-alert-error">

                <span class="admin-venues-alert-icon">
                    !
                </span>

                <div>
                    <?= venueEscape($errorMessage) ?>
                </div>

            </div>

        <?php endif; ?>

        <!-- STATISTICS -->

        <section class="admin-venues-stats">

            <div class="admin-venues-stat-card">

                <div class="admin-venues-stat-icon">
                    🏟️
                </div>

                <div>
                    <span>Total Venues</span>
                    <strong><?= $totalVenues ?></strong>
                </div>

            </div>

            <div class="admin-venues-stat-card">

                <div class="admin-venues-stat-icon">
                    ✓
                </div>

                <div>
                    <span>Available</span>
                    <strong><?= $availableVenues ?></strong>
                </div>

            </div>

            <div class="admin-venues-stat-card">

                <div class="admin-venues-stat-icon">
                    ⏸
                </div>

                <div>
                    <span>Unavailable</span>
                    <strong><?= $unavailableVenues ?></strong>
                </div>

            </div>

            <div class="admin-venues-stat-card">

                <div class="admin-venues-stat-icon">
                    🔧
                </div>

                <div>
                    <span>Maintenance</span>
                    <strong><?= $maintenanceVenues ?></strong>
                </div>

            </div>

        </section>

        <!-- VENUE CARD -->

        <section class="admin-venues-card">

            <div class="admin-venues-card-header">

                <div>

                    <h2>All Venues</h2>

                    <p>
                        View and manage the venues available in SportSync.
                    </p>

                </div>

            </div>

            <!-- FILTERS -->

            <form
                method="GET"
                action="admin-venues.php"
                class="admin-venues-filters"
            >

                <div class="admin-venues-search-box">

                    <label for="venueSearch">
                        Search
                    </label>

                    <input
                        type="search"
                        id="venueSearch"
                        name="search"
                        value="<?= venueEscape($search) ?>"
                        placeholder="Search venue or location..."
                    >

                </div>

                <div class="admin-venues-status-filter">

                    <label for="venueStatus">
                        Status
                    </label>

                    <select
                        id="venueStatus"
                        name="status"
                    >

                        <option
                            value=""
                            <?= $statusFilter === '' ? 'selected' : '' ?>
                        >
                            All Statuses
                        </option>

                        <option
                            value="AVAILABLE"
                            <?= $statusFilter === 'AVAILABLE' ? 'selected' : '' ?>
                        >
                            Available
                        </option>

                        <option
                            value="UNAVAILABLE"
                            <?= $statusFilter === 'UNAVAILABLE' ? 'selected' : '' ?>
                        >
                            Unavailable
                        </option>

                        <option
                            value="MAINTENANCE"
                            <?= $statusFilter === 'MAINTENANCE' ? 'selected' : '' ?>
                        >
                            Maintenance
                        </option>

                    </select>

                </div>

                <button
                    type="submit"
                    class="admin-venues-filter-button"
                >
                    Apply
                </button>

                <?php if ($search !== '' || $statusFilter !== ''): ?>

                    <a
                        href="admin-venues.php"
                        class="admin-venues-clear-button"
                    >
                        Clear
                    </a>

                <?php endif; ?>

            </form>

            <!-- TABLE -->

            <div class="admin-venues-table-wrapper">

                <?php if ($venues === []): ?>

                    <div class="admin-venues-empty">

                        <div class="admin-venues-empty-icon">
                            🏟️
                        </div>

                        <h3>No venues found</h3>

                        <p>
                            Try changing your search/filter or add a new venue.
                        </p>

                    </div>

                <?php else: ?>

                    <table class="admin-venues-table">

                        <thead>

                            <tr>

                                <th>Venue</th>

                                <th>Location</th>

                                <th>Capacity</th>

                                <th>Status</th>

                                <th>Created</th>

                                <th>Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($venues as $venue): ?>

                                <?php

                                $status = strtoupper(
                                    (string) $venue['availability_status']
                                );

                                $statusClass = match ($status) {

                                    'AVAILABLE' => 'available',

                                    'UNAVAILABLE' => 'unavailable',

                                    'MAINTENANCE' => 'maintenance',

                                    default => 'unknown'
                                };

                                ?>

                                <tr>

                                    <td>

                                        <div class="admin-venues-name-cell">

                                            <div class="admin-venues-name-icon">
                                                🏟️
                                            </div>

                                            <div>

                                                <strong>
                                                    <?= venueEscape($venue['venue_name']) ?>
                                                </strong>

                                                <small>
                                                    Venue #<?= (int) $venue['venue_id'] ?>
                                                </small>

                                            </div>

                                        </div>

                                    </td>

                                    <td>
                                        <?= venueEscape($venue['location']) ?>
                                    </td>

                                    <td>

                                        <?php if ($venue['capacity'] !== null): ?>

                                            <?= number_format(
                                                (int) $venue['capacity']
                                            ) ?>

                                        <?php else: ?>

                                            <span class="admin-venues-muted">
                                                Not specified
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <span
                                            class="admin-venues-status <?= $statusClass ?>"
                                        >

                                            <span class="admin-venues-status-dot"></span>

                                            <?= venueEscape($status) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <?= venueEscape(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    (string) $venue['created_at']
                                                )
                                            )
                                        ) ?>

                                    </td>

                                    <td>

                                        <button
                                            type="button"
                                            class="admin-venues-manage-button"
                                            data-venue-id="<?= (int) $venue['venue_id'] ?>"
                                            data-venue-name="<?= venueEscape($venue['venue_name']) ?>"
                                            data-location="<?= venueEscape($venue['location']) ?>"
                                            data-capacity="<?= $venue['capacity'] !== null ? (int) $venue['capacity'] : '' ?>"
                                            data-status="<?= venueEscape($status) ?>"
                                        >
                                            Manage
                                        </button>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </section>

    </div>

</main>


<!-- =========================================================
     ADD VENUE MODAL
     ========================================================= -->

<div
    class="admin-venues-modal"
    id="addVenueModal"
    aria-hidden="true"
>

    <div
        class="admin-venues-modal-overlay"
        id="addVenueModalOverlay"
    ></div>

    <div
        class="admin-venues-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="addVenueModalTitle"
    >

        <div class="admin-venues-modal-header">

            <div>

                <h2 id="addVenueModalTitle">
                    Add New Venue
                </h2>

                <p>
                    Add a sports venue to SportSync.
                </p>

            </div>

            <button
                type="button"
                class="admin-venues-modal-close"
                id="closeAddVenueButton"
            >
                ×
            </button>

        </div>

        <form
            method="POST"
            action="admin-venues.php"
            class="admin-venues-form"
        >

            <?php if (function_exists('csrfField')): ?>

                <?= csrfField() ?>

            <?php elseif (function_exists('generateCsrfToken')): ?>

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= venueEscape(generateCsrfToken()) ?>"
                >

            <?php endif; ?>

            <input
                type="hidden"
                name="action"
                value="add_venue"
            >

            <div class="admin-venues-form-group">

                <label for="venueName">
                    Venue Name <span>*</span>
                </label>

                <input
                    type="text"
                    id="venueName"
                    name="venue_name"
                    maxlength="120"
                    placeholder="Example: Cricket Ground"
                    required
                >

            </div>

            <div class="admin-venues-form-group">

                <label for="venueLocation">
                    Location <span>*</span>
                </label>

                <input
                    type="text"
                    id="venueLocation"
                    name="location"
                    maxlength="255"
                    placeholder="Example: College Sports Ground"
                    required
                >

            </div>

            <div class="admin-venues-form-row">

                <div class="admin-venues-form-group">

                    <label for="venueCapacity">
                        Capacity
                    </label>

                    <input
                        type="number"
                        id="venueCapacity"
                        name="capacity"
                        min="1"
                        step="1"
                        placeholder="Example: 500"
                    >

                </div>

                <div class="admin-venues-form-group">

                    <label for="venueAvailability">
                        Availability Status <span>*</span>
                    </label>

                    <select
                        id="venueAvailability"
                        name="availability_status"
                    >

                        <option value="AVAILABLE">
                            Available
                        </option>

                        <option value="UNAVAILABLE">
                            Unavailable
                        </option>

                        <option value="MAINTENANCE">
                            Maintenance
                        </option>

                    </select>

                </div>

            </div>

            <div class="admin-venues-modal-actions">

                <button
                    type="button"
                    class="admin-venues-cancel-button"
                    id="cancelAddVenueButton"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="admin-venues-save-button"
                >
                    Add Venue
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     MANAGE VENUE MODAL
     ========================================================= -->

<div
    class="admin-venues-modal"
    id="manageVenueModal"
    aria-hidden="true"
>

    <div
        class="admin-venues-modal-overlay"
        id="manageVenueModalOverlay"
    ></div>

    <div
        class="admin-venues-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="manageVenueModalTitle"
    >

        <div class="admin-venues-modal-header">

            <div>

                <h2 id="manageVenueModalTitle">
                    Manage Venue
                </h2>

                <p>
                    Update venue information and availability.
                </p>

            </div>

            <button
                type="button"
                class="admin-venues-modal-close"
                id="closeManageVenueButton"
            >
                ×
            </button>

        </div>

        <form
            method="POST"
            action="admin-venues.php"
            class="admin-venues-form"
        >

            <?php if (function_exists('csrfField')): ?>

                <?= csrfField() ?>

            <?php elseif (function_exists('generateCsrfToken')): ?>

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= venueEscape(generateCsrfToken()) ?>"
                >

            <?php endif; ?>

            <input
                type="hidden"
                name="action"
                value="update_venue"
            >

            <input
                type="hidden"
                name="venue_id"
                id="manageVenueId"
                value=""
            >

            <div class="admin-venues-form-group">

                <label for="manageVenueName">
                    Venue Name <span>*</span>
                </label>

                <input
                    type="text"
                    id="manageVenueName"
                    name="venue_name"
                    maxlength="120"
                    required
                >

            </div>

            <div class="admin-venues-form-group">

                <label for="manageVenueLocation">
                    Location <span>*</span>
                </label>

                <input
                    type="text"
                    id="manageVenueLocation"
                    name="location"
                    maxlength="255"
                    required
                >

            </div>

            <div class="admin-venues-form-row">

                <div class="admin-venues-form-group">

                    <label for="manageVenueCapacity">
                        Capacity
                    </label>

                    <input
                        type="number"
                        id="manageVenueCapacity"
                        name="capacity"
                        min="1"
                        step="1"
                    >

                </div>

                <div class="admin-venues-form-group">

                    <label for="manageVenueStatus">
                        Availability Status <span>*</span>
                    </label>

                    <select
                        id="manageVenueStatus"
                        name="availability_status"
                    >

                        <option value="AVAILABLE">
                            Available
                        </option>

                        <option value="UNAVAILABLE">
                            Unavailable
                        </option>

                        <option value="MAINTENANCE">
                            Maintenance
                        </option>

                    </select>

                </div>

            </div>

            <div class="admin-venues-modal-actions">

                <button
                    type="button"
                    class="admin-venues-cancel-button"
                    id="cancelManageVenueButton"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="admin-venues-save-button"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<style>

/* =========================================================
   PAGE
   ========================================================= */

.admin-venues-page {
    width: 100%;
    min-height: calc(100vh - 80px);
    padding: 30px;
    box-sizing: border-box;
}

.admin-venues-container {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
}


/* =========================================================
   HEADER
   ========================================================= */

.admin-venues-page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 28px;
}

.admin-venues-breadcrumb {
    margin-bottom: 8px;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.admin-venues-page-header h1 {
    margin: 0 0 8px;
    font-size: 30px;
}

.admin-venues-page-header p {
    margin: 0;
    color: #64748b;
}


/* =========================================================
   BUTTONS
   ========================================================= */

.admin-venues-add-button,
.admin-venues-filter-button,
.admin-venues-save-button,
.admin-venues-cancel-button,
.admin-venues-clear-button,
.admin-venues-manage-button {
    border: 0;
    cursor: pointer;
    font-family: inherit;
    text-decoration: none;
    transition: 0.2s ease;
}

.admin-venues-add-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 10px;
    background: var(--primary-color, #2563eb);
    color: #fff;
    font-weight: 700;
}

.admin-venues-add-button:hover {
    transform: translateY(-1px);
    opacity: .92;
}

.admin-venues-add-icon {
    font-size: 22px;
}


/* =========================================================
   ALERT
   ========================================================= */

.admin-venues-alert {
    display: flex;
    gap: 12px;
    margin-bottom: 22px;
    padding: 15px 17px;
    border-radius: 10px;
}

.admin-venues-alert-error {
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #991b1b;
}


/* =========================================================
   STATS
   ========================================================= */

.admin-venues-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}

.admin-venues-stat-card {
    display: flex;
    align-items: center;
    gap: 15px;
    min-height: 105px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: var(--card-bg, #fff);
}

.admin-venues-stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border-radius: 12px;
    background: #eff6ff;
    font-size: 22px;
}

.admin-venues-stat-card span {
    display: block;
    margin-bottom: 5px;
    color: #64748b;
    font-size: 13px;
}

.admin-venues-stat-card strong {
    font-size: 25px;
}


/* =========================================================
   CARD
   ========================================================= */

.admin-venues-card {
    overflow: hidden;
    border: 1px solid #e5e7eb;
    border-radius: 15px;
    background: var(--card-bg, #fff);
}

.admin-venues-card-header {
    padding: 24px 26px;
    border-bottom: 1px solid #e5e7eb;
}

.admin-venues-card-header h2 {
    margin: 0 0 6px;
    font-size: 20px;
}

.admin-venues-card-header p {
    margin: 0;
    color: #64748b;
}


/* =========================================================
   FILTERS
   ========================================================= */

.admin-venues-filters {
    display: flex;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 15px;
    padding: 22px 26px;
    border-bottom: 1px solid #e5e7eb;
    background: #fafbfc;
}

.admin-venues-search-box {
    flex: 1 1 300px;
}

.admin-venues-status-filter {
    flex: 0 1 220px;
}

.admin-venues-filters label,
.admin-venues-form-group label {
    display: block;
    margin-bottom: 7px;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

.admin-venues-form-group label span {
    color: #dc2626;
}

.admin-venues-filters input,
.admin-venues-filters select,
.admin-venues-form-group input,
.admin-venues-form-group select {
    width: 100%;
    min-height: 45px;
    padding: 0 13px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    outline: none;
    background: #fff;
    font-family: inherit;
    box-sizing: border-box;
}

.admin-venues-filter-button {
    min-height: 45px;
    padding: 0 18px;
    border-radius: 9px;
    background: var(--primary-color, #2563eb);
    color: #fff;
    font-weight: 700;
}

.admin-venues-clear-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 45px;
    padding: 0 16px;
    border-radius: 9px;
    background: #e2e8f0;
    color: #334155;
    font-weight: 700;
}


/* =========================================================
   TABLE
   ========================================================= */

.admin-venues-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.admin-venues-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}

.admin-venues-table th {
    padding: 15px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    text-align: left;
    text-transform: uppercase;
}

.admin-venues-table td {
    padding: 17px 20px;
    border-bottom: 1px solid #eef2f7;
    font-size: 14px;
    vertical-align: middle;
}

.admin-venues-table tbody tr:hover {
    background: #fafcff;
}

.admin-venues-name-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.admin-venues-name-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border-radius: 10px;
    background: #eff6ff;
}

.admin-venues-name-cell strong {
    display: block;
    margin-bottom: 3px;
}

.admin-venues-name-cell small {
    color: #94a3b8;
    font-size: 12px;
}

.admin-venues-muted {
    color: #94a3b8;
}


/* =========================================================
   STATUS
   ========================================================= */

.admin-venues-status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
}

.admin-venues-status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

.admin-venues-status.available {
    background: #dcfce7;
    color: #166534;
}

.admin-venues-status.available .admin-venues-status-dot {
    background: #16a34a;
}

.admin-venues-status.unavailable {
    background: #fee2e2;
    color: #991b1b;
}

.admin-venues-status.unavailable .admin-venues-status-dot {
    background: #dc2626;
}

.admin-venues-status.maintenance {
    background: #fef3c7;
    color: #92400e;
}

.admin-venues-status.maintenance .admin-venues-status-dot {
    background: #d97706;
}


/* =========================================================
   MANAGE BUTTON
   ========================================================= */

.admin-venues-manage-button {
    min-height: 36px;
    padding: 0 13px;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    background: #fff;
    color: var(--primary-color, #2563eb);
    font-size: 13px;
    font-weight: 700;
}

.admin-venues-manage-button:hover {
    background: #eff6ff;
}


/* =========================================================
   EMPTY
   ========================================================= */

.admin-venues-empty {
    padding: 60px 25px;
    text-align: center;
}

.admin-venues-empty-icon {
    margin-bottom: 15px;
    font-size: 42px;
}

.admin-venues-empty h3 {
    margin: 0 0 7px;
}

.admin-venues-empty p {
    margin: 0;
    color: #64748b;
}


/* =========================================================
   MODAL
   ========================================================= */

.admin-venues-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
}

.admin-venues-modal.is-open {
    display: block;
}

.admin-venues-modal-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, .55);
    backdrop-filter: blur(3px);
}

.admin-venues-modal-dialog {
    position: relative;
    z-index: 2;
    width: calc(100% - 32px);
    max-width: 620px;
    max-height: calc(100vh - 40px);
    margin: 20px auto;
    overflow-y: auto;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, .25);
}

.admin-venues-modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    padding: 24px 26px;
    border-bottom: 1px solid #e5e7eb;
}

.admin-venues-modal-header h2 {
    margin: 0 0 5px;
}

.admin-venues-modal-header p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}

.admin-venues-modal-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border: 0;
    border-radius: 8px;
    background: #f1f5f9;
    cursor: pointer;
    font-size: 25px;
}

.admin-venues-form {
    padding: 26px;
}

.admin-venues-form-group {
    margin-bottom: 19px;
}

.admin-venues-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.admin-venues-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.admin-venues-cancel-button {
    min-height: 44px;
    padding: 0 18px;
    border-radius: 9px;
    background: #e2e8f0;
    color: #334155;
    font-weight: 700;
}

.admin-venues-save-button {
    min-height: 44px;
    padding: 0 20px;
    border-radius: 9px;
    background: var(--primary-color, #2563eb);
    color: #fff;
    font-weight: 700;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 900px) {

    .admin-venues-page {
        padding: 22px 18px 35px;
    }

    .admin-venues-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .admin-venues-page-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .admin-venues-add-button {
        width: 100%;
    }
}

@media (max-width: 600px) {

    .admin-venues-page {
        padding: 18px 12px 30px;
    }

    .admin-venues-stats {
        grid-template-columns: 1fr;
    }

    .admin-venues-form-row {
        grid-template-columns: 1fr;
    }

    .admin-venues-form {
        padding: 20px;
    }

    .admin-venues-modal-header {
        padding: 20px;
    }

    .admin-venues-modal-actions {
        flex-direction: column-reverse;
    }

    .admin-venues-cancel-button,
    .admin-venues-save-button {
        width: 100%;
    }
}

</style>


<script>

document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | ADD VENUE MODAL
    |--------------------------------------------------------------------------
    */

    const addModal = document.getElementById('addVenueModal');

    const addOverlay = document.getElementById(
        'addVenueModalOverlay'
    );

    const openAddButton = document.getElementById(
        'openAddVenueButton'
    );

    const closeAddButton = document.getElementById(
        'closeAddVenueButton'
    );

    const cancelAddButton = document.getElementById(
        'cancelAddVenueButton'
    );

    const venueNameInput = document.getElementById(
        'venueName'
    );


    /*
    |--------------------------------------------------------------------------
    | MANAGE VENUE MODAL
    |--------------------------------------------------------------------------
    */

    const manageModal = document.getElementById(
        'manageVenueModal'
    );

    const manageOverlay = document.getElementById(
        'manageVenueModalOverlay'
    );

    const closeManageButton = document.getElementById(
        'closeManageVenueButton'
    );

    const cancelManageButton = document.getElementById(
        'cancelManageVenueButton'
    );

    const manageVenueId = document.getElementById(
        'manageVenueId'
    );

    const manageVenueName = document.getElementById(
        'manageVenueName'
    );

    const manageVenueLocation = document.getElementById(
        'manageVenueLocation'
    );

    const manageVenueCapacity = document.getElementById(
        'manageVenueCapacity'
    );

    const manageVenueStatus = document.getElementById(
        'manageVenueStatus'
    );


    /*
    |--------------------------------------------------------------------------
    | OPEN ADD
    |--------------------------------------------------------------------------
    */

    function openAddModal() {

        if (!addModal) {
            return;
        }

        addModal.classList.add('is-open');

        addModal.setAttribute(
            'aria-hidden',
            'false'
        );

        if (venueNameInput) {

            setTimeout(function () {

                venueNameInput.focus();

            }, 100);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CLOSE ADD
    |--------------------------------------------------------------------------
    */

    function closeAddModal() {

        if (!addModal) {
            return;
        }

        addModal.classList.remove('is-open');

        addModal.setAttribute(
            'aria-hidden',
            'true'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | OPEN MANAGE
    |--------------------------------------------------------------------------
    */

    function openManageModal(button) {

        if (!manageModal) {
            return;
        }

        manageVenueId.value =
            button.dataset.venueId || '';

        manageVenueName.value =
            button.dataset.venueName || '';

        manageVenueLocation.value =
            button.dataset.location || '';

        manageVenueCapacity.value =
            button.dataset.capacity || '';

        manageVenueStatus.value =
            button.dataset.status || 'AVAILABLE';

        manageModal.classList.add('is-open');

        manageModal.setAttribute(
            'aria-hidden',
            'false'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CLOSE MANAGE
    |--------------------------------------------------------------------------
    */

    function closeManageModal() {

        if (!manageModal) {
            return;
        }

        manageModal.classList.remove('is-open');

        manageModal.setAttribute(
            'aria-hidden',
            'true'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ADD BUTTON
    |--------------------------------------------------------------------------
    */

    if (openAddButton) {

        openAddButton.addEventListener(
            'click',
            openAddModal
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ADD CLOSE BUTTONS
    |--------------------------------------------------------------------------
    */

    if (closeAddButton) {

        closeAddButton.addEventListener(
            'click',
            closeAddModal
        );
    }

    if (cancelAddButton) {

        cancelAddButton.addEventListener(
            'click',
            closeAddModal
        );
    }

    if (addOverlay) {

        addOverlay.addEventListener(
            'click',
            closeAddModal
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MANAGE BUTTONS
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.admin-venues-manage-button')
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    openManageModal(button);

                }
            );

        });


    /*
    |--------------------------------------------------------------------------
    | MANAGE CLOSE BUTTONS
    |--------------------------------------------------------------------------
    */

    if (closeManageButton) {

        closeManageButton.addEventListener(
            'click',
            closeManageModal
        );
    }

    if (cancelManageButton) {

        cancelManageButton.addEventListener(
            'click',
            closeManageModal
        );
    }

    if (manageOverlay) {

        manageOverlay.addEventListener(
            'click',
            closeManageModal
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ESCAPE
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key !== 'Escape') {
                return;
            }

            closeAddModal();

            closeManageModal();

        }
    );

});

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>