<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SportSync - Admin Sports Management
|--------------------------------------------------------------------------
|
| Admin can:
| - View sports
| - Search sports
| - Filter by category
| - Filter by status
| - Add sport
| - Edit sport
| - Activate / deactivate sport
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireLogin();

requireRole('ADMIN');

$db = db();

$pageTitle = 'Sports Management';

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function adminSportsEscape(?string $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        /*
        |--------------------------------------------------------------------------
        | CSRF
        |--------------------------------------------------------------------------
        */

        if (
            function_exists('verifyCsrfRequest')
        ) {

            verifyCsrfRequest();

        } elseif (
            function_exists('verifyCsrfToken')
        ) {

            $token = $_POST['csrf_token'] ?? '';

            if (!verifyCsrfToken($token)) {
                throw new RuntimeException(
                    'Invalid security token. Please refresh the page and try again.'
                );
            }

        }


        $action = trim(
            (string) ($_POST['action'] ?? '')
        );


        /*
        |--------------------------------------------------------------------------
        | ADD SPORT
        |--------------------------------------------------------------------------
        */

        if ($action === 'add') {

            $sportName = trim(
                (string) ($_POST['sport_name'] ?? '')
            );

            $category = trim(
                (string) ($_POST['category'] ?? '')
            );

            $description = trim(
                (string) ($_POST['description'] ?? '')
            );

            $defaultMaxPlayers = (int) (
                $_POST['default_max_team_players'] ?? 1
            );

            $rulesInformation = trim(
                (string) ($_POST['rules_information'] ?? '')
            );


            /*
            |--------------------------------------------------------------------------
            | Validation
            |--------------------------------------------------------------------------
            */

            if ($sportName === '') {

                throw new RuntimeException(
                    'Sport name is required.'
                );

            }


            if (
                mb_strlen($sportName) < 2 ||
                mb_strlen($sportName) > 100
            ) {

                throw new RuntimeException(
                    'Sport name must be between 2 and 100 characters.'
                );

            }


            if (
                !in_array(
                    $category,
                    ['Indoor', 'Outdoor'],
                    true
                )
            ) {

                throw new RuntimeException(
                    'Please select a valid category.'
                );

            }


            if ($defaultMaxPlayers < 1) {

                throw new RuntimeException(
                    'Maximum players must be at least 1.'
                );

            }


            if ($defaultMaxPlayers > 100) {

                throw new RuntimeException(
                    'Maximum players cannot be greater than 100.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Duplicate Check
            |--------------------------------------------------------------------------
            */

            $duplicateStmt = $db->prepare("
                SELECT sport_id
                FROM sports
                WHERE LOWER(TRIM(sport_name)) =
                      LOWER(TRIM(:sport_name))
                LIMIT 1
            ");

            $duplicateStmt->execute([
                ':sport_name' => $sportName
            ]);

            if ($duplicateStmt->fetch()) {

                throw new RuntimeException(
                    'A sport with this name already exists.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Insert
            |--------------------------------------------------------------------------
            */

            $insertStmt = $db->prepare("
                INSERT INTO sports (
                    sport_name,
                    category,
                    description,
                    default_max_team_players,
                    rules_information,
                    sport_status
                )
                VALUES (
                    :sport_name,
                    :category,
                    :description,
                    :default_max_team_players,
                    :rules_information,
                    'ACTIVE'
                )
            ");

            $insertStmt->execute([
                ':sport_name' => $sportName,
                ':category' => $category,
                ':description' => $description !== ''
                    ? $description
                    : null,
                ':default_max_team_players' => $defaultMaxPlayers,
                ':rules_information' => $rulesInformation !== ''
                    ? $rulesInformation
                    : null
            ]);


            $success = 'Sport added successfully.';

        }


        /*
        |--------------------------------------------------------------------------
        | EDIT SPORT
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'edit') {

            $sportId = (int) (
                $_POST['sport_id'] ?? 0
            );

            $sportName = trim(
                (string) ($_POST['sport_name'] ?? '')
            );

            $category = trim(
                (string) ($_POST['category'] ?? '')
            );

            $description = trim(
                (string) ($_POST['description'] ?? '')
            );

            $defaultMaxPlayers = (int) (
                $_POST['default_max_team_players'] ?? 1
            );

            $rulesInformation = trim(
                (string) ($_POST['rules_information'] ?? '')
            );


            if ($sportId <= 0) {

                throw new RuntimeException(
                    'Invalid sport selected.'
                );

            }


            if ($sportName === '') {

                throw new RuntimeException(
                    'Sport name is required.'
                );

            }


            if (
                mb_strlen($sportName) < 2 ||
                mb_strlen($sportName) > 100
            ) {

                throw new RuntimeException(
                    'Sport name must be between 2 and 100 characters.'
                );

            }


            if (
                !in_array(
                    $category,
                    ['Indoor', 'Outdoor'],
                    true
                )
            ) {

                throw new RuntimeException(
                    'Please select a valid category.'
                );

            }


            if ($defaultMaxPlayers < 1) {

                throw new RuntimeException(
                    'Maximum players must be at least 1.'
                );

            }


            if ($defaultMaxPlayers > 100) {

                throw new RuntimeException(
                    'Maximum players cannot be greater than 100.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Duplicate Check
            |--------------------------------------------------------------------------
            */

            $duplicateStmt = $db->prepare("
                SELECT sport_id
                FROM sports
                WHERE LOWER(TRIM(sport_name)) =
                      LOWER(TRIM(:sport_name))
                  AND sport_id <> :sport_id
                LIMIT 1
            ");

            $duplicateStmt->execute([
                ':sport_name' => $sportName,
                ':sport_id' => $sportId
            ]);

            if ($duplicateStmt->fetch()) {

                throw new RuntimeException(
                    'Another sport with this name already exists.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Update
            |--------------------------------------------------------------------------
            */

            $updateStmt = $db->prepare("
                UPDATE sports
                SET
                    sport_name = :sport_name,
                    category = :category,
                    description = :description,
                    default_max_team_players =
                        :default_max_team_players,
                    rules_information = :rules_information
                WHERE sport_id = :sport_id
                LIMIT 1
            ");

            $updateStmt->execute([
                ':sport_name' => $sportName,
                ':category' => $category,
                ':description' => $description !== ''
                    ? $description
                    : null,
                ':default_max_team_players' => $defaultMaxPlayers,
                ':rules_information' => $rulesInformation !== ''
                    ? $rulesInformation
                    : null,
                ':sport_id' => $sportId
            ]);


            $success = 'Sport updated successfully.';

        }


        /*
        |--------------------------------------------------------------------------
        | TOGGLE STATUS
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'toggle_status') {

            $sportId = (int) (
                $_POST['sport_id'] ?? 0
            );


            if ($sportId <= 0) {

                throw new RuntimeException(
                    'Invalid sport selected.'
                );

            }


            $statusStmt = $db->prepare("
                SELECT
                    sport_id,
                    sport_name,
                    sport_status
                FROM sports
                WHERE sport_id = :sport_id
                LIMIT 1
            ");

            $statusStmt->execute([
                ':sport_id' => $sportId
            ]);

            $sport = $statusStmt->fetch(
                PDO::FETCH_ASSOC
            );


            if (!$sport) {

                throw new RuntimeException(
                    'Sport not found.'
                );

            }


            $newStatus =
                $sport['sport_status'] === 'ACTIVE'
                    ? 'INACTIVE'
                    : 'ACTIVE';


            $toggleStmt = $db->prepare("
                UPDATE sports
                SET sport_status = :sport_status
                WHERE sport_id = :sport_id
                LIMIT 1
            ");

            $toggleStmt->execute([
                ':sport_status' => $newStatus,
                ':sport_id' => $sportId
            ]);


            $success =
                $newStatus === 'ACTIVE'
                    ? 'Sport activated successfully.'
                    : 'Sport deactivated successfully.';

        }


        /*
        |--------------------------------------------------------------------------
        | UNKNOWN ACTION
        |--------------------------------------------------------------------------
        */

        else {

            throw new RuntimeException(
                'Invalid action.'
            );

        }

    } catch (Throwable $exception) {

        $error = $exception->getMessage();

    }
}


/*
|--------------------------------------------------------------------------
| Search and Filters
|--------------------------------------------------------------------------
*/

$search = trim(
    (string) ($_GET['search'] ?? '')
);

$categoryFilter = trim(
    (string) ($_GET['category'] ?? '')
);

$statusFilter = trim(
    (string) ($_GET['status'] ?? '')
);


/*
|--------------------------------------------------------------------------
| Build Sports Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        sport_id,
        sport_name,
        category,
        description,
        default_max_team_players,
        rules_information,
        sport_status
    FROM sports
    WHERE 1 = 1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            sport_name LIKE :search
            OR description LIKE :search
            OR rules_information LIKE :search
        )
    ";

    $params[':search'] = '%' . $search . '%';

}


/*
|--------------------------------------------------------------------------
| Category Filter
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $categoryFilter,
        ['Indoor', 'Outdoor'],
        true
    )
) {

    $sql .= "
        AND category = :category
    ";

    $params[':category'] = $categoryFilter;

}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $statusFilter,
        ['ACTIVE', 'INACTIVE'],
        true
    )
) {

    $sql .= "
        AND sport_status = :status
    ";

    $params[':status'] = $statusFilter;

}


/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        sport_status = 'ACTIVE' DESC,
        sport_name ASC
";


$sportsStmt = $db->prepare($sql);

$sportsStmt->execute($params);

$sports = $sportsStmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalSportsStmt = $db->query("
    SELECT COUNT(*)
    FROM sports
");

$totalSports = (int) (
    $totalSportsStmt->fetchColumn()
);


$activeSportsStmt = $db->query("
    SELECT COUNT(*)
    FROM sports
    WHERE sport_status = 'ACTIVE'
");

$activeSports = (int) (
    $activeSportsStmt->fetchColumn()
);


$inactiveSportsStmt = $db->query("
    SELECT COUNT(*)
    FROM sports
    WHERE sport_status = 'INACTIVE'
");

$inactiveSports = (int) (
    $inactiveSportsStmt->fetchColumn()
);


$indoorSportsStmt = $db->query("
    SELECT COUNT(*)
    FROM sports
    WHERE category = 'Indoor'
");

$indoorSports = (int) (
    $indoorSportsStmt->fetchColumn()
);


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';

?>

<style>

/* =========================================================
   ADMIN SPORTS PAGE
   ========================================================= */

.admin-sports-page {
    width: 100%;
    max-width: 1250px;
    margin: 0 auto;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.admin-sports-breadcrumb {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 18px;
    color: #667085;
    font-size: 13px;
}

.admin-sports-breadcrumb a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
}

.admin-sports-breadcrumb a:hover {
    text-decoration: underline;
}


.admin-sports-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 25px;
}


.admin-sports-header h1 {
    margin: 0;
    color: #101828;
    font-size: 30px;
    line-height: 1.2;
    font-weight: 800;
}


.admin-sports-header p {
    margin: 8px 0 0;
    max-width: 720px;
    color: #667085;
    font-size: 14px;
    line-height: 1.6;
}


.admin-add-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 18px;
    border: 0;
    border-radius: 10px;
    background: #2563eb;
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.18);
    transition: 0.2s ease;
}

.admin-add-button:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}


/* =========================================================
   ALERTS
   ========================================================= */

.admin-sports-alert {
    margin-bottom: 20px;
    padding: 14px 16px;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.5;
}


.admin-sports-alert-success {
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #166534;
}


.admin-sports-alert-error {
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #991b1b;
}


/* =========================================================
   STAT CARDS
   ========================================================= */

.admin-sports-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}


.admin-sports-stat {
    padding: 20px;
    border: 1px solid #eaecf0;
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 5px 18px rgba(16, 24, 40, 0.05);
}


.admin-sports-stat-label {
    color: #667085;
    font-size: 13px;
    font-weight: 600;
}


.admin-sports-stat-value {
    margin-top: 7px;
    color: #101828;
    font-size: 28px;
    font-weight: 800;
}


/* =========================================================
   MAIN CARD
   ========================================================= */

.admin-sports-card {
    overflow: hidden;
    border: 1px solid #eaecf0;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 5px 18px rgba(16, 24, 40, 0.05);
}


/* =========================================================
   FILTER BAR
   ========================================================= */

.admin-sports-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 18px;
    border-bottom: 1px solid #eaecf0;
}


.admin-sports-filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    width: 100%;
}


.admin-sports-search {
    flex: 1 1 280px;
    min-width: 220px;
}


.admin-sports-search input,
.admin-sports-filter select {
    width: 100%;
    min-height: 42px;
    padding: 0 13px;
    border: 1px solid #d0d5dd;
    border-radius: 9px;
    background: #ffffff;
    color: #101828;
    font-size: 14px;
    outline: none;
}


.admin-sports-search input:focus,
.admin-sports-filter select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
}


.admin-sports-filter {
    width: 170px;
}


.admin-filter-button {
    min-height: 42px;
    padding: 0 16px;
    border: 1px solid #d0d5dd;
    border-radius: 9px;
    background: #ffffff;
    color: #344054;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}


.admin-filter-button:hover {
    background: #f9fafb;
}


/* =========================================================
   TABLE
   ========================================================= */

.admin-sports-table-wrapper {
    width: 100%;
    overflow-x: auto;
}


.admin-sports-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}


.admin-sports-table th {
    padding: 14px 18px;
    border-bottom: 1px solid #eaecf0;
    background: #f9fafb;
    color: #475467;
    text-align: left;
    font-size: 12px;
    font-weight: 750;
    white-space: nowrap;
}


.admin-sports-table td {
    padding: 16px 18px;
    border-bottom: 1px solid #f2f4f7;
    color: #344054;
    font-size: 14px;
    vertical-align: middle;
}


.admin-sports-table tbody tr:hover {
    background: #f9fbff;
}


.admin-sport-name {
    display: flex;
    align-items: center;
    gap: 12px;
}


.admin-sport-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    border-radius: 11px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 20px;
}


.admin-sport-name strong {
    display: block;
    color: #101828;
    font-size: 14px;
    font-weight: 750;
}


.admin-sport-description {
    margin-top: 3px;
    max-width: 300px;
    overflow: hidden;
    color: #667085;
    font-size: 12px;
    line-height: 1.4;
    text-overflow: ellipsis;
    white-space: nowrap;
}


/* =========================================================
   BADGES
   ========================================================= */

.admin-sport-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 750;
    white-space: nowrap;
}


.admin-sport-badge-indoor {
    background: #f3e8ff;
    color: #7e22ce;
}


.admin-sport-badge-outdoor {
    background: #dcfce7;
    color: #166534;
}


.admin-sport-badge-active {
    background: #dcfce7;
    color: #166534;
}


.admin-sport-badge-inactive {
    background: #f2f4f7;
    color: #475467;
}


/* =========================================================
   ACTIONS
   ========================================================= */

.admin-sport-actions {
    display: flex;
    align-items: center;
    gap: 7px;
}


.admin-sport-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 0 10px;
    border: 1px solid #d0d5dd;
    border-radius: 8px;
    background: #ffffff;
    color: #344054;
    font-size: 12px;
    font-weight: 650;
    cursor: pointer;
    text-decoration: none;
}


.admin-sport-action:hover {
    background: #f9fafb;
}


.admin-sport-action-edit {
    color: #2563eb;
    border-color: #bfdbfe;
    background: #eff6ff;
}


.admin-sport-action-toggle {
    color: #475467;
}


.admin-sport-action-danger {
    color: #b42318;
}


/* =========================================================
   EMPTY STATE
   ========================================================= */

.admin-sports-empty {
    padding: 55px 20px;
    text-align: center;
}


.admin-sports-empty-icon {
    margin-bottom: 12px;
    font-size: 42px;
}


.admin-sports-empty h3 {
    margin: 0;
    color: #101828;
    font-size: 18px;
}


.admin-sports-empty p {
    margin: 7px 0 0;
    color: #667085;
    font-size: 14px;
}


/* =========================================================
   MODAL
   ========================================================= */

.admin-sports-modal {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(16, 24, 40, 0.55);
}


.admin-sports-modal.is-open {
    display: flex;
}


.admin-sports-modal-content {
    width: 100%;
    max-width: 620px;
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 25px 60px rgba(16, 24, 40, 0.20);
}


.admin-sports-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 20px 22px;
    border-bottom: 1px solid #eaecf0;
}


.admin-sports-modal-header h2 {
    margin: 0;
    color: #101828;
    font-size: 20px;
    font-weight: 800;
}


.admin-sports-modal-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: 0;
    border-radius: 8px;
    background: #f2f4f7;
    color: #344054;
    font-size: 20px;
    cursor: pointer;
}


.admin-sports-modal-body {
    padding: 22px;
}


/* =========================================================
   FORM
   ========================================================= */

.admin-sports-form-group {
    margin-bottom: 17px;
}


.admin-sports-form-group label {
    display: block;
    margin-bottom: 7px;
    color: #344054;
    font-size: 13px;
    font-weight: 700;
}


.admin-sports-form-group input,
.admin-sports-form-group select,
.admin-sports-form-group textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 11px 13px;
    border: 1px solid #d0d5dd;
    border-radius: 9px;
    background: #ffffff;
    color: #101828;
    font-family: inherit;
    font-size: 14px;
    outline: none;
}


.admin-sports-form-group textarea {
    min-height: 100px;
    resize: vertical;
}


.admin-sports-form-group input:focus,
.admin-sports-form-group select:focus,
.admin-sports-form-group textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
}


.admin-sports-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}


.admin-sports-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 17px 22px;
    border-top: 1px solid #eaecf0;
}


.admin-sports-cancel {
    min-height: 42px;
    padding: 0 16px;
    border: 1px solid #d0d5dd;
    border-radius: 9px;
    background: #ffffff;
    color: #344054;
    font-size: 14px;
    font-weight: 650;
    cursor: pointer;
}


.admin-sports-submit {
    min-height: 42px;
    padding: 0 18px;
    border: 0;
    border-radius: 9px;
    background: #2563eb;
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 900px) {

    .admin-sports-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

}


@media (max-width: 700px) {

    .admin-sports-header {
        flex-direction: column;
    }

    .admin-add-button {
        width: 100%;
    }

    .admin-sports-toolbar {
        padding: 14px;
    }

    .admin-sports-filter {
        width: 100%;
    }

    .admin-sports-search {
        flex-basis: 100%;
        width: 100%;
    }

    .admin-filter-button {
        width: 100%;
    }

    .admin-sports-form-grid {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 520px) {

    .admin-sports-page {
        width: 100%;
    }

    .admin-sports-header h1 {
        font-size: 25px;
    }

    .admin-sports-stats {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .admin-sports-stat {
        padding: 15px;
    }

    .admin-sports-stat-value {
        font-size: 23px;
    }

    .admin-sports-modal {
        padding: 10px;
    }

    .admin-sports-modal-content {
        max-height: 95vh;
    }

}


/* =========================================================
   REDUCED MOTION
   ========================================================= */

@media (prefers-reduced-motion: reduce) {

    .admin-add-button,
    .admin-sport-action {
        transition: none;
    }

}

</style>


<main class="admin-sports-page">


    <!-- =====================================================
         BREADCRUMB
         ===================================================== -->

    <div class="admin-sports-breadcrumb">

        <a href="<?= adminSportsEscape($dashboardPage) ?>">
            Dashboard
        </a>

        <span>›</span>

        <span>Sports Management</span>

    </div>


    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <div class="admin-sports-header">

        <div>

            <h1>
                Manage Sports
            </h1>

            <p>
                Add, update and manage the sports available
                in your college sports management system.
            </p>

        </div>


        <button
            type="button"
            class="admin-add-button"
            id="openAddSportModal"
        >
            <span>＋</span>
            Add Sport
        </button>

    </div>


    <!-- =====================================================
         ALERTS
         ===================================================== -->

    <?php if ($success !== ''): ?>

        <div class="admin-sports-alert admin-sports-alert-success">
            <?= adminSportsEscape($success) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="admin-sports-alert admin-sports-alert-error">
            <?= adminSportsEscape($error) ?>
        </div>

    <?php endif; ?>


    <!-- =====================================================
         STATISTICS
         ===================================================== -->

    <section class="admin-sports-stats">

        <div class="admin-sports-stat">

            <div class="admin-sports-stat-label">
                Total Sports
            </div>

            <div class="admin-sports-stat-value">
                <?= $totalSports ?>
            </div>

        </div>


        <div class="admin-sports-stat">

            <div class="admin-sports-stat-label">
                Active Sports
            </div>

            <div class="admin-sports-stat-value">
                <?= $activeSports ?>
            </div>

        </div>


        <div class="admin-sports-stat">

            <div class="admin-sports-stat-label">
                Inactive Sports
            </div>

            <div class="admin-sports-stat-value">
                <?= $inactiveSports ?>
            </div>

        </div>


        <div class="admin-sports-stat">

            <div class="admin-sports-stat-label">
                Indoor Sports
            </div>

            <div class="admin-sports-stat-value">
                <?= $indoorSports ?>
            </div>

        </div>

    </section>


    <!-- =====================================================
         SPORTS TABLE CARD
         ===================================================== -->

    <section class="admin-sports-card">


        <!-- FILTERS -->

        <div class="admin-sports-toolbar">

            <form
                method="GET"
                class="admin-sports-filters"
            >

                <div class="admin-sports-search">

                    <input
                        type="search"
                        name="search"
                        value="<?= adminSportsEscape($search) ?>"
                        placeholder="Search sports..."
                        aria-label="Search sports"
                    >

                </div>


                <div class="admin-sports-filter">

                    <select
                        name="category"
                        aria-label="Filter category"
                    >

                        <option value="">
                            All Categories
                        </option>

                        <option
                            value="Indoor"
                            <?= $categoryFilter === 'Indoor'
                                ? 'selected'
                                : '' ?>
                        >
                            Indoor
                        </option>

                        <option
                            value="Outdoor"
                            <?= $categoryFilter === 'Outdoor'
                                ? 'selected'
                                : '' ?>
                        >
                            Outdoor
                        </option>

                    </select>

                </div>


                <div class="admin-sports-filter">

                    <select
                        name="status"
                        aria-label="Filter status"
                    >

                        <option value="">
                            All Status
                        </option>

                        <option
                            value="ACTIVE"
                            <?= $statusFilter === 'ACTIVE'
                                ? 'selected'
                                : '' ?>
                        >
                            Active
                        </option>

                        <option
                            value="INACTIVE"
                            <?= $statusFilter === 'INACTIVE'
                                ? 'selected'
                                : '' ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="admin-filter-button"
                >
                    Filter
                </button>


                <?php if (
                    $search !== '' ||
                    $categoryFilter !== '' ||
                    $statusFilter !== ''
                ): ?>

                    <a
                        href="admin-sports.php"
                        class="admin-filter-button"
                        style="display:inline-flex;align-items:center;justify-content:center;text-decoration:none;"
                    >
                        Clear
                    </a>

                <?php endif; ?>

            </form>

        </div>


        <!-- =================================================
             TABLE
             ================================================= -->

        <?php if (!empty($sports)): ?>

            <div class="admin-sports-table-wrapper">

                <table class="admin-sports-table">

                    <thead>

                        <tr>

                            <th>
                                Sport Name
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Team / Players
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($sports as $sport): ?>

                            <?php

                            $sportId = (int) $sport['sport_id'];

                            $sportName =
                                (string) $sport['sport_name'];

                            $category =
                                (string) $sport['category'];

                            $description =
                                (string) (
                                    $sport['description'] ?? ''
                                );

                            $maxPlayers =
                                (int) (
                                    $sport[
                                        'default_max_team_players'
                                    ] ?? 1
                                );

                            $rules =
                                (string) (
                                    $sport[
                                        'rules_information'
                                    ] ?? ''
                                );

                            $status =
                                (string) $sport['sport_status'];

                            ?>


                            <tr>


                                <!-- SPORT NAME -->

                                <td>

                                    <div class="admin-sport-name">

                                        <div class="admin-sport-icon">
                                            🏅
                                        </div>


                                        <div>

                                            <strong>
                                                <?= adminSportsEscape(
                                                    $sportName
                                                ) ?>
                                            </strong>


                                            <?php if ($description !== ''): ?>

                                                <div
                                                    class="admin-sport-description"
                                                    title="<?= adminSportsEscape(
                                                        $description
                                                    ) ?>"
                                                >
                                                    <?= adminSportsEscape(
                                                        $description
                                                    ) ?>
                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </td>


                                <!-- CATEGORY -->

                                <td>

                                    <?php if (
                                        $category === 'Indoor'
                                    ): ?>

                                        <span
                                            class="admin-sport-badge admin-sport-badge-indoor"
                                        >
                                            Indoor
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="admin-sport-badge admin-sport-badge-outdoor"
                                        >
                                            Outdoor
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- TEAM SIZE -->

                                <td>

                                    <?= $maxPlayers ?>

                                    <?php if ($maxPlayers === 1): ?>

                                        player

                                    <?php else: ?>

                                        players

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php if (
                                        $status === 'ACTIVE'
                                    ): ?>

                                        <span
                                            class="admin-sport-badge admin-sport-badge-active"
                                        >
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="admin-sport-badge admin-sport-badge-inactive"
                                        >
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="admin-sport-actions">


                                        <!-- EDIT -->

                                        <button
                                            type="button"
                                            class="admin-sport-action admin-sport-action-edit edit-sport-button"
                                            data-id="<?= $sportId ?>"
                                            data-name="<?= adminSportsEscape(
                                                $sportName
                                            ) ?>"
                                            data-category="<?= adminSportsEscape(
                                                $category
                                            ) ?>"
                                            data-description="<?= adminSportsEscape(
                                                $description
                                            ) ?>"
                                            data-max-players="<?= $maxPlayers ?>"
                                            data-rules="<?= adminSportsEscape(
                                                $rules
                                            ) ?>"
                                        >
                                            Edit
                                        </button>


                                        <!-- STATUS -->

                                        <form
                                            method="POST"
                                            style="display:inline;"
                                            onsubmit="return confirm('Are you sure you want to change this sport status?');"
                                        >

                                            <?= csrfField() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="toggle_status"
                                            >

                                            <input
                                                type="hidden"
                                                name="sport_id"
                                                value="<?= $sportId ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="admin-sport-action admin-sport-action-toggle"
                                            >

                                                <?= $status === 'ACTIVE'
                                                    ? 'Deactivate'
                                                    : 'Activate' ?>

                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


        <?php else: ?>

            <div class="admin-sports-empty">

                <div class="admin-sports-empty-icon">
                    🏅
                </div>

                <h3>
                    No sports found
                </h3>

                <p>
                    Try changing your search or filters,
                    or add a new sport.
                </p>

            </div>

        <?php endif; ?>


    </section>


</main>


<!-- =========================================================
     ADD / EDIT MODAL
     ========================================================= -->

<div
    class="admin-sports-modal"
    id="sportModal"
    aria-hidden="true"
>

    <div class="admin-sports-modal-content">


        <div class="admin-sports-modal-header">

            <h2 id="sportModalTitle">
                Add Sport
            </h2>

            <button
                type="button"
                class="admin-sports-modal-close"
                id="closeSportModal"
                aria-label="Close"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            id="sportForm"
        >

            <?= csrfField() ?>


            <div class="admin-sports-modal-body">


                <input
                    type="hidden"
                    name="action"
                    id="sportAction"
                    value="add"
                >


                <input
                    type="hidden"
                    name="sport_id"
                    id="sportId"
                    value=""
                >


                <!-- SPORT NAME -->

                <div class="admin-sports-form-group">

                    <label for="sportName">
                        Sport Name *
                    </label>

                    <input
                        type="text"
                        id="sportName"
                        name="sport_name"
                        maxlength="100"
                        placeholder="Example: Cricket"
                        required
                    >

                </div>


                <!-- CATEGORY + MAX PLAYERS -->

                <div class="admin-sports-form-grid">


                    <div class="admin-sports-form-group">

                        <label for="sportCategory">
                            Category *
                        </label>

                        <select
                            id="sportCategory"
                            name="category"
                            required
                        >

                            <option value="">
                                Select Category
                            </option>

                            <option value="Indoor">
                                Indoor
                            </option>

                            <option value="Outdoor">
                                Outdoor
                            </option>

                        </select>

                    </div>


                    <div class="admin-sports-form-group">

                        <label for="sportMaxPlayers">
                            Maximum Players *
                        </label>

                        <input
                            type="number"
                            id="sportMaxPlayers"
                            name="default_max_team_players"
                            min="1"
                            max="100"
                            value="1"
                            required
                        >

                    </div>

                </div>


                <!-- DESCRIPTION -->

                <div class="admin-sports-form-group">

                    <label for="sportDescription">
                        Description
                    </label>

                    <textarea
                        id="sportDescription"
                        name="description"
                        maxlength="1000"
                        placeholder="Short description of the sport..."
                    ></textarea>

                </div>


                <!-- RULES -->

                <div class="admin-sports-form-group">

                    <label for="sportRules">
                        Rules / Information
                    </label>

                    <textarea
                        id="sportRules"
                        name="rules_information"
                        maxlength="3000"
                        placeholder="Basic rules or useful information..."
                    ></textarea>

                </div>


            </div>


            <div class="admin-sports-modal-footer">

                <button
                    type="button"
                    class="admin-sports-cancel"
                    id="cancelSportModal"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="admin-sports-submit"
                    id="sportSubmitButton"
                >
                    Add Sport
                </button>

            </div>

        </form>

    </div>

</div>


<script>

(function () {

    'use strict';


    const modal =
        document.getElementById('sportModal');

    const openButton =
        document.getElementById('openAddSportModal');

    const closeButton =
        document.getElementById('closeSportModal');

    const cancelButton =
        document.getElementById('cancelSportModal');

    const form =
        document.getElementById('sportForm');

    const modalTitle =
        document.getElementById('sportModalTitle');

    const actionInput =
        document.getElementById('sportAction');

    const idInput =
        document.getElementById('sportId');

    const nameInput =
        document.getElementById('sportName');

    const categoryInput =
        document.getElementById('sportCategory');

    const maxPlayersInput =
        document.getElementById('sportMaxPlayers');

    const descriptionInput =
        document.getElementById('sportDescription');

    const rulesInput =
        document.getElementById('sportRules');

    const submitButton =
        document.getElementById('sportSubmitButton');


    if (!modal || !form) {
        return;
    }


    function openModal() {

        modal.classList.add('is-open');

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.style.overflow = 'hidden';

    }


    function closeModal() {

        modal.classList.remove('is-open');

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.style.overflow = '';

    }


    function prepareAddSport() {

        form.reset();

        actionInput.value = 'add';

        idInput.value = '';

        modalTitle.textContent =
            'Add Sport';

        submitButton.textContent =
            'Add Sport';

        maxPlayersInput.value = '1';

        openModal();

        setTimeout(function () {

            nameInput.focus();

        }, 100);

    }


    function prepareEditSport(button) {

        form.reset();

        actionInput.value = 'edit';

        idInput.value =
            button.dataset.id || '';

        nameInput.value =
            button.dataset.name || '';

        categoryInput.value =
            button.dataset.category || '';

        maxPlayersInput.value =
            button.dataset.maxPlayers || '1';

        descriptionInput.value =
            button.dataset.description || '';

        rulesInput.value =
            button.dataset.rules || '';

        modalTitle.textContent =
            'Edit Sport';

        submitButton.textContent =
            'Save Changes';

        openModal();

        setTimeout(function () {

            nameInput.focus();

        }, 100);

    }


    /*
    |--------------------------------------------------------------------------
    | Add
    |--------------------------------------------------------------------------
    */

    if (openButton) {

        openButton.addEventListener(
            'click',
            prepareAddSport
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Edit Buttons
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.edit-sport-button')
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    prepareEditSport(button);

                }
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Close
    |--------------------------------------------------------------------------
    */

    if (closeButton) {

        closeButton.addEventListener(
            'click',
            closeModal
        );

    }


    if (cancelButton) {

        cancelButton.addEventListener(
            'click',
            closeModal
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Click outside
    |--------------------------------------------------------------------------
    */

    modal.addEventListener(
        'click',
        function (event) {

            if (
                event.target === modal
            ) {

                closeModal();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Escape
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape' &&
                modal.classList.contains('is-open')
            ) {

                closeModal();

            }

        }
    );


})();

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>