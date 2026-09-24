<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

requireValidCsrfToken($_POST['csrf_token'] ?? null);

$user = currentUser();
$pdo = db();

/*
|--------------------------------------------------------------------------
| 1. Read and validate form data
|--------------------------------------------------------------------------
*/

$sportId = filter_var(
    $_POST['sport_id'] ?? null,
    FILTER_VALIDATE_INT
);

$teamName = trim($_POST['team_name'] ?? '');

$teamCategory = strtoupper(
    trim($_POST['team_category'] ?? '')
);

$coachId = filter_var(
    $_POST['coach_id'] ?? null,
    FILTER_VALIDATE_INT
);

$rosterLimit = filter_var(
    $_POST['roster_limit'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $sportId === false
    || $sportId === null
    || $sportId <= 0
) {
    http_response_code(400);
    exit('Invalid sport.');
}

if ($teamName === '') {
    http_response_code(400);
    exit('Team name is required.');
}

if (mb_strlen($teamName) > 120) {
    http_response_code(400);
    exit('Team name is too long.');
}

$allowedCategories = [
    'OPEN',
    'MEN',
    'WOMEN',
    'MIXED'
];

if (!in_array($teamCategory, $allowedCategories, true)) {
    http_response_code(400);
    exit('Invalid team category.');
}

if (
    $rosterLimit === false
    || $rosterLimit === null
    || $rosterLimit < 1
    || $rosterLimit > 500
) {
    http_response_code(400);
    exit('Invalid roster limit.');
}

/*
|--------------------------------------------------------------------------
| 2. Validate selected sport
|--------------------------------------------------------------------------
*/

$sportStatement = $pdo->prepare(
    'SELECT
        sport_id,
        sport_name,
        default_max_team_players
     FROM sports
     WHERE sport_id = :sport_id
       AND sport_status = :status
     LIMIT 1'
);

$sportStatement->execute([
    ':sport_id' => $sportId,
    ':status' => 'ACTIVE'
]);

$sport = $sportStatement->fetch(PDO::FETCH_ASSOC);

if (!$sport) {
    http_response_code(400);
    exit('Selected sport is not active or does not exist.');
}

/*
|--------------------------------------------------------------------------
| 3. Validate coach if selected
|--------------------------------------------------------------------------
*/

$validatedCoachId = null;

if (
    $coachId !== false
    && $coachId !== null
    && $coachId > 0
) {
    $coachStatement = $pdo->prepare(
        'SELECT
            cp.coach_id
         FROM coach_profiles cp
         INNER JOIN users u
            ON u.user_id = cp.user_id
         WHERE cp.coach_id = :coach_id
           AND cp.coach_status = :coach_status
           AND u.account_status = :account_status
         LIMIT 1'
    );

    $coachStatement->execute([
        ':coach_id' => $coachId,
        ':coach_status' => 'ACTIVE',
        ':account_status' => 'APPROVED'
    ]);

    $coach = $coachStatement->fetch(PDO::FETCH_ASSOC);

    if (!$coach) {
        http_response_code(400);
        exit('Selected coach is not active or approved.');
    }

    $validatedCoachId = $coachId;
}

/*
|--------------------------------------------------------------------------
| 4. Check duplicate team name within the same sport
|--------------------------------------------------------------------------
*/

$duplicateStatement = $pdo->prepare(
    'SELECT
        team_id
     FROM teams
     WHERE sport_id = :sport_id
       AND team_name = :team_name
     LIMIT 1'
);

$duplicateStatement->execute([
    ':sport_id' => $sportId,
    ':team_name' => $teamName
]);

$duplicateTeam = $duplicateStatement->fetch(PDO::FETCH_ASSOC);

if ($duplicateTeam) {
    http_response_code(409);
    exit(
        'A team with this name already exists for the selected sport.'
    );
}

/*
|--------------------------------------------------------------------------
| 5. Create team
|--------------------------------------------------------------------------
*/

$pdo->beginTransaction();

try {
    $insertStatement = $pdo->prepare(
        'INSERT INTO teams (
            sport_id,
            coach_id,
            team_name,
            team_category,
            roster_limit,
            team_status
         )
         VALUES (
            :sport_id,
            :coach_id,
            :team_name,
            :team_category,
            :roster_limit,
            :team_status
         )'
    );

    $insertStatement->execute([
        ':sport_id' => $sportId,
        ':coach_id' => $validatedCoachId,
        ':team_name' => $teamName,
        ':team_category' => $teamCategory,
        ':roster_limit' => $rosterLimit,
        ':team_status' => 'ACTIVE'
    ]);

    $teamId = (int) $pdo->lastInsertId();

    $pdo->commit();

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Team creation failed: '
        . $exception->getMessage()
    );

    http_response_code(500);
    exit('Unable to create team.');
}

/*
|--------------------------------------------------------------------------
| 6. Audit the action
|--------------------------------------------------------------------------
*/

auditLog(
    'TEAM_CREATED',
    'TEAM',
    $teamId,
    'Team created: '
    . $teamName
    . ' for sport '
    . $sport['sport_name']
);

/*
|--------------------------------------------------------------------------
| 7. Redirect
|--------------------------------------------------------------------------
*/

header(
    'Location: admin-teams.php?message=created'
);

exit;