<?php

/**
 * Blends Mock Exam performance, Topics-pipeline progress, and Quiz Battle
 * activity into one Rank/Points score for the team leaderboard. Topics
 * mastered carries the heaviest weight since it's the most granular signal
 * of real study progress; Mock Exam score is capped at 100 (the percentage
 * itself is already 0-100); battle points reward both playing and winning
 * so participation isn't worthless next to one lucky top score.
 */

function csa_compute_leaderboard_points(array $stats): int
{
    $topicsMastered = $stats['topicsMastered'] ?? 0;
    $bestExamPercent = $stats['bestExamPercent'] ?? null;
    $battleWins = $stats['battleWins'] ?? 0;
    $battlesPlayed = $stats['battlesPlayed'] ?? 0;

    $examPoints = $bestExamPercent !== null ? (int)round($bestExamPercent) : 0;

    return ($topicsMastered * 15) + $examPoints + ($battleWins * 8) + ($battlesPlayed * 2);
}

/**
 * `tier` is a slug the frontend maps to an inline-SVG icon
 * (webapp/assets/js/lib/rank-icons.js's RankIcons.medal()) instead of an
 * emoji -- kept as a plain machine-readable key here rather than baking any
 * presentation into the API response.
 *
 * @return array{label: string, tier: string}
 */
function csa_rank_for_points(int $points): array
{
    $tiers = [
        ['min' => 550, 'label' => 'Diamond', 'tier' => 'diamond'],
        ['min' => 400, 'label' => 'Platinum', 'tier' => 'platinum'],
        ['min' => 250, 'label' => 'Gold', 'tier' => 'gold'],
        ['min' => 100, 'label' => 'Silver', 'tier' => 'silver'],
        ['min' => 0, 'label' => 'Bronze', 'tier' => 'bronze'],
    ];
    foreach ($tiers as $tier) {
        if ($points >= $tier['min']) {
            return ['label' => $tier['label'], 'tier' => $tier['tier']];
        }
    }
    // Unreachable: the last tier's min is 0, so the loop above always matches.
    return ['label' => 'Bronze', 'tier' => 'bronze'];
}
