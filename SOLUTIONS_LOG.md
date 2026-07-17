# Solutions Log

Running log of non-obvious fixes and architecture decisions — newest entries at the top. Populated by the `solutions-log` skill (see [CLAUDE.md](CLAUDE.md)). Skip trivial fixes; only log things that took real investigation or involved a tradeoff.

## 2026-07-18 First test suite: extracted Focus Coach scoring for testability

**Symptom:** No automated tests existed anywhere in this project. All prior verification (per the session that built this app) was manual browser testing — not repeatable, nothing to run `ci-baseline-guard` against.
**Root cause:** N/A — not a bug. `api/focus_coach.php` mixed live PDO queries with the priority-scoring math in one script-level file, so the math couldn't be unit tested without a database connection.
**Fix:** Extracted the scoring calculation into `webapp/lib/focus_coach_scoring.php` as a pure function (`csa_compute_category_score`), taking a data row, exam stats, and an injected `DateTime` instead of touching the DB or wall-clock time directly. `focus_coach.php` now just orchestrates (2 queries + a loop calling the function + `usort`). Added `tests/FocusCoachScoringTest.php` (8 tests, 28 assertions) covering: exam-driven vs. default accuracy scoring, the 2-rating confidence-gap threshold, both overconfidence and underconfidence detection, the notes-boost cap, a zero-questions divide-by-zero guard, and same-day-review edge cases.
**Why this approach:** Verified behavior-preservation before considering the refactor safe — ran the original inline logic (backed up before editing) against the new function on 6 fixtures via a throwaway comparison script; all matched exactly (`!==` check, zero mismatches) before any test was written against the new code. One real (pre-existing, not introduced) finding along the way: `knownPercent` returns `int 0` in the zero-questions branch but a rounded `float` otherwise — a minor type inconsistency, left as-is since fixing it wasn't in scope and it doesn't affect any current caller (JSON encoding doesn't distinguish `0` from `0.0`).

**Scope note:** This covers 1 of 33 API endpoints and none of the client-side JS (battle/drill/exam logic). See `CLAUDE.md`'s "Known Gaps" for what's still untested.
