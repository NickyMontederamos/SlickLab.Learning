-- Incorrect Answers Review learning loop: mini-exams are modeled as real
-- exam_attempts rows (reusing all existing grading/scoring code), linked
-- back to the original full exam via parent_attempt_id. See
-- webapp/lib/incorrect_review.php and SOLUTIONS_LOG.md (2026-07-21 entry).
ALTER TABLE exam_attempts
    ADD COLUMN parent_attempt_id INT UNSIGNED NULL,
    ADD COLUMN attempt_kind ENUM('full','mini') NOT NULL DEFAULT 'full';

-- Named fk_exam_attempts_parent_2026 (not the more obvious
-- fk_exam_attempts_parent) because that name collided with a stray,
-- unexplained leftover constraint name already present in production's
-- InnoDB dictionary when this was first applied there (2026-07-22) --
-- information_schema showed nothing using it, but MySQL/MariaDB scope
-- foreign-key constraint names per-DATABASE, not per-table, and something
-- InnoDB still considered "taken" rejected the plain name with errno 121.
-- See SOLUTIONS_LOG.md, 2026-07-22 entry.
ALTER TABLE exam_attempts
    ADD CONSTRAINT fk_exam_attempts_parent_2026 FOREIGN KEY (parent_attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    ADD INDEX idx_exam_attempts_parent (parent_attempt_id);
