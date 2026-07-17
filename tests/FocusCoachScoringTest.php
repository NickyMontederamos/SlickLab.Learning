<?php

use PHPUnit\Framework\TestCase;

final class FocusCoachScoringTest extends TestCase
{
    private DateTime $now;

    protected function setUp(): void
    {
        $this->now = new DateTime('2026-07-18 12:00:00');
    }

    public function testExamAccuracyDrivesAccuracyScoreWhenAvailable(): void
    {
        $row = [
            'category' => 'Pharmacology', 'totalQuestions' => 20, 'knownCount' => 12,
            'reviewCount' => 5, 'unseenCount' => 3, 'lastReviewedAt' => '2026-07-15 09:00:00',
            'notesCount' => 1, 'avgConfidence' => 3.5, 'confidenceCount' => 4,
        ];
        $result = csa_compute_category_score($row, ['total' => 10, 'correct' => 7], $this->now);

        $this->assertSame(70.0, $result['examPercent']);
        $this->assertSame(60.0, $result['knownPercent']);
        $this->assertSame(3, $result['daysSinceReview']);
        $this->assertSame(29.0, $result['priorityScore']);
        $this->assertContains('70% correct on Mock Exam (10 questions seen)', $result['reasons']);
        $this->assertContains('Last reviewed 3 days ago', $result['reasons']);
    }

    public function testNoExamDataFallsBackToDefaultAccuracyScoreOf50(): void
    {
        $row = [
            'category' => 'Anatomy', 'totalQuestions' => 15, 'knownCount' => 5,
            'reviewCount' => 5, 'unseenCount' => 5, 'lastReviewedAt' => null,
            'notesCount' => 0, 'avgConfidence' => null, 'confidenceCount' => 0,
        ];
        $result = csa_compute_category_score($row, null, $this->now);

        $this->assertNull($result['examPercent']);
        $this->assertNull($result['daysSinceReview']);
        $this->assertSame(58.3, $result['priorityScore']);
        $this->assertContains('Not yet tested on the Mock Exam', $result['reasons']);
        $this->assertContains('Never reviewed in Flashcards', $result['reasons']);
    }

    public function testConfidenceGapIsNullBelowTwoRatings(): void
    {
        // Only 1 confidence rating recorded — must not compute a gap from noise.
        $row = [
            'category' => 'Ethics', 'totalQuestions' => 8, 'knownCount' => 8,
            'reviewCount' => 0, 'unseenCount' => 0, 'lastReviewedAt' => '2026-07-17 09:00:00',
            'notesCount' => 0, 'avgConfidence' => 5.0, 'confidenceCount' => 1,
        ];
        $result = csa_compute_category_score($row, ['total' => 5, 'correct' => 1], $this->now);

        $this->assertNull($result['confidenceGap']);
        $this->assertSame(1, $result['daysSinceReview']);
        $this->assertContains('Last reviewed 1 day ago', $result['reasons']);
    }

    public function testOverconfidenceTrapAddsBoostAndWarningReason(): void
    {
        // Rates self max confidence (5.0) but scored 0% on the exam for this category.
        $row = [
            'category' => 'Cardiology', 'totalQuestions' => 10, 'knownCount' => 8,
            'reviewCount' => 1, 'unseenCount' => 1, 'lastReviewedAt' => '2026-07-18 08:00:00',
            'notesCount' => 0, 'avgConfidence' => 5.0, 'confidenceCount' => 3,
        ];
        $result = csa_compute_category_score($row, ['total' => 4, 'correct' => 0], $this->now);

        $this->assertSame(100.0, $result['confidenceGap']);
        $this->assertSame(62.0, $result['priorityScore']);
        $this->assertContains(
            '⚠ You rate yourself confident here, but results say otherwise — possible overconfidence trap',
            $result['reasons']
        );
    }

    public function testUnderconfidenceAddsEncouragingReasonNotWarning(): void
    {
        // Rates self lowest confidence (1.0) but scored 100% on the exam.
        $row = [
            'category' => 'Neurology', 'totalQuestions' => 10, 'knownCount' => 9,
            'reviewCount' => 1, 'unseenCount' => 0, 'lastReviewedAt' => '2026-07-18 07:00:00',
            'notesCount' => 2, 'avgConfidence' => 1.0, 'confidenceCount' => 5,
        ];
        $result = csa_compute_category_score($row, ['total' => 6, 'correct' => 6], $this->now);

        $this->assertSame(-100.0, $result['confidenceGap']);
        $this->assertSame(4.9, $result['priorityScore']);
        $this->assertContains(
            'You know this better than you feel — low self-rated confidence despite solid results',
            $result['reasons']
        );
        $this->assertNotContains(
            '⚠ You rate yourself confident here, but results say otherwise — possible overconfidence trap',
            $result['reasons']
        );
    }

    public function testNoteBoostIsCappedAt20(): void
    {
        // 5 notes * 7 = 35, but the boost must cap at 20.
        $row = [
            'category' => 'Renal', 'totalQuestions' => 6, 'knownCount' => 6,
            'reviewCount' => 0, 'unseenCount' => 0, 'lastReviewedAt' => '2026-07-17 12:00:00',
            'notesCount' => 5, 'avgConfidence' => null, 'confidenceCount' => 0,
        ];
        $result = csa_compute_category_score($row, null, $this->now);

        $this->assertSame(22.8, $result['priorityScore']);
        $this->assertContains('5 personal notes flagged here', $result['reasons']);
    }

    public function testZeroQuestionsInCategoryDoesNotDivideByZero(): void
    {
        // Defensive: the live query can't currently produce this (categories come
        // from an inner join over `questions`), but the function is a general-purpose
        // pure function and must not fatal on it.
        $row = [
            'category' => 'Empty', 'totalQuestions' => 0, 'knownCount' => 0,
            'reviewCount' => 0, 'unseenCount' => 0, 'lastReviewedAt' => null,
            'notesCount' => 0, 'avgConfidence' => null, 'confidenceCount' => 0,
        ];
        $result = csa_compute_category_score($row, null, $this->now);

        // knownPercent's zero-questions branch returns int 0, not float 0.0 (pre-existing
        // in the original inline code — not introduced by the lib/ extraction).
        $this->assertSame(0, $result['knownPercent']);
        $this->assertSame(70.0, $result['priorityScore']);
    }

    public function testReviewedTodayShowsNoStalenessReasonButRecordsZeroDays(): void
    {
        $row = [
            'category' => 'Cardiology', 'totalQuestions' => 10, 'knownCount' => 8,
            'reviewCount' => 1, 'unseenCount' => 1, 'lastReviewedAt' => '2026-07-18 08:00:00',
            'notesCount' => 0, 'avgConfidence' => null, 'confidenceCount' => 0,
        ];
        $result = csa_compute_category_score($row, null, $this->now);

        $this->assertSame(0, $result['daysSinceReview']);
        foreach ($result['reasons'] as $reason) {
            $this->assertStringNotContainsString('Last reviewed', $reason);
        }
    }
}
