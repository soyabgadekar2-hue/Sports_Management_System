<?php
declare(strict_types=1);

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
| Get Active Sports
|--------------------------------------------------------------------------
*/

$sportsStmt = $pdo->query("
    SELECT
        sport_id,
        sport_name
    FROM sports
    WHERE sport_status = 'ACTIVE'
    ORDER BY sport_name
");

$sports = $sportsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Get Available Venues
|--------------------------------------------------------------------------
|
| IMPORTANT:
| venues table uses availability_status.
| It does NOT use venue_status.
|
*/

$venuesStmt = $pdo->query("
    SELECT
        venue_id,
        venue_name
    FROM venues
    WHERE availability_status = 'AVAILABLE'
    ORDER BY venue_name
");

$venues = $venuesStmt->fetchAll(PDO::FETCH_ASSOC);

$error = $_GET['error'] ?? '';

$pageTitle = 'Create Tournament';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
    /* =========================================================
       CREATE TOURNAMENT PAGE
       ========================================================= */

    .tournament-create-page {
        width: 100%;
        max-width: 1180px;
        margin: 0 auto;
    }

    /* Breadcrumb */

    .tournament-breadcrumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 18px;
        color: #98a2b3;
        font-size: 13px;
    }

    .tournament-breadcrumb a {
        color: #2563eb;
        font-weight: 650;
        text-decoration: none;
    }

    .tournament-breadcrumb a:hover {
        text-decoration: underline;
    }

    /* Page heading */

    .tournament-page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 24px;
    }

    .tournament-heading-content {
        min-width: 0;
    }

    .tournament-heading-content h1 {
        margin: 0;
        color: #101828;
        font-size: 28px;
        font-weight: 800;
        line-height: 1.2;
        letter-spacing: -0.5px;
    }

    .tournament-heading-content p {
        max-width: 720px;
        margin: 8px 0 0;
        color: #667085;
        font-size: 14px;
        line-height: 1.6;
    }

    .tournament-header-badge {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border: 1px solid #dbe7ff;
        border-radius: 999px;
        background: #f3f7ff;
        color: #155eef;
        font-size: 11px;
        font-weight: 750;
        white-space: nowrap;
    }

    .tournament-header-badge-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #2563eb;
    }

    /* Error */

    .tournament-error {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 20px;
        padding: 14px 16px;
        border: 1px solid #f2c4c0;
        border-radius: 12px;
        background: #fff5f4;
        color: #b42318;
        font-size: 13px;
        line-height: 1.5;
    }

    .tournament-error-icon {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #fee4e2;
        font-size: 12px;
        font-weight: 800;
    }

    /* Main form */

    .tournament-form {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Cards */

    .tournament-form-card {
        overflow: hidden;
        border: 1px solid #e4e9f2;
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 8px 28px rgba(15, 23, 42, 0.05);
    }

    .tournament-form-card-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 20px 22px;
        border-bottom: 1px solid #eaecf0;
    }

    .tournament-section-number {
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: #eef4ff;
        color: #155eef;
        font-size: 13px;
        font-weight: 800;
    }

    .tournament-section-heading {
        min-width: 0;
    }

    .tournament-section-heading h2 {
        margin: 0;
        color: #101828;
        font-size: 17px;
        font-weight: 750;
    }

    .tournament-section-heading p {
        margin: 4px 0 0;
        color: #667085;
        font-size: 12px;
        line-height: 1.5;
    }

    .tournament-form-card-body {
        padding: 22px;
    }

    /* Grid */

    .tournament-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .tournament-form-grid.three-columns {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .tournament-form-group {
        min-width: 0;
    }

    .tournament-form-group.full-width {
        grid-column: 1 / -1;
    }

    /* Labels */

    .tournament-form-label {
        display: block;
        margin-bottom: 7px;
        color: #344054;
        font-size: 12px;
        font-weight: 750;
    }

    .tournament-required {
        color: #d92d20;
        margin-left: 2px;
    }

    .tournament-help-text {
        margin: 6px 0 0;
        color: #98a2b3;
        font-size: 11px;
        line-height: 1.5;
    }

    /* Inputs */

    .tournament-input,
    .tournament-select,
    .tournament-textarea {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #d0d5dd;
        border-radius: 9px;
        background: #ffffff;
        color: #101828;
        font-family: inherit;
        font-size: 13px;
        transition:
            border-color 0.18s ease,
            box-shadow 0.18s ease,
            background 0.18s ease;
    }

    .tournament-input,
    .tournament-select {
        height: 42px;
        padding: 0 12px;
    }

    .tournament-textarea {
        min-height: 110px;
        padding: 11px 12px;
        resize: vertical;
        line-height: 1.5;
    }

    .tournament-input::placeholder,
    .tournament-textarea::placeholder {
        color: #98a2b3;
    }

    .tournament-input:hover,
    .tournament-select:hover,
    .tournament-textarea:hover {
        border-color: #98a2b3;
    }

    .tournament-input:focus,
    .tournament-select:focus,
    .tournament-textarea:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    /* Points cards */

    .tournament-points-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }

    .tournament-point-card {
        padding: 16px;
        border: 1px solid #e4e7ec;
        border-radius: 12px;
        background: #f8fafc;
        transition:
            border-color 0.18s ease,
            transform 0.18s ease,
            box-shadow 0.18s ease;
    }

    .tournament-point-card:hover {
        border-color: #c7d7fe;
        transform: translateY(-1px);
        box-shadow: 0 5px 16px rgba(15, 23, 42, 0.05);
    }

    .tournament-point-card.win {
        background: #f0fdf4;
        border-color: #ccebd6;
    }

    .tournament-point-card.draw {
        background: #fffbeb;
        border-color: #f3dfaa;
    }

    .tournament-point-card.loss {
        background: #fff7f6;
        border-color: #f3d0cc;
    }

    .tournament-point-label {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 10px;
        color: #344054;
        font-size: 12px;
        font-weight: 750;
    }

    .tournament-point-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 800;
    }

    .win .tournament-point-icon {
        background: #dcfce7;
        color: #15803d;
    }

    .draw .tournament-point-icon {
        background: #fef3c7;
        color: #a16207;
    }

    .loss .tournament-point-icon {
        background: #fee2e2;
        color: #b91c1c;
    }

    .tournament-point-input {
        width: 100%;
        height: 42px;
        box-sizing: border-box;
        padding: 0 11px;
        border: 1px solid #d0d5dd;
        border-radius: 8px;
        background: #ffffff;
        color: #101828;
        font-family: inherit;
        font-size: 14px;
        font-weight: 700;
    }

    .tournament-point-input:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
    }

    /* Info box */

    .tournament-info-box {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        margin-top: 18px;
        padding: 13px 15px;
        border: 1px solid #dbe7ff;
        border-radius: 10px;
        background: #f6f9ff;
        color: #475467;
        font-size: 12px;
        line-height: 1.6;
    }

    .tournament-info-icon {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #e0eaff;
        color: #155eef;
        font-size: 11px;
        font-weight: 800;
    }

    /* Action bar */

    .tournament-action-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 22px;
        border: 1px solid #e4e9f2;
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 8px 28px rgba(15, 23, 42, 0.05);
    }

    .tournament-action-note {
        color: #667085;
        font-size: 11px;
        line-height: 1.5;
    }

    .tournament-action-buttons {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 0 0 auto;
    }

    .tournament-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 130px;
        height: 42px;
        padding: 0 17px;
        border-radius: 9px;
        font-family: inherit;
        font-size: 12px;
        font-weight: 750;
        text-decoration: none;
        cursor: pointer;
        transition:
            background 0.18s ease,
            border-color 0.18s ease,
            transform 0.18s ease,
            box-shadow 0.18s ease;
    }

    .tournament-button:hover {
        transform: translateY(-1px);
    }

    .tournament-button-cancel {
        border: 1px solid #d0d5dd;
        background: #ffffff;
        color: #344054;
    }

    .tournament-button-cancel:hover {
        background: #f8fafc;
        border-color: #98a2b3;
    }

    .tournament-button-submit {
        border: 1px solid #2563eb;
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.18);
    }

    .tournament-button-submit:hover {
        background: #1d4ed8;
        border-color: #1d4ed8;
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.24);
    }

    .tournament-button-submit:focus-visible,
    .tournament-button-cancel:focus-visible {
        outline: 3px solid rgba(37, 99, 235, 0.16);
        outline-offset: 2px;
    }

    /* Responsive */

    @media (max-width: 900px) {

        .tournament-page-header {
            flex-direction: column;
        }

        .tournament-header-badge {
            align-self: flex-start;
        }

        .tournament-form-grid.three-columns {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .tournament-points-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 700px) {

        .tournament-form-grid,
        .tournament-form-grid.three-columns {
            grid-template-columns: 1fr;
        }

        .tournament-form-card-body {
            padding: 18px;
        }

        .tournament-form-card-header {
            padding: 17px 18px;
        }

        .tournament-action-bar {
            align-items: stretch;
            flex-direction: column;
        }

        .tournament-action-buttons {
            width: 100%;
        }

        .tournament-button {
            flex: 1;
            min-width: 0;
        }
    }

    @media (max-width: 480px) {

        .tournament-heading-content h1 {
            font-size: 24px;
        }

        .tournament-section-heading h2 {
            font-size: 16px;
        }

        .tournament-action-buttons {
            flex-direction: column;
        }

        .tournament-button {
            width: 100%;
        }
    }
</style>


<div class="tournament-create-page">

    <!-- Breadcrumb -->

    <div class="tournament-breadcrumb">

        <a href="admin-dashboard.php">
            Dashboard
        </a>

        <span>›</span>

        <a href="admin-tournaments.php">
            Tournament Management
        </a>

        <span>›</span>

        <span>Create Tournament</span>

    </div>


    <!-- Page Header -->

    <div class="tournament-page-header">

        <div class="tournament-heading-content">

            <h1>
                Create Tournament
            </h1>

            <p>
                Set up a new sports tournament by providing its basic
                information, schedule, venue, points system, rules,
                and current status.
            </p>

        </div>


        <div class="tournament-header-badge">

            <span class="tournament-header-badge-dot"></span>

            Tournament Setup

        </div>

    </div>


    <?php if ($error !== ''): ?>

        <div class="tournament-error">

            <div class="tournament-error-icon">
                !
            </div>

            <div>
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        action="create-tournament-process.php"
        class="tournament-form"
    >

        <?= csrfField() ?>


        <!-- =====================================================
             SECTION 1 - BASIC INFORMATION
             ===================================================== -->

        <section class="tournament-form-card">

            <div class="tournament-form-card-header">

                <div class="tournament-section-number">
                    01
                </div>

                <div class="tournament-section-heading">

                    <h2>
                        Basic Information
                    </h2>

                    <p>
                        Enter the main details of the tournament.
                    </p>

                </div>

            </div>


            <div class="tournament-form-card-body">

                <div class="tournament-form-grid">


                    <!-- Tournament Name -->

                    <div class="tournament-form-group full-width">

                        <label
                            for="tournament_name"
                            class="tournament-form-label"
                        >
                            Tournament Name
                            <span class="tournament-required">*</span>
                        </label>

                        <input
                            type="text"
                            name="tournament_name"
                            id="tournament_name"
                            class="tournament-input"
                            maxlength="150"
                            placeholder="e.g. Annual Inter College Football Championship"
                            required
                        >

                    </div>


                    <!-- Sport -->

                    <div class="tournament-form-group">

                        <label
                            for="sport_id"
                            class="tournament-form-label"
                        >
                            Sport
                            <span class="tournament-required">*</span>
                        </label>

                        <select
                            name="sport_id"
                            id="sport_id"
                            class="tournament-select"
                            required
                        >

                            <option value="">
                                Select Sport
                            </option>

                            <?php foreach ($sports as $sport): ?>

                                <option
                                    value="<?= (int) $sport['sport_id'] ?>"
                                >
                                    <?= htmlspecialchars(
                                        $sport['sport_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <p class="tournament-help-text">
                            Select the sport for this tournament.
                        </p>

                    </div>


                    <!-- Format -->

                    <div class="tournament-form-group">

                        <label
                            for="tournament_format"
                            class="tournament-form-label"
                        >
                            Tournament Format
                            <span class="tournament-required">*</span>
                        </label>

                        <select
                            name="tournament_format"
                            id="tournament_format"
                            class="tournament-select"
                            required
                        >

                            <option value="LEAGUE">
                                League
                            </option>

                        </select>

                        <p class="tournament-help-text">
                            League format is currently supported in V1.
                        </p>

                    </div>


                    <!-- Description -->

                    <div class="tournament-form-group full-width">

                        <label
                            for="tournament_description"
                            class="tournament-form-label"
                        >
                            Description
                        </label>

                        <textarea
                            name="tournament_description"
                            id="tournament_description"
                            class="tournament-textarea"
                            rows="4"
                            placeholder="Briefly describe the tournament, participating teams, or purpose..."
                        ></textarea>

                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             SECTION 2 - SCHEDULE & VENUE
             ===================================================== -->

        <section class="tournament-form-card">

            <div class="tournament-form-card-header">

                <div class="tournament-section-number">
                    02
                </div>

                <div class="tournament-section-heading">

                    <h2>
                        Schedule & Venue
                    </h2>

                    <p>
                        Define when and where the tournament will take place.
                    </p>

                </div>

            </div>


            <div class="tournament-form-card-body">

                <div class="tournament-form-grid">


                    <!-- Start Date -->

                    <div class="tournament-form-group">

                        <label
                            for="start_date"
                            class="tournament-form-label"
                        >
                            Start Date
                            <span class="tournament-required">*</span>
                        </label>

                        <input
                            type="date"
                            name="start_date"
                            id="start_date"
                            class="tournament-input"
                            required
                        >

                    </div>


                    <!-- End Date -->

                    <div class="tournament-form-group">

                        <label
                            for="end_date"
                            class="tournament-form-label"
                        >
                            End Date
                            <span class="tournament-required">*</span>
                        </label>

                        <input
                            type="date"
                            name="end_date"
                            id="end_date"
                            class="tournament-input"
                            required
                        >

                    </div>


                    <!-- Venue -->

                    <div class="tournament-form-group full-width">

                        <label
                            for="venue_id"
                            class="tournament-form-label"
                        >
                            Venue
                        </label>

                        <select
                            name="venue_id"
                            id="venue_id"
                            class="tournament-select"
                        >

                            <option value="">
                                Not assigned
                            </option>

                            <?php foreach ($venues as $venue): ?>

                                <option
                                    value="<?= (int) $venue['venue_id'] ?>"
                                >
                                    <?= htmlspecialchars(
                                        $venue['venue_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <p class="tournament-help-text">
                            You can leave the venue unassigned and configure it later.
                        </p>

                    </div>

                </div>


                <div class="tournament-info-box">

                    <div class="tournament-info-icon">
                        i
                    </div>

                    <div>
                        The end date should be the same as or later than
                        the start date.
                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             SECTION 3 - POINTS SYSTEM
             ===================================================== -->

        <section class="tournament-form-card">

            <div class="tournament-form-card-header">

                <div class="tournament-section-number">
                    03
                </div>

                <div class="tournament-section-heading">

                    <h2>
                        Points System
                    </h2>

                    <p>
                        Define the points awarded for match results.
                    </p>

                </div>

            </div>


            <div class="tournament-form-card-body">

                <div class="tournament-points-grid">


                    <!-- Win -->

                    <div class="tournament-point-card win">

                        <div class="tournament-point-label">

                            <span class="tournament-point-icon">
                                W
                            </span>

                            Points for Win

                        </div>

                        <input
                            type="number"
                            name="points_win"
                            id="points_win"
                            class="tournament-point-input"
                            min="0"
                            step="0.01"
                            value="3"
                            required
                        >

                    </div>


                    <!-- Draw -->

                    <div class="tournament-point-card draw">

                        <div class="tournament-point-label">

                            <span class="tournament-point-icon">
                                D
                            </span>

                            Points for Draw

                        </div>

                        <input
                            type="number"
                            name="points_draw"
                            id="points_draw"
                            class="tournament-point-input"
                            min="0"
                            step="0.01"
                            value="1"
                            required
                        >

                    </div>


                    <!-- Loss -->

                    <div class="tournament-point-card loss">

                        <div class="tournament-point-label">

                            <span class="tournament-point-icon">
                                L
                            </span>

                            Points for Loss

                        </div>

                        <input
                            type="number"
                            name="points_loss"
                            id="points_loss"
                            class="tournament-point-input"
                            min="0"
                            step="0.01"
                            value="0"
                            required
                        >

                    </div>

                </div>


                <div class="tournament-info-box">

                    <div class="tournament-info-icon">
                        i
                    </div>

                    <div>
                        Default SportSync league scoring is
                        <strong>3 points for a win, 1 point for a draw,
                        and 0 points for a loss.</strong>
                        You can change these values for this tournament.
                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             SECTION 4 - RULES
             ===================================================== -->

        <section class="tournament-form-card">

            <div class="tournament-form-card-header">

                <div class="tournament-section-number">
                    04
                </div>

                <div class="tournament-section-heading">

                    <h2>
                        Rules & Information
                    </h2>

                    <p>
                        Add important rules or instructions for participants.
                    </p>

                </div>

            </div>


            <div class="tournament-form-card-body">

                <div class="tournament-form-group">

                    <label
                        for="rules_information"
                        class="tournament-form-label"
                    >
                        Tournament Rules
                    </label>

                    <textarea
                        name="rules_information"
                        id="rules_information"
                        class="tournament-textarea"
                        rows="7"
                        placeholder="Enter tournament rules, match rules, player requirements, tie-break information, or other important instructions..."
                    ></textarea>

                    <p class="tournament-help-text">
                        Keep the rules clear so coaches, coordinators,
                        and players can understand the tournament requirements.
                    </p>

                </div>

            </div>

        </section>


        <!-- =====================================================
             SECTION 5 - STATUS
             ===================================================== -->

        <section class="tournament-form-card">

            <div class="tournament-form-card-header">

                <div class="tournament-section-number">
                    05
                </div>

                <div class="tournament-section-heading">

                    <h2>
                        Tournament Status
                    </h2>

                    <p>
                        Choose the current lifecycle status of this tournament.
                    </p>

                </div>

            </div>


            <div class="tournament-form-card-body">

                <div class="tournament-form-grid">

                    <div class="tournament-form-group">

                        <label
                            for="tournament_status"
                            class="tournament-form-label"
                        >
                            Status
                            <span class="tournament-required">*</span>
                        </label>

                        <select
                            name="tournament_status"
                            id="tournament_status"
                            class="tournament-select"
                            required
                        >

                            <option value="DRAFT">
                                Draft
                            </option>

                            <option value="REGISTRATION_OPEN">
                                Registration Open
                            </option>

                            <option value="ACTIVE">
                                Active
                            </option>

                            <option value="COMPLETED">
                                Completed
                            </option>

                            <option value="CANCELLED">
                                Cancelled
                            </option>

                        </select>

                        <p class="tournament-help-text">
                            For a newly created tournament, Draft is recommended
                            until all tournament details are ready.
                        </p>

                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             ACTION BAR
             ===================================================== -->

        <div class="tournament-action-bar">

            <div class="tournament-action-note">
                Fields marked with <strong>*</strong> are required.
            </div>

            <div class="tournament-action-buttons">

                <a
                    href="admin-tournaments.php"
                    class="tournament-button tournament-button-cancel"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="tournament-button tournament-button-submit"
                >
                    Create Tournament
                </button>

            </div>

        </div>

    </form>

</div>


<script>
    document.addEventListener('DOMContentLoaded', function () {

        const startDate = document.getElementById('start_date');
        const endDate = document.getElementById('end_date');
        const form = document.querySelector('.tournament-form');

        /*
        |--------------------------------------------------------------------------
        | Keep End Date >= Start Date
        |--------------------------------------------------------------------------
        */

        if (startDate && endDate) {

            startDate.addEventListener('change', function () {

                endDate.min = startDate.value;

                if (
                    endDate.value &&
                    endDate.value < startDate.value
                ) {
                    endDate.value = startDate.value;
                }

            });

        }


        /*
        |--------------------------------------------------------------------------
        | Final Date Validation
        |--------------------------------------------------------------------------
        */

        if (form) {

            form.addEventListener('submit', function (event) {

                if (
                    startDate &&
                    endDate &&
                    startDate.value &&
                    endDate.value &&
                    endDate.value < startDate.value
                ) {

                    event.preventDefault();

                    alert(
                        'End Date cannot be earlier than Start Date.'
                    );

                    endDate.focus();

                    return;
                }

            });

        }

    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>