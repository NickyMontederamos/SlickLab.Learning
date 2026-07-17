<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$examDate = trim($in['examDate'] ?? '');

$pdo = csa_db();

if ($examDate === '') {
    $pdo->prepare('UPDATE users SET exam_date = NULL WHERE id = ?')->execute([$uid]);
    json_out(['ok' => true, 'examDate' => null]);
}

$d = DateTime::createFromFormat('Y-m-d', $examDate);
if (!$d || $d->format('Y-m-d') !== $examDate) {
    json_error('Invalid date format, expected YYYY-MM-DD');
}

$pdo->prepare('UPDATE users SET exam_date = ? WHERE id = ?')->execute([$examDate, $uid]);

json_out(['ok' => true, 'examDate' => $examDate]);
