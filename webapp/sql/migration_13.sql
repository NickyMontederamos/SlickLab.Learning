-- Performance: exam_attempts had no index covering user_id, only
-- parent_attempt_id. Every dashboard/history/activity-streak query filters
-- by user_id directly (api/activity.php, api/exam_history.php, and the
-- topic-quiz-gating endpoints) and was doing a full table scan to do it.
-- (Deliberately not spelling out the literal endpoint filenames or the
-- plural form of the lesson-content table name here -- the prefixing
-- helper rewrites that whole word wherever it appears, even inside a
-- comment. See SOLUTIONS_LOG.md, 2026-07-22.)
ALTER TABLE exam_attempts ADD INDEX idx_exam_attempts_user (user_id);
