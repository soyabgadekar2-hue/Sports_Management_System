<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);


/*
|--------------------------------------------------------------------------
| Only POST allowed
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: admin-tournaments.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

requireValidCsrfToken(
    $_POST['csrf_token'] ?? null
);


$pdo = db();


/*
|--------------------------------------------------------------------------
| Read Form Data
|--------------------------------------------------------------------------
*/

$sportId = filter_input(
    INPUT_POST,
    'sport_id',
    FILTER_VALIDATE_INT
);

$venueId = filter_input(
    INPUT_POST,
    'venue_id',
    FILTER_VALIDATE_INT
);

$tournamentName = trim(
    (string) (
        $_POST['tournament_name'] ?? ''
    )
);

$description = trim(
    (string) (
        $_POST['tournament_description'] ?? ''
    )
);

$startDate = trim(
    (string) (
        $_POST['start_date'] ?? ''
    )
);

$endDate = trim(
    (string) (
        $_POST['end_date'] ?? ''
    )
);

$format = trim(
    (string) (
        $_POST['tournament_format'] ?? ''
    )
);

$pointsWin = filter_input(
    INPUT_POST,
    'points_win',
    FILTER_VALIDATE_FLOAT
);

$pointsDraw = filter_input(
    INPUT_POST,
    'points_draw',
    FILTER_VALIDATE_FLOAT
);

$pointsLoss = filter_input(
    INPUT_POST,
    'points_loss',
    FILTER_VALIDATE_FLOAT
);

$rules = trim(
    (string) (
        $_POST['rules_information'] ?? ''
    )
);

$status = trim(
    (string) (
        $_POST['tournament_status'] ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| Validate Sport
|--------------------------------------------------------------------------
*/

if (!$sportId) {

    header(
        'Location: create-tournament.php?error=' .
        urlencode('Please select a valid sport.')
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Tournament Name
|--------------------------------------------------------------------------
*/

if (
    $tournamentName === '' ||
    strlen($tournamentName) > 150
) {

    header(
        'Location: create-tournament.php?error=' .
        urlencode('Please enter a valid tournament name.')
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Format
|--------------------------------------------------------------------------
*/

if (!in_array(
    $format,
    ['LEAGUE'],
    true
)) {

    header(
        'Location: create-tournament.php?error=' .
        urlencode('Invalid tournament format.')
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Status
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'DRAFT',
    'REGISTRATION_OPEN',
    'ACTIVE',
    'COMPLETED',
    'CANCELLED'
];

if (!in_array(
    $status,
    $allowedStatuses,
    true
)) {

    header(
        'Location: create-tournament.php?error=' .
        urlencode('Invalid tournament status.')
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Points
|--------------------------------------------------------------------------
*/

if (
    $pointsWin === false ||
    $pointsWin < 0
) {

    header(
        'Location: create-tournament.php?error=' .
        urlencode('Invalid win points.')
    );

    exit;
}


if (
    $pointsDraw === false ||
    $pointsDraw < 0
) {

    header(
        'Location: create-tournament.php?error=' .
        urlencode('Invalid draw points.')
    );

    exit;
}


if (
    $pointsLoss === false ||
    $pointsLoss < 0
) {

    header(
        'Location: create-tournament.php?error=' .
        urlencode('Invalid loss points.')
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Dates
|--------------------------------------------------------------------------
*/

if (
    $startDate === '' ||
    $endDate === ''
) {

    header(
        'Location: create-tournament.php?error=' .
        urlencode('Start and end dates are required.')
    );

    exit;
}


if ($endDate < $startDate) {

    header(
        'Location: create-tournament.php?error=' .
        urlencode('End date cannot be before start date.')
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Sport Exists and Is Active
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT sport_id
    FROM sports
    WHERE sport_id = :sport_id
      AND sport_status = 'ACTIVE'
    LIMIT 1
");

$stmt->execute([
    ':sport_id' => $sportId
]);

if (!$stmt->fetch()) {

    header(
        'Location: create-tournament.php?error=' .
        urlencode('Selected sport is not available.')
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Venue
|--------------------------------------------------------------------------
|
| IMPORTANT:
| venues uses availability_status = AVAILABLE
|
*/

if (
    $venueId !== false &&
    $venueId !== null
) {

    $stmt = $pdo->prepare("
        SELECT venue_id
        FROM venues
        WHERE venue_id = :venue_id
          AND availability_status = 'AVAILABLE'
        LIMIT 1
    ");

    $stmt->execute([
        ':venue_id' => $venueId
    ]);

    if (!$stmt->fetch()) {

        header(
            'Location: create-tournament.php?error=' .
            urlencode('Selected venue is not available.')
        );

        exit;
    }

} else {

    $venueId = null;
}


/*
|--------------------------------------------------------------------------
| Check Duplicate Tournament Name
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT tournament_id
    FROM tournaments
    WHERE sport_id = :sport_id
      AND tournament_name = :tournament_name
    LIMIT 1
");

$stmt->execute([
    ':sport_id' => $sportId,
    ':tournament_name' => $tournamentName
]);

if ($stmt->fetch()) {

    header(
        'Location: create-tournament.php?error=' .
        urlencode(
            'A tournament with this name already exists for this sport.'
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$user = currentUser();

if ($user === null) {

    http_response_code(401);

    exit('Unauthorized');
}

$createdByUserId = (int) $user['id'];


/*
|--------------------------------------------------------------------------
| Create Tournament
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    $stmt = $pdo->prepare("
        INSERT INTO tournaments (

            sport_id,

            venue_id,

            tournament_name,

            tournament_description,

            start_date,

            end_date,

            tournament_format,

            points_win,

            points_draw,

            points_loss,

            rules_information,

            tournament_status,

            created_by_user_id

        )

        VALUES (

            :sport_id,

            :venue_id,

            :tournament_name,

            :tournament_description,

            :start_date,

            :end_date,

            :tournament_format,

            :points_win,

            :points_draw,

            :points_loss,

            :rules_information,

            :tournament_status,

            :created_by_user_id

        )
    ");


    $stmt->execute([

        ':sport_id' =>
            $sportId,

        ':venue_id' =>
            $venueId,

        ':tournament_name' =>
            $tournamentName,

        ':tournament_description' =>
            $description !== ''
                ? $description
                : null,

        ':start_date' =>
            $startDate,

        ':end_date' =>
            $endDate,

        ':tournament_format' =>
            $format,

        ':points_win' =>
            $pointsWin,

        ':points_draw' =>
            $pointsDraw,

        ':points_loss' =>
            $pointsLoss,

        ':rules_information' =>
            $rules !== ''
                ? $rules
                : null,

        ':tournament_status' =>
            $status,

        ':created_by_user_id' =>
            $createdByUserId

    ]);


    $tournamentId = (int) $pdo->lastInsertId();


    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */

    auditLog(

        'TOURNAMENT_CREATED',

        'TOURNAMENT',

        $tournamentId,

        'Created tournament: ' .
        $tournamentName

    );


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        'Location: admin-tournaments.php?message=created'
    );

    exit;


} catch (Throwable $exception) {


    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    error_log(
        'Tournament creation failed: ' .
        $exception->getMessage()
    );


    header(
        'Location: create-tournament.php?error=' .
        urlencode(
            'Unable to create tournament. Please try again.'
        )
    );

    exit;
}