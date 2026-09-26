<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SportSync - Pending Student Management
|--------------------------------------------------------------------------
| Shows student accounts waiting for approval.
| Accessible by:
| - ADMIN
| - SPORTS_COORDINATOR
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireLogin();

requireAnyRole([
    'ADMIN',
    'SPORTS_COORDINATOR'
]);

$pdo = db();

/*
|--------------------------------------------------------------------------
| Fetch Pending Students
|--------------------------------------------------------------------------
*/

$statement = $pdo->query(
    'SELECT
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
     INNER JOIN player_profiles pp
        ON pp.user_id = u.user_id
     WHERE u.role_id = 4
       AND u.account_status = "PENDING"
     ORDER BY u.created_at DESC'
);

$students = $statement->fetchAll(PDO::FETCH_ASSOC);

$pendingCount = count($students);

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

$pageTitle = 'Pending Students';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
    /*
    |--------------------------------------------------------------------------
    | Pending Students Page
    |--------------------------------------------------------------------------
    */

    .student-management-page {
        width: 100%;
    }

    .student-management-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 24px;
    }

    .student-management-heading {
        min-width: 0;
    }

    .student-management-heading h1 {
        margin: 0;
        color: var(--text-primary, #172033);
        font-size: 28px;
        font-weight: 800;
        letter-spacing: -0.5px;
        line-height: 1.2;
    }

    .student-management-heading p {
        margin: 8px 0 0;
        max-width: 720px;
        color: var(--text-secondary, #667085);
        font-size: 14px;
        line-height: 1.6;
    }

    .student-pending-summary {
        flex: 0 0 auto;
        min-width: 150px;
        padding: 16px 20px;
        border: 1px solid #f2d48b;
        border-radius: 14px;
        background: #fff8e7;
        text-align: center;
    }

    .student-pending-summary strong {
        display: block;
        color: #946200;
        font-size: 24px;
        font-weight: 800;
        line-height: 1.2;
    }

    .student-pending-summary span {
        display: block;
        margin-top: 4px;
        color: #7a5a12;
        font-size: 11px;
        font-weight: 700;
    }

    /*
    |--------------------------------------------------------------------------
    | Breadcrumb
    |--------------------------------------------------------------------------
    */

    .student-breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 18px;
        color: var(--text-muted, #98a2b3);
        font-size: 13px;
    }

    .student-breadcrumb a {
        color: var(--primary, #2563eb);
        font-weight: 650;
        text-decoration: none;
    }

    .student-breadcrumb a:hover {
        text-decoration: underline;
    }

    /*
    |--------------------------------------------------------------------------
    | Main Card
    |--------------------------------------------------------------------------
    */

    .student-management-card {
        overflow: hidden;
        background: #ffffff;
        border: 1px solid var(--border-color, #e4e9f2);
        border-radius: 16px;
        box-shadow: 0 8px 28px rgba(15, 23, 42, 0.05);
    }

    .student-management-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 20px 22px;
        border-bottom: 1px solid #eaecf0;
    }

    .student-management-card-title h2 {
        margin: 0;
        color: #101828;
        font-size: 17px;
        font-weight: 750;
    }

    .student-management-card-title p {
        margin: 5px 0 0;
        color: #667085;
        font-size: 12px;
        line-height: 1.5;
    }

    .student-count-label {
        flex: 0 0 auto;
        padding: 7px 11px;
        border-radius: 999px;
        background: #eef4ff;
        color: #155eef;
        font-size: 11px;
        font-weight: 750;
        white-space: nowrap;
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    .student-table-wrapper {
        width: 100%;
        overflow-x: auto;
    }

    .student-table {
        width: 100%;
        min-width: 1120px;
        border-collapse: collapse;
    }

    .student-table th {
        padding: 13px 16px;
        background: #f8fafc;
        border-bottom: 1px solid #eaecf0;
        color: #667085;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.45px;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .student-table td {
        padding: 16px;
        border-bottom: 1px solid #f0f2f5;
        color: #344054;
        font-size: 13px;
        vertical-align: middle;
    }

    .student-table tbody tr {
        transition: background 0.18s ease;
    }

    .student-table tbody tr:hover {
        background: #fafcff;
    }

    .student-table tbody tr:last-child td {
        border-bottom: none;
    }

    /*
    |--------------------------------------------------------------------------
    | Student Information
    |--------------------------------------------------------------------------
    */

    .student-primary-info {
        min-width: 190px;
    }

    .student-name {
        color: #101828;
        font-size: 13px;
        font-weight: 750;
        line-height: 1.4;
    }

    .student-email {
        margin-top: 4px;
        color: #667085;
        font-size: 11px;
        line-height: 1.4;
    }

    .student-phone {
        margin-top: 3px;
        color: #98a2b3;
        font-size: 11px;
    }

    .student-id {
        display: inline-flex;
        align-items: center;
        padding: 5px 9px;
        border-radius: 7px;
        background: #eef4ff;
        color: #155eef;
        font-size: 11px;
        font-weight: 750;
        white-space: nowrap;
    }

    .student-secondary {
        color: #475467;
        line-height: 1.4;
    }

    .student-muted {
        color: #98a2b3;
    }

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    .student-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 9px;
        border-radius: 999px;
        background: #fff8e7;
        color: #946200;
        font-size: 11px;
        font-weight: 750;
        white-space: nowrap;
    }

    .student-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #e9a400;
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    .student-actions {
        display: flex;
        align-items: center;
        gap: 7px;
        white-space: nowrap;
    }

    .student-action-form {
        display: inline-flex;
        margin: 0;
    }

    .student-action-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 76px;
        height: 34px;
        padding: 0 11px;
        border: 1px solid transparent;
        border-radius: 8px;
        font-family: inherit;
        font-size: 11px;
        font-weight: 750;
        cursor: pointer;
        transition:
            background 0.18s ease,
            border-color 0.18s ease,
            transform 0.18s ease,
            box-shadow 0.18s ease;
    }

    .student-action-button:hover {
        transform: translateY(-1px);
    }

    .student-action-button:focus-visible {
        outline: 3px solid rgba(37, 99, 235, 0.18);
        outline-offset: 2px;
    }

    .student-approve-button {
        background: #e8f7ee;
        border-color: #b8e4c8;
        color: #16743a;
    }

    .student-approve-button:hover {
        background: #d9f2e2;
        box-shadow: 0 4px 12px rgba(22, 116, 58, 0.10);
    }

    .student-reject-button {
        background: #fff0ef;
        border-color: #f2c4c0;
        color: #b42318;
    }

    .student-reject-button:hover {
        background: #ffe4e1;
        box-shadow: 0 4px 12px rgba(180, 35, 24, 0.10);
    }

    /*
    |--------------------------------------------------------------------------
    | Empty State
    |--------------------------------------------------------------------------
    */

    .student-empty-state {
        padding: 70px 25px;
        text-align: center;
    }

    .student-empty-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 68px;
        height: 68px;
        margin: 0 auto 17px;
        border-radius: 50%;
        background: #eef4ff;
        color: #155eef;
        font-size: 27px;
        font-weight: 800;
    }

    .student-empty-state h3 {
        margin: 0;
        color: #101828;
        font-size: 19px;
        font-weight: 750;
    }

    .student-empty-state p {
        max-width: 470px;
        margin: 8px auto 0;
        color: #667085;
        font-size: 13px;
        line-height: 1.65;
    }

    /*
    |--------------------------------------------------------------------------
    | Loading State
    |--------------------------------------------------------------------------
    */

    .student-action-button.is-processing {
        pointer-events: none;
        opacity: 0.65;
    }

    /*
    |--------------------------------------------------------------------------
    | Responsive
    |--------------------------------------------------------------------------
    */

    @media (max-width: 900px) {
        .student-management-header {
            flex-direction: column;
        }

        .student-pending-summary {
            width: 100%;
        }
    }

    @media (max-width: 600px) {
        .student-management-heading h1 {
            font-size: 24px;
        }

        .student-management-card-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .student-count-label {
            align-self: flex-start;
        }
    }
</style>

<div class="student-management-page">

    <!-- Breadcrumb -->
    <div class="student-breadcrumb">
        <a href="admin-dashboard.php">
            Dashboard
        </a>

        <span>›</span>

        <span>
            Student Management
        </span>

        <span>›</span>

        <span>
            Pending Students
        </span>
    </div>

    <!-- Page Header -->
    <div class="student-management-header">

        <div class="student-management-heading">

            <h1>
                Pending Student Registrations
            </h1>

            <p>
                Review student registrations waiting for approval.
                Approve valid accounts to give students access to SportSync,
                or reject registrations that should not receive access.
            </p>

        </div>

        <div class="student-pending-summary">

            <strong>
                <?= $pendingCount ?>
            </strong>

            <span>
                Pending Registration<?= $pendingCount === 1 ? '' : 's' ?>
            </span>

        </div>

    </div>

    <!-- Students Card -->
    <section class="student-management-card">

        <div class="student-management-card-header">

            <div class="student-management-card-title">

                <h2>
                    Students Awaiting Approval
                </h2>

                <p>
                    Review the submitted student information before making
                    an approval decision.
                </p>

            </div>

            <div class="student-count-label">
                <?= $pendingCount ?> Pending
            </div>

        </div>

        <?php if (empty($students)): ?>

            <!-- Empty State -->
            <div class="student-empty-state">

                <div class="student-empty-icon">
                    ✓
                </div>

                <h3>
                    No Pending Registrations
                </h3>

                <p>
                    There are currently no student accounts waiting
                    for approval. New student registrations will
                    automatically appear here.
                </p>

            </div>

        <?php else: ?>

            <!-- Student Table -->
            <div class="student-table-wrapper">

                <table class="student-table">

                    <thead>

                        <tr>

                            <th>
                                Student
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

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($students as $student): ?>

                        <tr>

                            <!-- Student -->
                            <td>

                                <div class="student-primary-info">

                                    <div class="student-name">
                                        <?= e($student['full_name']) ?>
                                    </div>

                                    <div class="student-email">
                                        <?= e($student['email']) ?>
                                    </div>

                                    <?php if (!empty($student['phone'])): ?>

                                        <div class="student-phone">
                                            <?= e($student['phone']) ?>
                                        </div>

                                    <?php endif; ?>

                                </div>

                            </td>

                            <!-- Student ID -->
                            <td>

                                <?php if (!empty($student['student_id'])): ?>

                                    <span class="student-id">
                                        <?= e($student['student_id']) ?>
                                    </span>

                                <?php else: ?>

                                    <span class="student-muted">
                                        —
                                    </span>

                                <?php endif; ?>

                            </td>

                            <!-- Department -->
                            <td>
                                <span class="student-secondary">
                                    <?= e($student['department']) ?: '—' ?>
                                </span>
                            </td>

                            <!-- Course -->
                            <td>
                                <span class="student-secondary">
                                    <?= e($student['course']) ?: '—' ?>
                                </span>
                            </td>

                            <!-- Academic Year -->
                            <td>
                                <span class="student-secondary">
                                    <?= e($student['academic_year']) ?: '—' ?>
                                </span>
                            </td>

                            <!-- Semester -->
                            <td>

                                <?php if ($student['semester'] !== null): ?>

                                    <span class="student-secondary">
                                        <?= e((string) $student['semester']) ?>
                                    </span>

                                <?php else: ?>

                                    <span class="student-muted">
                                        —
                                    </span>

                                <?php endif; ?>

                            </td>

                            <!-- Gender -->
                            <td>
                                <span class="student-secondary">
                                    <?= e($student['gender']) ?: '—' ?>
                                </span>
                            </td>

                            <!-- Registered -->
                            <td>
                                <span class="student-secondary">
                                    <?= e($student['created_at']) ?>
                                </span>
                            </td>

                            <!-- Status -->
                            <td>

                                <span class="student-status">

                                    <span class="student-status-dot"></span>

                                    Pending

                                </span>

                            </td>

                            <!-- Actions -->
                            <td>

                                <div class="student-actions">

                                    <!-- Approve -->
                                    <form
                                        method="POST"
                                        action="approve-student.php"
                                        class="student-action-form"
                                    >

                                        <?= csrfField() ?>

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int) $student['user_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="student-action-button student-approve-button"
                                            data-action="approve"
                                            data-student-name="<?= e($student['full_name']) ?>"
                                        >
                                            Approve
                                        </button>

                                    </form>

                                    <!-- Reject -->
                                    <form
                                        method="POST"
                                        action="reject-student.php"
                                        class="student-action-form"
                                    >

                                        <?= csrfField() ?>

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int) $student['user_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="student-action-button student-reject-button"
                                            data-action="reject"
                                            data-student-name="<?= e($student['full_name']) ?>"
                                        >
                                            Reject
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const actionButtons = document.querySelectorAll(
            '.student-action-button'
        );

        actionButtons.forEach(function (button) {

            button.addEventListener('click', function (event) {

                const action = button.dataset.action || '';
                const studentName = button.dataset.studentName || 'this student';

                let message = '';

                if (action === 'approve') {
                    message =
                        'Are you sure you want to approve ' +
                        studentName +
                        '? This will allow the student to access SportSync.';
                }

                if (action === 'reject') {
                    message =
                        'Are you sure you want to reject ' +
                        studentName +
                        '\'s registration?';
                }

                if (message && !window.confirm(message)) {
                    event.preventDefault();
                    return;
                }

                button.classList.add('is-processing');

                button.textContent =
                    action === 'approve'
                        ? 'Approving...'
                        : 'Rejecting...';

            });

        });

    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>