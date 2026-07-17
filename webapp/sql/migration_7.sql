-- Fix 24 answer-key mismatches found by comparing the app's question bank
-- against the official CSA MOCK 1 / CSA MOCK 2 PDFs (red-marked correct answers).
-- All 24 -- including q049, q218, and q238, which went through a second round of
-- manual human verification against the physical PDFs after initial automated
-- extraction flagged them as uncertain -- are confirmed final.
-- Safe to re-run; only touches options.is_correct for these specific questions.

-- q011 (CSA_MOCK_2 #11): ['C']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 11;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 11 AND o.letter IN ('C');

-- q026 (CSA_MOCK_2 #27): ['D']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 27;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 27 AND o.letter IN ('D');

-- q028 (CSA_MOCK_2 #29): ['A', 'C', 'D']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 29;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 29 AND o.letter IN ('A','C','D');

-- q049 (CSA_MOCK_2 #50): ['A']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 50;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 50 AND o.letter IN ('A');

-- q054 (CSA_MOCK_2 #55): ['B', 'E', 'F', 'I']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 55;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 55 AND o.letter IN ('B','E','F','I');

-- q061 (CSA_MOCK_2 #62): ['C', 'D']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 62;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 62 AND o.letter IN ('C','D');

-- q064 (CSA_MOCK_2 #65): ['B']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 65;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 65 AND o.letter IN ('B');

-- q072 (CSA_MOCK_2 #73): ['A', 'B', 'C', 'D']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 73;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 73 AND o.letter IN ('A','B','C','D');

-- q080 (CSA_MOCK_2 #81): ['E']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 81;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 81 AND o.letter IN ('E');

-- q091 (CSA_MOCK_2 #91): ['C']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 91;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 91 AND o.letter IN ('C');

-- q092 (CSA_MOCK_2 #92): ['B']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 92;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 92 AND o.letter IN ('B');

-- q142 (CSA_MOCK_2 #143): ['D']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 143;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 143 AND o.letter IN ('D');

-- q149 (CSA_MOCK_2 #150): ['C']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 150;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 150 AND o.letter IN ('C');

-- q179 (CSA_MOCK_2 #181): ['B', 'D']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 181;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'CSA_MOCK_2' AND q.orig_num = 181 AND o.letter IN ('B','D');

-- q215 (MOCK_EXAM_CSA #19): ['B']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 19;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 19 AND o.letter IN ('B');

-- q218 (MOCK_EXAM_CSA #23): ['E']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 23;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 23 AND o.letter IN ('E');

-- q231 (MOCK_EXAM_CSA #37): ['B', 'C', 'E']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 37;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 37 AND o.letter IN ('B','C','E');

-- q237 (MOCK_EXAM_CSA #43): ['A', 'D', 'E']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 43;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 43 AND o.letter IN ('A','D','E');

-- q238 (MOCK_EXAM_CSA #44): ['D']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 44;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 44 AND o.letter IN ('D');

-- q246 (MOCK_EXAM_CSA #52): ['B']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 52;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 52 AND o.letter IN ('B');

-- q255 (MOCK_EXAM_CSA #61): ['C']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 61;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 61 AND o.letter IN ('C');

-- q258 (MOCK_EXAM_CSA #64): ['D']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 64;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 64 AND o.letter IN ('D');

-- q266 (MOCK_EXAM_CSA #72): ['A', 'D', 'E']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 72;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 72 AND o.letter IN ('A','D','E');

-- q270 (MOCK_EXAM_CSA #76): ['A', 'C', 'D', 'F']
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 0 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 76;
UPDATE options o JOIN questions q ON q.id = o.question_id SET o.is_correct = 1 WHERE q.source = 'MOCK_EXAM_CSA' AND q.orig_num = 76 AND o.letter IN ('A','C','D','F');


-- Add 5 legitimate questions from the official PDFs that were never added to the app.
-- (A 6th missing question, CSA_MOCK_2 #111, is a confirmed near-duplicate of #103
-- already in the app and is intentionally NOT added.)

-- q275 (MOCK_EXAM_CSA #12)
INSERT INTO questions (source, orig_num, question_text, choose_n, category, explanation, wrong_answer_notes, confidence) VALUES ('MOCK_EXAM_CSA', 12, 'Which ServiceNow utility gives a Service Desk agent the ability to trace from a Service having an issue, to see which CIs supporting that service have active issues?', 1, 'CMDB', 'CI Dependency View lets an agent trace from a Service down to the CIs supporting it and see which of those CIs currently have active issues affecting that service.', 'Event Management Homepage surfaces alerts/events but doesn''t trace service-to-CI dependency; Service Dashboard and CI Health Dashboard show status summaries, not the specific dependency-tracing view this question describes.', 'high');
SET @qid := LAST_INSERT_ID();
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'A', 'CI Dependency View', 1);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'B', 'Event Management Homepage', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'C', 'Service Dashboard', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'D', 'CI Health Dashboard', 0);

-- q276 (MOCK_EXAM_CSA #21)
INSERT INTO questions (source, orig_num, question_text, choose_n, category, explanation, wrong_answer_notes, confidence) VALUES ('MOCK_EXAM_CSA', 21, 'An IT manager is responsible for the Network and Hardware assignment groups, each group contains 5 team members. These team members are working on many tasks, but the manager cannot see any tasks on the Service Desk > My Groups Work list. What could explain this?', 1, 'Security & Roles', '''My Groups Work'' only shows tasks for groups the logged-in user is actually a member of. Being set as the group''s manager doesn''t automatically add someone as a member, so if the manager isn''t personally in the Network and Hardware groups, their tasks won''t appear on this list.', 'An empty manager field would be unrelated to what a member sees on their own work list; the itil role controls broader ITSM access, not specifically which groups show on ''My Groups Work''; membership in the Service Desk group is irrelevant since this list is scoped to the Network and Hardware groups, not Service Desk.', 'high');
SET @qid := LAST_INSERT_ID();
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'A', 'The Assignment Group manager field is empty.', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'B', 'The manager does not have the itil role.', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'C', 'The manager is not a member of the Service Desk group.', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'D', 'The manager is not a member of the Network and Hardware groups.', 1);

-- q277 (MOCK_EXAM_CSA #36)
INSERT INTO questions (source, orig_num, question_text, choose_n, category, explanation, wrong_answer_notes, confidence) VALUES ('MOCK_EXAM_CSA', 36, 'Which tool is used to define relationships between fields in an import set table and a target table?', 1, 'Data Import', 'A Transform Map defines the field-to-field mapping between an import set (staging) table and the target table it''s importing data into.', '''Schema Map'', ''Field Transformer'', and ''Transform Schema'' are not real ServiceNow feature names — Transform Map is the actual term used for this mapping tool.', 'high');
SET @qid := LAST_INSERT_ID();
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'A', 'Schema Map', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'B', 'Field Transformer', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'C', 'Transform Map', 1);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'D', 'Transform Schema', 0);

-- q278 (CSA_MOCK_2 #19)
INSERT INTO questions (source, orig_num, question_text, choose_n, category, explanation, wrong_answer_notes, confidence) VALUES ('CSA_MOCK_2', 19, 'On the knowledge base record, which tab would you use to define which users are not able to write articles to the knowledge base?', 1, 'Knowledge Management', 'The ''Cannot Author'' related list on a Knowledge Base record explicitly blocks specified users or groups from contributing/writing articles to that base.', '''Can Contribute'', ''Can Read'', ''Can Write'', and ''Can Author'' all define who CAN take an action — only ''Cannot Author'' is the explicit block list this question is asking about.', 'high');
SET @qid := LAST_INSERT_ID();
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'A', 'Can Contribute', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'B', 'Cannot Author', 1);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'C', 'Can Read', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'D', 'Can Write', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'E', 'Can Author', 0);

-- q279 (CSA_MOCK_2 #186)
INSERT INTO questions (source, orig_num, question_text, choose_n, category, explanation, wrong_answer_notes, confidence) VALUES ('CSA_MOCK_2', 186, 'A customer requests the following data quality measures be added: ✑ Incident numbers should be read only, on all lists and forms, for all users. ✑ Short Description field should be mandatory, on all records, across all applications, on Insert. Which type of policy would you use to meet this requirement?', 1, 'Forms', 'A UI Policy can make a field read-only or mandatory across all forms and lists for a table based on conditions, which covers both requirements described here.', '''Data Quality Policy'', ''Dictionary Design Policy'', ''UI Data Policy'', and ''Field Criteria Policy'' are not real ServiceNow policy types; Data Policy specifically enforces rules outside the UI (e.g. via API/import) and isn''t the primary tool for form/list field behavior described here.', 'high');
SET @qid := LAST_INSERT_ID();
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'A', 'Data Quality Policy', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'B', 'Dictionary Design Policy', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'C', 'UI Data Policy', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'D', 'UI Policy', 1);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'E', 'Field Criteria Policy', 0);
INSERT INTO options (question_id, letter, option_text, is_correct) VALUES (@qid, 'F', 'Data Policy', 0);
