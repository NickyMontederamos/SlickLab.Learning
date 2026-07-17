<?php

use PHPUnit\Framework\TestCase;

final class ExamGradingTest extends TestCase
{
    // --- csa_normalize_selected_letters / csa_is_answer_correct ---

    public function testExactSingleAnswerMatches(): void
    {
        $selected = csa_normalize_selected_letters(['A']);
        $this->assertTrue(csa_is_answer_correct($selected, ['A']));
    }

    public function testMultiSelectMatchesRegardlessOfSubmittedOrder(): void
    {
        $selected = csa_normalize_selected_letters(['C', 'A']);
        $this->assertSame(['A', 'C'], $selected);
        $this->assertTrue(csa_is_answer_correct($selected, ['A', 'C']));
    }

    public function testMultiSelectMissingOneCorrectOptionIsWrong(): void
    {
        // Picked A but the question requires A and C both checked.
        $selected = csa_normalize_selected_letters(['A']);
        $this->assertFalse(csa_is_answer_correct($selected, ['A', 'C']));
    }

    public function testMultiSelectWithExtraWrongOptionIsWrong(): void
    {
        // Picked A, C, and D, but D is not a correct option — partial credit does not exist.
        $selected = csa_normalize_selected_letters(['A', 'C', 'D']);
        $this->assertFalse(csa_is_answer_correct($selected, ['A', 'C']));
    }

    public function testDuplicateSubmittedLettersAreDeduplicated(): void
    {
        $selected = csa_normalize_selected_letters(['A', 'A', 'B']);
        $this->assertSame(['A', 'B'], $selected);
        $this->assertTrue(csa_is_answer_correct($selected, ['A', 'B']));
    }

    public function testMixedIntAndStringLettersAreCoercedToStrings(): void
    {
        // Realistic: a client could send a numeric-looking value as an int, not a string.
        $selected = csa_normalize_selected_letters([1, 'B']);
        $this->assertSame(['1', 'B'], $selected);
    }

    public function testNonArrayInputNormalizesToEmptySelection(): void
    {
        // Malformed client payload — must not throw, must grade as "nothing selected".
        $this->assertSame([], csa_normalize_selected_letters('not-an-array'));
        $this->assertSame([], csa_normalize_selected_letters(null));
    }

    public function testEmptySelectionAgainstEmptyCorrectAnswerIsCorrect(): void
    {
        // Edge case: a data issue where no option is flagged correct. Vacuously "correct"
        // since [] === [] — documenting existing behavior, not endorsing the data state.
        $selected = csa_normalize_selected_letters([]);
        $this->assertTrue(csa_is_answer_correct($selected, []));
    }

    // --- csa_compute_exam_score ---

    public function testScoreAboveThresholdPasses(): void
    {
        $result = csa_compute_exam_score(8, 10, 70.0);
        $this->assertSame(80.0, $result['scorePercent']);
        $this->assertTrue($result['passed']);
    }

    public function testScoreBelowThresholdFails(): void
    {
        $result = csa_compute_exam_score(5, 10, 70.0);
        $this->assertSame(50.0, $result['scorePercent']);
        $this->assertFalse($result['passed']);
    }

    public function testScoreExactlyAtThresholdPasses(): void
    {
        // >= , not > — scoring exactly the pass percent must count as passing.
        $result = csa_compute_exam_score(7, 10, 70.0);
        $this->assertSame(70.0, $result['scorePercent']);
        $this->assertTrue($result['passed']);
    }

    public function testZeroTotalQuestionsDoesNotDivideByZero(): void
    {
        $result = csa_compute_exam_score(0, 0, 70.0);
        $this->assertSame(0.0, $result['scorePercent']);
        $this->assertFalse($result['passed']);
    }

    public function testScorePercentRoundsToTwoDecimalPlaces(): void
    {
        // 1/3 = 33.333...% — must round to 33.33, not truncate or round to 1 decimal.
        $result = csa_compute_exam_score(1, 3, 70.0);
        $this->assertSame(33.33, $result['scorePercent']);
    }

    public function testPerfectScorePasses(): void
    {
        $result = csa_compute_exam_score(10, 10, 70.0);
        $this->assertSame(100.0, $result['scorePercent']);
        $this->assertTrue($result['passed']);
    }
}
