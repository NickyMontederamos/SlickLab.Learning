<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$topicId = (int)($in['topicId'] ?? 0);
$blockNumber = (int)($in['blockNumber'] ?? 0);
$contentType = (string)($in['contentType'] ?? 'review');
$bodyMd = (string)($in['bodyMd'] ?? '');
$status = (string)($in['status'] ?? 'draft');

if ($topicId <= 0) {
    json_error('Invalid topicId');
}
if ($blockNumber < 0) {
    json_error('Invalid blockNumber');
}
if (!in_array($contentType, ['review', 'lab_instructions', 'lab_checklist'], true)) {
    json_error('Invalid contentType');
}
if (!in_array($status, ['placeholder', 'draft', 'published'], true)) {
    json_error('Invalid status');
}

$pdo = csa_db();

$checkStmt = $pdo->prepare('SELECT id FROM topics WHERE id = ?');
$checkStmt->execute([$topicId]);
if (!$checkStmt->fetch()) {
    json_error('Topic not found', 404);
}

$stmt = $pdo->prepare(
    'INSERT INTO topic_block_content (topic_id, block_number, content_type, body_md, status, updated_by, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE body_md = VALUES(body_md), status = VALUES(status), updated_by = VALUES(updated_by), updated_at = NOW()'
);
$stmt->execute([$topicId, $blockNumber, $contentType, $bodyMd, $status, $uid]);

json_out(['topicId' => $topicId, 'blockNumber' => $blockNumber, 'contentType' => $contentType, 'status' => $status]);
