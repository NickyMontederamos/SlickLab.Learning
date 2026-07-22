<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$roomId = (int)($in['roomId'] ?? 0);
if ($roomId <= 0) {
    json_error('Invalid roomId');
}

$pdo = csa_db();
$stmt = $pdo->prepare('SELECT host_user_id, status FROM battle_beta_rooms WHERE id = ?');
$stmt->execute([$roomId]);
$room = $stmt->fetch();
if (!$room) {
    json_error('Room not found', 404);
}
if ((int)$room['host_user_id'] !== $uid) {
    json_error('Only the host can end the battle', 403);
}
if (!in_array($room['status'], ['in_progress', 'paused'], true)) {
    json_out(['ok' => true]); // already finished or not started; idempotent
}

$pdo->prepare("UPDATE battle_beta_rooms SET status = 'finished', finished_at = NOW() WHERE id = ?")
    ->execute([$roomId]);

json_out(['ok' => true]);
