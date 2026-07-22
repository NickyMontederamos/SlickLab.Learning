<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$roomId = (int)($in['roomId'] ?? 0);
$classKey = (string)($in['classKey'] ?? '');
// Only these two are playable this beta -- the other 5 are locked/"Coming Soon"
// in the UI and were never wired up server-side, so there's nothing to check
// them against here; an unrecognized key is simply rejected.
if ($roomId <= 0 || !in_array($classKey, ['speedster', 'saboteur'], true)) {
    json_error('Invalid class selection');
}

$pdo = csa_db();
$stmt = $pdo->prepare('SELECT status FROM battle_beta_rooms WHERE id = ?');
$stmt->execute([$roomId]);
$room = $stmt->fetch();
if (!$room) {
    json_error('Room not found', 404);
}
if ($room['status'] !== 'waiting') {
    json_error('Cannot change class after the battle has started', 409);
}

$check = $pdo->prepare('SELECT id FROM battle_beta_participants WHERE room_id = ? AND user_id = ?');
$check->execute([$roomId, $uid]);
if (!$check->fetch()) {
    json_error('You are not a participant in this room', 403);
}

$pdo->prepare('UPDATE battle_beta_participants SET class_key = ? WHERE room_id = ? AND user_id = ?')
    ->execute([$classKey, $roomId, $uid]);

json_out(['ok' => true, 'classKey' => $classKey]);
