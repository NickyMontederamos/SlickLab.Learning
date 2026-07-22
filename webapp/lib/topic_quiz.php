<?php

/**
 * Builds the question-ID selection for a topic quiz: mostly fresh questions
 * from the topic's own pool, with 1-2 slots reserved for questions from the
 * cross-topic revision pool (previously missed, not yet Leitner "known").
 * Deliberately deterministic given deterministic inputs — the caller passes
 * already-shuffled arrays and does a final shuffle() of the combined result
 * afterward (same split as exam_start.php: randomness stays in the endpoint,
 * selection logic stays pure and testable).
 */

/**
 * @param array $shuffledFreshIds    This topic's own question pool, already shuffled.
 * @param array $shuffledRevisionIds Cross-topic revision-pool question IDs, already shuffled.
 * @param int   $quizSize            Target question count for the quiz.
 * @param int   $revisionSlots       Max revision-pool questions to mix in.
 * @return array{questionIds: array, freshCount: int, revisionCount: int}
 */
function csa_build_topic_quiz_selection(
    array $shuffledFreshIds,
    array $shuffledRevisionIds,
    int $quizSize,
    int $revisionSlots = 2
): array {
    $revisionCandidates = array_values(array_unique($shuffledRevisionIds));
    $revisionPicks = array_slice($revisionCandidates, 0, min($revisionSlots, count($revisionCandidates)));

    // A question already picked for a revision slot is never also drawn as
    // "fresh", even if it happens to belong to this same topic's pool.
    $freshCandidates = array_values(array_diff(array_unique($shuffledFreshIds), $revisionPicks));
    $freshSlotsNeeded = max(0, $quizSize - count($revisionPicks));
    $freshPicks = array_slice($freshCandidates, 0, min($freshSlotsNeeded, count($freshCandidates)));

    return [
        // A thin topic pool can legitimately return fewer than $quizSize
        // total IDs -- that's surfaced here, not silently padded or errored.
        'questionIds' => array_merge($revisionPicks, $freshPicks),
        'freshCount' => count($freshPicks),
        'revisionCount' => count($revisionPicks),
    ];
}

/**
 * Topic 1 is always unlocked; topic N unlocks once the user has a passing
 * 'topic' attempt for topic N-1. No stored "unlocked" column -- this is
 * derived fresh every time from pass/fail history, same reasoning as every
 * other derived-not-stored state in this app (see incorrect_review.php).
 *
 * @param array $topicIdsBySortOrder Topic IDs, already ordered by sort_order ascending.
 * @param array $passedTopicIds      Topic IDs the user has at least one passing 'topic' attempt for.
 * @return array<int,bool> topicId => unlocked
 */
function csa_compute_unlocked_topics(array $topicIdsBySortOrder, array $passedTopicIds): array
{
    $unlocked = [];
    $chainOpen = true; // topic 1 always unlocked
    foreach ($topicIdsBySortOrder as $topicId) {
        $unlocked[$topicId] = $chainOpen;
        // A pass only propagates the chain forward if the topic it was
        // scored on was itself reachable -- a stale/anomalous pass on a
        // still-locked topic must not unlock the one after it.
        $chainOpen = $chainOpen && in_array($topicId, $passedTopicIds, true);
    }
    return $unlocked;
}
