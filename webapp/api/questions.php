<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/walkthrough.php';

$uid = require_login();
$pdo = csa_db();

$categoryTemplates = require __DIR__ . '/../lib/walkthrough_templates.php';

$userStmt = $pdo->prepare('SELECT service_now_url FROM users WHERE id = ?');
$userStmt->execute([$uid]);
$serviceNowUrl = $userStmt->fetchColumn() ?: null;

// Optional Incorrect Review mode: restrict to whatever this user got wrong
// on a specific exam attempt, derived server-side (never trust a
// client-supplied ID list) from exam_answers rather than any client input.
$attemptId = (int)($_GET['attemptId'] ?? 0);
$restrictToIds = null;
if ($attemptId > 0) {
    $attemptStmt = $pdo->prepare('SELECT id FROM exam_attempts WHERE id = ? AND user_id = ?');
    $attemptStmt->execute([$attemptId, $uid]);
    if (!$attemptStmt->fetch()) {
        json_error('Attempt not found', 404);
    }
    $wrongStmt = $pdo->prepare('SELECT question_id FROM exam_answers WHERE attempt_id = ? AND is_correct = 0');
    $wrongStmt->execute([$attemptId]);
    $restrictToIds = array_flip(array_map('intval', $wrongStmt->fetchAll(PDO::FETCH_COLUMN)));
}

$questions = $pdo->query(
    'SELECT id, source, orig_num, question_text, choose_n, category, explanation, wrong_answer_notes, confidence, walkthrough FROM questions ORDER BY id'
)->fetchAll();

$options = $pdo->query('SELECT id, question_id, letter, option_text, is_correct FROM options ORDER BY question_id, letter')->fetchAll();

$progressRows = $pdo->prepare('SELECT question_id, status, box, due_at, note, last_confidence FROM flashcard_progress WHERE user_id = ?');
$progressRows->execute([$uid]);
$progress = [];
foreach ($progressRows->fetchAll() as $row) {
    $progress[(int)$row['question_id']] = [
        'status' => $row['status'],
        'box' => (int)$row['box'],
        'dueAt' => $row['due_at'],
        'note' => $row['note'],
        'selfConfidence' => $row['last_confidence'] !== null ? (int)$row['last_confidence'] : null,
    ];
}

$optionsByQ = [];
foreach ($options as $o) {
    $optionsByQ[(int)$o['question_id']][] = [
        'letter' => $o['letter'],
        'text' => $o['option_text'],
        'correct' => (bool)$o['is_correct'],
    ];
}

$now = date('Y-m-d H:i:s');
$out = [];
foreach ($questions as $q) {
    $qid = (int)$q['id'];
    if ($restrictToIds !== null && !isset($restrictToIds[$qid])) {
        continue;
    }
    $p = $progress[$qid] ?? null;
    $out[] = [
        'id' => $qid,
        'source' => $q['source'],
        'origNum' => (int)$q['orig_num'],
        'text' => $q['question_text'],
        'chooseN' => (int)$q['choose_n'],
        'category' => $q['category'],
        'explanation' => $q['explanation'],
        'wrongAnswerNotes' => $q['wrong_answer_notes'],
        'confidence' => $q['confidence'],
        'walkthrough' => csa_resolve_walkthrough(
            $q['walkthrough'],
            $q['category'],
            $categoryTemplates,
            $serviceNowUrl,
            $q['question_text'],
            csa_correct_answer_summary($optionsByQ[$qid] ?? [])
        ),
        'options' => $optionsByQ[$qid] ?? [],
        'progress' => $p['status'] ?? 'unseen',
        'box' => $p['box'] ?? 0,
        'dueAt' => $p['dueAt'] ?? null,
        'due' => $p === null || $p['dueAt'] === null || $p['dueAt'] <= $now,
        'note' => $p['note'] ?? null,
        'selfConfidence' => $p['selfConfidence'] ?? null,
    ];
}

json_out(['questions' => $out]);
