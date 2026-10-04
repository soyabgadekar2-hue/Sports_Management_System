<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';

requireLogin();

$user = currentUser();
$role = $user['role'] ?? '';

if ($role !== 'ADMIN' && $role !== 'SPORTS_COORDINATOR') {
    http_response_code(403);
    exit('Access denied');
}

$db = db();

/*
|--------------------------------------------------------------------------
| Load approved players
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("
    SELECT
        user_id,
        full_name,
        email,
        phone,
        account_status,
        created_at
    FROM users
    WHERE role_id = 4
      AND account_status = 'APPROVED'
    ORDER BY full_name ASC
");

$stmt->execute();

$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/
function adminStudentsEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$pageTitle = 'Players';

require_once __DIR__ . '/../includes/header.php';

?>

<div class="page-content">

    <!-- ================================================================
         PAGE HEADER
    ================================================================= -->
    <div class="page-header">

        <div class="page-header-main">

            <div class="page-icon">
                👥
            </div>

            <div>
                <h1>Players</h1>

                <p>
                    View students whose SportSync accounts have been approved
                    and are ready to participate in sports.
                </p>
            </div>

        </div>

        <div class="page-header-actions">

            <span class="status-badge status-approved">
                <?= count($players) ?>
                Approved Player<?= count($players) === 1 ? '' : 's' ?>
            </span>

            <?php if ($role === 'ADMIN'): ?>

                <a
                    href="admin-pending-students.php"
                    class="secondary-button"
                >
                    Review Pending Students
                </a>

            <?php endif; ?>

        </div>

    </div>


    <!-- ================================================================
         SIMPLE WORKFLOW
    ================================================================= -->
    <div class="workflow-card">

        <div class="workflow-title">
            <span class="workflow-icon">📋</span>

            <div>
                <strong>Player Account Flow</strong>

                <p>
                    New student → Admin review → Approved → Player can
                    participate in SportSync
                </p>
            </div>
        </div>

    </div>


    <!-- ================================================================
         PLAYERS CARD
    ================================================================= -->
    <div class="card">

        <div class="card-header">

            <div class="card-header-content">

                <div>
                    <h2>Approved Players</h2>

                    <p>
                        These students have approved accounts and can
                        participate in SportSync activities.
                    </p>
                </div>

                <?php if (!empty($players)): ?>

                    <div class="search-box">

                        <span class="search-icon">🔎</span>

                        <input
                            type="search"
                            id="playerSearch"
                            placeholder="Search players..."
                            autocomplete="off"
                        >

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <?php if (empty($players)): ?>

            <!-- ========================================================
                 EMPTY STATE
            ========================================================= -->
            <div class="empty-state">

                <div class="empty-state-icon">
                    👤
                </div>

                <h3>No Approved Players</h3>

                <p>
                    There are currently no approved player accounts
                    in the system.
                </p>

                <?php if ($role === 'ADMIN'): ?>

                    <a
                        href="admin-pending-students.php"
                        class="primary-button"
                    >
                        Check Pending Students
                    </a>

                <?php endif; ?>

            </div>

        <?php else: ?>

            <!-- ========================================================
                 PLAYERS TABLE
            ========================================================= -->
            <div class="table-responsive">

                <table
                    class="data-table"
                    id="playersTable"
                >

                    <thead>

                        <tr>

                            <th>
                                Player
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Phone
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Registered
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($players as $player): ?>

                        <?php

                        $fullName = trim(
                            (string) $player['full_name']
                        );

                        /*
                         * Create initials from first and last name.
                         */
                        $nameParts = preg_split(
                            '/\s+/',
                            $fullName
                        );

                        $initials = '';

                        if (!empty($nameParts[0])) {

                            $initials .= strtoupper(
                                substr(
                                    $nameParts[0],
                                    0,
                                    1
                                )
                            );
                        }

                        if (
                            is_array($nameParts)
                            && count($nameParts) > 1
                        ) {

                            $lastPart = $nameParts[
                                count($nameParts) - 1
                            ];

                            $initials .= strtoupper(
                                substr(
                                    $lastPart,
                                    0,
                                    1
                                )
                            );
                        }

                        /*
                         * Registered date.
                         */
                        $registeredDate = '-';

                        if (!empty($player['created_at'])) {

                            $timestamp = strtotime(
                                (string) $player['created_at']
                            );

                            if ($timestamp !== false) {

                                $registeredDate = date(
                                    'd M Y',
                                    $timestamp
                                );
                            }
                        }

                        ?>

                        <tr class="player-row">

                            <!-- Player -->
                            <td>

                                <div class="player-cell">

                                    <div class="player-avatar">
                                        <?= adminStudentsEscape(
                                            $initials ?: 'P'
                                        ) ?>
                                    </div>

                                    <div class="player-information">

                                        <strong>
                                            <?= adminStudentsEscape(
                                                $fullName
                                            ) ?>
                                        </strong>

                                        <small>
                                            Player Account
                                        </small>

                                    </div>

                                </div>

                            </td>


                            <!-- Email -->
                            <td>

                                <span class="contact-value">
                                    <?= adminStudentsEscape(
                                        $player['email']
                                    ) ?>
                                </span>

                            </td>


                            <!-- Phone -->
                            <td>

                                <span class="contact-value">
                                    <?= adminStudentsEscape(
                                        $player['phone'] ?? '-'
                                    ) ?>
                                </span>

                            </td>


                            <!-- Status -->
                            <td>

                                <span class="status-badge status-approved">
                                    <span class="status-dot"></span>
                                    APPROVED
                                </span>

                            </td>


                            <!-- Registered -->
                            <td>

                                <span class="date-value">
                                    <?= adminStudentsEscape(
                                        $registeredDate
                                    ) ?>
                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <!-- Search result message -->
            <div
                id="noSearchResults"
                class="no-search-results"
                hidden
            >
                <div class="no-search-icon">
                    🔎
                </div>

                <strong>
                    No players found
                </strong>

                <p>
                    Try searching with a different name, email or phone
                    number.
                </p>
            </div>

        <?php endif; ?>

    </div>

</div>


<style>

/* ================================================================
   PAGE
================================================================ */

.page-content {
    padding: 24px;
    max-width: 1500px;
    margin: 0 auto;
}


/* ================================================================
   PAGE HEADER
================================================================ */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.page-header-main {
    display: flex;
    align-items: center;
    gap: 14px;
}

.page-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: #eef4ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
    flex-shrink: 0;
}

.page-header h1 {
    margin: 0 0 6px;
    font-size: 30px;
}

.page-header p {
    margin: 0;
    color: #6b7280;
    line-height: 1.5;
}

.page-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}


/* ================================================================
   BUTTONS
================================================================ */

.primary-button,
.secondary-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 10px 16px;
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
    background: #175cff;
    color: #ffffff;
    border: 1px solid #175cff;
}

.primary-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(23, 92, 255, 0.18);
}

.secondary-button {
    background: #ffffff;
    color: #175cff;
    border: 1px solid #dbe5ff;
}

.secondary-button:hover {
    background: #f5f8ff;
}


/* ================================================================
   WORKFLOW CARD
================================================================ */

.workflow-card {
    background: #f8fbff;
    border: 1px solid #dce8ff;
    border-radius: 14px;
    padding: 16px 18px;
    margin-bottom: 20px;
}

.workflow-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.workflow-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.workflow-title strong {
    display: block;
    margin-bottom: 3px;
    color: #172033;
}

.workflow-title p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}


/* ================================================================
   CARD
================================================================ */

.card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 6px 24px rgba(15, 23, 42, 0.06);
}

.card-header {
    padding: 22px 24px;
    border-bottom: 1px solid #e5e7eb;
}

.card-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
}

.card-header h2 {
    margin: 0 0 5px;
    font-size: 20px;
}

.card-header p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
    line-height: 1.5;
}


/* ================================================================
   SEARCH
================================================================ */

.search-box {
    width: min(320px, 100%);
    position: relative;
}

.search-box input {
    width: 100%;
    height: 42px;
    box-sizing: border-box;
    padding: 0 14px 0 40px;
    border: 1px solid #dbe2ea;
    border-radius: 10px;
    outline: none;
    font-size: 14px;
    color: #172033;
    background: #ffffff;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.search-box input:focus {
    border-color: #175cff;
    box-shadow: 0 0 0 3px rgba(23, 92, 255, 0.10);
}

.search-icon {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 15px;
    pointer-events: none;
}


/* ================================================================
   STATUS
================================================================ */

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.status-approved {
    background: #e8f8ef;
    color: #15803d;
}

.status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
}


/* ================================================================
   TABLE
================================================================ */

.table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

.data-table th {
    padding: 15px 20px;
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
}

.data-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #eef2f7;
    font-size: 14px;
    color: #334155;
}

.data-table tbody tr {
    transition: background 0.18s ease;
}

.data-table tbody tr:hover {
    background: #f8fbff;
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}


/* ================================================================
   PLAYER CELL
================================================================ */

.player-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.player-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #175cff;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}

.player-information strong {
    display: block;
    color: #172033;
    font-size: 14px;
}

.player-information small {
    display: block;
    margin-top: 3px;
    color: #64748b;
    font-size: 12px;
}

.contact-value {
    word-break: break-word;
}

.date-value {
    color: #475569;
    white-space: nowrap;
}


/* ================================================================
   EMPTY STATE
================================================================ */

.empty-state {
    padding: 70px 20px;
    text-align: center;
}

.empty-state-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 15px;
    border-radius: 50%;
    background: #eef4ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.empty-state h3 {
    margin: 0 0 8px;
    color: #172033;
}

.empty-state p {
    margin: 0 auto 20px;
    max-width: 450px;
    color: #6b7280;
    line-height: 1.6;
}


/* ================================================================
   NO SEARCH RESULTS
================================================================ */

.no-search-results {
    padding: 50px 20px;
    text-align: center;
}

.no-search-icon {
    width: 54px;
    height: 54px;
    margin: 0 auto 12px;
    border-radius: 50%;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 23px;
}

.no-search-results strong {
    display: block;
    margin-bottom: 5px;
    color: #172033;
}

.no-search-results p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}


/* ================================================================
   MOBILE
================================================================ */

@media (max-width: 700px) {

    .page-content {
        padding: 16px;
    }

    .page-header {
        align-items: flex-start;
    }

    .page-header-main {
        align-items: flex-start;
    }

    .page-icon {
        width: 46px;
        height: 46px;
        font-size: 22px;
    }

    .page-header h1 {
        font-size: 26px;
    }

    .page-header-actions {
        width: 100%;
    }

    .page-header-actions .status-badge {
        width: fit-content;
    }

    .secondary-button {
        width: 100%;
    }

    .workflow-card {
        padding: 14px;
    }

    .workflow-title {
        align-items: flex-start;
    }

    .card-header {
        padding: 18px 16px;
    }

    .card-header-content {
        align-items: stretch;
    }

    .search-box {
        width: 100%;
    }

    .data-table th,
    .data-table td {
        padding-left: 14px;
        padding-right: 14px;
    }

    .empty-state {
        padding: 55px 18px;
    }
}

</style>


<script>

/*
|--------------------------------------------------------------------------
| Player Search
|--------------------------------------------------------------------------
|
| This search works on the already loaded approved-player table.
| It does not require a database query for every keystroke.
|
*/

document.addEventListener('DOMContentLoaded', function () {

    const searchInput =
        document.getElementById('playerSearch');

    const table =
        document.getElementById('playersTable');

    const noResults =
        document.getElementById('noSearchResults');

    if (!searchInput || !table) {
        return;
    }

    const rows =
        table.querySelectorAll(
            'tbody .player-row'
        );

    searchInput.addEventListener(
        'input',
        function () {

            const searchTerm =
                searchInput.value
                    .trim()
                    .toLowerCase();

            let visibleRows = 0;

            rows.forEach(function (row) {

                const rowText =
                    row.textContent
                        .toLowerCase();

                const matches =
                    rowText.includes(searchTerm);

                row.style.display =
                    matches ? '' : 'none';

                if (matches) {
                    visibleRows++;
                }

            });

            if (noResults) {

                noResults.hidden =
                    visibleRows !== 0;

            }

        }
    );

});

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>