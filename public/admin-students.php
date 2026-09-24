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

$pageTitle = 'Players';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">

    <div class="page-header">

        <div>
            <h1>Players</h1>

            <p>
                View all approved players registered in SportSync.
            </p>
        </div>

        <div class="page-header-actions">

            <span class="status-badge status-approved">
                <?= count($players) ?>
                Approved Player<?= count($players) === 1 ? '' : 's' ?>
            </span>

        </div>

    </div>

    <div class="card">

        <div class="card-header">

            <div>
                <h2>Approved Players</h2>

                <p>
                    Students whose accounts have been approved are shown here.
                </p>
            </div>

        </div>

        <?php if (empty($players)): ?>

            <div class="empty-state">

                <div class="empty-state-icon">
                    👤
                </div>

                <h3>No Approved Players</h3>

                <p>
                    There are currently no approved players in the system.
                </p>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="data-table">

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

                        if (count($nameParts) > 1) {
                            $initials .= strtoupper(
                                substr(
                                    $nameParts[count($nameParts) - 1],
                                    0,
                                    1
                                )
                            );
                        }

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

                        <tr>

                            <td>

                                <div class="player-cell">

                                    <div class="player-avatar">
                                        <?= htmlspecialchars(
                                            $initials ?: 'P'
                                        ) ?>
                                    </div>

                                    <div>

                                        <strong>
                                            <?= htmlspecialchars(
                                                $fullName
                                            ) ?>
                                        </strong>

                                        <small>
                                            Player
                                        </small>

                                    </div>

                                </div>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) $player['email']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) (
                                        $player['phone'] ?? '-'
                                    )
                                ) ?>
                            </td>

                            <td>

                                <span class="status-badge status-approved">
                                    APPROVED
                                </span>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $registeredDate
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<style>

.page-content {
    padding: 24px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.page-header h1 {
    margin: 0 0 6px;
}

.page-header p {
    margin: 0;
    color: #6b7280;
}

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

.card-header h2 {
    margin: 0 0 5px;
}

.card-header p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
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
    border-bottom: 1px solid #e5e7eb;
}

.data-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #eef2f7;
    font-size: 14px;
}

.data-table tbody tr:hover {
    background: #f8fbff;
}

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
    flex-shrink: 0;
}

.player-cell strong {
    display: block;
}

.player-cell small {
    display: block;
    margin-top: 3px;
    color: #64748b;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.status-approved {
    background: #e8f8ef;
    color: #15803d;
}

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
}

.empty-state p {
    margin: 0;
    color: #6b7280;
}

@media (max-width: 700px) {

    .page-content {
        padding: 16px;
    }

    .page-header h1 {
        font-size: 26px;
    }

}

</style>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>