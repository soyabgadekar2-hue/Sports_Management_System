-- Read-only checks for Phase 1.
-- Import schema.sql first, and seed.sql if you want the sample-data checks.

USE college_sports_management;

SHOW TABLES;

SELECT
    (SELECT COUNT(*) FROM roles) AS roles_count,
    (SELECT COUNT(*) FROM users) AS users_count,
    (SELECT COUNT(*) FROM sports) AS sports_count,
    (SELECT COUNT(*) FROM teams) AS teams_count,
    (SELECT COUNT(*) FROM tournaments) AS tournaments_count,
    (SELECT COUNT(*) FROM matches) AS matches_count;

SELECT role_name
FROM roles
ORDER BY role_id;

SELECT sport_name, default_max_team_players, sport_status
FROM sports
ORDER BY sport_name;

SELECT
    tr.tournament_name,
    s.sport_name,
    tr.points_win,
    tr.points_draw,
    tr.points_loss,
    GROUP_CONCAT(tt.rule_code ORDER BY tt.priority_order SEPARATOR ' > ') AS tie_break_order
FROM tournaments AS tr
JOIN sports AS s ON s.sport_id = tr.sport_id
LEFT JOIN tournament_tiebreakers AS tt ON tt.tournament_id = tr.tournament_id
GROUP BY
    tr.tournament_id, tr.tournament_name, s.sport_name,
    tr.points_win, tr.points_draw, tr.points_loss;

SELECT
    u.full_name,
    pp.student_id,
    GROUP_CONCAT(s.sport_name ORDER BY s.sport_name SEPARATOR ', ') AS enrolled_sports
FROM player_profiles AS pp
JOIN users AS u ON u.user_id = pp.user_id
LEFT JOIN player_sports AS ps
    ON ps.player_id = pp.player_id AND ps.participation_status = 'ACTIVE'
LEFT JOIN sports AS s ON s.sport_id = ps.sport_id
GROUP BY u.user_id, u.full_name, pp.student_id;
