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
$stmt = $pdo->prepare('SELECT * FROM battle_rooms WHERE id = ?');
$stmt->execute([$roomId]);
$room = $stmt->fetch();
if (!$room) {
    json_error('Room not found', 404);
}
if (!in_array($room['status'], ['in_progress', 'paused'], true)) {
    json_error('Nothing to quit — this battle is not currently active', 409);
}

$check = $pdo->prepare("SELECT id FROM battle_participants WHERE room_id = ? AND user_id = ? AND status = 'active'");
$check->execute([$roomId, $uid]);
if (!$check->fetch()) {
    json_error('You are not an active participant in this room', 403);
}

$pdo->prepare("UPDATE battle_participants SET status = 'left' WHERE room_id = ? AND user_id = ?")
    ->execute([$roomId, $uid]);

$isHost = (int)$room['host_user_id'] === $uid;

if ($isHost) {
    // No one left with kick/end authority once the host quits — finish the battle now with current scores.
    $pdo->prepare("UPDATE battle_rooms SET status = 'finished', finished_at = NOW() WHERE id = ?")
        ->execute([$roomId]);
} elseif ((int)($room['disconnected_user_id'] ?? 0) === $uid && $room['status'] === 'paused') {
    // The quitting player was the one blocking the battle — resume immediately.
    $pauseSeconds = time() - strtotime($room['paused_at']);
    $pdo->prepare(
        "UPDATE battle_rooms SET status = 'in_progress',
         question_started_at = DATE_ADD(question_started_at, INTERVAL ? SECOND),
         paused_at = NULL, disconnected_user_id = NULL
         WHERE id = ? AND status = 'paused'"
    )->execute([$pauseSeconds, $roomId]);
}

json_out(['ok' => true]);
