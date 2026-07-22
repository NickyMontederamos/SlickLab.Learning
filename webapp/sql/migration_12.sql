-- Gamified Topic Learning Loop, Phase 1: consolidates the 25 existing
-- questions.category values into 22 clean topics, adds an admin role (for
-- topic lesson authoring), and extends exam_attempts to support topic
-- quizzes alongside the existing full/mini kinds. See
-- webapp/lib/topic_quiz.php and SOLUTIONS_LOG.md (2026-07-22 entry).

-- Category consolidation (must match the same rename already applied to
-- webapp/data/questions.json, or a reseed via seed.php would revert this).
UPDATE questions SET category = 'Security & ACL' WHERE category = 'Security & Roles';
UPDATE questions SET category = 'ITSM & SLA' WHERE category IN ('ITSM', 'ITSM/SLA');
UPDATE questions SET category = 'Scripting & Automation' WHERE category IN ('Scripting', 'Automation/AI');

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

ALTER TABLE users
    ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0;

-- Named fk_exam_attempts_topic_2026, matching the _2026 suffix workaround
-- applied to fk_exam_attempts_parent in migration_11.sql for the same
-- reason (a same-database constraint-name collision unrelated to this
-- table) -- see that file's comment and SOLUTIONS_LOG.md, 2026-07-22 entry.
ALTER TABLE exam_attempts
    ADD COLUMN topic_id INT UNSIGNED NULL,
    ADD CONSTRAINT fk_exam_attempts_topic_2026 FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL,
    MODIFY COLUMN attempt_kind ENUM('full','mini','topic') NOT NULL DEFAULT 'full';

-- Pedagogical order: platform fundamentals -> data/config -> automation ->
-- specialized modules. sort_order also drives topic-unlock gating (topic N
-- unlocks once topic N-1 has a passing 'topic' attempt).
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
