<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
$pdo = csa_db();

$stmt = $pdo->prepare(
    "SELECT q.category, COUNT(*) AS total, SUM(ea.is_correct) AS correct
     FROM exam_answers ea
     JOIN exam_attempts att ON att.id = ea.attempt_id
     JOIN questions q ON q.id = ea.question_id
     WHERE att.user_id = ? AND att.status = 'completed'
     GROUP BY q.category"
);
$stmt->execute([$uid]);

$rows = array_map(function ($r) {
    $total = (int)$r['total'];
    $correct = (int)$r['correct'];
    return [
        'category' => $r['category'],
        'total' => $total,
        'correct' => $correct,
        'percent' => $total > 0 ? round(($correct / $total) * 100, 1) : 0,
    ];
}, $stmt->fetchAll());

usort($rows, fn($a, $b) => $a['percent'] <=> $b['percent']);

json_out(['categories' => $rows]);
