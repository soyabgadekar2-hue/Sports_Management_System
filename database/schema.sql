-- College Sports Management System, Version 1
-- Target: MySQL 8.0+ or a current XAMPP MariaDB release with InnoDB support.
-- Import this file before database/seed.sql.

CREATE DATABASE IF NOT EXISTS college_sports_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE college_sports_management;

CREATE TABLE roles (
    role_id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_role_name (role_name)
) ENGINE=InnoDB;

CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id TINYINT UNSIGNED NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NULL,
    password_hash VARCHAR(255) NOT NULL,
    account_status ENUM('PENDING', 'APPROVED', 'REJECTED', 'SUSPENDED')
        NOT NULL DEFAULT 'PENDING',
    approved_by_user_id INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role_id, account_status),
    KEY idx_users_approver (approved_by_user_id),
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles (role_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_users_approved_by
        FOREIGN KEY (approved_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE player_profiles (
    player_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    student_id VARCHAR(40) NOT NULL,
    department VARCHAR(100) NOT NULL,
    course VARCHAR(100) NULL,
    academic_year VARCHAR(30) NOT NULL,
    semester TINYINT UNSIGNED NULL,
    gender ENUM('MALE', 'FEMALE', 'OTHER', 'PREFER_NOT_TO_SAY') NULL,
    date_of_birth DATE NULL,
    player_status ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_player_profiles_user (user_id),
    UNIQUE KEY uq_player_profiles_student_id (student_id),
    KEY idx_player_profiles_department_year (department, academic_year),
    CONSTRAINT fk_player_profiles_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE coach_profiles (
    coach_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    employee_id VARCHAR(40) NOT NULL,
    designation VARCHAR(100) NULL,
    specialization VARCHAR(150) NULL,
    coach_status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_coach_profiles_user (user_id),
    UNIQUE KEY uq_coach_profiles_employee_id (employee_id),
    CONSTRAINT fk_coach_profiles_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE sports (
    sport_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_name VARCHAR(80) NOT NULL,
    category VARCHAR(60) NULL,
    description TEXT NULL,
    default_max_team_players SMALLINT UNSIGNED NOT NULL,
    rules_information TEXT NULL,
    sport_status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sports_sport_name (sport_name),
    CONSTRAINT chk_sports_default_roster_limit
        CHECK (default_max_team_players > 0)
) ENGINE=InnoDB;

CREATE TABLE player_sports (
    player_sport_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    sport_id INT UNSIGNED NOT NULL,
    participation_status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_player_sports_player_sport (player_id, sport_id),
    KEY idx_player_sports_sport_status (sport_id, participation_status),
    CONSTRAINT fk_player_sports_player
        FOREIGN KEY (player_id) REFERENCES player_profiles (player_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_player_sports_sport
        FOREIGN KEY (sport_id) REFERENCES sports (sport_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE teams (
    team_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_id INT UNSIGNED NOT NULL,
    coach_id INT UNSIGNED NULL,
    team_name VARCHAR(120) NOT NULL,
    team_category VARCHAR(50) NOT NULL DEFAULT 'OPEN',
    roster_limit SMALLINT UNSIGNED NULL,
    team_status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_teams_sport_name_category (sport_id, team_name, team_category),
    KEY idx_teams_coach_status (coach_id, team_status),
    CONSTRAINT chk_teams_roster_limit
        CHECK (roster_limit IS NULL OR roster_limit > 0),
    CONSTRAINT fk_teams_sport
        FOREIGN KEY (sport_id) REFERENCES sports (sport_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_teams_coach
        FOREIGN KEY (coach_id) REFERENCES coach_profiles (coach_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE team_players (
    team_player_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    left_at DATETIME NULL,
    membership_status ENUM('ACTIVE', 'INACTIVE', 'REMOVED') NOT NULL DEFAULT 'ACTIVE',
    assigned_by_user_id INT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_team_players_membership_history (team_id, player_id, joined_at),
    KEY idx_team_players_player_status (player_id, membership_status),
    KEY idx_team_players_team_status (team_id, membership_status),
    CONSTRAINT chk_team_players_dates
        CHECK (left_at IS NULL OR left_at >= joined_at),
    CONSTRAINT fk_team_players_player
        FOREIGN KEY (player_id) REFERENCES player_profiles (player_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_team_players_team
        FOREIGN KEY (team_id) REFERENCES teams (team_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_team_players_assigned_by
        FOREIGN KEY (assigned_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE venues (
    venue_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venue_name VARCHAR(120) NOT NULL,
    location VARCHAR(255) NOT NULL,
    capacity INT UNSIGNED NULL,
    availability_status ENUM('AVAILABLE', 'UNAVAILABLE', 'MAINTENANCE')
        NOT NULL DEFAULT 'AVAILABLE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_venues_name (venue_name),
    KEY idx_venues_status (availability_status)
) ENGINE=InnoDB;

CREATE TABLE venue_sports (
    venue_id INT UNSIGNED NOT NULL,
    sport_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (venue_id, sport_id),
    KEY idx_venue_sports_sport (sport_id),
    CONSTRAINT fk_venue_sports_venue
        FOREIGN KEY (venue_id) REFERENCES venues (venue_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_venue_sports_sport
        FOREIGN KEY (sport_id) REFERENCES sports (sport_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE events (
    event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_id INT UNSIGNED NOT NULL,
    venue_id INT UNSIGNED NOT NULL,
    event_title VARCHAR(150) NOT NULL,
    event_description TEXT NULL,
    event_start DATETIME NOT NULL,
    event_end DATETIME NOT NULL,
    registration_deadline DATETIME NOT NULL,
    max_participants SMALLINT UNSIGNED NOT NULL,
    event_status ENUM('DRAFT', 'OPEN', 'CLOSED', 'CANCELLED', 'COMPLETED')
        NOT NULL DEFAULT 'DRAFT',
    created_by_user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_events_sport_status_start (sport_id, event_status, event_start),
    KEY idx_events_venue_time (venue_id, event_start, event_end),
    CONSTRAINT chk_events_dates
        CHECK (event_end > event_start AND registration_deadline <= event_start),
    CONSTRAINT chk_events_max_participants
        CHECK (max_participants > 0),
    CONSTRAINT fk_events_sport
        FOREIGN KEY (sport_id) REFERENCES sports (sport_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_events_venue
        FOREIGN KEY (venue_id) REFERENCES venues (venue_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_events_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE event_registrations (
    event_registration_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    registration_status ENUM('PENDING', 'APPROVED', 'WAITLISTED', 'CANCELLED')
        NOT NULL DEFAULT 'PENDING',
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by_user_id INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    remarks VARCHAR(500) NULL,
    UNIQUE KEY uq_event_registrations_event_player (event_id, player_id),
    KEY idx_event_registrations_player_status (player_id, registration_status),
    KEY idx_event_registrations_event_status (event_id, registration_status),
    CONSTRAINT fk_event_registrations_event
        FOREIGN KEY (event_id) REFERENCES events (event_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_event_registrations_player
        FOREIGN KEY (player_id) REFERENCES player_profiles (player_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_event_registrations_reviewer
        FOREIGN KEY (reviewed_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tournaments (
    tournament_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_id INT UNSIGNED NOT NULL,
    venue_id INT UNSIGNED NULL,
    tournament_name VARCHAR(150) NOT NULL,
    tournament_description TEXT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    tournament_format ENUM('LEAGUE') NOT NULL DEFAULT 'LEAGUE',
    points_win DECIMAL(5,2) NOT NULL DEFAULT 3.00,
    points_draw DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    points_loss DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    rules_information TEXT NULL,
    tournament_status ENUM('DRAFT', 'REGISTRATION_OPEN', 'ACTIVE', 'COMPLETED', 'CANCELLED')
        NOT NULL DEFAULT 'DRAFT',
    created_by_user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tournaments_sport_name_dates (sport_id, tournament_name, start_date),
    KEY idx_tournaments_status_dates (tournament_status, start_date, end_date),
    CONSTRAINT chk_tournaments_dates
        CHECK (end_date >= start_date),
    CONSTRAINT chk_tournaments_points
        CHECK (points_win >= 0 AND points_draw >= 0 AND points_loss >= 0),
    CONSTRAINT fk_tournaments_sport
        FOREIGN KEY (sport_id) REFERENCES sports (sport_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tournaments_venue
        FOREIGN KEY (venue_id) REFERENCES venues (venue_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_tournaments_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE tournament_tiebreakers (
    tournament_tiebreaker_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    priority_order TINYINT UNSIGNED NOT NULL,
    rule_code ENUM('POINTS', 'SCORE_DIFFERENCE', 'SCORE_FOR', 'HEAD_TO_HEAD')
        NOT NULL,
    UNIQUE KEY uq_tournament_tiebreakers_priority (tournament_id, priority_order),
    UNIQUE KEY uq_tournament_tiebreakers_rule (tournament_id, rule_code),
    CONSTRAINT chk_tournament_tiebreakers_priority
        CHECK (priority_order > 0),
    CONSTRAINT fk_tournament_tiebreakers_tournament
        FOREIGN KEY (tournament_id) REFERENCES tournaments (tournament_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE tournament_teams (
    tournament_team_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    participation_status ENUM('ACTIVE', 'WITHDRAWN', 'DISQUALIFIED')
        NOT NULL DEFAULT 'ACTIVE',
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registered_by_user_id INT UNSIGNED NULL,
    remarks VARCHAR(500) NULL,
    UNIQUE KEY uq_tournament_teams_tournament_team (tournament_id, team_id),
    KEY idx_tournament_teams_team_status (team_id, participation_status),
    CONSTRAINT fk_tournament_teams_tournament
        FOREIGN KEY (tournament_id) REFERENCES tournaments (tournament_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tournament_teams_team
        FOREIGN KEY (team_id) REFERENCES teams (team_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_tournament_teams_registered_by
        FOREIGN KEY (registered_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE matches (
    match_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    team_a_id INT UNSIGNED NOT NULL,
    team_b_id INT UNSIGNED NOT NULL,
    venue_id INT UNSIGNED NOT NULL,
    match_number SMALLINT UNSIGNED NOT NULL,
    scheduled_start DATETIME NOT NULL,
    scheduled_end DATETIME NOT NULL,
    match_status ENUM('SCHEDULED', 'COMPLETED', 'POSTPONED', 'CANCELLED')
        NOT NULL DEFAULT 'SCHEDULED',
    notes VARCHAR(1000) NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_matches_tournament_number (tournament_id, match_number),
    KEY idx_matches_venue_time (venue_id, scheduled_start, scheduled_end),
    KEY idx_matches_team_a_time (team_a_id, scheduled_start, scheduled_end),
    KEY idx_matches_team_b_time (team_b_id, scheduled_start, scheduled_end),
    KEY idx_matches_tournament_team_a (tournament_id, team_a_id),
    KEY idx_matches_tournament_team_b (tournament_id, team_b_id),
    KEY idx_matches_status_time (match_status, scheduled_start),
    CONSTRAINT chk_matches_different_teams
        CHECK (team_a_id <> team_b_id),
    CONSTRAINT chk_matches_schedule
        CHECK (scheduled_end > scheduled_start),
    CONSTRAINT fk_matches_tournament
        FOREIGN KEY (tournament_id) REFERENCES tournaments (tournament_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_matches_team_a_in_tournament
        FOREIGN KEY (tournament_id, team_a_id)
        REFERENCES tournament_teams (tournament_id, team_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_matches_team_b_in_tournament
        FOREIGN KEY (tournament_id, team_b_id)
        REFERENCES tournament_teams (tournament_id, team_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_matches_venue
        FOREIGN KEY (venue_id) REFERENCES venues (venue_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_matches_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE match_results (
    match_id INT UNSIGNED PRIMARY KEY,
    team_a_score DECIMAL(10,2) NOT NULL,
    team_b_score DECIMAL(10,2) NOT NULL,
    winner_team_id INT UNSIGNED NULL,
    result_notes VARCHAR(1000) NULL,
    entered_by_user_id INT UNSIGNED NOT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_match_results_scores
        CHECK (team_a_score >= 0 AND team_b_score >= 0),
    CONSTRAINT fk_match_results_match
        FOREIGN KEY (match_id) REFERENCES matches (match_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_match_results_winner
        FOREIGN KEY (winner_team_id) REFERENCES teams (team_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_match_results_entered_by
        FOREIGN KEY (entered_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_match_results_updated_by
        FOREIGN KEY (updated_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tournament_standings (
    tournament_standing_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    matches_played SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    wins SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    draws SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    losses SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    score_for DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    score_against DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    score_difference DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    points DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    standing_rank SMALLINT UNSIGNED NULL,
    last_calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tournament_standings_tournament_team (tournament_id, team_id),
    KEY idx_tournament_standings_rank (tournament_id, standing_rank),
    KEY idx_tournament_standings_points (tournament_id, points),
    CONSTRAINT fk_tournament_standings_tournament_team
        FOREIGN KEY (tournament_id, team_id)
        REFERENCES tournament_teams (tournament_id, team_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE player_match_participation (
    match_participation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    participation_status ENUM('PLAYED', 'SUBSTITUTE', 'DID_NOT_PLAY')
        NOT NULL DEFAULT 'PLAYED',
    recorded_by_user_id INT UNSIGNED NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_player_match_participation (match_id, player_id),
    KEY idx_player_match_participation_player (player_id, participation_status),
    CONSTRAINT fk_player_match_participation_match
        FOREIGN KEY (match_id) REFERENCES matches (match_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_player_match_participation_player
        FOREIGN KEY (player_id) REFERENCES player_profiles (player_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_player_match_participation_team
        FOREIGN KEY (team_id) REFERENCES teams (team_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_player_match_participation_recorded_by
        FOREIGN KEY (recorded_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE player_match_statistics (
    player_match_statistic_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_participation_id INT UNSIGNED NOT NULL,
    metric_code VARCHAR(50) NOT NULL,
    metric_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_player_match_statistics_metric
        (match_participation_id, metric_code),
    CONSTRAINT fk_player_match_statistics_participation
        FOREIGN KEY (match_participation_id)
        REFERENCES player_match_participation (match_participation_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE attendance_records (
    attendance_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    attendance_status ENUM('PRESENT', 'ABSENT', 'LATE') NOT NULL,
    recorded_by_user_id INT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_records_team_player_date
        (team_id, player_id, attendance_date),
    KEY idx_attendance_records_player_date (player_id, attendance_date),
    CONSTRAINT fk_attendance_records_team
        FOREIGN KEY (team_id) REFERENCES teams (team_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_records_player
        FOREIGN KEY (player_id) REFERENCES player_profiles (player_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_records_recorded_by
        FOREIGN KEY (recorded_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
    notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id INT UNSIGNED NOT NULL,
    created_by_user_id INT UNSIGNED NULL,
    notification_type VARCHAR(50) NOT NULL,
    notification_title VARCHAR(150) NOT NULL,
    notification_message TEXT NOT NULL,
    action_url VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_recipient_read_created
        (recipient_user_id, is_read, created_at),
    CONSTRAINT fk_notifications_recipient
        FOREIGN KEY (recipient_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_notifications_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    audit_log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NULL,
    action_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id INT UNSIGNED NULL,
    action_summary VARCHAR(500) NOT NULL,
    previous_data LONGTEXT NULL,
    new_data LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_logs_entity (entity_type, entity_id, created_at),
    KEY idx_audit_logs_actor_created (actor_user_id, created_at),
    CONSTRAINT fk_audit_logs_actor
        FOREIGN KEY (actor_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;
