<?php

/**
 * Extracted from api/exam_start.php so the question-count and timer-scaling
 * math is testable without a database. Mechanical extraction — logic
 * unchanged from the original inline version, only parameterized.
 *
 * Note: preserves the original's lack of a fullTotalQuestions === 0 guard —
 * see SOLUTIONS_LOG.md for why this is a known latent issue, not fixed here.
 *
 * @param int $requestedCount     Raw requested question count from the client.
 * @param int[] $allowedCounts    Valid choices, e.g. [25, 50, 100, 274].
 * @param int $defaultCount       Used when $requestedCount isn't in $allowedCounts.
 * @param int $fullTotalQuestions Total questions in the bank.
 * @param int $fullDurationSeconds Full exam duration (e.g. 5400 = 90 min) at $fullTotalQuestions.
 * @return array{count:int, durationSeconds:int}
 */
function csa_plan_exam(
    int $requestedCount,
    array $allowedCounts,
    int $defaultCount,
    int $fullTotalQuestions,
    int $fullDurationSeconds
): array {
    if (!in_array($requestedCount, $allowedCounts, true)) {
        $requestedCount = $defaultCount;
    }
    $count = min($requestedCount, $fullTotalQuestions);

    // Scale the timer proportionally to the full-length pace, rounded to the
    // nearest 30 seconds, with a 60-second floor so a tiny quiz never gets a
    // near-zero timer.
    $durationSeconds = (int)round(($fullDurationSeconds / $fullTotalQuestions) * $count / 30) * 30;
    $durationSeconds = max(60, $durationSeconds);

    return ['count' => $count, 'durationSeconds' => $durationSeconds];
}
