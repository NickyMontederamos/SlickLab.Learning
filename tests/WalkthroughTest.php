<?php

use PHPUnit\Framework\TestCase;

final class WalkthroughTest extends TestCase
{
    private array $templates;

    protected function setUp(): void
    {
        // Placeholders live on their own lines, matching the real templates in
        // walkthrough_templates.php — needed so tests correctly exercise
        // "drop the whole line" rather than "blank out part of a line".
        $this->templates = [
            'CMDB' => "This flashcard: \"{{QUESTION_CONTEXT}}\"\nCorrect answer: {{CORRECT_ANSWER}}\n\nSteps for CMDB.\n\nYour ServiceNow instance: {{SERVICE_NOW_URL}}",
            'Forms' => 'Steps for Forms, no placeholders here.',
        ];
    }

    /** Calls csa_resolve_walkthrough() with sensible defaults for the params a given test isn't exercising. */
    private function resolve(
        ?string $questionWalkthrough,
        string $category,
        ?string $serviceNowUrl,
        string $questionContext = 'Sample question text?',
        string $correctAnswerContext = 'A: Sample answer'
    ): string {
        return csa_resolve_walkthrough(
            $questionWalkthrough,
            $category,
            $this->templates,
            $serviceNowUrl,
            $questionContext,
            $correctAnswerContext
        );
    }

    public function testPerQuestionWalkthroughTakesPriorityOverCategoryTemplate(): void
    {
        $result = $this->resolve('Bespoke steps for this exact question.', 'CMDB', null);
        $this->assertSame('Bespoke steps for this exact question.', $result);
    }

    public function testFallsBackToCategoryTemplateWhenNoPerQuestionWalkthrough(): void
    {
        $result = $this->resolve(null, 'CMDB', null);
        $this->assertStringContainsString('Steps for CMDB.', $result);
    }

    public function testEmptyStringPerQuestionWalkthroughIsTreatedAsAbsent(): void
    {
        // A blank/whitespace-only DB value must not "win" over a real category template.
        $result = $this->resolve('   ', 'CMDB', null);
        $this->assertStringContainsString('Steps for CMDB.', $result);
    }

    public function testFallsBackToComingSoonWhenNeitherExists(): void
    {
        $result = $this->resolve(null, 'Agile/VTB', null);
        $this->assertStringContainsString('Walkthrough coming soon!', $result);
        $this->assertStringContainsString('Agile/VTB', $result);
    }

    public function testQuestionContextAndCorrectAnswerAreSubstitutedIntoCategoryTemplate(): void
    {
        $result = $this->resolve(null, 'CMDB', null, 'What field controls X?', 'B: The Y field');
        $this->assertStringContainsString('This flashcard: "What field controls X?"', $result);
        $this->assertStringContainsString('Correct answer: B: The Y field', $result);
    }

    public function testQuestionContextAndCorrectAnswerAreAlsoSubstitutedIntoPerQuestionWalkthrough(): void
    {
        // Not just category templates -- a per-question walkthrough that
        // happens to use these placeholders gets them resolved too.
        $result = $this->resolve('Q: {{QUESTION_CONTEXT}} / A: {{CORRECT_ANSWER}}', 'CMDB', null, 'Ctx here', 'Ans here');
        $this->assertSame('Q: Ctx here / A: Ans here', $result);
    }

    public function testServiceNowUrlPlaceholderIsSubstitutedWhenSet(): void
    {
        $result = $this->resolve(null, 'CMDB', 'https://dev12345.service-now.com');
        $this->assertStringContainsString('Your ServiceNow instance: https://dev12345.service-now.com', $result);
        $this->assertStringNotContainsString('{{SERVICE_NOW_URL}}', $result);
    }

    public function testServiceNowUrlTrailingSlashIsStripped(): void
    {
        $result = $this->resolve(null, 'CMDB', 'https://dev12345.service-now.com/');
        $this->assertStringContainsString('Your ServiceNow instance: https://dev12345.service-now.com', $result);
        $this->assertStringNotContainsString('.com//', $result);
    }

    public function testServiceNowUrlLineIsDroppedEntirelyWhenNotSet(): void
    {
        // No nudge to go configure one — the line just isn't there.
        $result = $this->resolve(null, 'CMDB', null, '', '');
        $this->assertStringNotContainsString('{{SERVICE_NOW_URL}}', $result);
        $this->assertStringNotContainsString('Your ServiceNow instance:', $result);
        $this->assertStringNotContainsString('Account settings', $result);
        $this->assertSame("This flashcard: \"\"\nCorrect answer: \n\nSteps for CMDB.", $result);
    }

    public function testEmptyStringServiceNowUrlIsTreatedAsNotSet(): void
    {
        $result = $this->resolve(null, 'CMDB', '  ', '', '');
        $this->assertStringNotContainsString('Your ServiceNow instance:', $result);
    }

    public function testTemplateWithoutAPlaceholderIsUnaffectedByMissingUrl(): void
    {
        // "Forms" template has no {{SERVICE_NOW_URL}} token at all — must pass through unchanged.
        $result = $this->resolve(null, 'Forms', null);
        $this->assertSame('Steps for Forms, no placeholders here.', $result);
    }

    public function testTemplateWithoutAPlaceholderIsUnaffectedWhenUrlIsSet(): void
    {
        $result = $this->resolve(null, 'Forms', 'https://dev12345.service-now.com');
        $this->assertSame('Steps for Forms, no placeholders here.', $result);
    }

    // --- csa_correct_answer_summary() ---

    public function testCorrectAnswerSummarySingleAnswer(): void
    {
        $options = [
            ['letter' => 'A', 'text' => 'Right one', 'correct' => true],
            ['letter' => 'B', 'text' => 'Wrong one', 'correct' => false],
        ];
        $this->assertSame('A: Right one', csa_correct_answer_summary($options));
    }

    public function testCorrectAnswerSummaryMultiSelectJoinsWithSemicolons(): void
    {
        $options = [
            ['letter' => 'A', 'text' => 'First', 'correct' => true],
            ['letter' => 'B', 'text' => 'Second', 'correct' => false],
            ['letter' => 'C', 'text' => 'Third', 'correct' => true],
        ];
        $this->assertSame('A: First; C: Third', csa_correct_answer_summary($options));
    }

    public function testCorrectAnswerSummaryEmptyOptionsReturnsEmptyString(): void
    {
        $this->assertSame('', csa_correct_answer_summary([]));
    }
}
