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
 * categories in the seeded question bank up front. The remaining 17
 * categories (added 2026-07-23) carry the same caveat.
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

    'Platform Admin' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow (may need the admin role):
1. Click "ALL" > type sys_properties.list (or the specific admin module the question refers to) > Enter.
2. Locate the property/setting this question is about.
3. Note or change its value to match the scenario above.
4. Click "Save" (or "Update" if on a form).

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Users/Groups/Roles' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow (needs the admin or user_admin role):
1. Click "ALL" > type sys_user_list.do (for a user), sys_user_group_list.do (for a group), or sys_user_role_list.do (for a role) depending on the scenario above.
2. Find or create the record the question is about.
3. Open it and locate the field(s)/relationship(s) referenced above (e.g. Roles related list, Group Members related list).
4. Make the change described, then click "Update".

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Lists & Filters' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow:
1. Open any list (e.g. Incident > All).
2. Use the filter/condition row above the column headers to build a condition matching the scenario above.
3. Click the gear icon (list personalization) to add/remove/reorder columns as described.
4. Confirm the resulting list matches what the question describes.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Data Model' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow (needs the admin role):
1. Click "ALL" > type sys_db_object_list.do to browse tables, or open a specific table's Dictionary via System Definition > Dictionary.
2. Find the table/field this question is about.
3. Check its Type, extends-relationship, or reference field configuration to match the scenario above.
4. If applicable, try dot-walking a reference field (e.g. in a report or filter) to see the relationship in action.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Business Logic' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow (needs the admin role):
1. Click "ALL" > type sys_script_list.do (Business Rules) or sys_script_include_list.do (Script Includes).
2. Find or create the rule/include this question is about.
3. Check its "When to run" (before/after/async/display) and Order to match the scenario above.
4. Save, then trigger the condition (e.g. update the target record) and confirm the expected behavior.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Update Sets' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow:
1. Click "ALL" > type sys_update_set_list.do > Enter.
2. Create a new Update Set and switch to it as your current set (top-right picker).
3. Make a small configuration change (e.g. add a field) to see it captured automatically.
4. Preview the update set, review any conflicts, then Commit (only on a test instance) to match the scenario above.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Knowledge Management' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow:
1. Click "ALL" > type kb_knowledge_list.do > Enter.
2. Find or create the article this question is about.
3. Check its Workflow state (Draft/Published/Retired) and Knowledge Base/Category to match the scenario above.
4. If published, search for it from the Knowledge Portal to confirm visibility.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Notifications' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow (needs the admin role):
1. Click "ALL" > type sysevent_email_action_list.do > Enter.
2. Find or create the notification this question is about.
3. Check its trigger table/condition and "When to send" settings to match the scenario above.
4. Trigger the condition (e.g. update the target record) and check System Logs > Email > Sent to confirm it fired.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Reporting' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow:
1. Click "ALL" > type Reports > Create New (or open sys_report_list.do).
2. Choose the source table and report type (List/Bar/Pie/Trend) matching the scenario above.
3. Configure grouping/filter conditions as described, then Save.
4. Add it to a Dashboard if the question involves dashboard sharing.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Data Import' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow (needs the admin role):
1. Click "ALL" > type sys_data_source_list.do > Enter, or use System Import Sets > Load Data.
2. Upload or point to a small sample file matching the scenario above.
3. Run the import, then open the resulting Transform Map to check field mappings and any Coalesce settings.
4. Confirm the transformed record landed correctly on the target table.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Flow Designer' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow (needs the admin or flow_designer role):
1. Click "ALL" > type Flow Designer > Enter.
2. Create a new Flow with a trigger matching the scenario above (e.g. record created on a table).
3. Add the Action(s) described, then check the logic (If/Else, For Each) if relevant.
4. Activate the flow, then trigger the condition and confirm it ran (Flow Designer > Executions).

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Scripting & Automation' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow (needs the admin role):
1. Click "ALL" > type sys_script_client_list.do (Client Scripts) or sys_script_include_list.do (Script Includes).
2. Find or create the script this question is about.
3. Match its type (onLoad/onChange/onSubmit for Client Scripts) or reusable function (for Script Includes) to the scenario above.
4. Test by triggering the relevant form event, or by calling the Script Include from a Background Script (read-only queries only).

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Collaboration' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow:
1. Open any record (e.g. an Incident).
2. Use the Connect/Collaboration icon to start a conversation tied to that record, matching the scenario above.
3. If the question is about Live Feed, post an update there instead and try an @mention.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Virtual Agent' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow (needs the Virtual Agent plugin active):
1. Click "ALL" > type Virtual Agent Designer > Enter.
2. Find or create the Topic this question is about, matching its trigger phrase/intent to the scenario above.
3. Publish/activate the topic, then test it via the chat widget.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'ITSM & SLA' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow:
1. Click "ALL" > type incident_list.do (or problem_list.do / change_request_list.do) > Enter.
2. Create or open a record matching the scenario above.
3. If the question is about SLAs, check the SLA related list at the bottom of the form for the running timer/percentage.
4. Cross-reference the actual rule at "ALL" > contract_sla_list.do if the question is about SLA Definition conditions.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Testing/ATF' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow (needs the Automated Test Framework plugin active):
1. Click "ALL" > type Automated Test Framework > Tests > Enter.
2. Create or open a Test matching the scenario above.
3. Add/inspect its Test Steps (form actions) and any Assertion steps.
4. Run the test and check the Test Results for pass/fail per step.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,

    'Agile/VTB' => <<<'TXT'
This flashcard: "{{QUESTION_CONTEXT}}"
Correct answer: {{CORRECT_ANSWER}}

Try it in ServiceNow (needs the Agile Development / VTB plugin active):
1. Click "ALL" > type Visual Task Boards > Enter, or Agile Development > Stories for the planning side.
2. Create or open a board/story matching the scenario above.
3. If the question is about VTB, drag a card between columns and confirm the underlying record's field updates.
4. If the question is about Agile planning, check how the Story relates to its Sprint/Epic.

Your ServiceNow instance: {{SERVICE_NOW_URL}}
TXT,
];
