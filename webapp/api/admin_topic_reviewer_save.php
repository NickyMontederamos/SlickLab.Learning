<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$topicId = (int)($in['topicId'] ?? 0);
$reviewerMd = (string)($in['reviewerMd'] ?? '');
$reviewerStatus = (string)($in['reviewerStatus'] ?? 'draft');

if ($topicId <= 0) {
    json_error('Invalid topicId');
}
if (!in_array($reviewerStatus, ['placeholder', 'draft', 'published'], true)) {
    json_error('Invalid reviewerStatus');
}

$pdo = csa_db();

$checkStmt = $pdo->prepare('SELECT id FROM topics WHERE id = ?');
$checkStmt->execute([$topicId]);
if (!$checkStmt->fetch()) {
    json_error('Topic not found', 404);
}

$stmt = $pdo->prepare(
    'UPDATE topics SET reviewer_md = ?, reviewer_status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?'
);
$stmt->execute([$reviewerMd, $reviewerStatus, $uid, $topicId]);

json_out(['topicId' => $topicId, 'reviewerStatus' => $reviewerStatus]);
