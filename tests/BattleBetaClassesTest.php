<?php

use PHPUnit\Framework\TestCase;

final class BattleBetaClassesTest extends TestCase
{
    // --- csa_battle_beta_score_correct_answer ---

    public function testNoClassNoBonusPassesPointsThrough(): void
    {
        $r = csa_battle_beta_score_correct_answer(null, 10, 3.0, 10, null);
        $this->assertSame(10, $r['points']);
        $this->assertFalse($r['bonusConsumed']);
    }

    public function testSpeedsterOverclockBonusWithinGraceWindow(): void
    {
        $r = csa_battle_beta_score_correct_answer('speedster', 10, 1.5, 10, null);
        $this->assertSame(13, $r['points']);
    }

    public function testSpeedsterNoOverclockBonusJustOverGraceWindow(): void
    {
        $r = csa_battle_beta_score_correct_answer('speedster', 10, 1.51, 10, null);
        $this->assertSame(10, $r['points']);
    }

    public function testSaboteurPassiveDeductsOnePoint(): void
    {
        $r = csa_battle_beta_score_correct_answer('saboteur', 10, 3.0, 10, null);
        $this->assertSame(9, $r['points']);
    }

    public function testSaboteurPassiveNeverGoesNegativeAtMinimumPoints(): void
    {
        $r = csa_battle_beta_score_correct_answer('saboteur', 0, 3.0, 10, null);
        $this->assertSame(0, $r['points']);
    }

    public function testHasteBonusMultipliesAndIsConsumed(): void
    {
        $r = csa_battle_beta_score_correct_answer(null, 10, 3.0, 10, 'haste');
        $this->assertSame(15, $r['points']);
        $this->assertTrue($r['bonusConsumed']);
    }

    public function testOverwriteBonusIgnoresSpeedAndGrantsMax(): void
    {
        $r = csa_battle_beta_score_correct_answer(null, 2, 8.0, 20, 'overwrite');
        $this->assertSame(20, $r['points']);
        $this->assertTrue($r['bonusConsumed']);
    }

    public function testHasteStacksWithSpeedsterPassive(): void
    {
        // 10 base + 3 overclock = 13, then *1.5 haste = 19.5 -> rounds to 20
        $r = csa_battle_beta_score_correct_answer('speedster', 10, 1.0, 10, 'haste');
        $this->assertSame(20, $r['points']);
    }

    // --- csa_battle_beta_score_wrong_answer ---

    public function testWrongAnswerWithNoChargesResetsStreak(): void
    {
        $r = csa_battle_beta_score_wrong_answer(0);
        $this->assertFalse($r['preserveStreak']);
        $this->assertSame(0, $r['remainingCharges']);
    }

    public function testWrongAnswerWithChargesPreservesStreakAndConsumesOne(): void
    {
        $r = csa_battle_beta_score_wrong_answer(2);
        $this->assertTrue($r['preserveStreak']);
        $this->assertSame(1, $r['remainingCharges']);
    }

    public function testLastChargeIsConsumedDownToZero(): void
    {
        $r = csa_battle_beta_score_wrong_answer(1);
        $this->assertTrue($r['preserveStreak']);
        $this->assertSame(0, $r['remainingCharges']);
    }

    // --- csa_battle_beta_check_milestones ---

    public function testNoMilestoneCrossedBelow25(): void
    {
        $r = csa_battle_beta_check_milestones('speedster', 0, 24);
        $this->assertSame(0, $r['newTier']);
    }

    public function testSpeedster50UnlocksHaste(): void
    {
        $r = csa_battle_beta_check_milestones('speedster', 25, 52);
        $this->assertSame(50, $r['newTier']);
        $this->assertSame('haste', $r['setNextCorrectBonus']);
        $this->assertSame(0, $r['bonusPoints']);
    }

    public function testSpeedster75GrantsInstantBonusPoints(): void
    {
        $r = csa_battle_beta_check_milestones('speedster', 50, 77);
        $this->assertSame(75, $r['newTier']);
        $this->assertSame(8, $r['bonusPoints']);
    }

    public function testSaboteur25GrantsTwoShieldCharges(): void
    {
        $r = csa_battle_beta_check_milestones('saboteur', 0, 26);
        $this->assertSame(25, $r['newTier']);
        $this->assertSame(2, $r['addShieldCharges']);
    }

    public function testSaboteur50GrantsExtraSeconds(): void
    {
        $r = csa_battle_beta_check_milestones('saboteur', 25, 51);
        $this->assertSame(50, $r['newTier']);
        $this->assertSame(3, $r['addExtraSeconds']);
    }

    public function testSaboteur75UnlocksOverwrite(): void
    {
        $r = csa_battle_beta_check_milestones('saboteur', 50, 80);
        $this->assertSame(75, $r['newTier']);
        $this->assertSame('overwrite', $r['setNextCorrectBonus']);
    }

    public function testAlreadyPassedTierDoesNotRefire(): void
    {
        $r = csa_battle_beta_check_milestones('speedster', 50, 60);
        $this->assertSame(50, $r['newTier']); // stays at 50, no re-trigger of the 25 or 50 unlock
        $this->assertNull($r['setNextCorrectBonus']);
    }

    public function testJumpingMultipleTiersAtOnceAccumulatesAllEffects(): void
    {
        // A single big answer could plausibly cross 25 and 50 in one jump.
        $r = csa_battle_beta_check_milestones('saboteur', 0, 55);
        $this->assertSame(50, $r['newTier']);
        $this->assertSame(2, $r['addShieldCharges']);
        $this->assertSame(3, $r['addExtraSeconds']);
    }

    // --- csa_battle_beta_mana_after_streak ---

    public function testSpeedsterManaFillsEveryTwoStreak(): void
    {
        $this->assertSame(35, csa_battle_beta_mana_after_streak('speedster', 2, 0));
        $this->assertSame(0, csa_battle_beta_mana_after_streak('speedster', 1, 0));
    }

    public function testSpeedsterManaCapsAt100(): void
    {
        $this->assertSame(100, csa_battle_beta_mana_after_streak('speedster', 8, 90));
    }

    public function testSaboteurManaNeverChanges(): void
    {
        $this->assertSame(0, csa_battle_beta_mana_after_streak('saboteur', 4, 0));
    }
}
