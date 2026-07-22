-- Adds a dense, high-yield "Reviewer Module" per topic entry -- distinct
-- from the existing soft overview text and from the per-block review
-- content: a scannable cram-sheet, not prose, meant for last-pass review
-- right before attempting a Gate Check or the Full Mock Exam.
ALTER TABLE topics
    ADD COLUMN reviewer_md MEDIUMTEXT NULL,
    ADD COLUMN reviewer_status ENUM('placeholder','draft','published') NOT NULL DEFAULT 'placeholder';
