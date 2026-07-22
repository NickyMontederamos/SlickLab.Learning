<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
$roomId = (int)($_GET['roomId'] ?? 0);
if ($roomId <= 0) {
    json_error('Invalid roomId');
}

$pdo = csa_db();
$stmt = $pdo->prepare('SELECT * FROM battle_beta_rooms WHERE id = ?');
$stmt->execute([$roomId]);
$room = $stmt->fetch();
if (!$room) {
    json_error('Room not found', 404);
}

$stmt = $pdo->prepare(
    'SELECT p.user_id, u.username, p.is_host, p.is_ready, p.score, p.class_key
     FROM battle_beta_participants p JOIN users u ON u.id = p.user_id
     WHERE p.room_id = ? ORDER BY p.joined_at ASC'
);
$stmt->execute([$roomId]);
$participants = array_map(function ($p) {
    return [
        'userId' => (int)$p['user_id'],
        'username' => $p['username'],
        'isHost' => (bool)$p['is_host'],
        'isReady' => (bool)$p['is_ready'],
        'score' => (int)$p['score'],
        'classKey' => $p['class_key'],
    ];
}, $stmt->fetchAll());

$me = null;
foreach ($participants as $p) {
    if ($p['userId'] === $uid) {
        $me = $p;
        break;
    }
}
if (!$me) {
    json_error('You are not a participant in this room', 403);
}

json_out([
    'room' => [
        'id' => (int)$room['id'],
        'code' => $room['code'],
        'itemCount' => (int)$room['item_count'],
        'winningScore' => $room['winning_score'] !== null ? (int)$room['winning_score'] : null,
        'status' => $room['status'],
    ],
    'participants' => $participants,
    'isHost' => $me['isHost'],
    'meReady' => $me['isReady'],
    'myClassKey' => $me['classKey'],
]);
