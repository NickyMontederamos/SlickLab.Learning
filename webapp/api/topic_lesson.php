<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/topic_quiz.php';

$uid = require_login();
$topicId = (int)($_GET['topicId'] ?? 0);
if ($topicId <= 0) {
    json_error('Invalid topicId');
}

$pdo = csa_db();

$stmt = $pdo->prepare('SELECT id, name, category_key, lesson_body_md, lesson_status FROM topics WHERE id = ?');
$stmt->execute([$topicId]);
$topic = $stmt->fetch();
if (!$topic) {
    json_error('Topic not found', 404);
}

$imgStmt = $pdo->prepare('SELECT id, file_path, alt_text FROM topic_lesson_images WHERE topic_id = ? ORDER BY sort_order, id');
$imgStmt->execute([$topicId]);
$images = array_map(
    fn($r) => [
        'id' => (int)$r['id'],
        'url' => 'assets/' . $r['file_path'],
        'altText' => $r['alt_text'],
    ],
    $imgStmt->fetchAll()
);

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE category = ?');
$countStmt->execute([$topic['category_key']]);
$blocksTotal = csa_compute_block_count((int)$countStmt->fetchColumn());
$pipelineMode = $blocksTotal > 0 ? 'blocks' : 'lab';

$contentStmt = $pdo->prepare(
    'SELECT block_number, content_type, body_md, status FROM topic_block_content WHERE topic_id = ? ORDER BY block_number, content_type'
);
$contentStmt->execute([$topicId]);
$blockContent = array_map(
    fn($r) => [
        'blockNumber' => (int)$r['block_number'],
        'contentType' => $r['content_type'],
        'bodyMd' => $r['body_md'],
        'status' => $r['status'],
    ],
    $contentStmt->fetchAll()
);

json_out([
    'topicId' => (int)$topic['id'],
    'name' => $topic['name'],
    'lessonBodyMd' => $topic['lesson_body_md'],
    'lessonStatus' => $topic['lesson_status'],
    'images' => $images,
    'pipelineMode' => $pipelineMode,
    'blocksTotal' => $blocksTotal > 0 ? $blocksTotal : null,
    'blockContent' => $blockContent,
]);
