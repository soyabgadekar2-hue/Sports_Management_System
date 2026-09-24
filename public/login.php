<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/csrf.php';

if (isset($_SESSION['user_id'])) {

    $role = $_SESSION['role_name'] ?? '';

    switch ($role) {

        case 'ADMIN':
            header('Location: admin-dashboard.php');
            exit;

        case 'SPORTS_COORDINATOR':
            header('Location: coordinator-dashboard.php');
            exit;

        case 'COACH':
            header('Location: coach-dashboard.php');
            exit;

        case 'PLAYER':
            header('Location: player-dashboard.php');
            exit;
    }
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login | SportSync</title>

    <meta
        name="description"
        content="SportSync - Smart Sports Management System"
    >

    <link
        rel="icon"
        type="image/svg+xml"
        href="../assets/images/sportsync-mark.svg"
    >

    <style>

        /* =====================================================
           RESET
        ===================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            min-height: 100%;
        }

        body {
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            color: #172033;
            background: #f5f7fb;
            overflow-x: hidden;
        }

        /* =====================================================
           PAGE
        ===================================================== */

        .auth-page {
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 24px;

            position: relative;
            overflow: hidden;

            background:
                radial-gradient(
                    circle at 10% 10%,
                    rgba(37, 99, 235, 0.10),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 90% 90%,
                    rgba(37, 99, 235, 0.08),
                    transparent 30%
                ),
                #f5f7fb;
        }

        /* =====================================================
           ANIMATED BACKGROUND
        ===================================================== */

        .background-circle {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            opacity: 0.35;
            filter: blur(1px);
        }

        .background-circle.one {
            width: 220px;
            height: 220px;

            top: -90px;
            left: -70px;

            background: rgba(37, 99, 235, 0.12);

            animation:
                floatCircleOne 9s ease-in-out infinite;
        }

        .background-circle.two {
            width: 280px;
            height: 280px;

            right: -100px;
            bottom: -110px;

            background: rgba(59, 130, 246, 0.10);

            animation:
                floatCircleTwo 11s ease-in-out infinite;
        }

        .background-circle.three {
            width: 100px;
            height: 100px;

            right: 15%;
            top: 8%;

            background: rgba(37, 99, 235, 0.07);

            animation:
                floatCircleThree 7s ease-in-out infinite;
        }

        @keyframes floatCircleOne {

            0%,
            100% {
                transform: translate(0, 0);
            }

            50% {
                transform: translate(25px, 20px);
            }
        }

        @keyframes floatCircleTwo {

            0%,
            100% {
                transform: translate(0, 0);
            }

            50% {
                transform: translate(-25px, -25px);
            }
        }

        @keyframes floatCircleThree {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(18px);
            }
        }

        /* =====================================================
           MAIN CONTAINER
        ===================================================== */

        .auth-container {
            position: relative;
            z-index: 5;

            width: 100%;
            max-width: 1050px;

            display: grid;

            grid-template-columns:
                0.95fr 1.05fr;

            background: #ffffff;

            border-radius: 24px;

            overflow: hidden;

            border: 1px solid rgba(226, 232, 240, 0.9);

            box-shadow:
                0 25px 70px rgba(15, 23, 42, 0.12),
                0 8px 25px rgba(15, 23, 42, 0.05);

            animation:
                containerEnter 0.8s
                cubic-bezier(0.22, 1, 0.36, 1)
                both;
        }

        @keyframes containerEnter {

            from {
                opacity: 0;

                transform:
                    translateY(25px)
                    scale(0.98);
            }

            to {
                opacity: 1;

                transform:
                    translateY(0)
                    scale(1);
            }
        }

        /* =====================================================
           BRAND PANEL
        ===================================================== */

        .auth-brand-panel {
            position: relative;

            min-height: 540px;

            padding: 30px;

            display: flex;
            flex-direction: column;
            justify-content: center;

            overflow: hidden;

            color: #ffffff;

            background:
                linear-gradient(
                    145deg,
                    #0f2f9e 0%,
                    #1d4ed8 48%,
                    #2563eb 100%
                );
        }

        .auth-brand-panel::before {
            content: "";

            position: absolute;

            width: 300px;
            height: 300px;

            top: -130px;
            right: -120px;

            border-radius: 50%;

            background:
                rgba(255, 255, 255, 0.08);

            animation:
                brandOrb 10s ease-in-out infinite;
        }

        .auth-brand-panel::after {
            content: "";

            position: absolute;

            width: 240px;
            height: 240px;

            bottom: -130px;
            left: -100px;

            border-radius: 50%;

            background:
                rgba(255, 255, 255, 0.07);

            animation:
                brandOrbTwo 12s ease-in-out infinite;
        }

        @keyframes brandOrb {

            0%,
            100% {
                transform:
                    translate(0, 0)
                    scale(1);
            }

            50% {
                transform:
                    translate(-25px, 25px)
                    scale(1.08);
            }
        }

        @keyframes brandOrbTwo {

            0%,
            100% {
                transform:
                    translate(0, 0)
                    scale(1);
            }

            50% {
                transform:
                    translate(25px, -20px)
                    scale(1.08);
            }
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }

        /* =====================================================
           LOGO
        ===================================================== */

        .auth-logo {
            width: 96px;
            height: 96px;

            display: block;

            object-fit: contain;

            margin-bottom: 18px;

            filter:
                drop-shadow(
                    0 10px 20px rgba(0, 0, 0, 0.18)
                );

            animation:
                logoFloat 4s ease-in-out infinite;
        }

        @keyframes logoFloat {

            0%,
            100% {
                transform:
                    translateY(0)
                    rotate(0deg);
            }

            50% {
                transform:
                    translateY(-7px)
                    rotate(1deg);
            }
        }

        .brand-title {
            font-size: 42px;
            line-height: 1.05;

            font-weight: 800;

            letter-spacing: -1.5px;

            margin-bottom: 10px;

            animation:
                textReveal 0.8s
                0.15s
                both;
        }

        .brand-tagline {
            font-size: 16px;
            line-height: 1.6;

            color:
                rgba(255, 255, 255, 0.88);

            max-width: 360px;

            margin-bottom: 28px;

            animation:
                textReveal 0.8s
                0.25s
                both;
        }

        @keyframes textReveal {

            from {
                opacity: 0;

                transform:
                    translateY(12px);
            }

            to {
                opacity: 1;

                transform:
                    translateY(0);
            }
        }

        /* =====================================================
           FEATURES
        ===================================================== */

        .brand-features {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .brand-feature {
            display: flex;
            align-items: center;

            gap: 10px;

            font-size: 14px;

            color:
                rgba(255, 255, 255, 0.94);

            animation:
                featureEnter 0.6s
                both;
        }

        .brand-feature:nth-child(1) {
            animation-delay: 0.35s;
        }

        .brand-feature:nth-child(2) {
            animation-delay: 0.45s;
        }

        .brand-feature:nth-child(3) {
            animation-delay: 0.55s;
        }

        @keyframes featureEnter {

            from {
                opacity: 0;

                transform:
                    translateX(-15px);
            }

            to {
                opacity: 1;

                transform:
                    translateX(0);
            }
        }

        .feature-icon {
            width: 28px;
            height: 28px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 8px;

            background:
                rgba(255, 255, 255, 0.13);

            font-size: 14px;

            transition:
                transform 0.25s ease,
                background 0.25s ease;
        }

        .brand-feature:hover .feature-icon {
            transform:
                rotate(-8deg)
                scale(1.1);

            background:
                rgba(255, 255, 255, 0.20);
        }

        /* =====================================================
           FORM PANEL
        ===================================================== */

        .auth-form-panel {
            padding: 30px 42px;

            display: flex;
            flex-direction: column;
            justify-content: center;

            background: #ffffff;
        }

        .form-header {
            margin-bottom: 20px;

            animation:
                formEnter 0.7s
                0.15s
                both;
        }

        @keyframes formEnter {

            from {
                opacity: 0;

                transform:
                    translateY(15px);
            }

            to {
                opacity: 1;

                transform:
                    translateY(0);
            }
        }

        .form-header h1 {
            font-size: 30px;
            line-height: 1.2;

            font-weight: 750;

            color: #172033;

            margin-bottom: 7px;
        }

        .form-header p {
            font-size: 14px;
            line-height: 1.5;

            color: #64748b;
        }

        /* =====================================================
           ERROR
        ===================================================== */

        .login-error {
            padding: 11px 13px;

            margin-bottom: 16px;

            border-radius: 10px;

            border: 1px solid #fecaca;

            background: #fef2f2;

            color: #b91c1c;

            font-size: 13px;
            line-height: 1.45;

            animation:
                errorShake 0.45s ease both;
        }

        @keyframes errorShake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        /* =====================================================
           ROLE SECTION
        ===================================================== */

        .role-section {
            margin-bottom: 18px;

            animation:
                formEnter 0.7s
                0.25s
                both;
        }

        .field-label {
            display: block;

            margin-bottom: 8px;

            font-size: 13px;
            font-weight: 700;

            color: #334155;
        }

        .role-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 9px;
        }

        /* =====================================================
           ROLE OPTION
        ===================================================== */

        .role-option {
            position: relative;
            cursor: pointer;
        }

        .role-option input {
            position: absolute;

            opacity: 0;

            pointer-events: none;
        }

        /* =====================================================
           ROLE CARD
        ===================================================== */

        .role-card {
            position: relative;

            min-height: 64px;

            padding: 10px 11px;

            display: flex;
            align-items: center;

            gap: 10px;

            border: 1px solid #e2e8f0;

            border-radius: 11px;

            background: #ffffff;

            overflow: hidden;

            transition:
                border-color 0.25s ease,
                background 0.25s ease,
                transform 0.25s ease,
                box-shadow 0.25s ease;
        }

        .role-card::before {
            content: "";

            position: absolute;

            top: 0;
            left: -120%;

            width: 80%;
            height: 100%;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255, 255, 255, 0.65),
                    transparent
                );

            transform:
                skewX(-20deg);

            transition:
                left 0.6s ease;
        }

        .role-card:hover::before {
            left: 140%;
        }

        .role-card:hover {
            border-color: #93c5fd;

            background: #f8fbff;

            transform:
                translateY(-2px);

            box-shadow:
                0 8px 20px
                rgba(37, 99, 235, 0.08);
        }

        /* =====================================================
           SELECTED ROLE
        ===================================================== */

        .role-option input:checked + .role-card {
            border-color: #2563eb;

            background:
                linear-gradient(
                    135deg,
                    #eff6ff,
                    #dbeafe
                );

            transform:
                translateY(-2px)
                scale(1.015);

            box-shadow:
                0 8px 24px
                rgba(37, 99, 235, 0.16),

                0 0 0 3px
                rgba(37, 99, 235, 0.10);

            animation:
                selectedRole 0.35s ease;
        }

        @keyframes selectedRole {

            0% {
                transform:
                    translateY(0)
                    scale(0.98);
            }

            60% {
                transform:
                    translateY(-3px)
                    scale(1.025);
            }

            100% {
                transform:
                    translateY(-2px)
                    scale(1.015);
            }
        }

        .role-option input:checked + .role-card::after {
            content: "✓";

            position: absolute;

            top: 6px;
            right: 7px;

            width: 19px;
            height: 19px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #2563eb;

            color: #ffffff;

            font-size: 11px;
            font-weight: 800;

            box-shadow:
                0 3px 8px
                rgba(37, 99, 235, 0.28);

            animation:
                checkPop 0.3s ease;
        }

        @keyframes checkPop {

            0% {
                opacity: 0;

                transform:
                    scale(0);
            }

            70% {
                transform:
                    scale(1.15);
            }

            100% {
                opacity: 1;

                transform:
                    scale(1);
            }
        }

        /* =====================================================
           ROLE ICON
        ===================================================== */

        .role-icon {
            width: 34px;
            height: 34px;

            flex: 0 0 34px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 9px;

            background: #eff6ff;

            font-size: 17px;

            transition:
                transform 0.25s ease,
                background 0.25s ease;
        }

        .role-card:hover .role-icon {
            transform:
                scale(1.08)
                rotate(-4deg);
        }

        .role-option input:checked + .role-card .role-icon {
            background: #2563eb;

            transform:
                scale(1.08);

            box-shadow:
                0 5px 12px
                rgba(37, 99, 235, 0.20);
        }

        /* =====================================================
           ROLE TEXT
        ===================================================== */

        .role-info strong {
            display: block;

            font-size: 12px;

            color: #172033;

            margin-bottom: 2px;
        }

        .role-info span {
            display: block;

            font-size: 10px;

            color: #64748b;
        }

        .role-option input:checked + .role-card
        .role-info strong {
            color: #1d4ed8;
        }

        /* =====================================================
           FORM GROUPS
        ===================================================== */

        .form-group {
            margin-bottom: 15px;

            animation:
                formEnter 0.7s
                0.35s
                both;
        }

        .form-group label {
            display: block;

            margin-bottom: 7px;

            font-size: 13px;
            font-weight: 700;

            color: #334155;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            height: 43px;

            padding: 0 13px;

            border: 1px solid #dbe2ea;

            border-radius: 10px;

            background: #ffffff;

            color: #172033;

            font-size: 13px;

            outline: none;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                transform 0.2s ease;
        }

        .input-wrapper input::placeholder {
            color: #94a3b8;
        }

        .input-wrapper input:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.10);

            transform:
                translateY(-1px);
        }

        /* =====================================================
           PASSWORD
        ===================================================== */

        .password-wrapper input {
            padding-right: 45px;
        }

        .password-toggle {
            position: absolute;

            top: 50%;
            right: 11px;

            transform:
                translateY(-50%);

            border: 0;

            background: transparent;

            color: #64748b;

            cursor: pointer;

            font-size: 16px;

            width: 28px;
            height: 28px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 7px;

            transition:
                color 0.2s ease,
                background 0.2s ease,
                transform 0.2s ease;
        }

        .password-toggle:hover {
            color: #2563eb;

            background: #eff6ff;

            transform:
                translateY(-50%)
                scale(1.08);
        }

        /* =====================================================
           LOGIN BUTTON
        ===================================================== */

        .login-button {
            width: 100%;

            min-height: 44px;

            border: 0;

            border-radius: 10px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #1d4ed8
                );

            color: #ffffff;

            font-size: 14px;
            font-weight: 700;

            cursor: pointer;

            position: relative;

            overflow: hidden;

            box-shadow:
                0 8px 18px
                rgba(37, 99, 235, 0.22);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .login-button::before {
            content: "";

            position: absolute;

            top: 0;
            left: -120%;

            width: 70%;
            height: 100%;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255, 255, 255, 0.25),
                    transparent
                );

            transform:
                skewX(-20deg);

            transition:
                left 0.6s ease;
        }

        .login-button:hover::before {
            left: 140%;
        }

        .login-button:hover {
            transform:
                translateY(-2px);

            box-shadow:
                0 12px 26px
                rgba(37, 99, 235, 0.30);
        }

        .login-button:active {
            transform:
                translateY(0)
                scale(0.99);
        }

        /* =====================================================
           REGISTER
        ===================================================== */

        .register-link {
            margin-top: 17px;

            text-align: center;

            font-size: 12px;

            color: #64748b;

            animation:
                formEnter 0.7s
                0.45s
                both;
        }

        .register-link a {
            color: #2563eb;

            font-weight: 700;

            text-decoration: none;

            transition:
                color 0.2s ease;
        }

        .register-link a:hover {
            color: #1d4ed8;

            text-decoration: underline;
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        .auth-footer {
            margin-top: 18px;

            text-align: center;

            font-size: 10px;

            line-height: 1.5;

            color: #94a3b8;

            animation:
                formEnter 0.7s
                0.55s
                both;
        }

        .auth-footer strong {
            color: #64748b;
        }

        /* =====================================================
           TABLET
        ===================================================== */

        @media (max-width: 900px) {

            .auth-page {
                padding: 18px;
            }

            .auth-container {
                max-width: 760px;

                grid-template-columns:
                    0.9fr 1.1fr;
            }

            .auth-brand-panel {
                min-height: 500px;
                padding: 25px;
            }

            .auth-form-panel {
                padding: 28px;
            }

            .auth-logo {
                width: 82px;
                height: 82px;
            }

            .brand-title {
                font-size: 34px;
            }

            .brand-tagline {
                font-size: 14px;
            }

            .form-header h1 {
                font-size: 27px;
            }
        }

        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 700px) {

            .auth-page {
                min-height: 100vh;

                padding: 14px;

                align-items: flex-start;
            }

            .auth-container {
                width: 100%;

                display: block;

                max-width: 520px;

                border-radius: 18px;
            }

            .auth-brand-panel {
                min-height: auto;

                padding: 25px 22px;

                text-align: center;
            }

            .brand-content {
                display: flex;

                flex-direction: column;

                align-items: center;
            }

            .auth-logo {
                width: 76px;
                height: 76px;

                margin-bottom: 12px;
            }

            .brand-title {
                font-size: 31px;

                letter-spacing: -1px;
            }

            .brand-tagline {
                max-width: 330px;

                margin-bottom: 18px;

                font-size: 13px;
            }

            .brand-features {
                width: 100%;

                max-width: 330px;

                gap: 8px;

                text-align: left;
            }

            .brand-feature {
                font-size: 12px;
            }

            .feature-icon {
                width: 25px;
                height: 25px;

                flex-basis: 25px;

                font-size: 12px;
            }

            .auth-form-panel {
                padding: 25px 20px 22px;
            }

            .form-header {
                margin-bottom: 18px;
            }

            .form-header h1 {
                font-size: 25px;
            }

            .form-header p {
                font-size: 13px;
            }

            .role-card {
                min-height: 61px;
            }
        }

        /* =====================================================
           SMALL MOBILE
        ===================================================== */

        @media (max-width: 420px) {

            .auth-page {
                padding: 8px;
            }

            .auth-container {
                border-radius: 15px;
            }

            .auth-brand-panel {
                padding: 21px 16px;
            }

            .auth-logo {
                width: 70px;
                height: 70px;
            }

            .brand-title {
                font-size: 28px;
            }

            .brand-tagline {
                font-size: 12px;

                margin-bottom: 15px;
            }

            .brand-features {
                gap: 6px;
            }

            .brand-feature {
                font-size: 11px;
            }

            .auth-form-panel {
                padding: 22px 15px 18px;
            }

            .role-grid {
                grid-template-columns:
                    1fr 1fr;

                gap: 7px;
            }

            .role-card {
                min-height: 58px;

                padding: 8px;
            }

            .role-icon {
                width: 30px;
                height: 30px;

                flex-basis: 30px;

                font-size: 15px;
            }

            .role-info strong {
                font-size: 11px;
            }

            .role-info span {
                font-size: 9px;
            }

            .form-group {
                margin-bottom: 13px;
            }
        }

        /* =====================================================
           SHORT LAPTOP SCREENS
        ===================================================== */

        @media (max-height: 720px) and (min-width: 701px) {

            .auth-page {
                padding: 12px;
            }

            .auth-brand-panel {
                min-height: 500px;

                padding: 24px;
            }

            .auth-form-panel {
                padding: 24px 34px;
            }

            .auth-logo {
                width: 76px;
                height: 76px;

                margin-bottom: 12px;
            }

            .brand-title {
                font-size: 35px;
            }

            .brand-tagline {
                margin-bottom: 18px;
            }

            .brand-features {
                gap: 8px;
            }

            .role-card {
                min-height: 59px;
            }

            .form-header {
                margin-bottom: 14px;
            }

            .form-header h1 {
                font-size: 26px;
            }

            .form-group {
                margin-bottom: 11px;
            }

            .auth-footer {
                margin-top: 12px;
            }
        }

        /* =====================================================
           REDUCED MOTION
        ===================================================== */

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation: none !important;
                transition: none !important;
            }
        }

    </style>

</head>

<body>

    <!-- Animated background -->

    <div class="background-circle one"></div>
    <div class="background-circle two"></div>
    <div class="background-circle three"></div>

    <div class="auth-page">

        <div class="auth-container">

            <!-- =================================================
                 BRAND PANEL
            ================================================== -->

            <section class="auth-brand-panel">

                <div class="brand-content">

                    <img
                        src="../assets/images/sportsync-mark.svg"
                        alt="SportSync Logo"
                        class="auth-logo"
                    >

                    <h2 class="brand-title">
                        SportSync
                    </h2>

                    <p class="brand-tagline">
                        Smart Sports Management System
                    </p>

                    <div class="brand-features">

                        <div class="brand-feature">

                            <span class="feature-icon">
                                🏆
                            </span>

                            <span>
                                Manage sports and tournaments
                            </span>

                        </div>

                        <div class="brand-feature">

                            <span class="feature-icon">
                                👥
                            </span>

                            <span>
                                Connect players, teams and coaches
                            </span>

                        </div>

                        <div class="brand-feature">

                            <span class="feature-icon">
                                📊
                            </span>

                            <span>
                                Track matches, results and statistics
                            </span>

                        </div>

                    </div>

                </div>

            </section>

            <!-- =================================================
                 LOGIN FORM
            ================================================== -->

            <section class="auth-form-panel">

                <div class="form-header">

                    <h1>
                        Welcome Back
                    </h1>

                    <p>
                        Sign in to continue to your SportSync account.
                    </p>

                </div>

                <?php if (!empty($error)): ?>

                    <div class="login-error">

                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                <?php endif; ?>

                <form
                    action="login-process.php"
                    method="POST"
                    autocomplete="on"
                    id="loginForm"
                >

                    <!-- =================================================
                         CSRF PROTECTION
                    ================================================== -->

                    <?= csrfField() ?>

                    <!-- =========================================
                         ROLE SELECTION
                    ========================================== -->

                    <div class="role-section">

                        <span class="field-label">
                            Select your role
                        </span>

                        <div class="role-grid">

                            <!-- PLAYER -->

                            <label class="role-option">

                                <input
                                    type="radio"
                                    name="selected_role"
                                    value="PLAYER"
                                    checked
                                    required
                                >

                                <div class="role-card">

                                    <div class="role-icon">
                                        🧑‍🎓
                                    </div>

                                    <div class="role-info">

                                        <strong>
                                            Player
                                        </strong>

                                        <span>
                                            Student / Athlete
                                        </span>

                                    </div>

                                </div>

                            </label>

                            <!-- COACH -->

                            <label class="role-option">

                                <input
                                    type="radio"
                                    name="selected_role"
                                    value="COACH"
                                    required
                                >

                                <div class="role-card">

                                    <div class="role-icon">
                                        🧑‍🏫
                                    </div>

                                    <div class="role-info">

                                        <strong>
                                            Coach
                                        </strong>

                                        <span>
                                            Team Coach
                                        </span>

                                    </div>

                                </div>

                            </label>

                            <!-- COORDINATOR -->

                            <label class="role-option">

                                <input
                                    type="radio"
                                    name="selected_role"
                                    value="SPORTS_COORDINATOR"
                                    required
                                >

                                <div class="role-card">

                                    <div class="role-icon">
                                        🏅
                                    </div>

                                    <div class="role-info">

                                        <strong>
                                            Coordinator
                                        </strong>

                                        <span>
                                            Sports Coordinator
                                        </span>

                                    </div>

                                </div>

                            </label>

                            <!-- ADMIN -->

                            <label class="role-option">

                                <input
                                    type="radio"
                                    name="selected_role"
                                    value="ADMIN"
                                    required
                                >

                                <div class="role-card">

                                    <div class="role-icon">
                                        ⚙️
                                    </div>

                                    <div class="role-info">

                                        <strong>
                                            Admin
                                        </strong>

                                        <span>
                                            System Administrator
                                        </span>

                                    </div>

                                </div>

                            </label>

                        </div>

                    </div>

                    <!-- =========================================
                         EMAIL
                    ========================================== -->

                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <div class="input-wrapper">

                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="Enter your email"
                                autocomplete="email"
                                required
                            >

                        </div>

                    </div>

                    <!-- =========================================
                         PASSWORD
                    ========================================== -->

                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <div class="input-wrapper password-wrapper">

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                id="passwordToggle"
                                aria-label="Show password"
                            >
                                👁
                            </button>

                        </div>

                    </div>

                    <!-- =========================================
                         LOGIN
                    ========================================== -->

                    <button
                        type="submit"
                        class="login-button"
                    >
                        Sign In
                    </button>

                </form>

                <!-- =============================================
                     REGISTER
                ============================================== -->

                <div class="register-link">

                    Don't have an account?

                    <a href="register.php">
                        Register as a Player
                    </a>

                </div>

                <!-- =============================================
                     FOOTER
                ============================================== -->

                <div class="auth-footer">

                    <div>

                        <strong>SportSync</strong>
                        — Smart Sports Management System

                    </div>

                    <div>

                        Developed by:

                        <strong>
                            Soyab • Ashish • Siddiq
                        </strong>

                    </div>

                </div>

            </section>

        </div>

    </div>

    <!-- =====================================================
         JAVASCRIPT
    ====================================================== -->

    <script>

        /* =================================================
           PASSWORD VISIBILITY
        ================================================= */

        const passwordInput =
            document.getElementById('password');

        const passwordToggle =
            document.getElementById('passwordToggle');

        if (passwordInput && passwordToggle) {

            passwordToggle.addEventListener(
                'click',
                function () {

                    const isPassword =
                        passwordInput.type === 'password';

                    passwordInput.type =
                        isPassword
                            ? 'text'
                            : 'password';

                    passwordToggle.textContent =
                        isPassword
                            ? '🙈'
                            : '👁';

                    passwordToggle.setAttribute(
                        'aria-label',
                        isPassword
                            ? 'Hide password'
                            : 'Show password'
                    );

                }
            );

        }

        /* =================================================
           ROLE SELECTION
        ================================================= */

        const roleInputs =
            document.querySelectorAll(
                'input[name="selected_role"]'
            );

        roleInputs.forEach(function (input) {

            input.addEventListener(
                'change',
                function () {

                    /*
                     * Small visual pulse on the selected
                     * role card.
                     */

                    const card =
                        input.nextElementSibling;

                    if (!card) {
                        return;
                    }

                    card.style.animation = 'none';

                    /*
                     * Force browser to restart animation.
                     */

                    void card.offsetWidth;

                    card.style.animation =
                        'selectedRole 0.35s ease';

                    /*
                     * Update login button text so the
                     * selected role is immediately obvious.
                     */

                    const loginButton =
                        document.querySelector(
                            '.login-button'
                        );

                    if (loginButton) {

                        const roleNames = {

                            PLAYER: 'Player',

                            COACH: 'Coach',

                            SPORTS_COORDINATOR:
                                'Coordinator',

                            ADMIN: 'Admin'
                        };

                        const roleName =
                            roleNames[input.value]
                            || '';

                        loginButton.textContent =
                            'Sign In as ' + roleName;
                    }

                }
            );

        });

        /* =================================================
           INITIAL LOGIN BUTTON
        ================================================= */

        const initiallySelectedRole =
            document.querySelector(
                'input[name="selected_role"]:checked'
            );

        const initialLoginButton =
            document.querySelector(
                '.login-button'
            );

        if (
            initiallySelectedRole &&
            initialLoginButton
        ) {

            const roleNames = {

                PLAYER: 'Player',

                COACH: 'Coach',

                SPORTS_COORDINATOR:
                    'Coordinator',

                ADMIN: 'Admin'
            };

            initialLoginButton.textContent =
                'Sign In as ' +
                (
                    roleNames[
                        initiallySelectedRole.value
                    ] || 'Player'
                );
        }

        /* =================================================
           FORM SUBMIT FEEDBACK
        ================================================= */

        const loginForm =
            document.getElementById('loginForm');

        if (loginForm) {

            loginForm.addEventListener(
                'submit',
                function () {

                    const loginButton =
                        document.querySelector(
                            '.login-button'
                        );

                    if (loginButton) {

                        loginButton.textContent =
                            'Signing In...';

                        loginButton.style.opacity =
                            '0.85';

                        loginButton.style.cursor =
                            'wait';
                    }

                }
            );

        }

    </script>

</body>

</html>