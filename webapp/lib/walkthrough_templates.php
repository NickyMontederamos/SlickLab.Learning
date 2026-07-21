<?php

/**
 * Category-level generic walkthrough templates for the "Show Me How"
 * feature. Categories not listed here fall back to the "coming soon"
 * message in csa_resolve_walkthrough().
 *
 * Each template opens with {{QUESTION_CONTEXT}} and {{CORRECT_ANSWER}},
 * substituted from the actual flashcard's own data — so even though the
 * steps below are generic per category, the resolved text always names
 * the specific question and its correct answer, not just "here's how this
 * feature works in general."
 *
 * DRAFT CONTENT — written from general, well-established ServiceNow UI
 * conventions (the "ALL" application navigator, <table>_list.do URLs,
 * standard form Submit/Update buttons), not verified against a specific
 * live instance. Review and correct each one against your actual
 * ServiceNow instance before treating it as authoritative — see
 * SOLUTIONS_LOG.md, 2026-07-21 entries, for why this wasn't done for all
 * 25 categories in the seeded question bank up front.
 *
 * Keys must match the `category` column in the questions table exactly
 * (case-sensitive).
 */
return [
    'Service Catalog' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow:
1. Click "ALL" (top-left nav) > type catalog_home.do > Enter.
2. Find the catalog item this question is about and open it.
3. Fill the required fields (marked *) to match the scenario above.
4. Click "Order Now" (or "Add to Cart" then "Checkout").
5. Confirm you land on a request number (RITM...). To recheck later: "ALL" > sc_req_item_list.do.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'CMDB' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow:
1. Click "ALL" (top-left nav) > type cmdb_ci_list.do (or a specific CI class list, e.g. cmdb_ci_server_list.do) > Enter.
2. Filter/search for a CI matching the scenario above.
3. Open it and locate the field(s) the question is about.
4. Make the change described above, then click "Update".
5. Reopen the record to confirm it saved.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Forms' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow:
1. Open any record on the relevant table (e.g. an Incident).
2. Right-click the grey form header bar > Configure > Form Layout.
3. Move the field(s) this question refers to between "Available fields" and the form, using the arrow buttons.
4. Click "Save".
5. Reopen a record on that table to confirm the field appears (or doesn't) as expected.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Navigation' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow:
1. Click "ALL" (top-left nav) to open the application filter.
2. Type the module or table this question is about into the filter box (e.g. a list view like incident_list.do).
3. Press Enter, or click the matching module.
4. Confirm the page that loads matches the scenario above.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Security & ACL' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow (needs the security_admin role — user icon > "Elevate Role" if you don't have it active):
1. Click "ALL" > type sys_security_acl_list.do > Enter.
2. Find or create the ACL for the table/field this question is about.
3. Set Type, Name (table/field), and Operation to match the scenario above.
4. Under "Requires role" (bottom of the form), add the role(s) described above.
5. Click "Update", then verify with user icon > "Impersonate User".

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,
];
