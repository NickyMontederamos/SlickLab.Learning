<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$itemCount = (int)($in['itemCount'] ?? 0);
if (!in_array($itemCount, [10, 20, 30], true)) {
    $itemCount = 10;
}
// Scoring is point-based (up to 20/question with speed + difficulty weighting, plus class
// modifiers), so the cap here is a generous upper bound, not the old "1 point per question" cap.
$winningScore = isset($in['winningScore']) && $in['winningScore'] !== '' && $in['winningScore'] !== null
    ? max(1, min($itemCount * 25, (int)$in['winningScore']))
    : 100; // Spellfire Beta defaults to the "Race to 100" mode this beta is built around
$ttsEnabled = !empty($in['ttsEnabled']) ? 1 : 0;

$pdo = csa_db();

function battle_beta_generate_code(PDO $pdo): string
{
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no 0/O/1/I/L to avoid ambiguity
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $pdo->prepare('SELECT id FROM battle_beta_rooms WHERE code = ?');
        $stmt->execute([$code]);
        if (!$stmt->fetch()) {
            return $code;
        }
    }
    throw new RuntimeException('Could not generate a unique room code');
}

$code = battle_beta_generate_code($pdo);

$pdo->beginTransaction();
$stmt = $pdo->prepare(
    'INSERT INTO battle_beta_rooms (code, host_user_id, item_count, winning_score, tts_enabled, status)
     VALUES (?, ?, ?, ?, ?, "waiting")'
);
$stmt->execute([$code, $uid, $itemCount, $winningScore, $ttsEnabled]);
$roomId = (int)$pdo->lastInsertId();

$pdo->prepare('INSERT INTO battle_beta_participants (room_id, user_id, is_host, is_ready) VALUES (?, ?, 1, 0)')
    ->execute([$roomId, $uid]);
$pdo->commit();

json_out(['roomId' => $roomId, 'code' => $code]);
