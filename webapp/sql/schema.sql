-- CSA Prep schema (MySQL / MariaDB compatible, works on InfinityFree)

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    username VARCHAR(60) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    exam_date DATE NULL,
    last_active_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    service_now_url VARCHAR(255) NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(40) NOT NULL,
    orig_num INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    choose_n TINYINT UNSIGNED NOT NULL DEFAULT 1,
    category VARCHAR(60) NOT NULL,
    explanation TEXT NOT NULL,
    wrong_answer_notes TEXT NULL,
    confidence ENUM('high','medium','low') NOT NULL DEFAULT 'high',
    walkthrough TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    letter CHAR(1) NOT NULL,
    option_text TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_options_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS flashcard_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    status ENUM('unseen','known','review') NOT NULL DEFAULT 'unseen',
    box TINYINT UNSIGNED NOT NULL DEFAULT 0,
    due_at DATETIME NULL,
    last_reviewed_at DATETIME NULL,
    note TEXT NULL,
    last_confidence TINYINT UNSIGNED NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_question (user_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS topics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    category_key VARCHAR(60) NOT NULL UNIQUE,
    sort_order SMALLINT UNSIGNED NOT NULL,
    lesson_body_md MEDIUMTEXT NULL,
    lesson_status ENUM('placeholder','draft','published') NOT NULL DEFAULT 'placeholder',
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_topics_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS topic_lesson_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id INT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255) NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by INT UNSIGNED NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_topic_lesson_images_topic (topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 5400,
    total_questions INT UNSIGNED NOT NULL DEFAULT 0,
    question_ids MEDIUMTEXT NULL,
    option_order MEDIUMTEXT NULL,
    correct_count INT UNSIGNED NOT NULL DEFAULT 0,
    score_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    passed TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('in_progress','completed','abandoned') NOT NULL DEFAULT 'in_progress',
    parent_attempt_id INT UNSIGNED NULL,
    attempt_kind ENUM('full','mini','topic') NOT NULL DEFAULT 'full',
    topic_id INT UNSIGNED NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL,
    INDEX idx_exam_attempts_parent (parent_attempt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    selected_letters VARCHAR(20) NOT NULL DEFAULT '',
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_attempt_question (attempt_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS battle_rooms (
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

CREATE TABLE IF NOT EXISTS battle_participants (
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
    FOREIGN KEY (room_id) REFERENCES battle_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_room_user (room_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS battle_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    question_index INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    selected_letters VARCHAR(20) NOT NULL DEFAULT '',
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    seconds_taken DECIMAL(5,2) NULL,
    answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES battle_rooms(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_room_user_qidx (room_id, user_id, question_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS battle_reactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    emoji VARCHAR(8) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES battle_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed topic taxonomy for the Gamified Topic Learning Loop (Phase 1), one
-- row per consolidated category. sort_order also drives topic-unlock gating.
INSERT INTO topics (slug, name, category_key, sort_order) VALUES
    ('navigation', 'Navigation', 'Navigation', 1),
    ('platform-admin', 'Platform Admin', 'Platform Admin', 2),
    ('users-groups-roles', 'Users, Groups & Roles', 'Users/Groups/Roles', 3),
    ('security-acl', 'Security & ACL', 'Security & ACL', 4),
    ('forms', 'Forms', 'Forms', 5),
    ('lists-filters', 'Lists & Filters', 'Lists & Filters', 6),
    ('data-model', 'Data Model', 'Data Model', 7),
    ('cmdb', 'CMDB', 'CMDB', 8),
    ('business-logic', 'Business Logic', 'Business Logic', 9),
    ('update-sets', 'Update Sets', 'Update Sets', 10),
    ('service-catalog', 'Service Catalog', 'Service Catalog', 11),
    ('knowledge-management', 'Knowledge Management', 'Knowledge Management', 12),
    ('notifications', 'Notifications', 'Notifications', 13),
    ('reporting', 'Reporting', 'Reporting', 14),
    ('data-import', 'Data Import', 'Data Import', 15),
    ('flow-designer', 'Flow Designer', 'Flow Designer', 16),
    ('scripting-automation', 'Scripting & Automation', 'Scripting & Automation', 17),
    ('collaboration', 'Collaboration', 'Collaboration', 18),
    ('virtual-agent', 'Virtual Agent', 'Virtual Agent', 19),
    ('itsm-sla', 'ITSM & SLA', 'ITSM & SLA', 20),
    ('testing-atf', 'Testing/ATF', 'Testing/ATF', 21),
    ('agile-vtb', 'Agile/VTB', 'Agile/VTB', 22);
