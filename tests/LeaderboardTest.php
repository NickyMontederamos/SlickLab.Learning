<?php

use PHPUnit\Framework\TestCase;

final class LeaderboardTest extends TestCase
{
    public function testPointsFromTopicsMasteredOnly(): void
    {
        $points = csa_compute_leaderboard_points([
            'topicsMastered' => 3, 'bestExamPercent' => null, 'battleWins' => 0, 'battlesPlayed' => 0,
        ]);
        $this->assertSame(45, $points);
    }

    public function testPointsFromExamPercentRoundsToNearestInt(): void
    {
        $points = csa_compute_leaderboard_points([
            'topicsMastered' => 0, 'bestExamPercent' => 91.61, 'battleWins' => 0, 'battlesPlayed' => 0,
        ]);
        $this->assertSame(92, $points);
    }

    public function testNullExamPercentContributesNothing(): void
    {
        $points = csa_compute_leaderboard_points([
            'topicsMastered' => 0, 'bestExamPercent' => null, 'battleWins' => 0, 'battlesPlayed' => 0,
        ]);
        $this->assertSame(0, $points);
    }

    public function testBattleWinsCountBothWinAndParticipationPoints(): void
    {
        $points = csa_compute_leaderboard_points([
            'topicsMastered' => 0, 'bestExamPercent' => null, 'battleWins' => 2, 'battlesPlayed' => 2,
        ]);
        // 2 wins * 8 + 2 played * 2 = 20
        $this->assertSame(20, $points);
    }

    public function testAllFourSourcesBlendTogether(): void
    {
        $points = csa_compute_leaderboard_points([
            'topicsMastered' => 5, 'bestExamPercent' => 100.0, 'battleWins' => 3, 'battlesPlayed' => 5,
        ]);
        // 5*15 + 100 + 3*8 + 5*2 = 75 + 100 + 24 + 10 = 209
        $this->assertSame(209, $points);
    }

    public function testMissingKeysDefaultSafely(): void
    {
        $points = csa_compute_leaderboard_points([]);
        $this->assertSame(0, $points);
    }

    public function testRankForZeroPointsIsBronze(): void
    {
        $rank = csa_rank_for_points(0);
        $this->assertSame('Bronze', $rank['label']);
    }

    public function testRankTierBoundariesAreInclusiveAtTheMinimum(): void
    {
        $this->assertSame('Bronze', csa_rank_for_points(99)['label']);
        $this->assertSame('Silver', csa_rank_for_points(100)['label']);
        $this->assertSame('Silver', csa_rank_for_points(249)['label']);
        $this->assertSame('Gold', csa_rank_for_points(250)['label']);
        $this->assertSame('Gold', csa_rank_for_points(399)['label']);
        $this->assertSame('Platinum', csa_rank_for_points(400)['label']);
        $this->assertSame('Platinum', csa_rank_for_points(549)['label']);
        $this->assertSame('Diamond', csa_rank_for_points(550)['label']);
    }

    public function testRankForVeryHighPointsStaysDiamond(): void
    {
        $rank = csa_rank_for_points(10000);
        $this->assertSame('Diamond', $rank['label']);
    }

    public function testEveryRankHasAValidTierSlug(): void
    {
        $validTiers = ['bronze', 'silver', 'gold', 'platinum', 'diamond'];
        foreach ([0, 100, 250, 400, 550] as $points) {
            $rank = csa_rank_for_points($points);
            $this->assertContains($rank['tier'], $validTiers);
        }
    }
}
