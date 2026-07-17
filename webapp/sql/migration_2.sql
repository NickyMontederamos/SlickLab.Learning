-- Migration 2: adds columns for weak-area analytics, spaced repetition,
-- option shuffling, exam countdown, and wrong-answer trap notes.
-- Safe to run on an existing database that already has the original schema.

ALTER TABLE users
    ADD COLUMN exam_date DATE NULL,
    ADD UNIQUE KEY uniq_username (username);

ALTER TABLE questions
    ADD COLUMN wrong_answer_notes TEXT NULL;

ALTER TABLE flashcard_progress
    ADD COLUMN box TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN due_at DATETIME NULL;

ALTER TABLE exam_attempts
    ADD COLUMN option_order MEDIUMTEXT NULL AFTER question_ids;
