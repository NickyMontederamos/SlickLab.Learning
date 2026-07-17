# Deploying CSA Prep to InfinityFree (slicklab.digital)

## 1. Create the database tables (via phpMyAdmin — no remote MySQL access)

InfinityFree does not allow MySQL connections from outside their network, so `sql/schema.sql`
must be imported through their web UI, not from your local machine.

1. Log into the InfinityFree client area > your hosting account > **MySQL Databases**.
2. Confirm the database `if0_37565517_csa` exists (create it if not).
3. Open **phpMyAdmin** for that database.
4. Go to the **Import** tab, choose `sql/schema.sql` from this project, and run the import.
5. Verify the 6 tables appear: `users`, `questions`, `options`, `flashcard_progress`,
   `exam_attempts`, `exam_answers`.

## 2. Upload the files (FTP)

Upload the **contents of the `webapp/` folder** (not the folder itself, and not the PDFs
sitting next to it) into your domain's `htdocs/` directory — or a subfolder like
`htdocs/csa-prep/` if you want it at `slicklab.digital/csa-prep/` instead of the domain root.

Use any FTP client (FileZilla, WinSCP) with the FTP credentials from the InfinityFree control
panel (separate from the MySQL credentials).

## 3. Switch to production database config

Locally, `config/config.php` points at the local dev database. Before/after uploading,
replace its contents with `config/config.production.php` (already filled in with your
InfinityFree MySQL host/user/password), OR just upload `config.production.php` and rename
it to `config.php` on the server via your FTP client.

**Do not** leave `config.production.php` sitting alongside `config.php` with real
credentials in a publicly-downloadable place other than your own host — both files execute
as PHP server-side so visiting them in a browser is safe (no source leaks), but don't commit
them to a public GitHub repo.

## 4. Seed the question bank

Visit (once):

```
https://slicklab.digital/seed.php?key=csa-seed-2026
```

(or whatever path you uploaded to, e.g. `https://slicklab.digital/csa-prep/seed.php?key=...`)

You should see `Seeded 274 questions with options.` This is safe to re-run — it wipes and
reloads `questions`, `options`, `flashcard_progress`, `exam_attempts`, and `exam_answers`
each time (user accounts are untouched).

**After confirming it worked, delete `seed.php` from the server** (or at least change the
`$SEED_KEY` value in it to something private) so nobody else can wipe your question bank.

## 5. Test on the live site

1. Visit the site, register a test account, confirm login works.
2. Open Flashcards, flip a card, mark Known/Review, reload the page, confirm it persisted.
3. Start the Mock Exam, answer a couple of questions, reload the page mid-exam, confirm the
   timer/answers resume correctly (not the local dev bug where it showed 569 minutes — that
   was fixed by forcing UTC on both PHP and the DB connection in `config/db.php`).
4. Submit the exam and confirm the score/review screen and Dashboard history update.

## Notes

- PHP requirements: PDO MySQL extension, `password_hash`/`password_verify` (PHP 7.2+).
  InfinityFree's default PHP version (selectable in their panel) supports this.
- Sessions: uses standard PHP sessions (cookie-based). No extra config needed.
- If you ever add more questions later, edit `data/questions.json` (or regenerate it) and
  re-run `seed.php` — it's idempotent.
