
<?php

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function sendJsonResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function normalizeAssistantText(string $text): string
{
    $text = trim($text);

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($text, 'UTF-8');
    }

    return strtolower($text);
}

function containsAny(string $message, array $keywords): bool
{
    foreach ($keywords as $keyword) {
        if (strpos($message, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

function getRoleName(string $role): string
{
    return match (strtoupper($role)) {
        'ADMIN' => 'Admin',
        'SPORTS_COORDINATOR' => 'Sports Coordinator',
        'COACH' => 'Coach',
        'PLAYER' => 'Player',
        default => 'SportSync user',
    };
}

function getRoleHelp(string $role): string
{
    return match (strtoupper($role)) {
        'ADMIN' =>
            "As an Admin, you generally manage users, account approvals, system settings, and overall system activity. Available options depend on your SportSync setup.",

        'SPORTS_COORDINATOR' =>
            "As a Sports Coordinator, you generally organize sports activities, teams, and matches. Use the relevant coordinator pages available in your account.",

        'COACH' =>
            "As a Coach, you generally work with your assigned teams and players, review match information, and maintain player statistics where those features are available.",

        'PLAYER' =>
            "As a Player, you can generally view your profile, assigned team, match information, and statistics available to your account.",

        default =>
            "Your available pages and actions depend on your assigned SportSync role.",
    };
}

function getAssistantReply(string $message, string $role): string
{
    $text = normalizeAssistantText($message);
    $roleName = getRoleName($role);

    // Greetings
    if (preg_match('/^(hi|hello|hey)(\b|[!.?,]|$)/i', $text)) {
        return "Hello! 👋 I’m the SportSync Help Assistant. I can guide you through common  system features and sports-management topics. What would you like help with?";
    }

    // Website help
    if (containsAny($text, [
        'how to use this website',
        'how do i use',
        'how to use',
        'use this website',
        'navigate',
        'website help',
        'guide me',
    ])) {
        return "Welcome to SportSync! Here's how to get started:\n\n" .
            "1. Use the menu to open the pages available for your role.\n" .
            "2. Open your dashboard to review the information and actions available to you.\n" .
            "3. Use the relevant pages to view teams, players, matches, or statistics.\n" .
            "4. If a page or action is not available, contact your Admin or authorized  Coordinator.\n\n" .
            "Tell me which page you want to use, and I’ll explain it step by step.";
    }

    // Assistant capabilities
    if (containsAny($text, [
        'what can you do',
        'your features',
        'help me',
        'what do you do',
        'who are you',
    ])) {
        return "I’m the SportSync Help Assistant. I can explain common features, roles, matches, teams, and statistics. I use prepared answers and cannot access live database records or perform actions for you.";
    }

    // Role-specific guidance
    if (containsAny($text, [
        'my role',
        'my responsibility',
        'what can i do',
        'my permissions',
        'my dashboard',
    ])) {
        return "You are using SportSync as a {$roleName}.\n\n" .
            getRoleHelp($role) .
            "\n\nYour exact menu options depend on how your project is configured.";
    }

    // Login and account help
    if (containsAny($text, [
        'login',
        'log in',
        'sign in',
        'cannot access',
        "can't access",
        'password',
        'account pending',
        'pending approval',
        'account rejected',
        'suspended',
    ])) {
        return "For login or account problems:\n" .
            "1. Check that you entered the correct email and password.\n" .
            "2. Confirm that your account has been approved if approval is required.\n" .
            "3. If your account is pending, rejected, or suspended, contact the Admin or authorized coordinator.\n" .
            "4. Never share your password with anyone.";
    }

    // Match help
    if (containsAny($text, [
        'match',
        'fixture',
        'schedule',
        'tournament',
        'league',
        'knockout',
    ])) {
        return "A match is a game between teams or players. A fixture is a scheduled match with details such as teams, date, time, venue, and status.\n\n" .
            "To check match information, open the matches page available in your account. A Coach or Coordinator may have additional match-related options depending on permissions.\n\n" .
            "I cannot create or change a match from this chat.";
    }

    // Team help
    if (containsAny($text, [
        'team',
        'squad',
        'team member',
        'team members',
    ])) {
        return "A team is a group of players representing a club, class, or college in a sport. Team pages commonly show team details and assigned players.\n\n" .
            "Open the team-related page available in your SportSync account. If you cannot see a team or player, contact the authorized Coach or Coordinator.";
    }

    // Player help
    if (containsAny($text, [
        'player',
        'student',
        'registration',
        'register',
        'profile',
    ])) {
        return "Player profiles commonly contain personal or student details and sports-related information. Keep your profile information accurate.\n\n" .
            "If you need to update a detail that you cannot edit, contact the authorized person for your account. Do not share private student information in this chat.";
    }

    // Statistics help
    if (containsAny($text, [
        'statistic',
        'statistics',
        'stats',
        'performance',
        'score',
        'points',
        'goals',
        'runs',
        'wickets',
        'result',
    ])) {
        return "Sports statistics record performance during a match or competition. Depending on the sport, examples include goals, runs, wickets, points, assists, or appearances.\n\n" .
            "Open the statistics or match details page available to your role to review recorded information. I cannot view or change your live statistics.";
    }

    // Coach-specific guidance
    if (
        strtoupper($role) === 'COACH' &&
        containsAny($text, [
            'coach',
            'training',
            'practice',
            'attendance',
        ])
    ) {
        return "As a Coach, you can use the pages available to you to review assigned teams, players, matches, and statistics. For training or attendance features, check whether they are included in your SportSync installation.";
    }

    // Coordinator-specific guidance
    if (
        strtoupper($role) === 'SPORTS_COORDINATOR' &&
        containsAny($text, [
            'coordinator',
            'organize',
            'organise',
            'event',
            'competition',
        ])
    ) {
        return "As a Sports Coordinator, you generally help organize sports activities and competitions. Check the coordinator pages available in your account for team and match management options. Contact an Admin if you need access that is not available.";
    }

    // Admin-specific guidance
    if (
        strtoupper($role) === 'ADMIN' &&
        containsAny($text, [
            'admin',
            'approve',
            'approval',
            'user account',
            'manage user',
        ])
    ) {
        return "As an Admin, you may have access to account management and approval features. Open the relevant Admin page and review the account details before taking action. I cannot approve accounts or change user records through this chat.";
    }

    // General sports questions
    if (containsAny($text, [
        'football',
        'soccer',
        'cricket',
        'basketball',
        'volleyball',
        'badminton',
        'sports rule',
        'rules',
    ])) {
        return "I can help with basic sports-management concepts, but this offline assistant has limited prepared answers for individual sports rules. Ask about a specific topic, such as match fixtures, teams, player statistics, or tournament formats.";
    }

    // Default response
    return "I’m not sure how to answer that with my current offline help topics.\n\n" .
        "Try asking about:\n" .
        "• Your SportSync role and available pages\n" .
        "• Login or account approval\n" .
        "• Teams and players\n" .
        "• Matches and fixtures\n" .
        "• Sports statistics\n\n" .
        "This assistant uses prepared answers and does not access live SportSync records.";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    sendJsonResponse(405, [
        'message' => 'Only POST requests are allowed.',
    ]);
}

$user = currentUser();

if (!$user || empty($user['id'])) {
    sendJsonResponse(401, [
        'message' => 'Your session has expired. Please log in again.',
    ]);
}

$sessionToken = $_SESSION['ai_assistant_csrf'] ?? '';
$requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !is_string($requestToken) ||
    !hash_equals($sessionToken, $requestToken)
) {
    sendJsonResponse(403, [
        'message' => 'Security check failed. Refresh the page and try again.',
    ]);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput ?: '', true);

if (!is_array($data)) {
    sendJsonResponse(400, [
        'message' => 'Invalid request data.',
    ]);
}

$message = trim((string) ($data['message'] ?? ''));

if ($message === '') {
    sendJsonResponse(422, [
        'message' => 'Please enter a question.',
    ]);
}

if (strlen($message) > 4000) {
    sendJsonResponse(422, [
        'message' => 'Your question is too long. Please shorten it.',
    ]);
}

$role = (string) ($user['role'] ?? 'PLAYER');
$reply = getAssistantReply($message, $role);

sendJsonResponse(200, [
    'reply' => $reply,
]);