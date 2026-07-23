<?php
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../lib/exhibition_exam.php';

$uid = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$in = json_input();
$topicIds = array_values(array_unique(array_map('intval', $in['topicIds'] ?? [])));
if (count($topicIds) < 2) {
    json_error('Pick at least 2 topics as candidates');
}

$pdo = csa_db();

$unlockedMap = csa_exhibition_unlocked_topic_ids($pdo, $uid);
foreach ($topicIds as $tid) {
    if (empty($unlockedMap[$tid])) {
        json_error('You can only pick topics you have unlocked yourself', 403);
    }
}

// Mirrors battle_create.php's battle_generate_code() -- same alphabet
// (no 0/O/1/I/L) and collision-retry loop, scoped to exhibition_sessions.
function exhibition_generate_code(PDO $pdo): string
{
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $pdo->prepare('SELECT id FROM exhibition_sessions WHERE code = ?');
        $stmt->execute([$code]);
        if (!$stmt->fetch()) {
            return $code;
        }
    }
    throw new RuntimeException('Could not generate a unique session code');
}

$code = exhibition_generate_code($pdo);

$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT INTO exhibition_sessions (code, host_user_id, status) VALUES (?, ?, 'waiting')");
$stmt->execute([$code, $uid]);
$sessionId = (int)$pdo->lastInsertId();

// The host's chosen candidates are recorded as their own initial votes --
// this is the only place "which topics can this session be voted on" is
// stored (see migration_17.sql for why there's no separate candidates
// column). Votes are add-only (no un-voting), specifically so this
// derived candidate set can never shrink after creation.
$voteStmt = $pdo->prepare('INSERT INTO exhibition_votes (session_id, user_id, topic_id) VALUES (?, ?, ?)');
foreach ($topicIds as $tid) {
    $voteStmt->execute([$sessionId, $uid, $tid]);
}
$pdo->commit();

json_out(['sessionId' => $sessionId, 'code' => $code]);
