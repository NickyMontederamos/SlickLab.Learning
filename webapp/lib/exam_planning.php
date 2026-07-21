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
    $durationSeconds = csa_scale_exam_duration($fullDurationSeconds, $fullTotalQuestions, $count);

    return ['count' => $count, 'durationSeconds' => $durationSeconds];
}

/**
 * Scales the timer proportionally to the full-length pace, rounded to the
 * nearest 30 seconds, with a 60-second floor so a tiny quiz never gets a
 * near-zero timer. Split out of csa_plan_exam() so callers with a count that
 * isn't one of the preset allowedCounts (e.g. a mini-exam's fixed incorrect-
 * question set) can scale duration without going through count validation
 * meant for the preset-count picker.
 */
function csa_scale_exam_duration(int $fullDurationSeconds, int $fullTotalQuestions, int $count): int
{
    $durationSeconds = (int)round(($fullDurationSeconds / $fullTotalQuestions) * $count / 30) * 30;
    return max(60, $durationSeconds);
}
