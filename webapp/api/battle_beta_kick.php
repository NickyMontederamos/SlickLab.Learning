<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$roomId = (int)($in['roomId'] ?? 0);
$targetUserId = (int)($in['userId'] ?? 0);
if ($roomId <= 0 || $targetUserId <= 0) {
    json_error('Invalid request');
}

$pdo = csa_db();
$stmt = $pdo->prepare('SELECT * FROM battle_beta_rooms WHERE id = ?');
$stmt->execute([$roomId]);
$room = $stmt->fetch();
if (!$room) {
    json_error('Room not found', 404);
}
if ((int)$room['host_user_id'] !== $uid) {
    json_error('Only the host can kick a player', 403);
}
if (!in_array($room['status'], ['in_progress', 'paused'], true)) {
    json_error('Can only kick a player during an active battle', 409);
}
if ($targetUserId === $uid) {
    json_error('The host cannot kick themselves');
}

$pdo->prepare("UPDATE battle_beta_participants SET status = 'kicked' WHERE room_id = ? AND user_id = ?")
    ->execute([$roomId, $targetUserId]);

// If this player was the one blocking the battle, resume play immediately.
if ((int)($room['disconnected_user_id'] ?? 0) === $targetUserId && $room['status'] === 'paused') {
    $pauseSeconds = time() - strtotime($room['paused_at']);
    $pdo->prepare(
        "UPDATE battle_beta_rooms SET status = 'in_progress',
         question_started_at = DATE_ADD(question_started_at, INTERVAL ? SECOND),
         paused_at = NULL, disconnected_user_id = NULL
         WHERE id = ? AND status = 'paused'"
    )->execute([$pauseSeconds, $roomId]);
}

json_out(['ok' => true]);
