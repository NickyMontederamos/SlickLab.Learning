<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$code = strtoupper(trim($in['code'] ?? ''));
$roomIdIn = !empty($in['roomId']) ? (int)$in['roomId'] : null;
if ($code === '' && !$roomIdIn) {
    json_error('Enter a room code');
}

$pdo = csa_db();
if ($roomIdIn) {
    $stmt = $pdo->prepare("SELECT id, status FROM battle_rooms WHERE id = ?");
    $stmt->execute([$roomIdIn]);
} else {
    $stmt = $pdo->prepare("SELECT id, status FROM battle_rooms WHERE code = ?");
    $stmt->execute([$code]);
}
$room = $stmt->fetch();

if (!$room) {
    json_error('Room not found', 404);
}
if ($room['status'] !== 'waiting') {
    json_error('This battle has already started or finished', 409);
}

$roomId = (int)$room['id'];

$stmt = $pdo->prepare('SELECT id FROM battle_participants WHERE room_id = ? AND user_id = ?');
$stmt->execute([$roomId, $uid]);
if ($stmt->fetch()) {
    json_out(['roomId' => $roomId]); // already joined, just resume
}

$maxParticipants = csa_config()['battle']['max_participants'];
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM battle_participants WHERE room_id = ?');
$countStmt->execute([$roomId]);
if ((int)$countStmt->fetchColumn() >= $maxParticipants) {
    json_error('This room is full', 409);
}

$pdo->prepare('INSERT INTO battle_participants (room_id, user_id, is_host, is_ready) VALUES (?, ?, 0, 0)')
    ->execute([$roomId, $uid]);

json_out(['roomId' => $roomId]);
