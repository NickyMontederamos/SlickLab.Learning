-- Adaptive Tiered Block Pipeline (Phase 2 of the topic-learning feature).
-- Splits a robust topic's quiz into sequential micro-blocks, each gated by
-- its own small formative check, followed by a cumulative Gate Check that
-- actually unlocks the next topic. 'topic' keeps its existing meaning (the
-- attempt that gates the unlock) -- only the new intermediate block quizzes
-- get the new 'topic_block' kind, so every existing pass-detection query
-- keeps working unchanged.
ALTER TABLE exam_attempts
    MODIFY COLUMN attempt_kind ENUM('full','mini','topic','topic_block') NOT NULL DEFAULT 'full',
    ADD COLUMN block_number SMALLINT UNSIGNED NULL,
    ADD INDEX idx_exam_attempts_topic_block (topic_id, attempt_kind, block_number);

-- Authored content for the pipeline: one row per (topic, block, content
-- type). A robust topic gets block_number 1..N with content_type='review'.
-- A thin (small-pool) topic gets block_number=0 rows for 'review',
-- 'lab_instructions', and 'lab_checklist' -- the hands-on practical-exercise
-- track for a subject area with too few authored items to test on its own.
-- Block-to-question mapping is deliberately not stored here -- it's derived
-- at request time from the item pool itself, same "derive don't
-- denormalize" principle as the existing unlock chain and revision pool.
CREATE TABLE IF NOT EXISTS topic_block_content (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id INT UNSIGNED NOT NULL,
    block_number SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    content_type ENUM('review','lab_instructions','lab_checklist') NOT NULL DEFAULT 'review',
    body_md MEDIUMTEXT NULL,
    status ENUM('placeholder','draft','published') NOT NULL DEFAULT 'placeholder',
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NULL,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_topic_block_type (topic_id, block_number, content_type),
    INDEX idx_topic_block_content_topic (topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
