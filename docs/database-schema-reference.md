# Final database schema reference

The import-ready definition, including every column type, foreign key, unique
key, check constraint, index, and ON DELETE / ON UPDATE action, is in
database/schema.sql. This reference explains the design in plain language.

| Table | Primary key | Important columns | Why it exists |
|---|---|---|---|
| roles | role_id TINYINT | role_name VARCHAR(50) UNIQUE | Keeps the four authorised application roles in one controlled list. |
| users | user_id INT | role_id, email VARCHAR(150) UNIQUE, password_hash VARCHAR(255), account_status ENUM | Stores login identities, approvals, and contact information. |
| player_profiles | player_id INT | user_id UNIQUE, student_id UNIQUE, department, academic_year | Stores student/player-only information without creating a separate players table. |
| coach_profiles | coach_id INT | user_id UNIQUE, employee_id UNIQUE, specialization | Stores coach-only information. |
| sports | sport_id INT | sport_name UNIQUE, default_max_team_players SMALLINT | Stores the four Version 1 sports and any future sport without schema changes. |
| player_sports | player_sport_id INT | player_id, sport_id, participation_status | Links a player to many sports. The player/sport unique key prevents a duplicate enrolment. |
| teams | team_id INT | sport_id, coach_id, team_name, roster_limit | Stores one sport-specific team. A NULL roster_limit uses the sport default. |
| team_players | team_player_id INT | player_id, team_id, joined_at, left_at, membership_status | Preserves active and historical team membership. |
| venues | venue_id INT | venue_name UNIQUE, location, capacity, availability_status | Stores college locations where matches/events happen. |
| venue_sports | venue_id + sport_id | venue_id, sport_id | Allows one venue to support multiple sports. |
| events | event_id INT | sport_id, venue_id, event_start/end, deadline, max_participants | Stores individual-player sports events. |
| event_registrations | event_registration_id INT | event_id, player_id, registration_status | Stores one player's registration for one event. |
| tournaments | tournament_id INT | sport_id, point values DECIMAL, dates, status | Stores one league tournament and its configurable rules. |
| tournament_tiebreakers | tournament_tiebreaker_id INT | tournament_id, priority_order, rule_code | Stores the tie-break order as data rather than hard-coded PHP. |
| tournament_teams | tournament_team_id INT | tournament_id, team_id, participation_status | Records a team's league entry. |
| matches | match_id INT | tournament_id, team_a_id, team_b_id, venue_id, scheduled times | Stores scheduled league matches. |
| match_results | match_id INT | team_a_score, team_b_score, winner_team_id | Stores one final result for each completed match. |
| tournament_standings | tournament_standing_id INT | tournament_id, team_id, wins/draws/losses, points, standing_rank | Stores recalculated league standings for fast display. |
| player_match_participation | match_participation_id INT | match_id, player_id, team_id, participation_status | Records actual player appearances; this makes match-count statistics genuine. |
| player_match_statistics | player_match_statistic_id INT | match_participation_id, metric_code, metric_value DECIMAL | Stores extensible sport-specific data such as GOALS, RUNS, WICKETS, or POINTS. |
| attendance_records | attendance_id INT | team_id, player_id, attendance_date, attendance_status | Stores simple team-training attendance. |
| notifications | notification_id INT | recipient_user_id, type, title, is_read | Stores useful internal messages for one user. |
| audit_logs | audit_log_id BIGINT | actor_user_id, action_type, entity_type, entity_id | Keeps evidence of important changes, especially corrected results. |

## Important database constraints

| Rule | How it is enforced |
|---|---|
| One user email and one student ID | Unique keys on users.email and player_profiles.student_id |
| One player profile or coach profile per user | Unique keys on the profile user_id columns |
| A player joins a sport once | Unique key on player_sports(player_id, sport_id) |
| No duplicate team entry in a tournament | Unique key on tournament_teams(tournament_id, team_id) |
| No duplicate event registration | Unique key on event_registrations(event_id, player_id) |
| One result per match | match_results.match_id is both primary key and foreign key |
| Team A differs from Team B | matches check constraint plus PHP validation |
| Valid match/event/tournament dates | Check constraints plus PHP validation |
| Both teams are tournament participants | Composite foreign keys from matches to tournament_teams |
| History is not silently lost | Foreign keys mostly use ON DELETE RESTRICT |

## Important indexes

| Index | Purpose |
|---|---|
| users(role_id, account_status) | Role dashboards and pending-account review |
| player_profiles(department, academic_year) | Player filtering |
| player_sports(sport_id, participation_status) | Sport participant lookups |
| team_players(player_id/team_id, membership_status) | Team assignment and conflict checks |
| events(sport_id, event_status, event_start) | Event filtering |
| matches(venue_id/team_id, scheduled_start, scheduled_end) | Venue and team overlap queries |
| tournament_standings(tournament_id, points/rank) | Fast league table display |
| notifications(recipient_user_id, is_read, created_at) | Dashboard notification list |
| audit_logs(entity_type, entity_id, created_at) | Result-correction history |

## ER diagram

~~~text
roles 1 ───< users 1 ─── 0..1 player_profiles
                 └───── 0..1 coach_profiles

player_profiles >───< player_sports >───< sports
player_profiles >───< team_players >───< teams >───1 sports
coach_profiles 1 ───< teams

venues >───< venue_sports >───< sports
sports 1 ───< events >───1 venues
events 1 ───< event_registrations >───1 player_profiles

sports 1 ───< tournaments >───0..1 venues
tournaments 1 ───< tournament_tiebreakers
tournaments >───< tournament_teams >───1 teams
tournaments 1 ───< matches >───1 venues
matches 1 ─── 0..1 match_results
tournaments >───< tournament_standings >───1 teams

matches 1 ───< player_match_participation >───1 player_profiles
player_match_participation 1 ───< player_match_statistics
teams 1 ───< attendance_records >───1 player_profiles
users 1 ───< notifications
users 0..1 ───< audit_logs
~~~
