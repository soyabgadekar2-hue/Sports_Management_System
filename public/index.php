<?php
/*
|--------------------------------------------------------------------------
| SportSync - Landing Page
|--------------------------------------------------------------------------
| Smart Sports Management System
| Developed by: Soyab • Ashish • Siddiq
|--------------------------------------------------------------------------
*/
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        SportSync | Smart Sports Management System
    </title>

    <meta
        name="description"
        content="SportSync is a smart sports management system for managing players, teams, coaches, tournaments, matches and statistics."
    >

    <meta
        name="theme-color"
        content="#2563eb"
    >

    <link
        rel="icon"
        type="image/svg+xml"
        href="../assets/images/sportsync-mark.svg"
    >

    <style>

        /* =====================================================
           ROOT
        ===================================================== */

        :root {

            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-deep: #0f2f9e;
            --primary-light: #eff6ff;

            --dark: #0f172a;
            --dark-2: #172033;
            --text: #334155;
            --muted: #64748b;

            --white: #ffffff;
            --surface: #f8fafc;
            --border: #e2e8f0;

            --success: #10b981;

            --radius-sm: 10px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --radius-xl: 32px;

            --shadow-sm:
                0 5px 20px rgba(15, 23, 42, 0.06);

            --shadow-md:
                0 15px 40px rgba(15, 23, 42, 0.08);

            --shadow-lg:
                0 25px 70px rgba(15, 23, 42, 0.12);

            --transition:
                0.25s ease;
        }


        /* =====================================================
           RESET
        ===================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            width: 100%;

            min-height: 100vh;

            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            color: var(--dark);

            background:
                var(--white);

            overflow-x: hidden;
        }


        a {
            color: inherit;
            text-decoration: none;
        }


        button {
            font: inherit;
        }


        img {
            max-width: 100%;
            display: block;
        }


        .container {

            width: min(
                1180px,
                calc(100% - 40px)
            );

            margin: 0 auto;
        }


        /* =====================================================
           SCROLL PROGRESS
        ===================================================== */

        .scroll-progress {

            position: fixed;

            top: 0;
            left: 0;

            width: 0%;
            height: 3px;

            z-index: 9999;

            background:
                linear-gradient(
                    90deg,
                    #60a5fa,
                    #2563eb,
                    #1d4ed8
                );
        }


        /* =====================================================
           NAVBAR
        ===================================================== */

        .navbar {

            position: fixed;

            top: 0;
            left: 0;

            width: 100%;

            z-index: 1000;

            padding: 16px 0;

            transition:
                background 0.3s ease,
                box-shadow 0.3s ease,
                padding 0.3s ease;
        }


        .navbar.scrolled {

            padding: 10px 0;

            background:
                rgba(255, 255, 255, 0.94);

            backdrop-filter:
                blur(18px);

            -webkit-backdrop-filter:
                blur(18px);

            box-shadow:
                0 5px 25px
                rgba(15, 23, 42, 0.08);
        }


        .nav-inner {

            display: flex;

            align-items: center;

            justify-content: space-between;
        }


        .brand {

            display: flex;

            align-items: center;

            gap: 11px;

            flex-shrink: 0;
        }


        .brand-logo {

            width: 48px;
            height: 48px;

            object-fit: contain;

            filter:
                drop-shadow(
                    0 5px 10px
                    rgba(37, 99, 235, 0.18)
                );

            transition:
                transform var(--transition);
        }


        .brand:hover .brand-logo {

            transform:
                rotate(-3deg)
                scale(1.05);
        }


        .brand-text {

            display: flex;

            flex-direction: column;
        }


        .brand-name {

            font-size: 21px;

            line-height: 1;

            font-weight: 800;

            letter-spacing: -0.5px;

            color: var(--dark);
        }


        .brand-tagline {

            margin-top: 4px;

            font-size: 9px;

            font-weight: 600;

            letter-spacing: 0.4px;

            color: var(--muted);
        }


        .nav-links {

            display: flex;

            align-items: center;

            gap: 30px;
        }


        .nav-links a {

            position: relative;

            font-size: 13px;

            font-weight: 600;

            color: #475569;

            transition:
                color var(--transition);
        }


        .nav-links a::after {

            content: "";

            position: absolute;

            left: 0;
            bottom: -7px;

            width: 0;
            height: 2px;

            border-radius: 10px;

            background:
                var(--primary);

            transition:
                width var(--transition);
        }


        .nav-links a:hover {

            color: var(--primary);
        }


        .nav-links a:hover::after {

            width: 100%;
        }


        .nav-actions {

            display: flex;

            align-items: center;

            gap: 9px;
        }


        .nav-login {

            padding: 10px 17px;

            border:
                1px solid var(--border);

            border-radius: 10px;

            font-size: 12px;

            font-weight: 700;

            color: var(--dark);

            background:
                var(--white);

            transition:
                all var(--transition);
        }


        .nav-login:hover {

            border-color:
                #bfdbfe;

            color:
                var(--primary);

            background:
                #eff6ff;
        }


        .nav-register {

            padding: 10px 17px;

            border-radius: 10px;

            font-size: 12px;

            font-weight: 700;

            color: var(--white);

            background:
                var(--primary);

            box-shadow:
                0 7px 18px
                rgba(37, 99, 235, 0.20);

            transition:
                all var(--transition);
        }


        .nav-register:hover {

            transform:
                translateY(-2px);

            background:
                var(--primary-dark);

            box-shadow:
                0 10px 25px
                rgba(37, 99, 235, 0.28);
        }


        .mobile-menu-btn {

            display: none;

            width: 42px;
            height: 42px;

            border: 1px solid var(--border);

            border-radius: 10px;

            background: var(--white);

            color: var(--dark);

            cursor: pointer;
        }


        /* =====================================================
           HERO
        ===================================================== */

        .hero {

            position: relative;

            min-height: 760px;

            display: flex;

            align-items: center;

            padding:
                130px 0 90px;

            overflow: hidden;

            background:

                radial-gradient(
                    circle at 5% 15%,
                    rgba(37, 99, 235, 0.10),
                    transparent 28%
                ),

                radial-gradient(
                    circle at 90% 30%,
                    rgba(59, 130, 246, 0.12),
                    transparent 28%
                ),

                linear-gradient(
                    180deg,
                    #f8fbff 0%,
                    #ffffff 100%
                );
        }


        .hero-grid {

            display: grid;

            grid-template-columns:
                1fr 0.9fr;

            align-items: center;

            gap: 70px;
        }


        .hero-content {

            position: relative;

            z-index: 2;
        }


        .hero-badge {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding: 7px 11px;

            margin-bottom: 20px;

            border:
                1px solid #bfdbfe;

            border-radius: 100px;

            background:
                rgba(239, 246, 255, 0.8);

            color:
                var(--primary-dark);

            font-size: 11px;

            font-weight: 700;

            animation:
                revealUp 0.7s
                0.1s both;
        }


        .badge-dot {

            width: 7px;
            height: 7px;

            border-radius: 50%;

            background:
                #22c55e;

            box-shadow:
                0 0 0 4px
                rgba(34, 197, 94, 0.12);

            animation:
                pulseDot 2s
                infinite;
        }


        @keyframes pulseDot {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }


        .hero-title {

            max-width: 680px;

            font-size:
                clamp(
                    46px,
                    6vw,
                    72px
                );

            line-height: 1.02;

            letter-spacing: -3.5px;

            font-weight: 850;

            color: var(--dark);

            animation:
                revealUp 0.8s
                0.2s both;
        }


        .hero-title span {

            display: inline-block;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #1d4ed8
                );

            -webkit-background-clip:
                text;

            background-clip:
                text;

            color: transparent;
        }


        .hero-description {

            max-width: 590px;

            margin-top: 23px;

            font-size: 17px;

            line-height: 1.75;

            color: var(--muted);

            animation:
                revealUp 0.8s
                0.3s both;
        }


        .hero-actions {

            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 11px;

            margin-top: 31px;

            animation:
                revealUp 0.8s
                0.4s both;
        }


        .btn-primary {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            min-height: 48px;

            padding: 0 20px;

            border-radius: 11px;

            color: var(--white);

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #1d4ed8
                );

            font-size: 13px;

            font-weight: 750;

            box-shadow:
                0 10px 25px
                rgba(37, 99, 235, 0.22);

            transition:
                all var(--transition);
        }


        .btn-primary:hover {

            transform:
                translateY(-3px);

            box-shadow:
                0 15px 32px
                rgba(37, 99, 235, 0.30);
        }


        .btn-secondary {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            min-height: 48px;

            padding: 0 20px;

            border:
                1px solid var(--border);

            border-radius: 11px;

            color: var(--dark);

            background:
                var(--white);

            font-size: 13px;

            font-weight: 750;

            transition:
                all var(--transition);
        }


        .btn-secondary:hover {

            border-color:
                #bfdbfe;

            color:
                var(--primary);

            background:
                #f8fbff;

            transform:
                translateY(-2px);
        }


        .hero-note {

            margin-top: 18px;

            font-size: 11px;

            color: #94a3b8;

            animation:
                revealUp 0.8s
                0.5s both;
        }


        /* =====================================================
           HERO VISUAL
        ===================================================== */

        .hero-visual {

            position: relative;

            min-height: 510px;

            display: flex;

            align-items: center;

            justify-content: center;

            animation:
                visualEnter 1s
                0.2s both;
        }


        @keyframes visualEnter {

            from {

                opacity: 0;

                transform:
                    translateX(35px)
                    scale(0.96);
            }

            to {

                opacity: 1;

                transform:
                    translateX(0)
                    scale(1);
            }
        }


        .visual-glow {

            position: absolute;

            width: 380px;
            height: 380px;

            border-radius: 50%;

            background:
                radial-gradient(
                    circle,
                    rgba(37, 99, 235, 0.18),
                    rgba(37, 99, 235, 0)
                );

            animation:
                visualGlow 5s
                ease-in-out
                infinite;
        }


        @keyframes visualGlow {

            0%,
            100% {
                transform:
                    scale(1);
            }

            50% {
                transform:
                    scale(1.12);
            }
        }


        .sports-orbit {

            position: absolute;

            width: 400px;
            height: 400px;

            border:
                1px dashed
                rgba(37, 99, 235, 0.20);

            border-radius: 50%;

            animation:
                orbitRotate 22s
                linear
                infinite;
        }


        @keyframes orbitRotate {

            from {
                transform:
                    rotate(0deg);
            }

            to {
                transform:
                    rotate(360deg);
            }
        }


        .sports-orbit::before,
        .sports-orbit::after {

            content: "";

            position: absolute;

            width: 9px;
            height: 9px;

            border-radius: 50%;

            background:
                var(--primary);

            box-shadow:
                0 0 0 6px
                rgba(37, 99, 235, 0.10);
        }


        .sports-orbit::before {

            top: 30px;
            left: 65px;
        }


        .sports-orbit::after {

            right: 45px;
            bottom: 65px;
        }


        .hero-card {

            position: relative;

            z-index: 3;

            width: 340px;

            padding: 25px;

            border:
                1px solid
                rgba(255, 255, 255, 0.8);

            border-radius: 28px;

            background:
                rgba(255, 255, 255, 0.88);

            backdrop-filter:
                blur(20px);

            -webkit-backdrop-filter:
                blur(20px);

            box-shadow:
                0 30px 80px
                rgba(15, 23, 42, 0.14);

            animation:
                heroCardFloat 5s
                ease-in-out
                infinite;
        }


        @keyframes heroCardFloat {

            0%,
            100% {
                transform:
                    translateY(0);
            }

            50% {
                transform:
                    translateY(-10px);
            }
        }


        .hero-card-top {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 20px;
        }


        .hero-card-title {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .hero-mini-logo {

            width: 40px;
            height: 40px;

            object-fit: contain;
        }


        .hero-card-title strong {

            display: block;

            font-size: 14px;

            color: var(--dark);
        }


        .hero-card-title span {

            display: block;

            margin-top: 3px;

            font-size: 9px;

            color: var(--muted);
        }


        .live-badge {

            display: flex;

            align-items: center;

            gap: 5px;

            padding: 5px 8px;

            border-radius: 100px;

            color: #047857;

            background:
                #ecfdf5;

            font-size: 9px;

            font-weight: 750;
        }


        .live-dot {

            width: 5px;
            height: 5px;

            border-radius: 50%;

            background:
                #10b981;

            animation:
                pulseDot 1.5s
                infinite;
        }


        .match-card {

            padding: 17px;

            border-radius: 17px;

            background:
                linear-gradient(
                    145deg,
                    #eff6ff,
                    #f8fafc
                );

            border:
                1px solid #dbeafe;
        }


        .match-label {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 15px;

            font-size: 9px;

            font-weight: 700;

            color: var(--muted);
        }


        .match-teams {

            display: grid;

            grid-template-columns:
                1fr auto 1fr;

            align-items: center;

            gap: 10px;
        }


        .match-team {

            text-align: center;
        }


        .team-ball {

            width: 52px;
            height: 52px;

            margin: 0 auto 8px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 15px;

            font-size: 25px;

            background:
                var(--white);

            box-shadow:
                0 7px 20px
                rgba(15, 23, 42, 0.08);
        }


        .match-team strong {

            display: block;

            font-size: 10px;

            color: var(--dark);
        }


        .score {

            font-size: 22px;

            font-weight: 850;

            color: var(--primary);
        }


        .score small {

            display: block;

            margin-top: 2px;

            font-size: 8px;

            font-weight: 600;

            color: var(--muted);
        }


        .hero-stats {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 8px;

            margin-top: 10px;
        }


        .hero-stat {

            padding: 11px;

            text-align: center;

            border:
                1px solid var(--border);

            border-radius: 12px;

            background:
                var(--white);
        }


        .hero-stat strong {

            display: block;

            font-size: 17px;

            color: var(--dark);
        }


        .hero-stat span {

            display: block;

            margin-top: 2px;

            font-size: 8px;

            color: var(--muted);
        }


        /* =====================================================
           FLOATING SPORTS
        ===================================================== */

        .floating-sport {

            position: absolute;

            z-index: 4;

            width: 54px;
            height: 54px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 16px;

            background:
                rgba(255, 255, 255, 0.92);

            border:
                1px solid
                rgba(226, 232, 240, 0.9);

            box-shadow:
                0 15px 30px
                rgba(15, 23, 42, 0.10);

            font-size: 25px;

            backdrop-filter:
                blur(10px);
        }


        .floating-sport.football {

            top: 48px;
            left: 55px;

            animation:
                floatSportOne 4.5s
                ease-in-out
                infinite;
        }


        .floating-sport.cricket {

            right: 35px;
            top: 105px;

            animation:
                floatSportTwo 5.5s
                ease-in-out
                infinite;
        }


        .floating-sport.basketball {

            left: 25px;
            bottom: 95px;

            animation:
                floatSportThree 5s
                ease-in-out
                infinite;
        }


        .floating-sport.volleyball {

            right: 40px;
            bottom: 55px;

            animation:
                floatSportFour 4.8s
                ease-in-out
                infinite;
        }


        @keyframes floatSportOne {

            0%,
            100% {
                transform:
                    translateY(0)
                    rotate(-5deg);
            }

            50% {
                transform:
                    translateY(-14px)
                    rotate(5deg);
            }
        }


        @keyframes floatSportTwo {

            0%,
            100% {
                transform:
                    translateY(0)
                    rotate(4deg);
            }

            50% {
                transform:
                    translateY(12px)
                    rotate(-5deg);
            }
        }


        @keyframes floatSportThree {

            0%,
            100% {
                transform:
                    translateY(0);
            }

            50% {
                transform:
                    translateY(-11px);
            }
        }


        @keyframes floatSportFour {

            0%,
            100% {
                transform:
                    translateY(0)
                    rotate(4deg);
            }

            50% {
                transform:
                    translateY(13px)
                    rotate(-4deg);
            }
        }


        /* =====================================================
           REVEAL ANIMATION
        ===================================================== */

        .reveal {

            opacity: 0;

            transform:
                translateY(30px);

            transition:
                opacity 0.7s ease,
                transform 0.7s ease;
        }


        .reveal.visible {

            opacity: 1;

            transform:
                translateY(0);
        }


        @keyframes revealUp {

            from {

                opacity: 0;

                transform:
                    translateY(22px);
            }

            to {

                opacity: 1;

                transform:
                    translateY(0);
            }
        }


        /* =====================================================
           SECTION HEADER
        ===================================================== */

        .section {

            padding: 100px 0;
        }


        .section-header {

            max-width: 670px;

            margin:
                0 auto 55px;

            text-align: center;
        }


        .section-label {

            display: inline-block;

            margin-bottom: 10px;

            font-size: 10px;

            font-weight: 800;

            letter-spacing: 1.2px;

            text-transform: uppercase;

            color:
                var(--primary);
        }


        .section-title {

            font-size:
                clamp(
                    30px,
                    4vw,
                    44px
                );

            line-height: 1.1;

            letter-spacing: -1.5px;

            font-weight: 800;

            color: var(--dark);
        }


        .section-description {

            margin-top: 14px;

            font-size: 15px;

            line-height: 1.7;

            color: var(--muted);
        }


        /* =====================================================
           SPORTS
        ===================================================== */

        .sports-section {

            background:
                #ffffff;
        }


        .sports-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 17px;
        }


        .sport-card {

            position: relative;

            min-height: 300px;

            padding: 22px;

            display: flex;

            flex-direction: column;

            justify-content: flex-end;

            border-radius: 21px;

            overflow: hidden;

            color: var(--white);

            isolation: isolate;

            box-shadow:
                var(--shadow-sm);

            transition:
                transform 0.35s ease,
                box-shadow 0.35s ease;
        }


        .sport-card::before {

            content: "";

            position: absolute;

            inset: 0;

            z-index: -2;

            background-size:
                cover;

            background-position:
                center;

            transition:
                transform 0.5s ease;
        }


        .sport-card::after {

            content: "";

            position: absolute;

            inset: 0;

            z-index: -1;

            background:
                linear-gradient(
                    180deg,
                    rgba(15, 23, 42, 0.05) 15%,
                    rgba(15, 23, 42, 0.82) 100%
                );
        }


        .sport-card:hover {

            transform:
                translateY(-8px);

            box-shadow:
                var(--shadow-lg);
        }


        .sport-card:hover::before {

            transform:
                scale(1.08);
        }


        .sport-card.football::before {

            background-image:
                url(
                    "https://images.unsplash.com/photo-1579952363873-27f3bade9f55?auto=format&fit=crop&w=900&q=80"
                );
        }


        .sport-card.cricket::before {

            background-image:
                url(
                    "https://images.unsplash.com/photo-1531415074968-036ba1b575da?auto=format&fit=crop&w=900&q=80"
                );
        }


        .sport-card.basketball::before {

            background-image:
                url(
                    "https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=900&q=80"
                );
        }


        .sport-card.volleyball::before {

            background-image:
                url(
                    "https://images.unsplash.com/photo-1612872087720-bb876e2e67d1?auto=format&fit=crop&w=900&q=80"
                );
        }


        .sport-icon {

            position: absolute;

            top: 20px;
            right: 20px;

            width: 48px;
            height: 48px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 14px;

            background:
                rgba(255, 255, 255, 0.17);

            backdrop-filter:
                blur(10px);

            font-size: 23px;
        }


        .sport-card h3 {

            font-size: 20px;

            font-weight: 800;

            margin-bottom: 6px;
        }


        .sport-card p {

            font-size: 11px;

            line-height: 1.6;

            color:
                rgba(255, 255, 255, 0.82);
        }


        /* =====================================================
           FEATURES
        ===================================================== */

        .features-section {

            background:
                var(--surface);
        }


        .features-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 17px;
        }


        .feature-card {

            padding: 26px;

            border:
                1px solid var(--border);

            border-radius: 19px;

            background:
                var(--white);

            box-shadow:
                var(--shadow-sm);

            transition:
                all 0.3s ease;
        }


        .feature-card:hover {

            transform:
                translateY(-6px);

            border-color:
                #bfdbfe;

            box-shadow:
                var(--shadow-md);
        }


        .feature-number {

            font-size: 10px;

            font-weight: 800;

            color:
                #93c5fd;
        }


        .feature-icon-box {

            width: 50px;
            height: 50px;

            margin:
                16px 0 17px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 14px;

            color:
                var(--primary);

            background:
                var(--primary-light);

            font-size: 22px;

            transition:
                transform 0.3s ease;
        }


        .feature-card:hover
        .feature-icon-box {

            transform:
                rotate(-5deg)
                scale(1.08);
        }


        .feature-card h3 {

            font-size: 17px;

            font-weight: 800;

            color: var(--dark);

            margin-bottom: 8px;
        }


        .feature-card p {

            font-size: 12px;

            line-height: 1.7;

            color: var(--muted);
        }


        /* =====================================================
           HOW IT WORKS
        ===================================================== */

        .workflow-section {

            background:
                #ffffff;
        }


        .workflow {

            position: relative;

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 25px;
        }


        .workflow-line {

            position: absolute;

            top: 31px;

            left: 11%;

            right: 11%;

            height: 1px;

            background:
                linear-gradient(
                    90deg,
                    #bfdbfe,
                    #2563eb,
                    #bfdbfe
                );

            z-index: 0;
        }


        .workflow-item {

            position: relative;

            z-index: 1;

            text-align: center;
        }


        .workflow-number {

            width: 62px;
            height: 62px;

            margin:
                0 auto 18px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 50%;

            border:
                5px solid #ffffff;

            background:
                var(--primary);

            color:
                var(--white);

            font-size: 16px;

            font-weight: 850;

            box-shadow:
                0 10px 25px
                rgba(37, 99, 235, 0.20);
        }


        .workflow-item h3 {

            font-size: 15px;

            font-weight: 800;

            margin-bottom: 7px;
        }


        .workflow-item p {

            max-width: 190px;

            margin: 0 auto;

            font-size: 11px;

            line-height: 1.6;

            color: var(--muted);
        }


        /* =====================================================
           STATS
        ===================================================== */

        .stats-section {

            padding: 70px 0;

            background:
                linear-gradient(
                    135deg,
                    #0f2f9e,
                    #1d4ed8
                );

            color: var(--white);

            overflow: hidden;

            position: relative;
        }


        .stats-section::before {

            content: "";

            position: absolute;

            width: 450px;
            height: 450px;

            border-radius: 50%;

            right: -180px;
            top: -250px;

            background:
                rgba(255, 255, 255, 0.06);
        }


        .stats-grid {

            position: relative;

            z-index: 2;

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;
        }


        .stat-item {

            padding: 20px;

            text-align: center;

            border:
                1px solid
                rgba(255, 255, 255, 0.12);

            border-radius: 17px;

            background:
                rgba(255, 255, 255, 0.07);

            backdrop-filter:
                blur(10px);
        }


        .stat-number {

            font-size: 31px;

            line-height: 1;

            font-weight: 850;
        }


        .stat-label {

            margin-top: 8px;

            font-size: 10px;

            color:
                rgba(255, 255, 255, 0.70);
        }


        /* =====================================================
           CTA
        ===================================================== */

        .cta-section {

            padding: 100px 0;

            background:
                #ffffff;
        }


        .cta-box {

            position: relative;

            padding:
                65px 55px;

            text-align: center;

            border-radius: 30px;

            overflow: hidden;

            background:
                linear-gradient(
                    135deg,
                    #eff6ff,
                    #f8fafc
                );

            border:
                1px solid #dbeafe;
        }


        .cta-box::before {

            content: "";

            position: absolute;

            width: 220px;
            height: 220px;

            top: -130px;
            left: -70px;

            border-radius: 50%;

            background:
                rgba(37, 99, 235, 0.08);
        }


        .cta-box h2 {

            position: relative;

            z-index: 2;

            font-size:
                clamp(
                    29px,
                    4vw,
                    43px
                );

            letter-spacing: -1.5px;

            font-weight: 850;
        }


        .cta-box p {

            position: relative;

            z-index: 2;

            max-width: 570px;

            margin:
                13px auto 24px;

            font-size: 14px;

            line-height: 1.7;

            color: var(--muted);
        }


        .cta-actions {

            position: relative;

            z-index: 2;

            display: flex;

            justify-content: center;

            flex-wrap: wrap;

            gap: 10px;
        }


        /* =====================================================
           TEAM
        ===================================================== */

        .team-section {

            padding:
                90px 0;

            background:
                var(--surface);
        }


        .team-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 17px;

            max-width: 780px;

            margin:
                0 auto;
        }


        .team-card {

            padding: 25px;

            text-align: center;

            border:
                1px solid var(--border);

            border-radius: 18px;

            background:
                var(--white);

            transition:
                all 0.3s ease;
        }


        .team-card:hover {

            transform:
                translateY(-6px);

            border-color:
                #bfdbfe;

            box-shadow:
                var(--shadow-md);
        }


        .team-avatar {

            width: 82px;
            height: 82px;

            margin:
                0 auto 15px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 50%;

            color:
                var(--primary);

            background:
                #eff6ff;

            border:
                2px solid #dbeafe;

            font-size: 21px;

            font-weight: 850;

            overflow: hidden;

            box-shadow:
                0 8px 20px
                rgba(37, 99, 235, 0.10);
        }


        .team-avatar.team-photo {

            padding: 0;

            background:
                #eff6ff;

            transition:
                border-color 0.3s ease,
                box-shadow 0.3s ease,
                transform 0.3s ease;
        }


        .team-avatar.team-photo img {

            width: 100%;
            height: 100%;

            display: block;

            object-fit: cover;

            object-position: center;
        }


        .team-card:hover
        .team-avatar.team-photo {

            border-color:
                #93c5fd;

            box-shadow:
                0 12px 28px
                rgba(37, 99, 235, 0.18);

            transform:
                scale(1.03);
        }


        .team-card h3 {

            font-size: 15px;

            font-weight: 800;

            margin-bottom: 4px;
        }


        .team-card p {

            font-size: 10px;

            color: var(--muted);
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {

            padding:
                55px 0 25px;

            background:
                #0b1220;

            color:
                rgba(255, 255, 255, 0.75);
        }


        .footer-grid {

            display: grid;

            grid-template-columns:
                1.4fr 0.8fr 0.8fr;

            gap: 60px;

            padding-bottom: 35px;

            border-bottom:
                1px solid
                rgba(255, 255, 255, 0.08);
        }


        .footer-brand {

            display: flex;

            align-items: flex-start;

            gap: 12px;
        }


        .footer-logo {

            width: 48px;
            height: 48px;

            flex-shrink: 0;
        }


        .footer-brand h3 {

            color:
                var(--white);

            font-size: 19px;

            font-weight: 800;
        }


        .footer-brand p {

            max-width: 340px;

            margin-top: 7px;

            font-size: 11px;

            line-height: 1.7;

            color:
                rgba(255, 255, 255, 0.52);
        }


        .footer-column h4 {

            margin-bottom: 14px;

            color:
                var(--white);

            font-size: 12px;

            font-weight: 750;
        }


        .footer-column a {

            display: block;

            width: fit-content;

            margin-bottom: 9px;

            font-size: 11px;

            color:
                rgba(255, 255, 255, 0.52);

            transition:
                color var(--transition);
        }


        .footer-column a:hover {

            color:
                var(--white);
        }


        .footer-bottom {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            padding-top: 20px;

            font-size: 10px;

            color:
                rgba(255, 255, 255, 0.40);
        }


        .footer-bottom strong {

            color:
                rgba(255, 255, 255, 0.65);
        }


        /* =====================================================
           BACK TO TOP
        ===================================================== */

        .back-to-top {

            position: fixed;

            right: 20px;
            bottom: 20px;

            width: 42px;
            height: 42px;

            display: flex;

            align-items: center;
            justify-content: center;

            border: 0;

            border-radius: 12px;

            color:
                var(--white);

            background:
                var(--primary);

            box-shadow:
                0 10px 25px
                rgba(37, 99, 235, 0.25);

            cursor: pointer;

            opacity: 0;

            visibility: hidden;

            transform:
                translateY(10px);

            transition:
                all 0.3s ease;

            z-index: 900;
        }


        .back-to-top.show {

            opacity: 1;

            visibility: visible;

            transform:
                translateY(0);
        }


        .back-to-top:hover {

            background:
                var(--primary-dark);

            transform:
                translateY(-3px);
        }


        /* =====================================================
           MOBILE NAV
        ===================================================== */

        @media (max-width: 850px) {

            .nav-links {

                position: fixed;

                top: 74px;

                left: 20px;
                right: 20px;

                display: none;

                flex-direction: column;

                align-items: stretch;

                gap: 0;

                padding: 10px;

                border:
                    1px solid var(--border);

                border-radius: 16px;

                background:
                    rgba(255, 255, 255, 0.97);

                backdrop-filter:
                    blur(15px);

                box-shadow:
                    var(--shadow-lg);
            }


            .nav-links.open {

                display: flex;
            }


            .nav-links a {

                padding: 13px;

                border-radius: 9px;
            }


            .nav-links a:hover {

                background:
                    #eff6ff;
            }


            .nav-links a::after {

                display: none;
            }


            .nav-actions {

                display: none;
            }


            .mobile-menu-btn {

                display: flex;

                align-items: center;
                justify-content: center;

                font-size: 20px;
            }


            .hero-grid {

                grid-template-columns:
                    1fr;

                gap: 30px;
            }


            .hero {

                min-height: auto;

                padding-top: 115px;
            }


            .hero-content {

                text-align: center;
            }


            .hero-badge {

                margin-bottom: 15px;
            }


            .hero-title {

                margin:
                    0 auto;

                font-size:
                    clamp(
                        42px,
                        10vw,
                        60px
                    );
            }


            .hero-description {

                margin:
                    20px auto 0;

                font-size: 15px;
            }


            .hero-actions {

                justify-content: center;
            }


            .hero-note {

                text-align: center;
            }


            .hero-visual {

                min-height: 450px;
            }


            .sports-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }


            .features-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }


            .workflow {

                grid-template-columns:
                    repeat(2, 1fr);

                row-gap: 40px;
            }


            .workflow-line {

                display: none;
            }


            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }


            .footer-grid {

                grid-template-columns:
                    1fr 1fr;
            }


            .footer-brand {

                grid-column:
                    1 / -1;
            }
        }


        /* =====================================================
           TABLET
        ===================================================== */

        @media (max-width: 650px) {

            .container {

                width:
                    calc(100% - 28px);
            }


            .section {

                padding:
                    75px 0;
            }


            .hero {

                padding:
                    105px 0 60px;
            }


            .hero-title {

                letter-spacing:
                    -2px;
            }


            .hero-description {

                font-size: 14px;

                line-height: 1.7;
            }


            .hero-visual {

                min-height: 390px;
            }


            .hero-card {

                width:
                    min(315px, 82vw);

                padding: 20px;
            }


            .sports-orbit {

                width: 330px;
                height: 330px;
            }


            .floating-sport {

                width: 45px;
                height: 45px;

                font-size: 21px;
            }


            .floating-sport.football {

                left:
                    5%;
            }


            .floating-sport.cricket {

                right:
                    4%;
            }


            .floating-sport.basketball {

                left:
                    1%;
            }


            .floating-sport.volleyball {

                right:
                    2%;
            }


            .sports-grid {

                grid-template-columns:
                    1fr 1fr;

                gap: 10px;
            }


            .sport-card {

                min-height: 250px;

                padding: 17px;
            }


            .features-grid {

                grid-template-columns:
                    1fr;
            }


            .workflow {

                grid-template-columns:
                    1fr;
            }


            .stats-grid {

                grid-template-columns:
                    1fr 1fr;
            }


            .team-grid {

                grid-template-columns:
                    1fr;
            }


            .team-card {

                max-width: 300px;

                width: 100%;

                margin: 0 auto;
            }


            .team-avatar.team-photo {

                width: 78px;
                height: 78px;
            }


            .cta-box {

                padding:
                    45px 20px;
            }


            .footer-grid {

                grid-template-columns:
                    1fr;
            }


            .footer-brand {

                grid-column:
                    auto;
            }


            .footer-bottom {

                flex-direction:
                    column;

                text-align:
                    center;
            }
        }


        /* =====================================================
           SMALL MOBILE
        ===================================================== */

        @media (max-width: 430px) {

            .brand-logo {

                width: 42px;
                height: 42px;
            }


            .brand-name {

                font-size: 18px;
            }


            .brand-tagline {

                font-size: 8px;
            }


            .hero-title {

                font-size: 39px;

                letter-spacing:
                    -2.2px;
            }


            .hero-actions {

                width: 100%;

                flex-direction:
                    column;
            }


            .btn-primary,
            .btn-secondary {

                width: 100%;
            }


            .hero-visual {

                min-height: 340px;
            }


            .hero-card {

                width:
                    285px;
            }


            .sports-orbit {

                width: 300px;
                height: 300px;
            }


            .floating-sport {

                width: 40px;
                height: 40px;

                font-size: 18px;

                border-radius: 12px;
            }


            .floating-sport.football {

                top: 25px;
                left: 0;
            }


            .floating-sport.cricket {

                top: 65px;
                right: 0;
            }


            .floating-sport.basketball {

                bottom: 50px;
                left: 0;
            }


            .floating-sport.volleyball {

                right: 0;
                bottom: 25px;
            }


            .sports-grid {

                grid-template-columns:
                    1fr;
            }


            .sport-card {

                min-height: 260px;
            }


            .stats-grid {

                grid-template-columns:
                    1fr 1fr;

                gap: 8px;
            }


            .stat-number {

                font-size: 25px;
            }


            .section-title {

                font-size: 30px;
            }
        }


        /* =====================================================
           REDUCED MOTION
        ===================================================== */

        @media (prefers-reduced-motion: reduce) {

            html {
                scroll-behavior: auto;
            }


            *,
            *::before,
            *::after {

                animation:
                    none !important;

                transition:
                    none !important;
            }
        }

    </style>

</head>


<body>


    <!-- =====================================================
         SCROLL PROGRESS
    ====================================================== -->

    <div
        class="scroll-progress"
        id="scrollProgress"
    ></div>


    <!-- =====================================================
         NAVBAR
    ====================================================== -->

    <header
        class="navbar"
        id="navbar"
    >

        <div class="container nav-inner">


            <!-- BRAND -->

            <a
                href="#home"
                class="brand"
            >

                <img
                    src="../assets/images/sportsync-mark.svg"
                    alt="SportSync Logo"
                    class="brand-logo"
                >

                <div class="brand-text">

                    <span class="brand-name">
                        SportSync
                    </span>

                    <span class="brand-tagline">
                        Smart Sports Management System
                    </span>

                </div>

            </a>


            <!-- NAV LINKS -->

            <nav
                class="nav-links"
                id="navLinks"
            >

                <a href="#home">
                    Home
                </a>

                <a href="#sports">
                    Sports
                </a>

                <a href="#features">
                    Features
                </a>

                <a href="#how-it-works">
                    How It Works
                </a>

                <a href="#team">
                    Team
                </a>

            </nav>


            <!-- ACTIONS -->

            <div class="nav-actions">

                <a
                    href="login.php"
                    class="nav-login"
                >
                    Login
                </a>

                <a
                    href="register.php"
                    class="nav-register"
                >
                    Register
                </a>

            </div>


            <!-- MOBILE MENU -->

            <button
                type="button"
                class="mobile-menu-btn"
                id="mobileMenuBtn"
                aria-label="Open menu"
            >
                ☰
            </button>


        </div>

    </header>


    <!-- =====================================================
         HERO
    ====================================================== -->

    <main id="home">

        <section class="hero">

            <div class="container hero-grid">


                <!-- HERO CONTENT -->

                <div class="hero-content">


                    <div class="hero-badge">

                        <span class="badge-dot"></span>

                        <span>
                            One Platform. Every Game.
                        </span>

                    </div>


                    <h1 class="hero-title">

                        Manage Sports.
                        <br>

                        Connect Teams.
                        <br>

                        <span>
                            Track Success.
                        </span>

                    </h1>


                    <p class="hero-description">

                        SportSync brings players, coaches,
                        teams, tournaments, matches and
                        statistics together in one smart
                        sports management platform.

                    </p>


                    <div class="hero-actions">

                        <a
                            href="login.php"
                            class="btn-primary"
                        >

                            Get Started

                            <span>
                                →
                            </span>

                        </a>


                        <a
                            href="register.php"
                            class="btn-secondary"
                        >

                            Register as Player

                            <span>
                                ↗
                            </span>

                        </a>

                    </div>


                    <p class="hero-note">

                        Built for smarter college sports management.

                    </p>


                </div>


                <!-- HERO VISUAL -->

                <div class="hero-visual">


                    <div class="visual-glow"></div>

                    <div class="sports-orbit"></div>


                    <!-- FLOATING SPORTS -->

                    <div class="floating-sport football">
                        ⚽
                    </div>

                    <div class="floating-sport cricket">
                        🏏
                    </div>

                    <div class="floating-sport basketball">
                        🏀
                    </div>

                    <div class="floating-sport volleyball">
                        🏐
                    </div>


                    <!-- MAIN CARD -->

                    <div class="hero-card">


                        <div class="hero-card-top">

                            <div class="hero-card-title">

                                <img
                                    src="../assets/images/sportsync-mark.svg"
                                    alt=""
                                    class="hero-mini-logo"
                                >

                                <div>

                                    <strong>
                                        SportSync
                                    </strong>

                                    <span>
                                        Match Center
                                    </span>

                                </div>

                            </div>


                            <div class="live-badge">

                                <span class="live-dot"></span>

                                Live

                            </div>

                        </div>


                        <div class="match-card">


                            <div class="match-label">

                                <span>
                                    FOOTBALL • LEAGUE
                                </span>

                                <span>
                                    MATCH #01
                                </span>

                            </div>


                            <div class="match-teams">


                                <div class="match-team">

                                    <div class="team-ball">
                                        🔵
                                    </div>

                                    <strong>
                                        Gold
                                    </strong>

                                </div>


                                <div class="score">

                                    2 - 1

                                    <small>
                                        FINAL
                                    </small>

                                </div>


                                <div class="match-team">

                                    <div class="team-ball">
                                        🔴
                                    </div>

                                    <strong>
                                        Red
                                    </strong>

                                </div>


                            </div>


                        </div>


                        <div class="hero-stats">


                            <div class="hero-stat">

                                <strong>
                                    4
                                </strong>

                                <span>
                                    Sports
                                </span>

                            </div>


                            <div class="hero-stat">

                                <strong>
                                    24/7
                                </strong>

                                <span>
                                    Access
                                </span>

                            </div>


                            <div class="hero-stat">

                                <strong>
                                    100%
                                </strong>

                                <span>
                                    Digital
                                </span>

                            </div>


                        </div>


                    </div>


                </div>


            </div>

        </section>


        <!-- =================================================
             SPORTS
        ================================================== -->

        <section
            class="section sports-section"
            id="sports"
        >

            <div class="container">


                <div class="section-header reveal">

                    <span class="section-label">
                        Sports
                    </span>

                    <h2 class="section-title">
                        One platform for every game
                    </h2>

                    <p class="section-description">

                        Manage multiple sports through one
                        organized and connected system.

                    </p>

                </div>


                <div class="sports-grid">


                    <article
                        class="sport-card football reveal"
                    >

                        <div class="sport-icon">
                            ⚽
                        </div>

                        <h3>
                            Football
                        </h3>

                        <p>
                            Teams, matches, players,
                            results and statistics.
                        </p>

                    </article>


                    <article
                        class="sport-card cricket reveal"
                    >

                        <div class="sport-icon">
                            🏏
                        </div>

                        <h3>
                            Cricket
                        </h3>

                        <p>
                            Organize cricket teams,
                            tournaments and match data.
                        </p>

                    </article>


                    <article
                        class="sport-card basketball reveal"
                    >

                        <div class="sport-icon">
                            🏀
                        </div>

                        <h3>
                            Basketball
                        </h3>

                        <p>
                            Manage teams, players,
                            fixtures and performance.
                        </p>

                    </article>


                    <article
                        class="sport-card volleyball reveal"
                    >

                        <div class="sport-icon">
                            🏐
                        </div>

                        <h3>
                            Volleyball
                        </h3>

                        <p>
                            Keep tournaments,
                            matches and players organized.
                        </p>

                    </article>


                </div>

            </div>

        </section>


        <!-- =================================================
             FEATURES
        ================================================== -->

        <section
            class="section features-section"
            id="features"
        >

            <div class="container">


                <div class="section-header reveal">

                    <span class="section-label">
                        Powerful Features
                    </span>

                    <h2 class="section-title">
                        Everything your sports department needs
                    </h2>

                    <p class="section-description">

                        A centralized system designed to make
                        college sports management simpler,
                        faster and more organized.

                    </p>

                </div>


                <div class="features-grid">


                    <article class="feature-card reveal">

                        <span class="feature-number">
                            01
                        </span>

                        <div class="feature-icon-box">
                            👥
                        </div>

                        <h3>
                            Player Management
                        </h3>

                        <p>
                            Register players, manage approvals,
                            sports participation and player
                            information from one place.
                        </p>

                    </article>


                    <article class="feature-card reveal">

                        <span class="feature-number">
                            02
                        </span>

                        <div class="feature-icon-box">
                            🏆
                        </div>

                        <h3>
                            Tournament Management
                        </h3>

                        <p>
                            Create tournaments, register teams,
                            manage fixtures and maintain
                            tournament standings.
                        </p>

                    </article>


                    <article class="feature-card reveal">

                        <span class="feature-number">
                            03
                        </span>

                        <div class="feature-icon-box">
                            ⚽
                        </div>

                        <h3>
                            Match Management
                        </h3>

                        <p>
                            Schedule matches, record results,
                            manage participation and maintain
                            match history.
                        </p>

                    </article>


                    <article class="feature-card reveal">

                        <span class="feature-number">
                            04
                        </span>

                        <div class="feature-icon-box">
                            📊
                        </div>

                        <h3>
                            Statistics
                        </h3>

                        <p>
                            Track player performance,
                            match statistics and tournament
                            performance.
                        </p>

                    </article>


                    <article class="feature-card reveal">

                        <span class="feature-number">
                            05
                        </span>

                        <div class="feature-icon-box">
                            🛡️
                        </div>

                        <h3>
                            Role-Based Access
                        </h3>

                        <p>
                            Separate access for Admin,
                            Sports Coordinator, Coach and
                            Player accounts.
                        </p>

                    </article>


                    <article class="feature-card reveal">

                        <span class="feature-number">
                            06
                        </span>

                        <div class="feature-icon-box">
                            📱
                        </div>

                        <h3>
                            Responsive Design
                        </h3>

                        <p>
                            Access SportSync comfortably across
                            desktops, tablets and mobile devices.
                        </p>

                    </article>


                </div>

            </div>

        </section>


        <!-- =================================================
             HOW IT WORKS
        ================================================== -->

        <section
            class="section workflow-section"
            id="how-it-works"
        >

            <div class="container">


                <div class="section-header reveal">

                    <span class="section-label">
                        Simple Workflow
                    </span>

                    <h2 class="section-title">
                        From registration to results
                    </h2>

                    <p class="section-description">

                        SportSync connects the complete sports
                        management workflow.

                    </p>

                </div>


                <div class="workflow">


                    <div class="workflow-line"></div>


                    <article class="workflow-item reveal">

                        <div class="workflow-number">
                            01
                        </div>

                        <h3>
                            Register
                        </h3>

                        <p>
                            Students register as players
                            and select their sports.
                        </p>

                    </article>


                    <article class="workflow-item reveal">

                        <div class="workflow-number">
                            02
                        </div>

                        <h3>
                            Organize
                        </h3>

                        <p>
                            Coordinators and coaches manage
                            teams and tournaments.
                        </p>

                    </article>


                    <article class="workflow-item reveal">

                        <div class="workflow-number">
                            03
                        </div>

                        <h3>
                            Compete
                        </h3>

                        <p>
                            Teams participate in scheduled
                            matches and tournaments.
                        </p>

                    </article>


                    <article class="workflow-item reveal">

                        <div class="workflow-number">
                            04
                        </div>

                        <h3>
                            Track
                        </h3>

                        <p>
                            Results, standings and player
                            statistics stay organized.
                        </p>

                    </article>


                </div>

            </div>

        </section>


        <!-- =================================================
             STATS
        ================================================== -->

        <section class="stats-section">

            <div class="container">

                <div class="stats-grid">


                    <div class="stat-item reveal">

                        <div class="stat-number">
                            4
                        </div>

                        <div class="stat-label">
                            Supported Sports
                        </div>

                    </div>


                    <div class="stat-item reveal">

                        <div class="stat-number">
                            4
                        </div>

                        <div class="stat-label">
                            User Roles
                        </div>

                    </div>


                    <div class="stat-item reveal">

                        <div class="stat-number">
                            1
                        </div>

                        <div class="stat-label">
                            Central Platform
                        </div>

                    </div>


                    <div class="stat-item reveal">

                        <div class="stat-number">
                            ∞
                        </div>

                        <div class="stat-label">
                            Possibilities
                        </div>

                    </div>


                </div>

            </div>

        </section>


        <!-- =================================================
             CTA
        ================================================== -->

        <section class="cta-section">

            <div class="container">


                <div class="cta-box reveal">


                    <h2>
                        Ready to manage sports smarter?
                    </h2>


                    <p>

                        Join SportSync and bring players,
                        coaches, teams, tournaments and
                        match management together.

                    </p>


                    <div class="cta-actions">

                        <a
                            href="login.php"
                            class="btn-primary"
                        >
                            Get Started →
                        </a>


                        <a
                            href="register.php"
                            class="btn-secondary"
                        >
                            Register as Player
                        </a>

                    </div>


                </div>

            </div>

        </section>


        <!-- =================================================
             TEAM
        ================================================== -->

        <section
            class="team-section"
            id="team"
        >

            <div class="container">


                <div class="section-header reveal">

                    <span class="section-label">
                        Our Team
                    </span>

                    <h2 class="section-title">
                        Built by students, for smarter sports
                    </h2>

                    <p class="section-description">

                        SportSync is developed as a collaborative
                        college project by our three-member team.

                    </p>

                </div>


                <div class="team-grid">


                    <!-- SIDDIQ -->

                    <article class="team-card reveal">

                        <div class="team-avatar team-photo">

                            <img
                                src="../assets/images/team/siddiq.png"
                                alt="Siddiq - Developer & Team Member"
                            >

                        </div>

                        <h3>
                            Siddiq
                        </h3>

                        <p>
                            Developer &amp; Team Member
                        </p>

                    </article>


                    <!-- SOYAB -->

                    <article class="team-card reveal">

                        <div class="team-avatar team-photo">

                            <img
                                src="../assets/images/team/soyab.png"
                                alt="Soyab - Developer & Team Member"
                            >

                        </div>

                        <h3>
                            Soyab
                        </h3>

                        <p>
                            Developer &amp; Team Member
                        </p>

                    </article>


                    <!-- ASHISH -->

                    <article class="team-card reveal">

                        <div class="team-avatar team-photo">

                            <img
                                src="../assets/images/team/ashish.png"
                                alt="Ashish - Developer & Team Member"
                            >

                        </div>

                        <h3>
                            Ashish
                        </h3>

                        <p>
                            Developer &amp; Team Member
                        </p>

                    </article>


                </div>

            </div>

        </section>


    </main>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer class="footer">


        <div class="container">


            <div class="footer-grid">


                <div class="footer-brand">


                    <img
                        src="../assets/images/sportsync-mark.svg"
                        alt="SportSync Logo"
                        class="footer-logo"
                    >


                    <div>

                        <h3>
                            SportSync
                        </h3>

                        <p>

                            Smart Sports Management System
                            designed to connect players,
                            teams, coaches, tournaments,
                            matches and statistics.

                        </p>

                    </div>


                </div>


                <div class="footer-column">

                    <h4>
                        Platform
                    </h4>

                    <a href="#sports">
                        Sports
                    </a>

                    <a href="#features">
                        Features
                    </a>

                    <a href="#how-it-works">
                        How It Works
                    </a>

                    <a href="login.php">
                        Login
                    </a>

                </div>


                <div class="footer-column">

                    <h4>
                        Account
                    </h4>

                    <a href="login.php">
                        Sign In
                    </a>

                    <a href="register.php">
                        Register
                    </a>

                    <a href="#team">
                        Our Team
                    </a>

                </div>


            </div>


            <div class="footer-bottom">


                <span>
                    ©
                    <?= date('Y') ?>
                    SportSync. All rights reserved.
                </span>


                <span>
                    Developed by:
                    <strong>
                        Soyab • Ashish • Siddiq
                    </strong>
                </span>


            </div>


        </div>


    </footer>


    <!-- =====================================================
         BACK TO TOP
    ====================================================== -->

    <button
        type="button"
        class="back-to-top"
        id="backToTop"
        aria-label="Back to top"
    >
        ↑
    </button>


    <!-- =====================================================
         JAVASCRIPT
    ====================================================== -->

    <script>

        /* =================================================
           NAVBAR SCROLL
        ================================================= */

        const navbar =
            document.getElementById('navbar');


        const scrollProgress =
            document.getElementById('scrollProgress');


        const backToTop =
            document.getElementById('backToTop');


        function handleScroll() {

            const scrollTop =
                window.scrollY;

            const documentHeight =
                document.documentElement.scrollHeight
                - window.innerHeight;


            if (scrollTop > 30) {

                navbar.classList.add(
                    'scrolled'
                );

            } else {

                navbar.classList.remove(
                    'scrolled'
                );

            }


            if (documentHeight > 0) {

                const progress =
                    (
                        scrollTop /
                        documentHeight
                    ) * 100;

                scrollProgress.style.width =
                    progress + '%';

            }


            if (scrollTop > 500) {

                backToTop.classList.add(
                    'show'
                );

            } else {

                backToTop.classList.remove(
                    'show'
                );

            }

        }


        window.addEventListener(
            'scroll',
            handleScroll,
            { passive: true }
        );


        handleScroll();


        /* =================================================
           BACK TO TOP
        ================================================= */

        backToTop.addEventListener(
            'click',
            function () {

                window.scrollTo({

                    top: 0,

                    behavior: 'smooth'

                });

            }
        );


        /* =================================================
           MOBILE MENU
        ================================================= */

        const mobileMenuBtn =
            document.getElementById(
                'mobileMenuBtn'
            );


        const navLinks =
            document.getElementById(
                'navLinks'
            );


        mobileMenuBtn.addEventListener(
            'click',
            function () {

                navLinks.classList.toggle(
                    'open'
                );


                const isOpen =
                    navLinks.classList.contains(
                        'open'
                    );


                mobileMenuBtn.textContent =
                    isOpen ? '✕' : '☰';

            }
        );


        /* =================================================
           CLOSE MOBILE MENU
        ================================================= */

        document.querySelectorAll(
            '.nav-links a'
        ).forEach(function (link) {

            link.addEventListener(
                'click',
                function () {

                    navLinks.classList.remove(
                        'open'
                    );

                    mobileMenuBtn.textContent =
                        '☰';

                }
            );

        });


        /* =================================================
           SCROLL REVEAL
        ================================================= */

        const revealElements =
            document.querySelectorAll(
                '.reveal'
            );


        const revealObserver =
            new IntersectionObserver(

                function (entries) {

                    entries.forEach(
                        function (entry) {

                            if (
                                entry.isIntersecting
                            ) {

                                entry.target.classList.add(
                                    'visible'
                                );

                                revealObserver.unobserve(
                                    entry.target
                                );

                            }

                        }
                    );

                },

                {
                    threshold: 0.12
                }

            );


        revealElements.forEach(
            function (element) {

                revealObserver.observe(
                    element
                );

            }
        );


        /* =================================================
           ACTIVE NAVIGATION
        ================================================= */

        const sections =
            document.querySelectorAll(
                'section[id]'
            );


        const navItems =
            document.querySelectorAll(
                '.nav-links a'
            );


        const sectionObserver =
            new IntersectionObserver(

                function (entries) {

                    entries.forEach(
                        function (entry) {

                            if (
                                entry.isIntersecting
                            ) {

                                const id =
                                    entry.target.id;


                                navItems.forEach(
                                    function (link) {

                                        link.style.color =
                                            '';

                                        if (
                                            link.getAttribute(
                                                'href'
                                            ) ===
                                            '#' + id
                                        ) {

                                            link.style.color =
                                                '#2563eb';

                                        }

                                    }
                                );

                            }

                        }
                    );

                },

                {
                    rootMargin:
                        '-35% 0px -55% 0px'
                }

            );


        sections.forEach(
            function (section) {

                sectionObserver.observe(
                    section
                );

            }
        );


        /* =================================================
           ESC KEY - MOBILE MENU
        ================================================= */

        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape'
                ) {

                    navLinks.classList.remove(
                        'open'
                    );

                    mobileMenuBtn.textContent =
                        '☰';

                }

            }
        );

    </script>


</body>

</html>