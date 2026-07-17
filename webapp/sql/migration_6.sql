-- Presence tracking for the "who's online" indicator on the Quiz Battle intro screen.

ALTER TABLE users
    ADD COLUMN last_active_at DATETIME NULL;
