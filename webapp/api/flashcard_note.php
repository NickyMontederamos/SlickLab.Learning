<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$questionId = (int)($in['questionId'] ?? 0);
$note = trim((string)($in['note'] ?? ''));
if ($questionId <= 0) {
    json_error('Invalid questionId');
}
if (strlen($note) > 2000) {
    json_error('Note is too long (2000 characters max)');
}

$pdo = csa_db();
// A note can be saved on a card the user has never formally reviewed yet, so
// this upserts a row with default scheduling fields rather than requiring
// flashcard_progress.php to have run first.
$stmt = $pdo->prepare(
    'INSERT INTO flashcard_progress (user_id, question_id, status, box, note)
     VALUES (:uid, :qid, "unseen", 0, :note)
     ON DUPLICATE KEY UPDATE note = VALUES(note)'
);
$stmt->execute([':uid' => $uid, ':qid' => $questionId, ':note' => $note === '' ? null : $note]);

json_out(['ok' => true]);
