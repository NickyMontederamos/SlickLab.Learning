<?php

/**
 * Extracted from api/exam_submit.php so grading logic is testable without a
 * live database connection. Mechanical extraction — logic unchanged from the
 * original inline version, only parameterized.
 */

/**
 * Normalizes a raw submitted answer (possibly containing duplicate, unsorted,
 * or mixed-type letters, or not even an array at all if the client sent
 * something malformed) into a sorted, deduplicated array of strings, ready
 * to compare against the correct-answer key.
 */
function csa_normalize_selected_letters($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $letters = array_values(array_unique(array_map('strval', $raw)));
    sort($letters);
    return $letters;
}

/**
 * A question is correct only if the selected letters exactly match the full
 * set of correct letters — multi-select questions require every correct
 * option checked and no incorrect ones checked. Order-independent.
 *
 * @param array $normalizedSelected Already normalized via csa_normalize_selected_letters().
 * @param array $correctLetters     Raw correct-letter list, not required to be pre-sorted.
 */
function csa_is_answer_correct(array $normalizedSelected, array $correctLetters): bool
{
    $correctSorted = $correctLetters;
    sort($correctSorted);
    return $normalizedSelected === $correctSorted;
}

/**
 * @return array{scorePercent: float, passed: bool}
 */
function csa_compute_exam_score(int $correctCount, int $total, float $passPercent): array
{
    $scorePercent = $total > 0 ? round(($correctCount / $total) * 100, 2) : 0.0;
    return [
        'scorePercent' => $scorePercent,
        'passed' => $scorePercent >= $passPercent,
    ];
}
