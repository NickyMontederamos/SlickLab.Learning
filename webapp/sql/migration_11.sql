-- Incorrect Answers Review learning loop: mini-exams are modeled as real
-- exam_attempts rows (reusing all existing grading/scoring code), linked
-- back to the original full exam via parent_attempt_id. See
-- webapp/lib/incorrect_review.php and SOLUTIONS_LOG.md (2026-07-21 entry).
ALTER TABLE exam_attempts
    ADD COLUMN parent_attempt_id INT UNSIGNED NULL,
    ADD COLUMN attempt_kind ENUM('full','mini') NOT NULL DEFAULT 'full';

ALTER TABLE exam_attempts
    ADD CONSTRAINT fk_exam_attempts_parent FOREIGN KEY (parent_attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    ADD INDEX idx_exam_attempts_parent (parent_attempt_id);
