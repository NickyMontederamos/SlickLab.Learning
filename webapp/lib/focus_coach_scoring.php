<?php

/**
 * Extracted from api/focus_coach.php so the scoring math is testable without
 * a live database connection. This is a mechanical extraction — the logic
 * below is unchanged from the original inline version, only parameterized.
 *
 * @param array         $row  One row from the flashcard-progress-per-category
 *                            query: category, totalQuestions, knownCount,
 *                            reviewCount, unseenCount, lastReviewedAt,
 *                            notesCount, avgConfidence, confidenceCount.
 * @param array|null    $exam ['total' => int, 'correct' => int] for this
 *                            category from the exam-accuracy query, or null
 *                            if the category has no completed exam attempts.
 * @param DateTime      $now  Injected so "days since review" is deterministic
 *                            in tests.
 */
function csa_compute_category_score(array $row, ?array $exam, DateTime $now): array
{
    $category = $row['category'];
    $totalQuestions = (int)$row['totalQuestions'];
    $knownCount = (int)$row['knownCount'];
    $reviewCount = (int)$row['reviewCount'];
    $unseenCount = (int)$row['unseenCount'];
    $notesCount = (int)$row['notesCount'];
    $knownPercent = $totalQuestions > 0 ? round(($knownCount / $totalQuestions) * 100, 1) : 0;

    $examTotal = $exam['total'] ?? 0;
    $examCorrect = $exam['correct'] ?? 0;
    $examPercent = $examTotal > 0 ? round(($examCorrect / $examTotal) * 100, 1) : null;

    $daysSinceReview = null;
    if ($row['lastReviewedAt']) {
        $last = new DateTime($row['lastReviewedAt']);
        $daysSinceReview = (int)floor(($now->getTimestamp() - $last->getTimestamp()) / 86400);
    }

    // Self-rated confidence (1-5) vs. actual performance: a "confidence gap" is
    // the psychologically dangerous case of feeling sure but being wrong (or,
    // in the other direction, doubting yourself despite solid results). Only
    // trust this signal once there are at least 2 ratings, to avoid noise from
    // a single flick of the slider.
    $confidenceCount = (int)$row['confidenceCount'];
    $avgConfidence = $row['avgConfidence'] !== null ? round((float)$row['avgConfidence'], 2) : null;
    $confidenceGap = null;
    $confidenceBoost = 0;
    if ($confidenceCount >= 2 && $avgConfidence !== null) {
        $confidencePercent = (($avgConfidence - 1) / 4) * 100;
        $actualPercent = $examPercent !== null ? $examPercent : $knownPercent;
        $confidenceGap = round($confidencePercent - $actualPercent, 1);
    }

    // Deterministic weighting: exam accuracy matters most (it's the real test),
    // then flashcard mastery, then staleness, then small nudges for a flagged
    // note or a detected overconfidence gap.
    $accuracyScore = $examPercent !== null ? (100 - $examPercent) : 50;
    $masteryScore = 100 - $knownPercent;
    $recencyScore = $daysSinceReview === null ? 100 : min(100, $daysSinceReview * 5);
    $noteBoost = min(20, $notesCount * 7);
    if ($confidenceGap !== null && $confidenceGap > 15) {
        $confidenceBoost = min(15, round($confidenceGap / 100 * 15, 1));
    }

    $priorityScore = round(
        0.40 * $accuracyScore +
        0.35 * $masteryScore +
        0.15 * $recencyScore +
        0.10 * $noteBoost +
        $confidenceBoost,
        1
    );

    $reasons = [];
    if ($examPercent !== null) {
        $reasons[] = "{$examPercent}% correct on Mock Exam ({$examTotal} question" . ($examTotal === 1 ? '' : 's') . " seen)";
    } else {
        $reasons[] = 'Not yet tested on the Mock Exam';
    }
    $notMastered = $reviewCount + $unseenCount;
    if ($notMastered > 0) {
        $reasons[] = "{$notMastered} of {$totalQuestions} flashcard(s) not yet mastered";
    }
    if ($daysSinceReview === null) {
        $reasons[] = 'Never reviewed in Flashcards';
    } elseif ($daysSinceReview >= 1) {
        $reasons[] = "Last reviewed {$daysSinceReview} day" . ($daysSinceReview === 1 ? '' : 's') . ' ago';
    }
    if ($notesCount > 0) {
        $reasons[] = "{$notesCount} personal note" . ($notesCount === 1 ? '' : 's') . ' flagged here';
    }
    if ($confidenceGap !== null && $confidenceGap > 15) {
        $reasons[] = '⚠ You rate yourself confident here, but results say otherwise — possible overconfidence trap';
    } elseif ($confidenceGap !== null && $confidenceGap < -15) {
        $reasons[] = "You know this better than you feel — low self-rated confidence despite solid results";
    }

    return [
        'category' => $category,
        'totalQuestions' => $totalQuestions,
        'knownCount' => $knownCount,
        'reviewCount' => $reviewCount,
        'unseenCount' => $unseenCount,
        'knownPercent' => $knownPercent,
        'examTotal' => $examTotal,
        'examCorrect' => $examCorrect,
        'examPercent' => $examPercent,
        'daysSinceReview' => $daysSinceReview,
        'notesCount' => $notesCount,
        'avgConfidence' => $avgConfidence,
        'confidenceGap' => $confidenceGap,
        'priorityScore' => $priorityScore,
        'reasons' => $reasons,
    ];
}
