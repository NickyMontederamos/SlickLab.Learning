<?php

/**
 * Resolves the "Show Me How" walkthrough text for a question:
 * 1. A per-question walkthrough, if the question has one set.
 * 2. Otherwise a category-level template, if one exists for this category.
 * 3. Otherwise a friendly "coming soon" fallback.
 *
 * {{QUESTION_CONTEXT}} and {{CORRECT_ANSWER}} are substituted from the
 * question's own data (not invented) so even a shared category template
 * reads as specific to the flashcard in front of the user, not a generic
 * "how this feature works in general" blurb.
 *
 * {{SERVICE_NOW_URL}} is substituted last. If no URL is configured, the
 * entire line containing it is dropped rather than replaced with a nudge to
 * go set one — the steps stand alone without it.
 */
function csa_resolve_walkthrough(
    ?string $questionWalkthrough,
    string $category,
    array $categoryTemplates,
    ?string $serviceNowUrl,
    string $questionContext,
    string $correctAnswerContext
): string {
    $text = null;
    if ($questionWalkthrough !== null && trim($questionWalkthrough) !== '') {
        $text = $questionWalkthrough;
    } elseif (isset($categoryTemplates[$category]) && trim($categoryTemplates[$category]) !== '') {
        $text = $categoryTemplates[$category];
    }

    if ($text === null) {
        return "Walkthrough coming soon! We haven't written a hands-on guide for this question or its category (\"{$category}\") yet.";
    }

    $text = str_replace(
        ['{{QUESTION_CONTEXT}}', '{{CORRECT_ANSWER}}'],
        [$questionContext, $correctAnswerContext],
        $text
    );

    if ($serviceNowUrl !== null && trim($serviceNowUrl) !== '') {
        $urlForDisplay = rtrim(trim($serviceNowUrl), '/');
        return str_replace('{{SERVICE_NOW_URL}}', $urlForDisplay, $text);
    }

    $lines = explode("\n", $text);
    $lines = array_filter($lines, fn($line) => !str_contains($line, '{{SERVICE_NOW_URL}}'));
    return rtrim(implode("\n", $lines));
}

/**
 * Builds the {{CORRECT_ANSWER}} text from a question's options: "A: Text"
 * for a single answer, "A: Text; C: Text" for multi-select. Shared by every
 * caller of csa_resolve_walkthrough() so this logic exists exactly once.
 *
 * @param array $options Each entry: ['letter' => string, 'text' => string, 'correct' => bool]
 */
function csa_correct_answer_summary(array $options): string
{
    $correct = array_filter($options, fn($o) => $o['correct']);
    $parts = array_map(fn($o) => "{$o['letter']}: {$o['text']}", $correct);
    return implode('; ', $parts);
}
