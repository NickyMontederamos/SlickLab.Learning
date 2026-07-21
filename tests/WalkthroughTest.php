<?php

use PHPUnit\Framework\TestCase;

final class WalkthroughTest extends TestCase
{
    private array $templates;

    protected function setUp(): void
    {
        // Placeholder lives on its own line, matching the real templates in
        // walkthrough_templates.php — needed so tests correctly exercise
        // "drop the whole line" rather than "blank out part of a line".
        $this->templates = [
            'CMDB' => "Steps for CMDB.\n\nYour ServiceNow instance: {{SERVICE_NOW_URL}}",
            'Forms' => 'Steps for Forms, no placeholder here.',
        ];
    }

    public function testPerQuestionWalkthroughTakesPriorityOverCategoryTemplate(): void
    {
        $result = csa_resolve_walkthrough('Bespoke steps for this exact question.', 'CMDB', $this->templates, null);
        $this->assertSame('Bespoke steps for this exact question.', $result);
    }

    public function testFallsBackToCategoryTemplateWhenNoPerQuestionWalkthrough(): void
    {
        $result = csa_resolve_walkthrough(null, 'CMDB', $this->templates, null);
        $this->assertStringContainsString('Steps for CMDB.', $result);
    }

    public function testEmptyStringPerQuestionWalkthroughIsTreatedAsAbsent(): void
    {
        // A blank/whitespace-only DB value must not "win" over a real category template.
        $result = csa_resolve_walkthrough('   ', 'CMDB', $this->templates, null);
        $this->assertStringContainsString('Steps for CMDB.', $result);
    }

    public function testFallsBackToComingSoonWhenNeitherExists(): void
    {
        $result = csa_resolve_walkthrough(null, 'Agile/VTB', $this->templates, null);
        $this->assertStringContainsString('Walkthrough coming soon!', $result);
        $this->assertStringContainsString('Agile/VTB', $result);
    }

    public function testServiceNowUrlPlaceholderIsSubstitutedWhenSet(): void
    {
        $result = csa_resolve_walkthrough(null, 'CMDB', $this->templates, 'https://dev12345.service-now.com');
        $this->assertStringContainsString('Your ServiceNow instance: https://dev12345.service-now.com', $result);
        $this->assertStringNotContainsString('{{SERVICE_NOW_URL}}', $result);
    }

    public function testServiceNowUrlTrailingSlashIsStripped(): void
    {
        $result = csa_resolve_walkthrough(null, 'CMDB', $this->templates, 'https://dev12345.service-now.com/');
        $this->assertStringContainsString('Your ServiceNow instance: https://dev12345.service-now.com', $result);
        $this->assertStringNotContainsString('.com//', $result);
    }

    public function testServiceNowUrlLineIsDroppedEntirelyWhenNotSet(): void
    {
        // No nudge to go configure one — the line just isn't there.
        $result = csa_resolve_walkthrough(null, 'CMDB', $this->templates, null);
        $this->assertStringNotContainsString('{{SERVICE_NOW_URL}}', $result);
        $this->assertStringNotContainsString('Your ServiceNow instance:', $result);
        $this->assertStringNotContainsString('Account settings', $result);
        $this->assertSame('Steps for CMDB.', $result);
    }

    public function testEmptyStringServiceNowUrlIsTreatedAsNotSet(): void
    {
        $result = csa_resolve_walkthrough(null, 'CMDB', $this->templates, '  ');
        $this->assertStringNotContainsString('Your ServiceNow instance:', $result);
        $this->assertSame('Steps for CMDB.', $result);
    }

    public function testTemplateWithoutAPlaceholderIsUnaffectedByMissingUrl(): void
    {
        // "Forms" template has no {{SERVICE_NOW_URL}} token at all — must pass through unchanged.
        $result = csa_resolve_walkthrough(null, 'Forms', $this->templates, null);
        $this->assertSame('Steps for Forms, no placeholder here.', $result);
    }

    public function testTemplateWithoutAPlaceholderIsUnaffectedWhenUrlIsSet(): void
    {
        $result = csa_resolve_walkthrough(null, 'Forms', $this->templates, 'https://dev12345.service-now.com');
        $this->assertSame('Steps for Forms, no placeholder here.', $result);
    }
}
