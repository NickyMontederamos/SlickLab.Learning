---
name: bounded-refinement-loop
description: Orchestrate one bounded cycle of test-run, regression-fix, and logging for a CI/CD refinement pass. Meant to be invoked on a schedule or via /loop, not run indefinitely in one sitting. Trigger with "run refinement cycle", "ci refinement pass".
---

# bounded-refinement-loop

## Instructions
1. Run `ci-baseline-guard` first. If no regressions and no failing tests, report "clean" and stop — do not invent work.
2. If regressions exist, fix at most 3 per cycle, in order of: (a) regressions with a clear, localized root cause, (b) regressions blocking other tests, (c) everything else. Skip anything requiring a schema/API/dependency change without explicit user sign-off first.
3. Hard cap: 5 fix attempts per regression. If unresolved after 5 attempts, stop working that issue, log it via `solutions-log` as "unresolved — needs human input" with what was tried and why it didn't work, and move on. Never loop indefinitely on a single failure.
4. After each fix, re-run only the affected test(s) to confirm before moving to the next regression — don't batch unverified fixes.
5. Hard limits, non-negotiable regardless of how this skill is invoked (scheduled, looped, or manual):
   - Never commit directly to main/master. Work on a branch named `ci-refinement/<date>`.
   - Never force-push, never push to a branch you didn't create this run.
   - Never auto-merge a PR.
   - Never touch CI/CD pipeline config, secrets, credentials, or dependency versions with security implications (e.g. auth libraries) without explicit user confirmation first.
   - Cap total diff size per cycle at roughly 200 changed lines; if a fix needs more than that, stop, log why, and leave it for human review rather than pushing a large autonomous change.
6. At the end of the cycle: run `solutions-log` for anything fixed, then hand off to `pr-description-sync` if there are committed changes on the branch. Report a summary: regressions found, fixed, unresolved, and the branch name.
7. If this is running unattended (via schedule), do not wait for confirmation mid-cycle — but everything in step 5 still applies exactly as written, and the cycle must end with a report, never with a merge or a push to a protected branch.
8. "Self-upgrade" in this loop means: after a cycle, reflect on what worked/what didn't in `solutions-log`, and if a real friction point was found, propose and draft a new project skill to address it — not autonomous goal generation. Only add a new skill when a concrete, observed friction point motivates it; don't invent skills speculatively.
