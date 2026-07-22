<?php
require __DIR__ . '/../config/bootstrap.php';

require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$imageId = (int)($in['imageId'] ?? 0);
if ($imageId <= 0) {
    json_error('Invalid imageId');
}

$pdo = csa_db();

$stmt = $pdo->prepare('SELECT file_path FROM topic_lesson_images WHERE id = ?');
$stmt->execute([$imageId]);
$filePath = $stmt->fetchColumn();
if ($filePath === false) {
    json_error('Image not found', 404);
}

$pdo->prepare('DELETE FROM topic_lesson_images WHERE id = ?')->execute([$imageId]);

// Best-effort file cleanup -- the DB row is the source of truth, so a
// leftover orphaned file on disk (e.g. delete failed due to a permissions
// quirk) is not treated as a request failure.
$fullPath = __DIR__ . '/../assets/' . $filePath;
if (is_file($fullPath)) {
    @unlink($fullPath);
}

json_out(['deleted' => true]);
