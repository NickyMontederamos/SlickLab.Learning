# CSA Prep

Live web app (flashcards, Mock Exams, Focus Coach, Rapid Drill) for CSA certification study. Plain PHP + HTML + vanilla JS + MySQL, deployed manually to InfinityFree via zip upload + phpMyAdmin migrations (see `webapp/DEPLOY.md`). No framework, no Composer, no npm/package.json.

## Local Toolchain
Nothing is on PATH — use full paths:
- PHP: `C:\Users\ndmon\tools\php\php.exe` (8.3.32, portable install; `mbstring` extension enabled 2026-07-18, was off by default)
- MariaDB: `C:\Users\ndmon\tools\mariadb\` (portable, used for local dev DB — not yet wired into the test suite)
- PHPUnit: `C:\Users\ndmon\tools\phpunit\phpunit.phar` (11.5.56, standalone — no Composer on this machine)
- Node: on PATH already (v24.15.0). JS tests use Node's **built-in** test runner (`node:test` + `node:assert`) — no npm install, no package.json, deliberately zero new dependencies for a project that otherwise has none.

## CI Commands
- Install: n/a (no package manager; PHPUnit is the standalone phar above, JS tests use only Node built-ins)
- Test (PHP): `"C:\Users\ndmon\tools\php\php.exe" "C:\Users\ndmon\tools\phpunit\phpunit.phar"` (run from repo root, reads `phpunit.xml`)
- Test (JS): `node --test "tests/js/**/*.test.js"` (run from repo root — a bare directory path like `tests/js` or `tests/js/` does **not** work with this Node version's `--test`, must be a glob or explicit file list)
- Lint: `"C:\Users\ndmon\tools\php\php.exe" -l <file>` (PHP syntax check only) / `node --check <file>` (JS syntax check only) — no style linter configured for either
- Build: n/a (no build step; deploy zips are assembled manually per `webapp/DEPLOY.md`)

## Test Suite Status
As of 2026-07-18: **5 test files, 49 tests** (38 PHP + 11 JS), covering:
- `webapp/lib/focus_coach_scoring.php` (from `api/focus_coach.php`) — priority scoring, confidence-gap detection.
- `webapp/lib/exam_grading.php` (from `api/exam_submit.php`) — multi-select answer grading, score/pass-percent calculation.
- `webapp/lib/flashcard_scheduling.php` (from `api/flashcard_progress.php`) — Leitner-box spaced-repetition scheduling.
- `webapp/lib/exam_planning.php` (from `api/exam_start.php`) — question-count validation, proportional exam-timer scaling.
- `webapp/assets/js/lib/drill-timing.js` (from `assets/js/flashcards.js`'s Rapid Drill mode) — progress-tick math, card-index wraparound.

Everything else in `webapp/api/` (29 other endpoints — battle mode, auth, leaderboard, etc.) and the rest of `webapp/assets/js/` (battle.js, exam.js timer display, match-game logic) has **zero automated coverage** — all prior verification of those was manual browser testing. Treat `ci-baseline-guard` "0 regressions" as "0 regressions in the ~15% of the app that has tests," not "the app is regression-free."

## Project Memory
- `.claude/ci-baseline.json` — current baseline (49/49 passing), gitignored (regenerate via `ci-baseline-capture`, don't hand-edit). Schema now has `commands: {php, js}` (plural) instead of a single `command` string, since this project has two stacks.

## Known Gaps
- No tests for the other 29 API endpoints or most client-side JS (battle scoring, exam countdown display, flashcard match-game).
- No CI pipeline — tests only run locally, on demand.
- One known-but-unfixed latent defect: `csa_plan_exam()` in `webapp/lib/exam_planning.php` divides by the question-bank total with no zero-guard (would throw `DivisionByZeroError` if the bank were ever empty — not reachable today, bank always has 274 seeded questions). See `SOLUTIONS_LOG.md`.
