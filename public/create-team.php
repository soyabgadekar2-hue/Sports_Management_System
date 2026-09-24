<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

/*
|--------------------------------------------------------------------------
| Get active sports
|--------------------------------------------------------------------------
*/
$sportStatement = $pdo->prepare(
    'SELECT
        sport_id,
        sport_name,
        default_max_team_players
     FROM sports
     WHERE sport_status = :status
     ORDER BY sport_name ASC'
);

$sportStatement->execute([
    ':status' => 'ACTIVE'
]);

$sports = $sportStatement->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Get active coaches
|--------------------------------------------------------------------------
*/
$coachStatement = $pdo->prepare(
    'SELECT
        cp.coach_id,
        u.full_name,
        cp.employee_id,
        cp.designation,
        cp.specialization
     FROM coach_profiles cp
     INNER JOIN users u
        ON u.user_id = cp.user_id
     WHERE cp.coach_status = :coach_status
       AND u.account_status = :account_status
     ORDER BY u.full_name ASC'
);

$coachStatement->execute([
    ':coach_status' => 'ACTIVE',
    ':account_status' => 'APPROVED'
]);

$coaches = $coachStatement->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Common Header
|--------------------------------------------------------------------------
*/
$pageTitle = 'Create Team';

require_once __DIR__ . '/../includes/header.php';

?>

<style>

    /* =========================================================
       CREATE TEAM PAGE
       ========================================================= */

    .create-team-page {
        max-width: 1100px;
        margin: 0 auto;
    }


    /* =========================================================
       PAGE HEADER
       ========================================================= */

    .create-team-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }

    .create-team-header-content {
        flex: 1;
    }

    .create-team-label {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 999px;
        background: #eff6ff;
        color: #2563eb;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        margin-bottom: 10px;
    }

    .create-team-header h1 {
        margin: 0 0 8px;
        font-size: 30px;
        line-height: 1.2;
        color: #111827;
    }

    .create-team-header p {
        margin: 0;
        color: #6b7280;
        font-size: 15px;
    }

    .create-team-header-icon {
        width: 64px;
        height: 64px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 18px;
        background: #eff6ff;
        font-size: 30px;
        flex-shrink: 0;
    }


    /* =========================================================
       BACK BUTTON
       ========================================================= */

    .create-team-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
        color: #2563eb;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
    }

    .create-team-back:hover {
        color: #1d4ed8;
    }


    /* =========================================================
       FORM CARD
       ========================================================= */

    .create-team-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 28px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
    }

    .create-team-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding-bottom: 20px;
        margin-bottom: 24px;
        border-bottom: 1px solid #e5e7eb;
    }

    .create-team-card-header h2 {
        margin: 0 0 5px;
        color: #111827;
        font-size: 20px;
    }

    .create-team-card-header p {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
    }

    .create-team-card-icon {
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


    /* =========================================================
       FORM GRID
       ========================================================= */

    .team-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 22px;
    }

    .team-form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .team-form-group.full-width {
        grid-column: 1 / -1;
    }

    .team-form-group label {
        color: #111827;
        font-size: 14px;
        font-weight: 600;
    }

    .required-mark {
        color: #dc2626;
    }

    .team-form-group input,
    .team-form-group select {
        width: 100%;
        min-height: 46px;
        padding: 11px 13px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        background: #ffffff;
        color: #111827;
        font-size: 14px;
        outline: none;
        box-sizing: border-box;
        transition: 0.2s ease;
    }

    .team-form-group input:focus,
    .team-form-group select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .team-form-help {
        margin: 0;
        color: #6b7280;
        font-size: 12px;
        line-height: 1.5;
    }


    /* =========================================================
       CATEGORY OPTIONS
       ========================================================= */

    .category-options {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .category-option {
        position: relative;
    }

    .category-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .category-option label {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        background: #ffffff;
        color: #374151;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: 0.2s ease;
    }

    .category-option label:hover {
        border-color: #2563eb;
    }

    .category-option input:checked + label {
        border-color: #2563eb;
        background: #eff6ff;
        color: #2563eb;
    }


    /* =========================================================
       FORM ACTIONS
       ========================================================= */

    .team-form-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 28px;
        padding-top: 22px;
        border-top: 1px solid #e5e7eb;
    }

    .team-cancel-button,
    .team-submit-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 10px 20px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s ease;
        box-sizing: border-box;
    }

    .team-cancel-button {
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #374151;
    }

    .team-cancel-button:hover {
        background: #f9fafb;
    }

    .team-submit-button {
        border: 1px solid #2563eb;
        background: #2563eb;
        color: #ffffff;
    }

    .team-submit-button:hover {
        background: #1d4ed8;
        border-color: #1d4ed8;
        transform: translateY(-1px);
    }


    /* =========================================================
       INFO BOX
       ========================================================= */

    .create-team-info {
        display: flex;
        gap: 12px;
        margin-top: 20px;
        padding: 15px 16px;
        border: 1px solid #dbeafe;
        border-radius: 12px;
        background: #eff6ff;
    }

    .create-team-info-icon {
        font-size: 18px;
        flex-shrink: 0;
    }

    .create-team-info p {
        margin: 0;
        color: #1e40af;
        font-size: 13px;
        line-height: 1.6;
    }


    /* =========================================================
       RESPONSIVE
       ========================================================= */

    @media (max-width: 800px) {

        .team-form-grid {
            grid-template-columns: 1fr;
        }

        .team-form-group.full-width {
            grid-column: auto;
        }

        .category-options {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

    }


    @media (max-width: 600px) {

        .create-team-card {
            padding: 20px;
        }

        .create-team-header h1 {
            font-size: 25px;
        }

        .create-team-header-icon {
            width: 52px;
            height: 52px;
            font-size: 25px;
        }

        .team-form-actions {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .team-cancel-button,
        .team-submit-button {
            width: 100%;
        }

    }

</style>


<div class="dashboard-page">

    <div class="create-team-page">


        <!-- =====================================================
             BACK
             ===================================================== -->

        <a
            href="admin-teams.php"
            class="create-team-back"
        >
            ← Back to Team Management
        </a>


        <!-- =====================================================
             PAGE HEADER
             ===================================================== -->

        <section class="create-team-header">

            <div class="create-team-header-content">

                <div class="create-team-label">
                    TEAM MANAGEMENT
                </div>

                <h1>
                    Create New Team
                </h1>

                <p>
                    Create a team, select its sport, assign a coach,
                    and configure the roster limit.
                </p>

            </div>


            <div class="create-team-header-icon">
                👥
            </div>

        </section>


        <!-- =====================================================
             FORM CARD
             ===================================================== -->

        <section class="create-team-card">

            <div class="create-team-card-header">

                <div>

                    <h2>
                        Team Information
                    </h2>

                    <p>
                        Enter the basic information for the new team.
                    </p>

                </div>

                <div class="create-team-card-icon">
                    ⚙️
                </div>

            </div>


            <form
                action="create-team-process.php"
                method="POST"
            >

                <?= csrfField() ?>


                <div class="team-form-grid">


                    <!-- =================================================
                         SPORT
                         ================================================= -->

                    <div class="team-form-group">

                        <label for="sport_id">
                            Sport
                            <span class="required-mark">*</span>
                        </label>

                        <select
                            name="sport_id"
                            id="sport_id"
                            required
                        >

                            <option value="">
                                -- Select Sport --
                            </option>

                            <?php foreach ($sports as $sport): ?>

                                <option
                                    value="<?= (int) $sport['sport_id'] ?>"
                                >

                                    <?= htmlspecialchars(
                                        (string) $sport['sport_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <p class="team-form-help">
                            Select the sport for which this team will be created.
                        </p>

                    </div>


                    <!-- =================================================
                         TEAM NAME
                         ================================================= -->

                    <div class="team-form-group">

                        <label for="team_name">
                            Team Name
                            <span class="required-mark">*</span>
                        </label>

                        <input
                            type="text"
                            name="team_name"
                            id="team_name"
                            maxlength="120"
                            placeholder="Enter team name"
                            required
                        >

                        <p class="team-form-help">
                            Use a clear and unique team name.
                        </p>

                    </div>


                    <!-- =================================================
                         TEAM CATEGORY
                         ================================================= -->

                    <div class="team-form-group full-width">

                        <label>
                            Team Category
                            <span class="required-mark">*</span>
                        </label>

                        <div class="category-options">


                            <div class="category-option">

                                <input
                                    type="radio"
                                    name="team_category"
                                    id="category_open"
                                    value="OPEN"
                                    checked
                                >

                                <label for="category_open">
                                    OPEN
                                </label>

                            </div>


                            <div class="category-option">

                                <input
                                    type="radio"
                                    name="team_category"
                                    id="category_men"
                                    value="MEN"
                                >

                                <label for="category_men">
                                    MEN
                                </label>

                            </div>


                            <div class="category-option">

                                <input
                                    type="radio"
                                    name="team_category"
                                    id="category_women"
                                    value="WOMEN"
                                >

                                <label for="category_women">
                                    WOMEN
                                </label>

                            </div>


                            <div class="category-option">

                                <input
                                    type="radio"
                                    name="team_category"
                                    id="category_mixed"
                                    value="MIXED"
                                >

                                <label for="category_mixed">
                                    MIXED
                                </label>

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         COACH
                         ================================================= -->

                    <div class="team-form-group">

                        <label for="coach_id">
                            Coach
                        </label>

                        <select
                            name="coach_id"
                            id="coach_id"
                        >

                            <option value="">
                                -- No Coach Assigned --
                            </option>

                            <?php foreach ($coaches as $coach): ?>

                                <option
                                    value="<?= (int) $coach['coach_id'] ?>"
                                >

                                    <?= htmlspecialchars(
                                        (string) $coach['full_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                    <?php if (!empty($coach['specialization'])): ?>

                                        -
                                        <?= htmlspecialchars(
                                            (string) $coach['specialization'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <p class="team-form-help">
                            You can assign an active approved coach to this team.
                        </p>

                    </div>


                    <!-- =================================================
                         ROSTER LIMIT
                         ================================================= -->

                    <div class="team-form-group">

                        <label for="roster_limit">
                            Roster Limit
                            <span class="required-mark">*</span>
                        </label>

                        <input
                            type="number"
                            name="roster_limit"
                            id="roster_limit"
                            min="1"
                            max="500"
                            placeholder="Example: 15"
                            required
                        >

                        <p class="team-form-help">
                            Maximum number of players allowed in this team.
                        </p>

                    </div>

                </div>


                <!-- =====================================================
                     INFORMATION
                     ===================================================== -->

                <div class="create-team-info">

                    <div class="create-team-info-icon">
                        ℹ️
                    </div>

                    <p>
                        After creating the team, you can manage its players
                        and team details from Team Management.
                    </p>

                </div>


                <!-- =====================================================
                     ACTIONS
                     ===================================================== -->

                <div class="team-form-actions">

                    <a
                        href="admin-teams.php"
                        class="team-cancel-button"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="team-submit-button"
                    >
                        ➕ Create Team
                    </button>

                </div>

            </form>

        </section>

    </div>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>