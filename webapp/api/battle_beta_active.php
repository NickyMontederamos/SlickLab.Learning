<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
$pdo = csa_db();

$stmt = $pdo->prepare(
    "SELECT r.id, r.code, r.status FROM battle_beta_rooms r
     JOIN battle_beta_participants p ON p.room_id = r.id
     WHERE p.user_id = ? AND p.status = 'active' AND r.status IN ('waiting', 'in_progress', 'paused')
     ORDER BY r.id DESC LIMIT 1"
);
$stmt->execute([$uid]);
$room = $stmt->fetch();

if (!$room) {
    json_out(['active' => false]);
}

json_out(['active' => true, 'roomId' => (int)$room['id'], 'code' => $room['code'], 'status' => $room['status']]);
