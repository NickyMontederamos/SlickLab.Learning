<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$roomId = (int)($in['roomId'] ?? 0);
$emoji = (string)($in['emoji'] ?? '');
$allowedEmoji = ['👍', '😂', '🔥', '😱', '👏', '😢'];
if ($roomId <= 0 || !in_array($emoji, $allowedEmoji, true)) {
    json_error('Invalid reaction');
}

$pdo = csa_db();
$check = $pdo->prepare("SELECT id FROM battle_beta_participants WHERE room_id = ? AND user_id = ? AND status = 'active'");
$check->execute([$roomId, $uid]);
if (!$check->fetch()) {
    json_error('You are not an active participant in this room', 403);
}

$pdo->prepare('INSERT INTO battle_beta_reactions (room_id, user_id, emoji) VALUES (?, ?, ?)')
    ->execute([$roomId, $uid, $emoji]);

// Reacting is proof of life too, same as answering — don't let a burst of
// reactions with no state poll in between get the player flagged as disconnected.
$pdo->prepare("UPDATE battle_beta_participants SET last_seen_at = NOW() WHERE room_id = ? AND user_id = ? AND status = 'active'")
    ->execute([$roomId, $uid]);

json_out(['ok' => true]);
