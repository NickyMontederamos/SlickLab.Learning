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
 * @return array{label: string, emoji: string}
 */
function csa_rank_for_points(int $points): array
{
    $tiers = [
        ['min' => 550, 'label' => 'Diamond', 'emoji' => '💎'],
        ['min' => 400, 'label' => 'Platinum', 'emoji' => '🌟'],
        ['min' => 250, 'label' => 'Gold', 'emoji' => '🥇'],
        ['min' => 100, 'label' => 'Silver', 'emoji' => '🥈'],
        ['min' => 0, 'label' => 'Bronze', 'emoji' => '🥉'],
    ];
    foreach ($tiers as $tier) {
        if ($points >= $tier['min']) {
            return ['label' => $tier['label'], 'emoji' => $tier['emoji']];
        }
    }
    // Unreachable: the last tier's min is 0, so the loop above always matches.
    return ['label' => 'Bronze', 'emoji' => '🥉'];
}
