-- Hands-on "Show Me How" walkthroughs, plus the ServiceNow instance URL
-- used to resolve {{SERVICE_NOW_URL}} in walkthrough text. See
-- webapp/lib/walkthrough.php and SOLUTIONS_LOG.md (2026-07-21 entry).
ALTER TABLE questions
    ADD COLUMN walkthrough TEXT NULL;

ALTER TABLE users
    ADD COLUMN service_now_url VARCHAR(255) NULL;
