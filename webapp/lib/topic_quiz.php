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

/**
 * How many micro-blocks a topic's item pool is split into for the tiered
 * block pipeline. Below 10 items there isn't enough content to test in
 * blocks at all -- those topics use the Self-Directed Instance-Lab track
 * instead (0 signals "not block-based, use the lab track").
 *
 * @param int $poolSize Number of questions in the topic's own pool.
 * @return int Block count (2-6), or 0 for a thin/lab-track topic.
 */
function csa_compute_block_count(int $poolSize): int
{
    if ($poolSize < 10) {
        return 0;
    }
    if ($poolSize < 15) {
        return 2;
    }
    if ($poolSize < 20) {
        return 3;
    }
    if ($poolSize < 30) {
        return 4;
    }
    return $poolSize >= 36 ? 6 : 5;
}

/**
 * Same chain-safe reasoning as csa_compute_unlocked_topics(), one level
 * down: which block of a topic the user should attempt next. No stored
 * "current block" column -- derived fresh from which block_number values
 * have a passing 'topic_block' attempt, same as everything else in this
 * pipeline.
 *
 * @param int   $totalBlocks      From csa_compute_block_count().
 * @param array $passedBlockNumbers Block numbers (1-indexed) the user has passed.
 * @return int The next block to attempt (1-indexed), or $totalBlocks + 1
 *             once every block is passed -- callers treat that sentinel as
 *             "all blocks cleared, the Gate Check is unlocked."
 */
function csa_compute_current_block(int $totalBlocks, array $passedBlockNumbers): int
{
    for ($block = 1; $block <= $totalBlocks; $block++) {
        if (!in_array($block, $passedBlockNumbers, true)) {
            return $block;
        }
    }
    return $totalBlocks + 1;
}

/**
 * Splits an ordered list of question IDs into $totalBlocks roughly-equal
 * chunks and returns the 1-indexed $blockNumber'th chunk. Block-to-question
 * mapping is never stored -- every question belongs to exactly one block,
 * computed fresh from the pool itself each time.
 *
 * @param array $orderedQuestionIds A topic's question IDs in a stable order.
 * @param int   $totalBlocks        From csa_compute_block_count().
 * @param int   $blockNumber        1-indexed block to return.
 * @return array The question IDs belonging to that block.
 */
function csa_slice_block_questions(array $orderedQuestionIds, int $totalBlocks, int $blockNumber): array
{
    if ($totalBlocks <= 0 || $blockNumber < 1 || $blockNumber > $totalBlocks) {
        return [];
    }
    $total = count($orderedQuestionIds);
    $baseSize = intdiv($total, $totalBlocks);
    $remainder = $total % $totalBlocks;

    // The first $remainder blocks absorb one extra item each, so a pool
    // that doesn't divide evenly still uses every question exactly once
    // instead of leaving a short last block.
    $start = 0;
    for ($b = 1; $b < $blockNumber; $b++) {
        $start += $baseSize + ($b <= $remainder ? 1 : 0);
    }
    $thisSize = $baseSize + ($blockNumber <= $remainder ? 1 : 0);
    return array_slice($orderedQuestionIds, $start, $thisSize);
}
