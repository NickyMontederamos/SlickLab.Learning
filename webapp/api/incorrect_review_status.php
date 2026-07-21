<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/incorrect_review.php';

$uid = require_login();
$attemptId = (int)($_GET['attemptId'] ?? 0);
if ($attemptId <= 0) {
    json_error('Invalid attemptId');
}

$pdo = csa_db();

$attemptStmt = $pdo->prepare('SELECT id FROM exam_attempts WHERE id = ? AND user_id = ?');
$attemptStmt->execute([$attemptId, $uid]);
if (!$attemptStmt->fetch()) {
    json_error('Attempt not found', 404);
}

$wrongStmt = $pdo->prepare('SELECT question_id FROM exam_answers WHERE attempt_id = ? AND is_correct = 0');
$wrongStmt->execute([$attemptId]);
$incorrectIds = array_map('intval', $wrongStmt->fetchAll(PDO::FETCH_COLUMN));

$statuses = [];
if (count($incorrectIds)) {
    $placeholders = implode(',', array_fill(0, count($incorrectIds), '?'));
    $progressStmt = $pdo->prepare(
        "SELECT question_id, status FROM flashcard_progress WHERE user_id = ? AND question_id IN ($placeholders)"
    );
    $progressStmt->execute([$uid, ...$incorrectIds]);
    $statusByQuestion = [];
    foreach ($progressStmt->fetchAll() as $row) {
        $statusByQuestion[(int)$row['question_id']] = $row['status'];
    }
    foreach ($incorrectIds as $qid) {
        $statuses[] = $statusByQuestion[$qid] ?? 'unseen';
    }
}

$readiness = csa_compute_review_readiness($statuses);

json_out([
    'attemptId' => $attemptId,
    'incorrectQuestionIds' => $incorrectIds,
    'total' => $readiness['total'],
    'knownCount' => $readiness['knownCount'],
    'knownRate' => $readiness['knownRate'],
    'ready' => $readiness['ready'],
]);
