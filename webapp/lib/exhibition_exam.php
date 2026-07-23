<?php

require_once __DIR__ . '/topic_quiz.php';

/**
 * Pure functions for the Exhibition Exam feature: tallying candidate-topic
 * votes into a winning set, deciding whether a session has timed out, and
 * computing the winner once one closes. No DB access -- see
 * api/exhibition_*.php for the orchestration that calls these.
 */

/**
 * How many distinct voters a topic needs to clear the majority bar.
 */
function csa_exhibition_vote_threshold(int $participantCount): int
{
    return (int)ceil($participantCount / 2);
}

/**
 * Ranks every candidate topic by [vote count desc, sort_order asc] (a
 * deterministic tie-break, same reasoning as topic_quiz.php's sort_order
 * ordering), keeps every topic that cleared a majority of participants, and
 * falls back to the top 2 highest-voted candidates outright if fewer than 2
 * cleared that bar -- guarantees the "2+ topics" minimum always resolves,
 * per the agreed spec.
 *
 * @param array<int,int> $voteCountsByTopic Every candidate topic ID => its
 *                        distinct-voter count (0 is valid and expected for
 *                        a candidate nobody has voted for yet -- omitting a
 *                        candidate here would silently drop it from the
 *                        top-2 fallback pool).
 * @param array<int,int> $sortOrderByTopic  Topic ID => sort_order, for the
 *                        tie-break.
 * @return int[] Winning topic IDs, ranked (highest-ranked first).
 */
function csa_tally_exhibition_votes(array $voteCountsByTopic, array $sortOrderByTopic, int $participantCount): array
{
    $candidates = array_keys($voteCountsByTopic);
    usort($candidates, function ($a, $b) use ($voteCountsByTopic, $sortOrderByTopic) {
        $countCmp = $voteCountsByTopic[$b] <=> $voteCountsByTopic[$a];
        if ($countCmp !== 0) {
            return $countCmp;
        }
        return ($sortOrderByTopic[$a] ?? 0) <=> ($sortOrderByTopic[$b] ?? 0);
    });

    $threshold = csa_exhibition_vote_threshold($participantCount);
    $winners = array_values(array_filter($candidates, fn($id) => $voteCountsByTopic[$id] >= $threshold));

    if (count($winners) < 2) {
        $winners = array_slice($candidates, 0, min(2, count($candidates)));
    }

    return $winners;
}

/**
 * Unions and dedupes the winning topics' own question pools into one flat
 * list, preserving first-seen order (the caller shuffles afterward, same
 * split as exam_start.php: randomness stays in the endpoint).
 *
 * @param array<int,int[]> $questionIdsByTopic Winning topic ID => its question IDs.
 * @return int[]
 */
function csa_union_exhibition_question_pools(array $questionIdsByTopic): array
{
    $merged = [];
    foreach ($questionIdsByTopic as $ids) {
        $merged = array_merge($merged, $ids);
    }
    return array_values(array_unique($merged));
}

/**
 * A session is expired once its 24h window has elapsed -- checked on every
 * read (lobby state, start-attempt, close) rather than via a cron job, same
 * "cron-less lazy expiry" approach as everywhere else this app runs on
 * shared hosting with no background job runner.
 */
function csa_exhibition_is_expired(?string $closesAt, DateTimeInterface $now): bool
{
    if ($closesAt === null) {
        return false;
    }
    return new DateTime($closesAt) <= $now;
}

/**
 * Winner = highest correct_count, tiebreak lowest duration_seconds --
 * equivalent to ranking by score_percent since every attempt in a session
 * answers the identical shared question set. Returns null (discard the
 * session entirely, no leaderboard credit) unless at least 2 *distinct*
 * users actually completed an attempt -- a session that stayed solo never
 * produces a winner, per the agreed spec.
 *
 * @param array<int,array{userId:int,correctCount:int,durationSeconds:int}> $attempts
 *        One row per completed 'custom' attempt tied to the session.
 */
function csa_compute_exhibition_winner(array $attempts): ?int
{
    $distinctUsers = array_unique(array_map(fn($a) => $a['userId'], $attempts));
    if (count($distinctUsers) < 2) {
        return null;
    }

    $best = null;
    foreach ($attempts as $a) {
        if (
            $best === null
            || $a['correctCount'] > $best['correctCount']
            || ($a['correctCount'] === $best['correctCount'] && $a['durationSeconds'] < $best['durationSeconds'])
        ) {
            $best = $a;
        }
    }

    return $best['userId'];
}

/**
 * Shared by exhibition_create.php and exhibition_vote.php -- both need to
 * reject a topic the requesting user hasn't personally unlocked. Thin
 * wrapper around topic_quiz.php's csa_compute_unlocked_topics() so both
 * endpoints query it the same way instead of duplicating the two queries
 * it needs (topic order + this user's passed topics).
 */
function csa_exhibition_unlocked_topic_ids(PDO $pdo, int $uid): array
{
    $topicIdsSorted = $pdo->query('SELECT id FROM topics ORDER BY sort_order')->fetchAll(PDO::FETCH_COLUMN);
    $topicIdsSorted = array_map('intval', $topicIdsSorted);

    $passedStmt = $pdo->prepare(
        "SELECT DISTINCT topic_id FROM exam_attempts WHERE user_id = ? AND attempt_kind = 'topic' AND passed = 1 AND topic_id IS NOT NULL"
    );
    $passedStmt->execute([$uid]);
    $passedTopicIds = array_map('intval', $passedStmt->fetchAll(PDO::FETCH_COLUMN));

    return csa_compute_unlocked_topics($topicIdsSorted, $passedTopicIds);
}

/**
 * Lazy-expiry check, called from every read path that touches a session
 * (lobby state, start-attempt, close) instead of a cron job. Flips
 * 'open' -> 'closed' in the DB if the 24h window has passed; returns the
 * (possibly updated) session row so the caller doesn't need a second query.
 *
 * Compares against PHP's `new DateTime()` directly (not a `SELECT NOW()`
 * round-trip) -- same convention exam_active.php already relies on
 * (`time() - strtotime($attempt['started_at'])`), safe because
 * config/db.php pins both sides to UTC: `date_default_timezone_set('UTC')`
 * for PHP, and `csa_db()` runs `SET time_zone = '+00:00'` on every
 * connection so MySQL's NOW()/CURRENT_TIMESTAMP write UTC too. (A live
 * check during this feature's build seemed to show an 8h-stale bug here --
 * it turned out to be an artifact of writing a test closes_at value
 * through a raw mysql CLI client, which used *its own* session's local
 * timezone instead of csa_db()'s forced UTC -- not a real bug in this
 * function or in the app's actual request path.)
 */
function csa_exhibition_maybe_autoclose(PDO $pdo, array $session): array
{
    if ($session['status'] === 'open' && csa_exhibition_is_expired($session['closes_at'], new DateTime())) {
        $pdo->prepare("UPDATE exhibition_sessions SET status = 'closed', closed_at = NOW() WHERE id = ? AND status = 'open'")
            ->execute([$session['id']]);
        $session['status'] = 'closed';
    }
    return $session;
}

/**
 * Looks up every completed 'custom' attempt tied to a session, runs
 * csa_compute_exhibition_winner() over them, and shapes the result for
 * display. Shared by exhibition_close.php (immediate display right after
 * closing) and leaderboard.php (re-derived at read time, same "no
 * winner-flag column" reasoning as migration_17.sql -- there is nothing to
 * go stale). Returns null both when the session stayed solo and when
 * nobody has completed an attempt yet.
 */
function csa_exhibition_compute_session_winner(PDO $pdo, int $sessionId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT user_id, correct_count, score_percent, duration_seconds,
                TIMESTAMPDIFF(SECOND, started_at, submitted_at) AS took_seconds
         FROM exam_attempts
         WHERE exhibition_session_id = ? AND attempt_kind = 'custom' AND status = 'completed'"
    );
    $stmt->execute([$sessionId]);
    $rows = $stmt->fetchAll();

    $attempts = array_map(fn($r) => [
        'userId' => (int)$r['user_id'],
        'correctCount' => (int)$r['correct_count'],
        // TIMESTAMPDIFF, not the stored duration_seconds allotment -- the
        // tiebreak is "who finished faster", not "who got a shorter timer".
        'durationSeconds' => (int)$r['took_seconds'],
    ], $rows);

    $winnerUserId = csa_compute_exhibition_winner($attempts);
    if ($winnerUserId === null) {
        return null;
    }

    $winnerRow = null;
    foreach ($rows as $r) {
        if ((int)$r['user_id'] === $winnerUserId) {
            $winnerRow = $r;
            break;
        }
    }

    $userStmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $userStmt->execute([$winnerUserId]);

    return [
        'userId' => $winnerUserId,
        'username' => $userStmt->fetchColumn(),
        'correctCount' => (int)$winnerRow['correct_count'],
        'scorePercent' => (float)$winnerRow['score_percent'],
    ];
}
