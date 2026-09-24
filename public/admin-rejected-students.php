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
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        u.account_status,
        u.created_at,
        pp.student_id,
        pp.department,
        pp.course,
        pp.academic_year,
        pp.semester,
        pp.gender
    FROM users u
    LEFT JOIN player_profiles pp
        ON pp.user_id = u.user_id
    WHERE u.role_id = 4
      AND u.account_status = 'REJECTED'
    ORDER BY u.created_at DESC
");

$stmt->execute();

$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Rejected Players';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">

    <div class="page-header">

        <div>

            <h1>Rejected Players</h1>

            <p>
                View player registrations that were rejected by the administration.
            </p>

        </div>

        <div class="page-header-actions">

            <span class="status-badge status-rejected">

                <?= count($players) ?>

                Rejected Player<?= count($players) === 1 ? '' : 's' ?>

            </span>

        </div>

    </div>

    <div class="card">

        <div class="card-header">

            <div>

                <h2>Rejected Player Registrations</h2>

                <p>
                    Rejected player accounts are kept here for administrative records.
                </p>

            </div>

        </div>

        <?php if (empty($players)): ?>

            <div class="empty-state">

                <div class="empty-state-icon">
                    ✓
                </div>

                <h3>
                    No Rejected Players
                </h3>

                <p>
                    There are currently no rejected player registrations.
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
                                Student ID
                            </th>

                            <th>
                                Department
                            </th>

                            <th>
                                Course
                            </th>

                            <th>
                                Academic Year
                            </th>

                            <th>
                                Semester
                            </th>

                            <th>
                                Gender
                            </th>

                            <th>
                                Registered
                            </th>

                            <th>
                                Status
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

                                            <?= htmlspecialchars(
                                                (string) $player['email']
                                            ) ?>

                                        </small>

                                    </div>

                                </div>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    (string) (
                                        $player['student_id'] ?? '-'
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    (string) (
                                        $player['department'] ?? '-'
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    (string) (
                                        $player['course'] ?? '-'
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    (string) (
                                        $player['academic_year'] ?? '-'
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    (string) (
                                        $player['semester'] ?? '-'
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    (string) (
                                        $player['gender'] ?? '-'
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $registeredDate
                                ) ?>

                            </td>

                            <td>

                                <span class="status-badge status-rejected">

                                    REJECTED

                                </span>

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
    min-width: 1200px;
}

.data-table th {
    padding: 15px 20px;
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    text-align: left;
    text-transform: uppercase;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
}

.data-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #eef2f7;
    font-size: 14px;
}

.data-table tbody tr:hover {
    background: #fff8f8;
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
    background: #64748b;
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
    white-space: nowrap;
}

.status-rejected {
    background: #feecec;
    color: #c62828;
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
    background: #eef8f1;
    color: #15803d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 700;
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