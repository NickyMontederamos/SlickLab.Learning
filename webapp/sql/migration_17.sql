-- Exhibition Exam: a host-created multi-topic voting session. Invite via a
-- join code, everyone votes on candidate topics from their own unlocked
-- set, the winning topics become one shared full-length exam, everyone
-- takes it independently within a 24h window. Leaderboard credit only if
-- 2+ people actually take it. See CSA_Prep/EXHIBITION_EXAM_PLAN.md (agreed
-- spec) and SOLUTIONS_LOG.md for the design decisions below.

ALTER TABLE exam_attempts
    MODIFY COLUMN attempt_kind ENUM('full','mini','topic','topic_block','custom') NOT NULL DEFAULT 'full',
    ADD COLUMN topic_ids MEDIUMTEXT NULL,
    ADD COLUMN exhibition_session_id INT UNSIGNED NULL,
    ADD INDEX idx_exam_attempts_exhibition_session (exhibition_session_id);

-- No candidate-topics column: the host's chosen candidates are recorded as
-- their own initial votes (see exhibition_create.php), so "the set of
-- topics this session can be voted on" is simply
-- `SELECT DISTINCT topic_id FROM exhibition_votes WHERE session_id = ?` --
-- one less denormalized list to keep in sync, same "derive, don't
-- duplicate" principle as topic_quiz.php's unlock chain. Votes are add-only
-- (no un-voting) specifically so this derived candidate set can never lose
-- a topic partway through a session.
--
-- No winning-topic-ids column either: the winning set is always
-- recomputed from exhibition_votes via csa_tally_exhibition_votes() --
-- votes are frozen once status leaves 'waiting', so re-tallying at
-- exhibition_start_attempt.php time is deterministic and cheap at this
-- app's scale (see webapp/lib/exhibition_exam.php).
--
-- No participants table: a "participant" is simply a distinct voter
-- (COUNT(DISTINCT user_id) in exhibition_votes for the session), which
-- also always includes the host once their seed votes land.
--
-- No winner/is_winner flag: same reasoning as leaderboard.php's existing
-- battle top-score subquery -- the winner is derived at read time from
-- exam_attempts.correct_count/duration_seconds for completed 'custom'
-- attempts sharing an exhibition_session_id, never stored twice.
CREATE TABLE IF NOT EXISTS exhibition_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(8) NOT NULL UNIQUE,
    host_user_id INT UNSIGNED NOT NULL,
    status ENUM('waiting','open','closed') NOT NULL DEFAULT 'waiting',
    question_ids MEDIUMTEXT NULL,
    host_last_seen_at DATETIME NULL,
    opened_at DATETIME NULL,
    closes_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exhibition_votes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    topic_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES exhibition_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_exhibition_session_user_topic (session_id, user_id, topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
