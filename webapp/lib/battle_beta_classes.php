<?php

/**
 * Pure scoring/milestone logic for the Spellfire Beta's two playable
 * classes. Every effect here is deliberately self-targeted only (a
 * player's class only ever reads/modifies that same player's own score,
 * streak, and pending-effect flags) -- see migration_16.sql and
 * SOLUTIONS_LOG.md for why that constraint exists. Kept separate from
 * battle_common.php (the classic mode's speed/weight formula) since these
 * functions layer class modifiers on top of, not instead of, that formula.
 */

const BATTLE_BETA_OVERCLOCK_SECONDS = 1.5; // Speedster passive: answer this fast or faster for the bonus
const BATTLE_BETA_OVERCLOCK_BONUS = 3;
const BATTLE_BETA_MALWARE_PENALTY = 1; // Saboteur passive: flat deduction on their own correct answers
const BATTLE_BETA_HASTE_MULTIPLIER = 1.5;
const BATTLE_BETA_MILESTONE_TIERS = [10, 50, 75]; // first tier lowered from 25 -- was too slow to reach in real play

/**
 * Applies a class's passive plus any pending one-shot bonus to an already
 * speed/weight-scored correct answer. Never returns a negative value.
 *
 * @param int $maxWeightedPoints The full-credit value for this question (BATTLE_SPEED_MAX_POINTS * weight) -- what Overwrite grants regardless of actual speed.
 * @return array{points: int, bonusConsumed: bool}
 */
function csa_battle_beta_score_correct_answer(
    ?string $classKey,
    int $weightedPoints,
    float $secondsTaken,
    int $maxWeightedPoints,
    ?string $pendingBonus
): array {
    $points = $weightedPoints;

    if ($classKey === 'speedster' && $secondsTaken <= BATTLE_BETA_OVERCLOCK_SECONDS) {
        $points += BATTLE_BETA_OVERCLOCK_BONUS;
    }
    if ($classKey === 'saboteur') {
        $points = max(0, $points - BATTLE_BETA_MALWARE_PENALTY);
    }

    $bonusConsumed = false;
    if ($pendingBonus === 'haste') {
        $points = (int)round($points * BATTLE_BETA_HASTE_MULTIPLIER);
        $bonusConsumed = true;
    } elseif ($pendingBonus === 'overwrite') {
        $points = $maxWeightedPoints;
        $bonusConsumed = true;
    }

    return ['points' => max(0, $points), 'bonusConsumed' => $bonusConsumed];
}

/**
 * A wrong answer normally resets current_streak to 0. Saboteur's Corrupted
 * Cache (25-point unlock) spends one shield charge to preserve the streak
 * instead, for its first 2 uses after unlocking.
 *
 * @return array{preserveStreak: bool, remainingCharges: int}
 */
function csa_battle_beta_score_wrong_answer(int $shieldCharges): array
{
    if ($shieldCharges > 0) {
        return ['preserveStreak' => true, 'remainingCharges' => $shieldCharges - 1];
    }
    return ['preserveStreak' => false, 'remainingCharges' => 0];
}

/**
 * Checks every milestone tier the player's score just crossed (a single
 * large-enough answer could cross more than one at once) and accumulates
 * every newly-unlocked effect. Never re-fires a tier already passed
 * ($oldTier gates each check), so this is safe to call after every answer
 * regardless of how many times the score has changed.
 *
 * @return array{
 *   newTier: int,
 *   bonusPoints: int,
 *   setNextCorrectBonus: ?string,
 *   addShieldCharges: int,
 *   addExtraSeconds: int
 * }
 */
function csa_battle_beta_check_milestones(?string $classKey, int $oldTier, int $newScore): array
{
    $result = [
        'newTier' => $oldTier,
        'bonusPoints' => 0,
        'setNextCorrectBonus' => null,
        'addShieldCharges' => 0,
        'addExtraSeconds' => 0,
    ];

    foreach (BATTLE_BETA_MILESTONE_TIERS as $tier) {
        if ($oldTier >= $tier || $newScore < $tier) {
            continue;
        }
        $result['newTier'] = $tier;

        if ($classKey === 'speedster') {
            if ($tier === 50) {
                $result['setNextCorrectBonus'] = 'haste';
            } elseif ($tier === 75) {
                $result['bonusPoints'] += 8; // Hyper-Drive Burst: instant, not tied to the next answer
            }
        } elseif ($classKey === 'saboteur') {
            if ($tier === 10) {
                $result['addShieldCharges'] += 2; // Corrupted Cache
            } elseif ($tier === 50) {
                $result['addExtraSeconds'] += 3; // System Lag
            } elseif ($tier === 75) {
                $result['setNextCorrectBonus'] = 'overwrite';
            }
        }
    }

    return $result;
}

/**
 * Mana is cosmetic in this beta -- nothing spends it, it's purely a UI fill
 * animation for Speedster's Adrenaline Surge flavor. Saboteur's kit has no
 * mana interaction at all, so this is a no-op for any other class.
 */
function csa_battle_beta_mana_after_streak(?string $classKey, int $currentStreak, int $currentMana): int
{
    if ($classKey === 'speedster' && $currentStreak > 0 && $currentStreak % 2 === 0) {
        return min(100, $currentMana + 35);
    }
    return $currentMana;
}
