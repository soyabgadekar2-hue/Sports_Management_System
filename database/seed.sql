-- Sample data for local development only.
-- Import database/schema.sql first.
-- Every seeded login below uses the password: password
-- Change or remove these accounts before a real demonstration.

USE college_sports_management;

INSERT INTO roles (role_id, role_name, description) VALUES
    (1, 'ADMIN', 'Full system administration'),
    (2, 'SPORTS_COORDINATOR', 'Sports operations and tournament management'),
    (3, 'COACH', 'Assigned team management and attendance'),
    (4, 'PLAYER', 'Student player access');

INSERT INTO users (
    user_id, role_id, full_name, email, phone, password_hash,
    account_status, approved_at
) VALUES
    (1, 1, 'System Administrator', 'admin@college.test', '9000000001',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.2z0g6oGHDV9sXx4NQVC',
     'APPROVED', NOW()),
    (2, 2, 'Sports Coordinator', 'coordinator@college.test', '9000000002',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.2z0g6oGHDV9sXx4NQVC',
     'APPROVED', NOW()),
    (3, 3, 'Football Coach', 'coach@college.test', '9000000003',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.2z0g6oGHDV9sXx4NQVC',
     'APPROVED', NOW()),
    (4, 4, 'Aarav Sharma', 'aarav@college.test', '9000000004',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.2z0g6oGHDV9sXx4NQVC',
     'APPROVED', NOW()),
    (5, 4, 'Kabir Khan', 'kabir@college.test', '9000000005',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.2z0g6oGHDV9sXx4NQVC',
     'APPROVED', NOW()),
    (6, 4, 'Pending Student', 'pending@college.test', '9000000006',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC.2z0g6oGHDV9sXx4NQVC',
     'PENDING', NULL);

UPDATE users
SET approved_by_user_id = 1
WHERE user_id IN (2, 3, 4, 5);

INSERT INTO coach_profiles (
    user_id, employee_id, designation, specialization, coach_status
) VALUES
    (3, 'EMP-SPORT-001', 'Football Coach', 'Football', 'ACTIVE');

INSERT INTO player_profiles (
    user_id, student_id, department, course, academic_year, semester,
    gender, date_of_birth, player_status
) VALUES
    (4, 'STU-2026-001', 'Computer Applications', 'BCA', 'Second Year', 3,
     'MALE', '2006-04-15', 'ACTIVE'),
    (5, 'STU-2026-002', 'Commerce', 'BCom', 'Second Year', 3,
     'MALE', '2006-08-21', 'ACTIVE'),
    (6, 'STU-2026-003', 'Computer Applications', 'BCA', 'First Year', 1,
     'PREFER_NOT_TO_SAY', NULL, 'ACTIVE');

INSERT INTO sports (
    sport_name, category, description, default_max_team_players,
    rules_information, sport_status
) VALUES
    ('Football', 'Outdoor', 'College football competition', 18,
     'Match duration and roster rules are configured by the coordinator.', 'ACTIVE'),
    ('Cricket', 'Outdoor', 'College cricket competition', 15,
     'Overs and match rules are configured by the coordinator.', 'ACTIVE'),
    ('Basketball', 'Indoor', 'College basketball competition', 12,
     'Game rules are configured by the coordinator.', 'ACTIVE'),
    ('Volleyball', 'Indoor', 'College volleyball competition', 12,
     'Set rules are configured by the coordinator.', 'ACTIVE');

INSERT INTO player_sports (player_id, sport_id, participation_status)
SELECT pp.player_id, s.sport_id, 'ACTIVE'
FROM player_profiles AS pp
JOIN users AS u ON u.user_id = pp.user_id
JOIN sports AS s ON s.sport_name = 'Football'
WHERE u.email IN ('aarav@college.test', 'kabir@college.test');

INSERT INTO player_sports (player_id, sport_id, participation_status)
SELECT pp.player_id, s.sport_id, 'ACTIVE'
FROM player_profiles AS pp
JOIN users AS u ON u.user_id = pp.user_id
JOIN sports AS s ON s.sport_name = 'Basketball'
WHERE u.email = 'aarav@college.test';

INSERT INTO venues (venue_name, location, capacity, availability_status) VALUES
    ('Main Football Ground', 'North Campus', 800, 'AVAILABLE'),
    ('Indoor Sports Hall', 'Student Activity Centre', 300, 'AVAILABLE');

INSERT INTO venue_sports (venue_id, sport_id)
SELECT v.venue_id, s.sport_id
FROM venues AS v
JOIN sports AS s ON s.sport_name = 'Football'
WHERE v.venue_name = 'Main Football Ground';

INSERT INTO venue_sports (venue_id, sport_id)
SELECT v.venue_id, s.sport_id
FROM venues AS v
JOIN sports AS s ON s.sport_name IN ('Basketball', 'Volleyball')
WHERE v.venue_name = 'Indoor Sports Hall';

INSERT INTO teams (
    sport_id, coach_id, team_name, team_category, roster_limit, team_status
)
SELECT s.sport_id, cp.coach_id, 'College Football Blue', 'OPEN', 18, 'ACTIVE'
FROM sports AS s
JOIN coach_profiles AS cp ON cp.employee_id = 'EMP-SPORT-001'
WHERE s.sport_name = 'Football';

INSERT INTO teams (
    sport_id, coach_id, team_name, team_category, roster_limit, team_status
)
SELECT s.sport_id, cp.coach_id, 'College Football Gold', 'OPEN', 18, 'ACTIVE'
FROM sports AS s
JOIN coach_profiles AS cp ON cp.employee_id = 'EMP-SPORT-001'
WHERE s.sport_name = 'Football';

INSERT INTO team_players (
    player_id, team_id, membership_status, assigned_by_user_id, notes
)
SELECT pp.player_id, t.team_id, 'ACTIVE', 2, 'Sample active membership'
FROM player_profiles AS pp
JOIN users AS u ON u.user_id = pp.user_id
JOIN teams AS t ON t.team_name = 'College Football Blue'
WHERE u.email = 'aarav@college.test';

INSERT INTO team_players (
    player_id, team_id, membership_status, assigned_by_user_id, notes
)
SELECT pp.player_id, t.team_id, 'ACTIVE', 2, 'Sample active membership'
FROM player_profiles AS pp
JOIN users AS u ON u.user_id = pp.user_id
JOIN teams AS t ON t.team_name = 'College Football Gold'
WHERE u.email = 'kabir@college.test';

INSERT INTO events (
    sport_id, venue_id, event_title, event_description,
    event_start, event_end, registration_deadline, max_participants,
    event_status, created_by_user_id
)
SELECT
    s.sport_id, v.venue_id, 'Football Skills Assessment',
    'Individual registration assessment for football players.',
    '2027-02-10 09:00:00', '2027-02-10 12:00:00',
    '2027-02-08 17:00:00', 40, 'OPEN', 2
FROM sports AS s
JOIN venues AS v ON v.venue_name = 'Main Football Ground'
WHERE s.sport_name = 'Football';

INSERT INTO event_registrations (
    event_id, player_id, registration_status, reviewed_by_user_id, reviewed_at
)
SELECT e.event_id, pp.player_id, 'APPROVED', 2, NOW()
FROM events AS e
CROSS JOIN player_profiles AS pp
JOIN users AS u ON u.user_id = pp.user_id
WHERE e.event_title = 'Football Skills Assessment'
  AND u.email = 'aarav@college.test';

INSERT INTO tournaments (
    sport_id, venue_id, tournament_name, tournament_description,
    start_date, end_date, tournament_format,
    points_win, points_draw, points_loss,
    rules_information, tournament_status, created_by_user_id
)
SELECT
    s.sport_id, v.venue_id, 'Inter-Department Football League 2027',
    'Sample league tournament with configurable points.',
    '2027-03-01', '2027-03-20', 'LEAGUE',
    3.00, 1.00, 0.00,
    'Points, score difference, score for, then head-to-head decide ranking.',
    'REGISTRATION_OPEN', 2
FROM sports AS s
JOIN venues AS v ON v.venue_name = 'Main Football Ground'
WHERE s.sport_name = 'Football';

INSERT INTO tournament_tiebreakers (tournament_id, priority_order, rule_code)
SELECT tournament_id, 1, 'POINTS'
FROM tournaments
WHERE tournament_name = 'Inter-Department Football League 2027';

INSERT INTO tournament_tiebreakers (tournament_id, priority_order, rule_code)
SELECT tournament_id, 2, 'SCORE_DIFFERENCE'
FROM tournaments
WHERE tournament_name = 'Inter-Department Football League 2027';

INSERT INTO tournament_tiebreakers (tournament_id, priority_order, rule_code)
SELECT tournament_id, 3, 'SCORE_FOR'
FROM tournaments
WHERE tournament_name = 'Inter-Department Football League 2027';

INSERT INTO tournament_tiebreakers (tournament_id, priority_order, rule_code)
SELECT tournament_id, 4, 'HEAD_TO_HEAD'
FROM tournaments
WHERE tournament_name = 'Inter-Department Football League 2027';

INSERT INTO tournament_teams (
    tournament_id, team_id, participation_status, registered_by_user_id
)
SELECT tr.tournament_id, tm.team_id, 'ACTIVE', 2
FROM tournaments AS tr
JOIN teams AS tm ON tm.team_name IN ('College Football Blue', 'College Football Gold')
WHERE tr.tournament_name = 'Inter-Department Football League 2027';

INSERT INTO tournament_standings (tournament_id, team_id, standing_rank)
SELECT tr.tournament_id, tm.team_id, NULL
FROM tournaments AS tr
JOIN teams AS tm ON tm.team_name IN ('College Football Blue', 'College Football Gold')
WHERE tr.tournament_name = 'Inter-Department Football League 2027';

INSERT INTO notifications (
    recipient_user_id, created_by_user_id, notification_type,
    notification_title, notification_message, action_url
) VALUES
    (4, 2, 'EVENT_REGISTRATION',
     'Event registration approved',
     'Your registration for the Football Skills Assessment was approved.',
     '/player/events.php'),
    (5, 2, 'TOURNAMENT',
     'Football league registration',
     'Your team has been added to the Inter-Department Football League 2027.',
     '/player/tournaments.php');

INSERT INTO audit_logs (
    actor_user_id, action_type, entity_type, entity_id, action_summary
) VALUES
    (1, 'SEED_DATA_CREATED', 'SYSTEM', NULL,
     'Sample development data created for the College Sports Management System.');
