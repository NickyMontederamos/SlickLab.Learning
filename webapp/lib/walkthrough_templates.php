<?php

/**
 * Category-level generic walkthrough templates for the "Show Me How"
 * feature. Categories not listed here fall back to the "coming soon"
 * message in csa_resolve_walkthrough().
 *
 * DRAFT CONTENT — written from general, well-established ServiceNow UI
 * conventions (the "ALL" application navigator, <table>_list.do URLs,
 * standard form Submit/Update buttons), not verified against a specific
 * live instance. Review and correct each one against your actual
 * ServiceNow instance before treating it as authoritative — see
 * SOLUTIONS_LOG.md, 2026-07-21 entry, for why this wasn't done for all 25
 * categories in the seeded question bank up front.
 *
 * Keys must match the `category` column in the questions table exactly
 * (case-sensitive).
 */
return [
    'Service Catalog' => <<<'TXT'
You are already in ServiceNow Dashboards.

Let's work with the Service Catalog step-by-step:

1. Open the Service Catalog: click "ALL" at the top of the left navigation menu (this opens the full application filter). In the filter/search box that appears, type: catalog_home.do and press Enter.

2. Browse or Search for an Item: on the Service Catalog homepage, either browse the category tiles, or use the search box at the top of the page to search for the specific item mentioned in this question.

3. Open the Catalog Item: click the item's name or tile to open its Request form.

4. Fill in the Required Fields: any field marked with a red asterisk (*) is mandatory. Fill these based on the scenario in the question.

5. Add to Cart / Order Now: click "Add to Cart" (to review before submitting) or "Order Now" (to submit immediately) — usually near the top-right or bottom of the item form.

6. Review Your Cart (if used): click the cart icon at the top of the page, review the item(s), then click "Checkout" / "Submit Order".

7. Verify It Worked: you'll land on an order confirmation / Request (RITM) page showing a request number (e.g. RITM0012345). To double-check later, click "ALL", type sc_req_item_list.do, and press Enter to see your request in the list.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'CMDB' => <<<'TXT'
You are already in ServiceNow Dashboards.

Let's work with the CMDB (Configuration Management Database) step-by-step:

1. Open the CMDB: click "ALL" at the top-left of the left navigation menu. In the filter box, type cmdb_ci_list.do (the general Configuration Item list) and press Enter — or a more specific table name if the question names a specific CI class (e.g. cmdb_ci_server_list.do for Servers).

2. Search or Filter: use the column filter row (click the funnel icon, or type directly into a column's filter box) to narrow down to the specific CI mentioned in the question.

3. Open a Configuration Item: click the CI's name to open its record.

4. Review or Edit Fields: locate the field named in the question (e.g. "Operational Status", "Assigned to", "Install status"). Click inside it to edit, or use the dropdown if it's a choice field.

5. Check Relationships (if relevant): scroll to the "Related Items" or "CI Relationships" related list at the bottom of the form to see what this CI depends on or is depended on by.

6. Save: click "Update" (top-right or bottom of the form).

7. Verify It Worked: you should return to the CI list, or see a confirmation banner. Reopen the record to confirm your change was saved.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Forms' => <<<'TXT'
You are already in ServiceNow Dashboards.

Let's work with Form Layout / Form Design step-by-step:

1. Open Form Layout for a Table: click "ALL" at the top-left. In the filter box, type the table's list view (e.g. incident_list.do), press Enter, then right-click the grey form header bar on a record (or a column header on the list) and select "Configure > Form Layout".

2. Add or Remove Fields: in the Form Layout screen you'll see two columns — "Available fields" (left) and the fields already on the form (right). Select a field on the left and click the right-arrow to add it, or select one on the right and click the left-arrow to remove it.

3. Reorder Fields (if needed): drag a field up or down within the right-hand list to change where it appears on the form.

4. Add a Section (if needed): above the field list, use "Add" under Form Sections if the question asks you to create a new tab/section.

5. Save: click "Save" (top-right of the Form Layout screen).

6. Verify It Worked: navigate back to a record on that table and confirm the field now appears (or no longer appears) on the form, in the position you set.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Navigation' => <<<'TXT'
You are already in ServiceNow Dashboards.

Let's practice core Navigation step-by-step:

1. Use the Application Navigator: at the very top-left of the screen, click "ALL" to open the full application/module filter menu.

2. Filter to Find a Module: start typing the name of the application or module mentioned in the question (e.g. "Incident", "Service Catalog", "Reports") into the filter box — matching modules highlight as you type.

3. Jump to a Table Directly: instead of browsing, type a table's list-view name directly into the same filter box (e.g. incident_list.do, sys_user_list.do) and press Enter to go straight there.

4. Use Favorites: hover a module in the navigator until a star icon appears, then click it to add the module to "My Favorites" at the top of the navigator.

5. Use the History: click the clock/history icon near the top of the navigator to see recently visited pages.

6. Verify It Worked: confirm the page that loaded matches what the question asked for (correct table, correct list or record).

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Security & ACL' => <<<'TXT'
You are already in ServiceNow Dashboards. ACL records are only visible/editable if your account has the "security_admin" role, or "Elevate Role" access — do this first if needed.

Let's work with Access Control (ACLs) step-by-step:

1. Elevate Your Role (if needed): click your user icon/name at the top-right, choose "Elevate Role", check "security_admin", click "OK".

2. Open the Access Control List: click "ALL", type sys_security_acl_list.do in the filter box, press Enter.

3. Find or Create an ACL: use the column filters to search for an existing ACL on the table/field named in the question, or click "New" (top-right of the list) to create one.

4. Set the Type and Target: on the ACL form, set "Type" (record, field, etc.), then "Name" — pick the table (and field, if Type = field) the question refers to, and the "Operation" (read/write/create/delete).

5. Set the Requirement: scroll to the "Requires role" related list at the bottom of the form and click "Edit" (or "New") to add the role(s) named in the question.

6. Set Condition/Script (if the question asks for conditional access): use the "Condition" builder or the "Script" field.

7. Save: click "Submit" or "Update".

8. Verify It Worked: use your user icon > "Impersonate User" to log in as a user with/without the required role, and confirm they can/can't perform the controlled action.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,
];
