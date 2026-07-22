<?php

use PHPUnit\Framework\TestCase;

final class IncorrectReviewTest extends TestCase
{
    // --- csa_compute_review_readiness() ---

    public function testExactlyAtThresholdIsReady(): void
    {
        // 4 of 5 known = 80% exactly, threshold is >=, must count as ready.
        $result = csa_compute_review_readiness(['known', 'known', 'known', 'known', 'review']);
        $this->assertSame(5, $result['total']);
        $this->assertSame(4, $result['knownCount']);
        $this->assertSame(0.8, $result['knownRate']);
        $this->assertTrue($result['ready']);
    }

    public function testJustBelowThresholdIsNotReady(): void
    {
        // 3 of 5 known = 60%, below 80%.
        $result = csa_compute_review_readiness(['known', 'known', 'known', 'review', 'review']);
        $this->assertSame(0.6, $result['knownRate']);
        $this->assertFalse($result['ready']);
    }

    public function testAllKnownIsReady(): void
    {
        $result = csa_compute_review_readiness(['known', 'known', 'known']);
        $this->assertSame(1.0, $result['knownRate']);
        $this->assertTrue($result['ready']);
    }

    public function testNoneKnownIsNotReady(): void
    {
        $result = csa_compute_review_readiness(['review', 'unseen', 'review']);
        $this->assertSame(0.0, $result['knownRate']);
        $this->assertFalse($result['ready']);
    }

    public function testUnseenQuestionsCountAgainstReadinessNotJustReview(): void
    {
        // A question never touched during review must drag the rate down,
        // same as one still stuck at "review" -- neither counts as known.
        $result = csa_compute_review_readiness(['known', 'known', 'known', 'known', 'unseen']);
        $this->assertSame(0.8, $result['knownRate']);
        $this->assertTrue($result['ready']); // 4/5 = exactly 80%, still passes
    }

    public function testEmptyListIsNotReadyAndDoesNotDivideByZero(): void
    {
        $result = csa_compute_review_readiness([]);
        $this->assertSame(0, $result['total']);
        $this->assertSame(0.0, $result['knownRate']);
        $this->assertFalse($result['ready']);
    }

    public function testCustomThresholdIsRespected(): void
    {
        // 1 of 3 known = ~33.3%, ready against a lowered 30% threshold...
        $lenient = csa_compute_review_readiness(['known', 'review', 'review'], 0.3);
        $this->assertEqualsWithDelta(0.3333, $lenient['knownRate'], 0.001);
        $this->assertTrue($lenient['ready']);

        // ...but not ready against the default 80% threshold.
        $strict = csa_compute_review_readiness(['known', 'review', 'review']);
        $this->assertFalse($strict['ready']);
    }

    // --- csa_pass_percent_for_kind() ---

    public function testFullAttemptUsesTheDefaultPassPercent(): void
    {
        $this->assertSame(70.0, csa_pass_percent_for_kind('full', ['mini' => 80.0, 'topic' => 80.0], 70.0));
    }

    public function testMiniAttemptUsesItsOwnPassPercent(): void
    {
        $this->assertSame(80.0, csa_pass_percent_for_kind('mini', ['mini' => 80.0, 'topic' => 80.0], 70.0));
    }

    public function testTopicAttemptUsesItsOwnPassPercent(): void
    {
        $this->assertSame(80.0, csa_pass_percent_for_kind('topic', ['mini' => 80.0, 'topic' => 80.0], 70.0));
    }

    public function testUnrecognizedKindFallsBackToTheDefault(): void
    {
        $this->assertSame(70.0, csa_pass_percent_for_kind('bogus', ['mini' => 80.0, 'topic' => 80.0], 70.0));
    }
}
