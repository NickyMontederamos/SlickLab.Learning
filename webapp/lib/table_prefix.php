<?php

/**
 * The full set of table names this app uses. Kept as an explicit list
 * (not derived from the DB) so prefixing is a pure, testable string
 * operation with no DB dependency and no risk of prefixing something
 * that only coincidentally looks like a table name.
 */
const CSA_TABLES = [
    'users',
    'questions',
    'options',
    'flashcard_progress',
    'exam_attempts',
    'exam_answers',
    'battle_rooms',
    'battle_participants',
    'battle_answers',
    'battle_reactions',
];

/**
 * Rewrites every whole-word occurrence of a known table name in $sql to
 * $prefix + that name. Word-boundary matching means it will not touch
 * column names that merely start with a table name (e.g. "question_id"
 * is untouched by the "questions" rule, since "question" != "questions"
 * and \b requires an exact word match either way).
 *
 * Returns $sql unchanged when $prefix is '' (the default for production/
 * local configs, which don't set table_prefix at all) — this function is
 * a no-op until a project's config explicitly opts into prefixing.
 */
function csa_prefix_tables(string $sql, string $prefix): string
{
    if ($prefix === '') {
        return $sql;
    }
    foreach (CSA_TABLES as $table) {
        $sql = preg_replace('/\b' . preg_quote($table, '/') . '\b/', $prefix . $table, $sql);
    }
    return $sql;
}
