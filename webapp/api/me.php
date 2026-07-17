<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = current_user_id();
if ($uid === null) {
    json_out(['authenticated' => false]);
}

$pdo = csa_db();
$stmt = $pdo->prepare('SELECT username, exam_date FROM users WHERE id = ?');
$stmt->execute([$uid]);
$row = $stmt->fetch();

json_out([
    'authenticated' => true,
    'id' => $uid,
    'username' => $row['username'] ?? ($_SESSION['username'] ?? ''),
    'examDate' => $row['exam_date'] ?? null,
]);
