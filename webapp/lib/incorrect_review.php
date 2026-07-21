<?php

/**
 * Computes readiness for the mini-exam gate: what fraction of the
 * incorrect-review question set the user has reached "known" status on
 * (box >= 2 in the Leitner algorithm -- the same definition already used by
 * the "Known" flashcard filter and Focus Coach's mastery score, chosen
 * specifically so this doesn't invent a new, parallel "did they click Good
 * this session" concept).
 *
 * @param string[] $statuses One flashcard_progress.status value per
 *                            incorrect question ('known'/'review'/'unseen').
 *                            A question never reviewed should be passed as
 *                            'unseen', not omitted -- omitting it would
 *                            silently shrink the denominator and inflate
 *                            the readiness rate.
 */
function csa_compute_review_readiness(array $statuses, float $threshold = 0.8): array
{
    $total = count($statuses);
    if ($total === 0) {
        return ['total' => 0, 'knownCount' => 0, 'knownRate' => 0.0, 'ready' => false];
    }
    $knownCount = count(array_filter($statuses, fn($s) => $s === 'known'));
    $knownRate = round($knownCount / $total, 4);
    return [
        'total' => $total,
        'knownCount' => $knownCount,
        'knownRate' => $knownRate,
        'ready' => $knownRate >= $threshold,
    ];
}

/**
 * Mini-exam attempts use a stricter pass bar than the full exam -- they're
 * a readiness gate over material the user has already reviewed once, not a
 * first pass through the whole bank.
 */
function csa_pass_percent_for_kind(string $attemptKind, float $fullPassPercent, float $miniPassPercent): float
{
    return $attemptKind === 'mini' ? $miniPassPercent : $fullPassPercent;
}
