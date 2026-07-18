<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/exam_planning.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$pdo = csa_db();
$config = csa_config()['exam'];

$in = json_input();
$requestedCount = (int)($in['count'] ?? 0);
$allowedCounts = [25, 50, 100, 274];

// Abandon any stale in_progress attempt before starting a fresh one.
$pdo->prepare("UPDATE exam_attempts SET status = 'abandoned' WHERE user_id = ? AND status = 'in_progress'")
    ->execute([$uid]);

$allIds = $pdo->query('SELECT id FROM questions ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$fullTotal = count($allIds);

$plan = csa_plan_exam($requestedCount, $allowedCounts, 274, $fullTotal, (int)$config['duration_seconds']);
$count = $plan['count'];
$durationSeconds = $plan['durationSeconds'];

shuffle($allIds);
$selectedIds = array_slice($allIds, 0, $count);

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

// Shuffle each question's option display order and persist it, so resume/review stay consistent.
$optionOrder = [];
foreach ($optionsByQ as $qid => $opts) {
    shuffle($opts);
    $optionsByQ[$qid] = $opts;
    $optionOrder[$qid] = array_map(fn($o) => $o['letter'], $opts);
}

$stmt = $pdo->prepare(
    'INSERT INTO exam_attempts (user_id, duration_seconds, total_questions, question_ids, option_order, status)
     VALUES (?, ?, ?, ?, ?, "in_progress")'
);
$stmt->execute([$uid, $durationSeconds, $count, json_encode($selectedIds), json_encode($optionOrder)]);
$attemptId = (int)$pdo->lastInsertId();

$out = [];
foreach ($selectedIds as $qid) {
    $q = $questionsById[$qid];
    $out[] = [
        'id' => $qid,
        'text' => $q['question_text'],
        'chooseN' => (int)$q['choose_n'],
        'category' => $q['category'],
        'options' => $optionsByQ[$qid] ?? [],
    ];
}

json_out([
    'attemptId' => $attemptId,
    'durationSeconds' => $durationSeconds,
    'questions' => $out,
]);
