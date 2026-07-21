<?php

use PHPUnit\Framework\TestCase;

final class ExamPlanningTest extends TestCase
{
    private const ALLOWED_COUNTS = [25, 50, 100, 274];
    private const DEFAULT_COUNT = 274;
    private const FULL_DURATION = 5400; // 90 minutes, matches config/config.local.php
    private const FULL_TOTAL = 274; // current live question bank size

    public function testFullLengthExamGetsFullDuration(): void
    {
        $plan = csa_plan_exam(274, self::ALLOWED_COUNTS, self::DEFAULT_COUNT, self::FULL_TOTAL, self::FULL_DURATION);
        $this->assertSame(274, $plan['count']);
        $this->assertSame(5400, $plan['durationSeconds']);
    }

    public function testShorterExamGetsProportionallyScaledDuration(): void
    {
        // 25/274 of 5400s = ~492.7s, rounds to nearest 30s = 480s.
        $plan = csa_plan_exam(25, self::ALLOWED_COUNTS, self::DEFAULT_COUNT, self::FULL_TOTAL, self::FULL_DURATION);
        $this->assertSame(25, $plan['count']);
        $this->assertSame(480, $plan['durationSeconds']);
    }

    public function testInvalidRequestedCountFallsBackToDefault(): void
    {
        // Not in the allowed list — must silently fall back, not error or pass through unchanged.
        $plan = csa_plan_exam(999, self::ALLOWED_COUNTS, self::DEFAULT_COUNT, self::FULL_TOTAL, self::FULL_DURATION);
        $this->assertSame(274, $plan['count']);
    }

    public function testZeroOrMissingCountFallsBackToDefault(): void
    {
        $plan = csa_plan_exam(0, self::ALLOWED_COUNTS, self::DEFAULT_COUNT, self::FULL_TOTAL, self::FULL_DURATION);
        $this->assertSame(274, $plan['count']);
    }

    public function testNegativeCountFallsBackToDefaultRatherThanProducingNegativeQuestions(): void
    {
        $plan = csa_plan_exam(-5, self::ALLOWED_COUNTS, self::DEFAULT_COUNT, self::FULL_TOTAL, self::FULL_DURATION);
        $this->assertSame(274, $plan['count']);
    }

    public function testDurationHasA60SecondFloorForVeryShortQuizzes(): void
    {
        // A tiny bank (e.g. 2 questions out of a 274-scale 90-min exam) would scale to
        // well under 60s on the raw formula — the floor must kick in.
        $plan = csa_plan_exam(25, self::ALLOWED_COUNTS, self::DEFAULT_COUNT, 274, 60);
        $this->assertGreaterThanOrEqual(60, $plan['durationSeconds']);
    }

    public function testDurationRoundsToNearest30Seconds(): void
    {
        // Every returned duration must be an exact multiple of 30.
        foreach ([25, 50, 100, 274] as $requested) {
            $plan = csa_plan_exam($requested, self::ALLOWED_COUNTS, self::DEFAULT_COUNT, self::FULL_TOTAL, self::FULL_DURATION);
            $this->assertSame(0, $plan['durationSeconds'] % 30, "requested=$requested");
        }
    }

    public function testScalingWorksForDifferentBankSizesNotJustTheCurrentLiveOne(): void
    {
        // The formula shouldn't be hardcoded to the current 274-question bank —
        // verify it scales correctly if the bank were smaller.
        $plan = csa_plan_exam(50, self::ALLOWED_COUNTS, self::DEFAULT_COUNT, 100, self::FULL_DURATION);
        $this->assertSame(50, $plan['count']);
        $this->assertSame(2700, $plan['durationSeconds']); // half of a 100-question bank -> half duration
    }

    public function testRequestedCountNeverExceedsAvailableQuestions(): void
    {
        // If the bank has fewer questions than requested, count must clamp down —
        // this can't be reached via the current allowed-counts list against a 274-question
        // bank, but the function must not silently over-select against a smaller bank.
        $plan = csa_plan_exam(100, self::ALLOWED_COUNTS, self::DEFAULT_COUNT, 50, self::FULL_DURATION);
        $this->assertSame(50, $plan['count']);
    }

    // --- csa_scale_exam_duration() ---
    // Split out for mini-exams: a fixed incorrect-question count (e.g. 7)
    // isn't one of the preset allowedCounts, so it can't go through
    // csa_plan_exam()'s count validation at all.

    public function testScaleExamDurationForANonPresetCount(): void
    {
        $duration = csa_scale_exam_duration(self::FULL_DURATION, self::FULL_TOTAL, 7);
        $this->assertSame(150, $duration);
    }

    public function testScaleExamDurationStillHasThe60SecondFloor(): void
    {
        $duration = csa_scale_exam_duration(self::FULL_DURATION, self::FULL_TOTAL, 1);
        $this->assertGreaterThanOrEqual(60, $duration);
    }

    public function testScaleExamDurationMatchesCsaPlanExamForAPresetCount(): void
    {
        // Same formula, same result, whichever entry point is used.
        $viaPlan = csa_plan_exam(50, self::ALLOWED_COUNTS, self::DEFAULT_COUNT, self::FULL_TOTAL, self::FULL_DURATION);
        $viaScale = csa_scale_exam_duration(self::FULL_DURATION, self::FULL_TOTAL, 50);
        $this->assertSame($viaPlan['durationSeconds'], $viaScale);
    }
}
