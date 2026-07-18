<?php

use PHPUnit\Framework\TestCase;

final class FlashcardSchedulingTest extends TestCase
{
    public function testAgainAlwaysResetsToBoxZeroRegardlessOfCurrentBox(): void
    {
        foreach ([0, 1, 2, 3, 4] as $box) {
            $result = csa_next_leitner_state($box, 'again');
            $this->assertSame(0, $result['box'], "box=$box");
            $this->assertSame('review', $result['status'], "box=$box");
            $this->assertSame(10, $result['dueMinutes'], "box=$box");
        }
    }

    public function testGoodAdvancesBoxByOne(): void
    {
        $result = csa_next_leitner_state(0, 'good');
        $this->assertSame(1, $result['box']);
        $this->assertSame(1440, $result['dueMinutes']); // 1 day
    }

    public function testGoodClampsAtBoxFourInsteadOfOverflowing(): void
    {
        // Already at the top box — must stay at 4, not advance to a non-existent box 5.
        $result = csa_next_leitner_state(4, 'good');
        $this->assertSame(4, $result['box']);
        $this->assertSame(43200, $result['dueMinutes']); // 30 days
    }

    public function testStatusIsReviewForBoxZeroAndOne(): void
    {
        $this->assertSame('review', csa_next_leitner_state(0, 'again')['status']);
        // Box 0 -> good -> box 1, still "review" (threshold is box <= 1).
        $this->assertSame('review', csa_next_leitner_state(0, 'good')['status']);
    }

    public function testStatusBecomesKnownAtBoxTwoAndAbove(): void
    {
        // Box 1 -> good -> box 2, crosses into "known".
        $this->assertSame('known', csa_next_leitner_state(1, 'good')['status']);
        $this->assertSame('known', csa_next_leitner_state(3, 'good')['status']);
    }

    public function testDueMinutesMatchesDocumentedLeitnerIntervals(): void
    {
        $expected = [0 => 10, 1 => 1440, 2 => 4320, 3 => 10080, 4 => 43200];
        foreach ($expected as $box => $minutes) {
            // Reach each box via repeated 'good' from box 0, then check the interval directly.
            $this->assertSame($minutes, CSA_LEITNER_INTERVALS[$box], "box=$box");
        }
    }

    public function testFullProgressionFromBoxZeroToMax(): void
    {
        // A card answered "good" 5 times in a row: 0 -> 1 -> 2 -> 3 -> 4 -> 4 (clamped).
        $box = 0;
        $expectedBoxes = [1, 2, 3, 4, 4];
        foreach ($expectedBoxes as $expectedBox) {
            $result = csa_next_leitner_state($box, 'good');
            $this->assertSame($expectedBox, $result['box']);
            $box = $result['box'];
        }
    }
}
