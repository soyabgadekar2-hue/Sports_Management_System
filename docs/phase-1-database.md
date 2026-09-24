# Phase 1: Database plan

## Import order

1. Import database/schema.sql.
2. Import database/seed.sql only when local sample data is wanted.
3. Run database/verify.sql to view read-only confirmation queries.

The database name is college_sports_management.

The plain-language table reference and text ER diagram are in
docs/database-schema-reference.md.

## Planned project folders

~~~text
Sports_Management_System/
├── config/                 Server-only configuration
├── database/
│   ├── schema.sql          Final database structure
│   └── seed.sql            Local sample data
├── docs/
│   └── phase-1-database.md
├── includes/               Shared PHP security and layout files
├── api/                    AJAX PHP endpoints
├── public/
│   ├── css/
│   ├── js/
│   ├── images/
│   └── uploads/            Reserved; profile uploads are not Version 1
├── admin/
├── coordinator/
├── coach/
├── player/
├── reports/
├── ai/                     Added after the core system is stable
├── index.php
├── login.php
├── logout.php
└── README.md
~~~

Only database and documentation files are created in Phase 1. The remaining
folders are the approved structure for later phases.

## Relationship rules

- A user has one role. A player or coach is a user with the matching profile.
- player_profiles, not a separate players table, is the player entity.
- player_sports is the many-to-many link that allows one player to join many sports.
- teams belongs to one sport. team_players keeps membership history instead of
  deleting old memberships.
- PHP will allow only one ACTIVE team_players row for a player in a given sport.
  This cross-table rule cannot be expressed as one portable MySQL unique key.
- tournament_teams links a team to one tournament. Its unique key prevents a
  duplicate tournament entry.
- The composite match foreign keys ensure both match teams are already enrolled
  in that match's tournament.
- tournament_standings stores calculated league values. It is recalculated after
  a result is added or corrected.
- player_match_participation records a genuine match appearance. The child
  player_match_statistics table stores sport-specific numeric metrics.

## Delete policy

Sports records are historical data. Most relationships use ON DELETE RESTRICT,
so a referenced player, team, sport, venue, tournament, or match cannot be
silently removed. The application will deactivate/cancel records instead.

Two deliberate exceptions exist:

- If a coach account is removed, a team's coach_id becomes NULL.
- If the user who created a notification, approval, attendance record, or audit
  event is removed, the historical record remains and its creator reference
  becomes NULL.

## Database-level and PHP-level validation

The schema enforces foreign keys, uniqueness, positive numeric values, valid
date order, and different teams in a match.

PHP must additionally validate:

- the signed-in user's role;
- that pending users cannot log in;
- a player is actively enrolled in the team's sport;
- a player has no other active team in that sport;
- team roster capacity;
- registration deadlines, event capacity, and player eligibility;
- team/venue time overlap before scheduling a match;
- match winner is Team A or Team B;
- a participating player belongs to the match team;
- a result correction recalculates standings/statistics and creates an audit log.

These checks belong in PHP because they compare several tables and must run in a
transaction when data changes.

## Statistics design

There is intentionally no separate all-time team_statistics table. That would
duplicate result data and can become inaccurate.

- tournament_standings is the stored team statistic for a particular league.
- All-time team reports are calculated from completed matches and match_results.
- Player match counts come from player_match_participation.
- Sport-specific performance, such as goals, runs, wickets, or points, comes
  from player_match_statistics.

## Local sample accounts

After importing the optional seed file, all sample accounts use the password
password. These are local development accounts only and must be changed or
removed before a real demonstration.

| Role | Email |
|---|---|
| Admin | admin@college.test |
| Sports Coordinator | coordinator@college.test |
| Coach | coach@college.test |
| Player | aarav@college.test |
| Pending Player | pending@college.test |

## XAMPP and phpMyAdmin setup

1. Install XAMPP and open the XAMPP Control Panel.
2. Start Apache and MySQL. Apache is not needed for this database import, but
   it will be needed when PHP pages are built.
3. Open http://localhost/phpmyadmin in a browser.
4. Select the Import tab.
5. Choose database/schema.sql and click Import. The script creates
   college_sports_management automatically.
6. Select college_sports_management in the left sidebar.
7. Import database/seed.sql only if local sample accounts are required.
8. Import database/verify.sql, or open its contents in phpMyAdmin's SQL tab,
   to run the confirmation queries.

Expected seed checks:

- 4 roles exist.
- 6 users exist, including one Pending player.
- 4 sports exist.
- The Football tournament shows 3/1/0 points and the approved four-rule
  tie-break order.
- Aarav Sharma is enrolled in Football and Basketball.

If an import fails, copy the exact phpMyAdmin error message before changing the
schema. Do not import seed.sql again without recreating the database, because
its unique keys correctly reject duplicate sample rows.
