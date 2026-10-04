<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

$matchId = filter_input(
    INPUT_GET,
    'match_id',
    FILTER_VALIDATE_INT
);

if (!$matchId) {
    exit('Invalid match ID.');
}

/*
|--------------------------------------------------------------------------
| Get Match
|--------------------------------------------------------------------------
*/

$matchStmt = $pdo->prepare("
    SELECT
        m.match_id,
        m.match_number,
        m.tournament_id,
        m.team_a_id,
        m.team_b_id,
        m.scheduled_start,
        m.scheduled_end,
        m.match_status,
        tournament.tournament_name,
        tournament.sport_id,
        sport.sport_name,
        team_a.team_name AS team_a_name,
        team_b.team_name AS team_b_name,
        v.venue_name,
        mr.team_a_score,
        mr.team_b_score,
        mr.result_notes
    FROM matches m
    INNER JOIN tournaments tournament
        ON tournament.tournament_id = m.tournament_id
    INNER JOIN sports sport
        ON sport.sport_id = tournament.sport_id
    INNER JOIN teams team_a
        ON team_a.team_id = m.team_a_id
    INNER JOIN teams team_b
        ON team_b.team_id = m.team_b_id
    INNER JOIN venues v
        ON v.venue_id = m.venue_id
    LEFT JOIN match_results mr
        ON mr.match_id = m.match_id
    WHERE m.match_id = :match_id
    LIMIT 1
");

$matchStmt->execute([
    'match_id' => $matchId
]);

$match = $matchStmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    exit('Match not found.');
}

if ($match['match_status'] !== 'SCHEDULED') {
    exit('This match is not available for result entry.');
}

$error = $_GET['error'] ?? '';

/*
|--------------------------------------------------------------------------
| Sport-specific result configuration
|--------------------------------------------------------------------------
*/

$sportName = trim((string) ($match['sport_name'] ?? ''));
$sportKey = strtolower($sportName);

$resultLabel = 'Score';
$resultUnit = 'score';
$resultIcon = '🏆';
$resultDescription = 'Enter the final score for both teams.';
$step = '1';
$placeholderA = 'Enter score';
$placeholderB = 'Enter score';

switch ($sportKey) {

    case 'football':
        $resultLabel = 'Goals';
        $resultUnit = 'goals';
        $resultIcon = '⚽';
        $resultDescription = 'Enter the number of goals scored by each team.';
        $step = '1';
        $placeholderA = 'e.g. 2';
        $placeholderB = 'e.g. 1';
        break;

    case 'cricket':
        $resultLabel = 'Runs';
        $resultUnit = 'runs';
        $resultIcon = '🏏';
        $resultDescription = 'Enter the final runs scored by each team.';
        $step = '1';
        $placeholderA = 'e.g. 156';
        $placeholderB = 'e.g. 148';
        break;

    case 'basketball':
        $resultLabel = 'Points';
        $resultUnit = 'points';
        $resultIcon = '🏀';
        $resultDescription = 'Enter the final points scored by each team.';
        $step = '1';
        $placeholderA = 'e.g. 78';
        $placeholderB = 'e.g. 72';
        break;

    case 'volleyball':
        $resultLabel = 'Sets Won';
        $resultUnit = 'sets';
        $resultIcon = '🏐';
        $resultDescription = 'Enter the number of sets won by each team.';
        $step = '1';
        $placeholderA = 'e.g. 3';
        $placeholderB = 'e.g. 1';
        break;

    case 'badminton':
        $resultLabel = 'Games Won';
        $resultUnit = 'games';
        $resultIcon = '🏸';
        $resultDescription = 'Enter the number of games won by each side.';
        $step = '1';
        $placeholderA = 'e.g. 2';
        $placeholderB = 'e.g. 0';
        break;

    case 'table tennis':
    case 'tabletennis':
    case 'table-tennis':
        $resultLabel = 'Games Won';
        $resultUnit = 'games';
        $resultIcon = '🏓';
        $resultDescription = 'Enter the number of games won by each side.';
        $step = '1';
        $placeholderA = 'e.g. 3';
        $placeholderB = 'e.g. 1';
        break;

    case 'carrom':
        $resultLabel = 'Points';
        $resultUnit = 'points';
        $resultIcon = '🎯';
        $resultDescription = 'Enter the final points scored by each team.';
        $step = '1';
        $placeholderA = 'e.g. 21';
        $placeholderB = 'e.g. 15';
        break;

    case 'chess':
        $resultLabel = 'Result Points';
        $resultUnit = 'points';
        $resultIcon = '♟️';
        $resultDescription = 'Enter the result value according to the tournament scoring system.';
        $step = '0.5';
        $placeholderA = 'e.g. 1';
        $placeholderB = 'e.g. 0';
        break;

    case 'athletics':
    case 'swimming':
        $resultLabel = 'Score';
        $resultUnit = 'score';
        $resultIcon = '🏅';
        $resultDescription = 'Use the tournament result value for this competition.';
        $step = '0.01';
        $placeholderA = 'Enter result';
        $placeholderB = 'Enter result';
        break;

    default:
        $resultLabel = 'Score';
        $resultUnit = 'score';
        $resultIcon = '🏆';
        $resultDescription = 'Enter the final result value for both teams.';
        $step = '1';
        $placeholderA = 'Enter score';
        $placeholderB = 'Enter score';
        break;
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function pageEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatDateTime(?string $dateTime): string
{
    if (!$dateTime) {
        return 'Not available';
    }

    $timestamp = strtotime($dateTime);

    if ($timestamp === false) {
        return $dateTime;
    }

    return date('d M Y, h:i A', $timestamp);
}

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
        Enter Match Result -
        <?= pageEscape($match['tournament_name']) ?>
    </title>

    <style>

        .enter-result-page {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding-bottom: 40px;
        }

        /*
        |--------------------------------------------------------------------------
        | Hero
        |--------------------------------------------------------------------------
        */

        .result-hero {
            background: linear-gradient(
                135deg,
                #0d47a1 0%,
                #1565c0 55%,
                #1976d2 100%
            );
            color: #ffffff;
            border-radius: 18px;
            padding: 28px 30px;
            margin-bottom: 24px;
            box-shadow: 0 12px 30px rgba(13, 71, 161, 0.18);
        }

        .result-hero-inner {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .result-hero-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.16);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }

        .result-hero h1 {
            margin: 0 0 7px;
            font-size: 28px;
            line-height: 1.2;
            font-weight: 800;
        }

        .result-hero p {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            opacity: 0.9;
        }

        .result-breadcrumb {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 17px;
            font-size: 13px;
        }

        .result-breadcrumb a {
            color: #ffffff;
            text-decoration: none;
            opacity: 0.9;
        }

        .result-breadcrumb a:hover {
            text-decoration: underline;
            opacity: 1;
        }

        .result-breadcrumb span {
            opacity: 0.55;
        }

        /*
        |--------------------------------------------------------------------------
        | Alert
        |--------------------------------------------------------------------------
        */

        .result-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 15px;
            margin-bottom: 20px;
            border-radius: 11px;
            background: #fff1f2;
            border: 1px solid #f5c2c7;
            color: #b42318;
            font-size: 13px;
            line-height: 1.5;
        }

        .result-alert-icon {
            flex-shrink: 0;
            font-size: 16px;
        }

        /*
        |--------------------------------------------------------------------------
        | Layout
        |--------------------------------------------------------------------------
        */

        .result-layout {
            display: grid;
            grid-template-columns: minmax(280px, 0.8fr) minmax(420px, 1.4fr);
            gap: 24px;
            align-items: start;
        }

        .result-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 7px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .result-card-header {
            padding: 20px 22px;
            border-bottom: 1px solid #eef0f3;
            background: #fafbfc;
        }

        .result-card-header h2 {
            margin: 0 0 5px;
            color: #172033;
            font-size: 18px;
        }

        .result-card-header p {
            margin: 0;
            color: #6b7280;
            font-size: 12px;
            line-height: 1.5;
        }

        .result-card-body {
            padding: 22px;
        }

        /*
        |--------------------------------------------------------------------------
        | Match information
        |--------------------------------------------------------------------------
        */

        .match-info-title {
            color: #172033;
            font-size: 20px;
            font-weight: 800;
            line-height: 1.35;
        }

        .sport-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 8px;
            padding: 7px 11px;
            border-radius: 999px;
            background: #eef5ff;
            color: #145db2;
            font-size: 12px;
            font-weight: 800;
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 11px;
            margin-top: 20px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 11px;
            border-radius: 10px;
            background: #f8fafc;
        }

        .info-icon {
            width: 33px;
            height: 33px;
            border-radius: 9px;
            background: #eaf2ff;
            color: #145db2;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 15px;
        }

        .info-content {
            min-width: 0;
        }

        .info-label {
            display: block;
            margin-bottom: 3px;
            color: #7a8491;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .info-value {
            display: block;
            color: #344054;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        /*
        |--------------------------------------------------------------------------
        | VS display
        |--------------------------------------------------------------------------
        */

        .match-preview {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #e0e7ef;
            border-radius: 13px;
            background: #fbfcfe;
        }

        .preview-label {
            margin-bottom: 11px;
            color: #7a8491;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: center;
        }

        .preview-teams {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 45px minmax(0, 1fr);
            align-items: center;
            gap: 8px;
        }

        .preview-team {
            min-width: 0;
            text-align: center;
        }

        .preview-team-name {
            color: #243044;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .preview-vs {
            width: 38px;
            height: 38px;
            margin: auto;
            border-radius: 50%;
            background: #edf2f7;
            color: #526174;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 900;
        }

        /*
        |--------------------------------------------------------------------------
        | Sport result banner
        |--------------------------------------------------------------------------
        */

        .sport-result-banner {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 21px;
            padding: 15px;
            border: 1px solid #dbe8f8;
            border-radius: 12px;
            background: #f5f9ff;
        }

        .sport-result-icon {
            width: 39px;
            height: 39px;
            border-radius: 10px;
            background: #e3efff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 19px;
        }

        .sport-result-content strong {
            display: block;
            margin-bottom: 3px;
            color: #194c82;
            font-size: 13px;
        }

        .sport-result-content p {
            margin: 0;
            color: #5d6b7a;
            font-size: 11px;
            line-height: 1.5;
        }

        /*
        |--------------------------------------------------------------------------
        | Score section
        |--------------------------------------------------------------------------
        */

        .score-section-title {
            margin: 0 0 14px;
            color: #172033;
            font-size: 14px;
            font-weight: 800;
        }

        .score-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 55px minmax(0, 1fr);
            align-items: end;
            gap: 12px;
        }

        .score-group {
            min-width: 0;
        }

        .score-label {
            display: block;
            min-height: 36px;
            margin-bottom: 7px;
            color: #273244;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.45;
        }

        .score-input {
            width: 100%;
            box-sizing: border-box;
            min-height: 60px;
            padding: 10px 12px;
            border: 2px solid #d6dce4;
            border-radius: 12px;
            background: #ffffff;
            color: #172033;
            font-family: inherit;
            font-size: 24px;
            font-weight: 800;
            text-align: center;
            outline: none;
            transition:
                border-color 0.18s ease,
                box-shadow 0.18s ease;
        }

        .score-input:focus {
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.12);
        }

        .score-input::placeholder {
            color: #b0b7c1;
            font-size: 15px;
            font-weight: 600;
        }

        .score-unit {
            margin-top: 5px;
            color: #7a8491;
            font-size: 10px;
            text-align: center;
        }

        .score-vs {
            width: 45px;
            height: 45px;
            margin-bottom: 7px;
            border-radius: 50%;
            background: #eef3f9;
            color: #36516f;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 900;
        }

        /*
        |--------------------------------------------------------------------------
        | Winner preview
        |--------------------------------------------------------------------------
        */

        .winner-preview {
            display: none;
            margin-top: 15px;
            padding: 11px 13px;
            border-radius: 10px;
            background: #eaf8f0;
            border: 1px solid #b7e4c7;
            color: #146c43;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }

        /*
        |--------------------------------------------------------------------------
        | Notes
        |--------------------------------------------------------------------------
        */

        .notes-section {
            margin-top: 23px;
            padding-top: 21px;
            border-top: 1px solid #edf0f3;
        }

        .notes-label {
            display: block;
            margin-bottom: 7px;
            color: #273244;
            font-size: 13px;
            font-weight: 700;
        }

        .notes-input {
            width: 100%;
            min-height: 105px;
            box-sizing: border-box;
            padding: 11px 12px;
            resize: vertical;
            border: 1px solid #d4d9e0;
            border-radius: 10px;
            background: #ffffff;
            color: #172033;
            font-family: inherit;
            font-size: 13px;
            line-height: 1.5;
            outline: none;
        }

        .notes-input:focus {
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        .notes-help {
            margin-top: 6px;
            color: #7a8491;
            font-size: 10px;
        }

        /*
        |--------------------------------------------------------------------------
        | Actions
        |--------------------------------------------------------------------------
        */

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #edf0f3;
        }

        .back-link {
            color: #536174;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }

        .back-link:hover {
            color: #145db2;
            text-decoration: underline;
        }

        .save-result-button {
            min-height: 43px;
            padding: 10px 18px;
            border: 0;
            border-radius: 10px;
            background: #1565c0;
            color: #ffffff;
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 5px 14px rgba(21, 101, 192, 0.2);
            transition:
                background 0.18s ease,
                transform 0.18s ease,
                box-shadow 0.18s ease;
        }

        .save-result-button:hover {
            background: #0d5cad;
            transform: translateY(-1px);
            box-shadow: 0 7px 17px rgba(21, 101, 192, 0.24);
        }

        .save-result-button:active {
            transform: translateY(0);
        }

        /*
        |--------------------------------------------------------------------------
        | Information
        |--------------------------------------------------------------------------
        */

        .result-info {
            margin-top: 24px;
            padding: 16px 18px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border: 1px solid #dbe8f8;
            border-radius: 13px;
            background: #f5f9ff;
        }

        .result-info-icon {
            font-size: 18px;
            flex-shrink: 0;
        }

        .result-info strong {
            display: block;
            margin-bottom: 3px;
            color: #194c82;
            font-size: 13px;
        }

        .result-info p {
            margin: 0;
            color: #5d6b7a;
            font-size: 11px;
            line-height: 1.5;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .result-layout {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 650px) {

            .result-hero {
                padding: 22px 18px;
                border-radius: 14px;
            }

            .result-hero-icon {
                width: 44px;
                height: 44px;
                border-radius: 11px;
                font-size: 22px;
            }

            .result-hero h1 {
                font-size: 22px;
            }

            .result-card {
                border-radius: 13px;
            }

            .result-card-header,
            .result-card-body {
                padding: 17px;
            }

            .score-grid {
                grid-template-columns: 1fr;
                gap: 9px;
            }

            .score-label {
                min-height: auto;
            }

            .score-vs {
                width: 38px;
                height: 38px;
                margin: 0 auto;
            }

            .form-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .back-link,
            .save-result-button {
                width: 100%;
                box-sizing: border-box;
                text-align: center;
            }

        }

        @media (max-width: 450px) {

            .result-hero {
                padding: 19px 15px;
            }

            .result-hero h1 {
                font-size: 20px;
            }

            .result-card-header,
            .result-card-body {
                padding: 15px;
            }

            .match-info-title {
                font-size: 18px;
            }

            .preview-teams {
                grid-template-columns: 1fr;
                gap: 7px;
            }

            .preview-vs {
                width: 34px;
                height: 34px;
            }

        }

    </style>

</head>

<body>

<div class="enter-result-page">

    <!-- Hero -->

    <section class="result-hero">

        <div class="result-hero-inner">

            <div class="result-hero-icon">
                <?= $resultIcon ?>
            </div>

            <div>

                <h1>Enter Match Result</h1>

                <p>
                    Record the final result of this completed league match.
                </p>

                <div class="result-breadcrumb">

                    <a
                        href="match-results.php?tournament_id=<?= (int) $match['tournament_id'] ?>"
                    >
                        Match Results
                    </a>

                    <span>›</span>

                    <span>
                        Match #<?= (int) $match['match_number'] ?>
                    </span>

                </div>

            </div>

        </div>

    </section>

    <?php if ($error !== ''): ?>

        <div class="result-alert">

            <div class="result-alert-icon">
                ⚠️
            </div>

            <div>
                <?= pageEscape($error) ?>
            </div>

        </div>

    <?php endif; ?>

    <div class="result-layout">

        <!-- Match Information -->

        <section class="result-card">

            <div class="result-card-header">

                <h2>Match Information</h2>

                <p>
                    Review the match details before entering the result.
                </p>

            </div>

            <div class="result-card-body">

                <div class="match-info-title">
                    Match #<?= (int) $match['match_number'] ?>
                </div>

                <div class="sport-badge">
                    <?= $resultIcon ?>
                    <?= pageEscape($match['sport_name']) ?>
                </div>

                <div class="info-list">

                    <div class="info-item">

                        <div class="info-icon">
                            🏆
                        </div>

                        <div class="info-content">

                            <span class="info-label">
                                Tournament
                            </span>

                            <span class="info-value">
                                <?= pageEscape($match['tournament_name']) ?>
                            </span>

                        </div>

                    </div>

                    <div class="info-item">

                        <div class="info-icon">
                            📍
                        </div>

                        <div class="info-content">

                            <span class="info-label">
                                Venue
                            </span>

                            <span class="info-value">
                                <?= pageEscape($match['venue_name']) ?>
                            </span>

                        </div>

                    </div>

                    <div class="info-item">

                        <div class="info-icon">
                            📅
                        </div>

                        <div class="info-content">

                            <span class="info-label">
                                Scheduled Date & Time
                            </span>

                            <span class="info-value">
                                <?= pageEscape(
                                    formatDateTime($match['scheduled_start'])
                                ) ?>
                            </span>

                        </div>

                    </div>

                    <div class="info-item">

                        <div class="info-icon">
                            🕐
                        </div>

                        <div class="info-content">

                            <span class="info-label">
                                Scheduled End
                            </span>

                            <span class="info-value">
                                <?= pageEscape(
                                    formatDateTime($match['scheduled_end'])
                                ) ?>
                            </span>

                        </div>

                    </div>

                </div>

                <!-- Teams Preview -->

                <div class="match-preview">

                    <div class="preview-label">
                        Teams
                    </div>

                    <div class="preview-teams">

                        <div class="preview-team">

                            <div class="preview-team-name">
                                <?= pageEscape($match['team_a_name']) ?>
                            </div>

                        </div>

                        <div class="preview-vs">
                            VS
                        </div>

                        <div class="preview-team">

                            <div class="preview-team-name">
                                <?= pageEscape($match['team_b_name']) ?>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>

        <!-- Result Entry -->

        <section class="result-card">

            <div class="result-card-header">

                <h2>
                    <?= $resultIcon ?>
                    Enter <?= pageEscape($resultLabel) ?>
                </h2>

                <p>
                    Record the final result for both teams.
                </p>

            </div>

            <div class="result-card-body">

                <!-- Sport-specific information -->

                <div class="sport-result-banner">

                    <div class="sport-result-icon">
                        <?= $resultIcon ?>
                    </div>

                    <div class="sport-result-content">

                        <strong>
                            <?= pageEscape($match['sport_name']) ?> Result
                        </strong>

                        <p>
                            <?= pageEscape($resultDescription) ?>
                        </p>

                    </div>

                </div>

                <form
                    method="POST"
                    action="save-match-result.php"
                    id="matchResultForm"
                >

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="match_id"
                        value="<?= (int) $matchId ?>"
                    >

                    <!-- Scores -->

                    <div>

                        <h3 class="score-section-title">
                            Final <?= pageEscape($resultLabel) ?>
                        </h3>

                        <div class="score-grid">

                            <!-- Team A -->

                            <div class="score-group">

                                <label
                                    for="team_a_score"
                                    class="score-label"
                                >
                                    <?= pageEscape($match['team_a_name']) ?>
                                    <br>
                                    <?= pageEscape($resultLabel) ?>
                                </label>

                                <input
                                    type="number"
                                    id="team_a_score"
                                    name="team_a_score"
                                    class="score-input"
                                    min="0"
                                    step="<?= pageEscape($step) ?>"
                                    placeholder="<?= pageEscape($placeholderA) ?>"
                                    required
                                    inputmode="decimal"
                                >

                                <div class="score-unit">
                                    <?= pageEscape($resultUnit) ?>
                                </div>

                            </div>

                            <!-- VS -->

                            <div class="score-vs">
                                VS
                            </div>

                            <!-- Team B -->

                            <div class="score-group">

                                <label
                                    for="team_b_score"
                                    class="score-label"
                                >
                                    <?= pageEscape($match['team_b_name']) ?>
                                    <br>
                                    <?= pageEscape($resultLabel) ?>
                                </label>

                                <input
                                    type="number"
                                    id="team_b_score"
                                    name="team_b_score"
                                    class="score-input"
                                    min="0"
                                    step="<?= pageEscape($step) ?>"
                                    placeholder="<?= pageEscape($placeholderB) ?>"
                                    required
                                    inputmode="decimal"
                                >

                                <div class="score-unit">
                                    <?= pageEscape($resultUnit) ?>
                                </div>

                            </div>

                        </div>

                        <div
                            class="winner-preview"
                            id="winnerPreview"
                        ></div>

                    </div>

                    <!-- Result Notes -->

                    <div class="notes-section">

                        <label
                            for="result_notes"
                            class="notes-label"
                        >
                            📝 Result Notes
                        </label>

                        <textarea
                            id="result_notes"
                            name="result_notes"
                            class="notes-input"
                            rows="5"
                            maxlength="1000"
                            placeholder="Add optional information about the result, performance, penalties, special circumstances, etc."
                        ></textarea>

                        <div class="notes-help">
                            Optional. Maximum 1000 characters.
                        </div>

                    </div>

                    <!-- Actions -->

                    <div class="form-actions">

                        <a
                            href="match-results.php?tournament_id=<?= (int) $match['tournament_id'] ?>"
                            class="back-link"
                        >
                            ← Back to Match Results
                        </a>

                        <button
                            type="submit"
                            class="save-result-button"
                        >
                            ✅ Save Match Result
                        </button>

                    </div>

                </form>

            </div>

        </section>

    </div>

    <!-- Information -->

    <div class="result-info">

        <div class="result-info-icon">
            💡
        </div>

        <div>

            <strong>
                Result workflow
            </strong>

            <p>
                After saving the result, the match will be marked as completed
                by the existing result-processing workflow and the tournament
                standings can be recalculated according to its points system.
            </p>

        </div>

    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const form = document.getElementById('matchResultForm');

        const teamAInput = document.getElementById('team_a_score');

        const teamBInput = document.getElementById('team_b_score');

        const winnerPreview = document.getElementById('winnerPreview');

        const teamAName =
            <?= json_encode(
                (string) $match['team_a_name'],
                JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_QUOT |
                JSON_HEX_AMP
            ) ?>;

        const teamBName =
            <?= json_encode(
                (string) $match['team_b_name'],
                JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_QUOT |
                JSON_HEX_AMP
            ) ?>;

        /*
        |--------------------------------------------------------------------------
        | Show winner preview
        |--------------------------------------------------------------------------
        */

        function updateWinnerPreview() {

            const scoreA = parseFloat(teamAInput.value);
            const scoreB = parseFloat(teamBInput.value);

            if (
                Number.isNaN(scoreA) ||
                Number.isNaN(scoreB)
            ) {
                winnerPreview.style.display = 'none';
                winnerPreview.textContent = '';
                return;
            }

            if (scoreA > scoreB) {

                winnerPreview.textContent =
                    '🏆 Winner: ' + teamAName;

                winnerPreview.style.display = 'block';

            } else if (scoreB > scoreA) {

                winnerPreview.textContent =
                    '🏆 Winner: ' + teamBName;

                winnerPreview.style.display = 'block';

            } else {

                winnerPreview.textContent =
                    '🤝 Result: Draw';

                winnerPreview.style.display = 'block';

            }

        }

        teamAInput.addEventListener(
            'input',
            updateWinnerPreview
        );

        teamBInput.addEventListener(
            'input',
            updateWinnerPreview
        );

        /*
        |--------------------------------------------------------------------------
        | Form validation
        |--------------------------------------------------------------------------
        */

        form.addEventListener('submit', function (event) {

            const scoreA = parseFloat(teamAInput.value);
            const scoreB = parseFloat(teamBInput.value);

            if (
                Number.isNaN(scoreA) ||
                Number.isNaN(scoreB)
            ) {
                return;
            }

            if (scoreA < 0 || scoreB < 0) {

                event.preventDefault();

                alert('Scores cannot be negative.');

                return;
            }

            updateWinnerPreview();

        });

    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>