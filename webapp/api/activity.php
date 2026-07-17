<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
$pdo = csa_db();

$stmt = $pdo->prepare(
    "SELECT DISTINCT DATE(last_reviewed_at) AS d FROM flashcard_progress WHERE user_id = ? AND last_reviewed_at IS NOT NULL
     UNION
     SELECT DISTINCT DATE(started_at) AS d FROM exam_attempts WHERE user_id = ?"
);
$stmt->execute([$uid, $uid]);
$dates = array_map(fn($r) => $r['d'], $stmt->fetchAll());
$dateSet = array_flip($dates);

// Current streak: count consecutive days ending today or yesterday.
$streak = 0;
$cursor = new DateTime('today');
if (!isset($dateSet[$cursor->format('Y-m-d')])) {
    $cursor->modify('-1 day');
}
while (isset($dateSet[$cursor->format('Y-m-d')])) {
    $streak++;
    $cursor->modify('-1 day');
}

// Last 30 days activity grid.
$last30 = [];
$cursor = new DateTime('today');
for ($i = 0; $i < 30; $i++) {
    $dateStr = $cursor->format('Y-m-d');
    $last30[] = ['date' => $dateStr, 'active' => isset($dateSet[$dateStr])];
    $cursor->modify('-1 day');
}
$last30 = array_reverse($last30);

json_out(['streak' => $streak, 'last30' => $last30]);
