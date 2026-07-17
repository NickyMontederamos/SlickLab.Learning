<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
$pdo = csa_db();

$stmt = $pdo->prepare(
    "SELECT id, started_at, duration_seconds, total_questions, question_ids, option_order FROM exam_attempts
     WHERE user_id = ? AND status = 'in_progress' ORDER BY id DESC LIMIT 1"
);
$stmt->execute([$uid]);
$attempt = $stmt->fetch();

if (!$attempt) {
    json_out(['active' => false]);
}

$startedAt = strtotime($attempt['started_at']);
$elapsed = time() - $startedAt;
$remaining = max(0, (int)$attempt['duration_seconds'] - $elapsed);

$selectedIds = json_decode($attempt['question_ids'] ?? '[]', true);
if (!is_array($selectedIds) || !count($selectedIds)) {
    // Legacy attempts created before question_ids existed: fall back to all questions.
    $selectedIds = $pdo->query('SELECT id FROM questions ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
}

$placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
$stmt = $pdo->prepare("SELECT id, question_text, choose_n, category FROM questions WHERE id IN ($placeholders)");
$stmt->execute($selectedIds);
$questionsById = [];
foreach ($stmt->fetchAll() as $q) {
    $questionsById[(int)$q['id']] = $q;
}

$stmt = $pdo->prepare("SELECT question_id, letter, option_text FROM options WHERE question_id IN ($placeholders) ORDER BY question_id, letter");
$stmt->execute($selectedIds);
$optionsByQ = [];
foreach ($stmt->fetchAll() as $o) {
    $optionsByQ[(int)$o['question_id']][] = ['letter' => $o['letter'], 'text' => $o['option_text']];
}

// Re-apply the same shuffled option order that was shown when the attempt started.
$optionOrder = json_decode($attempt['option_order'] ?? '[]', true);
if (is_array($optionOrder)) {
    foreach ($optionsByQ as $qid => $opts) {
        $order = $optionOrder[$qid] ?? null;
        if (!$order) {
            continue;
        }
        $byLetter = [];
        foreach ($opts as $o) {
            $byLetter[$o['letter']] = $o;
        }
        $optionsByQ[$qid] = array_values(array_filter(array_map(fn($l) => $byLetter[$l] ?? null, $order)));
    }
}

$out = [];
foreach ($selectedIds as $qid) {
    if (!isset($questionsById[$qid])) {
        continue;
    }
    $q = $questionsById[$qid];
    $out[] = [
        'id' => (int)$qid,
        'text' => $q['question_text'],
        'chooseN' => (int)$q['choose_n'],
        'category' => $q['category'],
        'options' => $optionsByQ[$qid] ?? [],
    ];
}

json_out([
    'active' => true,
    'attemptId' => (int)$attempt['id'],
    'remainingSeconds' => $remaining,
    'totalQuestions' => (int)$attempt['total_questions'],
    'questions' => $out,
]);
