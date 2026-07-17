# CSA Prep

Live web app (flashcards, Mock Exams, Focus Coach, Rapid Drill) for CSA certification study. Plain PHP + HTML + vanilla JS + MySQL, deployed manually to InfinityFree via zip upload + phpMyAdmin migrations (see `webapp/DEPLOY.md`). No framework, no Composer, no npm.

## Local Toolchain
Nothing is on PATH — use full paths:
- PHP: `C:\Users\ndmon\tools\php\php.exe` (8.3.32, portable install; `mbstring` extension enabled 2026-07-18, was off by default)
- MariaDB: `C:\Users\ndmon\tools\mariadb\` (portable, used for local dev DB — not yet wired into the test suite)
- PHPUnit: `C:\Users\ndmon\tools\phpunit\phpunit.phar` (11.5.56, standalone — no Composer on this machine)

## CI Commands
- Install: n/a (no package manager; PHPUnit is the standalone phar above)
- Test: `"C:\Users\ndmon\tools\php\php.exe" "C:\Users\ndmon\tools\phpunit\phpunit.phar"` (run from repo root, reads `phpunit.xml`)
- Lint: `"C:\Users\ndmon\tools\php\php.exe" -l <file>` (per-file syntax check only, no style linter configured)
- Build: n/a (no build step; deploy zips are assembled manually per `webapp/DEPLOY.md`)

## Test Suite Status
As of 2026-07-18: **2 test files, 22 tests**, covering:
- `webapp/lib/focus_coach_scoring.php` (extracted from `webapp/api/focus_coach.php`) — priority scoring, confidence-gap detection.
- `webapp/lib/exam_grading.php` (extracted from `webapp/api/exam_submit.php`) — multi-select answer grading, score/pass-percent calculation.

Everything else in `webapp/api/` (31 other endpoints) and `webapp/assets/js/` (battle, drill, exam, flashcards logic) has **zero automated coverage** — all prior verification was manual browser testing in the session that built this app. Treat any `ci-baseline-guard` "regressions: 0" result as "no regressions detected in the ~6% of the app that has tests," not "the app is regression-free."

## Project Memory
- `.claude/ci-baseline.json` — current baseline (22/22 passing), gitignored (regenerate via `ci-baseline-capture`, don't hand-edit)

## Known Gaps
- No tests for the other 31 API endpoints or any client-side JS logic (battle scoring, drill progress, exam timing).
- No CI pipeline — tests only run locally, on demand.
