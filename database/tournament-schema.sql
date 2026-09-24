USE college_sports_management;

-- ============================================================
-- 1. TOURNAMENTS
-- ============================================================

CREATE TABLE tournaments (
    tournament_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    sport_id INT UNSIGNED NOT NULL,

    tournament_name VARCHAR(150) NOT NULL,

    description TEXT NULL,

    tournament_format ENUM(
        'LEAGUE',
        'KNOCKOUT',
        'LEAGUE_AND_KNOCKOUT'
    ) NOT NULL DEFAULT 'LEAGUE',

    start_date DATE NOT NULL,

    end_date DATE NULL,

    venue VARCHAR(150) NULL,

    points_for_win SMALLINT UNSIGNED NOT NULL DEFAULT 3,

    points_for_draw SMALLINT UNSIGNED NOT NULL DEFAULT 1,

    points_for_loss SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    tiebreak_rule_1 ENUM(
        'POINTS',
        'SCORE_DIFFERENCE',
        'SCORE_FOR',
        'HEAD_TO_HEAD'
    ) NOT NULL DEFAULT 'POINTS',

    tiebreak_rule_2 ENUM(
        'POINTS',
        'SCORE_DIFFERENCE',
        'SCORE_FOR',
        'HEAD_TO_HEAD'
    ) NULL,

    tiebreak_rule_3 ENUM(
        'POINTS',
        'SCORE_DIFFERENCE',
        'SCORE_FOR',
        'HEAD_TO_HEAD'
    ) NULL,

    tiebreak_rule_4 ENUM(
        'POINTS',
        'SCORE_DIFFERENCE',
        'SCORE_FOR',
        'HEAD_TO_HEAD'
    ) NULL,

    tournament_status ENUM(
        'DRAFT',
        'REGISTRATION_OPEN',
        'REGISTRATION_CLOSED',
        'ONGOING',
        'COMPLETED',
        'CANCELLED'
    ) NOT NULL DEFAULT 'DRAFT',

    created_by_user_id INT UNSIGNED NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_tournaments_sport (sport_id),

    KEY idx_tournaments_status (tournament_status),

    KEY idx_tournaments_dates (start_date, end_date),

    KEY idx_tournaments_created_by (created_by_user_id),

    CONSTRAINT fk_tournaments_sport
        FOREIGN KEY (sport_id)
        REFERENCES sports (sport_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_tournaments_created_by
        FOREIGN KEY (created_by_user_id)
        REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB;


-- ============================================================
-- 2. TOURNAMENT TEAMS
-- ============================================================

CREATE TABLE tournament_teams (
    tournament_team_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    tournament_id INT UNSIGNED NOT NULL,

    team_id INT UNSIGNED NOT NULL,

    registration_status ENUM(
        'REGISTERED',
        'WITHDRAWN',
        'DISQUALIFIED'
    ) NOT NULL DEFAULT 'REGISTERED',

    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    registered_by_user_id INT UNSIGNED NOT NULL,

    notes VARCHAR(500) NULL,

    UNIQUE KEY uq_tournament_team (
        tournament_id,
        team_id
    ),

    KEY idx_tournament_teams_tournament (
        tournament_id
    ),

    KEY idx_tournament_teams_team (
        team_id
    ),

    KEY idx_tournament_teams_registered_by (
        registered_by_user_id
    ),

    CONSTRAINT fk_tournament_teams_tournament
        FOREIGN KEY (tournament_id)
        REFERENCES tournaments (tournament_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_tournament_teams_team
        FOREIGN KEY (team_id)
        REFERENCES teams (team_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_tournament_teams_registered_by
        FOREIGN KEY (registered_by_user_id)
        REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB;


-- ============================================================
-- 3. MATCHES
-- ============================================================

CREATE TABLE matches (
    match_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    tournament_id INT UNSIGNED NOT NULL,

    home_team_id INT UNSIGNED NOT NULL,

    away_team_id INT UNSIGNED NOT NULL,

    match_number INT UNSIGNED NULL,

    round_number INT UNSIGNED NULL,

    scheduled_date DATE NULL,

    scheduled_time TIME NULL,

    venue VARCHAR(150) NULL,

    match_status ENUM(
        'SCHEDULED',
        'LIVE',
        'COMPLETED',
        'POSTPONED',
        'CANCELLED'
    ) NOT NULL DEFAULT 'SCHEDULED',

    created_by_user_id INT UNSIGNED NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_matches_tournament (
        tournament_id
    ),

    KEY idx_matches_home_team (
        home_team_id
    ),

    KEY idx_matches_away_team (
        away_team_id
    ),

    KEY idx_matches_status (
        match_status
    ),

    KEY idx_matches_schedule (
        scheduled_date,
        scheduled_time
    ),

    CONSTRAINT fk_matches_tournament
        FOREIGN KEY (tournament_id)
        REFERENCES tournaments (tournament_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_matches_home_team
        FOREIGN KEY (home_team_id)
        REFERENCES teams (team_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_matches_away_team
        FOREIGN KEY (away_team_id)
        REFERENCES teams (team_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_matches_created_by
        FOREIGN KEY (created_by_user_id)
        REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_matches_different_teams
        CHECK (home_team_id <> away_team_id)

) ENGINE=InnoDB;


-- ============================================================
-- 4. MATCH RESULTS
-- ============================================================

CREATE TABLE match_results (
    match_result_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    match_id INT UNSIGNED NOT NULL,

    home_score DECIMAL(10,2) NOT NULL DEFAULT 0,

    away_score DECIMAL(10,2) NOT NULL DEFAULT 0,

    result_type ENUM(
        'HOME_WIN',
        'AWAY_WIN',
        'DRAW'
    ) NOT NULL,

    remarks VARCHAR(500) NULL,

    entered_by_user_id INT UNSIGNED NOT NULL,

    entered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_match_result_match (
        match_id
    ),

    KEY idx_match_results_entered_by (
        entered_by_user_id
    ),

    CONSTRAINT fk_match_results_match
        FOREIGN KEY (match_id)
        REFERENCES matches (match_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_match_results_entered_by
        FOREIGN KEY (entered_by_user_id)
        REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_match_results_non_negative
        CHECK (
            home_score >= 0
            AND away_score >= 0
        )

) ENGINE=InnoDB;


-- ============================================================
-- 5. TOURNAMENT STANDINGS
-- ============================================================

CREATE TABLE tournament_standings (
    standing_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    tournament_id INT UNSIGNED NOT NULL,

    team_id INT UNSIGNED NOT NULL,

    matches_played INT UNSIGNED NOT NULL DEFAULT 0,

    wins INT UNSIGNED NOT NULL DEFAULT 0,

    draws INT UNSIGNED NOT NULL DEFAULT 0,

    losses INT UNSIGNED NOT NULL DEFAULT 0,

    score_for DECIMAL(10,2) NOT NULL DEFAULT 0,

    score_against DECIMAL(10,2) NOT NULL DEFAULT 0,

    score_difference DECIMAL(10,2)
        NOT NULL DEFAULT 0,

    points INT NOT NULL DEFAULT 0,

    current_rank INT UNSIGNED NULL,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_tournament_standing_team (
        tournament_id,
        team_id
    ),

    KEY idx_standings_tournament_rank (
        tournament_id,
        current_rank
    ),

    KEY idx_standings_team (
        team_id
    ),

    CONSTRAINT fk_standings_tournament
        FOREIGN KEY (tournament_id)
        REFERENCES tournaments (tournament_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_standings_team
        FOREIGN KEY (team_id)
        REFERENCES teams (team_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB;