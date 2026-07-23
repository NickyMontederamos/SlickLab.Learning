# Exhibition Exam — Implementation Spec

Agreed design from a planning conversation (not yet built). Start a fresh conversation, point it at this file, and build from here — no need to re-derive any of the reasoning below, it's settled.

## What it is
A host picks 2+ of their own unlocked topics as candidates, invites others via a join code, everyone who joins votes on topics from their *own* unlocked set, the top-voted topics become a shared full-length exam, and everyone takes it independently within a 24h window. If 2+ people actually take it, the winner (and only the winner, if it stayed solo it's discarded entirely) gets recorded to the leaderboard.

## Schema changes (additive only, matches this project's existing conventions)
- `exam_attempts.attempt_kind` ENUM gets `'custom'` added (alongside existing `full,mini,topic,topic_block`).
- `exam_attempts` gets a new nullable column `topic_ids MEDIUMTEXT` (JSON array) — same storage pattern this column's neighbor `question_ids` already uses on the same table. No new join table.
- `exam_attempts` gets a new nullable column `exhibition_session_id INT UNSIGNED` (FK to the new table below) to group individual attempts into one session for winner comparison.
- New table `exhibition_sessions`:
  - `id`, `code VARCHAR(8) UNIQUE`, `host_user_id`
  - `status ENUM('waiting','open','closed')` — `waiting` = lobby/voting live, `open` = topics finalized + shared exam generated + 24h clock running, `closed` = expired or host-closed
  - `question_ids MEDIUMTEXT NULL` — the shared/finalized question set, generated once at finalize time, same for every participant's attempt
  - `host_last_seen_at DATETIME NULL` — for the join-notification check
  - `opened_at DATETIME NULL`, `closes_at DATETIME NULL` (opened_at + 24h), `closed_at DATETIME NULL`
  - `created_at DATETIME DEFAULT CURRENT_TIMESTAMP`
- New table `exhibition_votes`:
  - `id`, `session_id` (FK), `user_id` (FK), `topic_id` (FK)
  - `UNIQUE KEY (session_id, user_id, topic_id)` — one vote per user per topic, multiple topics per user allowed
- Both new tables need adding to `webapp/lib/table_prefix.php`'s `CSA_TABLES` const, or SlickPrepTime staging will silently query unprefixed tables. Follow the existing migration-comment gotcha (avoid whole-word table names like "topics" inside SQL comments — `csa_prefix_tables()` rewrites those too; see SOLUTIONS_LOG.md 2026-07-22 entry).

## Topic vote tally (write as a pure, tested function — same convention as `topic_quiz.php`)
1. Every participant votes for any number of topics from their own currently-unlocked set (server validates each vote against that voter's own `csa_compute_unlocked_topics()` result — never trust client-claimed unlock status).
2. Tally = count of distinct voters per topic.
3. Sort by vote count descending, tie-break by the topic's `sort_order` ascending (deterministic).
4. Inclusion rule: a topic makes the final exam if it got votes from at least half the participants (majority, `ceil(participantCount / 2)`). If fewer than 2 topics clear that bar, fall back to simply taking the top 2 highest-voted topics outright — guarantees the 2+ minimum always resolves.
5. Host manually triggers finalize (no fixed voting-window timer) — this runs the tally once, unions+dedupes the winning topics' question pools, generates `exhibition_sessions.question_ids`, flips status to `open`, stamps `opened_at`/`closes_at` (+24h).

## Question scope
No size picker — the exam is every question from the winning topics' combined pool (true "full exam" framing), matching the earlier confirmed decision.

## Exam-taking (async, reuses existing infrastructure)
Once a session is `open`, each participant independently starts their own `exam_attempts` row (`attempt_kind='custom'`, `exhibition_session_id` set, `topic_ids` = the winning topic IDs, `question_ids` = the session's shared shuffled set) whenever they want within the 24h window. Reuses `exam.html`/`exam.js` and `exam_submit.php` entirely — just needs `'custom'` added to `exam.js`'s existing `attemptKind === 'mini' || attemptKind === 'topic' || ...` auto-enter condition, and a `'custom'` branch in `results-message.js` for the post-submit CTA (no "next topic unlocked" messaging, since this doesn't gate anything).

## Winner + leaderboard
- Winner = highest `correct_count` (equivalent ranking to `score_percent` here since everyone answers the identical shared question set), tiebreak = lowest `duration_seconds`.
- Computed once the session is `closed` (24h elapsed, or host manually closes early).
- If 2+ distinct users actually submitted an attempt tied to this session: winner's result counts toward the leaderboard. If only the host (or only 1 person total) ever took it: **discard entirely, nothing recorded**.
- Leaderboard's existing "Best Mock Exam" component stays scoped to `attempt_kind='full'` only — unchanged, no code touches this. Exhibition wins need their own separate leaderboard signal/column, not blended into that metric.

## Revision pool
Wrong answers from Exhibition Exam attempts feed the **global** Incorrect Answers Review pool (Mock Exam → Flashcards → Mini-Exam) — same as Full Mock Exam attempts, since this is genuinely multi-topic exam-style content. Likely just needs the existing pool-derivation query's `attempt_kind` filter widened from `= 'full'` to `IN ('full','custom')` — find that query (probably in `incorrect_review.php` or wherever the global pool is derived) and confirm this is a safe, additive change before touching it.

## Lobby UI (new)
A waiting-room page — conceptually similar to Battle Rooms' lobby (roster, join code, host controls) but much lighter: no live turn-by-turn state needed, since nothing time-critical happens once the exam itself starts (that part is async, per above). Needs light polling (every few seconds, not Battle Rooms' 1.2s cadence) to detect new joins and update the vote tally display live.

**Background music / join notification:** a quiet looped `<audio>` track plays while anyone is in the lobby. Each poll tick compares participant count against the last-seen count; on an increase, briefly swell the volume (ramp up, decay back down after a few seconds) as the join alert. No browser push-permission prompts needed since it's just controlling an already-playing local audio element — this app has no existing notification system, and this avoids building one.

## New/changed files (expected surface)
- `webapp/sql/migration_17.sql` — the schema changes above
- `webapp/lib/exhibition_exam.php` (new) — pure functions: vote tally, topic finalization, winner computation; PHPUnit tests alongside, same convention as every other `lib/*.php` in this project
- New API endpoints (mirror existing naming): `exhibition_create.php`, `exhibition_join.php`, `exhibition_vote.php`, `exhibition_lobby_state.php` (polled), `exhibition_finalize.php`, `exhibition_start_attempt.php` (wraps `exam_start.php`-style logic, scoped to a session), `exhibition_close.php` (or a cron-less lazy-expiry check on read)
- `webapp/exhibition.html` + `webapp/assets/js/exhibition.js` (new) — lobby UI + audio
- `webapp/assets/js/api.js` — new `exhibition*` client methods
- `webapp/assets/js/exam.js` — add `'custom'` to auto-enter condition
- `webapp/assets/js/lib/results-message.js` — add `'custom'` branch + tests
- `webapp/lib/table_prefix.php` — add `exhibition_sessions`, `exhibition_votes` to `CSA_TABLES`
- `tests/TablePrefixTest.php` — update expected table count/list

## Verification plan (once built)
- PHPUnit for the new pure functions (tally, finalize, winner calc) — happy path, tie cases, majority-not-reached fallback, solo-session-discarded case.
- Full PHP+JS suite green, `php -l` / lint sweep.
- Live browser walk with 2+ real accounts: create session → both join lobby → both vote (confirm tally updates) → host finalizes → confirm winning topics match the vote math → both take the exam async → confirm winner computed correctly → confirm leaderboard updated only for the winner, only because it was genuinely multiplayer.
- Separately verify: a session that stays solo produces zero leaderboard changes.
- Log outcome to `SOLUTIONS_LOG.md`.
- Staging deploy (sync into `SlickPrepTimeLatest/SlickPrepTime/`, same file-by-file convention as every prior deploy) is a later, separate step — not part of the initial build.
