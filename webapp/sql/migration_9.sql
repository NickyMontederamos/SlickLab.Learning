-- Self-rated confidence per flashcard (1-5), so the Focus Coach can tell
-- "false confidence" (feels sure, gets it wrong) apart from a lucky guess
-- (unsure, gets it right) -- the psychological-vs-actual-knowledge signal.
ALTER TABLE flashcard_progress
    ADD COLUMN last_confidence TINYINT UNSIGNED NULL;
