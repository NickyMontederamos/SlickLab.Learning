<?php

/**
 * Resolves the "Show Me How" walkthrough text for a question:
 * 1. A per-question walkthrough, if the question has one set.
 * 2. Otherwise a category-level template, if one exists for this category.
 * 3. Otherwise a friendly "coming soon" fallback.
 *
 * {{SERVICE_NOW_URL}} is substituted last, on whichever text was chosen —
 * with a helpful placeholder if the user hasn't set one in Account settings.
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

    $urlForDisplay = ($serviceNowUrl !== null && trim($serviceNowUrl) !== '')
        ? rtrim(trim($serviceNowUrl), '/')
        : 'your ServiceNow instance (set this in Account settings for a direct link)';

    return str_replace('{{SERVICE_NOW_URL}}', $urlForDisplay, $text);
}
