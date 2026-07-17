<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$roomId = (int)($in['roomId'] ?? 0);
$ready = !empty($in['ready']);

if ($roomId <= 0) {
    json_error('Invalid roomId');
}

$pdo = csa_db();

$check = $pdo->prepare('SELECT id FROM battle_participants WHERE room_id = ? AND user_id = ?');
$check->execute([$roomId, $uid]);
if (!$check->fetch()) {
    json_error('You are not a participant in this room', 403);
}

$pdo->prepare('UPDATE battle_participants SET is_ready = ? WHERE room_id = ? AND user_id = ?')
    ->execute([$ready ? 1 : 0, $roomId, $uid]);

json_out(['ok' => true]);
