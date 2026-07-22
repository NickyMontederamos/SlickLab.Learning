<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
$topicId = (int)($_GET['topicId'] ?? 0);
if ($topicId <= 0) {
    json_error('Invalid topicId');
}

$pdo = csa_db();

$stmt = $pdo->prepare('SELECT id, name, lesson_body_md, lesson_status FROM topics WHERE id = ?');
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

json_out([
    'topicId' => (int)$topic['id'],
    'name' => $topic['name'],
    'lessonBodyMd' => $topic['lesson_body_md'],
    'lessonStatus' => $topic['lesson_status'],
    'images' => $images,
]);
