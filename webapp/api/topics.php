<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/topic_quiz.php';

$uid = require_login();
$pdo = csa_db();

$topics = $pdo->query('SELECT id, slug, name, category_key, sort_order, lesson_status FROM topics ORDER BY sort_order')->fetchAll();

$poolByCategory = [];
foreach ($pdo->query('SELECT category, COUNT(*) AS n FROM questions GROUP BY category')->fetchAll() as $row) {
    $poolByCategory[$row['category']] = (int)$row['n'];
}

$knownByCategory = [];
$masteryStmt = $pdo->prepare(
    'SELECT q.category, COUNT(*) AS known_count
     FROM flashcard_progress fp
     JOIN questions q ON q.id = fp.question_id
     WHERE fp.user_id = ? AND fp.status = "known"
     GROUP BY q.category'
);
$masteryStmt->execute([$uid]);
foreach ($masteryStmt->fetchAll() as $row) {
    $knownByCategory[$row['category']] = (int)$row['known_count'];
}

$passedStmt = $pdo->prepare(
    "SELECT DISTINCT topic_id FROM exam_attempts WHERE user_id = ? AND attempt_kind = 'topic' AND passed = 1 AND topic_id IS NOT NULL"
);
$passedStmt->execute([$uid]);
$passedTopicIds = array_map('intval', $passedStmt->fetchAll(PDO::FETCH_COLUMN));

// Passed blocks per topic, for the "Block N of M" progress each robust
// topic's card shows -- one query for every topic rather than one per card.
$passedBlocksByTopic = [];
$blockStmt = $pdo->prepare(
    "SELECT DISTINCT topic_id, block_number FROM exam_attempts
     WHERE user_id = ? AND attempt_kind = 'topic_block' AND passed = 1 AND block_number IS NOT NULL"
);
$blockStmt->execute([$uid]);
foreach ($blockStmt->fetchAll() as $row) {
    $passedBlocksByTopic[(int)$row['topic_id']][] = (int)$row['block_number'];
}

$topicIdsSorted = array_map(fn($t) => (int)$t['id'], $topics);
$unlockedMap = csa_compute_unlocked_topics($topicIdsSorted, $passedTopicIds);

$out = [];
foreach ($topics as $t) {
    $topicId = (int)$t['id'];
    $poolSize = $poolByCategory[$t['category_key']] ?? 0;
    $known = $knownByCategory[$t['category_key']] ?? 0;
    $blocksTotal = csa_compute_block_count($poolSize);
    $pipelineMode = $blocksTotal > 0 ? 'blocks' : 'lab';
    $currentBlockNumber = $blocksTotal > 0
        ? csa_compute_current_block($blocksTotal, $passedBlocksByTopic[$topicId] ?? [])
        : null;
    $out[] = [
        'id' => $topicId,
        'slug' => $t['slug'],
        'name' => $t['name'],
        'sortOrder' => (int)$t['sort_order'],
        'lessonStatus' => $t['lesson_status'],
        'poolSize' => $poolSize,
        'masteryPercent' => $poolSize > 0 ? (int)round($known / $poolSize * 100) : 0,
        'unlocked' => $unlockedMap[$topicId] ?? false,
        'passed' => in_array($topicId, $passedTopicIds, true),
        'pipelineMode' => $pipelineMode,
        'blocksTotal' => $blocksTotal > 0 ? $blocksTotal : null,
        'currentBlockNumber' => $currentBlockNumber,
    ];
}

json_out(['topics' => $out]);
