<?php

use PHPUnit\Framework\TestCase;

final class ExhibitionExamTest extends TestCase
{
    // --- csa_tally_exhibition_votes() ---

    public function testMajorityWinnersAreSelectedInVoteCountOrder(): void
    {
        // 4 participants, threshold = ceil(4/2) = 2.
        $winners = csa_tally_exhibition_votes(
            [10 => 3, 20 => 2, 30 => 1, 40 => 0],
            [10 => 1, 20 => 2, 30 => 3, 40 => 4],
            4
        );
        $this->assertSame([10, 20], $winners);
    }

    public function testTiedVoteCountsBreakBySortOrderAscending(): void
    {
        $winners = csa_tally_exhibition_votes(
            [10 => 2, 20 => 2, 30 => 2],
            [10 => 5, 20 => 1, 30 => 3],
            2 // threshold = 1, all three clear it
        );
        // All three cleared the majority bar; still returned in ranked order.
        $this->assertSame([20, 30, 10], $winners);
    }

    public function testFewerThanTwoMajorityWinnersFallsBackToTopTwoOverall(): void
    {
        // 5 participants, threshold = ceil(5/2) = 3. Only one topic clears it.
        $winners = csa_tally_exhibition_votes(
            [10 => 4, 20 => 2, 30 => 1],
            [10 => 1, 20 => 2, 30 => 3],
            5
        );
        // Falls back to the top 2 highest-voted overall, not just the sole majority winner.
        $this->assertSame([10, 20], $winners);
    }

    public function testZeroVotesEverywhereStillFallsBackToTopTwoBySortOrder(): void
    {
        $winners = csa_tally_exhibition_votes(
            [10 => 0, 20 => 0, 30 => 0],
            [10 => 3, 20 => 1, 30 => 2],
            3
        );
        $this->assertSame([20, 30], $winners);
    }

    public function testExactlyTwoCandidatesAlwaysResolvesToBoth(): void
    {
        $winners = csa_tally_exhibition_votes([10 => 0, 20 => 0], [10 => 1, 20 => 2], 6);
        $this->assertSame([10, 20], $winners);
    }

    public function testVoteThresholdRoundsUp(): void
    {
        $this->assertSame(2, csa_exhibition_vote_threshold(3));
        $this->assertSame(3, csa_exhibition_vote_threshold(5));
        $this->assertSame(1, csa_exhibition_vote_threshold(1));
        $this->assertSame(0, csa_exhibition_vote_threshold(0));
    }

    // --- csa_union_exhibition_question_pools() ---

    public function testUnionDedupesOverlappingQuestionIds(): void
    {
        $union = csa_union_exhibition_question_pools([
            10 => [1, 2, 3],
            20 => [3, 4, 5],
        ]);
        $this->assertSame([1, 2, 3, 4, 5], $union);
    }

    public function testUnionOfNonOverlappingPoolsIsAFlatConcatenation(): void
    {
        $union = csa_union_exhibition_question_pools([10 => [1, 2], 20 => [3, 4]]);
        $this->assertSame([1, 2, 3, 4], $union);
    }

    // --- csa_exhibition_is_expired() ---

    public function testNullClosesAtIsNeverExpired(): void
    {
        $this->assertFalse(csa_exhibition_is_expired(null, new DateTime('2026-07-24 12:00:00')));
    }

    public function testExpiredWhenClosesAtIsInThePast(): void
    {
        $this->assertTrue(csa_exhibition_is_expired('2026-07-23 12:00:00', new DateTime('2026-07-24 12:00:00')));
    }

    public function testNotExpiredWhenClosesAtIsInTheFuture(): void
    {
        $this->assertFalse(csa_exhibition_is_expired('2026-07-25 12:00:00', new DateTime('2026-07-24 12:00:00')));
    }

    public function testExactlyAtClosesAtCountsAsExpired(): void
    {
        $this->assertTrue(csa_exhibition_is_expired('2026-07-24 12:00:00', new DateTime('2026-07-24 12:00:00')));
    }

    // --- csa_compute_exhibition_winner() ---

    public function testHighestCorrectCountWins(): void
    {
        $winner = csa_compute_exhibition_winner([
            ['userId' => 1, 'correctCount' => 20, 'durationSeconds' => 1000],
            ['userId' => 2, 'correctCount' => 25, 'durationSeconds' => 1200],
        ]);
        $this->assertSame(2, $winner);
    }

    public function testTiedCorrectCountBreaksByLowerDuration(): void
    {
        $winner = csa_compute_exhibition_winner([
            ['userId' => 1, 'correctCount' => 20, 'durationSeconds' => 900],
            ['userId' => 2, 'correctCount' => 20, 'durationSeconds' => 1200],
        ]);
        $this->assertSame(1, $winner);
    }

    public function testSoloAttemptIsDiscardedEntirely(): void
    {
        $winner = csa_compute_exhibition_winner([
            ['userId' => 1, 'correctCount' => 25, 'durationSeconds' => 900],
        ]);
        $this->assertNull($winner);
    }

    public function testNoAttemptsAtAllIsDiscarded(): void
    {
        $this->assertNull(csa_compute_exhibition_winner([]));
    }

    public function testThreeDistinctUsersStillPicksASingleWinner(): void
    {
        $winner = csa_compute_exhibition_winner([
            ['userId' => 1, 'correctCount' => 10, 'durationSeconds' => 500],
            ['userId' => 2, 'correctCount' => 15, 'durationSeconds' => 800],
            ['userId' => 3, 'correctCount' => 15, 'durationSeconds' => 600],
        ]);
        $this->assertSame(3, $winner); // tied correctCount with user 2, but faster
    }

    public function testDuplicateAttemptRowsForTheSameUserDoNotCountAsTwoDistinctUsers(): void
    {
        // Defensive: even if a caller passes more than one row per user, "2+ distinct
        // users" still means distinct user IDs, not row count.
        $winner = csa_compute_exhibition_winner([
            ['userId' => 1, 'correctCount' => 10, 'durationSeconds' => 500],
            ['userId' => 1, 'correctCount' => 12, 'durationSeconds' => 400],
        ]);
        $this->assertNull($winner);
    }
}
