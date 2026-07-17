-- Migration 4: Quiz Battle disconnect handling (auto-pause, kick, end battle).
-- Safe to run on an existing database that already has migration_3 applied.

ALTER TABLE battle_rooms
    MODIFY status ENUM('waiting','in_progress','paused','finished') NOT NULL DEFAULT 'waiting',
    ADD COLUMN paused_at DATETIME NULL,
    ADD COLUMN disconnected_user_id INT UNSIGNED NULL;

ALTER TABLE battle_participants
    ADD COLUMN status ENUM('active','kicked','left') NOT NULL DEFAULT 'active',
    ADD COLUMN last_seen_at DATETIME NULL;
