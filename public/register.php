<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/csrf.php';

$csrfToken = csrfToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register | SportSync</title>

    <meta
        name="description"
        content="Create your SportSync student account."
    >

    <link rel="icon" type="image/svg+xml" href="../assets/images/sportsync-mark.svg">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Roboto,
                Arial,
                sans-serif;

            background:
                radial-gradient(
                    circle at top left,
                    rgba(37, 99, 235, 0.16),
                    transparent 35%
                ),
                radial-gradient(
                    circle at bottom right,
                    rgba(59, 130, 246, 0.10),
                    transparent 35%
                ),
                #f5f8ff;

            color: #172033;
            padding: 40px 20px;
        }

        .page-wrapper {
            width: 100%;
            max-width: 1050px;
            margin: 0 auto;
        }

        /* =========================
           BRAND
        ========================= */

        .brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-logo {
            width: 58px;
            height: 58px;
            object-fit: contain;
            margin-bottom: 10px;
        }

        .brand-name {
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.7px;
            color: #155eef;
        }

        .brand-tagline {
            margin-top: 4px;
            color: #667085;
            font-size: 14px;
        }

        /* =========================
           CARD
        ========================= */

        .registration-card {
            background: #ffffff;
            border: 1px solid #e4e9f2;
            border-radius: 22px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .card-header {
            padding: 32px 38px 24px;
            border-bottom: 1px solid #edf0f5;
        }

        .card-header h1 {
            font-size: 26px;
            color: #101828;
            margin-bottom: 8px;
        }

        .card-header p {
            color: #667085;
            font-size: 14px;
            line-height: 1.6;
        }

        .form-container {
            padding: 32px 38px 38px;
        }

        /* =========================
           SECTION
        ========================= */

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;

            margin-bottom: 18px;

            font-size: 16px;
            font-weight: 700;
            color: #1d2939;
        }

        .section-title::before {
            content: "";
            width: 4px;
            height: 20px;
            border-radius: 5px;
            background: #155eef;
        }

        /* =========================
           FORM GRID
        ========================= */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-size: 13px;
            font-weight: 650;
            color: #344054;
        }

        .required {
            color: #d92d20;
            margin-left: 2px;
        }

        input,
        select {
            width: 100%;
            height: 46px;

            border: 1px solid #d0d5dd;
            border-radius: 10px;

            padding: 0 13px;

            background: #ffffff;
            color: #101828;

            font-size: 14px;
            outline: none;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        input::placeholder {
            color: #98a2b3;
        }

        input:focus,
        select:focus {
            border-color: #155eef;
            box-shadow: 0 0 0 3px rgba(21, 94, 239, 0.12);
        }

        select {
            cursor: pointer;
        }

        .field-help {
            font-size: 12px;
            color: #667085;
            line-height: 1.4;
        }

        /* =========================
           PASSWORD
        ========================= */

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 50px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);

            border: none;
            background: transparent;

            color: #667085;
            font-size: 12px;
            font-weight: 600;

            cursor: pointer;
            padding: 5px;
        }

        .password-toggle:hover {
            color: #155eef;
        }

        /* =========================
           INFO BOX
        ========================= */

        .approval-info {
            display: flex;
            align-items: flex-start;
            gap: 12px;

            margin-bottom: 28px;
            padding: 14px 16px;

            border: 1px solid #dbe7ff;
            border-radius: 12px;

            background: #f4f7ff;
        }

        .approval-icon {
            width: 28px;
            height: 28px;
            flex-shrink: 0;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;
            background: #dbe7ff;
            color: #155eef;
            font-weight: 800;
            font-size: 14px;
        }

        .approval-info strong {
            display: block;
            margin-bottom: 3px;

            color: #1d2939;
            font-size: 13px;
        }

        .approval-info span {
            color: #667085;
            font-size: 12px;
            line-height: 1.5;
        }

        /* =========================
           ACTIONS
        ========================= */

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;

            padding-top: 8px;
            border-top: 1px solid #edf0f5;
        }

        .login-link {
            color: #667085;
            font-size: 14px;
        }

        .login-link a {
            color: #155eef;
            font-weight: 700;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .register-button {
            min-width: 175px;
            height: 46px;

            border: none;
            border-radius: 10px;

            background: #155eef;
            color: #ffffff;

            font-size: 14px;
            font-weight: 700;

            cursor: pointer;

            box-shadow: 0 6px 16px rgba(21, 94, 239, 0.22);

            transition:
                transform 0.2s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;
        }

        .register-button:hover {
            background: #0f4dcc;
            transform: translateY(-1px);
            box-shadow: 0 9px 20px rgba(21, 94, 239, 0.28);
        }

        .register-button:active {
            transform: translateY(0);
        }

        /* =========================
           FOOTER
        ========================= */

        .page-footer {
            text-align: center;
            margin-top: 24px;

            color: #98a2b3;
            font-size: 12px;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 700px) {

            body {
                padding: 25px 14px;
            }

            .brand {
                margin-bottom: 20px;
            }

            .brand-name {
                font-size: 26px;
            }

            .registration-card {
                border-radius: 16px;
            }

            .card-header {
                padding: 25px 22px 20px;
            }

            .card-header h1 {
                font-size: 22px;
            }

            .form-container {
                padding: 25px 22px 28px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .form-group.full-width {
                grid-column: auto;
            }

            .form-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .register-button {
                width: 100%;
            }

            .login-link {
                text-align: center;
            }
        }
    </style>
</head>

<body>

<div class="page-wrapper">

    <!-- =========================
         BRAND
    ========================== -->

    <div class="brand">

        <img
            src="../assets/images/sportsync-mark.svg"
            alt="SportSync"
            class="brand-logo"
        >

        <div class="brand-name">
            SportSync
        </div>

        <div class="brand-tagline">
            Smart Sports Management System
        </div>

    </div>


    <!-- =========================
         REGISTRATION CARD
    ========================== -->

    <div class="registration-card">

        <div class="card-header">

            <h1>
                Create Your Student Account
            </h1>

            <p>
                Register as a student player to participate in college sports
                activities, teams, tournaments and matches.
            </p>

        </div>


        <div class="form-container">

            <!-- Approval Information -->

            <div class="approval-info">

                <div class="approval-icon">
                    !
                </div>

                <div>

                    <strong>
                        Account approval required
                    </strong>

                    <span>
                        Your account will remain pending until an Admin or
                        Sports Coordinator approves your registration.
                    </span>

                </div>

            </div>


            <form
                action="register-process.php"
                method="POST"
            >

                <!-- CSRF Token -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                >


                <!-- =========================
                     PERSONAL INFORMATION
                ========================== -->

                <div class="form-section">

                    <div class="section-title">
                        Personal Information
                    </div>

                    <div class="form-grid">

                        <!-- Full Name -->

                        <div class="form-group">

                            <label for="full_name">
                                Full Name
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                maxlength="120"
                                placeholder="Enter your full name"
                                autocomplete="name"
                                required
                            >

                        </div>


                        <!-- Email -->

                        <div class="form-group">

                            <label for="email">
                                Email
                                <span class="required">*</span>
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="150"
                                placeholder="student@example.com"
                                autocomplete="email"
                                required
                            >

                        </div>


                        <!-- Phone -->

                        <div class="form-group">

                            <label for="phone">
                                Phone
                            </label>

                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                maxlength="20"
                                placeholder="Enter phone number"
                                autocomplete="tel"
                            >

                        </div>


                        <!-- Gender -->

                        <div class="form-group">

                            <label for="gender">
                                Gender
                            </label>

                            <select
                                id="gender"
                                name="gender"
                            >

                                <option value="">
                                    Select gender
                                </option>

                                <option value="MALE">
                                    Male
                                </option>

                                <option value="FEMALE">
                                    Female
                                </option>

                                <option value="OTHER">
                                    Other
                                </option>

                                <option value="PREFER_NOT_TO_SAY">
                                    Prefer not to say
                                </option>

                            </select>

                        </div>


                        <!-- Date of Birth -->

                        <div class="form-group">

                            <label for="date_of_birth">
                                Date of Birth
                            </label>

                            <input
                                type="date"
                                id="date_of_birth"
                                name="date_of_birth"
                            >

                        </div>

                    </div>

                </div>


                <!-- =========================
                     ACCOUNT SECURITY
                ========================== -->

                <div class="form-section">

                    <div class="section-title">
                        Account Security
                    </div>

                    <div class="form-grid">

                        <!-- Password -->

                        <div class="form-group full-width">

                            <label for="password">
                                Password
                                <span class="required">*</span>
                            </label>

                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    minlength="8"
                                    placeholder="Create a password"
                                    autocomplete="new-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    id="passwordToggle"
                                >
                                    Show
                                </button>

                            </div>

                            <span class="field-help">
                                Password must contain at least 8 characters.
                            </span>

                        </div>

                    </div>

                </div>


                <!-- =========================
                     ACADEMIC INFORMATION
                ========================== -->

                <div class="form-section">

                    <div class="section-title">
                        Academic Information
                    </div>

                    <div class="form-grid">

                        <!-- Student ID -->

                        <div class="form-group">

                            <label for="student_id">
                                Student ID
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                id="student_id"
                                name="student_id"
                                maxlength="40"
                                placeholder="Enter student ID"
                                required
                            >

                        </div>


                        <!-- Department -->

                        <div class="form-group">

                            <label for="department">
                                Department
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                id="department"
                                name="department"
                                maxlength="100"
                                placeholder="e.g. Computer Applications"
                                required
                            >

                        </div>


                        <!-- Course -->

                        <div class="form-group">

                            <label for="course">
                                Course
                            </label>

                            <input
                                type="text"
                                id="course"
                                name="course"
                                maxlength="100"
                                placeholder="e.g. BCA"
                            >

                        </div>


                        <!-- Academic Year -->

                        <div class="form-group">

                            <label for="academic_year">
                                Academic Year
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                id="academic_year"
                                name="academic_year"
                                maxlength="30"
                                placeholder="2026-27"
                                required
                            >

                        </div>


                        <!-- Semester -->

                        <div class="form-group">

                            <label for="semester">
                                Semester
                            </label>

                            <input
                                type="number"
                                id="semester"
                                name="semester"
                                min="1"
                                max="12"
                                placeholder="e.g. 5"
                            >

                        </div>

                    </div>

                </div>


                <!-- =========================
                     ACTIONS
                ========================== -->

                <div class="form-actions">

                    <div class="login-link">

                        Already have an account?

                        <a href="login.php">
                            Login
                        </a>

                    </div>

                    <button
                        type="submit"
                        class="register-button"
                    >
                        Create Student Account
                    </button>

                </div>

            </form>

        </div>

    </div>


    <!-- Footer -->

    <div class="page-footer">
        SportSync · Smart Sports Management System
    </div>

</div>


<!-- =========================
     PASSWORD TOGGLE
========================== -->

<script>

    const passwordInput =
        document.getElementById('password');

    const passwordToggle =
        document.getElementById('passwordToggle');


    passwordToggle.addEventListener('click', function () {

        if (passwordInput.type === 'password') {

            passwordInput.type = 'text';

            passwordToggle.textContent = 'Hide';

        } else {

            passwordInput.type = 'password';

            passwordToggle.textContent = 'Show';

        }

    });

</script>

</body>
</html>