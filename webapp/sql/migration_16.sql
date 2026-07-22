-- Spellfire Beta: a fully separate, self-contained clone of the Quiz Battle
-- module (rooms/participants/answers/reactions) so an experimental class/
-- milestone game mode can iterate freely with zero risk to the live tables.
-- Every class ability in this beta is deliberately self-targeted only --
-- no ability reads or writes another participant's row -- so none of this
-- needs cross-player locking; a player only ever mutates their own row.
CREATE TABLE IF NOT EXISTS battle_beta_rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(8) NOT NULL UNIQUE,
    host_user_id INT UNSIGNED NOT NULL,
    item_count INT UNSIGNED NOT NULL DEFAULT 10,
    winning_score INT UNSIGNED NULL,
    question_ids MEDIUMTEXT NULL,
    current_index INT NOT NULL DEFAULT -1,
    question_started_at DATETIME NULL,
    status ENUM('waiting','in_progress','paused','finished') NOT NULL DEFAULT 'waiting',
    paused_at DATETIME NULL,
    disconnected_user_id INT UNSIGNED NULL,
    tts_enabled TINYINT(1) NOT NULL DEFAULT 0,
    question_weights MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Milestone/effect columns beyond the classic participant row:
-- next_correct_bonus and wrong_answer_shield_charges and
-- pending_extra_seconds are three INDEPENDENT flags (not one shared
-- "active effect" slot) specifically so a later milestone (e.g. Saboteur's
-- System Lag at 50) can never silently clobber an earlier one still
-- pending (e.g. unconsumed Corrupted Cache charges from 25) before it's
-- had a chance to fire.
CREATE TABLE IF NOT EXISTS battle_beta_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    is_host TINYINT(1) NOT NULL DEFAULT 0,
    is_ready TINYINT(1) NOT NULL DEFAULT 0,
    score INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('active','kicked','left') NOT NULL DEFAULT 'active',
    last_seen_at DATETIME NULL,
    current_streak INT UNSIGNED NOT NULL DEFAULT 0,
    best_streak INT UNSIGNED NOT NULL DEFAULT 0,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    class_key ENUM('speedster','saboteur') NULL,
    mana TINYINT UNSIGNED NOT NULL DEFAULT 0,
    unlocked_tier TINYINT UNSIGNED NOT NULL DEFAULT 0,
    next_correct_bonus ENUM('haste','overwrite') NULL,
    wrong_answer_shield_charges TINYINT UNSIGNED NOT NULL DEFAULT 0,
    pending_extra_seconds TINYINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (room_id) REFERENCES battle_beta_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_beta_room_user (room_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS battle_beta_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    question_index INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    selected_letters VARCHAR(20) NOT NULL DEFAULT '',
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    seconds_taken DECIMAL(5,2) NULL,
    answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES battle_beta_rooms(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_beta_room_user_qidx (room_id, user_id, question_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS battle_beta_reactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    emoji VARCHAR(8) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES battle_beta_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
