<?php
require __DIR__ . '/../config/bootstrap.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
// No question-count limit in this beta -- item_count is stored only because
// the column mirrors the classic table's schema; battle_beta_start.php
// builds its own large repeating question pool and never reads this value.
// The battle's only end condition is winningScore.
$itemCount = 0;
$winningScore = isset($in['winningScore']) && $in['winningScore'] !== '' && $in['winningScore'] !== null
    ? max(1, min(1000, (int)$in['winningScore']))
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
