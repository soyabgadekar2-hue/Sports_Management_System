```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireLogin();
requireRole('ADMIN');

$pdo = db();

function eventFormatEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirectToEventFormats(): never
{
    header('Location: admin-event-formats.php');
    exit;
}

$errorMessage = '';
$successMessage = '';

/*
|--------------------------------------------------------------------------
| Handle Add / Update / Toggle
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verifyCsrfRequest()) {
            throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
        }

        $action = trim((string) ($_POST['action'] ?? ''));

        /*
        |--------------------------------------------------------------------------
        | Add Tournament Format
        |--------------------------------------------------------------------------
        */
        if ($action === 'add_format') {
            $formatName = trim((string) ($_POST['format_name'] ?? ''));
            $formatCode = strtoupper(trim((string) ($_POST['format_code'] ?? '')));
            $description = trim((string) ($_POST['description'] ?? ''));
            $status = trim((string) ($_POST['format_status'] ?? 'ACTIVE'));

            if ($formatName === '') {
                throw new RuntimeException('Format name is required.');
            }

            if ($formatCode === '') {
                throw new RuntimeException('Format code is required.');
            }

            if (!preg_match('/^[A-Z0-9_]+$/', $formatCode)) {
                throw new RuntimeException(
                    'Format code can contain only uppercase letters, numbers and underscores.'
                );
            }

            if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                $status = 'ACTIVE';
            }

            $check = $pdo->prepare(
                'SELECT event_format_id
                 FROM event_formats
                 WHERE format_name = :format_name
                    OR format_code = :format_code
                 LIMIT 1'
            );

            $check->execute([
                ':format_name' => $formatName,
                ':format_code' => $formatCode,
            ]);

            if ($check->fetch()) {
                throw new RuntimeException(
                    'A tournament format with the same name or code already exists.'
                );
            }

            $insert = $pdo->prepare(
                'INSERT INTO event_formats
                    (format_name, format_code, description, format_status)
                 VALUES
                    (:format_name, :format_code, :description, :format_status)'
            );

            $insert->execute([
                ':format_name' => $formatName,
                ':format_code' => $formatCode,
                ':description' => $description !== '' ? $description : null,
                ':format_status' => $status,
            ]);

            $_SESSION['event_format_success'] = 'Tournament format added successfully.';
            redirectToEventFormats();
        }

        /*
        |--------------------------------------------------------------------------
        | Update Tournament Format
        |--------------------------------------------------------------------------
        */
        if ($action === 'update_format') {
            $formatId = (int) ($_POST['event_format_id'] ?? 0);
            $formatName = trim((string) ($_POST['format_name'] ?? ''));
            $formatCode = strtoupper(trim((string) ($_POST['format_code'] ?? '')));
            $description = trim((string) ($_POST['description'] ?? ''));
            $status = trim((string) ($_POST['format_status'] ?? 'ACTIVE'));

            if ($formatId <= 0) {
                throw new RuntimeException('Invalid tournament format.');
            }

            if ($formatName === '') {
                throw new RuntimeException('Format name is required.');
            }

            if ($formatCode === '') {
                throw new RuntimeException('Format code is required.');
            }

            if (!preg_match('/^[A-Z0-9_]+$/', $formatCode)) {
                throw new RuntimeException(
                    'Format code can contain only uppercase letters, numbers and underscores.'
                );
            }

            if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                throw new RuntimeException('Invalid status.');
            }

            $check = $pdo->prepare(
                'SELECT event_format_id
                 FROM event_formats
                 WHERE (format_name = :format_name OR format_code = :format_code)
                   AND event_format_id <> :event_format_id
                 LIMIT 1'
            );

            $check->execute([
                ':format_name' => $formatName,
                ':format_code' => $formatCode,
                ':event_format_id' => $formatId,
            ]);

            if ($check->fetch()) {
                throw new RuntimeException(
                    'Another tournament format already uses this name or code.'
                );
            }

            $update = $pdo->prepare(
                'UPDATE event_formats
                 SET
                    format_name = :format_name,
                    format_code = :format_code,
                    description = :description,
                    format_status = :format_status
                 WHERE event_format_id = :event_format_id'
            );

            $update->execute([
                ':format_name' => $formatName,
                ':format_code' => $formatCode,
                ':description' => $description !== '' ? $description : null,
                ':format_status' => $status,
                ':event_format_id' => $formatId,
            ]);

            $_SESSION['event_format_success'] = 'Tournament format updated successfully.';
            redirectToEventFormats();
        }

        /*
        |--------------------------------------------------------------------------
        | Toggle Active / Inactive
        |--------------------------------------------------------------------------
        */
        if ($action === 'toggle_status') {
            $formatId = (int) ($_POST['event_format_id'] ?? 0);

            if ($formatId <= 0) {
                throw new RuntimeException('Invalid tournament format.');
            }

            $find = $pdo->prepare(
                'SELECT format_status
                 FROM event_formats
                 WHERE event_format_id = :event_format_id
                 LIMIT 1'
            );

            $find->execute([
                ':event_format_id' => $formatId,
            ]);

            $format = $find->fetch(PDO::FETCH_ASSOC);

            if (!$format) {
                throw new RuntimeException('Tournament format not found.');
            }

            $newStatus = $format['format_status'] === 'ACTIVE'
                ? 'INACTIVE'
                : 'ACTIVE';

            $update = $pdo->prepare(
                'UPDATE event_formats
                 SET format_status = :format_status
                 WHERE event_format_id = :event_format_id'
            );

            $update->execute([
                ':format_status' => $newStatus,
                ':event_format_id' => $formatId,
            ]);

            $_SESSION['event_format_success'] =
                $newStatus === 'ACTIVE'
                    ? 'Tournament format activated successfully.'
                    : 'Tournament format deactivated successfully.';

            redirectToEventFormats();
        }

        throw new RuntimeException('Invalid action.');
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Flash Message
|--------------------------------------------------------------------------
*/
if (isset($_SESSION['event_format_success'])) {
    $successMessage = (string) $_SESSION['event_format_success'];
    unset($_SESSION['event_format_success']);
}

/*
|--------------------------------------------------------------------------
| Search / Filter
|--------------------------------------------------------------------------
*/
$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$allowedStatuses = ['', 'ACTIVE', 'INACTIVE'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$sql = '
    SELECT
        event_format_id,
        format_name,
        format_code,
        description,
        format_status,
        created_at,
        updated_at
    FROM event_formats
    WHERE 1 = 1
';

$params = [];

if ($search !== '') {
    $sql .= '
        AND (
            format_name LIKE :search_name
            OR format_code LIKE :search_code
            OR description LIKE :search_description
        )
    ';

    $searchValue = '%' . $search . '%';

    $params[':search_name'] = $searchValue;
    $params[':search_code'] = $searchValue;
    $params[':search_description'] = $searchValue;
}

if ($statusFilter !== '') {
    $sql .= ' AND format_status = :status_filter ';
    $params[':status_filter'] = $statusFilter;
}

$sql .= ' ORDER BY event_format_id ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$formats = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/
$totalFormats = (int) $pdo
    ->query('SELECT COUNT(*) FROM event_formats')
    ->fetchColumn();

$activeFormats = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM event_formats
         WHERE format_status = 'ACTIVE'"
    )
    ->fetchColumn();

$inactiveFormats = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM event_formats
         WHERE format_status = 'INACTIVE'"
    )
    ->fetchColumn();

require_once __DIR__ . '/../includes/header.php';

?>

<style>
    .event-formats-page {
        max-width: 1400px;
        margin: 0 auto;
        padding: 28px 30px 50px;
    }

    .event-formats-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 26px;
    }

    .event-formats-header-content h1 {
        margin: 0 0 8px;
        font-size: 30px;
        line-height: 1.2;
        color: var(--text-color, #172033);
    }

    .event-formats-header-content p {
        margin: 0;
        color: var(--muted-text, #6b7280);
        font-size: 15px;
        line-height: 1.6;
    }

    .event-format-add-button {
        border: 0;
        border-radius: 10px;
        padding: 12px 18px;
        background: var(--primary-color, #2563eb);
        color: #ffffff;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        white-space: nowrap;
        box-shadow: 0 5px 14px rgba(37, 99, 235, 0.18);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .event-format-add-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.24);
    }

    .event-format-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        margin-bottom: 24px;
    }

    .event-format-stat-card {
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 14px;
        padding: 20px;
        min-height: 105px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
    }

    .event-format-stat-label {
        color: var(--muted-text, #6b7280);
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .event-format-stat-value {
        color: var(--text-color, #172033);
        font-size: 28px;
        font-weight: 800;
        line-height: 1;
    }

    .event-formats-card {
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 14px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
        overflow: hidden;
    }

    .event-formats-toolbar {
        padding: 20px 22px;
        border-bottom: 1px solid var(--border-color, #e5e7eb);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .event-format-search-form {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
    }

    .event-format-search-input,
    .event-format-filter-select {
        min-height: 42px;
        border: 1px solid var(--border-color, #d1d5db);
        border-radius: 9px;
        background: #ffffff;
        color: var(--text-color, #172033);
        padding: 0 12px;
        font-size: 14px;
        outline: none;
    }

    .event-format-search-input {
        width: 100%;
        max-width: 440px;
    }

    .event-format-filter-select {
        min-width: 150px;
    }

    .event-format-search-input:focus,
    .event-format-filter-select:focus,
    .event-format-input:focus,
    .event-format-textarea:focus {
        border-color: var(--primary-color, #2563eb);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
    }

    .event-format-search-button,
    .event-format-clear-button {
        min-height: 42px;
        border-radius: 9px;
        padding: 0 15px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .event-format-search-button {
        border: 0;
        background: var(--primary-color, #2563eb);
        color: #ffffff;
    }

    .event-format-clear-button {
        border: 1px solid var(--border-color, #d1d5db);
        background: #ffffff;
        color: #374151;
    }

    .event-formats-table-wrapper {
        width: 100%;
        overflow-x: auto;
    }

    .event-formats-table {
        width: 100%;
        min-width: 850px;
        border-collapse: collapse;
    }

    .event-formats-table th {
        background: #f8fafc;
        color: #475569;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        text-align: left;
        padding: 15px 18px;
        border-bottom: 1px solid var(--border-color, #e5e7eb);
        white-space: nowrap;
    }

    .event-formats-table td {
        padding: 17px 18px;
        color: #334155;
        font-size: 14px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: middle;
    }

    .event-formats-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .event-formats-table tbody tr:hover {
        background: #fafcff;
    }

    .event-format-name {
        font-weight: 750;
        color: var(--text-color, #172033);
    }

    .event-format-code {
        display: inline-flex;
        align-items: center;
        padding: 5px 9px;
        border-radius: 7px;
        background: #eef4ff;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.03em;
    }

    .event-format-description {
        max-width: 340px;
        color: #64748b;
        line-height: 1.5;
    }

    .event-format-status {
        display: inline-flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
    }

    .event-format-status.active {
        background: #ecfdf3;
        color: #15803d;
    }

    .event-format-status.inactive {
        background: #fef2f2;
        color: #b91c1c;
    }

    .event-format-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
    }

    .event-format-action-button {
        min-height: 35px;
        border: 1px solid var(--border-color, #d1d5db);
        border-radius: 8px;
        padding: 0 11px;
        background: #ffffff;
        color: #374151;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }

    .event-format-action-button:hover {
        background: #f8fafc;
    }

    .event-format-action-button.edit {
        color: #1d4ed8;
        border-color: #bfdbfe;
        background: #eff6ff;
    }

    .event-format-action-button.deactivate {
        color: #b45309;
        border-color: #fde68a;
        background: #fffbeb;
    }

    .event-format-action-button.activate {
        color: #15803d;
        border-color: #bbf7d0;
        background: #f0fdf4;
    }

    .event-formats-empty {
        text-align: center;
        padding: 50px 20px;
        color: #64748b;
    }

    .event-formats-empty strong {
        display: block;
        margin-bottom: 6px;
        color: #334155;
        font-size: 16px;
    }

    .event-format-alert {
        margin-bottom: 20px;
        border-radius: 10px;
        padding: 13px 15px;
        font-size: 14px;
        line-height: 1.5;
    }

    .event-format-alert.success {
        background: #ecfdf3;
        border: 1px solid #bbf7d0;
        color: #166534;
    }

    .event-format-alert.error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    /*
    |--------------------------------------------------------------------------
    | Modal
    |--------------------------------------------------------------------------
    */

    .event-format-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 22px;
    }

    .event-format-modal.is-open {
        display: flex;
    }

    .event-format-modal-overlay {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.58);
    }

    .event-format-modal-box {
        position: relative;
        z-index: 2;
        width: 100%;
        max-width: 600px;
        max-height: calc(100vh - 44px);
        overflow-y: auto;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.25);
    }

    .event-format-modal-header {
        padding: 20px 22px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
    }

    .event-format-modal-header h2 {
        margin: 0;
        color: #172033;
        font-size: 20px;
    }

    .event-format-modal-close {
        width: 36px;
        height: 36px;
        border: 0;
        border-radius: 8px;
        background: #f1f5f9;
        color: #475569;
        font-size: 20px;
        cursor: pointer;
    }

    .event-format-modal-body {
        padding: 24px 22px 26px;
    }

    .event-format-form-group {
        margin-bottom: 18px;
    }

    .event-format-form-group:last-child {
        margin-bottom: 0;
    }

    .event-format-form-label {
        display: block;
        margin-bottom: 7px;
        color: #334155;
        font-size: 13px;
        font-weight: 750;
    }

    .event-format-required {
        color: #dc2626;
    }

    .event-format-input,
    .event-format-textarea {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #d1d5db;
        border-radius: 9px;
        background: #ffffff;
        color: #172033;
        padding: 11px 12px;
        font-family: inherit;
        font-size: 14px;
        outline: none;
    }

    .event-format-input {
        min-height: 44px;
    }

    .event-format-textarea {
        min-height: 105px;
        resize: vertical;
        line-height: 1.5;
    }

    .event-format-help {
        margin-top: 6px;
        color: #64748b;
        font-size: 12px;
        line-height: 1.5;
    }

    .event-format-modal-footer {
        margin-top: 24px;
        padding-top: 18px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .event-format-cancel-button,
    .event-format-save-button {
        min-height: 42px;
        border-radius: 9px;
        padding: 0 17px;
        font-size: 14px;
        font-weight: 750;
        cursor: pointer;
    }

    .event-format-cancel-button {
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #374151;
    }

    .event-format-save-button {
        border: 0;
        background: var(--primary-color, #2563eb);
        color: #ffffff;
    }

    @media (max-width: 850px) {
        .event-formats-page {
            padding: 22px 18px 40px;
        }

        .event-formats-header {
            flex-direction: column;
        }

        .event-format-add-button {
            width: 100%;
        }

        .event-format-stats {
            grid-template-columns: 1fr;
        }

        .event-formats-toolbar {
            align-items: stretch;
        }

        .event-format-search-form {
            flex-direction: column;
            align-items: stretch;
        }

        .event-format-search-input {
            max-width: none;
        }

        .event-format-search-button,
        .event-format-clear-button,
        .event-format-filter-select {
            width: 100%;
        }
    }

    @media (max-width: 520px) {
        .event-formats-page {
            padding: 18px 12px 32px;
        }

        .event-formats-header-content h1 {
            font-size: 25px;
        }

        .event-format-stat-card {
            padding: 17px;
        }

        .event-formats-toolbar {
            padding: 16px;
        }

        .event-format-modal {
            padding: 12px;
        }

        .event-format-modal-box {
            max-height: calc(100vh - 24px);
            border-radius: 13px;
        }

        .event-format-modal-header {
            padding: 17px;
        }

        .event-format-modal-body {
            padding: 20px 17px;
        }

        .event-format-modal-footer {
            flex-direction: column-reverse;
        }

        .event-format-cancel-button,
        .event-format-save-button {
            width: 100%;
        }
    }
</style>

<div class="event-formats-page">

    <div class="event-formats-header">
        <div class="event-formats-header-content">
            <h1>Tournament Formats</h1>
            <p>
                Create and manage the formats used for SportSync tournaments.
            </p>
        </div>

        <button
            type="button"
            class="event-format-add-button"
            id="openAddFormatButton"
        >
            <span>＋</span>
            <span>Add Tournament Format</span>
        </button>
    </div>

    <?php if ($successMessage !== ''): ?>
        <div class="event-format-alert success">
            <?= eventFormatEscape($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="event-format-alert error">
            <?= eventFormatEscape($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="event-format-stats">

        <div class="event-format-stat-card">
            <div class="event-format-stat-label">Total Formats</div>
            <div class="event-format-stat-value">
                <?= $totalFormats ?>
            </div>
        </div>

        <div class="event-format-stat-card">
            <div class="event-format-stat-label">Active Formats</div>
            <div class="event-format-stat-value">
                <?= $activeFormats ?>
            </div>
        </div>

        <div class="event-format-stat-card">
            <div class="event-format-stat-label">Inactive Formats</div>
            <div class="event-format-stat-value">
                <?= $inactiveFormats ?>
            </div>
        </div>

    </div>

    <section class="event-formats-card">

        <div class="event-formats-toolbar">

            <form
                method="GET"
                action="admin-event-formats.php"
                class="event-format-search-form"
            >
                <input
                    type="search"
                    name="search"
                    class="event-format-search-input"
                    placeholder="Search format name, code or description..."
                    value="<?= eventFormatEscape($search) ?>"
                >

                <select
                    name="status"
                    class="event-format-filter-select"
                >
                    <option value="">All Statuses</option>
                    <option
                        value="ACTIVE"
                        <?= $statusFilter === 'ACTIVE' ? 'selected' : '' ?>
                    >
                        Active
                    </option>
                    <option
                        value="INACTIVE"
                        <?= $statusFilter === 'INACTIVE' ? 'selected' : '' ?>
                    >
                        Inactive
                    </option>
                </select>

                <button
                    type="submit"
                    class="event-format-search-button"
                >
                    Search
                </button>

                <?php if ($search !== '' || $statusFilter !== ''): ?>
                    <a
                        href="admin-event-formats.php"
                        class="event-format-clear-button"
                    >
                        Clear
                    </a>
                <?php endif; ?>
            </form>

        </div>

        <div class="event-formats-table-wrapper">

            <?php if (!$formats): ?>

                <div class="event-formats-empty">
                    <strong>No tournament formats found</strong>
                    <span>
                        Try changing your search/filter or add a new tournament format.
                    </span>
                </div>

            <?php else: ?>

                <table class="event-formats-table">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Format Name</th>
                            <th>Format Code</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($formats as $format): ?>

                            <tr>

                                <td>
                                    #<?= (int) $format['event_format_id'] ?>
                                </td>

                                <td>
                                    <div class="event-format-name">
                                        <?= eventFormatEscape($format['format_name']) ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="event-format-code">
                                        <?= eventFormatEscape($format['format_code']) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="event-format-description">
                                        <?=
                                            $format['description'] !== null &&
                                            trim((string) $format['description']) !== ''
                                                ? eventFormatEscape($format['description'])
                                                : 'No description'
                                        ?>
                                    </div>
                                </td>

                                <td>
                                    <span
                                        class="event-format-status
                                            <?= $format['format_status'] === 'ACTIVE'
                                                ? 'active'
                                                : 'inactive'
                                            ?>"
                                    >
                                        <?= eventFormatEscape($format['format_status']) ?>
                                    </span>
                                </td>

                                <td>

                                    <div class="event-format-actions">

                                        <button
                                            type="button"
                                            class="event-format-action-button edit"
                                            data-action="edit"
                                            data-id="<?= (int) $format['event_format_id'] ?>"
                                            data-name="<?= eventFormatEscape($format['format_name']) ?>"
                                            data-code="<?= eventFormatEscape($format['format_code']) ?>"
                                            data-description="<?= eventFormatEscape((string) ($format['description'] ?? '')) ?>"
                                            data-status="<?= eventFormatEscape($format['format_status']) ?>"
                                        >
                                            Manage
                                        </button>

                                        <form
                                            method="POST"
                                            action="admin-event-formats.php"
                                            onsubmit="return confirm('Are you sure you want to change this tournament format status?');"
                                        >
                                            <?= csrfField() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="toggle_status"
                                            >

                                            <input
                                                type="hidden"
                                                name="event_format_id"
                                                value="<?= (int) $format['event_format_id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="event-format-action-button
                                                    <?= $format['format_status'] === 'ACTIVE'
                                                        ? 'deactivate'
                                                        : 'activate'
                                                    ?>"
                                            >
                                                <?= $format['format_status'] === 'ACTIVE'
                                                    ? 'Deactivate'
                                                    : 'Activate'
                                                ?>
                                            </button>
                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>

    </section>

</div>

<!-- Add Tournament Format Modal -->
<div
    class="event-format-modal"
    id="addFormatModal"
    aria-hidden="true"
>

    <div
        class="event-format-modal-overlay"
        data-close-modal="add"
    ></div>

    <div
        class="event-format-modal-box"
        role="dialog"
        aria-modal="true"
        aria-labelledby="addFormatTitle"
    >

        <div class="event-format-modal-header">
            <h2 id="addFormatTitle">Add Tournament Format</h2>

            <button
                type="button"
                class="event-format-modal-close"
                data-close-modal="add"
                aria-label="Close"
            >
                ×
            </button>
        </div>

        <div class="event-format-modal-body">

            <form
                method="POST"
                action="admin-event-formats.php"
            >

                <?= csrfField() ?>

                <input
                    type="hidden"
                    name="action"
                    value="add_format"
                >

                <div class="event-format-form-group">

                    <label
                        for="addFormatName"
                        class="event-format-form-label"
                    >
                        Format Name
                        <span class="event-format-required">*</span>
                    </label>

                    <input
                        type="text"
                        id="addFormatName"
                        name="format_name"
                        class="event-format-input"
                        maxlength="100"
                        placeholder="Example: League"
                        required
                    >

                </div>

                <div class="event-format-form-group">

                    <label
                        for="addFormatCode"
                        class="event-format-form-label"
                    >
                        Format Code
                        <span class="event-format-required">*</span>
                    </label>

                    <input
                        type="text"
                        id="addFormatCode"
                        name="format_code"
                        class="event-format-input"
                        maxlength="50"
                        placeholder="Example: LEAGUE"
                        required
                    >

                    <div class="event-format-help">
                        Use uppercase letters, numbers and underscores.
                        Example: LEAGUE_KNOCKOUT
                    </div>

                </div>

                <div class="event-format-form-group">

                    <label
                        for="addFormatDescription"
                        class="event-format-form-label"
                    >
                        Description
                    </label>

                    <textarea
                        id="addFormatDescription"
                        name="description"
                        class="event-format-textarea"
                        placeholder="Explain how this tournament format works..."
                    ></textarea>

                </div>

                <div class="event-format-form-group">

                    <label
                        for="addFormatStatus"
                        class="event-format-form-label"
                    >
                        Status
                    </label>

                    <select
                        id="addFormatStatus"
                        name="format_status"
                        class="event-format-input"
                    >
                        <option value="ACTIVE">Active</option>
                        <option value="INACTIVE">Inactive</option>
                    </select>

                </div>

                <div class="event-format-modal-footer">

                    <button
                        type="button"
                        class="event-format-cancel-button"
                        data-close-modal="add"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="event-format-save-button"
                    >
                        Add Tournament Format
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<!-- Manage Tournament Format Modal -->
<div
    class="event-format-modal"
    id="manageFormatModal"
    aria-hidden="true"
>

    <div
        class="event-format-modal-overlay"
        data-close-modal="manage"
    ></div>

    <div
        class="event-format-modal-box"
        role="dialog"
        aria-modal="true"
        aria-labelledby="manageFormatTitle"
    >

        <div class="event-format-modal-header">

            <h2 id="manageFormatTitle">
                Manage Tournament Format
            </h2>

            <button
                type="button"
                class="event-format-modal-close"
                data-close-modal="manage"
                aria-label="Close"
            >
                ×
            </button>

        </div>

        <div class="event-format-modal-body">

            <form
                method="POST"
                action="admin-event-formats.php"
            >

                <?= csrfField() ?>

                <input
                    type="hidden"
                    name="action"
                    value="update_format"
                >

                <input
                    type="hidden"
                    name="event_format_id"
                    id="manageFormatId"
                >

                <div class="event-format-form-group">

                    <label
                        for="manageFormatName"
                        class="event-format-form-label"
                    >
                        Format Name
                        <span class="event-format-required">*</span>
                    </label>

                    <input
                        type="text"
                        id="manageFormatName"
                        name="format_name"
                        class="event-format-input"
                        maxlength="100"
                        required
                    >

                </div>

                <div class="event-format-form-group">

                    <label
                        for="manageFormatCode"
                        class="event-format-form-label"
                    >
                        Format Code
                        <span class="event-format-required">*</span>
                    </label>

                    <input
                        type="text"
                        id="manageFormatCode"
                        name="format_code"
                        class="event-format-input"
                        maxlength="50"
                        required
                    >

                    <div class="event-format-help">
                        Use uppercase letters, numbers and underscores.
                    </div>

                </div>

                <div class="event-format-form-group">

                    <label
                        for="manageFormatDescription"
                        class="event-format-form-label"
                    >
                        Description
                    </label>

                    <textarea
                        id="manageFormatDescription"
                        name="description"
                        class="event-format-textarea"
                    ></textarea>

                </div>

                <div class="event-format-form-group">

                    <label
                        for="manageFormatStatus"
                        class="event-format-form-label"
                    >
                        Status
                    </label>

                    <select
                        id="manageFormatStatus"
                        name="format_status"
                        class="event-format-input"
                    >
                        <option value="ACTIVE">Active</option>
                        <option value="INACTIVE">Inactive</option>
                    </select>

                </div>

                <div class="event-format-modal-footer">

                    <button
                        type="button"
                        class="event-format-cancel-button"
                        data-close-modal="manage"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="event-format-save-button"
                    >
                        Save Changes
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const addModal = document.getElementById('addFormatModal');
    const manageModal = document.getElementById('manageFormatModal');

    const openAddButton = document.getElementById('openAddFormatButton');

    const manageId = document.getElementById('manageFormatId');
    const manageName = document.getElementById('manageFormatName');
    const manageCode = document.getElementById('manageFormatCode');
    const manageDescription = document.getElementById('manageFormatDescription');
    const manageStatus = document.getElementById('manageFormatStatus');

    function openModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        document.body.style.overflow = 'hidden';
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        if (
            !document.getElementById('addFormatModal').classList.contains('is-open') &&
            !document.getElementById('manageFormatModal').classList.contains('is-open')
        ) {
            document.body.style.overflow = '';
        }
    }

    if (openAddButton) {
        openAddButton.addEventListener('click', function () {
            openModal(addModal);

            const nameInput = document.getElementById('addFormatName');

            if (nameInput) {
                setTimeout(function () {
                    nameInput.focus();
                }, 100);
            }
        });
    }

    document.querySelectorAll('[data-action="edit"]').forEach(function (button) {

        button.addEventListener('click', function () {

            manageId.value = button.dataset.id || '';
            manageName.value = button.dataset.name || '';
            manageCode.value = button.dataset.code || '';
            manageDescription.value = button.dataset.description || '';
            manageStatus.value = button.dataset.status || 'ACTIVE';

            openModal(manageModal);

            setTimeout(function () {
                manageName.focus();
            }, 100);
        });

    });

    document.querySelectorAll('[data-close-modal]').forEach(function (button) {

        button.addEventListener('click', function () {

            const modalType = button.dataset.closeModal;

            if (modalType === 'add') {
                closeModal(addModal);
            }

            if (modalType === 'manage') {
                closeModal(manageModal);
            }
        });

    });

    document.addEventListener('keydown', function (event) {

        if (event.key !== 'Escape') {
            return;
        }

        closeModal(addModal);
        closeModal(manageModal);
    });

});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```
