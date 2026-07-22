-- Performance: exam_attempts had no index covering user_id, only
-- parent_attempt_id. Every dashboard/history/activity-streak query filters
-- by user_id directly (api/activity.php, api/exam_history.php,
-- api/topics.php, api/topic_quiz_start.php) and was doing a full table
-- scan to do it.
ALTER TABLE exam_attempts ADD INDEX idx_exam_attempts_user (user_id);
