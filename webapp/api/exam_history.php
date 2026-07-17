<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
$pdo = csa_db();

$stmt = $pdo->prepare(
    "SELECT id, started_at, submitted_at, total_questions, correct_count, score_percent, passed, status
     FROM exam_attempts WHERE user_id = ? AND status = 'completed' ORDER BY id DESC LIMIT 25"
);
$stmt->execute([$uid]);

$out = array_map(function ($row) {
    return [
        'attemptId' => (int)$row['id'],
        'startedAt' => $row['started_at'],
        'submittedAt' => $row['submitted_at'],
        'total' => (int)$row['total_questions'],
        'correctCount' => (int)$row['correct_count'],
        'scorePercent' => (float)$row['score_percent'],
        'passed' => (bool)$row['passed'],
        'status' => $row['status'],
    ];
}, $stmt->fetchAll());

json_out(['attempts' => $out]);
