-- ============================================================
-- Gamified College Learning & Placement Platform
-- MySQL Schema — Run this in phpMyAdmin or MySQL CLI
-- Command: mysql -u root -p < schema.sql
-- ============================================================

-- ─── USERS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50)  NOT NULL,
    email           VARCHAR(100) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    college         VARCHAR(100) NOT NULL,
    branch          ENUM('CSE','IT','ECE','EEE','ME','CE','Other') DEFAULT 'CSE',
    graduation_year SMALLINT     NOT NULL,

    -- Gamification
    current_level   TINYINT      DEFAULT 1,
    total_xp        INT          DEFAULT 0,
    weekly_xp       INT          DEFAULT 0,
    weekly_xp_reset DATE         DEFAULT (DATE_ADD(CURDATE(), INTERVAL (7 - WEEKDAY(CURDATE())) DAY)),
    streak          SMALLINT     DEFAULT 0,
    max_streak      SMALLINT     DEFAULT 0,
    last_active_date DATE        DEFAULT NULL,

    -- Profile
    bio             VARCHAR(200) DEFAULT '',
    avatar_url      VARCHAR(255) DEFAULT '',
    linkedin_url    VARCHAR(255) DEFAULT '',
    github_url      VARCHAR(255) DEFAULT '',

    -- Access
    role            ENUM('student','admin') DEFAULT 'student',
    is_active       TINYINT(1)   DEFAULT 1,

    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email         (email),
    INDEX idx_current_level (current_level),
    INDEX idx_weekly_xp     (weekly_xp DESC),
    INDEX idx_total_xp      (total_xp DESC)
);

-- ─── LEVELS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS levels (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    level_number TINYINT      NOT NULL UNIQUE,
    title        VARCHAR(100) NOT NULL,
    description  TEXT         NOT NULL,
    xp_required  INT          NOT NULL DEFAULT 500,
    icon_emoji   VARCHAR(10)  DEFAULT '',
    color        VARCHAR(10)  DEFAULT '#2E75B6',
    is_active    TINYINT(1)   DEFAULT 1,

    INDEX idx_level_number (level_number)
);

-- ─── TASKS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tasks (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(150) NOT NULL,
    description  TEXT         NOT NULL,
    level_number TINYINT      NOT NULL,
    xp_reward    SMALLINT     NOT NULL DEFAULT 50,
    task_type    ENUM('coding','project','reading','practice','resume','interview','other') DEFAULT 'other',
    resource_url VARCHAR(255) DEFAULT '',
    sort_order   SMALLINT     DEFAULT 0,
    is_active    TINYINT(1)   DEFAULT 1,

    INDEX idx_level (level_number, sort_order),
    FOREIGN KEY (level_number) REFERENCES levels(level_number) ON DELETE CASCADE
);

-- ─── USER COMPLETED TASKS ────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_completed_tasks (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT      NOT NULL,
    task_id      INT      NOT NULL,
    xp_earned    SMALLINT NOT NULL DEFAULT 0,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_task (user_id, task_id),  -- Prevent re-completion
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id)  ON DELETE CASCADE
);

-- ─── BADGES ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS badges (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL UNIQUE,
    description   VARCHAR(255) NOT NULL,
    icon_emoji    VARCHAR(10)  DEFAULT '',
    trigger_type  ENUM('xp','streak','level','task_count','mentorship','leaderboard','manual') NOT NULL,
    trigger_value INT          NOT NULL,
    rarity        ENUM('common','rare','epic','legendary') DEFAULT 'common',
    is_active     TINYINT(1)   DEFAULT 1
);

-- ─── USER BADGES ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_badges (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id   INT NOT NULL,
    badge_id  INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_badge (user_id, badge_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
);

-- ─── XP HISTORY (weekly, for charts) ─────────────────────────
CREATE TABLE IF NOT EXISTS xp_history (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT         NOT NULL,
    week    VARCHAR(10) NOT NULL,  -- e.g. "2025-W12"
    xp      INT         DEFAULT 0,

    UNIQUE KEY unique_user_week (user_id, week),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── CONNECTIONS (Mentorship) ─────────────────────────────────
CREATE TABLE IF NOT EXISTS connections (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    mentor_id    INT NOT NULL,
    status       ENUM('pending','accepted','declined') DEFAULT 'pending',
    message      VARCHAR(300) DEFAULT '',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_connection (requester_id, mentor_id),
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mentor_id)    REFERENCES users(id) ON DELETE CASCADE
);

-- ─── DISCUSSIONS (Level Q&A Board) ───────────────────────────
CREATE TABLE IF NOT EXISTS discussions (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    level_number TINYINT      NOT NULL,
    author_id    INT          NOT NULL,
    title        VARCHAR(150) NOT NULL,
    content      TEXT         NOT NULL,
    upvotes      INT          DEFAULT 0,
    is_resolved  TINYINT(1)   DEFAULT 0,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_level_created (level_number, created_at DESC),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── DISCUSSION REPLIES ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS discussion_replies (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    discussion_id INT  NOT NULL,
    author_id     INT  NOT NULL,
    content       TEXT NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (discussion_id) REFERENCES discussions(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id)     REFERENCES users(id) ON DELETE CASCADE
);
