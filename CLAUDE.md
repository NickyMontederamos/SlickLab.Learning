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
As of 2026-07-18: **1 test file, 8 tests**, covering `webapp/lib/focus_coach_scoring.php` only (extracted from `webapp/api/focus_coach.php` specifically to make it testable without a live DB — see `SOLUTIONS_LOG.md`... not yet created, first real entry pending). Everything else in `webapp/api/` (32 other endpoints) and `webapp/assets/js/` (battle, drill, exam, flashcards logic) has **zero automated coverage** — all prior verification was manual browser testing in the session that built this app. Treat any `ci-baseline-guard` "regressions: 0" result as "no regressions detected in the ~3% of the app that has tests," not "the app is regression-free."

## Project Memory
- `.claude/ci-baseline.json` — current baseline (8/8 passing), not committed (no git repo yet — see below)

## Known Gaps
- **No git repository.** This project has been managed entirely through deploy zips, no version control. Not set up as part of building the test suite — that's a separate decision for the user to make explicitly.
- No tests for the other 32 API endpoints or any client-side JS logic (battle scoring, drill progress, exam timing).
- No CI pipeline — tests only run locally, on demand.
