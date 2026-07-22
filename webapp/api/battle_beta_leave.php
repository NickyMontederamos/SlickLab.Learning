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
$stmt = $pdo->prepare("SELECT status, host_user_id FROM battle_beta_rooms WHERE id = ?");
$stmt->execute([$roomId]);
$room = $stmt->fetch();
if (!$room) {
    json_out(['ok' => true]); // already gone
}
if ($room['status'] !== 'waiting') {
    json_error('Cannot leave a battle that has already started', 409);
}

$pdo->prepare('DELETE FROM battle_beta_participants WHERE room_id = ? AND user_id = ?')->execute([$roomId, $uid]);

// If the host left, disband the room entirely (deletes participants via cascade) rather than
// leaving an orphaned room with no host.
if ((int)$room['host_user_id'] === $uid) {
    $pdo->prepare('DELETE FROM battle_beta_rooms WHERE id = ?')->execute([$roomId]);
}

json_out(['ok' => true]);
