<?php

/**
 * Resolves the "Show Me How" walkthrough text for a question:
 * 1. A per-question walkthrough, if the question has one set.
 * 2. Otherwise a category-level template, if one exists for this category.
 * 3. Otherwise a friendly "coming soon" fallback.
 *
 * {{SERVICE_NOW_URL}} is substituted last, on whichever text was chosen. If
 * no URL is configured, the entire line containing the placeholder is
 * dropped rather than replaced with a nudge to go set one — the steps
 * stand alone without it, and the user shouldn't be pestered to configure
 * anything just to read them.
 */
function csa_resolve_walkthrough(
    ?string $questionWalkthrough,
    string $category,
    array $categoryTemplates,
    ?string $serviceNowUrl
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

    if ($serviceNowUrl !== null && trim($serviceNowUrl) !== '') {
        $urlForDisplay = rtrim(trim($serviceNowUrl), '/');
        return str_replace('{{SERVICE_NOW_URL}}', $urlForDisplay, $text);
    }

    $lines = explode("\n", $text);
    $lines = array_filter($lines, fn($line) => !str_contains($line, '{{SERVICE_NOW_URL}}'));
    return rtrim(implode("\n", $lines));
}
