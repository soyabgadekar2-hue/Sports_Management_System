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
| Helper Functions
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

function formatMatchDateTime(?string $dateTime): string
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

/*
|--------------------------------------------------------------------------
| Get Match and Existing Result
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

        t.tournament_name,
        t.tournament_format,

        sport.sport_name,

        team_a.team_name AS team_a_name,
        team_b.team_name AS team_b_name,

        v.venue_name,

        mr.team_a_score,
        mr.team_b_score,
        mr.result_notes

    FROM matches m

    INNER JOIN tournaments t
        ON t.tournament_id = m.tournament_id

    INNER JOIN sports sport
        ON sport.sport_id = t.sport_id

    INNER JOIN teams team_a
        ON team_a.team_id = m.team_a_id

    INNER JOIN teams team_b
        ON team_b.team_id = m.team_b_id

    INNER JOIN venues v
        ON v.venue_id = m.venue_id

    INNER JOIN match_results mr
        ON mr.match_id = m.match_id

    WHERE m.match_id = :match_id

    LIMIT 1
");

$matchStmt->execute([
    'match_id' => $matchId
]);

$match = $matchStmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    exit('Completed match result not found.');
}

if ($match['match_status'] !== 'COMPLETED') {
    exit('Only completed matches can have their results corrected.');
}

/*
|--------------------------------------------------------------------------
| Sport-Specific Result Configuration
|--------------------------------------------------------------------------
*/

$sportName = (string) ($match['sport_name'] ?? '');

$resultLabel = 'Score';
$resultIcon = '🏆';
$resultStep = '1';
$resultHint = 'Enter the final score for each team.';

switch (strtolower(trim($sportName))) {
    case 'football':
        $resultLabel = 'Goals';
        $resultIcon = '⚽';
        $resultStep = '1';
        $resultHint = 'Enter the number of goals scored by each team.';
        break;

    case 'cricket':
        $resultLabel = 'Runs';
        $resultIcon = '🏏';
        $resultStep = '1';
        $resultHint = 'Enter the total runs scored by each team.';
        break;

    case 'basketball':
        $resultLabel = 'Points';
        $resultIcon = '🏀';
        $resultStep = '1';
        $resultHint = 'Enter the final points scored by each team.';
        break;

    case 'volleyball':
        $resultLabel = 'Sets Won';
        $resultIcon = '🏐';
        $resultStep = '1';
        $resultHint = 'Enter the number of sets won by each team.';
        break;

    case 'badminton':
        $resultLabel = 'Games Won';
        $resultIcon = '🏸';
        $resultStep = '1';
        $resultHint = 'Enter the number of games won by each team.';
        break;

    case 'table tennis':
    case 'tabletennis':
        $resultLabel = 'Games Won';
        $resultIcon = '🏓';
        $resultStep = '1';
        $resultHint = 'Enter the number of games won by each team.';
        break;

    case 'carrom':
        $resultLabel = 'Points';
        $resultIcon = '🎯';
        $resultStep = '1';
        $resultHint = 'Enter the final points for each team.';
        break;

    case 'chess':
        $resultLabel = 'Result Points';
        $resultIcon = '♟️';
        $resultStep = '0.5';
        $resultHint = 'Use 1 for a win, 0 for a loss, and 0.5 for a draw.';
        break;

    case 'athletics':
        $resultLabel = 'Score';
        $resultIcon = '🏅';
        $resultStep = '0.01';
        $resultHint = 'Enter the recorded result/score.';
        break;

    case 'swimming':
        $resultLabel = 'Score';
        $resultIcon = '🏊';
        $resultStep = '0.01';
        $resultHint = 'Enter the recorded result/score.';
        break;
}

$error = $_GET['error'] ?? '';

$currentA = (string) ($match['team_a_score'] ?? '0');
$currentB = (string) ($match['team_b_score'] ?? '0');

/*
|--------------------------------------------------------------------------
| Determine Current Result Display
|--------------------------------------------------------------------------
*/

$winnerText = 'Draw';

if (
    is_numeric($currentA)
    && is_numeric($currentB)
) {
    $scoreA = (float) $currentA;
    $scoreB = (float) $currentB;

    if ($scoreA > $scoreB) {
        $winnerText = $match['team_a_name'];
    } elseif ($scoreB > $scoreA) {
        $winnerText = $match['team_b_name'];
    }
}

$pageTitle = 'Edit Match Result';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .edit-result-page {
        max-width: 1180px;
        margin: 0 auto;
        padding: 24px 20px 50px;
    }

    .result-hero {
        background: linear-gradient(135deg, #0d47a1, #1565c0);
        color: #ffffff;
        border-radius: 22px;
        padding: 30px;
        margin-bottom: 24px;
        box-shadow: 0 12px 30px rgba(13, 71, 161, 0.18);
    }

    .result-hero-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
    }

    .result-hero h1 {
        margin: 0 0 8px;
        font-size: 30px;
        line-height: 1.2;
    }

    .result-hero p {
        margin: 0;
        opacity: 0.92;
        line-height: 1.6;
    }

    .sport-badge {
        min-width: 110px;
        text-align: center;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.24);
        border-radius: 16px;
        padding: 14px 16px;
    }

    .sport-badge-icon {
        display: block;
        font-size: 30px;
        margin-bottom: 5px;
    }

    .sport-badge-name {
        font-weight: 700;
        font-size: 14px;
    }

    .back-links {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 22px;
    }

    .back-links a {
        text-decoration: none;
        background: #ffffff;
        color: #0d47a1;
        border: 1px solid #d8e2f0;
        padding: 9px 14px;
        border-radius: 10px;
        font-weight: 600;
        transition: 0.2s ease;
    }

    .back-links a:hover {
        background: #eef5ff;
        transform: translateY(-1px);
    }

    .error-box {
        background: #fff1f2;
        color: #b42318;
        border: 1px solid #fecdd3;
        border-radius: 12px;
        padding: 14px 16px;
        margin-bottom: 20px;
        font-weight: 600;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .info-card {
        background: #ffffff;
        border: 1px solid #e3eaf3;
        border-radius: 16px;
        padding: 18px;
        box-shadow: 0 5px 18px rgba(15, 35, 65, 0.06);
    }

    .info-card-label {
        display: block;
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 7px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .info-card-value {
        color: #172033;
        font-size: 16px;
        font-weight: 700;
        line-height: 1.4;
    }

    .match-card {
        background: #ffffff;
        border: 1px solid #e3eaf3;
        border-radius: 20px;
        padding: 26px;
        margin-bottom: 24px;
        box-shadow: 0 8px 24px rgba(15, 35, 65, 0.07);
    }

    .section-heading {
        margin-bottom: 20px;
    }

    .section-heading h2 {
        margin: 0 0 6px;
        color: #172033;
        font-size: 22px;
    }

    .section-heading p {
        margin: 0;
        color: #64748b;
        line-height: 1.5;
    }

    .teams-scoreboard {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        gap: 18px;
        margin-bottom: 26px;
    }

    .team-panel {
        border: 1px solid #dbe5f1;
        background: #f8fbff;
        border-radius: 18px;
        padding: 22px;
        text-align: center;
    }

    .team-panel.team-a {
        border-top: 4px solid #1565c0;
    }

    .team-panel.team-b {
        border-top: 4px solid #0d47a1;
    }

    .team-name {
        display: block;
        min-height: 48px;
        color: #172033;
        font-size: 18px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .current-score {
        display: block;
        margin-top: 10px;
        font-size: 34px;
        font-weight: 800;
        color: #0d47a1;
    }

    .score-separator {
        color: #64748b;
        font-size: 24px;
        font-weight: 800;
    }

    .result-banner {
        display: flex;
        align-items: center;
        gap: 12px;
        background: #eef6ff;
        border: 1px solid #cfe2ff;
        color: #174a8b;
        border-radius: 13px;
        padding: 14px 16px;
        margin-bottom: 24px;
    }

    .result-banner-icon {
        font-size: 24px;
    }

    .result-banner strong {
        display: block;
        margin-bottom: 2px;
    }

    .score-form {
        margin-top: 8px;
    }

    .score-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        margin-bottom: 22px;
    }

    .score-field {
        border: 1px solid #dbe5f1;
        border-radius: 16px;
        padding: 18px;
        background: #ffffff;
    }

    .score-field label {
        display: block;
        color: #172033;
        font-weight: 700;
        margin-bottom: 10px;
        line-height: 1.4;
    }

    .score-field input {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 13px 14px;
        font-size: 18px;
        font-weight: 700;
        color: #172033;
        outline: none;
        background: #ffffff;
        transition: 0.2s ease;
    }

    .score-field input:focus {
        border-color: #1565c0;
        box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.12);
    }

    .field-hint {
        margin: 8px 0 0;
        color: #64748b;
        font-size: 13px;
        line-height: 1.45;
    }

    .notes-field {
        margin-bottom: 22px;
    }

    .notes-field label {
        display: block;
        color: #172033;
        font-weight: 700;
        margin-bottom: 9px;
    }

    .notes-field textarea {
        width: 100%;
        min-height: 130px;
        box-sizing: border-box;
        resize: vertical;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 13px 14px;
        font: inherit;
        color: #172033;
        outline: none;
        transition: 0.2s ease;
    }

    .notes-field textarea:focus {
        border-color: #1565c0;
        box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.12);
    }

    .warning-box {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        background: #fff8e7;
        border: 1px solid #f4d58d;
        color: #7a5100;
        border-radius: 14px;
        padding: 15px 16px;
        margin-bottom: 22px;
        line-height: 1.5;
    }

    .warning-icon {
        font-size: 21px;
    }

    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 44px;
        padding: 10px 18px;
        border-radius: 11px;
        border: 1px solid transparent;
        text-decoration: none;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s ease;
        box-sizing: border-box;
    }

    .btn-primary {
        background: #1565c0;
        color: #ffffff;
        border-color: #1565c0;
    }

    .btn-primary:hover {
        background: #0d47a1;
        border-color: #0d47a1;
        transform: translateY(-1px);
    }

    .btn-secondary {
        background: #ffffff;
        color: #334155;
        border-color: #cbd5e1;
    }

    .btn-secondary:hover {
        background: #f8fafc;
    }

    .live-preview {
        margin-top: 24px;
        background: #f8fbff;
        border: 1px solid #dbe5f1;
        border-radius: 15px;
        padding: 16px;
    }

    .live-preview-title {
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.05em;
        margin-bottom: 7px;
    }

    .live-preview-result {
        color: #172033;
        font-size: 18px;
        font-weight: 800;
    }

    @media (max-width: 850px) {
        .info-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .teams-scoreboard {
            grid-template-columns: 1fr;
        }

        .score-separator {
            text-align: center;
        }
    }

    @media (max-width: 620px) {
        .edit-result-page {
            padding: 16px 12px 35px;
        }

        .result-hero {
            padding: 22px 18px;
            border-radius: 17px;
        }

        .result-hero-top {
            flex-direction: column;
        }

        .result-hero h1 {
            font-size: 24px;
        }

        .sport-badge {
            width: 100%;
            box-sizing: border-box;
        }

        .info-grid,
        .score-grid {
            grid-template-columns: 1fr;
        }

        .match-card {
            padding: 18px;
            border-radius: 16px;
        }

        .team-name {
            min-height: auto;
            padding: 5px 0;
        }

        .form-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .form-actions .btn {
            width: 100%;
        }
    }
</style>

<div class="edit-result-page">

    <div class="back-links">
        <a href="match-results.php?tournament_id=<?= (int) $match['tournament_id'] ?>">
            ← Back to Match Results
        </a>

        <a href="manage-tournament.php?tournament_id=<?= (int) $match['tournament_id'] ?>">
            Tournament
        </a>

        <a href="dashboard.php">
            Dashboard
        </a>
    </div>

    <section class="result-hero">
        <div class="result-hero-top">
            <div>
                <h1>✏️ Correct Match Result</h1>

                <p>
                    Update the recorded result for
                    <strong>
                        <?= pageEscape($match['team_a_name']) ?>
                    </strong>
                    vs
                    <strong>
                        <?= pageEscape($match['team_b_name']) ?>
                    </strong>.
                </p>
            </div>

            <div class="sport-badge">
                <span class="sport-badge-icon">
                    <?= pageEscape($resultIcon) ?>
                </span>

                <span class="sport-badge-name">
                    <?= pageEscape($sportName) ?>
                </span>
            </div>
        </div>
    </section>

    <?php if ($error !== ''): ?>
        <div class="error-box">
            ⚠️ <?= pageEscape($error) ?>
        </div>
    <?php endif; ?>

    <section class="info-grid">

        <div class="info-card">
            <span class="info-card-label">Tournament</span>
            <span class="info-card-value">
                <?= pageEscape($match['tournament_name']) ?>
            </span>
        </div>

        <div class="info-card">
            <span class="info-card-label">Match</span>
            <span class="info-card-value">
                Match #<?= (int) $match['match_number'] ?>
            </span>
        </div>

        <div class="info-card">
            <span class="info-card-label">Format</span>
            <span class="info-card-value">
                <?= pageEscape($match['tournament_format']) ?>
            </span>
        </div>

        <div class="info-card">
            <span class="info-card-label">Venue</span>
            <span class="info-card-value">
                <?= pageEscape($match['venue_name']) ?>
            </span>
        </div>

        <div class="info-card">
            <span class="info-card-label">Scheduled</span>
            <span class="info-card-value">
                <?= pageEscape(formatMatchDateTime($match['scheduled_start'])) ?>
            </span>
        </div>

        <div class="info-card">
            <span class="info-card-label">Status</span>
            <span class="info-card-value">
                Completed
            </span>
        </div>

    </section>

    <section class="match-card">

        <div class="section-heading">
            <h2>📊 Current Result</h2>
            <p>
                Review the currently saved result before making corrections.
            </p>
        </div>

        <div class="teams-scoreboard">

            <div class="team-panel team-a">
                <span class="team-name">
                    <?= pageEscape($match['team_a_name']) ?>
                </span>

                <span class="current-score" id="currentScoreA">
                    <?= pageEscape($currentA) ?>
                </span>
            </div>

            <div class="score-separator">
                VS
            </div>

            <div class="team-panel team-b">
                <span class="team-name">
                    <?= pageEscape($match['team_b_name']) ?>
                </span>

                <span class="current-score" id="currentScoreB">
                    <?= pageEscape($currentB) ?>
                </span>
            </div>

        </div>

        <div class="result-banner">
            <span class="result-banner-icon">
                <?= pageEscape($resultIcon) ?>
            </span>

            <div>
                <strong>Current Result</strong>

                <span id="currentWinner">
                    <?= pageEscape($winnerText) ?>
                </span>
            </div>
        </div>

    </section>

    <section class="match-card">

        <div class="section-heading">
            <h2>
                <?= pageEscape($resultIcon) ?>
                Correct <?= pageEscape($resultLabel) ?>
            </h2>

            <p>
                <?= pageEscape($resultHint) ?>
            </p>
        </div>

        <form
            method="POST"
            action="update-match-result.php"
            class="score-form"
            id="editResultForm"
        >

            <?= csrfField() ?>

            <input
                type="hidden"
                name="match_id"
                value="<?= (int) $matchId ?>"
            >

            <div class="score-grid">

                <div class="score-field">

                    <label for="team_a_score">
                        <?= pageEscape($match['team_a_name']) ?>
                        — <?= pageEscape($resultLabel) ?>
                    </label>

                    <input
                        type="number"
                        id="team_a_score"
                        name="team_a_score"
                        min="0"
                        step="<?= pageEscape($resultStep) ?>"
                        value="<?= pageEscape($currentA) ?>"
                        required
                    >

                    <p class="field-hint">
                        Enter the corrected <?= strtolower(pageEscape($resultLabel)) ?>.
                    </p>

                </div>

                <div class="score-field">

                    <label for="team_b_score">
                        <?= pageEscape($match['team_b_name']) ?>
                        — <?= pageEscape($resultLabel) ?>
                    </label>

                    <input
                        type="number"
                        id="team_b_score"
                        name="team_b_score"
                        min="0"
                        step="<?= pageEscape($resultStep) ?>"
                        value="<?= pageEscape($currentB) ?>"
                        required
                    >

                    <p class="field-hint">
                        Enter the corrected <?= strtolower(pageEscape($resultLabel)) ?>.
                    </p>

                </div>

            </div>

            <div class="live-preview">

                <div class="live-preview-title">
                    Updated Result Preview
                </div>

                <div
                    class="live-preview-result"
                    id="livePreview"
                >
                    <?= pageEscape($match['team_a_name']) ?>
                    <?= pageEscape($currentA) ?>
                    —
                    <?= pageEscape($currentB) ?>
                    <?= pageEscape($match['team_b_name']) ?>
                    •
                    <?= pageEscape($winnerText) ?>
                </div>

            </div>

            <br>

            <div class="notes-field">

                <label for="result_notes">
                    📝 Result Notes
                </label>

                <textarea
                    id="result_notes"
                    name="result_notes"
                    maxlength="1000"
                    placeholder="Add any correction reason, match notes, or important result information..."
                ><?= pageEscape($match['result_notes'] ?? '') ?></textarea>

            </div>

            <div class="warning-box">

                <span class="warning-icon">
                    ⚠️
                </span>

                <div>
                    <strong>Important:</strong>
                    Correcting this result will change the tournament
                    standings and may affect team statistics.
                    Make sure the updated result is accurate before saving.
                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    💾 Update Match Result
                </button>

                <a
                    href="match-results.php?tournament_id=<?= (int) $match['tournament_id'] ?>"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const scoreA = document.getElementById('team_a_score');
    const scoreB = document.getElementById('team_b_score');
    const preview = document.getElementById('livePreview');

    const teamAName = <?= json_encode((string) $match['team_a_name']) ?>;
    const teamBName = <?= json_encode((string) $match['team_b_name']) ?>;

    function updatePreview() {
        const a = parseFloat(scoreA.value);
        const b = parseFloat(scoreB.value);

        const valueA = scoreA.value === '' ? '—' : scoreA.value;
        const valueB = scoreB.value === '' ? '—' : scoreB.value;

        let resultText = 'Enter both results';

        if (!Number.isNaN(a) && !Number.isNaN(b)) {
            if (a > b) {
                resultText = teamAName + ' leads';
            } else if (b > a) {
                resultText = teamBName + ' leads';
            } else {
                resultText = 'Draw';
            }
        }

        preview.textContent =
            teamAName +
            ' ' +
            valueA +
            ' — ' +
            valueB +
            ' ' +
            teamBName +
            ' • ' +
            resultText;
    }

    scoreA.addEventListener('input', updatePreview);
    scoreB.addEventListener('input', updatePreview);

    updatePreview();
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>