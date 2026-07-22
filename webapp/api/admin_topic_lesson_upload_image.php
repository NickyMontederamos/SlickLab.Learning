<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/upload_validation.php';

// The first multipart/form-data endpoint in this app -- deliberately does
// NOT go through json_input() (that reads php://input as JSON, which is
// empty for a multipart request; the file arrives in $_FILES, other fields
// in $_POST instead).

$uid = require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$topicId = (int)($_POST['topicId'] ?? 0);
if ($topicId <= 0) {
    json_error('Invalid topicId');
}

$pdo = csa_db();
$checkStmt = $pdo->prepare('SELECT id FROM topics WHERE id = ?');
$checkStmt->execute([$topicId]);
if (!$checkStmt->fetch()) {
    json_error('Topic not found', 404);
}

$uploadConfig = csa_config()['uploads'];
$file = $_FILES['image'] ?? [];

$validation = csa_validate_upload($file, $uploadConfig['allowed_ext'], (int)$uploadConfig['max_bytes']);
if (!$validation['ok']) {
    json_error($validation['error']);
}

// Extension-allow-list is only step one -- confirm the actual file bytes
// really are an image of an allowed type before trusting it, since a
// client can freely lie about both the filename extension and the
// Content-Type header.
$allowedMimes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$realMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!isset($allowedMimes[$ext]) || $realMime !== $allowedMimes[$ext]) {
    json_error('File content does not match an allowed image type');
}

$topicDir = __DIR__ . "/../assets/uploads/topics/{$topicId}";
if (!is_dir($topicDir) && !mkdir($topicDir, 0755, true) && !is_dir($topicDir)) {
    json_error('Could not create upload directory', 500);
}

// Original filename is discarded beyond its extension -- randomized name
// avoids path-traversal tricks and filename collisions.
$randomName = bin2hex(random_bytes(8)) . '.' . $ext;
$destPath = "{$topicDir}/{$randomName}";
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    json_error('Could not save uploaded file', 500);
}

$relativePath = "uploads/topics/{$topicId}/{$randomName}";
$altText = trim((string)($_POST['altText'] ?? ''));
$stmt = $pdo->prepare(
    'INSERT INTO topic_lesson_images (topic_id, file_path, alt_text, uploaded_by) VALUES (?, ?, ?, ?)'
);
$stmt->execute([$topicId, $relativePath, $altText !== '' ? $altText : null, $uid]);

json_out([
    'id' => (int)$pdo->lastInsertId(),
    'url' => 'assets/' . $relativePath,
    'altText' => $altText,
]);
