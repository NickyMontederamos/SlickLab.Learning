<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/flashcard_scheduling.php';

$uid = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$questionId = (int)($in['questionId'] ?? 0);
$result = $in['result'] ?? '';
$confidence = isset($in['confidence']) ? (int)$in['confidence'] : null;

if ($questionId <= 0 || !in_array($result, ['again', 'good'], true)) {
    json_error('Invalid questionId or result');
}
if ($confidence !== null && ($confidence < 1 || $confidence > 5)) {
    json_error('Invalid confidence');
}

$pdo = csa_db();
$stmt = $pdo->prepare('SELECT box FROM flashcard_progress WHERE user_id = ? AND question_id = ?');
$stmt->execute([$uid, $questionId]);
$row = $stmt->fetch();
$box = $row ? (int)$row['box'] : 0;

$next = csa_next_leitner_state($box, $result);
$box = $next['box'];
$status = $next['status'];
$dueMinutes = $next['dueMinutes'];

$stmt = $pdo->prepare(
    'INSERT INTO flashcard_progress (user_id, question_id, status, box, due_at, last_reviewed_at, last_confidence)
     VALUES (:uid, :qid, :status, :box, DATE_ADD(NOW(), INTERVAL :mins MINUTE), NOW(), :confidence)
     ON DUPLICATE KEY UPDATE status = VALUES(status), box = VALUES(box), due_at = VALUES(due_at), last_reviewed_at = NOW(),
       last_confidence = COALESCE(VALUES(last_confidence), last_confidence)'
);
$stmt->execute([
    ':uid' => $uid, ':qid' => $questionId, ':status' => $status, ':box' => $box,
    ':mins' => $dueMinutes, ':confidence' => $confidence,
]);

json_out(['ok' => true, 'box' => $box, 'status' => $status]);
