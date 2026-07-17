<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$current = (string)($in['currentPassword'] ?? '');
$newPassword = (string)($in['newPassword'] ?? '');

if (strlen($newPassword) < 6) {
    json_error('New password must be at least 6 characters');
}

$pdo = csa_db();
$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([$uid]);
$row = $stmt->fetch();

if (!$row || !password_verify($current, $row['password_hash'])) {
    json_error('Current password is incorrect', 401);
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $uid]);

json_out(['ok' => true]);
