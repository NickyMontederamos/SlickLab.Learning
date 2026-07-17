-- Quiz Battle upgrade: TTS lock, difficulty-weighted scoring, streaks, reaction time, quick reactions.

ALTER TABLE battle_rooms
    ADD COLUMN tts_enabled TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN question_weights MEDIUMTEXT NULL;

ALTER TABLE battle_participants
    ADD COLUMN current_streak INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN best_streak INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE battle_answers
    ADD COLUMN seconds_taken DECIMAL(5,2) NULL;

CREATE TABLE IF NOT EXISTS battle_reactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    emoji VARCHAR(8) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES battle_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
